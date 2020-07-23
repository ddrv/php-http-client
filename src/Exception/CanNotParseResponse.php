<?php

declare(strict_types=1);

namespace Ddrv\Http\Client\Exception;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;

class CanNotParseResponse extends Exception implements ClientExceptionInterface
{

    /**
     * @var string
     */
    private $responseRaw;

    public function __construct(string $responseRaw)
    {
        parent::__construct('can not parse response', 127);
        $this->responseRaw = $responseRaw;
    }

    public function getResponseRaw(): string
    {
        return $this->responseRaw;
    }
}
