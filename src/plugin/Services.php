<?php

namespace batchnz\craftcommercemultivendor\plugin;

use batchnz\craftcommercemultivendor\services\VendorTypes;
use batchnz\craftcommercemultivendor\services\Vendors;
use batchnz\craftcommercemultivendor\services\Payments;
use batchnz\craftcommercemultivendor\services\Platform;
use batchnz\craftcommercemultivendor\services\Products;

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
            'vendorTypes' => VendorTypes::class,
            'vendors' => Vendors::class,
            'payments' => Payments::class,
            'platform' => Platform::class,
            'products' => Products::class,
        ]);
    }
}
