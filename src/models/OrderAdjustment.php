<?php

namespace batchnz\craftcommercemultivendor\models;

use batchnz\craftcommercemultivendor\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment as CommerceOrderAdjustment;

/**
 * @inheritdoc
 */
class OrderAdjustment extends CommerceOrderAdjustment
{
    /**
     * @var Order|null The order this adjustment belongs to
     */
    private $_order;

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        if ($this->_order === null && $this->orderId) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrder(Order $order)
    {
        $this->_order = $order;
    }
}
