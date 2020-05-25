<?php

namespace batchnz\craftcommercemultivendor\migrations;

use batchnz\craftcommercemultivendor\records\Email;
use batchnz\craftcommercemultivendor\records\OrderStatusEmail;

use Craft;
use craft\db\Migration;
use craft\commerce\db\Table;

/**
 * m200520_075212_multi_vendor_emails migration.
 */
class m200520_075212_multi_vendor_emails extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200520_075212_multi_vendor_emails cannot be reverted.\n";
        return false;
    }

    public function createTables()
    {
        $this->createTable(Email::tableName(), [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'subject' => $this->string()->notNull(),
            'recipientType' => $this->enum('recipientType', ['vendors', 'custom'])->defaultValue('custom'),
            'to' => $this->string(),
            'bcc' => $this->string(),
            'cc' => $this->string(),
            'replyTo' => $this->string(),
            'enabled' => $this->boolean(),
            'attachPdf' => $this->boolean(),
            'templatePath' => $this->string()->notNull(),
            'pdfTemplatePath' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(OrderStatusEmail::tableName(), [
            'id' => $this->primaryKey(),
            'orderStatusId' => $this->integer()->notNull(),
            'emailId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    public function createIndexes()
    {
        $this->createIndex(null, OrderStatusEmail::tableName(), 'orderStatusId', false);
        $this->createIndex(null, OrderStatusEmail::tableName(), 'emailId', false);
    }

    public function addForeignKeys()
    {
        $this->addForeignKey(null, OrderStatusEmail::tableName(), ['emailId'], Email::tableName(), ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, OrderStatusEmail::tableName(), ['orderStatusId'], Table::ORDERSTATUSES, ['id'], 'RESTRICT', 'CASCADE');
    }
}
