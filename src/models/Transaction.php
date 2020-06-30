<?php

namespace batchnz\craftcommercemultivendor\models;

use craft\commerce\models\Transaction as CommerceTransactionModel;

/**
 * Transaction model.
 */
class Transaction extends CommerceTransactionModel
{
    /**
     * @var int VendorId
     */
    public $vendorId;

    /**
     * @var Gateway
     */
    private $_gateway;

    /**
     * @var
     */
    private $_parentTransaction;

    /**
     * @var Order
     */
    private $_order;

    /**
     * @var Transaction[]
     */
    private $_children;

    // Public Methods
    // =========================================================================

    /**
     * @return Transaction|null
     */
    public function setParent(Transaction $parentTransaction)
    {
        $this->_parentTransaction = $parentTransaction;
    }
}
