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
     * @var array The order
     */
    public $order;

    /**
     * @var array The order as an array
     */
    public $orderInfo = [];
}
