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
use batchnz\craftcommercemultivendor\elements\Vendor;
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\helpers\ArrayHelper;
use batchnz\craftcommercemultivendor\behaviors\Template;
use batchnz\craftcommercemultivendor\plugin\Services as CommerceMultiVendorServices;
use batchnz\craftcommercemultivendor\services\VendorTypes;
use batchnz\craftcommercemultivendor\fields\Vendors;

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

use craft\commerce\services\Payments;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\stripe\events\BuildGatewayRequestEvent;
use craft\commerce\stripe\base\Gateway as StripeGateway;

use yii\BaseYii;
use yii\base\Event;

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

    const PLUGIN_HANDLE = 'craft-commerce-multi-vendor';

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
    public $hasCpSettings = false;

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

        $this->_registerCpSectionRoute();
        $this->_setPluginComponents();
        $this->_registerElementTypes();
        $this->_registerFieldTypes();
        $this->_registerVariables();
        $this->_registerCpNavItems();
        $this->_registerRoutes();
        $this->_registerAfterPluginsLoaded();
        $this->_registerProjectConfigEventListeners();
        $this->_registerEventHandlers();

        Craft::info(
            Craft::t(
                self::PLUGIN_HANDLE,
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    public function getCpNavItem()
    {
        $parent = parent::getCpNavItem();
        $parent['label'] = 'Platform Settings';
        return $parent;
    }

    // Protected Methods
    // =========================================================================

    // Private Methods
    // =========================================================================

    private function _registerCpSectionRoute()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules[self::PLUGIN_HANDLE.''] = self::PLUGIN_HANDLE.'/platform-settings';
            }
        );
    }

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

    private function _registerFieldTypes()
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Vendors::class;
        });
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
            'commerce/vendors' => self::PLUGIN_HANDLE.'/vendors/vendor-index',
            'commerce/vendors/<vendorId:\d+>' => self::PLUGIN_HANDLE.'/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>' => self::PLUGIN_HANDLE.'/vendors/vendor-index',
            'commerce/vendors/<vendorTypeHandle:{handle}>/new' => self::PLUGIN_HANDLE.'/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>/new/<siteHandle:{handle}>' => self::PLUGIN_HANDLE.'/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>/<vendorId:\d+><slug:(?:-[^\/]*)?>' => self::PLUGIN_HANDLE.'/vendors/edit-vendor',
            'commerce/vendors/<vendorTypeHandle:{handle}>/<vendorId:\d+><slug:(?:-[^\/]*)?>/<siteHandle:{handle}>' => self::PLUGIN_HANDLE.'/vendors/edit-vendor',

            // Override order routes
            // 'commerce/orders' => self::PLUGIN_HANDLE.'/orders/order-index',
            // 'commerce/orders/<orderId:\d+>' => self::PLUGIN_HANDLE.'/orders/edit-order',
            // 'commerce/orders/<orderStatusHandle:{handle}>' => self::PLUGIN_HANDLE.'/orders/order-index',

            'commerce/settings/vendortypes' => self::PLUGIN_HANDLE.'/vendor-types/vendor-type-index',
            'commerce/settings/vendortypes/<vendorTypeId:\d+>' => self::PLUGIN_HANDLE.'/vendor-types/edit-vendor-type',
            'commerce/settings/vendortypes/new' => self::PLUGIN_HANDLE.'/vendor-types/edit-vendor-type',

        ], false);
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

    private function _registerEventHandlers()
    {
        /**
         * We use this event to add connect transfer group details to the Stripe request
         */
        Event::on(StripeGateway::class, StripeGateway::EVENT_BUILD_GATEWAY_REQUEST, function(BuildGatewayRequestEvent $e) {
            $this->getPayments()->handleBuildGatewayRequestEvent($e);
        });

        /**
         * We use ths event to route funds between the vendor accounts
         */
        Event::on(Payments::class, Payments::EVENT_AFTER_PROCESS_PAYMENT, function(ProcessPaymentEvent $e) {
            $this->getPayments()->handleAfterProcessPaymentEvent($e);
        });
    }
}
