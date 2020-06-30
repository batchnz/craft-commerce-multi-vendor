<?php

namespace batchnz\craftcommercemultivendor\events;

use yii\base\Event;

/**
 * ModifyCartInfoEvent class.
 */
class ModifyOrderInfoEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var Order The new order
     */
    public $newOrder;

    /**
     * @var Order The previous order before modification
     */
    public $prevOrder;

    /**
     * @var array The order as an array
     */
    public $orderInfo = [];
}
