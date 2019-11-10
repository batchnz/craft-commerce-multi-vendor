<?php
namespace thejoshsmith\craftcommercemultivendor\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use ns\prefix\elements\Product;

class VendorQuery extends ElementQuery
{
    /**
     * @var string|null The vendors payment gateway token.
     */
    public $token;

    /**
     * @var int|int[]|null The vendor type ID(s) that the resulting vendors must have.
     */
    public $typeId;

    public function token($value)
    {
        $this->token = $value;
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
            'commerce_multivendor_vendors.token',
            'commerce_multivendor_vendors.typeId',
        ]);

        if ($this->token) {
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.token', $this->token));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.typeId', $this->typeId));
        }

        return parent::beforePrepare();
    }
}
