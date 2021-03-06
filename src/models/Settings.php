<?php
/**
 * Prism Syntax Highlighting plugin for Craft CMS 3.x
 *
 * Adds a new field type that provides syntax highlighting capabilities using PrismJS.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith <me@joshsmith.dev>
 */

namespace batchnz\craftcommercemultivendor\models;

use batchnz\craftcommercemultivendor\Plugin;

use Craft;
use craft\base\Model;

/**
 * @author    Josh Smith <me@joshsmith.dev>
 * @package   Craft Commerce Multi Vendor
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $navLabel = 'Multi Vendor Platform';

    /**
     * @var string
     */
    public $purchaseOrderPdfPath = '';

    /**
     * @var string
     */
    public $purchaseOrderPdfFilenameFormat = '';

    /**
     * @var string The purchase order reference format
     */
    public $orderReferenceFormat = '{{number[:7]}}';

    // Public Methods
    // =========================================================================

    public function getPdfPath()
    {
        return $this->purchaseOrderPdfPath;
    }

    public function getPdfFilenameFormat()
    {
        return $this->purchaseOrderPdfFilenameFormat;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }
}
