<?php

namespace batchnz\craftcommercemultivendor\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;
use craft\commerce\db\Table;
use batchnz\craftcommercemultivendor\records\Order;
use batchnz\craftcommercemultivendor\records\Transaction;
use batchnz\craftcommercemultivendor\records\Vendor;
use batchnz\craftcommercemultivendor\records\VendorType;
use batchnz\craftcommercemultivendor\records\VendorTypeSite;
use batchnz\craftcommercemultivendor\records\VendorTypeShippingCategory;
use batchnz\craftcommercemultivendor\records\VendorTypeTaxCategory;
use batchnz\craftcommercemultivendor\records\VendorAddress;
use batchnz\craftcommercemultivendor\records\PlatformSettings;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    public function createTables()
    {
        $vendorTableSchema = Craft::$app->db->schema->getTableSchema(Vendor::tableName());
        if( $vendorTableSchema === null ) {
            $this->createTable(Vendor::tableName(), [
                'id' => $this->primaryKey(),
                'stripe_access_token' => $this->string(255),
                'stripe_refresh_token' => $this->string(255),
                'stripe_publishable_key' => $this->string(255),
                'stripe_user_id' => $this->string(255),
                'stripe_token_type' => $this->string(255),
                'stripe_livemode' => $this->enum('stripe_livemode', ['0','1'])->defaultValue('1'),
                'stripe_scope' => $this->string(255),
                'postDate' => $this->dateTime(),
                'expiryDate' => $this->dateTime(),
                'typeId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid()
            ]);
        }

        $orderTableSchema = Craft::$app->db->schema->getTableSchema(Order::tableName());
        if( $orderTableSchema === null ) {
            $this->createTable(Order::tableName(), [
                'id' => $this->primaryKey(),
                'commerceOrderId' => $this->integer(),
                'vendorId' => $this->integer(),
                'total' => $this->decimal(14,4),
                'totalPaid' => $this->decimal(14,4),
                'paidStatus' => $this->enum('type', ['paid','partial','unpaid','overPaid'])->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid()
            ]);
        }

        $transactionTableSchema = Craft::$app->db->schema->getTableSchema(Transaction::tableName());
        if( $transactionTableSchema === null ) {
            $this->createTable(Transaction::tableName(), [
                'id' => $this->primaryKey(),
                'commerceTransactionId' => $this->integer()->notNull(),
                'orderId' => $this->integer()->notNull(),
                'parentId' => $this->integer(),
                'gatewayId' => $this->integer(),
                'vendorId' => $this->integer(),
                'hash' => $this->string(32),
                'type' => $this->enum('type', ['authorize', 'capture', 'purchase', 'refund'])->notNull(),
                'amount' => $this->decimal(14, 4),
                'paymentAmount' => $this->decimal(14, 4),
                'currency' => $this->string(),
                'paymentCurrency' => $this->string(),
                'paymentRate' => $this->decimal(14, 4),
                'status' => $this->enum('status', ['pending', 'redirect', 'success', 'failed', 'processing'])->notNull(),
                'reference' => $this->string(),
                'code' => $this->string(),
                'message' => $this->text(),
                'note' => $this->mediumText(),
                'response' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid()
            ]);

            $vendorTypeTableSchema = Craft::$app->db->schema->getTableSchema(VendorType::tableName());
            if( $vendorTypeTableSchema === null ) {
                $this->createTable(VendorType::tableName(), [
                    'id' => $this->primaryKey(),
                    'fieldLayoutId' => $this->integer(),
                    'name' => $this->string()->notNull(),
                    'handle' => $this->string()->notNull(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]);
            }

            $vendorTypeSiteTableSchema = Craft::$app->db->schema->getTableSchema(VendorTypeSite::tableName());
            if( $vendorTypeSiteTableSchema === null ) {
                $this->createTable(VendorTypeSite::tableName(), [
                    'id' => $this->primaryKey(),
                    'vendorTypeId' => $this->integer()->notNull(),
                    'siteId' => $this->integer()->notNull(),
                    'uriFormat' => $this->text(),
                    'template' => $this->string(500),
                    'hasUrls' => $this->boolean(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]);
            }

            $vendorTypeShippingCategoryTableSchema = Craft::$app->db->schema->getTableSchema(VendorTypeShippingCategory::tableName());
            if( $vendorTypeShippingCategoryTableSchema === null ) {
                $this->createTable(VendorTypeShippingCategory::tableName(), [
                    'id' => $this->primaryKey(),
                    'vendorTypeId' => $this->integer()->notNull(),
                    'shippingCategoryId' => $this->integer()->notNull(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]);
            }

            $vendorTypeTaxCategoryTableSchema = Craft::$app->db->schema->getTableSchema(VendorTypeTaxCategory::tableName());
            if( $vendorTypeTaxCategoryTableSchema === null ) {
                $this->createTable(VendorTypeTaxCategory::tableName(), [
                    'id' => $this->primaryKey(),
                    'vendorTypeId' => $this->integer()->notNull(),
                    'taxCategoryId' => $this->integer()->notNull(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]);
            }

            $vendorAddressesTableSchema = Craft::$app->db->schema->getTableSchema(VendorAddress::tableName());
            if( $vendorAddressesTableSchema === null ) {
                $this->createTable(VendorAddress::tableName(), [
                    'id' => $this->primaryKey(),
                    'vendorId' => $this->integer()->notNull(),
                    'addressId' => $this->integer()->notNull(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]);
            }

            $vendorSettingsTableSchema = Craft::$app->db->schema->getTableSchema(PlatformSettings::tableName());
            if( $vendorSettingsTableSchema === null ) {
                $this->createTable(PlatformSettings::tableName(), [
                    'id' => $this->primaryKey(),
                    'commission' => $this->decimal(14,4)->notNull()->default('0.00')->unsigned(),
                    'commissionType' => $this->enum('commissionType', ['percentage','amount'])->defaultValue('percentage')->notNull(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]);
            }
        }

        return true;
    }

    public function createIndexes()
    {
        $this->createIndex(null, Transaction::tableName(), 'parentId', false);
        $this->createIndex(null, Transaction::tableName(), 'orderId', false);
        $this->createIndex(null, Transaction::tableName(), 'gatewayId', false);
        $this->createIndex(null, Transaction::tableName(), 'commerceTransactionId', false);
        $this->createIndex(null, Transaction::tableName(), 'vendorId', false);
        $this->createIndex(null, Order::tableName(), 'commerceOrderId', false);
        $this->createIndex(null, Order::tableName(), 'vendorId', false);
    }

    public function addForeignKeys()
    {
        $this->addForeignKey(null, Transaction::tableName(), ['orderId'], Order::tableName(), ['id'], 'CASCADE');
        $this->addForeignKey(null, Transaction::tableName(), ['commerceTransactionId'], Table::TRANSACTIONS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Transaction::tableName(), ['parentId'], Transaction::tableName(), ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Transaction::tableName(), ['gatewayId'], Table::GATEWAYS, ['id'], null, 'CASCADE');
        $this->addForeignKey(null, Transaction::tableName(), ['vendorId'], Vendor::tableName(), ['id'], 'SET NULL');
        $this->addForeignKey(null, Order::tableName(), ['commerceOrderId'], Table::ORDERS, ['id'], 'CASCADE');
        $this->addForeignKey(null, Order::tableName(), ['vendorId'], Vendor::tableName(), ['id'], 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropForeignKeys();
        $this->dropTables();
        return true;
    }

    public function dropForeignKeys()
    {
        if ($this->_tableExists(Transaction::tableName())) {
            MigrationHelper::dropAllForeignKeysToTable(Transaction::tableName(), $this);
            MigrationHelper::dropAllForeignKeysOnTable(Transaction::tableName(), $this);
        }
        if ($this->_tableExists(Order::tableName())) {
            MigrationHelper::dropAllForeignKeysToTable(Order::tableName(), $this);
            MigrationHelper::dropAllForeignKeysOnTable(Order::tableName(), $this);
        }
    }

    public function dropTables()
    {
        $this->dropTableIfExists(VendorTypeTaxCategory::tableName());
        $this->dropTableIfExists(VendorTypeShippingCategory::tableName());
        $this->dropTableIfExists(VendorTypeSite::tableName());
        $this->dropTableIfExists(VendorType::tableName());
        $this->dropTableIfExists(Transaction::tableName());
        $this->dropTableIfExists(Order::tableName());
        $this->dropTableIfExists(Vendor::tableName());
        $this->dropTableIfExists(VendorAddress::tableName());
        $this->dropTableIfExists(PlatformSettings::tableName());
    }

    /**
     * Returns if the table exists.
     *
     * @param string $tableName
     * @param Migration|null $migration
     * @return bool If the table exists.
     */
    private function _tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName($tableName);
        $table = $schema->getTableSchema($rawTableName);

        return (bool)$table;
    }
}
