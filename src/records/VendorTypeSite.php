<?php

namespace batchnz\craftcommercemultivendor\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use yii\db\ActiveQueryInterface;

/**
 * Vendor type site record.
 *
 * @property bool $hasUrls
 * @property int $id
 * @property VendorType $vendorType
 * @property int $vendorTypeId
 * @property Site $site
 * @property int $siteId
 * @property string $template
 * @property string $uriFormat
 */
class VendorTypeSite extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_vendortypes_sites}}';
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
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id', 'siteId']);
    }
}
