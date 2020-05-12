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
     * Modifies the Stripe Gateway request by adding a transfer group parameter
     * which we can use later to route funds between connected vendor accounts.
     * @author Josh Smith <josh@batch.nz>
     * @param  BuildGatewayRequestEvent $e Event object
     * @return void
     */
    public function handleBuildGatewayRequestEvent(BuildGatewayRequestEvent $e)
    {
        if ($e->transaction->type === 'purchase') {
            // $e->request['transfer_group'] = "ORDER{$e->transaction->orderId}";
        }
    }

    /**
     * Handles the after process payment event.
     * We use this event to process transfers between the connected vendors accounts.
     * @author Josh Smith <josh@batch.nz>
     * @param  ProcessPaymentEvent $e Event object
     * @return void
     */
    public function handleAfterProcessPaymentEvent(ProcessPaymentEvent $e)
    {
        // 1. Load commission rate
        // 2. Loop through all order lines and group into vendors
        // 3. Minus the commission rate from each vendors subtotal
        // 4. Transfer the vendor amounts into their accounts

        // $platformService = Plugin::$instance->getPlatform();
        // $ordersService = Plugin::$instance->getOrders();
        // $vendorsService = Plugin::$instance->getVendors();

        // $order = $e->order;
        // $response = $e->response;
        // $transaction = $e->transaction;

        // Create order split for each vendor
        // $ordersService->createOrderSplit($order);


        // // Fetch the summarised order totals for each vendor
        // $vendorTotals = $vendorsService->getTotalsFromOrder($order);

        // // Loop through each of the vendor totals and process transfers
        // foreach ($vendorTotals as $vendorId => $total) {

        //     $vendor = $vendorsService->getVendorById($vendorId);
        //     $subOrder = $ordersService->createVendorOrder($vendor, $order);
        //     // $platformCommission = $platformService->calcCommission($total);
        //     // $vendorAmount = $total - $platformCommission;

        //     // Create order split records

        // }
    }
}
