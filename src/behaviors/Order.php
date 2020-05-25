<?php

namespace batchnz\craftcommercemultivendor\behaviors;

use batchnz\craftcommercemultivendor\elements\Order as SubOrder;
use batchnz\craftcommercemultivendor\elements\Vendor;

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

     /**
     * Returns sub orders for the current order
     * @author Josh Smith <josh@batch.nz>
     * @return array
     */
    public function hasSubOrders()
    {
        return SubOrder::find()->commerceOrderId($this->owner->id)->exists();
    }

    /**
     * Returns an array of vendors linked to this order
     * @author Josh Smith <josh@batch.nz>
     * @return array An array of vendors
     */
    public function getVendors()
    {
        return Vendor::find()->hasOrder($this->owner)->all();
    }

    /**
     * Returns an array of vendor email addresses
     * @author Josh Smith <josh@batch.nz>
     * @return array
     */
    public function getVendorEmails()
    {
        $vendors = $this->getVendors();
        if( empty($vendors) ) return [];

        $vendorEmails = [];
        foreach ($vendors as $vendor) {
            $user = $vendor->getUser();
            if( empty($user) ) continue;

            $vendorEmails[] = $user->email;
        }

        return $vendorEmails;
    }
}
