<?php

namespace Ddrv\Http\Client\Response;

class Response
{
    protected $statusCode;

    protected $reasonPhrase;

    protected $headers;

    protected $body;

    public function __construct($headers, $body)
    {
        foreach ((array)$headers as $header) {
            if (preg_match('#HTTP/(?<v>[0-9\.]+)\s+(?<s>[0-9]+)(\s+(?<p>.*))?#', $header, $out)) {
                $out = array_replace(array('v' => true, 's' => true, 'p' => true), $out);
                $this->statusCode = $out['s'];
                $this->reasonPhrase = $out['p'];
                $this->headers = array();
            } else {
                $out = explode(':', $header, 2);
                if (isset($out[1])) {
                    $key = trim($out[0]);
                    $key = $this->getHeaderName($key);
                    $value = trim($out[1]);
                    $this->headers[$key][] = $value;
                }
            }
        }
        $this->body = $body;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    public function getHeader($header)
    {
        $header = $this->getHeaderName($header);
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        }
        return null;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getContent()
    {
        return $this->body;

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