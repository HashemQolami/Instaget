# Hashem/Instaget

[![Author](http://img.shields.io/badge/author-@HashemQolami-blue.svg?style=flat-square)](https://twitter.com/HashemQolami)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/hashem/instaget.svg?style=flat-square)](https://packagist.org/packages/hashem/instaget)
[![Total Downloads](https://img.shields.io/packagist/dt/hashem/instaget.svg?style=flat-square)](https://packagist.org/packages/hashem/instaget)

Instaget is a simple PHP library to get Instagram photos per user and to store them on the local server for later usage.
Instaget is still in beta, more features will be implemented in the upcoming versions.

## Installation

Instaget is available via Composer:

``` bash
$ composer require hashem/instaget
```

## Usage

``` php
use Hashem\Instaget\Instaget as Instaget;
use League\Flysystem\Filesystem;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\Local as Adapter;

# Our (private) Filesystem
$fs = new Filesystem(new Adapter(__DIR__.'/'), [
    'visibility' => AdapterInterface::VISIBILITY_PRIVATE
]);

$inst = new Instaget($fs, [
	'clientID'  => 'YOUR_CLIENT_ID',
	'username'  => 'USERNAME',
	# Optional parameters:
	'count'     => 6,         // user feed limit
	'imageType' => 'large',   // size of the instagram shots
	'expTime'   => 3600,      // image caching exp. time
	'imagePath' => 'images/', // path to the image directory, with trailing forward-slash
	'dataPath'  => 'data/'    // path to the user's info directory, with trailing forward-slash
]);

# Get/Store the shots!
$shots = $inst->run();
```

# Requirements

Instaget requires PHP 5.4+ and [League\Flysystem](https://github.com/thephpleague/flysystem) filesystem.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email *hashem at qolami dot com* instead of using the issue tracker.

## Credits

- [Hashem Qolami](https://github.com/HashemQolami)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.