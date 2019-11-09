<?php

namespace thejoshsmith\craftcommercemultivendor\records;

use thejoshsmith\craftcommercemultivendor\Plugin;

use Craft;
use craft\db\ActiveRecord;
use craft\records\User;
use craft\commerce\records\Transaction as CommerceTransaction;
use thejoshsmith\craftcommercemultivendor\records\Vendor;
use thejoshsmith\craftcommercemultivendor\records\Order as VendorOrder;
use yii\db\ActiveQueryInterface;

/**
 * Transaction Record
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Transaction extends CommerceTransaction
{
     /**
     * Declares the name of the database table associated with this AR class.
     * @return string the table name
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_transactions}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCommerceTransaction(): ActiveQueryInterface
    {
        return $this->hasOne(CommerceTransaction::class, ['id' => 'commerceTransactionId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVendor(): ActiveQueryInterface
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendorId']);   
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId'])
            ->viaTable(CommerceTransaction::class, ['id' => 'commerceTransactionId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId'])
            ->viaTable(CommerceTransaction::class, ['id' => 'commerceTransactionId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCommerceOrder(): ActiveQueryInterface
    {
        return $this->hasOne(VendorOrder::class, ['id' => 'orderId']);
    }
}
