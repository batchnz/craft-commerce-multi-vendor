<?php

namespace batchnz\craftcommercemultivendor\controllers;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\elements\Vendor;

use Craft;
use craft\web\Controller;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Base Vendor Controller
 */
class BaseVendorController extends Controller
{
    // Protected Methods
    // =========================================================================

    /**
     * @param Order $order
     * @throws HttpException
     * @throws InvalidConfigException
     */
    protected function enforceOrderPermissions(Order $order)
    {
        $user = Craft::$app->getUser();
        $identity = $user->getIdentity();
        $isNew = !$order->id;

        // Ensure the logged in user belongs to the order vendor
        $vendorBelongsToUser = Plugin::getInstance()->getVendors()->getIsVendor($order->getVendor());

        // Ensure the order is accessible
        $canAccess = $order->getIsEditable() && $vendorBelongsToUser;

        try {
            // Make sure the logged in user has access to this order
            if( !$user->getIsAdmin() && !$canAccess ) {
                throw new ForbiddenHttpException();
            }
        } catch (ForbiddenHttpException $e) {
            throw new ForbiddenHttpException(Craft::t(Plugin::PLUGIN_HANDLE, 'You don\'t have access to manage this order.'));
        }
    }

    /**
     * @param Vendor $vendor
     * @throws HttpException
     * @throws InvalidConfigException
     */
    protected function enforceVendorPermissions(Vendor $vendor)
    {
        // Returns whether the logged in user is associated with this vendor
        $vendorBelongsToUser = Plugin::getInstance()->getVendors()->getIsVendor($vendor);

        $canAccess = $vendor->getIsEditable() && $vendorBelongsToUser;

        try {
            // Make sure the logged in user has access to this vendor
            if( !$user->getIsAdmin() && !$canAccess ) {
                throw new ForbiddenHttpException();
            }
        } catch (ForbiddenHttpException $e) {
            throw new ForbiddenHttpException(Craft::t(Plugin::PLUGIN_HANDLE, 'You don\'t have access to manage this vendor.'));
        }
    }
}