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
use batchnz\craftcommercemultivendor\elements\Order as SubOrder;
use batchnz\craftcommercemultivendor\helpers\ArrayHelper;
use batchnz\craftcommercemultivendor\behaviors\Order as OrderBehavior;
use batchnz\craftcommercemultivendor\behaviors\Template;
use batchnz\craftcommercemultivendor\plugin\Services as CommerceMultiVendorServices;
use batchnz\craftcommercemultivendor\services\Orders as OrdersService;
use batchnz\craftcommercemultivendor\services\VendorTypes;
use batchnz\craftcommercemultivendor\fields\Vendors;
use batchnz\craftcommercemultivendor\models\Settings;
use batchnz\craftcommercemultivendor\services\Emails as EmailsService;
use batchnz\craftcommercemultivendor\services\OrderStatuses as OrderStatusesService;

use Craft;
use craft\base\Element;
use craft\base\Plugin as CraftPlugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Sites;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\events\DefineBehaviorsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;

use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\elements\Product;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\models\Email as EmailModel;
use craft\commerce\records\Email;
use craft\commerce\services\LineItems;
use craft\commerce\services\OrderHistories;
use craft\commerce\services\Payments;
use craft\commerce\stripe\base\Gateway as StripeGateway;
use craft\commerce\stripe\events\BuildGatewayRequestEvent;

use yii\BaseYii;
use yii\base\Event;

