<?php

declare(strict_types=1);

namespace Ddrv\Http\Client\Exception;

use Exception;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

class InvalidRequest extends Exception implements RequestExceptionInterface
{

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(RequestInterface $request, string $message)
    {
        parent::__construct($message, 127);
        $this->request = $request;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
