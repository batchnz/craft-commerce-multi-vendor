<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Order;
use batchnz\craftcommercemultivendor\records\OrderAdjustment;

use Craft;
use craft\db\Migration;

/**
 * m200707_002212_multi_vendor_add_order_adjustments migration.
 */
class m200707_002212_multi_vendor_add_order_adjustments extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $orderAdjustmentsTableSchema = Craft::$app->db->schema->getTableSchema(OrderAdjustment::tableName());
        if( $orderAdjustmentsTableSchema === null ) {
            $this->createTable(OrderAdjustment::tableName(), [
                'id' => $this->primaryKey(),
                'orderId' => $this->integer()->notNull(),
                'lineItemId' => $this->integer(),
                'type' => $this->string()->notNull(),
                'name' => $this->string(),
                'description' => $this->string(),
                'amount' => $this->decimal(14, 4)->notNull(),
                'included' => $this->boolean(),
                'isEstimated' => $this->boolean()->notNull()->defaultValue(false),
                'sourceSnapshot' => $this->longText(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->addForeignKey(null, OrderAdjustment::tableName(), ['orderId'], Order::tableName(), ['id'], 'CASCADE');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200707_002212_multi_vendor_add_order_adjustments cannot be reverted.\n";
        return false;
    }
}
