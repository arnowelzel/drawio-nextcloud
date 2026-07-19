<?php

return [
    "routes" => [
        ["name" => "editor#index", "url" => "/edit", "verb" => "GET"],
        ["name" => "editor#load", "url" => "/ajax/load", "verb" => "GET"],
        ["name" => "editor#getFileInfo", "url" => "/ajax/getFileInfo", "verb" => "GET"],
        ["name" => "editor#getFileRevisions", "url" => "/ajax/getFileRevisions", "verb" => "GET"],
        ["name" => "editor#loadFileVersion", "url" => "/ajax/loadFileVersion", "verb" => "GET"],
        ["name" => "editor#create", "url" => "/ajax/new", "verb" => "POST"],
        ["name" => "editor#save", "url" => "/ajax/save", "verb" => "PUT"],
        ["name" => "editor#savePreview", "url" => "/ajax/savePreview", "verb" => "POST"],

        ["name" => "adminSettings#settings", "url" => "/ajax/adminSettings", "verb" => "POST"],
        ["name" => "personalSettings#settings", "url" => "/ajax/personalSettings", "verb" => "POST"],
    ]
];
