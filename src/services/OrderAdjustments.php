<?php

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\models\OrderAdjustment;
use batchnz\craftcommercemultivendor\records\OrderAdjustment as OrderAdjustmentRecord;

use Craft;
use craft\commerce\models\OrderAdjustment as CommerceOrderAdjustment;
use craft\commerce\services\OrderAdjustments as CommerceOrderAdjustments;
use craft\db\Query;
use craft\helpers\Json;

/**
 * Order adjustment service.
 */
class OrderAdjustments extends CommerceOrderAdjustments
{
    /**
     * Get all order adjustments by order's ID.
     *
     * @param int $orderId
     * @return OrderAdjustment[]
     */
    public function getAllOrderAdjustmentsByOrderId($orderId): array
    {
        $rows = $this->_createOrderAdjustmentQuery()
            ->where(['orderId' => $orderId])
            ->all();

        $adjustments = [];

        foreach ($rows as $row) {
            $row['sourceSnapshot'] = Json::decodeIfJson($row['sourceSnapshot']);
            $adjustments[] = new OrderAdjustment($row);
        }

        return $adjustments;
    }

    /**
     * @inheritdoc
     * Unfortunately we need to resuse this method from Commerce as we need to use our own OrderAdjustmentRecord.
     */
    public function saveOrderAdjustment(CommerceOrderAdjustment $orderAdjustment, bool $runValidation = true): bool
    {
        $isNewOrderAdjustment = !$orderAdjustment->id;

        if ($orderAdjustment->id) {
            $record = OrderAdjustmentRecord::findOne($orderAdjustment->id);

            if (!$record) {
                throw new Exception(Plugin::t( 'No order Adjustment exists with the ID “{id}”',
                    ['id' => $orderAdjustment->id]));
            }
        } else {
            $record = new OrderAdjustmentRecord();
        }

        if ($runValidation && !$orderAdjustment->validate()) {
            Craft::info('Order Adjustment not saved due to validation error.', __METHOD__);
            return false;
        }

        $record->name = $orderAdjustment->name;
        $record->type = $orderAdjustment->type;
        $record->description = $orderAdjustment->description;
        $record->amount = $orderAdjustment->amount;
        $record->included = $orderAdjustment->included;
        $record->sourceSnapshot = $orderAdjustment->sourceSnapshot;
        $record->lineItemId = $orderAdjustment->getLineItem()->id ?? null;
        $record->orderId = $orderAdjustment->getOrder()->id ?? null;
        $record->sourceSnapshot = $orderAdjustment->sourceSnapshot;
        $record->isEstimated = $orderAdjustment->isEstimated;

        $record->save(false);

        // Update the model with the latest IDs
        $orderAdjustment->id = $record->id;
        $orderAdjustment->orderId = $record->orderId;
        $orderAdjustment->lineItemId = $record->lineItemId;

        return true;
    }

    /**
     * Returns a Query object prepped for retrieving Order Adjustment.
     *
     * @return Query The query object.
     */
    private function _createOrderAdjustmentQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'description',
                'type',
                'amount',
                'included',
                'sourceSnapshot',
                'lineItemId',
                'orderId',
                'isEstimated'
            ])
            ->from([OrderAdjustmentRecord::tableName()]);
    }
}
