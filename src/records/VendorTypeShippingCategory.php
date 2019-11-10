<?php

namespace thejoshsmith\craftcommercemultivendor\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Vendor type shipping category record.
 *
 * @property VendorType $vendorType
 * @property int $vendorTypeId
 * @property ShippingCategory $shippingCategory
 * @property int $shippingCategoryId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class VendorTypeShippingCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_vendortypes_shippingcategories}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVendorType(): ActiveQueryInterface
    {
        return $this->hasOne(VendorType::class, ['id', 'vendorTypeId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id', 'shippingCategoryId']);
    }
}
