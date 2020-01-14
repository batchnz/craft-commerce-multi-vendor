<?php

namespace batchnz\craftcommercemultivendor\plugin;

use batchnz\craftcommercemultivendor\services\VendorTypes;
use batchnz\craftcommercemultivendor\services\Vendors;

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
        ]);
    }
}
