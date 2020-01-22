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
use craft\commerce\elements\Order;

/**
 * Transactions Service
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Transactions extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Todo, when we can query multivendor orders this will be redundant
     * as we'll loop through those directly and create a transaction for each.
     * @author Josh Smith <josh@batch.nz>
     * @param  Order        $order          Order object
     * @param  Transaction  $transaction    Transaction object
     * @return array
     */
    public function createTransactionsFromOrder(Order $order, Transaction $transaction)
    {
        $vendorsService = Plugin::$instance->getVendors();
        $platformService = Plugin::$instance->getPlatform();

        $vendorTotals = $vendorsService->getTotalsFromOrder($order);

        $transactions = [];
        foreach ($vendorTotals as $vendorId => $total) {
            $transactions[] = $this->createTransaction($order);
        }

        return $transactions;
    }

    public function createTransaction(Order $order = null, Transaction $parentTransaction = null, $typeOverride = null): Transaction
    {

    }
}
