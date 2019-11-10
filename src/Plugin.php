<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\craftcommercemultivendor;

use thejoshsmith\craftcommercemultivendor\services\Vendors as VendorsService;
use thejoshsmith\craftcommercemultivendor\services\Purchases as PurchasesService;
use thejoshsmith\craftcommercemultivendor\variables\CraftCommerceMultiVendorVariable;
use thejoshsmith\craftcommercemultivendor\twigextensions\CraftCommerceMultiVendorTwigExtension;
use thejoshsmith\craftcommercemultivendor\models\Settings;
use thejoshsmith\craftcommercemultivendor\elements\Vendor;
use thejoshsmith\craftcommercemultivendor\elements\Order;
use thejoshsmith\craftcommercemultivendor\helpers\ArrayHelper;
use thejoshsmith\craftcommercemultivendor\behaviors\Template;

use Craft;
use craft\base\Plugin as CraftPlugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\services\Elements;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\Cp;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;

use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Josh Smith
 * @package   Plugin
 * @since     1.0.0
 *
 * @property  VendorsService $vendors
 * @property  PurchasesService $purchases
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class Plugin extends CraftPlugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Plugin::$plugin
     *
     * @var Plugin
     */
    public static $instance;

    // /**
    //  * @inheritdoc
    //  */
    // public $hasCpSettings = true;

    // /**
    //  * @inheritdoc
    //  */
    // public $hasCpSection = true;

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

        $this->_registerElementTypes();
        $this->_registerVariables();
        $this->_registerCpNavItems();
        $this->_registerRoutes();
        $this->_registerAfterInstall();
        $this->_registerAfterPluginsLoaded();

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
        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('craftCommerceMultiVendor', CraftCommerceMultiVendorVariable::class);
            }
        );
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

            // Override order routes
            // 'commerce/orders' => 'craft-commerce-multi-vendor/orders/order-index',
            // 'commerce/orders/<orderId:\d+>' => 'craft-commerce-multi-vendor/orders/edit-order',
            // 'commerce/orders/<orderStatusHandle:{handle}>' => 'craft-commerce-multi-vendor/orders/order-index',

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
        $view->setCpTemplateRoots('commerce', '/var/www/craft-commerce-multi-vendor/src/templates/commerce', 'prepend');
        $view->setCpTemplateRoots('basecommerce', '/var/www/vendor/craftcms/commerce/src/templates');
    }
}
