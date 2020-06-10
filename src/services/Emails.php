<?php

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\models\Email;
use batchnz\craftcommercemultivendor\records\Email as EmailRecord;
use batchnz\craftcommercemultivendor\records\OrderStatusEmail as OrderStatusEmailRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\db\Table;
use craft\commerce\events\MailEvent;
use craft\commerce\events\EmailEvent;
use craft\commerce\models\Email as CommerceEmailModel;
use craft\commerce\services\Emails as CommerceEmails;
use yii\base\Event;


/**
 * Emails Service
 */
class Emails extends CommerceEmails {

    // Constants
    // =========================================================================

    const CONFIG_EMAILS_KEY = 'craftCommerceMultiVendor.emails';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getEmailById($id)
    {
        $result = $this->_createEmailQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new Email($result) : null;
    }

    /**
     * @inheritdoc
     */
    public function getAllEmails(): array
    {
        $rows = $this->_createEmailQuery()->all();

        $emails = [];
        foreach ($rows as $row) {
            $emails[] = new Email($row);
        }

        return $emails;
    }

    /**
     * @inheritdoc
     */
    public function saveEmail(CommerceEmailModel $email, bool $runValidation = true): bool
    {
        $isNewEmail = !(bool)$email->id;

        // Fire a 'beforeSaveEmail' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_EMAIL)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_EMAIL, new EmailEvent([
                'email' => $email,
                'isNew' => $isNewEmail
            ]));
        }

        if ($runValidation && !$email->validate()) {
            Craft::info('Email not saved due to validation error(s).', __METHOD__);
            return false;
        }

        if ($isNewEmail) {
            $emailUid = StringHelper::UUID();
        } else {
            $emailUid = Db::uidById(EmailRecord::tableName(), $email->id);
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $configData = [
            'name' => $email->name,
            'subject' => $email->subject,
            'recipientType' => $email->recipientType,
            'to' => $email->to,
            'bcc' => $email->bcc,
            'cc' => $email->cc,
            'replyTo' => $email->replyTo,
            'enabled' => (bool)$email->enabled,
            'templatePath' => $email->templatePath,
            'attachPdf' => (bool)$email->attachPdf,
            'pdfTemplatePath' => $email->pdfTemplatePath,
        ];

        $configPath = self::CONFIG_EMAILS_KEY . '.' . $emailUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewEmail) {
            $email->id = Db::idByUid(EmailRecord::tableName(), $emailUid);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function sendEmail($email, $order, $orderHistory): bool
    {
        error_log('EMAIL-DEBUG: Init send email.', 0);
        if (!$email->enabled) {
            return false;
        }

        // Set Craft to the site template mode
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
        $option = 'email';

        // Make sure date vars are in the correct format
        $dateFields = ['dateOrdered', 'datePaid'];
        foreach ($dateFields as $dateField) {
            if (isset($order->{$dateField}) && !($order->{$dateField} instanceof DateTime) && $order->{$dateField}) {
                $order->{$dateField} = DateTimeHelper::toDateTime($order->{$dateField});
            }
        }

        //sending emails
        $renderVariables = compact('order', 'orderHistory', 'option');

        $mailer = Craft::$app->getMailer();
        /** @var Message $newEmail */
        $newEmail = Craft::createObject(['class' => $mailer->messageClass, 'mailer' => $mailer]);

        $originalLanguage = Craft::$app->language;
        $craftMailSettings = App::mailSettings();

        $fromEmail = CommercePlugin::getInstance()->getSettings()->emailSenderAddress ?: $craftMailSettings->fromEmail;
        $fromEmail = Craft::parseEnv($fromEmail);

        $fromName = CommercePlugin::getInstance()->getSettings()->emailSenderName ?: $craftMailSettings->fromName;
        $fromName = Craft::parseEnv($fromName);

        if ($fromEmail) {
            $newEmail->setFrom($fromEmail);
        }

        if ($fromName && $fromEmail) {
            $newEmail->setFrom([$fromEmail => $fromName]);
        }

        if ($email->recipientType == EmailRecord::TYPE_VENDORS) {
            // use the order's language for template rendering the email fields and body.
            $orderLanguage = $order->orderLanguage ?: $originalLanguage;
            Craft::$app->language = $orderLanguage;

            if ($order->getVendor()) {
                $newEmail->setTo($order->getEmail());
            }
        }

        if ($email->recipientType == EmailRecord::TYPE_CUSTOM) {
            // To:
            try {
                $emails = $view->renderString($email->to, $renderVariables);
                $emails = preg_split('/[\s,]+/', $emails);

                $newEmail->setTo($emails);
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for custom email “{email}” in “To:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        if (!$newEmail->getTo()) {
            error_log('EMAIL-DEBUG: Failed as there is no to address.', 0);
            $error = Plugin::t('Email error. No email address found for order. Order: “{order}”', ['order' => $order->getShortNumber()]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // BCC:
        if ($email->bcc) {
            try {
                $bcc = $view->renderString($email->bcc, $renderVariables);
                $bcc = str_replace(';', ',', $bcc);
                $bcc = preg_split('/[\s,]+/', $bcc);

                if (array_filter($bcc)) {
                    $newEmail->setBcc($bcc);
                }
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for email “{email}” in “BCC:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        // CC:
        if ($email->cc) {
            try {
                $cc = $view->renderString($email->cc, $renderVariables);
                $cc = str_replace(';', ',', $cc);
                $cc = preg_split('/[\s,]+/', $cc);

                if (array_filter($cc)) {
                    $newEmail->setCc($cc);
                }
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for email “{email}” in “CC:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        if ($email->replyTo) {
            // Reply To:
            try {
                $newEmail->setReplyTo($view->renderString($email->replyTo, $renderVariables));
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for email “{email}” in “ReplyTo:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        // Subject:
        try {
            $newEmail->setSubject($view->renderString($email->subject, $renderVariables));
        } catch (\Exception $e) {
            error_log('EMAIL-DEBUG: Failed on parsing email template.', 0);
            $error = Plugin::t('Email template parse error for email “{email}” in “Subject:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Template Path
        try {
            $templatePath = $view->renderString($email->templatePath, $renderVariables);
        } catch (\Exception $e) {
            error_log('EMAIL-DEBUG: Failed on parsing email template path.', 0);
            $error = Plugin::t('Email template path parse error for email “{email}” in “Template Path”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Email Body
        if (!$view->doesTemplateExist($templatePath)) {
            error_log('EMAIL-DEBUG: Template does not exist.', 0);
            $error = Plugin::t('Email template does not exist at “{templatePath}” which resulted in “{templateParsedPath}” for email “{email}”. Order: “{order}”.', [
                'templatePath' => $email->templatePath,
                'templateParsedPath' => $templatePath,
                'email' => $email->name,
                'order' => $order->getShortNumber()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        if ($email->attachPdf && $path = $email->pdfTemplatePath ?: Plugin::getInstance()->getSettings()->getPdfPath()) {
            // Email Body
            if (!$view->doesTemplateExist($path)) {
                error_log('EMAIL-DEBUG: Email PDF template does not exist.', 0);
                $error = Plugin::t('Email PDF template does not exist at “{templatePath}” for email “{email}”. Order: “{order}”.', [
                    'templatePath' => $path,
                    'email' => $email->name,
                    'order' => $order->getShortNumber()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }

            try {
                $pdf = CommercePlugin::getInstance()->getPdf()->renderPdfForOrder($order, 'email', $path);

                $tempPath = Assets::tempFilePath('pdf');

                file_put_contents($tempPath, $pdf);

                // Get a file name
                $filenameFormat = Plugin::getInstance()->getSettings()->getPdfFilenameFormat();
                $fileName = $view->renderObjectTemplate($filenameFormat, $order);
                if (!$fileName) {
                    $fileName = 'Order-' . $order->number;
                }

                // Attachment information
                $options = ['fileName' => $fileName . '.pdf', 'contentType' => 'application/pdf'];
                $newEmail->attach($tempPath, $options);
            } catch (\Exception $e) {
                error_log('EMAIL-DEBUG: Email PDF generation failed.', 0);
                $error = Plugin::t('Email PDF generation error for email “{email}”. Order: “{order}”. PDF Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        try {
            $body = $view->renderTemplate($templatePath, $renderVariables);
            $newEmail->setHtmlBody($body);
        } catch (\Exception $e) {
            error_log('EMAIL-DEBUG: Email template parse error.', 0);
            $error = Plugin::t('Email template parse error for email “{email}”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        try {
            //raising event
            $event = new MailEvent([
                'craftEmail' => $newEmail,
                'commerceEmail' => $email,
                'order' => $order,
                'orderHistory' => $orderHistory
            ]);
            $this->trigger(self::EVENT_BEFORE_SEND_MAIL, $event);

            if (!$event->isValid) {
                $error = Plugin::t('Email “{email}”, for order "{order}" was cancelled by plugin.', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber()
                ]);

                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }

            if (!Craft::$app->getMailer()->send($newEmail)) {
                $error = Plugin::t('Commerce email “{email}” could not be sent for order “{order}”.', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber()
                ]);

                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        } catch (\Exception $e) {
            error_log('EMAIL-DEBUG: Email could not be sent.', 0);
            $error = Plugin::t('Email “{email}” could not be sent for order “{order}”. Error: {error} {file}:{line}', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'email' => $email->name,
                'order' => $order->getShortNumber()
            ]);

            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Raise an 'afterSendEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SEND_MAIL)) {
            $this->trigger(self::EVENT_AFTER_SEND_MAIL, new MailEvent([
                'craftEmail' => $newEmail,
                'commerceEmail' => $email,
                'order' => $order,
                'orderHistory' => $orderHistory
            ]));
        }

        Craft::$app->language = $originalLanguage;
        $view->setTemplateMode($oldTemplateMode);

        // Clear out the temp PDF file if it was created.
        if (!empty($tempPath)) {
            unlink($tempPath);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function handleChangedEmail(ConfigEvent $event)
    {
        $emailUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $emailRecord = $this->_getEmailRecord($emailUid);
            $isNewEmail = $emailRecord->getIsNewRecord();

            $emailRecord->name = $data['name'];
            $emailRecord->subject = $data['subject'];
            $emailRecord->recipientType = $data['recipientType'];
            $emailRecord->to = $data['to'];
            $emailRecord->bcc = $data['bcc'];
            $emailRecord->cc = $data['cc'] ?? null;
            $emailRecord->replyTo = $data['replyTo'] ?? null;
            $emailRecord->enabled = $data['enabled'];
            $emailRecord->templatePath = $data['templatePath'];
            $emailRecord->attachPdf = $data['attachPdf'];
            $emailRecord->pdfTemplatePath = $data['pdfTemplatePath'];
            $emailRecord->uid = $emailUid;

            $emailRecord->save(false);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Fire a 'afterSaveEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_EMAIL)) {
            $this->trigger(self::EVENT_AFTER_SAVE_EMAIL, new EmailEvent([
                'email' => $this->getEmailById($emailRecord->id),
                'isNew' => $isNewEmail
            ]));
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteEmailById($id): bool
    {
        $email = EmailRecord::findOne($id);

        if ($email) {
            // Fire a 'beforeDeleteEmail' event
            if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_EMAIL)) {
                $this->trigger(self::EVENT_BEFORE_DELETE_EMAIL, new EmailEvent([
                    'email' => $this->getEmailById($id),
                ]));
            }

            Craft::$app->getProjectConfig()->remove(self::CONFIG_EMAILS_KEY . '.' . $email->uid);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function handleDeletedEmail(ConfigEvent $event)
    {
        $uid = $event->tokenMatches[0];
        $emailRecord = $this->_getEmailRecord($uid);

        if (!$emailRecord) {
            return;
        }

        $email = $this->getEmailById($emailRecord->id);
        $emailRecord->delete();

        // Fire a 'beforeDeleteEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_EMAIL)) {
            $this->trigger(self::EVENT_AFTER_DELETE_EMAIL, new EmailEvent([
                'email' => $email
            ]));
        }
    }

    /**
     * @inheritdoc
     */
    public function getAllEmailsByOrderStatusId(int $id): array
    {
        $results = $this->_createEmailQuery()
            ->innerJoin(OrderStatusEmailRecord::tableName() . ' statusEmails', '[[emails.id]] = [[statusEmails.emailId]]')
            ->innerJoin(Table::ORDERSTATUSES . ' orderStatuses', '[[statusEmails.orderStatusId]] = [[orderStatuses.id]]')
            ->where(['orderStatuses.id' => $id])
            ->all();

        $emails = [];

        foreach ($results as $row) {
            $emails[] = new Email($row);
        }

        return $emails;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving Emails.
     *
     * @return Query
     */
    private function _createEmailQuery(): Query
    {
        return (new Query())
            ->select([
                'emails.id',
                'emails.name',
                'emails.subject',
                'emails.recipientType',
                'emails.to',
                'emails.bcc',
                'emails.cc',
                'emails.replyTo',
                'emails.enabled',
                'emails.templatePath',
                'emails.attachPdf',
                'emails.pdfTemplatePath',
                'emails.uid',
            ])
            ->orderBy('name')
            ->from([EmailRecord::tableName() . ' emails']);
    }

    /**
     * Gets an email record by uid.
     *
     * @param string $uid
     * @return EmailRecord
     */
    private function _getEmailRecord(string $uid): EmailRecord
    {
        if ($email = EmailRecord::findOne(['uid' => $uid])) {
            return $email;
        }

        return new EmailRecord();
    }
}
