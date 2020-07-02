<?php

use Ddrv\Http\Client\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;

require __DIR__ . '/vendor/autoload.php';

$headers = [
    'accept-encoding' => 'gzip, deflate',
    'te' => 'Trailers',
    'upgrade-insecure-requests' => 1,
];
$request = new Request('GET', 'https://ddrv.ru/', $headers);

$factory = new Psr17Factory();
$client = new Client($factory);
$client->setTimeOut(3.07);

$response = $client->sendRequest($request);

print_r([
    'status' => $response->getStatusCode(),
    'version' => $response->getProtocolVersion(),
    'headers' => $response->getHeaders(),
    'body' => $response->getBody()->__toString(),
    'size' => $response->getBody()->getSize()
]);
