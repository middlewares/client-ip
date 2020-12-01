# middlewares/client-ip

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
![Testing][ico-ga]
[![Total Downloads][ico-downloads]][link-downloads]

Middleware to detect the client ip and save it as a request attribute.

## Requirements

* PHP >= 7.2
* A [PSR-7 http library](https://github.com/middlewares/awesome-psr15-middlewares#psr-7-implementations)
* A [PSR-15 middleware dispatcher](https://github.com/middlewares/awesome-psr15-middlewares#dispatcher)

## Installation

This package is installable and autoloadable via Composer as [middlewares/client-ip](https://packagist.org/packages/middlewares/client-ip).

```sh
composer require middlewares/client-ip
```

## Usage

```php
$dispatcher = new Dispatcher([
	new Middlewares\ClientIp(),

    function ($request) {
        //Get the client ip
        $ip = $request->getAttribute('client-ip');
    }
]);

$response = $dispatcher->dispatch(new ServerRequest());
```

### proxy

This option configures the detection through proxies. The first argument is an array of ips or cidr of the trusted proxies. If it's empty, no ip filtering is made. The second argument is a list of the headers to inspect. If it's not defined, uses the default value `['Forwarded', 'Forwarded-For', 'Client-Ip', 'X-Forwarded', 'X-Forwarded-For', 'X-Cluster-Client-Ip']`. Disabled by default.

```php
//Use proxies
$middleware = (new Middlewares\ClientIp())->proxy();

//Trust only some proxies by ip
$middleware = (new Middlewares\ClientIp())->proxy(['10.10.10.10', '10.10.10.11']);

//Trust only some proxies by ip using a specific header
$middleware = (new Middlewares\ClientIp())->proxy(['10.10.10.10', '10.10.10.11'], ['X-Forwarded-For']);

// Trust only some proxies by cidr range
// usefull when you have an autoscaled proxy(like haproxy) in a subnet
$middleware = (new Middlewares\ClientIp())->proxy(['192.168.0.0/16', '10.0.0.0/8']);
```

### attribute

By default, the ip is stored in the `client-ip` attribute of the server request. This options allows to modify that:

```php
//Save the ip in the "ip" attribute
$middleware = (new Middlewares\ClientIp())->attribute('ip');
```

---

Please see [CHANGELOG](CHANGELOG.md) for more information about recent changes and [CONTRIBUTING](CONTRIBUTING.md) for contributing details.

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/middlewares/client-ip.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-ga]: https://github.com/middlewares/client-ip/workflows/testing/badge.svg
[ico-downloads]: https://img.shields.io/packagist/dt/middlewares/client-ip.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/middlewares/client-ip
[link-downloads]: https://packagist.org/packages/middlewares/client-ip
