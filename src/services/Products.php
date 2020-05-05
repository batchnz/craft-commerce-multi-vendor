<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\Plugin;

use Craft;
use craft\base\Component;
use craft\events\ModelEvent;

use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Products Service
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Products extends Component
{
    /**
     * Handles permissions check before a product is saved
     * @author Josh Smith <josh@batch.nz>
     * @param  ModelEvent $e
     * @return void
     */
    public function handleBeforeSaveEvent(ModelEvent $e)
    {
        $product = $e->sender;
        $user = Craft::$app->getUser();

        // Skip if the user is an admin user or this is a new product
        if( $user->getIsAdmin() || $e->isNew ) return;

        // Make sure this user has permission to edit this product type
        if( !$product->getIsEditable() ){
            throw new ForbiddenHttpException('You don\'t have permission to manage this product');
        }

        // Get the vendor associated with the current user
        $vendor = Plugin::$instance->getVendors()->getVendorByUserId($user->id, null);

        if( empty($vendor) ){
            throw new NotFoundHttpException('We couldn\'t find a vendor associated with your account');
        }

        // Ensure this product belongs to the vendor associated with the logged in user
        if( !$vendor->hasProduct($product) ){
            throw new ForbiddenHttpException('You don\'t have permission to manage this product');
        }
    }
}
