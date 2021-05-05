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
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @throws \KrystalCode\SagePayments\Sdk\DirectApi\Exception\ClientException
     *     If the request was unsuccessful due to a client error.
     */
    public function getRequest(
        string $endpoint,
        array $query = [],
        array $headers = [],
        array $options = []
    ): ?object;

    /**
     * Sends a POST request to the Direct API.
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
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @throws \KrystalCode\SagePayments\Sdk\DirectApi\Exception\ClientException
     *     If the request was unsuccessful due to a client error.
     */
    public function postRequest(
        string $endpoint,
        array $query = [],
        array $headers = [],
        array $options = []
    ): ?object;
}
