<?php

namespace thejoshsmith\craftcommercemultivendor\records;

use craft\db\ActiveRecord;
use craft\commerce\records\Address;
use yii\db\ActiveQueryInterface;

/**
 * Vendor address record.
 *
 * @property int $id
 * @property int $vendorId
 * @property int $addressId
 */
class VendorAddress extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_vendor_addresses}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getAddress(): ActiveQueryInterface
    {
        return $this->hasOne(Address::class, ['id', 'addressId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVendor(): ActiveQueryInterface
    {
        return $this->hasOne(Vendor::class, ['id', 'vendorId']);
    }
}
