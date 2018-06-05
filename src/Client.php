<?php

namespace Ddrv\Http\Client;

use Ddrv\Http\Client\Request\ConnectRequest;
use Ddrv\Http\Client\Request\DeleteRequest;
use Ddrv\Http\Client\Request\FormRequest;
use Ddrv\Http\Client\Request\GetRequest;
use Ddrv\Http\Client\Request\HeadRequest;
use Ddrv\Http\Client\Request\OptionsRequest;
use Ddrv\Http\Client\Request\PatchRequest;
use Ddrv\Http\Client\Request\PostRequest;
use Ddrv\Http\Client\Request\PutRequest;
use Ddrv\Http\Client\Request\Request;
use Ddrv\Http\Client\Request\TraceRequest;

class Client
{
    protected $timeout = 10;

    protected $baseUri = null;

    protected $userAgent = 'ddrv/http-client v1.0.0';

    protected $auth = [
        'type' => '',
        'data' => [],
    ];

    public function __construct($baseUri = null)
    {
        $this->baseUri = $baseUri;
    }

    public function timeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function auth($login, $password)
    {
        $this->auth = [
            'type' => 'basic',
            'data' => [
                'login' => $login,
                'password' => $password,
            ],
        ];
        return $this;
    }

    public function userAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    public function connect($uri)
    {
        $uri = $this->createUri($uri);
        $request = new ConnectRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function delete($uri)
    {
        $uri = $this->createUri($uri);
        $request = new DeleteRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function get($uri)
    {
        $uri = $this->createUri($uri);
        $request = new GetRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function head($uri)
    {
        $uri = $this->createUri($uri);
        $request = new HeadRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function options($uri)
    {
        $uri = $this->createUri($uri);
        $request = new OptionsRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function patch($uri)
    {
        $uri = $this->createUri($uri);
        $request = new PatchRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function post($uri)
    {
        $uri = $this->createUri($uri);
        $request = new PostRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function put($uri)
    {
        $uri = $this->createUri($uri);
        $request = new PutRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function trace($uri)
    {
        $uri = $this->createUri($uri);
        $request = new TraceRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    public function form($uri)
    {
        $uri = $this->createUri($uri);
        $request = new FormRequest($uri);
        $request = $this->sets($request);
        return $request;
    }

    protected function createUri($uri)
    {
        return $this->baseUri.$uri;
    }

    protected function sets(Request $request)
    {
        $request->timeout($this->timeout);
        $request->header('User-Agent', $this->userAgent);
        switch ($this->auth['type']) {
            case 'basic':
                $request->auth($this->auth['data']['login'], $this->auth['data']['password']);
                break;
        }
        return $request;
    }
}