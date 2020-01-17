<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\models;

use batchnz\craftcommercemultivendor\Plugin;

use Craft;
use craft\base\Model;

/**
 * CraftCommerceMultiVendor Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class PlatformSettings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * The amount of commission charged
     *
     * @var string
     */
    public $commission = 0.00;

    /**
     * The type of commission charged by the platform
     * Can be `percentage` or `amount`
     *
     * @var string
     */
    public $commissionType = 'percentage';

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['commissionType', 'commission'], 'required'],
            ['commissionType', 'string'],
            ['commissionType', 'default', 'value' => 'percentage'],
            ['commission', 'double', 'min' => '0'],
            ['commission', 'default', 'value' => '0.00'],
        ];
    }
}
