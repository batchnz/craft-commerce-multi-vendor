<?php

namespace batchnz\craftcommercemultivendor\events;

use yii\base\Event;

/**
 * CapturePlatformSnapshot class.
 */
class CapturePlatformSnapshotEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var LineItem The lineItem
     */
    public $lineItem;
}
