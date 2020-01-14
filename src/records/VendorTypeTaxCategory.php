<?php
namespace batchnz\craftcommercemultivendor\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Vendor type tax category record.
 *
 * @property VendorType $vendorType
 * @property int $vendorTypeId
 * @property TaxCategory $taxCategory
 * @property int $taxCategoryId
 */
class VendorTypeTaxCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_vendortypes_taxcategories}}';
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
    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id', 'taxCategoryId']);
    }
}
