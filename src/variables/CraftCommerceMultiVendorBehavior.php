<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\craftcommercemultivendor\variables;

use thejoshsmith\craftcommercemultivendor\Plugin;
use yii\base\Behavior;

use Craft;

/**
 * Craft Commerce Multi Vendor Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.craftCommerceMultiVendor }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class CraftCommerceMultiVendorBehavior extends Behavior
{
    /**
     * @var Plugin
     */
    public $commerceMultiVendor;

    public function init()
    {
        parent::init();

        // Point `craft.commerceMultiVendor` to the thejoshsmith\craftcommercemultivendor\Plugin instance
        $this->commerceMultiVendor = Plugin::getInstance();
    }
}
