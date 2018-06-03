<?php

namespace Ddrv\Http\Client\Request;

use Ddrv\Http\Client\Response\Response;

abstract class Request
{

    protected $method;

    protected $timeout;

    protected $headers;

    protected $redirects = 0;

    protected $uri;

    protected $body;

    public function __construct($uri)
    {
        $this->uri = $uri;
    }

    public function send()
    {
        $headers = '';
        foreach ($this->headers as $header => $values) {
            if ($values) {
                foreach ($values as $value) {
                    $headers .= $this->getHeaderName($header) . ': ' . $value . "\r\n";
                }
            }
        }
        $context = array(
            'http' => array(
                'ignore_errors' => true,
                'method' => $this->method,
                'header' => $headers,
                'protocol_version' => 1.1,
                'timeout' => $this->timeout,
                'follow_location' => ($this->redirects > 1)?1:0,
                'max_redirects' => $this->redirects,
                'content' => $this->body,
            ),
        );
        $responseBody = file_get_contents($this->uri, false, stream_context_create($context));
        return new Response($http_response_header, $responseBody);
    }

    public function timeout($time)
    {
        $this->timeout = $time;
        return $this;
    }

    public function auth($login, $password)
    {
        $this->header('Authorization', 'Basic '.base64_encode($login.':'.$password));
        return $this;
    }

    public function body($body)
    {
        $this->body = $body;
        return $this;
    }

    public function header($name, $values)
    {
        $this->headers[$name] = (array)$values;
        return $this;
    }

    protected function getHeaderName($name)
    {
        $name = strtolower($name);
        $array = explode('-', $name);
        foreach ($array as &$item) {
            $item = ucfirst($item);
            unset($item);
        }
        $name = implode('-', $array);
        return $name;
    }
}