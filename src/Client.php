<?php

declare(strict_types=1);

namespace Ddrv\Http\Client;

use Ddrv\Http\Client\Exception\CanNotParseResponse;
use Ddrv\Http\Client\Exception\ConnectionError;
use Ddrv\Http\Client\Exception\ConnectionTimeout;
use Ddrv\Http\Client\Exception\InvalidRequest;
use Ddrv\Http\Client\Exception\NetworkError;
use Ddrv\Http\Client\StreamFilter\GzipHeader;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

use function array_key_exists;
use function explode;
use function fclose;
use function fopen;
use function fread;
use function fwrite;
use function in_array;
use function is_resource;
use function microtime;
use function rewind;
use function stream_context_create;
use function stream_copy_to_stream;
use function stream_filter_register;
use function stream_socket_client;
use function strlen;
use function substr;

class Client implements ClientInterface
{

    const VERSION = '2.0.0';

    /**
     * @var resource[]
     */
    private $connections = [];

    /**
     * @var float
     */
    private $timeout = 60;

    /**
     * @var array
     */
    private $ssl = [];

    /**
     * @var string[]
     */
    private $defaultHeaders;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->defaultHeaders = [
            'User-Agent' => 'ddrv/http-client v' . self::VERSION,
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Connection' => 'close',
        ];
        stream_filter_register('gzip_header_filter', GzipHeader::class);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $connection = $this->getConnection($request->getUri());
        $requestRaw = $this->encodeRequest($request);

        // send request
        rewind($requestRaw);
        stream_copy_to_stream($requestRaw, $connection);
        fclose($requestRaw);

