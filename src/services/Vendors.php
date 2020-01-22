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

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
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
     * @param  int    $userId
     * @return Vendor object
     */
    public function getVendorByUserId(int $userId): ?Vendor
    {
        return Vendor::find()->relatedTo([$userId])->one();
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

    /**
     * Returns an array of vendor ids to order totals for each vendor
     * @author Josh Smith <josh@batch.nz>
     * @param  Order  $order
     * @return array
     */
    public function getTotalsFromOrder(Order $order): array
    {
        $vendorTotals = [];
        foreach ($order->getLineItems() as $line) {

            $vendor = $this->getVendorByVariantId($line->purchasableId);
            if( empty($vendor) ) throw new \Exception('Failed to find vendor for purchasableId ' . $line->purchasableId);

            if( empty($vendorTotals[$vendor->id]) ){
                $vendorTotals[$vendor->id] = 0.00;
            }

            $vendorTotals[$vendor->id] += $line->getTotal();
        }

        return $vendorTotals;
    }
}
