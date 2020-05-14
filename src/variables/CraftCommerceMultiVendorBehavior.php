<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\variables;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\elements\Order as SubOrder;
use batchnz\craftcommercemultivendor\db\OrderQuery as SubOrderQuery;
use batchnz\craftcommercemultivendor\elements\Vendor;
use batchnz\craftcommercemultivendor\elements\db\VendorQuery;
use batchnz\craftcommercemultivendor\records\VendorType;
use yii\db\ActiveQuery;
use yii\base\Behavior;

use Craft;

/**
 * Craft Commerce Multi Vendor Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.craftCommerceMultiVendor }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class CraftCommerceMultiVendorBehavior extends Behavior
{
    /**
     * @var Plugin
     */
    public $commerceMultiVendor;

    public function init()
    {
        parent::init();

        // Point `craft.commerceMultiVendor` to the batchnz\craftcommercemultivendor\Plugin instance
        $this->commerceMultiVendor = Plugin::getInstance();
    }

    public function vendors($criteria = null): VendorQuery
    {
        $query = Vendor::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }

    public function vendorTypes($criteria = null): ActiveQuery
    {
        $query = VendorType::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }

    public function subOrders($criteria = null): SubOrderQuery
    {
        $query = SubOrder::find();
        if ($criteria) {
            Craft::configure($query, $criteria);
        }
        return $query;
    }
}
