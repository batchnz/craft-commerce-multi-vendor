<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\craftcommercemultivendor\services;

use thejoshsmith\craftcommercemultivendor\Plugin;
use thejoshsmith\craftcommercemultivendor\elements\Vendor;

use Craft;
use craft\base\Component;

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
     * Returns a Vendor by the passed user Id
     * @author Josh Smith <josh@batch.nz>
     * @param  int    $userId
     * @return Vendor object
     */
    public function getVendorByUserId(int $userId): Vendor
    {
        return Vendor::find()->relatedTo([$userId])->one();
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
