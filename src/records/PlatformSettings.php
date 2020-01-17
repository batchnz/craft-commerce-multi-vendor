<?php

namespace batchnz\craftcommercemultivendor\records;

use craft\db\ActiveRecord;

/**
 * Platform Setttings
 *
 * @property int $id
 * @property float $commission
 * @property string $commissionType
 */
class PlatformSettings extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_platform_settings}}';
    }
}
