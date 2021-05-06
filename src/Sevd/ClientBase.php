<?php

declare(strict_types=1);

namespace KrystalCode\SagePayments\Sdk\Sevd;

use GuzzleHttp\Exception\RequestException;
use KrystalCode\SagePayments\Sdk\Sevd\Exception\InvalidConfigurationException;
use Psr\Log\LoggerInterface;

/**
 * Base class that facilitates client implementations of SEVD requests.
 *
 * @I Use Guzzle for issuing requests
 *    type     : improvement
 *    priority : low
 *    labels   : sevd, request, error-handling
 */
abstract class ClientBase
{
    /**
     * The URL for all requests.
     */
    protected const URL = 'https://www.sageexchange.com/sevd/frmEnvelope.aspx';

    /**
     * The client configuration array.
     *
     * @var array
     */
    protected array $config;

    /**
     * Constructs a new ClientBase object.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *     The logger to use when logging messages.
     * @param array $config
     *     The configuration array for the SEVD request client.
     *     For a list of supported options see
     *     \KrystalCode\SagePaymentsSDK\Sevd\ClientBase::config().
     *
     * @throws \KrystalCode\SagePayments\Sdk\Sevd\Exception\InvalidConfigurationException
     *     If one or more required configuration items are missing.
     *
     * @see \KrystalCode\SagePaymentsSDK\Sevd\ClientBase::config()
     */
    public function __construct(
        LoggerInterface $logger,
        array $config = []
    ) {
        $this->logger = $logger;

        $this->config($config);
        $this->url();
    }

    /**
     * Returns the tokenized request for the given XML request.
     *
     * @param \SimpleXMLElement $request
     *     The request XML element.
     *
     * @return string
     *     The tokenized request string.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *     If the request was unsuccessful.
     */
    protected function getTokenizedRequest(\SimpleXMLElement $request): string
    {
        $url = $this->url();
        $method = 'POST';

        $config = [
          'http' => [
            'header' => [
              'content-type: application/x-www-form-urlencoded',
              'accept: application/xml'
            ],
            'method' => $method,
            'content' => 'request=' . urlencode($request->asXML()),
          ]
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
            $this->convertHeadersToGuzzleFormat($config['http']['header']),
            $config['http']['content']
        );

        return $http_response_body;
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
     *
     * @I Share non-Guzzle request code with the Direct API using a trait
     *    type     : task
     *    priority : low
     *    labels   : sevd, request, coding-standards
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
     * Converts the given headers to the format handled by Guzzle.
     *
     * When a request fails, we throw a Guzzle exception so that the caller can
     * handle request errors in a uniform way compared to Guzzle requests of the
     * Direct API. To throw such exception we need to provide the request
     * headers in the format expected by Guzzle.
     *
     * @param array $headers
     *     The headers as expected by `stream_context_create()`.
     *
     * @return array
     *     The headers as expected by Guzzle.
     */
    protected function convertHeadersToGuzzleFormat(array $headers): array
    {
        $formatted_headers = [];

        return array_reduce(
            $headers,
            function ($carry, $item) {
                $header_parts = explode(':', $item);
                $carry[trim($header_parts[0])] = trim($header_parts[1]);

                return $carry;
            },
            []
        );
    }

    /**
     * Returns the base XML element for the request.
     *
     * @return \SimpleXMLElement
     *     The base XML element for the request.
     */
    protected function initXmlRequest(): \SimpleXMLElement
    {
        $xml_string = <<<XML
<?xml version='1.0' ?>
<Request_v1 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"></Request_v1>
XML;
        return new \SimpleXMLElement($xml_string);
    }

    /**
     * Prepares and stores the given configuration.
     *
     * @param array $config
     *     The configuration array that was given by constructor injection.
     *     Supported options are:
     *     - application_id: (string, required) The application ID.
     *     - client_id: (string, required) The Client ID.
     *     - client_secret: (string, required) The Client Secret.
     *     - merchant_id: (string, required) The Merchant ID.
     *     - merchant_key: (string, required) The Merchant Key.
     *     - language_id: (string, required) The language that will be used for
     *         the UI.
     *     - url: (string, optional) An alternative URL for all requests.
     *
     * @throws \KrystalCode\SagePayments\Sdk\Sevd\Exception\InvalidConfigurationException
     *     If one or more required configuration items are missing.
     */
    protected function config(array $config): void
    {
        $required = [
            'application_id',
            'client_id',
            'client_secret',
            'merchant_id',
            'merchant_key',
            'language_id',
        ];
        $missing = array_diff($required, array_keys($config));
        if ($missing) {
            throw new InvalidConfigurationException(sprintf(
                'The following required items are missing from the client configuration array: %s.',
                implode(', ', $missing)
            ));
        }

        $this->config = $config;
    }

    /**
     * Prepares, stores and returns the URL.
     *
     * @return string
     *     The URL.
     */
    protected function url(): string
    {
        if (isset($this->url)) {
            return $this->url;
        }

        // If we are explicitly given a URL, use that.
        if (isset($this->config['url'])) {
            $this->url = $this->config['url'];
            return $this->url;
        }

        $this->url = self::URL;
        return $this->url;
    }
}
