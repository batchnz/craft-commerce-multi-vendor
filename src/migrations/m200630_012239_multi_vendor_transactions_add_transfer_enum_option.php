<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Transaction;

use Craft;
use craft\db\Migration;

/**
 * m200630_012239_multi_vendor_transactions_add_transfer_enum_option migration.
 */
class m200630_012239_multi_vendor_transactions_add_transfer_enum_option extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn(Transaction::tableName(), 'type', $this->enum('type', ['authorize', 'capture', 'purchase', 'refund', 'transfer'])->notNull());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200630_012239_multi_vendor_transactions_add_transfer_enum_option cannot be reverted.\n";
        return false;
    }
}