// use craft\commerce\records\OrderHistory;

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
    public $schemaVersion = '1.0.8';

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
        $this->_registerPermissions();
        $this->_registerHooks();
        $this->_registerBehaviors();

        Craft::info(
            Craft::t(
                self::PLUGIN_HANDLE,
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * @param $message
     * @param array $params
     * @param null $language
     * @return string
     * @see Craft::t()
     * @since 2.2.0
     */
    public static function t($message, $params = [], $language = null)
    {
        return Craft::t(self::PLUGIN_HANDLE, $message, $params, $language);
    }

    public function getCpNavItem()
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = self::t($this->getSettings()->navLabel);
        $navItem['url'] = 'commerce-multi-vendor';

        $navItem['subnav']['platform-settings'] = [
            'label' => self::t('Platform Settings'),
            'url' => 'commerce-multi-vendor/platform-settings'
        ];
        $navItem['subnav']['settings'] = [
            'label' => self::t('System Settings'),
            'url' => 'commerce-multi-vendor/settings'
        ];

        return $navItem;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce-multi-vendor/settings/general'));
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

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
                $event->types[] = SubOrder::class;
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

            'commerce-multi-vendor' => ['template' => self::PLUGIN_HANDLE.'/index'],
            'commerce-multi-vendor/platform-settings' => self::PLUGIN_HANDLE.'/platform-settings',
            'commerce-multi-vendor/platform-settings/commission' => self::PLUGIN_HANDLE.'/platform-settings/commission',

            'commerce-multi-vendor/downloads/purchase-orders' => self::PLUGIN_HANDLE.'/downloads/purchase-orders',

            // General settings routes
            'commerce-multi-vendor/settings' => self::PLUGIN_HANDLE.'/settings',
            'commerce-multi-vendor/settings/general' => self::PLUGIN_HANDLE.'/settings/edit',

            // Email settings routes
            'commerce-multi-vendor/settings/emails' => self::PLUGIN_HANDLE.'/emails/index',
            'commerce-multi-vendor/settings/emails/new' =>  self::PLUGIN_HANDLE.'/emails/edit',
            'commerce-multi-vendor/settings/emails/<id:\d+>' =>  self::PLUGIN_HANDLE.'/emails/edit',

            // Order statuses routes
            'commerce-multi-vendor/settings/orderstatuses' => self::PLUGIN_HANDLE.'/order-statuses/index',
            'commerce-multi-vendor/settings/orderstatuses/<id:\d+>' =>  self::PLUGIN_HANDLE.'/order-statuses/edit',

            // Payment routes
            'commerce-multi-vendor/payments/transfer' => self::PLUGIN_HANDLE.'/payments/transfer',

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
            'commerce/settings/vendorordersettings' => self::PLUGIN_HANDLE.'/vendor-order-settings/edit',

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

        $ordersService = $this->getOrders();
        $projectConfigService->onAdd(OrdersService::CONFIG_FIELDLAYOUT_KEY, [$ordersService, 'handleChangedFieldLayout'])
            ->onUpdate(OrdersService::CONFIG_FIELDLAYOUT_KEY, [$ordersService, 'handleChangedFieldLayout'])
            ->onRemove(OrdersService::CONFIG_FIELDLAYOUT_KEY, [$ordersService, 'handleDeletedFieldLayout']);
        Event::on(Fields::class, Fields::EVENT_AFTER_DELETE_FIELD, [$ordersService, 'pruneDeletedField']);

        $orderStatusService = $this->getOrderStatuses();
        $projectConfigService->onAdd(OrderStatusesService::CONFIG_STATUSES_KEY . '.{uid}', [$orderStatusService, 'handleChangedOrderStatus'])
            ->onUpdate(OrderStatusesService::CONFIG_STATUSES_KEY . '.{uid}', [$orderStatusService, 'handleChangedOrderStatus'])
            ->onRemove(OrderStatusesService::CONFIG_STATUSES_KEY . '.{uid}', [$orderStatusService, 'handleDeletedOrderStatus']);
        Event::on(EmailsService::class, EmailsService::EVENT_AFTER_DELETE_EMAIL, [$orderStatusService, 'pruneDeletedEmail']);

        $emailService = $this->getEmails();
        $projectConfigService->onAdd(EmailsService::CONFIG_EMAILS_KEY . '.{uid}', [$emailService, 'handleChangedEmail'])
            ->onUpdate(EmailsService::CONFIG_EMAILS_KEY . '.{uid}', [$emailService, 'handleChangedEmail'])
            ->onRemove(EmailsService::CONFIG_EMAILS_KEY . '.{uid}', [$emailService, 'handleDeletedEmail']);
    }

    private function _registerEventHandlers()
    {
        /**
         * Handle after order save event
         */
        Event::on(Order::class, Element::EVENT_AFTER_DELETE, function(Event $e) {
            $this->getOrders()->handleAfterDeleteOrderEvent($e);
        });

        /**
         * Handle before product save event
         */
        Event::on(Product::class, Element::EVENT_BEFORE_SAVE, function(ModelEvent $e) {
            $this->getProducts()->handleBeforeSaveEvent($e);
        });

        /**
         * We use this event to create vendor orders
         */
        Event::on(Order::class, Order::EVENT_BEFORE_COMPLETE_ORDER, function(Event $e) {
            $this->getOrders()->handleBeforeCompleteOrderEvent($e);
        });

        /**
         * We use this event to set the completion reference on sub orders
         */
        Event::on(SubOrder::class, SubOrder::EVENT_BEFORE_COMPLETE_ORDER, function(Event $e) {
            $this->getOrders()->handleBeforeCompleteSubOrderEvent($e);
        });

        /**
         * Handle the sending of vendor emails linked to order statuses
         */
        Event::on(OrderHistories::class, OrderHistories::EVENT_ORDER_STATUS_CHANGE, function(OrderStatusEvent $e) {
            $this->getOrderStatuses()->handleOrderStatusChangeEvent($e);
        });

        /**
         * We use this event to add connect transfer group details to the Stripe request
         */
        Event::on(StripeGateway::class, StripeGateway::EVENT_BUILD_GATEWAY_REQUEST, function(BuildGatewayRequestEvent $e) {
            $this->getPayments()->handleBuildGatewayRequestEvent($e);
        });

        /**
         * Adds Vendor purchasable price into the line item snapshot
         */
        Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $e) {
            $this->getLineItems()->handlePopulateLineItemEvent($e);
        });
    }

    private function _registerPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $vendorTypes = Plugin::getInstance()->getVendorTypes()->getAllVendorTypes();

            $vendorTypePermissions = [];
            foreach ($vendorTypes as $vendorType) {
                $suffix = ':' . $vendorType->uid;
                $vendorTypePermissions['commerce-multi-vendor-manageVendorType' . $suffix] = ['label' => self::t('Manage “{type}” vendors', ['type' => $vendorType->name])];
            }

            $event->permissions[self::t('Craft Commerce Multi Vendor')]['commerce-multi-vendor-manageOrders'] = ['label' => self::t('Manage orders')];
            $event->permissions[self::t('Craft Commerce Multi Vendor')]['commerce-multi-vendor-manageVendors'] = ['label' => self::t('Manage vendors'), 'nested' => $vendorTypePermissions];
        });
    }

    private function _registerHooks()
    {
        Craft::$app->view->hook('cp.commerce.order.edit', function(array &$context) {
            // Check if this order has vendor orders
            $order = $context['order'];
            if( !$order->hasSubOrders() ) return;

            // Add the vendor orders tab
            array_splice($context['tabs'], 1, 0, [[
                'label' => 'Vendor Orders',
                'url' => '#vendorOrdersTab',
                'class' => ''
            ]]);
        });
    }

    private function _registerBehaviors()
    {
        Event::on(Order::class, Order::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $event->behaviors[] = OrderBehavior::class;
        });
    }
}
