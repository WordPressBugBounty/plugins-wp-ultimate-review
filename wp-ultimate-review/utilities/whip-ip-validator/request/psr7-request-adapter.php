<?php

/*
The MIT License (MIT)

Copyright (c) 2015 Vectorface, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

namespace WurReview\Utilities\Whip_Ip_Validator\Request;

/**
 * Provide IP address data from ta PSR-7 request.
 */
class Psr7_Request_Adapter implements Request_Adapter
{
    /**
     * The PSR-7 request that serves as the source of data.
     *
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    private $request;

    /**
     * A formatted version of the HTTP headers: ["header" => "value", ...]
     *
     * @var string[]
     */
    private $headers;

    /**
     * Create a new adapter for a superglobal $_SERVER-style array.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request An array in a format like PHP's $_SERVER var.
     */
    public function __construct(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function getRemoteAddr()
    {
        $server = $this->request->getServerParams();
        return isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : null;
    }

    public function getHeaders()
    {
        if (!isset($this->headers)) {
            $this->headers = array();
            foreach ($this->request->getHeaders() as $header => $values) {
                $this->headers[strtolower($header)] = end($values);
            }
        }
        return $this->headers;
    }
}
