<?php

namespace batchnz\craftcommercemultivendor\records;

use Craft;
use craft\commerce\records\Order as CommerceOrder;
use batchnz\craftcommercemultivendor\records\Vendor;
use yii\db\ActiveQueryInterface;

/**
 * Order Record
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Order extends CommerceOrder
{
     /**
     * Declares the name of the database table associated with this AR class.
     * @return string the table name
     */
    public static function tableName(): string
    {
        return '{{%commerce_multivendor_orders}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getLineItems(): ActiveQueryInterface
    {
        return parent::getLineItems()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTransactions(): ActiveQueryInterface
    {
        return parent::getTransactions()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getHistories(): ActiveQueryInterface
    {
        return parent::getHistories()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getBillingAddress(): ActiveQueryInterface
    {
        return parent::getBillingAddress()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingAddress(): ActiveQueryInterface
    {
        return parent::getShippingAddress()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscount(): ActiveQueryInterface
    {
        return parent::getDiscount()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGateway(): ActiveQueryInterface
    {
        return parent::getGateway()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPaymentSource(): ActiveQueryInterface
    {
        return parent::getPaymentSource()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCustomer(): ActiveQueryInterface
    {
        return parent::getCustomer()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVendor(): ActiveQueryInterface
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendorId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return parent::getElement()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrderStatus(): ActiveQueryInterface
    {
        return parent::getOrderStatus()->viaTable(CommerceOrder::class, ['id' => 'commerceOrderId']);
    }
}
