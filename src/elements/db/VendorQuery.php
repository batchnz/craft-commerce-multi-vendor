<?php
namespace thejoshsmith\craftcommercemultivendor\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use ns\prefix\elements\Product;

class VendorQuery extends ElementQuery
{
    public $token;

    public function token($value)
    {
        $this->token = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        // join in the products table
        $this->joinElementTable('commerce_multivendor_vendors');

        // select the price column
        $this->query->select([
            'commerce_multivendor_vendors.token',
        ]);

        if ($this->token) {
            $this->subQuery->andWhere(Db::parseParam('commerce_multivendor_vendors.token', $this->token));
        }

        return parent::beforePrepare();
    }
}
