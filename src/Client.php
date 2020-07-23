<?php

declare(strict_types=1);

namespace Ddrv\Http\Client;

use Ddrv\Http\Client\Exception\CanNotParseResponse;
use Ddrv\Http\Client\Exception\InvalidRequest;
use Ddrv\Http\Client\Exception\NetworkError;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

use function array_key_exists;
use function array_replace;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function stream_context_create;
use function trim;

class Client implements ClientInterface
{

    const VERSION = '2.0.0';

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $redirects;

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

    /**
     * @var UriInterface|null
     */
    private $proxy;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        int $timeout = 60,
        int $followRedirects = 0
    ) {
        $this
            ->setTimeout($timeout)
            ->setFollowRedirects($followRedirects)
            ->setProxy()
        ;
        $this->responseFactory = $responseFactory;
        $this->defaultHeaders = [
            'User-Agent' => 'ddrv/http-client v' . self::VERSION,
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Connection' => 'close',
        ];
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $request = $this->checkRequest($request);
        $uri = $request->getUri();
        $body = $request->getBody();
        $size = $body->getSize();
        $headers = [];
        if (!$request->hasHeader('Host')) {
            $headers[] = 'Host: ' . $uri->getHost();
        }
        foreach ($request->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                $headers[] = $header . ': ' . $value;
            }
        }
        if ($size && !$request->hasHeader('Content-Length')) {
            $headers[] = 'Content-Length: ' . $size;
        }
        foreach ($this->defaultHeaders as $header => $value) {
            if (!$request->hasHeader($header)) {
                $headers[] = $header . ': ' . $value;
            }
        }

        $context = [
            'http' => [
                'ignore_errors' => true,
                'method' => $request->getMethod(),
                'header' => implode($headers, "\r\n"),
                'protocol_version' => $request->getProtocolVersion(),
                'timeout' => $this->timeout,
                'follow_location' => $this->redirects > 0 ? 1 : 0,
                'max_redirects' => $this->redirects > 1 ? $this->redirects : 1,
                'content' => $body->__toString(),
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
            if (array_key_exists($host, $this->ssl)) {
                $ssl = array_replace($ssl, $this->ssl[$host]);
            }
            $context['ssl'] = $ssl;
        }
        try {
            $responseBody = file_get_contents($uri->__toString(), false, stream_context_create($context));
        } catch (Throwable $exception) {
            $responseBody = false;
        }
        if ($responseBody === false) {
            throw new NetworkError($request);
        }
        return $this->createResponse($http_response_header, $responseBody);
    }

    /**
     * @param string $host
     * @param string $cert
     * @param string $key
     * @param string|null $password
     * @return self
     */
    public function setSslAuth(string $host, string $cert, string $key, string $password = null): self
    {
        list ($h, $p) = explode(':', $host . ':');
        $p = (int)$p;
        if (!$p) {
            $p = 443;
        }
        $host = $h . ':' . $p;
        $this->ssl[$host]['local_cert'] = $cert;
        $this->ssl[$host]['local_pk'] = $key;
        if ($password) {
            $this->ssl[$host]['passphrase'] = $password;
        }
        return $this;
    }

    /**
     * @param string $host
     * @return self
     */
    public function unsetSslAuth(string $host): self
    {
        if (array_key_exists($host, $this->ssl)) {
            unset($this->ssl[$host]);
        }
        return $this;
    }

    /**
     * @param UriInterface|null $proxy
     * @return self
     */
    public function setProxy(UriInterface $proxy = null): self
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * @param int $redirects
     * @return self
     */
    public function setFollowRedirects(int $redirects): self
    {
        $this->redirects = $redirects >= 0 ? $redirects : 0;
        return $this;
    }

    /**
     * @param int $timeout
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout >= 0 ? $timeout : 0;
        return $this;
    }

    /**
     * @param RequestInterface $request
     *
     * @return RequestInterface
     *
     * @throws InvalidRequest
     */
    private function checkRequest(RequestInterface $request): RequestInterface
    {
        $versions = ['1.0', '1.1', '2.0', '2'];
        $methods = ['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'];
        $uri = $request->getUri();
        $uriChanged = false;
        $body = $request->getBody();
        $size = $body->getSize();
        if (!$uri->getHost()) {
            if (!$request->hasHeader('Host')) {
                throw new InvalidRequest($request, 'host is required');
            }
            list($host, $port) = explode(':', $request->getHeaderLine('Host') . ':');
            if ($port) {
                $port = (int)$port;
            }
            if (!$port) {
                $port = 80;
            }
            $uri = $uri->withHost($host)->withPort($port);
            $uriChanged = true;
        }
        if (!$uri->getScheme()) {
            if (!$uriChanged) {
                throw new InvalidRequest($request, 'scheme is required');
            }
            $scheme = $uri->getPort() === 443 ? 'https' : 'http';
            $uri = $uri->withScheme($scheme);
            $uriChanged = true;
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
        if ($uriChanged) {
            $request = $request->withUri($uri);
        }
        return $request;
    }

    /**
     * @param array $headers
     * @param string $content
     *
     * @return ResponseInterface
     *
     * @throws CanNotParseResponse
     */
    private function createResponse(array $headers, string $content): ResponseInterface
    {
        if (!count($headers)) {
            $this->throwRawResponse($headers, $content);
        }
        $follow = -1;
        $pattern = '#HTTP/(?<v>(1\.(0|1)|2(\.0)?)+)\s+(?<s>[1-5][0-9]{2})(\s+(?<p>.*))?#';
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
            $this->throwRawResponse($headers, $content);
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
        if ($content) {
            $body = $response->getBody();
            $body->write($content);
            $body->rewind();
        }
        return $response;
    }

    /**
     * @param array $headers
     * @param string $content
     *
     * @throws CanNotParseResponse
     */
    private function throwRawResponse(array $headers, string $content)
    {
        throw new CanNotParseResponse(implode("\r\n", $headers) . "\r\n\r\n" . $content);
    }

    private function getListAsString(array $array): string
    {
        $last = array_pop($array);
        return implode(', ', $array) . ' or ' . $last;
    }
}
