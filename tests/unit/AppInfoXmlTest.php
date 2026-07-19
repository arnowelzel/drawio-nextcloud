<?php

declare(strict_types=1);

namespace OCA\Drawio\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards invariants of appinfo/info.xml that Nextcloud does not validate at
 * install time but silently depends on.
 */
final class AppInfoXmlTest extends TestCase {

    private static function info(): \SimpleXMLElement {
        return simplexml_load_file(__DIR__ . '/../../appinfo/info.xml');
    }

    public function testAppIdentity(): void {
        $info = self::info();

        $this->assertSame('drawio', (string)$info->id);
        $this->assertSame('Drawio', (string)$info->namespace);
        $this->assertSame('agpl', (string)$info->licence);
    }

    public function testVersionIsInSyncWithPackageJson(): void {
        $package = json_decode(file_get_contents(__DIR__ . '/../../package.json'), true);

        $this->assertSame($package['version'], (string)self::info()->version,
            'appinfo/info.xml and package.json must declare the same version');
    }

    public function testDeclaredCompatibilityRange(): void {
        $dependencies = self::info()->dependencies;

        $this->assertSame('33', (string)$dependencies->nextcloud['min-version']);
        $this->assertSame('34', (string)$dependencies->nextcloud['max-version']);
        $this->assertSame('8.2', (string)$dependencies->php['min-version']);
    }

    /**
     * Regression test: the repair-steps element names must be the ones the
     * server executes (OC\App\InfoParser). A step under an unknown name -
     * this app used to ship "post-migrate" - is parsed but never runs, so
     * MIME types were not re-registered on upgrades.
     */
    public function testRepairStepsUseElementNamesTheServerExecutes(): void {
        $known = ['install', 'pre-migration', 'post-migration', 'live-migration', 'uninstall'];

        $steps = self::info()->{'repair-steps'};
        $this->assertNotNull($steps);

        $found = [];
        foreach ($steps->children() as $child) {
            $found[] = $child->getName();
            $this->assertContains($child->getName(), $known,
                'Unknown repair-steps element would be silently ignored by the server');
        }

        $this->assertContains('install', $found);
        $this->assertContains('post-migration', $found);
        $this->assertContains('uninstall', $found);
    }

    public function testRepairStepClassesExist(): void {
        foreach (self::info()->{'repair-steps'}->children() as $group) {
            foreach ($group->step as $step) {
                $this->assertTrue(class_exists((string)$step), "Repair step class $step must exist");
            }
        }
    }

    public function testSettingsClassesExist(): void {
        $settings = self::info()->settings;

        $this->assertTrue(class_exists((string)$settings->admin));
        $this->assertTrue(class_exists((string)$settings->{'admin-section'}));
    }
}
