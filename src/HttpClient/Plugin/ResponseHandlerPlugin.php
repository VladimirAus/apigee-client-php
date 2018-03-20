<?php

/**
 * Copyright 2018 Google LLC
 *                            
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *                            
 *      https://www.apache.org/licenses/LICENSE-2.0
 *                            
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Apigee\Edge\HttpClient\Plugin;

use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Exception\ApiRequestException;
use Apigee\Edge\Exception\ClientErrorException;
use Apigee\Edge\Exception\ServerErrorException;
use Http\Client\Common\Plugin;
use Http\Client\Exception;
use Http\Client\Exception\HttpException;
use Http\Client\Exception\RequestException;
use Http\Message\Formatter;
use Http\Message\Formatter\FullHttpMessageFormatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Handles API communication exceptions.
 */
final class ResponseHandlerPlugin implements Plugin
{
    /** @var \Http\Message\Formatter */
    private $formatter;

    /**
     * ResponseHandlerPlugin constructor.
     *
     * @param \Http\Message\Formatter|null $formatter
     */
    public function __construct(Formatter $formatter = null)
    {
        $this->formatter = $formatter ?: new FullHttpMessageFormatter();
    }

    /**
     * @inheritdoc
     */
    public function handleRequest(RequestInterface $request, callable $next, callable $first)
    {
        return $next($request)->then(function (ResponseInterface $response) use ($request) {
            return $this->decodeResponse($response, $request);
        }, function (Exception $e) use ($request): void {
            if ($e instanceof HttpException || in_array(HttpException::class, class_parents($e))) {
                $this->decodeResponse($e->getResponse(), $request);
            } elseif ($e instanceof RequestException || in_array(RequestException::class, class_parents($e))) {
                throw new ApiRequestException($request, $e->getMessage(), $e->getCode(), $e);
            }

            throw new ApiException($e->getMessage(), $e->getCode(), $e);
        });
    }

    /**
     * Throws an exception if API response code is higher than 399.
     *
     * @param ResponseInterface $response
     * @param RequestInterface $request
     *
     * @throws \Apigee\Edge\Exception\ClientErrorException
     * @throws \Apigee\Edge\Exception\ServerErrorException
     *
     * @return ResponseInterface
     */
    private function decodeResponse(ResponseInterface $response, RequestInterface $request)
    {
        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            throw new ClientErrorException($response, $request, (string) $response->getBody(), $response->getStatusCode(), null, $this->formatter);
        } elseif ($response->getStatusCode() >= 500 && $response->getStatusCode() < 600) {
            throw new ServerErrorException($response, $request, (string) $response->getBody(), $response->getStatusCode(), null, $this->formatter);
        }

        return $response;
    }
}