        // read response headers
        $deadline = microtime(true) + $this->timeout;
        $headers = $this->readResponseHeaders($deadline, $connection);
        $response = $this->createResponse($headers);
        $this->readResponseBody($response, $deadline, $connection);
        return $response;
    }

    /**
     * @param UriInterface $uri
     * @return resource|null
     * @throws ConnectionError
     */
    private function getConnection(UriInterface $uri)
    {
        $port = 80;
        $scheme = 'tcp';
        $context = [];
        if ($uri->getScheme() === 'https') {
            $port = 443;
            $scheme = 'ssl';
            $context = [
                'ssl' => [
//                    'verify_peer' => true,
                ],
            ];
        }
        if ($uri->getPort()) {
            $port = $uri->getPort();
        }
        $address = $scheme . '://' . $uri->getHost() . ':' . $port;
        if (array_key_exists($address, $this->ssl)) {
            $context['ssl'] = array_replace($context['ssl'], $this->ssl[$address]);
        }
        if (!array_key_exists($address, $this->connections) || !is_resource($this->connections[$address])) {
            $conn = @stream_socket_client(
                $address,
                $error,
                $message,
                -1,
                STREAM_CLIENT_CONNECT,
                stream_context_create($context)
            );
            if (!is_resource($conn)) {
                throw new ConnectionError($message, $error);
            }
            $this->connections[$address] = $conn;
        }
        return $this->connections[$address];
    }

    /**
     * @param RequestInterface $request
     *
     * @return resource
     *
     * @throws InvalidRequest
     */
    private function encodeRequest(RequestInterface $request)
    {
        $versions = ['1.0', '1.1', '2.0', '2'];
        $methods = ['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'];
        $uri = $request->getUri();
        $body = $request->getBody();
        $size = $body->getSize();
        if (!$uri->getHost()) {
            throw new InvalidRequest($request, 'host is required');
        }
        if (!$uri->getScheme()) {
            throw new InvalidRequest($request, 'scheme is required');
        }
        if (!in_array($uri->getScheme(), ['http', 'https'])) {
            throw new InvalidRequest($request, 'method may be http or https');
        }
        if (!in_array($request->getMethod(), $methods)) {
            throw new InvalidRequest($request, 'method may be ' . $this->getListAsString($methods));
        }
        if (!in_array($request->getProtocolVersion(), $versions)) {
            throw new InvalidRequest($request, 'protocol version may be ' . $this->getListAsString($versions));
        }
        if ($size && !$body->isReadable()) {
            throw new InvalidRequest($request, 'body is not readable');
        }
        $result = fopen('php://temp', 'rw+');
        $eol = "\r\n";
        $line = $request->getMethod() . ' ' . (string)$uri . ' HTTP/' . $request->getProtocolVersion() . $eol;
        fwrite($result, $line);

        if (!$request->hasHeader('Host')) {
            fwrite($result, 'Host: ' . $uri->getHost() . $eol);
        }
        foreach ($request->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                fwrite($result, $header . ': ' . $value . $eol);
            }
        }
        if ($size && !$request->hasHeader('Content-Length')) {
            fwrite($result, 'Content-Length: ' . $size . $eol);
        }
        fwrite($result, $eol);
        if (!$size) {
            fwrite($result, $eol);
            return $result;
        }
        if (!$body->isSeekable()) {
            fwrite($result, $body->__toString() . $eol);
            return $result;
        }
        $body->rewind();
        while (!$body->eof()) {
            fwrite($result, $body->read(4096));
        }
        fwrite($result, $eol);
        return $result;
    }

    /**
     * @param float $deadline
     * @param resource $connection
     * @return array
     * @throws ConnectionTimeout
     */
    private function readResponseHeaders(float $deadline, $connection): array
    {
        if (!is_resource($connection)) {
            throw new InvalidArgumentException('parameter connection must be resource');
        }
        $raw = '';
        $read = 0;
        $reading = true;
        do {
            $last = fread($connection, 1);
            $raw .= $last;
            $read += 1;
            if ($read >= 4 && $last == "\n" && substr($raw, -4) == "\r\n\r\n") {
                $reading = false;
            }
            $this->checkTimeout($deadline);
        } while ($reading);
        return explode("\r\n", substr($raw, 0, -4));
    }

    /**
     * @param array $headers
     *
     * @return ResponseInterface
     *
     * @throws CanNotParseResponse
     */
    private function createResponse(array $headers): ResponseInterface
    {
        if (!count($headers)) {
            $this->throwRawResponse($headers);
        }
        $follow = -1;
        $pattern = '#HTTP/(?<v>(1\.(0|1)|2\.0)+)\s+(?<s>[1-5][0-9]{2})(\s+(?<p>.*))?#';
        $stack = [];
        foreach ($headers as $header) {
            if (preg_match($pattern, $header, $out)) {
                $follow++;
                $item = array_replace(['v' => '1.1', 's' => 200, 'p' => ''], $out);
                $stack[$follow] = [
                    'version' => (string)$item['v'],
                    'status' => (int)$item['s'],
                    'phrase' => (string)$item['p'],
                    'headers' => [],
                ];
                continue;
            }
            $stack[$follow]['headers'][] = $header;
        }
        if (!count($stack)) {
            $this->throwRawResponse($headers);
        }
        $last = array_pop($stack);
        $response = $this->responseFactory
            ->createResponse($last['status'], $last['phrase'])
            ->withProtocolVersion($last['version'])
        ;
        foreach ($last['headers'] as $header) {
            list($name, $value) = array_replace(['', ''], explode(':', $header, 2));
            $name = trim($name);
            $value = trim($value);
            if (!$name || !$value) {
                continue;
            }
            $response = $response->withAddedHeader($name, $value);
        }
        return $response;
    }

    /**
     * @param ResponseInterface $response
     * @param float $deadline
     * @param resource $connection
     * @throws ConnectionTimeout
     */
    private function readResponseBody(ResponseInterface $response, float $deadline, $connection)
    {
        if (!is_resource($connection)) {
            throw new InvalidArgumentException('parameter connection must be resource');
        }
        $this->checkTimeout($deadline);
        $body = $response->getBody();

        $stream = fopen('php://temp', 'rw+');
        $zipped = stripos((string)$response->getHeaderLine('Content-Encoding'), 'gzip') !== false;
        $len = (int)$response->getHeaderLine('Content-Length');
        $chunked = stripos((string)$response->getHeaderLine('Transfer-Encoding'), 'chunked') !== false;
        $closed = stripos((string)$response->getHeaderLine('Connection'), 'close') !== false;
        $read = false;
        if ($len > 0) {
            $data = 0;
            while ($data < $len) {
                $this->checkTimeout($deadline);
                $chunk = fread($connection, 1024);
                $data += strlen($chunk);
                fwrite($stream, $chunk);
            }
            $read = true;
        }
        if (!$read && $chunked) {
            do {
                $this->checkTimeout($deadline);
                $line = '';
                do {
                    $last = fread($connection, 1);
                    $line .= $last;
                } while (strpos($line, "\r\n") === false);

                $size = (int)base_convert(explode(' ', $line, 2)[0], 16, 10);
                if ($size < 0) {
                    $size = 0;
                }
                if ($size) {
                    stream_copy_to_stream($connection, $stream, $size);
                }
                fread($connection, 2);
            } while ($size);
            $read = true;
        }
        if (!$read && $closed) {
            while (!feof($connection)) {
                $this->checkTimeout($deadline);
                fwrite($stream, fread($connection, 1024));
            }
        }
        if ($closed) {
            fclose($connection);
        }

        rewind($stream);
        if ($zipped) {
            $magic = fread($stream, 2);
            $input = '';
            if (strlen($magic) === 2) {
                if ((ord(substr($magic, 0, 1)) == 31) && (ord(substr($magic, 1, 1)) == 139)) {
                    stream_filter_append($stream, "gzip_header_filter", STREAM_FILTER_READ);
                    stream_filter_append($stream, "zlib.inflate", STREAM_FILTER_READ);
                } else {
                    $input = $magic;
                }
            }
            $body->write($input);
        }
        while (!feof($stream)) {
            $data = fread($stream, 1024);
            $body->write($data);
        }
        fclose($stream);
    }

    /**
     * @param float $deadline
     * @throws ConnectionTimeout
     */
    private function checkTimeout(float $deadline)
    {
        if ($this->timeout <= 0) {
            return;
        }
        if (microtime(true) > $deadline) {
            throw new ConnectionTimeout();
        }
    }

























    public function setTimeOut(float $seconds): self
    {
        $this->timeout = $seconds >= 0 ? $seconds : 0;
        return $this;
    }

    public function setSslAuth(string $host, string $path, string $cert, string $key, string $password = null): self
    {
        $this->ssl[$host][$path]['local_cert'] = $cert;
        $this->ssl[$host][$path]['local_pk'] = $key;
        if ($password) {
            $this->ssl[$host][$path]['passphrase'] = $password;
        }
        return $this;
    }

    public function setProxy(UriInterface $proxy = null): self
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function _sendRequest(RequestInterface $request): ResponseInterface
    {
        $connection = $this->getConnection($request->getUri());
        $requestRaw = $this->encodeRequest($request);

        // send request
        rewind($requestRaw);
        stream_copy_to_stream($requestRaw, $connection);
        fclose($requestRaw);

        // read response headers
        $headers = $this->readResponseHeaders($connection);
        $response = $this->createResponse($headers);

        $this->readResponseBody($response, $connection);
        return $response;

        print_r([$headers, $response->getBody()->__toString()]);

        die;
        $responseRaw = fopen('php://temp', 'rw+');


        $time = time();
        stream_copy_to_stream($connection, $responseRaw);
        echo '1' . PHP_EOL;
        rewind($responseRaw);
        $res = '';
        while (!feof($responseRaw)) {
            $res .= fgets($responseRaw, 4096);
        }
        fclose($responseRaw);
        fclose($connection);
        echo '2' . PHP_EOL;
        echo $res . PHP_EOL;
        echo 'response time :' . (time() - $time) . PHP_EOL;
        die;


        echo $server;die;

        $headers = [];
        foreach ($this->defaultHeaders as $header => $line) {
            if (!$request->hasHeader($header)) {
                $line = strtr($line, ["\r" => '', "\n" => '']);
                $headers[] = $header . ': ' . $line;

            }
        }
        foreach ($request->getHeaders() as $header => $values) {
            foreach ($values as $line) {
                $line = strtr($line, ["\r" => '', "\n" => '']);
                if (!$line) {
                    continue;
                }
                $headers[] = $header . ': ' . $line;
            }
        }
        $requestBodyRaw = $request->getBody()->__toString();
        $requestHeadersRaw = implode("\r\n", $headers);

        $context = [
            'http' => [
                'ignore_errors' => true,
                'method' => $request->getMethod(),
                'header' => $requestHeadersRaw,
                'protocol_version' => $request->getProtocolVersion(),
                'timeout' => $this->timeout,
                'follow_location' => $this->followRedirects > 1 ? 1 : 0,
                'max_redirects' => $this->followRedirects,
                'content' => $requestBodyRaw,
            ],
        ];
        if ($this->proxy) {
            $context['http']['proxy'] = $this->proxy->__toString();
        }
        if ($uri->getScheme() === 'https') {
            $ssl = [
                'verify_peer' => true,
            ];
            $host = $uri->getHost();
            $port = (string)$uri->getPort();
            if ($port && $port !== '443') {
                $host .= ':' . $port;
            }
            $path = '/' . ltrim($uri->getPath(), '/');
            if (array_key_exists($host, $this->ssl)) {
                foreach ($this->ssl[$host] as $p => $options) {
                    if (mb_strpos($path, $p) !== 0) {
                        continue;
                    }
                    $ssl = array_replace($ssl, $options);
                }
            }
            $context['ssl'] = $ssl;
        }
        try {
            $responseBodyRaw = file_get_contents($uri->__toString(), false, stream_context_create($context));
        } catch (Throwable $exception) {
            $responseBodyRaw = false;
        }
        if ($responseBodyRaw === false) {
            throw new NetworkError($request);
        }
        return $this->createResponse($http_response_header, $responseBodyRaw);
    }

    /**
     * @param array $headers
     *
     * @throws CanNotParseResponse
     */
    private function throwRawResponse(array $headers)
    {
        throw new CanNotParseResponse(implode("\r\n", $headers));
    }

    private function getListAsString(array $array): string
    {
        $last = array_pop($array);
        return implode(', ', $array) . ' or ' . $last;
    }
}
