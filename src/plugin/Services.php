<?php

namespace thejoshsmith\craftcommercemultivendor\plugin;

use thejoshsmith\craftcommercemultivendor\services\VendorTypes;

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
        ]);
    }
}
