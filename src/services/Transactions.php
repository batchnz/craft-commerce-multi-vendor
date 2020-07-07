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
use batchnz\craftcommercemultivendor\models\Transaction;
use batchnz\craftcommercemultivendor\records\Transaction as TransactionRecord;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order as CommerceOrder;
use craft\commerce\models\Transaction as CommerceTransaction;
use craft\commerce\events\TransactionEvent;
use craft\commerce\services\Transactions as CommerceTransactions;

/**
 * Transactions Service
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Transactions extends CommerceTransactions
{
    /**
     * Creates a transaction from the passed order
     * @author Josh Smith <josh@batch.nz>
     * @param  CommerceOrder|null       $order             The order
     * @param  CommerceTransaction|null $parentTransaction A parent transaction
     * @param  string                   $typeOverride      An override type
     * @return CommerceTransaction                         The created transaction
     */
    public function createTransaction(CommerceOrder $order = null, CommerceTransaction $parentTransaction = null, $typeOverride = null): CommerceTransaction
    {
        $commerceTransaction = parent::createTransaction($order, $parentTransaction, $typeOverride);
        $transaction = new Transaction($commerceTransaction);

        $transaction->vendorId = $order->vendorId;

        // Set the vendor amount on the transaction
        // This is the amount the vendor will receive minus NZBEX fees, freight, FAF etc. but including GST.
        $transaction->amount = $order->getTotal();

        if( $commerceTransaction->orderId ){
            $transaction->setOrder($commerceTransaction->getOrder());
        }

        if( $commerceTransaction->gatewayId ){
            $transaction->setGateway($commerceTransaction->getGateway());
        }

        if( $commerceTransaction->id ){
            $transaction->setChildTransactions($commerceTransaction->getChildTransactions());
        }

        if( $commerceTransaction->parentId ){
            $transaction->setParent($commerceTransaction->getParent());
        }

        return $transaction;
    }

    /**
     * Copied from parent to use different database table
     * @author Josh Smith <josh@batch.nz>
     * @param  Transaction  $model
     * @param  bool|boolean $runValidation
     * @return boolean
     */
    public function saveTransaction(CommerceTransaction $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            throw new TransactionException('Transactions cannot be modified.');
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Transaction not saved due to validation error.', __METHOD__);

            return false;
        }

        $fields = [
            'orderId',
            'hash',
            'gatewayId',
            'vendorId',
            'type',
            'status',
            'amount',
            'currency',
            'paymentAmount',
            'paymentCurrency',
            'paymentRate',
            'reference',
            'message',
            'note',
            'code',
            'response',
            'userId',
            'parentId'
        ];

        $record = new TransactionRecord();

        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->save(false);
        $model->id = $record->id;

        if ($model->status === TransactionRecord::STATUS_SUCCESS) {
            $model->order->updateOrderPaidInformation();
        }

        if ($model->status === TransactionRecord::STATUS_PROCESSING) {
            $model->order->markAsComplete();
        }

        // Raise 'afterSaveTransaction' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_TRANSACTION)) {
            $this->trigger(self::EVENT_AFTER_SAVE_TRANSACTION, new TransactionEvent([
                'transaction' => $model
            ]));
        }

        return true;
    }
}
