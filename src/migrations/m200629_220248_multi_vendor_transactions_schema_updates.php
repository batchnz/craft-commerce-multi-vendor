<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Transaction;

use Craft;
use craft\db\Migration;
use craft\db\Table;

/**
 * m200629_220248_multi_vendor_transactions_schema_updates migration.
 */
class m200629_220248_multi_vendor_transactions_schema_updates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn(Transaction::tableName(), 'commerceTransactionId');
        $this->addColumn(Transaction::tableName(), 'userId', $this->integer()->after('gatewayId'));
        $this->addForeignKey(null, Transaction::tableName(), ['userId'], Table::USERS, ['id']);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200629_220248_multi_vendor_transactions_schema_updates cannot be reverted.\n";
        return false;
    }
}
