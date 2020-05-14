<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\elements;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\db\OrderQuery;
use batchnz\craftcommercemultivendor\records\Order as OrderRecord;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\commerce\elements\Order as CommerceOrder;

/**
 * Order Element
 *
 * This element represents a vendors portion of the platforms original order
 *
 * @property int            $commerceOrderId     The actual platform order ID
 * @property int            $vendorId            The Id of the vendor that this order portion belongs to
 * @property int            $orderStatusId       The Id of the order status
 * @property int            $number              The order number
 * @property int            $total               The total portion of the vendors order
 * @property float          $totalPaid           The total paid
 * @property string|null    $paidStatus          The paid status
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Order extends CommerceOrder
{
    /**
     * The related commerce order
     * @var CommerceOrder
     */
    private $_commerceOrder;

    /**
     * Related commerce order Id
     * @var int
     */
    public $commerceOrderId;

    /**
     * Related vendor Id
     * @var int
     */
    public $vendorId;

    /**
     * Cache for line items
     * @var array
     */
    private $_lineItems;

    /**
     * Cache for order adjustments
     * @var array
     */
    private $_orderAdjustments;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if( !empty($this->commerceOrderId) ){
            $order = CommerceOrder::find()->id($this->commerceOrderId)->one();
            if( !empty($order) ) $this->setOrder($order);
        }

        parent::init();
    }

    /**
     * @return OrderAdjustment[]
     */
    public function getAdjustments(): array
    {
        if( empty($this->_orderAdjustments) ){
            $lineItems = $this->getLineItems();
            $orderAdjustments = $this->_commerceOrder->getAdjustments();

            // Filter out adjustments that don't match line items on this order
            $lineItemIds = array_column($lineItems, 'id');
            foreach ($orderAdjustments as $i => $orderAdjustment) {
                if( !in_array($orderAdjustment->lineItemId, $lineItemIds) ){
                    unset($orderAdjustments[$i]);
                }
            }

            $this->_orderAdjustments = $orderAdjustments;
        }

        return $this->_orderAdjustments;
    }

    /**
     * @return array
     */
    public function getOrderAdjustments(): array
    {
       return $this->_commerceOrder->getOrderAdjustments();
    }

    /**
     * @return LineItem[]
     */
    public function getLineItems(): array
    {
        if ($this->_lineItems === null) {
            $lineItems = $this->vendorId ? Plugin::getInstance()->getLineItems()->getAllLineItemsByOrderAndVendorId($this->commerceOrderId, $this->vendorId) : [];
            foreach ($lineItems as $lineItem) {
                $lineItem->setOrder($this);
            }
            $this->_lineItems = $lineItems;
        }

        return $this->_lineItems;
    }

    public function getVendor()
    {
        return Vendor::find()->subOrderId($this->id)->one();
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    /**
     * @inheritdoc
     * @return OrderQuery The newly created [[OrderQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new OrderQuery(static::class);
    }

    /**
     * Sets the parent order
     * @author Josh Smith <josh@batch.nz>
     * @param  CommerceOrder $order
     */
    public function setOrder(CommerceOrder $order)
    {
        // Map commerce order properties onto this object
        $this->number = $order->number;
        $this->reference = $order->reference;
        $this->couponCode = $order->couponCode;
        $this->dateOrdered = $order->dateOrdered;
        $this->datePaid = $order->datePaid;
        $this->currency = $order->currency;
        $this->gatewayId = $order->gatewayId;
        $this->lastIp = $order->lastIp;
        $this->orderLanguage = $order->orderLanguage;
        $this->message = $order->message;
        $this->returnUrl = $order->returnUrl;
        $this->cancelUrl = $order->cancelUrl;
        $this->orderStatusId = $order->orderStatusId;
        $this->billingAddressId = $order->billingAddressId;
        $this->shippingAddressId = $order->shippingAddressId;
        $this->estimatedBillingAddressId = $order->estimatedBillingAddressId;
        $this->estimatedShippingAddressId = $order->estimatedShippingAddressId;
        $this->makePrimaryShippingAddress = $order->makePrimaryShippingAddress;
        $this->makePrimaryBillingAddress = $order->makePrimaryBillingAddress;
        $this->shippingSameAsBilling = $order->shippingSameAsBilling;
        $this->billingSameAsShipping = $order->billingSameAsShipping;
        $this->estimatedBillingSameAsShipping = $order->estimatedBillingSameAsShipping;
        $this->shippingMethodHandle = $order->shippingMethodHandle;
        $this->customerId = $order->customerId;
        $this->registerUserOnOrderComplete = $order->registerUserOnOrderComplete;

        // Finally, assign the original order
        $this->_commerceOrder = $order;
    }

    /**
     * We assume the parent order is valid and nothing else requires validation
     * @author Josh Smith <josh@batch.nz>
     * @return array
     */
    public function rules()
    {
        return Element::rules();
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $orderRecord = OrderRecord::findOne($this->id);

            if (!$orderRecord) {
                throw new Exception('Invalid order ID: ' . $this->id);
            }
        } else {
            $orderRecord = new OrderRecord();
            $orderRecord->id = $this->id;
        }
        // $orderRecord->datePaid = $this->datePaid ?: null;

        $orderRecord->commerceOrderId = $this->commerceOrderId;
        $orderRecord->vendorId = $this->vendorId;
        $orderRecord->orderStatusId = $this->orderStatusId;
        $orderRecord->isCompleted = $this->isCompleted;
        $orderRecord->total = $this->getTotal();

        // $orderRecord->totalPrice = $this->getTotalPrice();
        $orderRecord->totalPaid = $this->getTotalPaid();
        $orderRecord->paidStatus = $this->getPaidStatus();

        $orderRecord->save(false);

        return Element::afterSave($isNew);
    }
}
