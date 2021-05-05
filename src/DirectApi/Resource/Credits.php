<?php

namespace KrystalCode\SagePayments\Sdk\DirectApi\Resource;

use KrystalCode\SagePayments\Sdk\DirectApi\ClientBase;

/**
 * The Credits API.
 */
class Credits extends ClientBase
{
    /**
     * The ID of the Credits API.
     */
    public const ID = 'credits';

    /**
     * Requests a refund for an existing transaction by issuing a credit.
     *
     * Used to process a credit. Referencing a previous transaction allows you
     * to issue a refund without knowing the card number and expiration date.
     *
     * @param string $reference
     *   The reference of the existing transaction to request the refund on.
     * @param array $credit
     *   An associative array containing the details of the credit as described
     *   on the `post_credits_reference` endpoint.
     *
     * @return object|null
     *     The response as an \stdClass object, or null if the response could
     *     not be decoded.
     *
     * @link https://developer.sagepayments.com/bankcard-ecommerce-moto/apis/post/credits/%7Breference%7D
     */
    public function postCreditsReference(
        string $reference,
        array $credit
    ): ?object {
        return $this->postRequest(
            "credits/{$reference}",
            [],
            [],
            ['json' => $credit]
        );
    }
}
