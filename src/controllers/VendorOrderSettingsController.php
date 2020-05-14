<?php

namespace batchnz\craftcommercemultivendor\controllers;

use Craft;
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\services\Orders;
use craft\commerce\controllers\BaseAdminController;
use craft\helpers\StringHelper;
use yii\web\Response;

/**
 * Class Order Settings Controller
 */
class VendorOrderSettingsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @param array $variables
     * @return Response
     */
    public function actionEdit(array $variables = []): Response
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Order::class);

        $variables['fieldLayout'] = $fieldLayout;
        $variables['title'] = Craft::t('craft-commerce-multi-vendor', 'Vendor Order Settings');

        return $this->renderTemplate('craft-commerce-multi-vendor/settings/vendorordersettings/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $configData = [StringHelper::UUID() => $fieldLayout->getConfig()];

        Craft::$app->getProjectConfig()->set(Orders::CONFIG_FIELDLAYOUT_KEY, $configData);

        Craft::$app->getSession()->setNotice(Craft::t('craft-commerce-multi-vendor', 'Vendor order fields saved.'));

        return $this->redirectToPostedUrl();
    }
}
