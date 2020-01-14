<?php

namespace batchnz\craftcommercemultivendor\records;

use batchnz\craftcommercemultivendor\Plugin;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Vendor Record
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Vendor extends ActiveRecord
{
    /**
     * Declares the name of the database table associated with this AR class.
     * @return string the table name
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_vendors}}';
    }
}
