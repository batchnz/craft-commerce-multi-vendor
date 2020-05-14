<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace batchnz\craftcommercemultivendor\db;

use batchnz\craftcommercemultivendor\records\Vendor as VendorRecord;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\db\OrderQuery as CommerceOrderQuery;
use craft\commerce\models\Customer;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use yii\db\Connection;
use yii\db\Expression;

/**
 * OrderQuery represents a SELECT SQL statement for orders in a way that is independent of DBMS.
 *
 * @method Order[]|array all($db = null)
 * @method Order|array|null one($db = null)
 * @method Order|array|null nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @replace {element} order
 * @replace {elements} orders
 * @replace {twig-method} craft.orders()
 * @replace {myElement} myOrder
 * @replace {element-class} \craft\commerce\elements\Order
 */
class OrderQuery extends CommerceOrderQuery
{
    public $vendorId;
    public $commerceOrderId;
    public $orderByVendors;

    public function vendorId($value)
    {
        $this->vendorId = $value;
        return $this;
    }

    public function vendor($value = null)
    {
        if ($value) {
            $this->vendorId = $value->id;
        } else {
            $this->vendorId = null;
        }

        return $this;
    }

    public function commerceOrderId($value)
    {
        $this->commerceOrderId = $value;
        return $this;
    }

    public function commerceOrder($value = null)
    {
        if ($value) {
            $this->commerceOrderId = $value->id;
        } else {
            $this->commerceOrderId = null;
        }

        return $this;
    }

    public function orderByVendors($value = SORT_ASC)
    {
        $this->orderByVendors = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        parent::beforePrepare();

        // Reset joins
        $this->query->join = [];
        $this->subQuery->join = [];

        $this->joinElementTable('commerce_multivendor_orders');

        $this->query->select([
            'commerce_multivendor_orders.id',
            'commerce_multivendor_orders.commerceOrderId',
            'commerce_multivendor_orders.vendorId',
            'commerce_multivendor_orders.orderStatusId',
            'commerce_multivendor_orders.isCompleted',
            'commerce_multivendor_orders.dateCreated',
            'commerce_multivendor_orders.dateUpdated'
        ]);

        if ($this->commerceOrderId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_orders.commerceOrderId', $this->commerceOrderId));
        }

        if ($this->vendorId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_orders.vendorId', $this->vendorId));
        }

        if( $this->orderByVendors ){
            $this->_applyOrderByVendors();
        }

        // Join to the commerce orders table
        $this->query->innerJoin(TABLE::ORDERS, '[[commerce_orders.id]] = [[commerce_multivendor_orders.commerceOrderId]]');
        $this->subQuery->innerJoin(TABLE::ORDERS, '[[commerce_orders.id]] = [[commerce_multivendor_orders.commerceOrderId]]');

        return true;
    }

    private function _applyOrderByVendors()
    {
        $this->query->innerJoin(VendorRecord::tableName() . ' vendors', '[[vendors.id]] = [[commerce_multivendor_orders.vendorId]]');
        $this->query->innerJoin('content' . ' vendorContent', '[[vendors.id]] = [[vendorContent.elementId]]');
        $this->query->orderBy(['vendorContent.title' => $this->orderByVendors]);
    }
}
