<?php

// Copyright (c) 2017  Joe Clarke <jclarke@cisco.com>
// All rights reserved.

// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions
// are met:
// 1. Redistributions of source code must retain the above copyright
//    notice, this list of conditions and the following disclaimer.
// 2. Redistributions in binary form must reproduce the above copyright
//    notice, this list of conditions and the following disclaimer in the
//    documentation and/or other materials provided with the distribution.

// THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
// ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
// IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
// ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
// FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
// DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
// OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
// HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
// LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
// OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
// SUCH DAMAGE.

include_once 'yang_catalog.inc.php';
require_once 'httpful.phar';

class RestException extends RuntimeException
{
    protected $rcode;

    public function __construct($msg, $code)
    {
        parent::__construct($msg);
        $this->rcode = $code;
    }

    public function getResponseCode()
    {
        return $this->rcode;
    }
}

class Rester
{
    private $base;
    private $username;
    private $password;
    private $timeout = REST_TIMEOUT;

    public function __construct($base, $username = null, $password = null, $timeout = REST_TIMEOUT)
    {
        $this->base = $base;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    private static function assertResponse($resp, $msg)
    {
        if ($resp->hasErrors()) {
            throw new RestException("Failed to $msg: {$resp->raw_body}", $resp->code);
        }
    }

    public function get($path, $headers = [], $mime = 'application/json', $parseWith = null)
    {
        $url = $this->base;

        $url .= $path;
        $req = \Httpful\Request::get($url);
        if ($this->username !== null && $this->password !== null) {
            $req = $req->authenticateWithBasic($this->username, $this->password);
        }

        if (count($headers) > 0) {
            $req = $req->addHeaders($headers);
        }

        $req = $req->expects($mime)->timeoutIn($this->timeout);
        if ($mime == 'application/json') {
            $req = $req->parseWith(function ($body) {
                $json = json_decode($body, true);

                return $json;
            });
        } elseif ($parseWith === null) {
            throw new RuntimeException("Function to parse type {$mime} not specified.");
        } else {
            $req = $req->parseWith($parseWith);
        }

        $response = $req->send();

        Rester::assertResponse($response, "get {$path} from {$this->base}");

        return $response->body;
    }

    public function post($path, $payload, $payloadMime = 'application/json', $mime = 'application/json', $parseWith = null)
    {
        $url = $this->base;

        $url .= $path;
        $req = \Httpful\Request::post($url);
        $req = $req->body($payload);
        if ($this->username !== null && $this->password !== null) {
            $req = $req->authenticateWithBasic($this->username, $this->password);
        }

        $req = $req->expects($mime)->timeoutIn($this->timeout);
        if ($mime == 'application/json') {
            $req = $req->parseWith(function ($body) {
                $json = json_decode($body, true);

                return $json;
            });
        } elseif ($parseWith === null) {
            throw new RuntimeException("Function to parse type {$mime} not specified.");
        } else {
            $req->parseWith($parseWith);
        }

        if ($payloadMime == 'application/json') {
            $req = $req->sendsJson();
        } elseif ($payloadMime == 'text/xml') {
            $req = $req->sendsXml();
        } else {
            $req->addHeaders('Content-Type', $payloadMime);
        }

        $response = $req->send();

        Rester::assertResponse($response, "post to {$path} from {$this->base}");

        return $response->body;
    }
}
