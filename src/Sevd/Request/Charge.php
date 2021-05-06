<?php

declare(strict_types=1);

namespace KrystalCode\SagePayments\Sdk\Sevd\Request;

use KrystalCode\SagePayments\Sdk\Sevd\ClientBase;

/**
 * Client for issuing charge requests.
 */
class Charge extends ClientBase
{
    /**
     * The ID of the Charge request.
     */
    public const ID = 'charge';

    /**
     * The Capture transaction (charge) type.
     */
    protected const CHARGE_TYPE_CAPTURE = '11';

    /**
     * The Authorization transaction (charge) type.
     */
    protected const CHARGE_TYPE_AUTH = '12';

    /**
     * Returns a tokenized request for a UI-enabled sale.
     *
     * @param array $transaction
     *     An associative array containing the details of the transaction. See
     *     \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildTransactionBase()
     * @param array $customer
     *     An associative array containing the details of the customer. See
     *     \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildCustomer()
     * @param bool $capture
     *     `false` to request an Auth charge (authorization only), `true` for a
     *     Sale charge (capture).
     * @param array $ui_settings
     *     An associative array containing UI-related settings. See
     *     \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildUi()
     *
     * @return string
     *     The tokenized request string.
     *
     * @throws \InvalidArgumentException
     *     When required items are missing from the transaction details.
     * @throws \GuzzleHttp\Exception\GuzzleException
     *     If the request was unsuccessful.
     *
     * @see \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildTransactionBase()
     * @see \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildCustomer()
     * @see \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildUi()
     */
    public function uiSale(
        array $transaction,
        array $customer,
        bool $capture,
        array $ui_settings = []
    ): string {
        $request = $this->initXmlRequest();

        $this->buildApplication($request);
        $this->buildPayments($request, $transaction, $customer, $capture);
        $this->buildUi($request, $ui_settings);

        return $this->getTokenizedRequest($request);
    }

    /**
     * Adds the Application element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $request
     *     The request XML element.
     */
    protected function buildApplication(\SimpleXMLElement $request): void
    {
        $application = $request->addChild('Application');
        $application->addChild(
            'ApplicationID',
            $this->config['application_id']
        );
        $application->addChild(
            'LanguageID',
            $this->config['language_id']
        );
    }

    /**
     * Adds the Payments element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $request
     *     The request XML element.
     * @param array $transaction
     *     An associative array containing the details of the transaction. See
     *     \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildTransactionBase()
     * @param array $customer
     *     An associative array containing the details of the customer. See
     *     \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildCustomer()
     * @param bool $capture
     *     `false` to request an Auth charge (authorization only), `true` for a
     *     Sale charge (capture).
     *
     * @see \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildTransactionBase()
     * @see \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildCustomer()
     */
    protected function buildPayments(
        \SimpleXMLElement $request,
        array $transaction,
        array $customer,
        bool $capture
    ): void {
        $payments = $request->addChild('Payments');
        $payment_type = $payments->addChild('PaymentType');

        $this->buildMerchant($payment_type);
        $this->buildTransactionBase($payment_type, $transaction, $capture);
        $this->buildCustomer($payment_type, $customer);
        $this->buildVaultStorage($payment_type);
    }

    /**
     * Adds the UI element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $request
     *     The request XML element.
     * @param array $ui_settings
     *     An associative array containing UI-related settings. Currently
     *     supported UI settings are the ones related to customer display. See
     *     \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildUiCustomer()
     *
     * @see \KrystalCode\SagePayments\Sdk\Sevd\Request\Charge::buildUiCustomer()
     */
    protected function buildUi(
        \SimpleXMLElement $request,
        array $ui_settings
    ): void {
        $single_payment = $request->addChild('UI')->addChild('SinglePayment');

        $this->buildUiTransactionBase($single_payment);
        $this->buildUiCustomer($single_payment, $ui_settings);
    }

    /**
     * Adds the Merchant element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $payment_type
     *     The PaymentType XML element.
     */
    protected function buildMerchant(\SimpleXMLElement $payment_type): void
    {
        $merchant = $payment_type->addChild('Merchant');
        $merchant->addChild('MerchantID', $this->config['merchant_id']);
        $merchant->addChild('MerchantKey', $this->config['merchant_key']);
    }

    /**
     * Adds the TransactionBase element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $payment_type
     *     The PaymentType XML element.
     * @param array $transaction
     *     An associative array containing the details of the transaction.
     *     Currently supported items are:
     *     - Reference1: (string, required) The merchant order reference.
     *     - Amount: (string, required) The total amount of the charge.
     *     - TransactionID: (string, optional) The merchant transaction ID.
     * @param bool $capture
     *     `false` to request an Auth charge (authorization only), `true` for a
     *     Sale charge (capture).
     *
     * @throws \InvalidArgumentException
     *     When required items are missing from the transaction details.
     */
    protected function buildTransactionBase(
        \SimpleXMLElement $payment_type,
        array $transaction,
        bool $capture
    ): void {
        $required = [
            'Reference1',
            'Amount',
        ];
        $missing = array_diff($required, array_keys($transaction));
        if ($missing) {
            throw new \InvalidArgumentException(sprintf(
                'The following required items are missing from the transaction data array: %s.',
                implode(', ', $missing)
            ));
        }

        $transaction_base = $payment_type->addChild('TransactionBase');

        if (!empty($transaction['TransactionID'])) {
            $transaction_base->addChild(
                'TransactionID',
                $transaction['TransactionID']
            );
        }

        $transaction_base->addChild(
            'TransactionType',
            $capture ? self::CHARGE_TYPE_CAPTURE : self::CHARGE_TYPE_AUTH
        );

        $transaction_base->addChild('Reference1', $transaction['Reference1']);
        $transaction_base->addChild('Amount', $transaction['Amount']);
    }

