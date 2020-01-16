<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor;

use batchnz\craftcommercemultivendor\services\Vendors as VendorsService;
use batchnz\craftcommercemultivendor\services\Purchases as PurchasesService;
use batchnz\craftcommercemultivendor\variables\CraftCommerceMultiVendorBehavior;
use batchnz\craftcommercemultivendor\twigextensions\CraftCommerceMultiVendorTwigExtension;
use batchnz\craftcommercemultivendor\models\Settings;
use batchnz\craftcommercemultivendor\elements\Vendor;
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\helpers\ArrayHelper;
use batchnz\craftcommercemultivendor\behaviors\Template;
use batchnz\craftcommercemultivendor\plugin\Services as CommerceMultiVendorServices;
use batchnz\craftcommercemultivendor\services\VendorTypes;

use Craft;
use craft\base\Plugin as CraftPlugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Sites;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;

use yii\base\Event;

use yii\BaseYii;

/**
 * Craft Commerce Multi Vendor Plugin
 *
 * @author    Josh Smith
 * @package   Plugin
 * @since     1.0.0
 */
class Plugin extends CraftPlugin
{
    use CommerceMultiVendorServices;

    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Plugin::$plugin
     *
     * @var Plugin
     */
    public static $instance;

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public $hasCpSection = true;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.0';

    /**
     * Plugin initialisation
     * @return void
     */
    public function init()
    {
        parent::init();
        self::$instance = $this;

        // Add in our Twig extensions
        Craft::$app->view->registerTwigExtension(new CraftCommerceMultiVendorTwigExtension());

        $this->_setPluginComponents();
        $this->_registerElementTypes();
        $this->_registerVariables();
        $this->_registerCpNavItems();
        $this->_registerRoutes();
        $this->_registerAfterInstall();
        $this->_registerAfterPluginsLoaded();
        $this->_registerProjectConfigEventListeners();

        Craft::info(
            Craft::t(
                'craft-commerce-multi-vendor',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'craft-commerce-multi-vendor/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }

    // Private Methods
    // =========================================================================

    private function _registerElementTypes()
    {
        // Register our elements
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Vendor::class;
                $event->types[] = Order::class;
            }
        );
    }

    private function _registerVariables()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->attachBehavior('commerceMultiVendor', CraftCommerceMultiVendorBehavior::class);
        });
    }

    private function _registerCpNavItems()
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {

                $commerceNav = [];
                foreach ($event->navItems as $i => $navItem) {
                    if( $navItem['url'] === 'commerce' ){
                        $commerceNav =& $event->navItems[$i];
                    }
                }

                $vendorsNav = [
                    'vendors' => [
                        'label' => 'Vendors',
                        'url' => 'commerce/vendors'
                    ]
                ];

                // Merge the vendors nav item
                ArrayHelper::array_splice_assoc($commerceNav['subnav'], 2, 0, $vendorsNav);
            }
        );
    }

    private function _registerRoutes()
    {
        $urlManager = Craft::$app->getUrlManager();

        $urlManager->addRules([

            // Vendor routes
            'commerce/vendors' => 'craft-commerce-multi-vendor/vendors/vendor-index',
            'commerce/vendors/<vendorId:\d+>' => 'craft-commerce-multi-vendor/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>' => 'craft-commerce-multi-vendor/vendors/vendor-index',
            'commerce/vendors/<vendorTypeHandle:{handle}>/new' => 'craft-commerce-multi-vendor/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>/new/<siteHandle:{handle}>' => 'craft-commerce-multi-vendor/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>/<vendorId:\d+><slug:(?:-[^\/]*)?>' => 'craft-commerce-multi-vendor/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>/<vendorId:\d+><slug:(?:-[^\/]*)?>/<siteHandle:{handle}>' => 'craft-commerce-multi-vendor/vendors/edit-vendor',

            // Override order routes
            // 'commerce/orders' => 'craft-commerce-multi-vendor/orders/order-index',
            // 'commerce/orders/<orderId:\d+>' => 'craft-commerce-multi-vendor/orders/edit-order',
            // 'commerce/orders/<orderStatusHandle:{handle}>' => 'craft-commerce-multi-vendor/orders/order-index',

            'commerce/settings/vendortypes' => 'craft-commerce-multi-vendor/vendor-types/vendor-type-index',
            'commerce/settings/vendortypes/<vendorTypeId:\d+>' => 'craft-commerce-multi-vendor/vendor-types/edit-vendor-type',
            'commerce/settings/vendortypes/new' => 'craft-commerce-multi-vendor/vendor-types/edit-vendor-type',

        ], false);
    }

    private function _registerAfterInstall()
    {
         // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                }
            }
        );
    }

    private function _registerAfterPluginsLoaded()
    {
        Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS, function(Event $event){
            $this->_registerRoutes();
            $this->_extendRoutes();
        });
    }

    private function _extendRoutes()
    {
        $view = Craft::$app->getView();
        $view->attachBehavior('TemplateBehavior', Template::class);
        $view->setCpTemplateRoots('commerce', '@batchnz/craftcommercemultivendor/templates/commerce', 'prepend');
        $view->setCpTemplateRoots('basecommerce', '@craft/commerce/templates');
    }

    private function _registerProjectConfigEventListeners()
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        $vendorTypeService = $this->getVendorTypes();
        $projectConfigService->onAdd(VendorTypes::CONFIG_VENDORTYPES_KEY . '.{uid}', [$vendorTypeService, 'handleChangedVendorType'])
            ->onUpdate(VendorTypes::CONFIG_VENDORTYPES_KEY . '.{uid}', [$vendorTypeService, 'handleChangedVendorType'])
            ->onRemove(VendorTypes::CONFIG_VENDORTYPES_KEY . '.{uid}', [$vendorTypeService, 'handleDeletedVendorType']);
        Event::on(Fields::class, Fields::EVENT_AFTER_DELETE_FIELD, [$vendorTypeService, 'pruneDeletedField']);
        Event::on(Sites::class, Sites::EVENT_AFTER_DELETE_SITE, [$vendorTypeService, 'pruneDeletedSite']);
    }
}
