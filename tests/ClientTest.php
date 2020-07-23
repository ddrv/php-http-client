<?php

declare(strict_types=1);

namespace Ddrv\Tests\Http\Client;

use Ddrv\Http\Client\Client;
use Http\Client\Exception;
use Http\Client\HttpClient;
use Http\Client\Tests\HttpClientTest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends HttpClientTest
{

    /**
     * @inheritDoc
     */
    protected function createHttpAdapter()
    {
        $factory = new Psr17Factory();
        return new class (new Client($factory)) implements HttpClient
        {
            /**
             * @var ClientInterface
             */
            private $client;

            public function __construct(ClientInterface $client)
            {
                $this->client = $client;
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                try {
                    return $this->client->sendRequest($request);
                } catch (ClientExceptionInterface $exception) {
                    throw new class ($exception) extends \Exception implements Exception
                    {
                        public function __construct(ClientExceptionInterface $e)
                        {
                            parent::__construct($e->getMessage(), $e->getCode(), $e->getPrevious());
                        }
                    };
                }
            }
        };
    }
}
