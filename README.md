# It looks like the official repo is abandoned, so as a temporary fix this can be used.

MobileDetectBundle
=============

Symfony 4/5/6 bundle for detect mobile devices, manage mobile view and redirect to the mobile and tablet version.

http://knpbundles.com/

Introduction
------------

This Bundle use [Mobile_Detect](https://github.com/serbanghita/Mobile-Detect) class and provides the following features:

* Detect the various mobile devices by Name, OS, browser User-Agent
* Manages site views for the various mobile devices (`mobile`, `tablet`, `full`)
* Redirects to mobile and tablet sites


Installation
------------

1. Install the latest version via composer:

    ```sh
    composer require netbull/mobile-detect-bundle --dev
    ```

2. If you don't use [Symfony Flex](https://symfony.com/doc/current/setup/flex.html), you must enable the bundle manually in the application:

     ```php
   // config/bundles.php
   // in older Symfony apps, enable the bundle in app/AppKernel.php
   return [
   // ...
    SunCat\MobileDetectBundle\MobileDetectBundle::class => ['dev' => true],
   ];
    ```

## Documentation

The bulk of the documentation is stored in the `Resources/doc/index.md` file in this bundle:

[Read the Documentation for master](https://github.com/netbull/MobileDetectBundle/blob/master/src/Resources/doc/index.md)


## License

This bundle is under the MIT license. See the complete license in the bundle:

    Resources/meta/LICENSE


## Credits

- [suncat2000](https://github.com/suncat2000), [HenriVesala](https://github.com/HenriVesala), [netmikey](https://github.com/netmikey) and [all contributors](https://github.com/suncat2000/MobileDetectBundle/graphs/contributors)
