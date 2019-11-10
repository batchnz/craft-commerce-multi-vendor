<?php

namespace thejoshsmith\craftcommercemultivendor\web\assets\commercemultivendorcp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;
use yii\web\JqueryAsset;

/**
 * Asset bundle for the Control Panel
 */
class CommerceMultiVendorCpAsset extends AssetBundle
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
            CpAsset::class,
            JqueryAsset::class,
        ];

        $this->js[] = 'js/commercemultivendorcp.js';

        parent::init();
    }
}