    /**
     * Adds the Customer element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $payment_type
     *     The PaymentType XML element.
     * @param array $customer
     *     An associative array containing the details of the customer.
     *     Currently supported items are:
     *     - Name: (array, optional) An array containing the following items:
     *         - FirstName: (string, optional) The customer's first name.
     *         - MI: (string, optional) The customer's middle name.
     *         - LastName: (string, optional) The customer's last name.
     *     - Address: (array, optional) An array containing the details of the
     *         customer's billing address:
     *         - AddressLine1: (string, optional) The address's address line 1.
     *         - AddressLine2: (string, optional) The address's address line 2.
     *         - City: (string, optional) The address's city.
     *         - State: (string, optional) The address's state.
     *         - ZipCode: (string, optional) The address's zip code.
     *         - Country: (string, optional) The address's country code.
     */
    protected function buildCustomer(
        \SimpleXMLElement $payment_type,
        array $customer
    ): void {
        $address = array_merge(
            [
                'AddressLine1' => '',
                'AddressLine2' => '',
                'City' => '',
                'State' => '',
                'ZipCode' => '',
                'Country' => '',
            ],
            $customer['Address'] ?? []
        );
        $name = array_merge(
            [
                'FirstName' => '',
                'MI' => '',
                'LastName' => '',
            ],
            $customer['Name'] ?? []
        );

        $customer_node = $payment_type->addChild('Customer');

        $name_node = $customer_node->addChild('Name');
        $name_node->addChild('FirstName', $name['FirstName']);
        $name_node->addChild('MI', $name['MI']);
        $name_node->addChild('LastName', $name['LastName']);

        $address_node = $customer_node->addChild('Address');
        $address_node->addChild('AddressLine1', $address['AddressLine1']);
        $address_node->addChild('AddressLine2', $address['AddressLine2']);
        $address_node->addChild('City', $address['City']);
        $address_node->addChild('State', $address['State']);
        $address_node->addChild('ZipCode', $address['ZipCode']);
        $address_node->addChild('Country', $address['Country']);
    }

    /**
     * Adds the VaultStorage element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $payment_type
     *     The PaymentType XML element.
     */
    protected function buildVaultStorage(\SimpleXMLElement $payment_type): void
    {
        $vault_storage = $payment_type->addChild('VaultStorage');
        $vault_storage->addChild('Service', 'CREATE');
    }

    /**
     * Adds the TransactionBase element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $single_payment
     *     The SinglePayment XML element.
     */
    protected function buildUiTransactionBase(
        \SimpleXMLElement $single_payment
    ): void {
        $transaction_base = $single_payment->addChild('TransactionBase');

        $reference = $transaction_base->addChild('Reference1');
        $reference->addChild('Enabled', 'false');
        $reference->addChild('Visible', 'true');

        $subtotal = $transaction_base->addChild('SubtotalAmount');
        $subtotal->addChild('Enabled', 'false');
        $subtotal->addChild('Visible', 'true');

        $tax = $transaction_base->addChild('TaxAmount');
        $tax->addChild('Enabled', 'false');
        $tax->addChild('Visible', 'false');

        $shipping = $transaction_base->addChild('ShippingAmount');
        $shipping->addChild('Enabled', 'false');
        $shipping->addChild('Visible', 'false');
    }

    /**
     * Adds the Customer element and its sub-elements to the XML request.
     *
     * @param \SimpleXMLElement $single_payment
     *     The SinglePayment XML element.
     * @param array $ui_settings
     *     An associative array containing UI-related settings. Currently
     *     supported UI settings related to customer display are:
     *     - edit_customer: (bool, optional) Whether to allow editing of the
     *         customer details (name and billing address) on the UI. Defaults
     *         to FALSE.
     */
    protected function buildUiCustomer(
        \SimpleXMLElement $single_payment,
        array $ui_settings
    ): void {
        $customer = $single_payment->addChild('Customer');
        $enabled = empty($ui_settings['edit_customer']) ? 'false' : 'true';

        $billing_properties = [
            'Name' => [
                'FirstName',
                'MI',
                'LastName',
            ],
            'Address' => [
                'AddressLine1',
                'AddressLine2',
                'City',
                'State',
                'ZipCode',
                'Country',
            ],
        ];
        foreach ($billing_properties as $group => $properties) {
            $group_node = $customer->addChild($group);
            foreach ($properties as $property) {
                $property_node = $group_node->addChild($property);
                $property_node->addChild('Enabled', $enabled);
                $property_node->addChild('Visible', 'true');
            }

            if ($group !== 'Address') {
                continue;
            }

            $email = $group_node->addChild('EmailAddress');
            $email->addChild('Enabled', 'false');
            $email->addChild('Visible', 'false');

            $telephone = $group_node->addChild('Telephone');
            $telephone->addChild('Enabled', 'false');
            $telephone->addChild('Visible', 'false');

            $customer_number = $group_node->addChild('CustomerNumber');
            $customer_number->addChild('Enabled', 'false');
            $customer_number->addChild('Visible', 'false');
        }
    }
}
