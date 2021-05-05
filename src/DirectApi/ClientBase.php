<?php

declare(strict_types=1);

namespace KrystalCode\SagePayments\Sdk\DirectApi;

use KrystalCode\SagePayments\Sdk\DirectApi\Exception\InvalidConfigurationException;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class that facilitates client implementations of Direct API resources.
 */
abstract class ClientBase implements ClientInterface
{
    /**
     * Indicates the development environment.
     */
    public const ENV_SANDBOX = 0;

    /**
     * Indicates the production environment.
     */
    public const ENV_PRODUCTION = 1;

    /**
     * The base URL for all API endpoints.
     *
     * It is the same for both sandbox and production environments.
     */
    public const BASE_URL = 'https://api-cert.sagepayments.com';

    /**
     * The base path for all Direct API endpoints.
     */
    public const BASE_PATH = 'bankcard/v1';

    /**
     * The base url.
     *
     * @var string
     */
    protected string $baseUrl;

    /**
     * The client configuration array.
     *
     * @var array
     *
     * @I Document supported client configuration settings
     *    type     : task
     *    priority : normal
     *    labels   : documentation
     */
    protected array $config;

    /**
     * The array of options for the Guzzle client.
     *
     * @var array
     */
    protected array $guzzleOptions;

    /**
     * The logger to use when logging messages.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Constructs a new ClientBase object.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *     The logger to use when logging messages.
     * @param array $config
     *     The configuration array for the API resource client.
     *     For a list of supported options see
     *     \KrystalCode\SagePaymentsSDK\DirectApi\ClientBase::config().
     * @param array $guzzleOptions
     *     An associative array containing the options to pass to the Guzzle
     *     client.
     *     For a list of supported options see \GuzzleHttp\Client::__construct().
     *
     * @see \KrystalCode\SagePaymentsSDK\DirectApi\ClientBase::config()
     * @see \GuzzleHttp\Client::__construct()
     */
    public function __construct(
        LoggerInterface $logger,
        array $config = [],
        array $guzzleOptions = []
    ) {
        $this->logger = $logger;
        $this->guzzleOptions = $guzzleOptions;

        $this->config($config);
        $this->baseUrl();
        $this->debug();
    }

    /**
     * {@inheritdoc}
     */
    public function getRequest(
        string $endpoint,
        array $query = [],
        array $headers = [],
        array $options = []
    ): ?object {
        return $this->sendRequest(
            'GET',
            $endpoint,
            $query,
            $headers,
            $options
        );
    }

    /**
     * {@inheritdoc}
     */
    public function postRequest(
        string $endpoint,
        array $query = [],
        array $headers = [],
        array $options = []
    ): ?object {
        return $this->sendNonGuzzleRequest(
            'POST',
            $endpoint,
            $query,
            $headers,
            $options
        );
    }

    /**
     * Sends a request to the Direct API.
     *
     * @param string $method
     *     The method of the request e.g. GET, POST, PUT, DELETE.
     * @param string $endpoint
     *     The endpoint to send the request to.
     * @param array $query
     *     An associative array containing the query parameters for the request.
     * @param array $headers
     *     An associative array containing the headers for the request.
     * @param array $options
     *     An associative array containing the options for the request.
     *     Supported options are all request options supported by Guzzle, see
     *     http://docs.guzzlephp.org/en/stable/request-options.html
     * @param int $retry
     *     The number of the request retry that we are currently at. Should be
     *     normally left to the default (0, initial request); it will be
     *     incremented on every retry based on the configuration passed to the
     *     client.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *     If the request was unsuccessful.
     *
     * @I Support retries on exceptions other than BadResponseException
     *    type     : improvement
     *    priority : low
     *    labels   : error-handling
     */
    protected function sendRequest(
        string $method,
        string $endpoint,
        array $query = [],
        array $headers = [],
        array $options = [],
        int $retry = 0
    ): ?object {
        $client = $this->buildClient();
        $url = $this->baseUrl() . '/' . $this->basePath() . '/' . $endpoint;
        $request = new Request(
            $method,
            $url
        );
        $options = $this->requestOptions(
            $method,
            $url,
            $query,
            $headers,
            $options
        );

        try {
            $response = $client->send($request, $options);
        } catch (BadResponseException $exception) {
            $response = $exception->getResponse();

            // Retry the request, if configured to do so.
            if ($this->retry($endpoint, $response->getStatusCode(), $retry)) {
                $retry++;

                // Log a warning message if we are retrying so that we know; if
                // it happens frequently it may be a problem.
                $this->logger->warning(
                    sprintf(
                        'Retry %d on the "%s" endpoint after a %s response.',
                        $retry,
                        $endpoint,
                        $response->getStatusCode()
                    )
                );

                return $this->sendRequest(
                    $method,
                    $endpoint,
                    $query,
                    $headers,
                    $options,
                    $retry
                );
            }

            throw $exception;
        }

        return $this->parseResponse($response, $request);
    }

