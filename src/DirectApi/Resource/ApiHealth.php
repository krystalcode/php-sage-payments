<?php

declare(strict_types=1);

namespace KrystalCode\SagePayments\Sdk\DirectApi\Resource;

use KrystalCode\SagePayments\Sdk\DirectApi\ClientBase;

/**
 * The API Health resource.
 */
class ApiHealth extends ClientBase
{
    /**
     * The ID of the API Health API.
     */
    public const ID = 'api_health';

    /**
     * Returns basic information about the front-end API.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @link https://developer.sagepayments.com/bankcard-ecommerce-moto/apis/get/ping
     */
    public function getPing(): ?object
    {
        return $this->getRequest('ping');
    }

    /**
     * Returns basic information about the front-end and back-end APIs.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @link https://developer.sagepayments.com/bankcard-ecommerce-moto/apis/get/status
     */
    public function getStatus(): ?object
    {
        return $this->getRequest('status');
    }
}
