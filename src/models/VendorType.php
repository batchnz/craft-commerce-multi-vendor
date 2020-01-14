<?php

namespace batchnz\craftcommercemultivendor\models;

use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\base\Model;
use batchnz\craftcommercemultivendor\Vendor;
use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\records\VendorType as VendorTypeRecord;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

/**
 * Vendor type model.
 * @method null setFieldLayout(FieldLayout $fieldLayout)
 * @method FieldLayout getFieldLayout()
 *
 * @property string $cpEditUrl
 * @property string $cpEditVariantUrl
 * @property FieldLayout $fieldLayout
 * @property mixed $vendorFieldLayout
 * @property array|ShippingCategory[]|int[] $shippingCategories
 * @property VendorTypeSite[] $siteSettings the vendor types' site-specific settings
 * @property TaxCategory[]|array|int[] $taxCategories
 * @mixin FieldLayoutBehavior
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class VendorType extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var string Handle
     */
    public $titleFormat;

    /**
     * @var string Template
     */
    public $template;

    /**
     * @var  int Field layout ID
     */
    public $fieldLayoutId;

    /**
     * @var string UID
     */
    public $uid;

    /**
     * @var TaxCategory[]
     */
    private $_taxCategories;

    /**
     * @var ShippingCategory[]
     */
    private $_shippingCategories;

    /**
     * @var VendorTypeSite[]
     */
    private $_siteSettings;

    // Public Methods
    // =========================================================================

    /**
     * @return null|string
     */
    public function __toString()
    {
        return $this->handle;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'fieldLayoutId'], 'number', 'integerOnly' => true],
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['handle'], UniqueValidator::class, 'targetClass' => VendorTypeRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/vendortypes/' . $this->id);
    }

    /**
     * Returns the vendor types's site-specific settings.
     *
     * @return VendorTypeSite[]
     */
    public function getSiteSettings(): array
    {
        if ($this->_siteSettings !== null) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(ArrayHelper::index(Plugin::getInstance()->getVendorTypes()->getVendorTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    /**
     * Sets the vendor type's site-specific settings.
     *
     * @param VendorTypeSite[] $siteSettings
     */
    public function setSiteSettings(array $siteSettings)
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setVendorType($this);
        }
    }

    // /**
    //  * @return ShippingCategory[]
    //  */
    // public function getShippingCategories(): array
    // {
    //     if ($this->_shippingCategories === null) {
    //         $this->_shippingCategories = Plugin::getInstance()->getShippingCategories()->getShippingCategoriesByVendorTypeId($this->id);
    //     }

    //     return $this->_shippingCategories;
    // }

    // /**
    //  * @param int[]|ShippingCategory[] $shippingCategories
    //  */
    // public function setShippingCategories($shippingCategories)
    // {
    //     $categories = [];
    //     foreach ($shippingCategories as $category) {
    //         if (is_numeric($category)) {
    //             if ($category = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($category)) {
    //                 $categories[$category->id] = $category;
    //             }
    //         } else if ($category instanceof ShippingCategory) {
    //             // Make sure it exists
    //             if ($category = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($category->id)) {
    //                 $categories[$category->id] = $category;
    //             }
    //         }
    //     }

    //     $this->_shippingCategories = $categories;
    // }

    // /**
    //  * @return TaxCategory[]
    //  */
    // public function getTaxCategories(): array
    // {
    //     if ($this->_taxCategories === null) {
    //         $this->_taxCategories = Plugin::getInstance()->getTaxCategories()->getTaxCategoriesByVendorTypeId($this->id);
    //     }

    //     return $this->_taxCategories;
    // }

    // /**
    //  * @param int[]|TaxCategory[] $taxCategories
    //  */
    // public function setTaxCategories($taxCategories)
    // {
    //     $categories = [];
    //     foreach ($taxCategories as $category) {
    //         if (is_numeric($category)) {
    //             if ($category = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($category)) {
    //                 $categories[$category->id] = $category;
    //             }
    //         } else {
    //             if ($category instanceof TaxCategory) {
    //                 // Make sure it exists.
    //                 if ($category = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($category->id)) {
    //                     $categories[$category->id] = $category;
    //                 }
    //             }
    //         }
    //     }

    //     $this->_taxCategories = $categories;
    // }

    /**
     * @return FieldLayout
     */
    public function getVendorFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('vendorFieldLayout');
        return $behavior->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'vendorFieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Vendor::class,
                'idAttribute' => 'fieldLayoutId'
            ]
        ];
    }
}
