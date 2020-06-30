<?php

namespace batchnz\craftcommercemultivendor\controllers;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\elements\Order;
use batchnz\craftcommercemultivendor\elements\Vendor;

use Craft;
use craft\commerce\controllers\BaseAdminController;

use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Payments Controller
 */
class PaymentsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * Overrides the parent requireAdmin method to allow administrative changes on production mode
     * @author Josh Smith <josh@batch.nz>
     * @param  bool|boolean $requireAdminChanges
     * @return void
     */
    public function requireAdmin(bool $requireAdminChanges = true)
    {
        return parent::requireAdmin(false);
    }

    /**
     * Transfer funds to a vendor for a particular order
     * @author Josh Smith <josh@batch.nz>
     * @return void
     */
    public function actionTransfer()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        $orderId = $request->post('orderId');
        if( empty($orderId) ){
            throw new BadRequestHttpException('Missing Order ID');
        }

        $vendorId = $request->post('vendorId');
        if( empty($vendorId) ){
            throw new BadRequestHttpException('Missing Vendor ID');
        }

        $order = Order::find()->id($orderId)->where(['vendorId' => $vendorId])->one();
        if( empty($order) ){
            throw new NotFoundHttpException('Order not found or doesn\'t belong to the Vendor');
        }

        $vendor = Vendor::find()->id($vendorId)->one();
        if( empty($vendor) ){
            throw NotFoundHttpException('Vendor not found');
        }

        // Make sure there's an outstanding balance on this order to be transferred
        if( !$order->hasOutstandingBalance() ){
            $customError = 'Order has no outstanding balance to be transferred';

            if ($request->getAcceptsJson()) {
                $this->asJson(['error' => $customError]);
            }

            $session->setError($customError);
            return null;
        }

        try {
            // Transfer the outstanding amount to the vendor
            Plugin::getInstance()->getPayments()->processTransfer($order);
        } catch(Exception $e) {
            if ($request->getAcceptsJson()) {
                $this->asJson(['error' => $e->getMessage()]);
            }

            $session->setError($e->getMessage());
            return null;
        }

        if ($request->getAcceptsJson()) {
            $response = ['success' => true];

            if ($redirect) {
                $response['redirect'] = $redirect;
            }

            if ($transaction) {
                /** @var Transaction $transaction */
                $response['transactionId'] = $transaction->reference;
                $response['transactionHash'] = $transaction->hash;
            }

            return $this->asJson($response);
        }

        // Show a flash message to confirm the amount transferred
        $formattedOrderAmount = Craft::$app->getFormatter()->asCurrency($order->getTotalPaid(), $order->getPaymentCurrency());
        $session->setNotice('Transferred '. $formattedOrderAmount .' to '. $vendor->name);
    }
}
