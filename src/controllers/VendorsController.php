<?php

namespace batchnz\craftcommercemultivendor\controllers;

use batchnz\craftcommercemultivendor\web\assets\vendorindex\VendorIndexAsset;
use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\elements\Vendor;

use Craft;
use craft\base\Element;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\models\FieldLayout;
use craft\web\Controller;
use DateTime;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Vendors Controller
 */
class VendorsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @throws HttpException
     */
    public function init()
    {
        $this->getView()->registerAssetBundle(VendorIndexAsset::class);
        parent::init();
    }

    /**
     * Index of vendors
     *
     * @param string $orderStatusHandle
     * @return Response
     * @throws Throwable
     */
    public function actionVendorIndex(): Response
    {
        return $this->renderTemplate('craft-commerce-multi-vendor/vendors/_index');
    }

    /**
     * @param string $vendorTypeHandle
     * @param int|null $vendorId
     * @param string|null $siteHandle
     * @param Vendor|null $vendor
     * @return Response
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public function actionEditVendor(string $vendorTypeHandle, int $vendorId = null, string $siteHandle = null, Vendor $vendor = null): Response
    {
        $variables = compact('vendorTypeHandle', 'vendorId', 'vendor');

        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new NotFoundHttpException('Invalid site handle: ' . $siteHandle);
            }
        }

        $this->_prepEditVendorVariables($variables);

        /** @var Vendor $vendor */
        $vendor = $variables['vendor'];

        if (!empty($vendor->id)) {
            $variables['title'] = $vendor->title;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new vendor');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'commerce/vendors/' . $variables['vendorTypeHandle'] . '/{id}-{slug}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] .
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

        $this->_prepVariables($variables);

        $variables['showPreviewBtn'] = false;

        return $this->renderTemplate('craft-commerce-multi-vendor/vendors/_edit', $variables);
    }

    /**
     * Save a new or existing vendor.
     *
     * @return Response|null
     * @throws Exception
     * @throws HttpException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSaveVendor()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $request = Craft::$app->getRequest();
        $vendorId = $request->getBodyParam('vendorId');
        $siteId = $request->getBodyParam('siteId');

        if ($vendorId) {
            $vendor = Plugin::getInstance()->getVendors()->getVendorById($vendorId, $siteId);

            if (!$vendor) {
                throw new HttpException(404, Craft::t('commerce', 'No vendor with the ID “{id}”', ['id' => $vendorId]));
            }
        } else {
            $vendor = new Vendor();
        }

        $vendor->typeId = $request->getBodyParam('typeId');
        $vendor->siteId = $siteId ?? $vendor->siteId;
        $vendor->enabled = (bool)$request->getBodyParam('enabled');
        if (($postDate = $request->getBodyParam('postDate')) !== null) {
            $vendor->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        if (($expiryDate = $request->getBodyParam('expiryDate')) !== null) {
            $vendor->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }
        // $vendor->taxCategoryId = $request->getBodyParam('taxCategoryId');
        // $vendor->shippingCategoryId = $request->getBodyParam('shippingCategoryId');
        $vendor->slug = $request->getBodyParam('slug');

        $vendor->enabledForSite = (bool)$request->getBodyParam('enabledForSite', $vendor->enabledForSite);
        $vendor->title = $request->getBodyParam('title', $vendor->title);

        $vendor->setFieldValuesFromRequest('fields');

        $this->enforceVendorPermissions($vendor);

        // Save the entry (finally!)
        if ($vendor->enabled && $vendor->enabledForSite) {
            $vendor->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($vendor)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $vendor->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save vendor.'));

            // Send the category back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'vendor' => $vendor
            ]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $vendor->id,
                'title' => $vendor->title,
                'status' => $vendor->getStatus(),
                'url' => $vendor->getUrl(),
                'cpEditUrl' => $vendor->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Vendor saved.'));

        return $this->redirectToPostedUrl($vendor);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @param Vendor $vendor
     * @throws HttpException
     * @throws InvalidConfigException
     */
    protected function enforceVendorPermissions(Vendor $vendor)
    {
        // $this->requirePermission('commerce-manageVendorType:' . $vendor->getType()->uid);
    }

    // Private Methods
    // =========================================================================

    /**
     * @param array $variables
     * @throws InvalidConfigException
     */
    private function _prepVariables(array &$variables)
    {
        $variables['tabs'] = [];

        /** @var VendorType $vendorType */
        $vendorType = $variables['vendorType'];
        /** @var Vendor $vendor */
        $vendor = $variables['vendor'];

        foreach ($vendorType->getVendorFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;
            if ($vendor->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    /** @var Field $field */
                    if ($hasErrors = $vendor->hasErrors($field->handle . '.*')) {
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => $hasErrors ? 'error' : null
            ];
        }
    }

    /**
     * @param array $variables
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    private function _prepEditVendorVariables(array &$variables)
    {
        if (!empty($variables['vendorTypeHandle'])) {
            $variables['vendorType'] = Plugin::getInstance()->getVendorTypes()->getVendorTypeByHandle($variables['vendorTypeHandle']);
        } else if (!empty($variables['vendorTypeId'])) {
            $variables['vendorType'] = Plugin::getInstance()->getVendorTypes()->getVendorTypeById($variables['vendorTypeId']);
        }

        if (empty($variables['vendorType'])) {
            throw new NotFoundHttpException('Vendor Type not found');
        }

        // Get the site
        // ---------------------------------------------------------------------

        if (Craft::$app->getIsMultiSite()) {
            // Only use the sites that the user has access to
            $variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
        } else {
            $variables['siteIds'] = [Craft::$app->getSites()->getPrimarySite()->id];
        }

        if (!$variables['siteIds']) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this vendor type');
        }

        if (empty($variables['site'])) {
            $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }

            $site = $variables['site'];
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }

        if (!empty($variables['vendorTypeHandle'])) {
            $variables['vendorType'] = Plugin::getInstance()->getVendorTypes()->getVendorTypeByHandle($variables['vendorTypeHandle']);
        }

        if (empty($variables['vendorType'])) {
            throw new HttpException(400, craft::t('commerce', 'Wrong vendor type specified'));
        }

        // Get the vendor
        // ---------------------------------------------------------------------

        if (empty($variables['vendor'])) {
            if (!empty($variables['vendorId'])) {
                $variables['vendor'] = Plugin::getInstance()->getVendors()->getVendorById($variables['vendorId'], $variables['site']->id);

                if (!$variables['vendor']) {
                    throw new NotFoundHttpException('Vendor not found');
                }
            } else {
                $variables['vendor'] = new Vendor();
                $variables['vendor']->typeId = $variables['vendorType']->id;
                $variables['vendor']->typeId = $variables['vendorType']->id;
                $variables['vendor']->enabled = true;
                $variables['vendor']->siteId = $site->id;
            }
        }

        if ($variables['vendor']->id) {
            $this->enforceVendorPermissions($variables['vendor']);
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['vendor']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
        }
    }
}
