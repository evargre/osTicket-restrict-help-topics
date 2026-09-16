<?php
return array(
    'id' => 'user-topics',
    'version' => '1.0.1',
    'ost_version' => '1.18',
    'name' => /* trans */ 'Per-User Help Topics',
    'author' => 'Llobet Regals',
    'description' => /* trans */ 'Restrict which Help Topics each end user can see and select when opening a ticket in the client portal.',
    'plugin' => 'UserTopicsPlugin.php:UserTopicsPlugin',
    'config' => 'config.php:UserTopicsConfig',
    'license' => 'GPLv2',
);
