<?php
namespace batchnz\craftcommercemultivendor\elements\db;

use batchnz\craftcommercemultivendor\records\VendorType;

use Craft;
use craft\db\Table;
use craft\db\Query;
use craft\elements\User;
use craft\elements\db\UserQuery;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Product;
use craft\commerce\elements\db\ProductQuery;

class VendorQuery extends ElementQuery
{
    /**
     * Stripe connection details
     * @var string
     */
    public $stripe_access_token;
    public $stripe_refresh_token;
    public $stripe_publishable_key;
    public $stripe_user_id;
    public $stripe_token_type;
    public $stripe_livemode;
    public $stripe_scope;

    /**
     * @var ProductQuery|array only return vendors that match the resulting product query.
     */
    public $hasProduct;

    /**
     * @var UserQuery|array only return vendors that match the resulting user query.
     */
    public $hasUser;

    /**
     * @var mixed The Post Date that the resulting vendors must have.
     */
    public $expiryDate;

    /**
     * @var mixed The Post Date that the resulting vendors must have.
     */
    public $postDate;

    /**
     * @var int|int[]|null The vendor type ID(s) that the resulting vendors must have.
     */
    public $typeId;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'type':
                $this->type($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    public function stripe_access_token($value)
    {
        $this->stripe_access_token = $value;
        return $this;
    }

    public function stripe_refresh_token($value)
    {
        $this->stripe_refresh_token = $value;
        return $this;
    }

    public function stripe_publishable_key($value)
    {
        $this->stripe_publishable_key = $value;
        return $this;
    }

    public function stripe_user_id($value)
    {
        $this->stripe_user_id = $value;
        return $this;
    }

    public function stripe_token_type($value)
    {
        $this->stripe_token_type = $value;
        return $this;
    }

    public function stripe_livemode($value)
    {
        $this->stripe_livemode = $value;
        return $this;
    }

    public function stripe_scope($value)
    {
        $this->stripe_scope = $value;
        return $this;
    }

    public function hasProduct($value)
    {
        $this->hasProduct = $value;
        return $this;
    }

    public function hasUser($value)
    {
        $this->hasUser = $value;
        return $this;
    }

    public function typeId($value)
    {
        $this->typeId = $value;
        return $this;
    }

    public function type($value)
    {
        if ($value instanceof VendorType) {
            $this->typeId = $value->id;
        } else if ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from([VendorType::tableName()])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * @see EntryQuery.php
     */
    public function before($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '<' . $value;

        return $this;
    }

    /**
     * @see EntryQuery.php
     */
    public function after($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '>=' . $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        // join in the multi vendors table
        $this->joinElementTable('commerce_multivendor_vendors');

        // select the price column
        $this->query->select([
            'commerce_multivendor_vendors.stripe_access_token',
            'commerce_multivendor_vendors.stripe_refresh_token',
            'commerce_multivendor_vendors.stripe_publishable_key',
            'commerce_multivendor_vendors.stripe_user_id',
            'commerce_multivendor_vendors.stripe_token_type',
            'commerce_multivendor_vendors.stripe_livemode',
            'commerce_multivendor_vendors.stripe_scope',
            'commerce_multivendor_vendors.typeId',
            'commerce_multivendor_vendors.postDate',
            'commerce_multivendor_vendors.expiryDate',
        ]);

        if( $this->stripe_access_token ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.stripe_access_token', $this->stripe_access_token));
        }

        if( $this->stripe_refresh_token ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.stripe_refresh_token', $this->stripe_refresh_token));
        }

        if( $this->stripe_publishable_key ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.stripe_publishable_key', $this->stripe_publishable_key));
        }

        if( $this->stripe_user_id ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.stripe_user_id', $this->stripe_user_id));
        }

        if( $this->stripe_token_type ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.stripe_token_type', $this->stripe_token_type));
        }

        if( $this->stripe_livemode ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.stripe_livemode', $this->stripe_livemode));
        }

        if( $this->stripe_scope ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.stripe_scope', $this->stripe_scope));
        }

        if ($this->postDate) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_products.postDate', $this->postDate));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_products.expiryDate', $this->expiryDate));
        }

        if( $this->typeId ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.typeId', $this->typeId));
        }

        $this->_applyHasProductParam();
        $this->_applyHasUserParam();

        return parent::beforePrepare();
    }

    private function _applyHasProductParam()
    {
        if ($this->hasProduct) {
            if ($this->hasProduct instanceof ProductQuery) {
                $productQuery = $this->hasProduct;
            } else {
                $productQuery = Product::find()
                    ->id($this->hasProduct->id)
                    ->status($this->hasProduct->status);
            }

            $productQuery->limit = null;
            $productQuery->select('relations.sourceId')->innerJoin(Table::RELATIONS, Table::RELATIONS.'.[[sourceId]] = '.CommerceTable::PRODUCTS.'.[[id]]');
            $productIds = $productQuery->asArray()->column();

            // Remove any blank product IDs (if any)
            $productIds = array_filter($productIds);
            $this->subQuery->innerJoin(Table::RELATIONS, Table::RELATIONS.'.[[targetId]] = {{%commerce_multivendor_vendors}}.[[id]]');
            $this->subQuery->andWhere(['relations.sourceId' => array_values($productIds)]);
        }
    }

    private function _applyHasUserParam()
    {
        if ($this->hasUser) {
            if ($this->hasUser instanceof UserQuery) {
                $userQuery = $this->hasUser;
            } else {
                $userQuery = User::find()
                    ->id($this->hasUser->id)
                    ->status($this->hasUser->status);
            }

            $userQuery->limit = null;
            $userQuery->select('relations.targetId')->innerJoin(Table::RELATIONS, Table::RELATIONS.'.[[targetId]] = '.Table::USERS.'.[[id]]');
            $userIds = $userQuery->asArray()->column();

            // Remove any blank product IDs (if any)
            $userIds = array_filter($userIds);
            $this->subQuery->innerJoin(Table::RELATIONS, Table::RELATIONS.'.[[sourceId]] = {{%commerce_multivendor_vendors}}.[[id]]');
            $this->subQuery->andWhere(['IN', 'relations.targetId', array_values($userIds)]);
        }
    }
}
