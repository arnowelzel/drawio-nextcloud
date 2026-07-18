# CLAUDE.md - drawio-nextcloud

## Project Overview

Nextcloud app that integrates the draw.io (diagrams.net) diagram editor. Users can create and edit `.drawio` diagrams and `.dwb` whiteboards directly within Nextcloud. The draw.io editor runs in an iframe and communicates with the Nextcloud backend via postMessage.

- **App ID:** `drawio`
- **Namespace:** `OCA\Drawio`
- **License:** AGPL
- **Nextcloud compatibility:** 33–34 (min-version and max-version in `appinfo/info.xml`), PHP 8.2–8.5
- **Version:** defined in both `appinfo/info.xml` and `package.json` (keep in sync)

## Repository Structure

```
appinfo/           App manifest (info.xml) and route definitions (routes.php)
lib/               PHP backend
  AppConfig.php    Configuration manager (get/set for all admin settings, backed by OCP IAppConfig)
  AppInfo/         Application bootstrap, DI registration, runtime MIME type registration
  Controller/      EditorController (file CRUD, revisions) & SettingsController
  Listeners/       Event handlers (file delete cleanup, reference widget loader, template creator, files scripts)
  Reference/       Reference Provider for inline diagram previews in Text/Collectives/Talk
  Migration/       MIME type registration/unregistration repair steps
  Preview/         Thumbnail generation from cached PNG previews
  Service/         PublicShareAuth (public share password session check)
  Settings/        Admin settings panel registration
src/               JavaScript source (webpack entry points)
  editor.js        Editor page – iframe communication, save/load, autosave, previews
  main.js          File list integration – file actions, public share auto-open
  settings.js      Admin settings form
  reference.js     Reference widget registration for inline diagram previews
  components/      Vue components (DrawioReferenceWidget.vue)
js/                Compiled webpack output (do not edit directly)
templates/         PHP templates for editor and settings pages
css/               Stylesheets (editor, settings)
img/               SVG icons (app, app-dark, drawio file type, whiteboard)
l10n/              Translations (~100 languages, managed in-repo)
scripts/           Build/maintenance scripts (extract-strings.js, dev-setup.sh, dev-rebuild.sh)
tests/             PHPUnit unit tests (tests/unit), end-to-end tests (tests/e2e), harness (tests/support) and psalm stubs (tests/stubs)
.github/workflows/ CI: lint/psalm/phpunit/e2e/build (ci.yml), release pipeline (release.yml), stale bot (stale.yml)
```

## Build & Development

### Prerequisites
- Node.js 20+
- npm
- PHP 8.2+ and composer (for backend tooling; can be run via Docker)

### Commands
```bash
npm ci                  # Install dependencies (use ci, not install)
npm run build           # Production build (webpack, output to js/)
npm run dev             # Development build
npm run watch           # Development build with file watching
npm run extract-strings # Extract translatable strings to l10n/source-strings.json

composer update         # Install PHP dev tooling (nextcloud/ocp, psalm, phpunit, guzzle)
composer run lint       # php -l over lib/, appinfo/, templates/, tests/
composer run psalm      # Static analysis against the nextcloud/ocp API
composer run test:unit  # PHPUnit unit tests (tests/unit)
composer run test:e2e   # PHPUnit end-to-end tests (tests/e2e) against a running instance
```
To check against Nextcloud 34 instead of 33: `composer require --dev nextcloud/ocp:dev-stable34` then `composer run psalm` (CI runs both).

info.xml must validate against the app store schema (CI enforces this; element order matters):
```bash
curl -sSLo /tmp/info.xsd https://apps.nextcloud.com/schema/apps/info.xsd
xmllint --noout --schema /tmp/info.xsd appinfo/info.xml
```

### Test suite layout
- `tests/unit/` — PHPUnit tests, one test class per production class. Covers EditorController (load/save/create/index/versions incl. locking, ETag conflicts and share permissions), SettingsController, AppConfig, PublicShareAuth, DrawioReferenceProvider, DrawioPreview, all listeners, both MIME repair steps, the Settings classes and appinfo/info.xml invariants (version sync with package.json, repair-step element names).
- `tests/e2e/` — end-to-end tests over HTTP against a **running** Nextcloud (start with `./scripts/dev-setup.sh`; the whole suite skips if the instance is unreachable). Configuration via `NEXTCLOUD_E2E_BASE_URL` (default `http://localhost:8088`), `NEXTCLOUD_E2E_ADMIN_USER`/`NEXTCLOUD_E2E_ADMIN_PASSWORD` (default admin/admin). Covers WebDAV MIME detection, load/save round trips with ETag conflicts, previews, versions, the editor page CSP, the full anonymous password-share flow (deny → wrong password → authenticate → read → editable-share write) and the settings/whiteboards toggle via the templates API. Authenticated calls use cookie-less basic auth + the `OCS-APIRequest: true` header (passes the CSRF check); the anonymous share flow uses a real cookie jar + the `data-requesttoken` scraped from the share page. Admin language is pinned to `en` so message assertions are deterministic. From another container, target `http://host.docker.internal:8088` (a trusted domain in docker-compose.yml).
- `tests/support/` — runtime shims that make OCP static helpers work without a server: `legacy-oc.php` (minimal `OC`, `OC_Util`, `OC\AppScriptDependency`, `OC\Hooks\Emitter`), `FakeServerContainer` (installed as `\OC::$server`), `ResetsGlobalState` trait (resets `\OCP\Util` script registries between tests).
- `tests/doubles` equivalents live in `tests/support/oca-doubles.php` — real (not psalm-only) declarations of `OCA\Files\Event\LoadAdditionalScriptsEvent`, `OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent` and the `OCA\Files_Versions` interfaces, mirroring server stable33.
- `tests/stubs/*.phpstub` — psalm-only stubs; keep them in sync with oca-doubles.php when server APIs change.
- No PHP runtime on this machine? Run everything through Docker: `docker run --rm -v "${PWD}:/app" -w /app composer:2 sh -c "composer update && composer run test:unit"`.

