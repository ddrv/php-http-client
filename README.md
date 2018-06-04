# ddrv/http-client

Simple HTTP client without cURL dependency. Required `allow_url_fopen` option in `php.ini` 

# Install

```
composer require ddrv/http-client
```

# Using
```php
<?php

require (__DIR__.'/vendor/autoload.php');

$http = new Ddrv\Http\Client\Client();

$response = $http->get('https://httpbin.org/get?param=value')->send();
$code = $response->getStatusCode();
$phrase = $response->getReasonPhrase();
$headers = $response->getHeaders();
$someHeader = $response->getHeader('Content-Type');
$body = $response->getContent();

/*
 * Methods
 */

$response = $http->connect('https://httpbin.org/get')->send();
$response = $http->delete('https://httpbin.org/delete')->send();
$response = $http->head('https://httpbin.org/get')->send();
$response = $http->options('https://httpbin.org/get')->send();
$response = $http->patch('https://httpbin.org/patch')->send();
$response = $http->post('https://httpbin.org/post')->send(); 
$response = $http->put('https://httpbin.org/put')->send(); 
$response = $http->trace('https://httpbin.org/get')->send(); 

/*
 * Headers and body
 */
$response = $http->post('https://httpbin.org/post')
    ->header('Content-Type', 'application/json')
    ->body('{"object": {"key": "value"}, "array": [1,2,3], "string": "text", "number": 10.2}')
    ->send()
;

/*
 * HTTP Basic auth
 */
$response = $http->get('https://httpbin.org/basic-auth/user/pass')->auth('user', 'pass')->send();
/* OR */
$response = $http->get('https://httpbin.org/basic-auth/user/pass')
    ->header('Authorization', 'Basic '.base64_encode('user:pass'))
    ->send()
;

/*
 * post multipart-form data
 */
$response = $http->form('https://httpbin.org/post')
    ->field('auth[login]', 'user')
    ->field('auth[pass]', 'password')
    ->file('file[photo]', '/path/to/file')
    ->fileFromString('file[about]', 'about.txt', 'it is a content of file')
    ->send()
;
```
