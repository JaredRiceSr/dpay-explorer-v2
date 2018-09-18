<?php

use Phalcon\Loader;

$loader = new Loader();

$loader->registerNamespaces([
    'DPayExplorer\Models'      => $config->application->modelsDir,
    'DPayExplorer\Controllers' => $config->application->controllersDir,
    'DPayExplorer\Helpers'     => $config->application->helpersDir,
    'DPayExplorer'             => $config->application->libraryDir
]);

$loader->registerDirs(array(
    '../app/helpers'
));

$loader->register();

// Use composer autoloader to load vendor classes
require_once BASE_PATH . '/vendor/autoload.php';