### Local Development (Docker)
See `DEV.md` for full details. Quick start:
```bash
npm ci
./scripts/dev-setup.sh        # builds, starts NC 33 + MariaDB, enables the app
```
Then open http://localhost:8088 (admin / admin). PHP changes are live (volume-mounted); JS changes require `npm run build`.

**Important:** Do not change the app version in `info.xml` during development — it will break the Nextcloud instance.

## Architecture

### Data Flow
1. User clicks a `.drawio`/`.dwb` file → `main.js` registers file actions via `@nextcloud/files`
2. Editor page loads → `editor.js` creates iframe pointing to draw.io (embed.diagrams.net or self-hosted)
3. draw.io ↔ Nextcloud communication via `postMessage` / "remote invoke" protocol
4. Save/load operations go through `EditorController` PHP endpoints
5. PNG previews are generated client-side and saved via `savePreview` endpoint

### Inline Previews (Reference Provider)
When a draw.io editor URL is pasted into Nextcloud Text, Collectives, Talk, Notes, or Deck:
1. `DrawioReferenceProvider` matches the URL and resolves file metadata + preview image
2. `DrawioReferenceListener` loads the `drawio-reference` JS bundle on demand
3. `reference.js` registers a Vue widget (`DrawioReferenceWidget.vue`) for the `drawio_diagram` rich object type
4. The widget renders an inline card with the diagram thumbnail and an "Open in Draw.io" link
5. Smart Picker (`/` menu) lists "Draw.io Diagrams" via `ISearchableReferenceProvider`

### Template Creator
`RegisterTemplateCreatorListener` registers `.drawio` and `.dwb` as file types in the Nextcloud "+" new file menu via `RegisterTemplateCreatorEvent`. The `.dwb` whiteboard creator is only registered when the "Enable whiteboards?" admin setting (`AppConfig::GetWhiteboards`) is `yes`. This also enables creating diagrams as Text document attachments (stored in `.attachments.{docId}/` folders). The editor detects attachment paths on close and redirects to the parent document.

### API Routes (all under `/apps/drawio/`)
| Method | URL                    | Controller Method        |
|--------|------------------------|--------------------------|
| GET    | `/edit`                | `EditorController@index` |
| GET    | `/ajax/load`           | `EditorController@load`  |
| GET    | `/ajax/getFileInfo`    | `EditorController@getFileInfo` |
| GET    | `/ajax/getFileRevisions` | `EditorController@getFileRevisions` |
| GET    | `/ajax/loadFileVersion` | `EditorController@loadFileVersion` |
| POST   | `/ajax/new`            | `EditorController@create` |
| PUT    | `/ajax/save`           | `EditorController@save`  |
| POST   | `/ajax/savePreview`    | `EditorController@savePreview` |
| POST   | `/ajax/settings`       | `SettingsController@settings` |

