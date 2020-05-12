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
use batchnz\craftcommercemultivendor\elements\Vendor;
use batchnz\craftcommercemultivendor\records\Vendor as VendorRecord;

use Craft;
use craft\base\Component;
use craft\db\Table;
use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Order as CommerceOrder;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;

/**
 * Vendors Service
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Vendors extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Get a vendor by ID.
     *
     * @param int $id
     * @param int $siteId
     * @return Vendor|null
     */
    public function getVendorById(int $id, $siteId = null)
    {
        /** @var Vendor $vendor */
        $vendor = Craft::$app->getElements()->getElementById($id, Vendor::class, $siteId);

        return $vendor;
    }

    /**
     * Returns a Vendor by the passed user ID
     * @author Josh Smith <josh@batch.nz>
     * @param  int          $userId
     * @param  string|null  $status
     * @return Vendor       object
     */
    public function getVendorByUserId(int $userId, $status = 'enabled'): ?Vendor
    {
        return Vendor::find()->relatedTo([$userId])->status($status)->one();
    }

    /**
     * Returns a Vendor by the passed variant ID
     * @author Josh Smith <josh@batch.nz>
     * @param  int    $variantId
     * @return Vendor
     */
    public function getVendorByVariantId(int $variantId): ?Vendor
    {
        $product = Product::find()->hasVariant(
            Variant::find()->id($variantId)
        )->one();
        if( empty($product) ) return null;

        return Vendor::find()->relatedTo($product)->one();
    }

    /**
     * Returns an array of vendors for the passed commerce order Id
     * @author Josh Smith <josh@batch.nz>
     * @param  int    $orderId
     * @return array
     */
    public function getVendorsByCommerceOrderId(int $orderId)
    {
        return Vendor::find()
            ->innerJoin(Table::RELATIONS, Table::RELATIONS.'.[[targetId]] = '.VendorRecord::tableName().'.[[id]]')
            ->innerJoin(CommerceTable::PRODUCTS, CommerceTable::PRODUCTS.'.[[id]] = '.Table::RELATIONS.'.[[sourceId]]')
            ->innerJoin(CommerceTable::VARIANTS, CommerceTable::VARIANTS.'.[[productId]] = '.CommerceTable::PRODUCTS.'.[[id]]')
            ->innerJoin(CommerceTable::LINEITEMS, CommerceTable::LINEITEMS.'.[[purchasableId]] = '.CommerceTable::VARIANTS.'.[[id]]')
            ->where(['commerce_lineitems.orderId' => $orderId])
            ->distinct()
        ->all();
    }

    /**
     * Handle a Site being saved.
     *
     * @param SiteEvent $event
     */
    public function afterSaveSiteHandler(SiteEvent $event)
    {
        $queue = Craft::$app->getQueue();
        $siteId = $event->oldPrimarySiteId;
        $elementTypes = [
            Vendor::class,
        ];

        foreach ($elementTypes as $elementType) {
            $queue->push(new ResaveElements([
                'elementType' => $elementType,
                'criteria' => [
                    'siteId' => $siteId,
                    'status' => null,
                    'enabledForSite' => false
                ]
            ]));
        }
    }
}
