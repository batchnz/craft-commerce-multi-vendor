<?php

namespace batchnz\craftcommercemultivendor\controllers;

use batchnz\craftcommercemultivendor\Plugin;

use Craft;
use craft\web\Controller;
use craft\commerce\Plugin as CommercePlugin;
use HttpInvalidParamException;
use yii\web\HttpException;

class DownloadsController extends Controller
{
    public function actionPurchaseOrders()
    {
        $number = Craft::$app->getRequest()->getQueryParam('number');
        $option = Craft::$app->getRequest()->getQueryParam('option', '');
        $type = Craft::$app->getRequest()->getQueryParam('type', 'pdf');

        if ($type !== 'pdf') {
            throw new HttpInvalidParamException('Unsupported document type');
        }

        if (!$number) {
            throw new HttpInvalidParamException('Order number required');
        }

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$order) {
            throw new HttpException('404','Order not found');
        }

        // Use the purchase order template path
        $templatePath = Plugin::getInstance()->getSettings()->purchaseOrderPdfPath;

        $pdf = CommercePlugin::getInstance()->getPdf()->renderPdfForOrder($order, $option, $templatePath);
        $filenameFormat = Plugin::getInstance()->getSettings()->purchaseOrderPdfFilenameFormat;

        $fileName = $this->getView()->renderObjectTemplate($filenameFormat, $order);

        if (!$fileName) {
            $fileName = 'Purchase-Order-' . $order->number;
        }

        return Craft::$app->getResponse()->sendContentAsFile($pdf, $fileName . '.pdf', [
            'mimeType' => 'application/pdf'
        ]);
    }
}