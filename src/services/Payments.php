<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\elements\Vendor;
use batchnz\craftcommercemultivendor\models\Transaction;
use batchnz\craftcommercemultivendor\records\Transaction as TransactionRecord;

use Craft;
use craft\base\Component;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\stripe\events\BuildGatewayRequestEvent;
use craft\db\Query;

/**
 * Payments Service
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Payments extends Component
{
    /**
     * Modifies the Stripe Gateway request by adding a transfer group parameter
     * which we can use later to route funds between connected vendor accounts.
     * @author Josh Smith <josh@batch.nz>
     * @param  BuildGatewayRequestEvent $e Event object
     * @return void
     */
    public function handleBuildGatewayRequestEvent(BuildGatewayRequestEvent $e)
    {
        if ($e->transaction->type === 'purchase') {
            $e->request['transfer_group'] = 'ORDER{'.$e->transaction->orderId.'}';
        }
    }

    /**
     * Processes a transfer for the passed order
     * @author Josh Smith <josh@batch.nz>
     * @param  Order  $order Order to process transfer for
     * @return void
     */
    public function processTransfer(Order $order)
    {
        $parentOrder = $order->getParentOrder();
        if( empty($parentOrder) ) throw new \Exception('Vendor order has no parent');

        // Fetch the last transaction, we'll take the gateway information off this record
        $lastTransaction = $parentOrder->getLastTransaction();
        if( empty($lastTransaction) ) throw new \Exception('No transaction exists for the order');

        // Set the correct gateway
        $order->gatewayId = $lastTransaction->gatewayId;

        // Create a transaction object
        $transaction = Plugin::getInstance()->getTransactions()->createTransaction($order);
        $transaction->type = TransactionRecord::TYPE_TRANSFER;
        $vendor = $order->getVendor();

        try {
            // Transfer the vendor payout to their Stripe account
            $transfer = \Stripe\Transfer::create([
              'amount' => (int) ($transaction->amount * 100),
              'currency' => strtolower($transaction->currency),
              'destination' => $vendor->stripe_user_id,
              'description' => 'Proceeds from NZBEX order #'.$parentOrder->number,
              'source_transaction' => $lastTransaction->reference,
              'transfer_group' => 'ORDER{'.$order->commerceOrderId.'}'
            ]);

            $response = $transfer->jsonSerialize();

            $this->_updateTransaction($transaction, $response);

            if ($transaction->status !== TransactionRecord::STATUS_SUCCESS) {
                throw new PaymentException($transaction->message);
            }

            // Success!
            $order->updateOrderPaidInformation();
        } catch (\Exception $e) {
            $transaction->status = TransactionRecord::STATUS_FAILED;
            $transaction->message = $e->getMessage();

            // If this transaction is already saved, don't even try.
            if (!$transaction->id) {
                $this->_saveTransaction($transaction);
            }

            Craft::error($e->getMessage());
            throw new PaymentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getTotalPaidForOrder(Order $order): float
    {
        $paid = (float)(new Query())
            ->from([TransactionRecord::tableName()])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE, TransactionRecord::TYPE_TRANSFER]
            ])
            ->sum('amount');

        return $paid - $this->getTotalRefundedForOrder($order);
    }

    /**
     * Gets the total transactions amount refunded.
     *
     * @param Order $order
     * @return float
     */
    public function getTotalRefundedForOrder(Order $order): float
    {
        return (float)(new Query())
            ->from([TransactionRecord::tableName()])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_REFUND]
            ])
            ->sum('amount');
    }

    /**
     * Gets the total transactions amount with authorized.
     *
     * @param Order $order
     * @return float
     */
    public function getTotalAuthorizedForOrder(Order $order): float
    {
        $authorized = (float)(new Query())
            ->from([TransactionRecord::tableName()])
            ->where([
                'orderId' => $order->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => [TransactionRecord::TYPE_AUTHORIZE, TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE, TransactionRecord::TYPE_TRANSFER]
            ])
            ->sum('amount');

        return $authorized - $this->getTotalRefundedForOrder($order);
    }

    /**
     * Stolen from commerce payments service as it's a private method.
     * @see  craft\commerce\services\Payments
     */
    private function _saveTransaction($child)
    {
        if (!Plugin::getInstance()->getTransactions()->saveTransaction($child)) {
            throw new TransactionException('Error saving transaction: ' . implode(', ', $child->errors));
        }
    }

    /**
     * Stolen from commerce payments service as it's a private method.
     * @see  craft\commerce\services\Payments
     */
    private function _updateTransaction(Transaction $transaction, array $response)
    {
        $transaction->status = TransactionRecord::STATUS_SUCCESS;
        $transaction->response = $response;
        $transaction->code = 200;
        $transaction->reference = $response['id'];
        $transaction->message = $response['destination'];

        $this->_saveTransaction($transaction);
    }
}
