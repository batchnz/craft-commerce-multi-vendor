<?php

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\models\Email;
use batchnz\craftcommercemultivendor\records\Email as EmailRecord;
use batchnz\craftcommercemultivendor\records\OrderStatusEmail as OrderStatusEmailRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
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
     * On service initialisation
     * @author Josh Smith <josh@batch.nz>
     * @return void
     */
    public function init()
    {
        parent::init();

        Event::on(CommerceEmails::class, CommerceEmails::EVENT_BEFORE_SEND_MAIL, function(MailEvent $e) {
            echo '<pre> $e->craftEmail->to: '; print_r($e->craftEmail->to); echo '</pre>'; die();
            if( strtolower($e->craftEmail->to) === EmailRecord::TYPE_VENDORS ) {
                $this->handleBeforeEmailSendEvent($e);
            }
        });
    }

    /**
     * Handles the commerce before email send event
     * @author Josh Smith <josh@batch.nz>
     * @param  MailEvent $e
     * @return void
     */
    public function handleBeforeEmailSendEvent(MailEvent $e)
    {
        echo '<pre> $e: '; print_r($e); echo '</pre>'; die();
    }

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
