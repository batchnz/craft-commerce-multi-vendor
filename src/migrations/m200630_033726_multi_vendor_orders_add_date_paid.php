<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Order;

use Craft;
use craft\db\Migration;

/**
 * m200630_033726_multi_vendor_orders_add_date_paid migration.
 */
class m200630_033726_multi_vendor_orders_add_date_paid extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Order::tableName(), 'datePaid', $this->dateTime()->after('isCompleted'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200630_033726_multi_vendor_orders_add_date_paid cannot be reverted.\n";
        return false;
    }
}
