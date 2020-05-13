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
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\elements\Vendor;

use Craft;
use craft\base\Component;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\stripe\events\BuildGatewayRequestEvent;

/**
 * Payments Service
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Payments extends Component
{
    /**
     * Handles the after process payment event.
     * We use this event to process transfers between the connected vendors accounts.
     * @author Josh Smith <josh@batch.nz>
     * @param  ProcessPaymentEvent $e Event object
     * @return void
     */
    public function handleAfterProcessPaymentEvent(ProcessPaymentEvent $e)
    {
        // Create order split for each vendor
        Plugin::$instance->getOrders()->createSubOrders($order);
    }
}
