<?php

namespace batchnz\craftcommercemultivendor\controllers;

use Craft;
use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\records\OrderStatusEmail;
use craft\helpers\ArrayHelper;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\controllers\BaseAdminController;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Order Status Controller
 */
class OrderStatusesController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        return $this->renderTemplate(Plugin::PLUGIN_HANDLE.'/settings/orderstatuses/index', compact('orderStatuses'));
    }

    /**
     * @param int|null $id
     * @param OrderStatus|null $orderStatus
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id, OrderStatus $orderStatus = null): Response
    {
        $variables = compact('id', 'orderStatus');

        if (!$variables['orderStatus']) {
            $variables['orderStatus'] = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($variables['id']);

            if (!$variables['orderStatus']) {
                throw new HttpException(404);
            }
        }

        if ($variables['orderStatus']->id) {
            $variables['title'] = $variables['orderStatus']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new order status');
        }

        $emails = Plugin::getInstance()->getEmails()->getAllEmails();
        $variables['emails'] = ArrayHelper::map($emails, 'id', 'name');

        return $this->renderTemplate(Plugin::PLUGIN_HANDLE.'/settings/orderstatuses/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getBodyParam('id');
        $orderStatus = CommercePlugin::getInstance()->getOrderStatuses()->getOrderStatusById($id);

        $emailIds = Craft::$app->getRequest()->getBodyParam('emails', []);

        if (!$emailIds) {
            $emailIds = [];
        }

        // Save it
        if (Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus, $emailIds)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Order status saved.'));
            $this->redirectToPostedUrl($orderStatus);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save order status.'));
        }

        Craft::$app->getUrlManager()->setRouteParams(compact('orderStatus', 'emailIds'));
    }
}
