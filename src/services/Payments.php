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

        $vendorsService = Plugin::$instance->getVendors();
        $platformService = Plugin::$instance->getPlatform();

        $order = $e->order;
        $response = $e->response;
        $transaction = $e->transaction;

        // Fetch the summarised order totals for each vendor
        $vendorTotals = $vendorsService->getTotalsFromOrder($order);

        // Loop through each of the vendor totals and process transfers
        foreach ($vendorTotals as $vendorId => $total) {

            $vendor = $vendorsService->getVendorById($vendorId);
            $platformCommission = $platformService->calcCommission($total);
            $vendorAmount = $total - $platformCommission;

// We will need to create transaction records here...

            //
            // Perform the transfer, if something goes wrong here, we need to send an email to NZBEX so they can manually resolve...
            //
            try {
                $this->processTransferToVendor($vendor, $vendorAmount, $response->getTransactionReference());
            } catch(\Exception $e) {
                echo '<pre> $e->getMessage(): '; print_r($e->getMessage()); echo '</pre>'; die();
                // Handle transfer failure
            }
        }
    }

    public function processTransferToVendor(Vendor $vendor, float $amount, string $sourceTransaction = '')
    {
        $data = [
            'amount' => (int) $amount * 100,
            'currency' => 'nzd',
            'destination' => $vendor->stripe_user_id,
        ];

        if( !empty($sourceTransaction) ){
            $data['source_transaction'] = $sourceTransaction;
        }

        return \Stripe\Transfer::create($data);
    }
}
