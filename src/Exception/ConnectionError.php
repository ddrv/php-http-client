<?php

declare(strict_types=1);

namespace Ddrv\Http\Client\Exception;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;

class ConnectionError extends Exception implements ClientExceptionInterface
{
}
