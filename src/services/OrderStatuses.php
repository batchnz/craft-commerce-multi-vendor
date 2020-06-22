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
use batchnz\craftcommercemultivendor\models\OrderStatus;
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\records\Email;
use batchnz\craftcommercemultivendor\records\OrderStatusEmail;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\db\Table;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\models\OrderStatus as CommerceOrderStatus;
use craft\commerce\services\OrderStatuses as CommerceOrderStatusesService;
use craft\commerce\records\OrderStatus as OrderStatusRecord;

/**
 * Order Statuses Service
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class OrderStatuses extends CommerceOrderStatusesService
{
    // Constants
    // =========================================================================
    const CONFIG_STATUSES_KEY = 'craftCommerceMultiVendor.orderStatuses';

    // Private Properties
    // =========================================================================

    /**
     * @var OrderStatus[]
     */
    private $_orderStatuses;

    // Public Properties
    // =========================================================================

    /**
     * Returns all Order Statuses
     *
     * @param bool $withTrashed
     * @return OrderStatus[]
     */
    public function getAllOrderStatuses($withTrashed = false): array
    {
        // Get the caches items if we have them cached, and the request is for non-trashed items
        if ($this->_orderStatuses !== null) {
            return $this->_orderStatuses;
        }

        $results = $this->_createOrderStatusesQuery($withTrashed)->all();
        $orderStatuses = [];

        foreach ($results as $row) {
            $orderStatuses[] = new OrderStatus($row);
        }

        return $orderStatuses;
    }

    /**
     * Save the order status.
     *
     * @param OrderStatus $orderStatus
     * @param array $emailIds
     * @param bool $runValidation should we validate this order status before saving.
     * @return bool
     * @throws Exception
     */
    public function saveOrderStatus(CommerceOrderStatus $orderStatus, array $emailIds = [], bool $runValidation = true): bool
    {
        $isNewStatus = !(bool)$orderStatus->id;
        if ($isNewStatus) {
            Craft::info('Order status not saved as the multi vendor plugin doesn\'t support new statuses at present.', __METHOD__);
            return false;
        }

        if ($runValidation && !$orderStatus->validate()) {
            Craft::info('Order status not saved due to validation error.', __METHOD__);
            return false;
        }

        $statusUid = Db::uidById(Table::ORDERSTATUSES, $orderStatus->id);

        // Make sure no statuses that are not archived share the handle
        $existingStatus = CommercePlugin::getInstance()->getOrderStatuses()->getOrderStatusByHandle($orderStatus->handle);

        if ($existingStatus && (!$orderStatus->id || $orderStatus->id != $existingStatus->id)) {
            $orderStatus->addError('handle', Plugin::t( 'That handle is already in use'));
            return false;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        if ($orderStatus->dateDeleted) {
            $configData = null;
        } else {
            $emails = Db::uidsByIds(Email::tableName(), $emailIds);
            $configData = [
                'emails' => array_combine($emails, $emails)
            ];
        }

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        $projectConfig->set($configPath, $configData);

        return true;
    }

    /**
     * Handles change order statuses
     * Based heavily off commerce's changed order status method
     * @author Josh Smith <josh@batch.nz>
     * @return void
     */
    public function handleChangedOrderStatus(ConfigEvent $event)
    {
        $statusUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $statusRecord = $this->_getOrderStatusRecord($statusUid);

            $connection = Craft::$app->getDb();
            // Drop them all and we will recreate the new ones.
            $connection->createCommand()->delete(OrderStatusEmail::tableName(), ['orderStatusId' => $statusRecord->id])->execute();

            if (!empty($data['emails'])) {
                foreach ($data['emails'] as $emailUid) {
                    Craft::$app->projectConfig->processConfigChanges(Emails::CONFIG_EMAILS_KEY . '.' . $emailUid);
                }

                $emailIds = Db::idsByUids(Email::tableName(), $data['emails']);

                foreach ($emailIds as $emailId) {
                    $connection->createCommand()
                        ->insert(OrderStatusEmail::tableName(), [
                            'orderStatusId' => $statusRecord->id,
                            'emailId' => $emailId
                        ])
                        ->execute();
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function statusChangeHandler($order, $orderHistory)
    {
        if( !$order->orderStatusId ) return;

        $status = $this->getOrderStatusById($order->orderStatusId);
        if (!$status || count($status->emails) === 0) return;
        // Loop and process status emails
        foreach ($status->emails as $email) {
            // Fetch vendor suborders
            $subOrders = Order::find()->commerceOrderId($order->id)->all();
            if( empty($subOrders) ) return;

            // Fire off an email for each suborder
            foreach ($subOrders as $subOrder) {
                Plugin::getInstance()->getEmails()->sendEmail($email, $subOrder, $orderHistory);
            }
        }
    }

    /**
     * Handles the order status change event.
     * We use this event to send vendor emails
     * @author Josh Smith <josh@batch.nz>
     * @param  OrderStatusEvent $e Event object
     * @return void
     */
    public function handleOrderStatusChangeEvent(OrderStatusEvent $e)
    {
        $this->statusChangeHandler($e->order, $e->orderHistory);
    }

    /**
     * Returns a Query object prepped for retrieving order statuses
     *
     * @param bool $withTrashed
     * @return Query
     */
    private function _createOrderStatusesQuery($withTrashed = false): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'color',
                'description',
                'sortOrder',
                'default',
                'dateDeleted',
                'uid'
            ])
            ->orderBy('sortOrder')
            ->from([Table::ORDERSTATUSES]);
    }

    /**
     * Gets an order status' record by uid.
     *
     * @param string $uid
     * @return OrderStatusRecord
     */
    private function _getOrderStatusRecord(string $uid): OrderStatusRecord
    {
        /** @var OrderStatusRecord $orderStatus */
        if ($orderStatus = OrderStatusRecord::findWithTrashed()->where(['uid' => $uid])->one()) {
            return $orderStatus;
        }

        return new OrderStatusRecord();
    }
}