    /**
     * Parses and returns the response to a request.
     *
     * It performs basic parsing of the response by decoding the JSON object
     * into an \stdClass object.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *     The response.
     * @param \Psr\Http\Message\RequestInterface $request
     *     The request.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     */
    protected function parseResponse(
        ResponseInterface $response,
        RequestInterface $request
    ): ?object {
        $response_body = (string) $response->getBody();

        // Some responses return an empty body. We still want to follow the API
        // and return an empty object.
        if (!$response_body) {
            return new \stdClass();
        }

        return json_decode($response_body);
    }

    /**
     * Sends a request to the Direct API without using Guzzle.
     *
     * For some reason POST requests are not accepted when send with Guzzle. We
     * therefore make them using the method provided in the API PHP samples
     * until we figure out why this happens.
     *
     * @param string $method
     *     The method of the request e.g. GET, POST, PUT, DELETE.
     * @param string $endpoint
     *     The endpoint to send the request to.
     * @param array $query
     *     An associative array containing the query parameters for the request.
     * @param array $headers
     *     An associative array containing the headers for the request.
     * @param array $options
     *     An associative array containing the options for the request.
     *     Supported options are:
     *     json: An array containing the body of the request.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *     If the request was unsuccessful.
     *
     * @I Use Guzzle for POST requests
     *    type     : task
     *    priority : normal
     *    labels   : request
     * @I Support retries for POST requests
     *    type     : bug
     *    priority : normal
     *    labels   : request
     */
    protected function sendNonGuzzleRequest(
        string $method,
        string $endpoint,
        array $query = [],
        array $headers = [],
        array $options = []
    ): ?object {
        $url = $this->baseUrl() . '/' . $this->basePath() . '/' . $endpoint;
        $options = $this->requestOptions(
            $method,
            $url,
            $query,
            $headers,
            $options
        );

        $header = [];
        foreach ($options['headers'] as $key => $value) {
            $header[] = "{$key}: {$value}";
        }
        $config = [
            'http' => [
                'header' => $header,
                'method' => $method,
                'content' => json_encode($options['json']),
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($config);
        $http_response_body = file_get_contents($url, false, $context);

        // If `file_get_contents` fails we'll get `false`. We don't know what
        // caused the failure.
        if ($http_response_body === false) {
            $http_response_body = '';
            $http_response_header = [
                'HTTP/1.1 504 Unknown Error'
            ];
        }
        // If the remote server closes the connection for whatever reason, the
        // variable containing the response headers will not exist. Simulate a
        // timeout response.
        // Not sure if this case is covered by handling a `false` response body
        // above.
        if (empty($http_response_header)) {
            $http_response_header = [
                'HTTP/1.1 504 Gateway Timeout'
            ];
        }

        $this->handleNonGuzzleResponse(
            $http_response_header,
            $http_response_body,
            $method,
            $url,
            $options['headers'],
            $config['http']['content']
        );

        return $this->parseNonGuzzleResponse($http_response_body);
    }

    /**
     * Detects and handles errors in non-Guzzle responses.
     *
     * @param array $http_response_header
     *     The response headers.
     * @param mixed $http_response_body
     *    The response body.
     * @param string $request_method.
     *     The method of the request e.g. GET, POST, PUT, DELETE.
     * @param string $url
     *     The URL of the request.
     * @param string $request_body
     *     The JSON-encoded request body.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *     If the request was unsuccessful.
     */
    protected function handleNonGuzzleResponse(
        array $http_response_header,
        $http_response_body,
        string $request_method,
        string $request_url,
        array $request_headers,
        $request_body
    ): void {
        [
            $response_code,
            $protocol_version,
            $response_reason_phrase,
        ] = $this->getNonGuzzleResponseCode($http_response_header);
        if ($response_code < 400) {
            return;
        }

        $request = new Request(
            $request_method,
            $request_url,
            $request_headers,
            $request_body
        );
        $response = new Response(
            $response_code,
            // @I Convert the headers array to Guzzle format
            //    type     : improvement
            //    priority : low
            //    labels   : api, error-handling
            $http_response_header,
            $http_response_body,
            $protocol_version,
            $response_reason_phrase
        );
        throw RequestException::create(
            $request,
            $response
        );
    }

    /**
     * Returns the response code contained in the given response headers.
     *
     * The response headers multiple response codes if there have been any
     * redirects. We return only the last one. We shouldn't really come across
     * such scenario, but we let's handle it to avoid errors.
     *
     * @param array $http_response_header
     *     The response headers.
     *
     * @return array
     *     An array containing the following items in the given order:
     *     - (int) The status code.
     *     - (string) The HTTP protocol version.
     *     - (string) The reason phrase.
     */
    protected function getNonGuzzleResponseCode(
        array $http_response_header
    ): array {
        return end(array_reduce(
            $http_response_header,
            function ($carry, $header) {
                $code = null;
                $protocol_version = null;
                $response_phrase = null;
                if (preg_match("#HTTP/[0-9\.]+\s+([0-9]+)#", $header, $matches)) {
                    $code = intval($matches[1]);
                    $parts = explode(' ', $header, 3);
                    $protocol_version = explode('/', $parts[0])[1];
                }
                if (preg_match("#HTTP/[0-9\.]+\s+([0-9]+)+\s+(.*)#", $header, $matches)) {
                    $reason_phrase = $matches[2];
                }

                if ($code) {
                    $carry[] = [$code, $protocol_version, $reason_phrase];
                }
                return $carry;
            },
            []
        ));
    }

    /**
     * Parses and returns the response to a non-Guzzle request.
     *
     * It performs basic parsing of the response by decoding the JSON object
     * into an \stdClass object.
     *
     * @param string $response_body
     *     The JSON-encoded response body.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     */
    protected function parseNonGuzzleResponse(string $response_body): ?object
    {
        // Some responses return an empty body. We still want to follow the API
        // and return an empty object.
        if (!$response_body) {
            return new \stdClass();
        }

        return json_decode($response_body);
    }

    /**
     * Determines whether the request should be retried.
     *
     * @param string $endpoint
     *     The endpoint of the request.
     * @param int $status_code
     *     The response status code of the last try.
     * @param int $retry
     *     The number of tries already executed for the request.
     *
     * @return bool
     *     Whether a retry should be executed for the request.
     *
     * @throws \KrystalCode\SagePayments\Sdk\DirectApi\Exception\InvalidConfigurationException
     *     If the retries option for the given status code has not been properly
     *     configured.
     */
    protected function retry(
        string $endpoint,
        int $status_code,
        int $retry
    ): int {
        if (empty($this->config['retries'])) {
            return false;
        }

        if (empty($this->config['retries'][$status_code])) {
            return false;
        }

        $config = &$this->config['retries'][$status_code];

        if (!isset($config['global'])) {
            throw new InvalidConfigurationException(sprintf(
                'The number of retries for the "%s" response status code has not been configured.',
                $status_code
            ));
        }

        $retries = $config['global'];

        if (!empty($config['endpoints'][$endpoint])) {
            $retries = $config['endpoints'][$endpoint];
        }

        if ($retries > $retry) {
            return true;
        }

        return false;
    }

    /**
     * Builds the Guzzle client.
     *
     * @return \GuzzleHttp\Client
     *     An intialized Guzzle client.
     */
    protected function buildClient(): GuzzleClient
    {
        return new GuzzleClient($this->guzzleOptions);
    }

    /**
     * Prepares and returns the options as required for a request.
     *
     * These are the options that are passed on the the Request object. It
     * includes query parameters and headers.
     *
     * In addition to the given options, it adds any default options that apply
     * to all API endpoints.
     *
     * @param array $query
     *     An associative array containing the query parameters for the request.
     * @param array $headers
     *     An associative array containing the headers for the request.
     * @param array $options
     *     An associative array containing additional options for the request.
     *     Supported options are all request options supported by Guzzle. See
     *     http://docs.guzzlephp.org/en/stable/request-options.html
     *
     * @return array
     *     The prepared options for the request. See \GuzzleHttp\RequestOptions.
     */
    protected function requestOptions(
        string $method,
        string $url,
        array $query,
        array $headers,
        array $options
    ): array {
        return $options + [
            'query' => $query,
            'headers' => $this->requestHeaders(
                $method,
                $url,
                $query,
                $headers,
                $options
            ),
            'debug' => $this->config['debug'],
        ];
    }

    /**
     * Prepares and returns the headers as required for a request.
     *
     * Adds to the given array the default headers that apply to all API
     * endpoints, including authorization headers.
     *
     * @param array $headers
     *     An associative array containing additional headers for the request.
     *
     * @return array
     *     The prepared headers for the request.
     */
    protected function requestHeaders(
        string $method,
        string $url,
        array $query,
        array $headers,
        array $options
    ): array {
        if ($query) {
            $url = $url . '?' . http_build_query($query);
        }
        $payload = isset($options['json']) ? json_encode($options['json']) : '';
        $nonce = uniqid();
        $timestamp = (string) time();

        $authorization = base64_encode(hash_hmac(
            'sha512',
            $method . $url . $payload . $this->config['merchant_id'] . $nonce . $timestamp,
            $this->config['client_secret'],
            true
        ));

        return [
            'clientId' => $this->config['client_id'],
            'merchantId' => $this->config['merchant_id'],
            'merchantKey' => $this->config['merchant_key'],
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'authorization' => $authorization,
            'Content-Type' => 'application/json',
        ] + $headers;
    }

    /**
     * Prepares and stores the given configuration.
     *
     * @param array $config
     *     The configuration array that was given by constructor injection.
     *     Supported options are:
     *     client_id: (required) The Client ID.
     *     client_secret: (required) The Client Secret.
     *     merchant_id: (required) The Merchant ID.
     *     merchant_key: (required) The Merchant Key.
     *     base_url: (optional) An alternative base URL for all API endpoints.
     *     debug: (optional) When `true` is given, all Guzzle requests will be
     *         run with the debug option. Currently, this does not apply to POST
     *         requests due to an issue preventing us from implementing them
     *         using Guzzle.
     *     retries: (optional) An associative array with options that will
     *         determine whether to retry failed requests.
     *
     * @throws \KrystalCode\SagePayments\Sdk\DirectApi\Exception\InvalidConfigurationException
     *     If one or more required configuration items are missing.
     *
     * @I Document retry configuration options
     *    type     : task
     *    priority : normal
     *    labels   : documentation
     */
    protected function config(array $config): void
    {
        $required = [
            'client_id',
            'client_secret',
            'merchant_id',
            'merchant_key',
        ];
        $missing = array_diff($required, array_keys($config));
        if ($missing) {
            throw new InvalidConfigurationException(sprintf(
                'The following required items are missing from the client configuration array: %s.',
                implode(', ', $missing)
            ));
        }

        // Default to development environment.
        $this->config = array_merge(
            ['env' => self::ENV_SANDBOX],
            $config
        );
    }

    /**
     * Prepares, stores and returns the base URL.
     *
     * @return string
     *     The base URL.
     */
    protected function baseUrl(): string
    {
        if (isset($this->baseUrl)) {
            return $this->baseUrl;
        }

        // If we are explicitly given a base URL, use that.
        if (isset($this->config['base_url'])) {
            $this->baseUrl = $this->config['base_url'];
            return $this->baseUrl;
        }

        $this->baseUrl = self::BASE_URL;
        return $this->baseUrl;
    }

    /**
     * Returns the base path which is the same for all resources.
     *
     * @return string
     *     The base path.
     */
    protected function basePath(): string
    {
        return self::BASE_PATH;
    }

    /**
     * Prepares and stores the debug configuration setting.
     *
     * If we are explicitly given a value in the configuration, use that.
     * Otherwise, default to `true` if we are on the sandbox environment,
     * `false` otherwise.
     */
    protected function debug(): void
    {
        if (isset($this->config['debug'])) {
            return;
        }

        if ($this->config['env'] === self::ENV_SANDBOX) {
            $this->config['debug'] = true;
            return;
        }

        $this->config['debug'] = false;
    }
}
