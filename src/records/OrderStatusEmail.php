<?php

namespace batchnz\craftcommercemultivendor\records;

use Email;
use craft\db\ActiveRecord;
use craft\commerce\records\OrderStatusEmail as CommerceOrderStatusEmail;
use yii\db\ActiveQueryInterface;

/**
 * Order status email record
 */
class OrderStatusEmail extends CommerceOrderStatusEmail
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_orderstatus_emails}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getEmail(): ActiveQueryInterface
    {
        return $this->hasOne(Email::class, ['id' => 'emailId']);
    }
}
