<?php

namespace batchnz\craftcommercemultivendor\records;

use craft\commerce\records\Email as CommerceEmail;

/**
 * Email Record
 * Adds a new constant for vendors emails
 */
class Email extends CommerceEmail
{
    // Constants
    // =========================================================================

    const TYPE_VENDORS = 'vendors';

    /**
     * Declares the name of the database table associated with this AR class.
     * @return string the table name
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_emails}}';
    }
}
