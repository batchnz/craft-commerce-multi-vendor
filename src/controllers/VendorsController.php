<?php

namespace thejoshsmith\craftcommercemultivendor\controllers;


use thejoshsmith\craftcommercemultivendor\web\assets\vendorindex\VendorIndexAsset;

use Craft;
use craft\base\Element;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
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
}
