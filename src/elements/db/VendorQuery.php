<?php
namespace thejoshsmith\craftcommercemultivendor\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use ns\prefix\elements\Product;

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
     * @var int|int[]|null The vendor type ID(s) that the resulting vendors must have.
     */
    public $typeId;

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

    public function typeId($value)
    {
        $this->typeId = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        // join in the products table
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
            'commerce_multivendor_vendors.typeId'
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
        if( $this->typeId ){
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.typeId', $this->typeId));
        }

        return parent::beforePrepare();
    }
}
