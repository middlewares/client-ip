# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [0.4.0] - 2017-04-13

### Changed

* Removed the `headers()` option and replaced by `proxy()` that accept two arguments: the ip of the trust proxies and the headers used
* The proxy headers have priority over `REMOTE_ADDR` variable, but they are disabled by default

## [0.3.0] - 2016-12-26

### Changed

* Updated tests
* Updated to `http-interop/http-middleware#0.4`
* Updated `friendsofphp/php-cs-fixer#2.0`

## [0.2.0] - 2016-11-27

### Changed

* Updated to `http-interop/http-middleware#0.3`

## 0.1.0 - 2016-10-10

First version

[0.4.0]: https://github.com/middlewares/client-ip/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/middlewares/client-ip/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/middlewares/client-ip/compare/v0.1.0...v0.2.0
