[![Latest Stable Version](https://img.shields.io/packagist/v/ddrv/http-client.svg?style=flat-square)](https://packagist.org/packages/ddrv/http-client)
[![Total Downloads](https://img.shields.io/packagist/dt/ddrv/http-client.svg?style=flat-square)](https://packagist.org/packages/ddrv/http-client/stats)
[![License](https://img.shields.io/packagist/l/ddrv/http-client.svg?style=flat-square)](https://github.com/ddrv/php-http-client/blob/master/LICENSE)
[![PHP](https://img.shields.io/packagist/php-v/ddrv/http-client.svg?style=flat-square)](https://php.net)

# ddrv/http-client

Simple HTTP client without cURL dependency. Required `allow_url_fopen` option in `php.ini` file. 

```ini
; php.ini
allow_url_fopen = On
```

# Install

Install this package, your favorite [psr-7 implementation](https://packagist.org/providers/psr/http-message-implementation) and your favorite [psr-17 implementation](https://packagist.org/providers/psr/http-factory-implementation).

```bash
composer require ddrv/http-client:^2.0
```

# Using

```php
<?php

use Ddrv\Http\Client\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/** 
 * @var ResponseFactoryInterface $responseFactory
 * @var int $timeout = 60
 * @var int $redirects = 0
 */
$http = new Client($responseFactory, $timeout, $redirects);

/** @var RequestInterface $request */
$response = $http->sendRequest($request);

$code = $response->getStatusCode();
$phrase = $response->getReasonPhrase();
$headers = $response->getHeaders();
$someHeader = $response->getHeader('Content-Type');

$body = $response->getBody()->__toString();
```

# Configuration

```php
<?php

use Ddrv\Http\Client\Client;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * @var ResponseFactoryInterface $responseFactory
 * @var UriInterface $proxy
 */

$http = new Client($responseFactory);

$http->setFollowRedirects(0); // Set 0 follow redirects (disable). 
$http->setTimeOut(10); // Set connection timeout 10 seconds

$http->setProxy($proxy); // Set proxy
$http->setProxy(); // Unset proxy
```

# SSL Authorization

```php
<?php

use Ddrv\Http\Client\Client;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * @var ResponseFactoryInterface $responseFactory
 */

$http = new Client($responseFactory);

$http->setSslAuth('host', 'ssl.crt', 'ssl.key'); // without password
$http->setSslAuth('host', 'ssl.crt', 'ssl.key', 'p@s$w0rd'); // with password
$http->unsetSslAuth('host'); // disable
```

