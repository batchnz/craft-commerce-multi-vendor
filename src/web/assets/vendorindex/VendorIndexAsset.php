<?php

namespace batchnz\craftcommercemultivendor\web\assets\vendorindex;

use batchnz\craftcommercemultivendor\web\assets\commercemultivendorcp\CommerceMultiVendorCpAsset;
use craft\web\AssetBundle;

/**
 * Edit Product edit asset bundle
 */
class VendorIndexAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CommerceMultiVendorCpAsset::class,
        ];

        $this->js = [
            'js/CommerceMultiVendorVendorIndex.js',
        ];

        parent::init();
    }
}
