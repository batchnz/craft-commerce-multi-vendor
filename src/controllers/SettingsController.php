<?php

namespace batchnz\craftcommercemultivendor\controllers;

use Craft;
use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\models\Settings;
use craft\commerce\controllers\BaseAdminController;
use yii\web\Response;

/**
 * Class Settings Controller
 */
class SettingsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        return $this->redirect('commerce-multi-vendor/settings/general');
    }

    /**
     * Commerce Settings Form
     */
    public function actionEdit(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        $variables = [
            'settings' => $settings
        ];

        return $this->renderTemplate('craft-commerce-multi-vendor/settings/general', $variables);
    }

    /**
     * @return Response|null
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();

        $params = Craft::$app->getRequest()->getBodyParams();
        $data = $params['settings'];

        $settings = Plugin::getInstance()->getSettings();
        $settings->navLabel = $data['navLabel'] ?? $settings->navLabel;
        $settings->purchaseOrderPdfPath = $data['purchaseOrderPdfPath'] ?? $settings->purchaseOrderPdfPath;
        $settings->purchaseOrderPdfFilenameFormat = $data['purchaseOrderPdfFilenameFormat'] ?? $settings->purchaseOrderPdfFilenameFormat;

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('craft-commerce-multi-vendor', 'Couldn’t save settings.'));
            return $this->renderTemplate('craft-commerce-multi-vendor/settings/general/index', compact('settings'));
        }

        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray());

        if (!$pluginSettingsSaved) {
            Craft::$app->getSession()->setError(Craft::t('craft-commerce-multi-vendor', 'Couldn’t save settings.'));
            return $this->renderTemplate('craft-commerce-multi-vendor/settings/general/index', compact('settings'));
        }

        Craft::$app->getSession()->setNotice(Craft::t('craft-commerce-multi-vendor', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