### Key Patterns
- **Concurrency:** ETags for optimistic conflict detection; ILockingProvider for file locking
- **Sharing:** Supports both authenticated users and public share tokens (separate code paths). Password-protected shares are checked by `Service\PublicShareAuth` against the `public_link_authenticated_frontend` session map (`PublicShareController::DAV_AUTHENTICATED_FRONTEND`, format introduced in NC 33)
- **Configuration:** All admin settings stored via `OCP\AppFramework\Services\IAppConfig` (`AppConfig.php`); `OCP\IConfig` app-value methods are deprecated and must not be used
- **Security attributes:** Controller access control uses PHP attributes (`#[NoAdminRequired]`, `#[PublicPage]`, `#[NoCSRFRequired]`, `#[AuthorizedAdminSetting]`) — the old annotation syntax is deprecated
- **Script loading:** `main.js` is loaded only in the Files app (`OCA\Files\Event\LoadAdditionalScriptsEvent`) and on public share pages (`OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent`) via `FilesScriptsListener`; editor/settings assets are added with `Util::addScript`/`Util::addStyle` in the controllers (the template `script()`/`style()` helpers are deprecated)
- **CSP:** The editor page allows the draw.io origin for `script-src`, `frame-src` and `worker-src` (+`blob:`). `child-src` was removed from the CSP API in NC 34 — do not reintroduce it
- **MIME types:** `application/x-drawio` (.drawio) and `application/x-drawio-wb` (.dwb), registered in repair steps
- **Translations:** Use `t('drawio', 'key')` in JS (`@nextcloud/l10n`), `$l->t('key')` in PHP templates, and `$this->trans->t('key')` in PHP controllers
- **Frontend globals:** `OCA.DrawIO` namespace used in `main.js`; `editor.js` uses an IIFE with `OCA` parameter
- **Reference Provider:** `DrawioReferenceProvider` extends `ADiscoverableReferenceProvider` and implements `ISearchableReferenceProvider`; rich object type is `drawio_diagram`
- **Template Creator:** `.drawio`/`.dwb` registered via `RegisterTemplateCreatorEvent` + `TemplateFileCreator`

## MIME Type Registration (Critical for Create/Edit Flow)

The create/edit flow — creating a `.drawio`/`.dwb` file from the "+" menu, then clicking it to open in the draw.io editor — depends on the following MIME type registration steps working together. **Removing or skipping any of these breaks the basic flow** (files download instead of opening in the editor):

1. **Config files** (`RegisterMimeType::registerForNewFiles`)
   - Writes to `config/mimetypemapping.json` (extension → MIME type)
   - Writes to `config/mimetypealiases.json` (MIME type → icon alias)

2. **Filecache update** (`RegisterMimeType::registerForExistingFiles`)
   - Updates DB filecache so existing `.drawio`/`.dwb` files have the correct MIME type

3. **Runtime registration** (`Application::boot`)
   - `$detector->registerType("drawio", "application/x-drawio")` / `registerType("dwb", ...)`
   - Backup for setups where the config directory is not writable; relies on the private
     `OC\Files\Type\Detection` class (guarded with `method_exists`) because there is no OCP
     API for this (https://github.com/nextcloud/server/issues/10131)
   - `getAllMappings()` must be called before `registerType()` — it forces the detector to load
     the default mappings, which would otherwise be skipped afterwards

Since 4.3.0 the app intentionally does **not** copy file type icons to `core/img/filetypes/` and does **not** regenerate `core/js/mimetypelist.js` (the old steps 3/4). Modifying core files triggered integrity check warnings (https://github.com/jgraph/drawio-nextcloud/issues/70), so this was dropped; `.drawio`/`.dwb` files show a generic file icon and admins can follow the FAQ (linked from the admin settings) to add icons manually. Do not reintroduce core file modifications.

### Testing the Create/Edit Flow
After any change to MIME type registration, always verify:
1. Run `./scripts/dev-setup.sh` for a fresh NC instance
2. Create a new `.drawio` file from the "+" menu → file should appear in the file list
3. Click the file → should open in the draw.io editor (NOT download)
4. Edit, save, and close → changes should persist
5. Re-open the file → saved content should be there

## File Conventions
- PHP follows PSR-2/PSR-12 style
- JavaScript uses ES6+ imports with `@nextcloud/*` packages
- Vue 2 Single File Components (`.vue`) used for reference widget (`src/components/`)
- `@nextcloud/vue` v8 provides `registerWidget` for reference widgets
- No JS linter or PHP formatter is configured
- PHP unit tests live in `tests/unit` (PHPUnit via `composer run test:unit`); psalm (`composer run psalm`) checks the code against the `nextcloud/ocp` API of both supported majors. Bug fixes should come with a regression test that fails before and passes after the fix.
- Repair steps in `appinfo/info.xml` must use the element names the server executes: `install`, `pre-migration`, `post-migration`, `live-migration`, `uninstall` — anything else is parsed but silently never run (this bit us with `post-migrate`); `tests/unit/AppInfoXmlTest.php` and the CI schema check guard this.

## Translations
Managed in-repo. The `l10n/` directory contains `.js` and `.json` files for ~100 languages. These are the runtime format Nextcloud loads directly.

- Run `npm run extract-strings` to regenerate `l10n/source-strings.json` — the canonical list of all English source strings
- The script scans `src/*.js`, `templates/*.php`, and `lib/**/*.php` for translation function calls
- To add/update a translation: edit the corresponding `l10n/{lang}.js` and `l10n/{lang}.json` files directly
- The `.js` format uses `OC.L10N.register("drawio", {...}, "pluralForm")` and the `.json` format uses `{"translations": {...}, "pluralForm": "..."}`

## Release Process
Handled by `.github/workflows/release.yml` on version tags (`v*`):
1. Checkout → npm ci → npm run build
2. Create zip/tar.gz archives (excluding dev files)
3. Upload to GitHub Releases
4. Sign with RSA key and publish to Nextcloud App Store
