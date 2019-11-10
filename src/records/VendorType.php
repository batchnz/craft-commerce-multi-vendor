<?php

namespace thejoshsmith\craftcommercemultivendor\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Vendor type record.
 */
class VendorType extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_vendortypes}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVendorTypesShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(VendorTypeShippingCategory::class, ['vendorTypeId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ShippingCategory::class, ['id' => 'shippingCategoryId'])
            ->via('vendorTypesShippingCategories');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVendorTypesTaxCategories(): ActiveQueryInterface
    {
        return $this->hasMany(VendorTypeTaxCategory::class, ['vendorTypeId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxCategories(): ActiveQueryInterface
    {
        return $this->hasMany(TaxCategory::class, ['id' => 'taxCategoryId'])
            ->via('vendorTypesTaxCategories');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
