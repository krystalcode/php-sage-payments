<?php

namespace KrystalCode\SagePayments\Sdk\DirectApi\Resource;

use KrystalCode\SagePayments\Sdk\DirectApi\ClientBase;

/**
 * The Charges API resource.
 */
class Charges extends ClientBase
{
    /**
     * The ID of the Charges API.
     */
    public const ID = 'charges';

    /**
     * The Authorization charge type.
     */
    public const CHARGE_TYPE_AUTH = 'Auth';

    /**
     * The Force charge type.
     */
    public const CHARGE_TYPE_FORCE = 'Force';

    /**
     * The Sale charge type.
     */
    public const CHARGE_TYPE_SALE = 'Sale';

    /**
     * Creates a new charge.
     *
     * @param string $type
     *     The charge type.
     * @param array $charge
     *     The details of the charge.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @throws \InvalidArgumentException
     *    When the charge type is not supported.
     *
     * @link https://developer.sagepayments.com/bankcard-ecommerce-moto/apis/post/charges
     */
    public function postCharges(string $type, array $charge): ?object
    {
        $this->validateChargeType($type);

        return $this->postRequest(
            'charges',
            ['type' => $type],
            [],
            ['json' => $charge]
        );
    }

    /**
     * Captures an existing Auth charge.
     *
     * @param string $reference
     *     The charge reference.
     * @param array $charge
     *     The details of the charge.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @link https://developer.sagepayments.com/bankcard-ecommerce-moto/apis/put/charges/%7Breference%7D
     */
    public function putCharges($reference, array $charge): ?object
    {
        return $this->putRequest(
            "charges/{$reference}",
            [],
            [],
            ['json' => $charge]
        );
    }

    /**
     * Validates that the given charge type is supported.
     *
     * @param string $type
     *    The charge type to validate.
     *
     * @throws \InvalidArgumentException
     *    When the charge type is not supported.
     */
    protected function validateChargeType(string $type): void
    {
        $supported_types = [
            self::CHARGE_TYPE_SALE,
            self::CHARGE_TYPE_AUTH,
            self::CHARGE_TYPE_FORCE,
        ];
        if (in_array($type, $supported_types)) {
            return;
        }

        throw new \InvalidArgumentException(
            "Unknown charge type {$type}."
        );
    }
}
