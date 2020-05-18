<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Order;

use Craft;
use craft\db\Migration;

/**
 * m200517_220621_multi_vendor_add_order_number migration.
 */
class m200517_220621_multi_vendor_add_order_number extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Order::tableName(), 'number', $this->string(32)->after('orderStatusId'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200517_220621_multi_vendor_add_order_number cannot be reverted.\n";
        return false;
    }
}
