<?php

namespace batchnz\craftcommercemultivendor\events;

use yii\base\Event;

/**
 * Vendor type event class.
 *
 * @author Josh Smith <josh@batch.nz>
 * @since 1.0
 */
class VendorTypeEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var VendorType|null The vendor type model associated with the event.
     */
    public $vendorType;

    /**
     * @var bool Whether the vendor type is brand new
     */
    public $isNew = false;
}
