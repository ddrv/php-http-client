<?php

use Ddrv\Http\Client\StreamFilter\GzipHeader;

//class_alias(php_user_filter::class, 'PhpUserFilter');

stream_filter_register('gzip_header_filter', GzipHeader::class);
