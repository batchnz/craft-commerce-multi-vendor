<?php

namespace thejoshsmith\craftcommercemultivendor\models;

use Craft;
use craft\commerce\base\Model;
use thejoshsmith\craftcommercemultivendor\Plugin;
use craft\models\Site;
use yii\base\InvalidConfigException;

/**
 * Vendor type locale model class.
 *
 * @property VendorType $vendorType the Vendor Type
 * @property Site $site the Site
 */
class VendorTypeSite extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Vendor type ID
     */
    public $vendorTypeId;

    /**
     * @var int Site ID
     */
    public $siteId;

    /**
     * @var bool Has Urls
     */
    public $hasUrls;

    /**
     * @var string URL Format
     */
    public $uriFormat;

    /**
     * @var string Template Path
     */
    public $template;

    /**
     * @var VendorType
     */
    private $_vendorType;

    /**
     * @var Site
     */
    private $_site;

    /**
     * @var bool
     */
    public $uriFormatIsRequired = true;

    // Public Methods
    // =========================================================================

    /**
     * Returns the Vendor Type.
     *
     * @return VendorType
     * @throws InvalidConfigException if [[vendorTypeId]] is missing or invalid
     */
    public function getVendorType(): VendorType
    {
        if ($this->_vendorType !== null) {
            return $this->_vendorType;
        }

        if (!$this->vendorTypeId) {
            throw new InvalidConfigException('Vendor type site is missing its vendor type ID');
        }

        if (($this->_vendorType = Plugin::getInstance()->getVendorTypes()->getVendorTypeById($this->vendorTypeId)) === null) {
            throw new InvalidConfigException('Invalid vendor type ID: ' . $this->vendorTypeId);
        }

        return $this->_vendorType;
    }

    /**
     * Sets the Vendor Type.
     *
     * @param VendorType $vendorType
     */
    public function setVendorType(VendorType $vendorType)
    {
        $this->_vendorType = $vendorType;
    }

    /**
     * @return Site
     * @throws InvalidConfigException if [[siteId]] is missing or invalid
     */
    public function getSite(): Site
    {
        if ($this->_site !== null) {
            return $this->_site;
        }

        if (!$this->siteId) {
            throw new InvalidConfigException('Vendor type site is missing its site ID');
        }

        if (($this->_site = Craft::$app->getSites()->getSiteById($this->siteId)) === null) {
            throw new InvalidConfigException('Invalid site ID: ' . $this->siteId);
        }

        return $this->_site;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
