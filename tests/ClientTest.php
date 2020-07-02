<?php

declare(strict_types=1);

namespace Ddrv\Tests\Http\Client;

use Ddrv\Http\Client\Client;
use Http\Client\Tests\HttpClientTest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;

class ClientTest extends HttpClientTest
{

    /**
     * @inheritDoc
     */
    protected function createHttpAdapter()
    {
        $client = new Client(new Psr17Factory());
        return $client;
    }

    public function testSendWithInvalidUri()
    {
        $request = self::$messageFactory->createRequest(
            'GET',
            $this->getInvalidUri(),
            $this->defaultHeaders
        );
        $this->expectException(ClientExceptionInterface::class);
        $this->httpAdapter->sendRequest($request);
    }
}
