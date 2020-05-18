<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2020 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\Plugin as CommerceMultiVendor;
use batchnz\craftcommercemultivendor\elements\Order as SubOrder;
use batchnz\craftcommercemultivendor\elements\Vendor;

use Craft;
use craft\base\Component;
use craft\events\ConfigEvent;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order as CommerceOrder;

/**
 * Orders Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Orders extends Component
{
    const CONFIG_FIELDLAYOUT_KEY = 'commerce.vendorOrders.fieldLayouts';

    // Public Methods
    // =========================================================================

    /**
     * Handle field layout change
     *
     * @param ConfigEvent $event
     */
    public function handleChangedFieldLayout(ConfigEvent $event)
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty($config = reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(SubOrder::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(SubOrder::class)->id;
        $layout->type = SubOrder::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout);
    }


    /**
     * Prune a deleted field from order field layouts.
     *
     * @param FieldEvent $event
     */
    public function pruneDeletedField(FieldEvent $event)
    {
        /** @var Field $field */
        $field = $event->field;
        $fieldUid = $field->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $layoutData = $projectConfig->get(self::CONFIG_FIELDLAYOUT_KEY);

        // Prune the UID from field layouts.
        if (is_array($layoutData)) {
            foreach ($layoutData as $layoutUid => $layout) {
                if (!empty($layout['tabs'])) {
                    foreach ($layout['tabs'] as $tabUid => $tab) {
                        $projectConfig->remove(self::CONFIG_FIELDLAYOUT_KEY . '.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                    }
                }
            }
        }
    }

    /**
     * Handle field layout being deleted
     *
     * @param ConfigEvent $event
     */
    public function handleDeletedFieldLayout(ConfigEvent $event)
    {
        Craft::$app->getFields()->deleteLayoutsByType(SubOrder::class);
    }

    /**
     * Generates the order split for each vendor, from the main order
     * @author Josh Smith <josh@batch.nz>
     * @param  CommerceOrder  $order    Commerce customer order
     * @return array                    An array of vendor orders
     */
    public function createSubOrders(CommerceOrder $order)
    {
        $orderSplits = [];

        // Get vendors from the commerce order record
        $vendors = CommerceMultiVendor::getInstance()->getVendors()->getVendorsByCommerceOrderId($order->id);
        if( empty($vendors) ) return [];

        // Get the default order status
        $defaultOrderStatus = Commerce::getInstance()->getOrderStatuses()->getDefaultOrderStatusForOrder($order);

        // Loop each vendor and create an order split for each one
        foreach ($vendors as $vendor) {
            $orderSplits[] = $this->createSubOrder($order, $vendor, $defaultOrderStatus);
        }

        return $orderSplits;
    }

    /**
     * Creates a sub order for the passed vendor
     * @author Josh Smith <josh@batch.nz>
     * @param  Order        $order
     * @param  Vendor       $vendor
     * @param  OrderStatus  $defaultOrderStatus
     * @return Order
     */
    public function createSubOrder($order, $vendor, $defaultOrderStatus)
    {
        $subOrder = $this->buildSubOrder($order, $vendor, $defaultOrderStatus);
        Craft::$app->getElements()->saveElement($subOrder);
        return $subOrder;
    }

    /**
     * Builds the suborder model
     * @author Josh Smith <josh@batch.nz>
     * @param  Order        $order
     * @param  Vendor       $vendor
     * @param  OrderStatus  $defaultOrderStatus
     * @return Order
     */
    protected function buildSubOrder($order, $vendor, $defaultOrderStatus)
    {
        $suborder = new SubOrder([
            'commerceOrderId' => $order->id,
            'vendorId' => $vendor->id,
            'orderStatusId' => $defaultOrderStatus ? $defaultOrderStatus->id : null,
            'isCompleted' => 1,
            'number' => Commerce::getInstance()->getCarts()->generateCartNumber()
        ]);

        $suborder->reference = $this->generateReference($suborder);

        return $suborder;
    }

    /**
     * Generates a reference from an order
     * @author Josh Smith <josh@batch.nz>
     * @param  SubOrder $order
     * @return string
     */
    public function generateReference(SubOrder $order)
    {
        $reference = '';
        $referenceTemplate = CommerceMultiVendor::getInstance()->getSettings()->orderReferenceFormat;

        try {
            $reference = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $order);
        } catch (Throwable $exception) {
            Craft::error('Unable to generate order reference for order ID: ' . $order->id . ', with format: ' . $referenceTemplate . ', error: ' . $exception->getMessage());
            throw $exception;
        }

        return $reference;
    }

    /**
     * Get an order by its ID.
     *
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id)
    {
        if (!$id) {
            return null;
        }

        $query = SubOrder::find();
        $query->id($id);
        $query->status(null);

        return $query->one();
    }

    /**
     * Get an order by its number.
     *
     * @param string $number
     * @return Order|null
     */
    public function getOrderByNumber(string $number)
    {
        if (!$number) {
            return null;
        }

        $query = SubOrder::find();
        $query->number($number);
        $query->status(null);

        return $query->one();
    }

    /**
     * Returns orders for the passed vendor
     * @author Josh Smith <josh@batch.nz>
     * @return array
     */
    public function getOrdersByVendor($vendor)
    {
        if (!$vendor) {
            return null;
        }

        $query = SubOrder::find();
        if ($vendor instanceof Vendor) {
            $query->vendor($vendor);
        } else {
            $query->vendorId($vendor);
        }
        $query->limit(null);

        return $query->all();
    }
}
