<?php

declare(strict_types=1);

namespace Ddrv\Http\Client\Exception;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;

class ConnectionTimeout extends Exception implements ClientExceptionInterface
{

    public function __construct()
    {
        parent::__construct('connection timeout', 1);
    }
}
