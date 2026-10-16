<?php

$loader = require dirname(__DIR__).'/vendor/autoload.php';
// Allow this isolated checkout to reuse installed dependencies without loading
// App or Tests classes from the checkout that owns vendor/.
$loader->setPsr4('App\\', dirname(__DIR__).'/app');
$loader->setPsr4('Tests\\', __DIR__);
$overrides = [];
foreach ($loader->getClassMap() as $class => $file) {
    foreach (['App\\' => dirname(__DIR__).'/app/', 'Tests\\' => __DIR__.'/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $local = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($local)) $overrides[$class] = $local;
        }
    }
}
$loader->addClassMap($overrides);
