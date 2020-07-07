<?php

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\events\CapturePlatformSnapshotEvent;
use batchnz\craftcommercemultivendor\records\Order as OrderRecord;
use batchnz\craftcommercemultivendor\records\Vendor as VendorRecord;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Json;
use craft\commerce\db\Table as CommerceTable;
use craft\commerce\events\LineItemEvent;
use craft\commerce\models\LineItem;
use yii\base\Component;

/**
 * Line item service.
 *
 * @author Josh Smith <josh@batch.nz>
 * @since 1.0
 */
class LineItems extends Component
{
    // Constants
    // =========================================================================

    const EVENT_CAPTURE_PLATFORM_SNAPSHOT = 'capturePlatformSnapshot';

    /**
     * @var LineItem[]
     */
    private $_lineItemsByOrderAndVendorId = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns an order's line items, per the order's vendor ID.
     *
     * @param int $vendorId the order's vendor ID
     * @return LineItem[] An array of all the line items for the matched order and vendor.
     */
    public function getAllLineItemsByOrderAndVendorId(int $orderId, int $vendorId): array
    {
        $key = $orderId.'-'.$vendorId;
        if (!isset($this->_lineItemsByOrderAndVendorId[$key])) {
            $results = $this->_createLineItemWithVendorsQuery()
                ->where([
                    'orderId' => $orderId,
                    'vendors.id' => $vendorId
                ])
                ->orderBy('dateCreated DESC')
                ->all();

            $this->_lineItemsByOrderAndVendorId[$key] = [];

            foreach ($results as $result) {
                $result['snapshot'] = Json::decodeIfJson($result['snapshot']);
                $result['price'] = $result['snapshot']['vendorPrice'];
                $result['salePrice'] = $result['snapshot']['vendorSalePrice'];
                $this->_lineItemsByOrderAndVendorId[$key][] = new LineItem($result);
            }
        }

        return $this->_lineItemsByOrderAndVendorId[$key];
    }

    /**
     * Handle the populate line item event
     * @author Josh Smith <josh@batch.nz>
     * @param  LineItemEvent $e
     * @return void
     */
    public function handlePopulateLineItemEvent(LineItemEvent $e)
    {
        $lineItem = $e->lineItem;
        $purchasable = $lineItem->getPurchasable();

        // Calculate the initial platform fee
        $platformFee = Plugin::getInstance()->getPlatform()->calcCommission($purchasable->getSalePrice());

        // Store the original purchasable price as the vendor price
        // This is so we can refer back to it later amid pricing changes.
        $lineItem->snapshot['vendorPrice'] = $purchasable->getPrice();
        $lineItem->snapshot['vendorSalePrice'] = $purchasable->getSalePrice();
        $lineItem->snapshot['vendorSaleAmount'] = $lineItem->saleAmount;
        $lineItem->snapshot['platformFee'] = $platformFee;

        // Fire a 'capturePlatformSnapshot' event
        // Todo: Implement a Vendor capture snapshot event similar to how Variant and Product fields are captured.
        $event = new CapturePlatformSnapshotEvent([
            'lineItem' => $lineItem
        ]);
        $this->trigger(self::EVENT_CAPTURE_PLATFORM_SNAPSHOT, $event);
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving line items.
     *
     * @return Query The query object.
     */
    private function _createLineItemWithVendorsQuery(): Query
    {
        return (new Query())
            ->select([
                'lineItems.id',
                'lineItems.options',
                'lineItems.price',
                'lineItems.saleAmount',
                'lineItems.salePrice',
                'lineItems.weight',
                'lineItems.length',
                'lineItems.height',
                'lineItems.width',
                'lineItems.qty',
                'lineItems.snapshot',
                'lineItems.note',
                'lineItems.purchasableId',
                'lineItems.orderId',
                'lineItems.taxCategoryId',
                'lineItems.shippingCategoryId',
                'lineItems.dateCreated'
            ])
            ->distinct()
            ->from([CommerceTable::LINEITEMS . ' lineItems'])
            ->innerJoin(CommerceTable::VARIANTS, CommerceTable::VARIANTS.'.[[id]] = '.'[[lineItems.purchasableId]]')
            ->innerJoin(CommerceTable::PRODUCTS, CommerceTable::PRODUCTS.'.[[id]] = '.CommerceTable::VARIANTS.'.[[productId]]')
            ->innerJoin(Table::RELATIONS, Table::RELATIONS.'.[[sourceId]] = '.CommerceTable::PRODUCTS.'.[[id]]')
            ->innerJoin(VendorRecord::tableName() . ' vendors', '[[vendors.id]] = '.Table::RELATIONS.'.[[targetId]]');
    }
}
