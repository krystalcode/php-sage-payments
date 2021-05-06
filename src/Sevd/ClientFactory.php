<?php

declare(strict_types=1);

namespace KrystalCode\SagePayments\Sdk\Sevd;

use KrystalCode\SagePayments\Sdk\Sevd\Request\Charge;
use Psr\Log\LoggerInterface;

/**
 * Factory for generating SEVD request clients.
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
     * Returns an initialized client for the requested request.
     *
     * @param string $request
     *     The ID of the request to prepare the client for.
     *
     * @return \KrystalCode\SagePayments\Sdk\Sevd\ClientBase
     *     The initialized request client.
     *
     * @throws \InvalidArgumentException
     *     When an unknown request ID is given.
     */
    public function get(string $request, array $config): ClientBase
    {
        switch ($request) {
            case Charge::ID:
                return new Charge($this->logger, $config);

            default:
                throw new \InvalidArgumentException(sprintf(
                    'Unknown request "%s"',
                    $api
                ));
        }
    }
}
