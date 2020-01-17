<?php

namespace batchnz\craftcommercemultivendor\controllers;

use Craft;
use craft\web\Controller;

use batchnz\craftcommercemultivendor\Plugin;

/**
 * Class Vendors Controller
 */
class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $settings = Plugin::$instance->getSettings();
        $this->renderTemplate(Plugin::PLUGIN_HANDLE, ['settings' => $settings]);
    }
}
