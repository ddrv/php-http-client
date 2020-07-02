<?php

declare(strict_types=1);

namespace Ddrv\Http\Client\StreamFilter;

use php_user_filter;

class GzipHeader extends php_user_filter
{

    /**
     * @var int
     */
    private $filtered = 0;

    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            if ($this->filtered === 0) {
                $len = 8;
                $header = substr($bucket->data, 0, 8);
                $flags = ord(substr($header, 1, 1));
                if ($flags & 0x08) {
                    $len = strpos($bucket->data, "\0", 8) + 1;
                }
                $bucket->data = substr($bucket->data, $len);
                $this->filtered = $len;
            }
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }
}
