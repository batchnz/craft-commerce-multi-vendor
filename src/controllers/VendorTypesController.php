<?php

namespace batchnz\craftcommercemultivendor\controllers;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\elements\Vendor;
use batchnz\craftcommercemultivendor\models\VendorType;
use batchnz\craftcommercemultivendor\models\VendorTypeSite;

use Craft;
use craft\commerce\controllers\BaseAdminController;
use craft\behaviors\FieldLayoutBehavior;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Vendor Type Controller
 */
class VendorTypesController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionVendorTypeIndex(): Response
    {
        $vendorTypes = Plugin::getInstance()->getVendorTypes()->getAllVendorTypes();
        return $this->renderTemplate('craft-commerce-multi-vendor/settings/vendortypes/index', compact('vendorTypes'));
    }

    /**
     * @param int|null $vendorTypeId
     * @param VendorType|null $vendorType
     * @return Response
     * @throws HttpException
     */
    public function actionEditVendorType(int $vendorTypeId = null, VendorType $vendorType = null): Response
    {
        $variables = compact('vendorTypeId', 'vendorType');

        $variables['brandNewVendorType'] = false;

        if (empty($variables['vendorType'])) {
            if (!empty($variables['vendorTypeId'])) {
                $vendorTypeId = $variables['vendorTypeId'];
                $variables['vendorType'] = Plugin::getInstance()->getVendorTypes()->getVendorTypeById($vendorTypeId);

                if (!$variables['vendorType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['vendorType'] = new VendorType();
                $variables['brandNewVendorType'] = true;
            }
        }

        if (!empty($variables['vendorTypeId'])) {
            $variables['title'] = $variables['vendorType']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Vendor Type');
        }

        $tabs = [
            'vendorTypeSettings' => [
                'label' => Craft::t('commerce', 'Settings'),
                'url' => '#vendor-type-settings',
            ],
            // 'taxAndShipping' => [
            //     'label' => Craft::t('commerce', 'Tax & Shipping'),
            //     'url' => '#tax-and-shipping',
            // ],
            'vendorFields' => [
                'label' => Craft::t('commerce', 'Vendor Fields'),
                'url' => '#vendor-fields',
            ]
        ];

        $variables['tabs'] = $tabs;
        $variables['selectedTab'] = 'vendorTypeSettings';

        return $this->renderTemplate('craft-commerce-multi-vendor/settings/vendortypes/_edit', $variables);
    }

    /**
     * @throws HttpException
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionSaveVendorType()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('manageCommerce')) {
            throw new HttpException(403, Craft::t('commerce', 'This action is not allowed for the current user.'));
        }

        $request = Craft::$app->getRequest();
        $this->requirePostRequest();

        $vendorType = new VendorType();

        // Shared attributes
        $vendorType->id = Craft::$app->getRequest()->getBodyParam('vendorTypeId');
        $vendorType->name = Craft::$app->getRequest()->getBodyParam('name');
        $vendorType->handle = Craft::$app->getRequest()->getBodyParam('handle');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.' . $site->handle);

            $siteSettings = new VendorTypeSite();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $vendorType->setSiteSettings($allSiteSettings);

        // Set the vendor type field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Vendor::class;
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $vendorType->getBehavior('vendorFieldLayout');
        $behavior->setFieldLayout($fieldLayout);

        // Save it
        if (Plugin::getInstance()->getVendorTypes()->saveVendorType($vendorType)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Vendor type saved.'));
            $this->redirectToPostedUrl($vendorType);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save vendor type.'));
        }

        // Send the vendorType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'vendorType' => $vendorType
        ]);
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionDeleteVendorType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $vendorTypeId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getVendorTypes()->deleteVendorTypeById($vendorTypeId);
        return $this->asJson(['success' => true]);
    }
}
