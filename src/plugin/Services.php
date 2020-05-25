<?php

namespace batchnz\craftcommercemultivendor\plugin;

use batchnz\craftcommercemultivendor\services\Emails;
use batchnz\craftcommercemultivendor\services\LineItems;
use batchnz\craftcommercemultivendor\services\Orders;
use batchnz\craftcommercemultivendor\services\OrderStatuses;
use batchnz\craftcommercemultivendor\services\Payments;
use batchnz\craftcommercemultivendor\services\Platform;
use batchnz\craftcommercemultivendor\services\Products;
use batchnz\craftcommercemultivendor\services\Vendors;
use batchnz\craftcommercemultivendor\services\VendorTypes;

/**
 * Trait Services
 *
 * @property VendorTypes $vendorTypes the vendorTypes service
 */
trait Services
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the products service
     *
     * @return Products The products service
     */
    public function getVendors(): Vendors
    {
        return $this->get('vendors');
    }

    /**
     * Returns the vendorTypes service
     *
     * @return VendorTypes The vendorTypes service
     */
    public function getVendorTypes(): VendorTypes
    {
        return $this->get('vendorTypes');
    }

    /**
     * Returns the orders service
     * @author Josh Smith <josh@batch.nz>
     * @return Orders The Orders service
     */
    public function getOrders(): Orders
    {
        return $this->get('orders');
    }

    /**
     * Returns the emails service
     * @author Josh Smith <josh@batch.nz>
     * @return Emails The Emails service
     */
    public function getEmails(): Emails
    {
        return $this->get('emails');
    }

    /**
     * Returns the line items service
     * @author Josh Smith <josh@batch.nz>
     * @return LineItems The line items service
     */
    public function getLineItems(): LineItems
    {
        return $this->get('lineItems');
    }

    /**
     * Returns the order statuses service
     * @author Josh Smith <josh@batch.nz>
     * @return OrderStatuses The order statuses service
     */
    public function getOrderStatuses(): OrderStatuses
    {
        return $this->get('orderStatuses');
    }

    /**
     * Returns the payments service
     * @author Josh Smith <josh@batch.nz>
     * @return Payments The Payments service
     */
    public function getPayments(): Payments
    {
        return $this->get('payments');
    }

    /**
     * Returns the platform service
     * @author Josh Smith <josh@batch.nz>
     * @return Platform The platform service
     */
    public function getPlatform(): Platform
    {
        return $this->get('platform');
    }

    /**
     * Returns the products service
     * @author Josh Smith <josh@batch.nz>
     * @return Products The products service
     */
    public function getProducts(): Products
    {
        return $this->get('products');
    }

    // Private Methods
    // =========================================================================

    /**
     * Sets the components of the commerce multi-vendor plugin
     */
    private function _setPluginComponents()
    {
        $this->setComponents([
            'emails' => Emails::class,
            'lineItems' => LineItems::class,
            'orders' => Orders::class,
            'orderStatuses' => OrderStatuses::class,
            'payments' => Payments::class,
            'platform' => Platform::class,
            'products' => Products::class,
            'vendors' => Vendors::class,
            'vendorTypes' => VendorTypes::class,
        ]);
    }
}
