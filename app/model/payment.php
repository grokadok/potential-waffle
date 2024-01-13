<?php

namespace bopdev;

use Exception;
use EasyTransac;

class Payment
{
    private $easytransac_api_key;

    public function __construct()
    {
        $this->easytransac_api_key = getenv('EASYTRANSAC_API_KEY');
        EasyTransac\Core\Services::getInstance()->provideAPIKey($this->easytransac_api_key);
        EasyTransac\Core\Services::getInstance()->setRequestTimeout(30);
        EasyTransac\Core\Services::getInstance()->setDebug(true);
        print('#### Payment initialized ####' . PHP_EOL);
    }

    public function testEasyTransac()
    {
        $customer = (new EasyTransac\Entities\Customer())
            ->setFirstname('Demo')
            ->setLastname('Mini SDK')
            // ->setCity('Strasbourg')
            // ->setUid('a1b2c3d4')
            ->setEmail('contact@bopalace.com');

        $transaction = (new EasyTransac\Entities\PaymentPageTransaction())
            ->setAmount(100)
            ->setClientIp('127.0.0.1')
            // ->setCustomer($customer)
            // ->setOperationType('paybybank')
            // ->setRebill('yes')
            // ->setRecurrence('monthly')
            // ->setReturnUrl('https://example.com/return')
            // ->setCancelUrl('https://example.com/cancel')
        ;

        $pp = new EasyTransac\Requests\PaymentPage();
        $response = $pp->execute($transaction);

        if ($response->isSuccess()) {
            $transactionItem = $response->getContent();
            // Get the payment page URL
            $paymentPageUrl = $transactionItem->getPageUrl();
            print('#### Payment page URL: ' . $paymentPageUrl . ' ####' . PHP_EOL);
            var_dump($transactionItem);
        } else {
            var_dump($response->getErrorMessage());
        }
    }

    public function paymentPage(array $data)
    {
        switch ($data['payment_service']) {
            case 1: // easytransac
                return $this->paymentPageEasyTransac($data);
                break;
        }
    }

    private function paymentPageEasyTransac(array $data)
    {
        $customer = (new EasyTransac\Entities\Customer())->setEmail($data['email']);
        if (!empty($data['firstname'])) $customer->setFirstname($data['firstname']);
        if (!empty($data['lastname'])) $customer->setLastname($data['lastname']);
        if (!empty($data['uid'])) $customer->setUid($data['uid']);

        $transaction = (new EasyTransac\Entities\PaymentPageTransaction())
            ->setAmount($data['amount'])
            ->setClientIp($data['client_ip'])
            ->setCustomer($customer)
            ->setOperationType($data['paymentType'])
            ->setReturnUrl('https://example.com/return') // TODO: set return & cancel url for payment pages
            ->setCancelUrl('https://example.com/cancel');
        if ($data['recurring']) {
            $transaction->setRebill('yes')
                ->setRecurrence('monthly');
        }
        $pp = new EasyTransac\Requests\PaymentPage();
        $response = $pp->execute($transaction);

        if ($response->isSuccess()) {
            $transactionItem = $response->getContent();
            return [
                'request_id' => $transactionItem->getRequestId(),
                'url' => $transactionItem->getPageUrl()
            ];
        } else {
            var_dump($response->getErrorMessage());
        }
    }

    public function refund(array $data)
    {
        switch ($data['payment_service']) {
            case 1: // easytransac
                $this->refundEasyTransac($data);
                break;
        }
    }

    private function refundEasyTransac(array $data)
    {
        $refund = (new EasyTransac\Entities\Refund())
            ->setTid($data['name'])
            ->setReason($data['reason']);

        if (!empty($data['amount'])) $refund->setAmount($data['amount']);

        $request = new EasyTransac\Requests\PaymentRefund();
        $response = $request->execute($refund);

        if ($response->isSuccess()) {
            $refundItem = $response->getContent();
            print('#### Refund successful ####' . PHP_EOL);
            var_dump($refundItem);
        } else {
            var_dump($response->getErrorMessage());
        }
    }

    public function status(array $data)
    {
        switch ($data['payment_service']) {
            case 1: // easytransac
                $this->statusEasyTransac($data);
                break;
        }
    }

    /**
     * Get the status response of a transaction.
     * @param array $data
     * @return EasyTransac\Entities\PaymentStatus
     */
    private function statusEasyTransac(array $data)
    {
        $transaction = new EasyTransac\Entities\PaymentStatus();
        if (!empty($data['transaction_id'])) $transaction->setTid($data['transaction_id']);
        if (!empty($data['request_id'])) $transaction->setRequestId($data['request_id']);
        $request = new EasyTransac\Requests\PaymentStatus();
        $response = $request->execute($transaction);

        if ($response->isSuccess()) {
            $transactionItem = $response->getContent();
            print('#### Transaction status: ' . $transactionItem->getStatus() . ' ####' . PHP_EOL);
            var_dump($transactionItem);

            // return an associative array of the transaction status with transaction id

            return $transactionItem;
        } else {
            var_dump($response->getErrorMessage());
        }
    }
}
