<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\elements;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\models\VendorType;
use batchnz\craftcommercemultivendor\elements\db\VendorQuery;
use batchnz\craftcommercemultivendor\records\Vendor as VendorRecord;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use craft\helpers\DateTimeHelper;
use craft\validators\DateTimeValidator;

use craft\commerce\elements\Product;

use yii\base\InvalidConfigException;

/**
 * Vendor Element
 *
 * Element is the base class for classes representing elements in terms of objects.
 *
 * @property FieldLayout|null      $fieldLayout           The field layout used by this element
 * @property array                 $htmlAttributes        Any attributes that should be included in the element’s DOM representation in the Control Panel
 * @property int[]                 $supportedSiteIds      The site IDs this element is available in
 * @property string|null           $uriFormat             The URI format used to generate this element’s URL
 * @property string|null           $url                   The element’s full URL
 * @property \Twig_Markup|null     $link                  An anchor pre-filled with this element’s URL and title
 * @property string|null           $ref                   The reference string to this element
 * @property string                $indexHtml             The element index HTML
 * @property bool                  $isEditable            Whether the current user can edit the element
 * @property string|null           $cpEditUrl             The element’s CP edit URL
 * @property string|null           $thumbUrl              The URL to the element’s thumbnail, if there is one
 * @property string|null           $iconUrl               The URL to the element’s icon image, if there is one
 * @property string|null           $status                The element’s status
 * @property Element               $next                  The next element relative to this one, from a given set of criteria
 * @property Element               $prev                  The previous element relative to this one, from a given set of criteria
 * @property Element               $parent                The element’s parent
 * @property mixed                 $route                 The route that should be used when the element’s URI is requested
 * @property int|null              $structureId           The ID of the structure that the element is associated with, if any
 * @property ElementQueryInterface $ancestors             The element’s ancestors
 * @property ElementQueryInterface $descendants           The element’s descendants
 * @property ElementQueryInterface $children              The element’s children
 * @property ElementQueryInterface $siblings              All of the element’s siblings
 * @property Element               $prevSibling           The element’s previous sibling
 * @property Element               $nextSibling           The element’s next sibling
 * @property bool                  $hasDescendants        Whether the element has descendants
 * @property int                   $totalDescendants      The total number of descendants that the element has
 * @property string                $title                 The element’s title
 * @property string|null           $serializedFieldValues Array of the element’s serialized custom field values, indexed by their handles
 * @property array                 $fieldParamNamespace   The namespace used by custom field params on the request
 * @property string                $contentTable          The name of the table this element’s content is stored in
 * @property string                $fieldColumnPrefix     The field column prefix this element’s content uses
 * @property string                $fieldContext          The field context this element’s content uses
 *
 * http://pixelandtonic.com/blog/craft-element-types
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Vendor extends Element
{
    const STATUS_LIVE = 'live';
    const STATUS_PENDING = 'pending';
    const STATUS_EXPIRED = 'expired';

    // Public Properties
    // =========================================================================

    /**
     * Payment gateway vendor credentials
     * I.e. this is used to store Stripe Connect tokens
     *
     * @var string
     */
    public $stripe_access_token;
    public $stripe_refresh_token;
    public $stripe_publishable_key;
    public $stripe_user_id;
    public $stripe_token_type;
    public $stripe_livemode;
    public $stripe_scope;

    /**
     * @var int Vendor type ID
     */
    public $typeId;

    /**
     * @var DateTime Post date
     */
    public $postDate;

    /**
     * @var DateTime Expiry date
     */
    public $expiryDate;

    /**
     * @inheritdoc
     */
    public $enabled;

    // Static Methods
    // =========================================================================

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        return Craft::t('craft-commerce-multi-vendor', 'Vendor');
    }

    /**
     * Returns whether elements of this type will be storing any data in the `content`
     * table (tiles or custom fields).
     *
     * @return bool Whether elements of this type will be storing any data in the `content` table.
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * Returns whether elements of this type have traditional titles.
     *
     * @return bool Whether elements of this type have traditional titles.
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        if ($status == self::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = ($this->expiryDate ? $this->expiryDate->getTimestamp() : null);

            if ($postDate <= $currentTime && ($expiryDate === null || $expiryDate > $currentTime)) {
                return self::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * Returns whether elements of this type have statuses.
     *
     * If this returns `true`, the element index template will show a Status menu
     * by default, and your elements will get status indicator icons next to them.
     *
     * Use [[statuses()]] to customize which statuses the elements might have.
     *
     * @return bool Whether elements of this type have statuses.
     * @see statuses()
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    public static function isSelectable(): bool
    {
        return true;
    }

    /**
     * Creates an [[ElementQueryInterface]] instance for query purpose.
     * @return ElementQueryInterface The newly created [[ElementQueryInterface]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new VendorQuery(static::class);
    }

    /**
     * Defines the sources that elements of this type may belong to.
     *
     * @param string|null $context The context ('index' or 'modal').
     *
     * @return array The sources.
     * @see sources()
     */
    protected static function defineSources(string $context = null): array
    {
        $vendorTypes = Plugin::getInstance()->getVendorTypes()->getAllVendorTypes();

        $vendorTypeIds = [];

        foreach ($vendorTypes as $vendorType) {
            $vendorTypeIds[] = $vendorType->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => 'All Vendors',
                'criteria' => [
                    'typeId' => $vendorTypeIds
                ]
            ]
        ];

        $sources[] = ['heading' => Craft::t('craft-commerce-multi-vendor', 'Vendor Types')];

        foreach ($vendorTypes as $vendorType) {
            $key = 'vendorType:' . $vendorType->uid;

            $sources[$key] = [
                'key' => $key,
                'label' => $vendorType->name,
                'data' => [
                    'handle' => $vendorType->handle
                ],
                'criteria' => ['typeId' => $vendorType->id]
            ];
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        return parent::defineActions($source);
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('craft-commerce-multi-vendor', 'Title')],
            'stripe_user_id' => ['label' => Craft::t('craft-commerce-multi-vendor', 'Stripe User ID')],
            'dateCreated' => ['label' => Craft::t('craft-commerce-multi-vendor', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('craft-commerce-multi-vendor', 'Date Updated')],
        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * Returns whether the passed product belongs to this vendor
     * @author Josh Smith <josh@batch.nz>
     * @param  Product $product Product Element
     * @return boolean
     */
    public function hasProduct(Product $product)
    {
        return Vendor::find()
            ->id($this->id)
            ->hasProduct(Product::find()->id($product->id))
        ->exists();
    }

    /**
     * Returns whether the current user can edit the element.
     *
     * @return bool
     */
    public function getIsEditable(): bool
    {
        return true;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->title;
    }

     /**
     * Returns the vendor's vendor type.
     *
     * @return VendorType
     * @throws InvalidConfigException
     */
    public function getType(): VendorType
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('Vendor is missing its vendor type ID');
        }

        $vendorType = Plugin::getInstance()->getVendorTypes()->getVendorTypeById($this->typeId);

        if (null === $vendorType) {
            throw new InvalidConfigException('Invalid vendor type ID: ' . $this->typeId);
        }

        return $vendorType;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        $vendorType = $this->getType();

        // The slug *might* not be set if this is a Draft and they've deleted it for whatever reason
        $url = UrlHelper::cpUrl('commerce/vendors/' . $vendorType->handle . '/' . $this->id . ($this->slug ? '-' . $this->slug : ''));

        if (Craft::$app->getIsMultiSite()) {
            $url .= '/' . $this->getSite()->handle;
        }

        return $url;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['typeId'], 'number', 'integerOnly' => true];
        $rules[] = [['postDate', 'expiryDate'], DateTimeValidator::class];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';
        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return $this->getType()->getFieldLayout();
    }

    /**
     * Returns whether this vendor is connected to Stripe
     * @author Josh Smith <josh@batch.nz>
     * @return boolean
     */
    public function isConnectedToStripe(): bool
    {
        return !empty($this->stripe_user_id);
    }

    // Indexes, etc.
    // -------------------------------------------------------------------------

    /**
     * Returns the HTML for the element’s editor HUD.
     *
     * @return string The HTML for the editor HUD
     */
    public function getEditorHtml(): string
    {
        $html = Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'textField', [
            [
                'label' => Craft::t('app', 'Title'),
                'siteId' => $this->siteId,
                'id' => 'title',
                'name' => 'title',
                'value' => $this->title,
                'errors' => $this->getErrors('title'),
                'first' => true,
                'autofocus' => true,
                'required' => true
            ]
        ]);

        $html .= parent::getEditorHtml();

        return $html;
    }

    // Events
    // -------------------------------------------------------------------------

   /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // Make sure the field layout is set correctly
        $this->fieldLayoutId = $this->getType()->fieldLayoutId;

        if ($this->enabled && !$this->postDate) {
            // Default the post date to the current date/time
            $this->postDate = new \DateTime();
            // ...without the seconds
            $this->postDate->setTimestamp($this->postDate->getTimestamp() - ($this->postDate->getTimestamp() % 60));
        }

        return parent::beforeSave($isNew);
    }

     /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $record = VendorRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid vendor ID: ' . $this->id);
            }
        } else {
            $record = new VendorRecord();
            $record->id = $this->id;
        }

        $record->stripe_access_token = $this->stripe_access_token;
        $record->stripe_refresh_token = $this->stripe_refresh_token;
        $record->stripe_publishable_key = $this->stripe_publishable_key;
        $record->stripe_user_id = $this->stripe_user_id;
        $record->stripe_token_type = $this->stripe_token_type;
        $record->stripe_livemode = $this->stripe_livemode;
        $record->stripe_scope = $this->stripe_scope;
        $record->postDate = $this->postDate;
        $record->expiryDate = $this->expiryDate;
        $record->typeId = $this->typeId;

        $record->save(false);

        $this->id = $record->id;

        return parent::afterSave($isNew);
    }

    /**
     * Performs actions before an element is deleted.
     *
     * @return bool Whether the element should be deleted
     */
    public function beforeDelete(): bool
    {
        return true;
    }

    /**
     * Performs actions after an element is deleted.
     *
     * @return void
     */
    public function afterDelete()
    {
    }

    /**
     * Performs actions before an element is moved within a structure.
     *
     * @param int $structureId The structure ID
     *
     * @return bool Whether the element should be moved within the structure
     */
    public function beforeMoveInStructure(int $structureId): bool
    {
        return true;
    }

    /**
     * Performs actions after an element is moved within a structure.
     *
     * @param int $structureId The structure ID
     *
     * @return void
     */
    public function afterMoveInStructure(int $structureId)
    {
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title'),
            'stripe_user_id' => Craft::t('commerce', 'Stripe User ID'),
        ];
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException if [[siteId]] is not set to a site ID that the entry's section is enabled for
     */
    public function getUriFormat()
    {
        $typeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($typeSiteSettings[$this->siteId])) {
            throw new InvalidConfigException('Entry’s section (' . $this->sectionId . ') is not enabled for site ' . $this->siteId);
        }

        return $typeSiteSettings[$this->siteId]->uriFormat;
    }

    /**
     * @inheritdoc
     */
    protected function route()
    {
        // Make sure the section is set to have URLs for this site
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $typeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($typeSiteSettings[$siteId]) || !$typeSiteSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $typeSiteSettings[$siteId]->template,
                'variables' => [
                    'vendor' => $this,
                ]
            ]
        ];
    }
}
