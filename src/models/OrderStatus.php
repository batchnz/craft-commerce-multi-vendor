<?php

namespace batchnz\craftcommercemultivendor\models;

use Craft;
use batchnz\craftcommercemultivendor\Plugin;
use craft\commerce\models\OrderStatus as CommerceOrderStatus;

/**
 * Order status model.
 * @inheritdoc
 */
class OrderStatus extends CommerceOrderStatus
{
    /**
     * @return Email[]
     */
    public function getEmails(): array
    {
        return $this->id ? Plugin::getInstance()->getEmails()->getAllEmailsByOrderStatusId($this->id) : [];
    }
}
