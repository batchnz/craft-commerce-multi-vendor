<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Order;

use Craft;
use craft\db\Migration;

/**
 * m200515_041911_multi_vendor_add_order_reference migration.
 */
class m200515_041911_multi_vendor_add_order_reference extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(Order::tableName(), 'reference', $this->string()->after('orderStatusId'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200515_041911_multi_vendor_add_order_reference cannot be reverted.\n";
        return false;
    }
}
