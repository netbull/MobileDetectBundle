<?php

if (!@include __DIR__ . './../vendor/autoload.php') {
    die("You must set up the project dependencies, running the following commands:
wget https://getcomposer.org/composer.phar
php composer.phar install
");
}

spl_autoload_register(function($class) {
    if (str_starts_with($class, 'SunCat\MobileDetectBundle\\') &&
        file_exists($file = __DIR__ . '/../' . implode('/', array_slice(explode('\\', $class), 3)) . '.php')
    ) {
        require_once $file;
    }
});
