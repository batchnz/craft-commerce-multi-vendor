<?php

namespace batchnz\craftcommercemultivendor\behaviors;

use batchnz\craftcommercemultivendor\elements\Order as SubOrder;

use Craft;
use yii\db\ActiveRecord;
use yii\base\Behavior;

class Order extends Behavior
{
    /**
     * Returns sub orders for the current order
     * @author Josh Smith <josh@batch.nz>
     * @return array
     */
    public function getSubOrders()
    {
        return SubOrder::find()->commerceOrderId($this->owner->id)->all();
    }
}
