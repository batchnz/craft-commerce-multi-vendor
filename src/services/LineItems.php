<?php

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\records\Order as OrderRecord;
use batchnz\craftcommercemultivendor\records\Vendor as VendorRecord;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Json;
use craft\commerce\db\Table as CommerceTable;
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
    /**
     * @var LineItem[]
     */
    private $_lineItemsByVendorId = [];

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
        if (!isset($this->_lineItemsByVendorId[$vendorId])) {
            $results = $this->_createLineItemWithVendorsQuery()
                ->where([
                    'orderId' => $orderId,
                    'vendors.id' => $vendorId
                ])
                ->orderBy('dateCreated DESC')
                ->all();

            $this->_lineItemsByVendorId[$vendorId] = [];

            foreach ($results as $result) {
                $result['snapshot'] = Json::decodeIfJson($result['snapshot']);
                $this->_lineItemsByVendorId[$vendorId][] = new LineItem($result);
            }
        }

        return $this->_lineItemsByVendorId[$vendorId];
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
