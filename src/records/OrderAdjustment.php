<?php

namespace batchnz\craftcommercemultivendor\records;

use batchnz\craftcommercemultivendor\records\Order;
use craft\commerce\records\OrderAdjustment as CommerceOrderAdjustment;
use yii\db\ActiveQueryInterface;

/**
 * @inheritdoc
 */
class OrderAdjustment extends CommerceOrderAdjustment
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_orderadjustments}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }
}
