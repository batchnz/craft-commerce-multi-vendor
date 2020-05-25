<?php

namespace batchnz\craftcommercemultivendor\models;

use batchnz\craftcommercemultivendor\records\Email as EmailRecord;
use craft\commerce\base\Model as CommerceBaseModel;
use craft\commerce\models\Email as CommerceEmailModel;

/**
 * Email model.
 */
class Email extends CommerceEmailModel
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = CommerceBaseModel::rules();

        $rules[] = [['name'], 'required'];
        $rules[] = [['subject'], 'required'];
        $rules[] = [['recipientType'], 'in', 'range' => [EmailRecord::TYPE_VENDORS, EmailRecord::TYPE_CUSTOM]];
        $rules[] = [
            ['to'], 'required', 'when' => static function($model) {
                return $model->recipientType == EmailRecord::TYPE_CUSTOM;
            }
        ];
        $rules[] = [['templatePath'], 'required'];
        return $rules;
    }
}
