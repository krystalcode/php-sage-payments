<?php

declare(strict_types=1);

namespace KrystalCode\SagePayments\Sdk\DirectApi;

use KrystalCode\SagePayments\Sdk\DirectApi\Resource\ApiHealth;
use KrystalCode\SagePayments\Sdk\DirectApi\Resource\Charges;
use KrystalCode\SagePayments\Sdk\DirectApi\Resource\Credits;
use Psr\Log\LoggerInterface;

/**
 * Factory for generating Direct API resource clients.
 */
class ClientFactory
{
    /**
     * The logger to pass to the client.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Constructs a new ClientFactory object.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *     The logger to pass to the client.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Returns an initialized client for the requested resource.
     *
     * @param string $api
     *     The ID of the API to prepare the client for.
     *
     * @return \KrystalCode\SagePayments\Sdk\DirectApi\ClientInterface
     *     The initialized API client.
     *
     * @throws \InvalidArgumentException
     *     When an unknown API ID is given.
     */
    public function get(string $api, array $config): ClientInterface
    {
        switch ($api) {
            case ApiHealth::ID:
                return new ApiHealth($this->logger, $config);

            case Charges::ID:
                return new Charges($this->logger, $config);

            case Credits::ID:
                return new Credits($this->logger, $config);

            default:
                throw new \InvalidArgumentException(sprintf(
                    'Unknown API "%s"',
                    $api
                ));
        }
    }
}
