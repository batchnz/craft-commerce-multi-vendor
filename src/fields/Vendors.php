<?php

namespace batchnz\craftcommercemultivendor\fields;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\elements\Vendor;

use Craft;
use craft\fields\BaseRelationField;

/**
 * Class Vendors Field
 * @author Josh Smith <josh@batch.nz>
 */
class Vendors extends BaseRelationField
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t(Plugin::PLUGIN_HANDLE, 'Vendors');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t(Plugin::PLUGIN_HANDLE, 'Add a vendor');
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return Vendor::class;
    }
}
