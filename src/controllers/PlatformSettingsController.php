<?php

namespace batchnz\craftcommercemultivendor\controllers;

use Craft;
use craft\web\Controller;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\models\PlatformSettings as PlatformSettingsModel;
use batchnz\craftcommercemultivendor\records\PlatformSettings as PlatformSettingsRecord;


/**
 * Class PlatformSettings Controller
 */
class PlatformSettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionIndex()
    {
        $params = Craft::$app->getUrlManager()->getRouteParams();
        $settings = PlatformSettingsRecord::find()->one();
        $this->renderTemplate(Plugin::PLUGIN_HANDLE, array_merge($params, ['settings' => $settings]));
    }

    /**
     * On save of the platform settings
     * @author Josh Smith <josh@batch.nz>
     * @return void
     */
    public function actionSave()
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $settings = Craft::$app->request->getBodyParam('settings');

        $settingsModel = new PlatformSettingsModel();
        $settingsModel->setAttributes($settings);

        if( ! $settingsModel->validate() ){
            $errors = $settingsModel->getErrors();
            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save platform settings.'));
            Craft::$app->getUrlManager()->setRouteParams(['errors' => $errors]);
            return null;
        }

        $settingsRecord = new PlatformSettingsRecord();
        $settingsRecord->commission = $settings['commission'];
        $settingsRecord->commissionType = $settings['commissionType'];

        // Truncate the table as there's only one record
        Craft::$app->db->createCommand()->truncateTable(PlatformSettingsRecord::tableName())->execute();
        $settingsRecord->save();

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Platform settings saved.'));
        return $this->redirectToPostedUrl();
    }
}
