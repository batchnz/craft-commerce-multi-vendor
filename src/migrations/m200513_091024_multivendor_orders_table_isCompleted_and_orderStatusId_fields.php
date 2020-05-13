<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Order;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\commerce\db\Table as CommerceTable;


/**
 * m200513_091024_multivendor_orders_table_isCompleted_and_orderStatusId_fields migration.
 */
class m200513_091024_multivendor_orders_table_isCompleted_and_orderStatusId_fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Add new columns
        $this->addColumn(Order::tableName(), 'orderStatusId', $this->integer()->after('vendorId'));
        $this->addColumn(Order::tableName(), 'isCompleted', $this->boolean()->after('orderStatusId'));

        // Add foreign keys
        $this->addForeignKey(null, Order::tableName(), ['id'], Table::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Order::tableName(), ['orderStatusId'], CommerceTable::ORDERSTATUSES, ['id'], 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200513_091024_multivendor_orders_table_isCompleted_and_orderStatusId_fields cannot be reverted.\n";
        return false;
    }
}
