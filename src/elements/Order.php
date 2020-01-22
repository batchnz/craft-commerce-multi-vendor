<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\elements;

use batchnz\craftcommercemultivendor\Plugin;

use Craft;
use craft\commerce\elements\Order as CommerceOrder;

/**
 * Order Element
 *
 * This element represents a vendors portion of the platforms original order
 *
 * @property int            $commerceOrderId     The actual platform order ID
 * @property int            $vendorId            The Id of the vendor that this order portion belongs to
 * @property int            $total               The total portion of the vendors order
 * @property float          $totalPaid           The total paid
 * @property string|null    $paidStatus          The paid status
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Order extends CommerceOrder
{
    /**
     * The related commerce order
     * @var CommerceOrder
     */
    private $_commerceOrder;

    public $commerceOrderId;
    public $vendorId;

    /**
     * Getter to return either a property on this object, or the commerce order.
     * @author Josh Smith <josh@batch.nz>
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        if( !empty($this->$key) ){
            return $this->$key;
        }
        if( !empty($this->_commerceOrder->$key) ){
            return $this->_commerceOrder->$key;
        }
    }
}
