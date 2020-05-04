<?php

/**
 * Extends the commerce products controller to save/delete products
 * Enforces commerce product permissions are set but doesn't enforce commerce plugin permissions
 * @author  Josh Smith <josh@batch.nz>
 */

namespace batchnz\craftcommercemultivendor\controllers;

use Craft;
use craft\commerce\controllers\BaseController;
use craft\commerce\controllers\ProductsController as CommerceProductsController;
use craft\commerce\elements\Product;

use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class Products Controller
 * @author Josh Smith <josh@batch.nz>
 */
class ProductsController extends CommerceProductsController
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->requirePermission('commerce-manageProducts');
        BaseController::init();
    }

     /**
     * @inheritdoc
     */
    public function actionProductIndex(): Response
    {
        throw new NotFoundHttpException();
    }

    /**
     * @inheritdoc
     */
    public function actionVariantIndex(): Response
    {
        throw new NotFoundHttpException();
    }

    /**
     * @inheritdoc
     */
    public function actionEditProduct(string $productTypeHandle, int $productId = null, string $siteHandle = null, Product $product = null): Response
    {
        throw new NotFoundHttpException();
    }
}
