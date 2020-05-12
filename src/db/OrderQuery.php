<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
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
class OrderQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var string The order number of the resulting order.
     */
    public $number;

    /**
     * @var string The short order number of the resulting order.
     */
    public $shortNumber;

    /**
     * @var string The order reference of the resulting order.
     * @used-by reference()
     */
    public $reference;

    /**
     * @var string The email address the resulting orders must have.
     */
    public $email;

    /**
     * @var bool The completion status that the resulting orders must have.
     */
    public $isCompleted;

    /**
     * @var mixed The Date Ordered date that the resulting orders must have.
     */
    public $dateOrdered;

    /**
     * @var mixed The date the order was paid.
     */
    public $datePaid;

    /**
     * @var int The Order Status ID that the resulting orders must have.
     */
    public $orderStatusId;

    /**
     * @var bool The completion status that the resulting orders must have.
     */
    public $customerId;

    /**
     * @var int The gateway ID that the resulting orders must have.
     */
    public $gatewayId;

    /**
     * @var bool Whether the order is paid
     */
    public $isPaid;

    /**
     * @var bool The payment status the resulting orders must belong to.
     */
    public $isUnpaid;

    /**
     * @var PurchasableInterface|PurchasableInterface[] The resulting orders must contain these Purchasables.
     */
    public $hasPurchasables;

    /**
     * @var bool Whether the order has any transactions
     */
    public $hasTransactions;

    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['commerce_multivendor_orders.id' => SORT_ASC];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'commerce_multivendor_orders.id';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'updatedAfter':
                $this->updatedAfter($value);
                break;
            case 'updatedBefore':
                $this->updatedBefore($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function number($value = null)
    {
        $this->number = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function shortNumber($value = null)
    {
        $this->shortNumber = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function reference(string $value = null)
    {
        $this->reference = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function email(string $value)
    {
        $this->email = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isCompleted(bool $value = true)
    {
        $this->isCompleted = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function dateOrdered($value)
    {
        $this->dateOrdered = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function datePaid($value)
    {
        $this->datePaid = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function orderStatus($value)
    {
        if ($value instanceof OrderStatus) {
            $this->orderStatusId = $value->id;
        } else if ($value !== null) {
            $this->orderStatusId = (new Query())
                ->select(['id'])
                ->from([Table::ORDERSTATUSES])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->orderStatusId = null;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function orderStatusId($value)
    {
        $this->orderStatusId = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function customer(Customer $value = null)
    {
        if ($value) {
            $this->customerId = $value->id;
        } else {
            $this->customerId = null;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function customerId($value)
    {
        $this->customerId = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function gateway(GatewayInterface $value = null)
    {
        if ($value) {
            /** @var Gateway $value */
            $this->gatewayId = $value->id;
        } else {
            $this->gatewayId = null;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function gatewayId($value)
    {
        $this->gatewayId = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function user($value)
    {
        if ($value instanceof User) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerByUserId($value->id);
            $this->customerId = $customer->id ?? null;
        } else if ($value !== null) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerByUserId($value);
            $this->customerId = $customer->id ?? null;
        } else {
            $this->customerId = null;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isPaid(bool $value = true)
    {
        $this->isPaid = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isUnpaid(bool $value = true)
    {
        $this->isUnpaid = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasTransactions(bool $value = true)
    {
        $this->hasTransactions = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasPurchasables($value)
    {
        $this->hasPurchasables = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('commerce_orders');

        $this->query->select([
            'commerce_orders.id',
            'commerce_orders.number',
            'commerce_orders.reference',
            'commerce_orders.couponCode',
            'commerce_orders.orderStatusId',
            'commerce_orders.dateOrdered',
            'commerce_orders.email',
            'commerce_orders.isCompleted',
            'commerce_orders.datePaid',
            'commerce_orders.currency',
            'commerce_orders.paymentCurrency',
            'commerce_orders.lastIp',
            'commerce_orders.orderLanguage',
            'commerce_orders.message',
            'commerce_orders.returnUrl',
            'commerce_orders.cancelUrl',
            'commerce_orders.billingAddressId',
            'commerce_orders.shippingAddressId',
            'commerce_orders.estimatedBillingAddressId',
            'commerce_orders.estimatedShippingAddressId',
            'commerce_orders.shippingMethodHandle',
            'commerce_orders.gatewayId',
            'commerce_orders.paymentSourceId',
            'commerce_orders.customerId',
            'commerce_orders.dateUpdated'
        ]);

        $commerce = Craft::$app->getPlugins()->getStoredPluginInfo('commerce');
        if ($commerce && version_compare($commerce['version'], '2.1.3', '>=')) {
            $this->query->addSelect(['commerce_orders.registerUserOnOrderComplete']);
        }

        if ($this->number !== null) {
            // If it's set to anything besides a non-empty string, abort the query
            if (!is_string($this->number) || $this->number === '') {
                return false;
            }

            $this->subQuery->andWhere(['commerce_orders.number' => $this->number]);
        }

        if ($this->shortNumber !== null) {
            // If it's set to anything besides a non-empty string, abort the query
            if (!is_string($this->shortNumber) || $this->shortNumber === '') {
                return false;
            }

            $this->subQuery->andWhere(new Expression('LEFT([[commerce_orders.number]], 7) = :shortNumber', [':shortNumber' => $this->shortNumber]));
        }

        if ($this->reference) {
            $this->subQuery->andWhere(['commerce_orders.reference' => $this->reference]);
        }

        if ($this->email) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.email', $this->email));
        }

        // Allow true ot false but not null
        if ($this->isCompleted !== null) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.isCompleted', $this->isCompleted));
        }

        if ($this->dateOrdered) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.dateOrdered', $this->dateOrdered));
        }

        if ($this->datePaid) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.datePaid', $this->datePaid));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.expiryDate', $this->expiryDate));
        }

        if ($this->dateUpdated) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_orders.dateUpdated', $this->dateUpdated));
        }

        if ($this->orderStatusId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.orderStatusId', $this->orderStatusId));
        }

        if ($this->customerId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.customerId', $this->customerId));
        }

        if ($this->gatewayId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_orders.gatewayId', $this->gatewayId));
        }

        // Allow true ot false but not null
        if (($this->isPaid !== null) && $this->isPaid) {
            $this->subQuery->andWhere('commerce_orders.totalPaid >= commerce_orders.totalPrice');
        }

        // Allow true ot false but not null
        if (($this->isUnpaid !== null) && $this->isUnpaid) {
            $this->subQuery->andWhere('commerce_orders.totalPaid < commerce_orders.totalPrice');
        }

        // Allow true ot false but not null
        if (($this->hasPurchasables !== null) && $this->hasPurchasables) {
            $purchasableIds = [];

            if (!is_array($this->hasPurchasables)) {
                $this->hasPurchasables = [$this->hasPurchasables];
            }

            foreach ($this->hasPurchasables as $purchasable) {
                if ($purchasable instanceof PurchasableInterface) {
                    $purchasableIds[] = $purchasable->getId();
                } else if (is_numeric($purchasable)) {
                    $purchasableIds[] = $purchasable;
                }
            }

            // Remove any blank purchasable IDs (if any)
            $purchasableIds = array_filter($purchasableIds);

            $this->subQuery->innerJoin(Table::LINEITEMS . ' lineitems', '[[lineitems.orderId]] = [[commerce_orders.id]]');
            $this->subQuery->andWhere(['in', '[[lineitems.purchasableId]]', $purchasableIds]);
        }

        // Allow true or false but not null
        if (($this->hasTransactions !== null) && $this->hasTransactions) {
            $this->subQuery->andWhere([
                'exists', (new Query())->select(new Expression('1'))
                    ->from([Table::TRANSACTIONS . ' transactions'])
                    ->where('[[commerce_orders.id]] = [[transactions.orderId]]')
            ]);
        }

        return parent::beforePrepare();
    }
}
