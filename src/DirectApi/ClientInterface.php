<?php

namespace KrystalCode\SagePayments\Sdk\DirectApi;

/**
 * Provides the interface for all Direct API client implementations.
 */
interface ClientInterface
{
    /**
     * Sends a GET request to the Direct API.
     *
     * @param string $endpoint
     *     The endpoint to send the request to.
     * @param array $query
     *     An associative array containing the query parameters for the request.
     * @param array $headers
     *     An associative array containing the headers for the request.
     * @param array $options
     *     An associative array containing the options for the request.
     *     Supported options are all request options supported by Guzzle.
     *     See http://docs.guzzlephp.org/en/stable/request-options.html
     * @param int $retry
     *     The number of the request retry that we are currently at. Should be
     *     normally left to the default (0, initial request); it will be
     *     incremented on every retry based on the configuration passed to the
     *     client.
     *
     * @return object
     *     The response as an \stdClass object.
     *
     * @throws \KrystalCode\SagePayments\Sdk\DirectApi\Exception\ClientException
     *     If the request was unsuccessful due to a client error.
     */
    public function getRequest(
        string $endpoint,
        array $query = [],
        array $headers = [],
        array $options = [],
        int $retry = 0
    ): object;
}
