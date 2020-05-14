<?php

namespace batchnz\craftcommercemultivendor\controllers;

use Craft;
use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\services\Orders;
use batchnz\craftcommercemultivendor\elements\Order as SubOrder;
use craft\base\Element;
use craft\commerce\controllers\BaseCpController;
use craft\commerce\Plugin as CommercePlugin;
use yii\base\Exception;

/**
 * Class Vendor Orders Controller
 */
class VendorOrdersController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    /**
     * Saves the Order
     *
     * @return null
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSaveOrder()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $order = $this->_setOrderFromPost();

        $order->setScenario(Element::SCENARIO_LIVE);

        if (!Craft::$app->getElements()->saveElement($order)) {
            Craft::$app->getSession()->setError(Craft::t('craft-commerce-multi-vendor', 'Couldn’t save order.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'order' => $order
            ]);
            return null;
        }

        if( $request->getIsAjax() ){
            return $this->asJson([
                'result' => 'success',
                'message' => 'Order successfully saved',
                'order' => $order
            ]);
        }

        return $this->redirectToPostedUrl($order);
    }

    /**
     * @return Order
     * @throws Exception
     */
    private function _setOrderFromPost(): SubOrder
    {
        $orderId = Craft::$app->getRequest()->getBodyParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new Exception(Craft::t('craft-commerce-multi-vendor', 'No order with the ID “{id}”', ['id' => $orderId]));
        }

        $order->setFieldValuesFromRequest('fields');

        return $order;
    }
}
