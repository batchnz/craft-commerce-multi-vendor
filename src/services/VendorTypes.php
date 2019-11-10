<?php

namespace thejoshsmith\craftcommercemultivendor\services;

use thejoshsmith\craftcommercemultivendor\elements\Vendor;
use thejoshsmith\craftcommercemultivendor\events\VendorTypeEvent;
use thejoshsmith\craftcommercemultivendor\models\VendorType;
use thejoshsmith\craftcommercemultivendor\models\VendorTypeSite;
use thejoshsmith\craftcommercemultivendor\records\VendorType as VendorTypeRecord;
use thejoshsmith\craftcommercemultivendor\records\VendorTypeSite as VendorTypeSiteRecord;
use thejoshsmith\craftcommercemultivendor\records\VendorTypeTaxCategory;
use thejoshsmith\craftcommercemultivendor\records\VendorTypeShippingCategory;

use Craft;
use craft\base\Field;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\commerce\db\Table;
use craft\errors\VendorTypeNotFoundException;
use craft\events\ConfigEvent;
use craft\events\DeleteSiteEvent;
use craft\events\FieldEvent;
use craft\events\SiteEvent;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * Vendor type service.
 *
 * @property array|VendorType[] $allVendorTypes all vendor types
 * @property array $allVendorTypeIds all of the vendor type IDs
 * @property array|VendorType[] $editableVendorTypes all editable vendor types
 * @property array $editableVendorTypeIds all of the vendor type IDs that are editable by the current user
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class VendorTypes extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event VendorTypeEvent The event that is triggered before a category group is saved.
     *
     * Plugins can get notified before a vendor type is being saved.
     *
     * ```php
     * use craft\commerce\events\VendorTypeEvent;
     * use craft\commerce\services\VendorTypes;
     * use yii\base\Event;
     *
     * Event::on(VendorTypes::class, VendorTypes::EVENT_BEFORE_SAVE_VENDORTYPE, function(VendorTypeEvent $e) {
     *      // Maybe create an audit trail of this action.
     * });
     * ```
     */
    const EVENT_BEFORE_SAVE_VENDORTYPE = 'beforeSaveVendorType';

    /**
     * @event VendorTypeEvent The event that is triggered after a vendor type is saved.
     *
     * Plugins can get notified after a vendor type has been saved.
     *
     * ```php
     * use craft\commerce\events\VendorTypeEvent;
     * use craft\commerce\services\VendorTypes;
     * use yii\base\Event;
     *
     * Event::on(VendorTypes::class, VendorTypes::EVENT_AFTER_SAVE_VENDORTYPE, function(VendorTypeEvent $e) {
     *      // Maybe prepare some 3rd party system for a new vendor type
     * });
     * ```
     */
    const EVENT_AFTER_SAVE_VENDORTYPE = 'afterSaveVendorType';

    const CONFIG_VENDORTYPES_KEY = 'commerce.vendorTypes';

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllVendorTypes = false;

    /**
     * @var VendorType[]
     */
    private $_vendorTypesById;

    /**
     * @var VendorType[]
     */
    private $_vendorTypesByHandle;

    /**
     * @var int[]
     */
    private $_allVendorTypeIds;

    /**
     * @var int[]
     */
    private $_editableVendorTypeIds;

    /**
     * @var VendorTypeSite[][]
     */
    private $_siteSettingsByVendorId = [];

    /**
     * @var array interim storage for vendor types being saved via CP
     */
    private $_savingVendorTypes = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns all editable vendor types.
     *
     * @return VendorType[] An array of all the editable vendor types.
     */
    public function getEditableVendorTypes(): array
    {
        $editableVendorTypeIds = $this->getEditableVendorTypeIds();
        $editableVendorTypes = [];

        foreach ($this->getAllVendorTypes() as $vendorTypes) {
            if (in_array($vendorTypes->id, $editableVendorTypeIds, false)) {
                $editableVendorTypes[] = $vendorTypes;
            }
        }

        return $editableVendorTypes;
    }

    /**
     * Returns all of the vendor type IDs that are editable by the current user.
     *
     * @return array An array of all the editable vendor types’ IDs.
     */
    public function getEditableVendorTypeIds(): array
    {
        if (null === $this->_editableVendorTypeIds) {
            $this->_editableVendorTypeIds = [];
            $allVendorTypes = $this->getAllVendorTypes();

            foreach ($allVendorTypes as $vendorType) {
                if (Craft::$app->getUser()->checkPermission('commerce-manageVendorType:' . $vendorType->uid)) {
                    $this->_editableVendorTypeIds[] = $vendorType->id;
                }
            }
        }

        return $this->_editableVendorTypeIds;
    }

    /**
     * Returns all of the vendor type IDs.
     *
     * @return array An array of all the vendor types’ IDs.
     */
    public function getAllVendorTypeIds(): array
    {
        if (null === $this->_allVendorTypeIds) {
            $this->_allVendorTypeIds = [];
            $vendorTypes = $this->getAllVendorTypes();

            foreach ($vendorTypes as $vendorType) {
                $this->_allVendorTypeIds[] = $vendorType->id;
            }
        }

        return $this->_allVendorTypeIds;
    }

    /**
     * Returns all vendor types.
     *
     * @return VendorType[] An array of all vendor types.
     */
    public function getAllVendorTypes(): array
    {
        if (!$this->_fetchedAllVendorTypes) {
            $results = $this->_createVendorTypeQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeVendorType(new VendorType($result));
            }

            $this->_fetchedAllVendorTypes = true;
        }

        return $this->_vendorTypesById ?: [];
    }

    /**
     * Returns a vendor type by its handle.
     *
     * @param string $handle The vendor type's handle.
     * @return VendorType|null The vendor type or `null`.
     */
    public function getVendorTypeByHandle($handle)
    {
        if (isset($this->_vendorTypesByHandle[$handle])) {
            return $this->_vendorTypesByHandle[$handle];
        }

        if ($this->_fetchedAllVendorTypes) {
            return null;
        }

        $result = $this->_createVendorTypeQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeVendorType(new VendorType($result));

        return $this->_vendorTypesByHandle[$handle];
    }

    /**
     * Returns an array of vendor type site settings for a vendor type by its ID.
     *
     * @param int $vendorTypeId the vendor type ID
     * @return array The vendor type settings.
     */
    public function getVendorTypeSites($vendorTypeId): array
    {
        if (!isset($this->_siteSettingsByVendorId[$vendorTypeId])) {
            $rows = (new Query())
                ->select([
                    'id',
                    'vendorTypeId',
                    'siteId',
                    'uriFormat',
                    'hasUrls',
                    'template'
                ])
                ->from(VendorTypeSiteRecord::tableName())
                ->where(['vendorTypeId' => $vendorTypeId])
                ->all();

            $this->_siteSettingsByVendorId[$vendorTypeId] = [];

            foreach ($rows as $row) {
                $this->_siteSettingsByVendorId[$vendorTypeId][] = new VendorTypeSite($row);
            }
        }

        return $this->_siteSettingsByVendorId[$vendorTypeId];
    }

    /**
     * Saves a vendor type.
     *
     * @param VendorType $vendorType The vendor type model.
     * @param bool $runValidation If validation should be ran.
     * @return bool Whether the vendor type was saved successfully.
     * @throws Throwable if reasons
     */
    public function saveVendorType(VendorType $vendorType, bool $runValidation = true): bool
    {
        $isNewVendorType = !$vendorType->id;

        // Fire a 'beforeSaveVendorType' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_VENDORTYPE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_VENDORTYPE, new VendorTypeEvent([
                'vendorType' => $vendorType,
                'isNew' => $isNewVendorType,
            ]));
        }

        if ($runValidation && !$vendorType->validate()) {
            Craft::info('Vendor type not saved due to validation error.', __METHOD__);

            return false;
        }

        if ($isNewVendorType) {
            $vendorType->uid = StringHelper::UUID();
        } else {
            /** @var VendorTypeRecord|null $existingVendorTypeRecord */
            $existingVendorTypeRecord = VendorTypeRecord::find()
                ->where(['id' => $vendorType->id])
                ->one();

            if (!$existingVendorTypeRecord) {
                throw new VendorTypeNotFoundException("No vendor type exists with the ID '{$vendorType->id}'");
            }

            $vendorType->uid = $existingVendorTypeRecord->uid;
        }

        $this->_savingVendorTypes[$vendorType->uid] = $vendorType;

        $vendorType->titleFormat = '{vendor.title}';

        $projectConfig = Craft::$app->getProjectConfig();
        $configData = [
            'name' => $vendorType->name,
            'handle' => $vendorType->handle,
            'siteSettings' => []
        ];

        $generateLayoutConfig = function(FieldLayout $fieldLayout): array {
            $fieldLayoutConfig = $fieldLayout->getConfig();

            if ($fieldLayoutConfig) {
                if (empty($fieldLayout->id)) {
                    $layoutUid = StringHelper::UUID();
                    $fieldLayout->uid = $layoutUid;
                } else {
                    $layoutUid = Db::uidById('{{%fieldlayouts}}', $fieldLayout->id);
                }

                return [$layoutUid => $fieldLayoutConfig];
            }

            return [];
        };

        $configData['vendorFieldLayouts'] = $generateLayoutConfig($vendorType->getFieldLayout());

        // Get the site settings
        $allSiteSettings = $vendorType->getSiteSettings();

        // Make sure they're all there
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            if (!isset($allSiteSettings[$siteId])) {
                throw new Exception('Tried to save a vendor type that is missing site settings');
            }
        }

        foreach ($allSiteSettings as $siteId => $settings) {
            $siteUid = Db::uidById(CraftTable::SITES, $siteId);
            $configData['siteSettings'][$siteUid] = [
                'hasUrls' => $settings['hasUrls'],
                'uriFormat' => $settings['uriFormat'],
                'template' => $settings['template'],
            ];
        }

        $configPath = self::CONFIG_VENDORTYPES_KEY . '.' . $vendorType->uid;
        $projectConfig->set($configPath, $configData);

        if ($isNewVendorType) {
            $vendorType->id = Db::idByUid(VendorTypeRecord::tableName(), $vendorType->uid);
        }

        return true;
    }

    /**
     * Handle a vendor type change.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleChangedVendorType(ConfigEvent $event)
    {
        $vendorTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;

        // Make sure fields and sites are processed
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $siteData = $data['siteSettings'];

            // Basic data
            $vendorTypeRecord = $this->_getVendorTypeRecord($vendorTypeUid);
            $isNewVendorType = $vendorTypeRecord->getIsNewRecord();
            $fieldsService = Craft::$app->getFields();

            $vendorTypeRecord->uid = $vendorTypeUid;
            $vendorTypeRecord->name = $data['name'];
            $vendorTypeRecord->handle = $data['handle'];

            if (!empty($data['vendorFieldLayouts']) && !empty($config = reset($data['vendorFieldLayouts']))) {
                // Save the main field layout
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $vendorTypeRecord->fieldLayoutId;
                $layout->type = Vendor::class;
                $layout->uid = key($data['vendorFieldLayouts']);
                $fieldsService->saveLayout($layout);
                $vendorTypeRecord->fieldLayoutId = $layout->id;
            } else if ($vendorTypeRecord->fieldLayoutId) {
                // Delete the main field layout
                $fieldsService->deleteLayoutById($vendorTypeRecord->fieldLayoutId);
                $vendorTypeRecord->fieldLayoutId = null;
            }

            $vendorTypeRecord->save(false);

            // Update the site settings
            // -----------------------------------------------------------------

            $sitesNowWithoutUrls = [];
            $sitesWithNewUriFormats = [];
            $allOldSiteSettingsRecords = [];

            if (!$isNewVendorType) {
                // Get the old vendor type site settings
                $allOldSiteSettingsRecords = VendorTypeSiteRecord::find()
                    ->where(['vendorTypeId' => $vendorTypeRecord->id])
                    ->indexBy('siteId')
                    ->all();
            }

            $siteIdMap = Db::idsByUids('{{%sites}}', array_keys($siteData));

            /** @var VendorTypeSiteRecord $siteSettings */
            foreach ($siteData as $siteUid => $siteSettings) {
                $siteId = $siteIdMap[$siteUid];

                // Was this already selected?
                if (!$isNewVendorType && isset($allOldSiteSettingsRecords[$siteId])) {
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new VendorTypeSiteRecord();
                    $siteSettingsRecord->vendorTypeId = $vendorTypeRecord->id;
                    $siteSettingsRecord->siteId = $siteId;
                }

                if ($siteSettingsRecord->hasUrls = $siteSettings['hasUrls']) {
                    $siteSettingsRecord->uriFormat = $siteSettings['uriFormat'];
                    $siteSettingsRecord->template = $siteSettings['template'];
                } else {
                    $siteSettingsRecord->uriFormat = null;
                    $siteSettingsRecord->template = null;
                }

                if (!$siteSettingsRecord->getIsNewRecord()) {
                    // Did it used to have URLs, but not anymore?
                    if ($siteSettingsRecord->isAttributeChanged('hasUrls', false) && !$siteSettings['hasUrls']) {
                        $sitesNowWithoutUrls[] = $siteId;
                    }

                    // Does it have URLs, and has its URI format changed?
                    if ($siteSettings['hasUrls'] && $siteSettingsRecord->isAttributeChanged('uriFormat', false)) {
                        $sitesWithNewUriFormats[] = $siteId;
                    }
                }

                $siteSettingsRecord->save(false);
            }

            if (!$isNewVendorType) {
                // Drop any site settings that are no longer being used, as well as the associated vendor/element
                // site rows
                $affectedSiteUids = array_keys($siteData);

                /** @noinspection PhpUndefinedVariableInspection */
                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    $siteUid = array_search($siteId, $siteIdMap, false);
                    if (!in_array($siteUid, $affectedSiteUids, false)) {
                        $siteSettingsRecord->delete();
                    }
                }
            }

            // Finally, deal with the existing vendors...
            // -----------------------------------------------------------------

            if (!$isNewVendorType) {
                // Get all of the vendor IDs in this group
                $vendorIds = Vendor::find()
                    ->typeId($vendorTypeRecord->id)
                    ->anyStatus()
                    ->limit(null)
                    ->ids();

                // Are there any sites left?
                if (!empty($siteData)) {
                    // Drop the old vendor URIs for any site settings that don't have URLs
                    if (!empty($sitesNowWithoutUrls)) {
                        $db->createCommand()
                            ->update(
                                '{{%elements_sites}}',
                                ['uri' => null],
                                [
                                    'elementId' => $vendorIds,
                                    'siteId' => $sitesNowWithoutUrls,
                                ])
                            ->execute();
                    } else if (!empty($sitesWithNewUriFormats)) {
                        foreach ($vendorIds as $vendorId) {
                            App::maxPowerCaptain();

                            // Loop through each of the changed sites and update all of the vendors’ slugs and
                            // URIs
                            foreach ($sitesWithNewUriFormats as $siteId) {
                                $vendor = Vendor::find()
                                    ->id($vendorId)
                                    ->siteId($siteId)
                                    ->anyStatus()
                                    ->one();

                                if ($vendor) {
                                    Craft::$app->getElements()->updateElementSlugAndUri($vendor, false, false);
                                }
                            }
                        }
                    }
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_allVendorTypeIds = null;
        $this->_editableVendorTypeIds = null;
        $this->_fetchedAllVendorTypes = false;
        unset(
            $this->_vendorTypesById[$vendorTypeRecord->id],
            $this->_vendorTypesByHandle[$vendorTypeRecord->handle],
            $this->_siteSettingsByVendorId[$vendorTypeRecord->id]
        );

        // Fire an 'afterSaveVendorType' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_VENDORTYPE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_VENDORTYPE, new VendorTypeEvent([
                'vendorType' => $this->getVendorTypeById($vendorTypeRecord->id),
                'isNew' => empty($this->_savingVendorTypes[$vendorTypeUid]),
            ]));
        }
    }

    /**
     * Returns all vendor types by a tax category id.
     *
     * @param $taxCategoryId
     * @return array
     */
    public function getVendorTypesByTaxCategoryId($taxCategoryId): array
    {
        $rows = $this->_createVendorTypeQuery()
            ->innerJoin(VendorTypeTaxCategory::tableName() . ' vendorTypeTaxCategories', '[[vendorTypes.id]] = [[vendorTypeTaxCategories.vendorTypeId]]')
            ->where(['vendorTypeTaxCategories.taxCategoryId' => $taxCategoryId])
            ->all();

        $vendorTypes = [];

        foreach ($rows as $row) {
            $vendorTypes[$row['id']] = new VendorType($row);
        }

        return $vendorTypes;
    }

    /**
     * Returns all vendor types by a shipping category id.
     *
     * @param $shippingCategoryId
     * @return array
     */
    public function getVendorTypesByShippingCategoryId($shippingCategoryId): array
    {
        $rows = $this->_createVendorTypeQuery()
            ->innerJoin(VendorTypeShippingCategory::tableName() . ' vendorTypeShippingCategories', '[[vendorTypes.id]] = [[vendorTypeShippingCategories.vendorTypeId]]')
            ->where(['vendorTypeShippingCategories.shippingCategoryId' => $shippingCategoryId])
            ->all();

        $vendorTypes = [];

        foreach ($rows as $row) {
            $vendorTypes[$row['id']] = new VendorType($row);
        }

        return $vendorTypes;
    }

    /**
     * Deletes a vendor type by its ID.
     *
     * @param int $id the vendor type's ID
     * @return bool Whether the vendor type was deleted successfully.
     * @throws Throwable if reasons
     */
    public function deleteVendorTypeById(int $id): bool
    {
        $vendorType = $this->getVendorTypeById($id);
        Craft::$app->getProjectConfig()->remove(self::CONFIG_VENDORTYPES_KEY . '.' . $vendorType->uid);
        return true;
    }

    /**
     * Handle a vendor type getting deleted.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleDeletedVendorType(ConfigEvent $event)
    {
        $uid = $event->tokenMatches[0];
        $vendorTypeRecord = $this->_getVendorTypeRecord($uid);

        if (!$vendorTypeRecord->id) {
            return;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $vendors = Vendor::find()
                ->typeId($vendorTypeRecord->id)
                ->anyStatus()
                ->limit(null)
                ->all();

            foreach ($vendors as $vendor) {
                Craft::$app->getElements()->deleteElement($vendor);
            }

            $fieldLayoutId = $vendorTypeRecord->fieldLayoutId;
            $variantFieldLayoutId = $vendorTypeRecord->variantFieldLayoutId;
            Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);

            if ($variantFieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($variantFieldLayoutId);
            }

            $vendorTypeRecord->delete();
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // Clear caches
        $this->_allVendorTypeIds = null;
        $this->_editableVendorTypeIds = null;
        $this->_fetchedAllVendorTypes = false;
        unset(
            $this->_vendorTypesById[$vendorTypeRecord->id],
            $this->_vendorTypesByHandle[$vendorTypeRecord->handle],
            $this->_siteSettingsByVendorId[$vendorTypeRecord->id]
        );
    }

    /**
     * Prune a deleted site from category group site settings.
     *
     * @param DeleteSiteEvent $event
     */
    public function pruneDeletedSite(DeleteSiteEvent $event)
    {
        $siteUid = $event->site->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $vendorTypes = $projectConfig->get(self::CONFIG_VENDORTYPES_KEY);

        // Loop through the vendor types and prune the UID from field layouts.
        if (is_array($vendorTypes)) {
            foreach ($vendorTypes as $vendorTypeUid => $vendorType) {
                $projectConfig->remove(self::CONFIG_VENDORTYPES_KEY . '.' . $vendorTypeUid . '.siteSettings.' . $siteUid);
            }
        }
    }

    /**
     * Prune a deleted field from category group layouts.
     *
     * @param FieldEvent $event
     */
    public function pruneDeletedField(FieldEvent $event)
    {
        /** @var Field $field */
        $field = $event->field;
        $fieldUid = $field->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $vendorTypes = $projectConfig->get(self::CONFIG_VENDORTYPES_KEY);

        // Loop through the vendor types and prune the UID from field layouts.
        if (is_array($vendorTypes)) {
            foreach ($vendorTypes as $vendorTypeUid => $vendorType) {
                if (!empty($vendorType['vendorFieldLayouts'])) {
                    foreach ($vendorType['vendorFieldLayouts'] as $layoutUid => $layout) {
                        if (!empty($layout['tabs'])) {
                            foreach ($layout['tabs'] as $tabUid => $tab) {
                                $projectConfig->remove(self::CONFIG_VENDORTYPES_KEY . '.' . $vendorTypeUid . '.vendorFieldLayouts.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                            }
                        }
                    }
                }
                if (!empty($vendorType['variantFieldLayouts'])) {
                    foreach ($vendorType['variantFieldLayouts'] as $layoutUid => $layout) {
                        if (!empty($layout['tabs'])) {
                            foreach ($layout['tabs'] as $tabUid => $tab) {
                                $projectConfig->remove(self::CONFIG_VENDORTYPES_KEY . '.' . $vendorTypeUid . '.variantFieldLayouts.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns a vendor type by its ID.
     *
     * @param int $vendorTypeId the vendor type's ID
     * @return VendorType|null either the vendor type or `null`
     */
    public function getVendorTypeById(int $vendorTypeId)
    {
        if (isset($this->_vendorTypesById[$vendorTypeId])) {
            return $this->_vendorTypesById[$vendorTypeId];
        }

        if ($this->_fetchedAllVendorTypes) {
            return null;
        }

        $result = $this->_createVendorTypeQuery()
            ->where(['id' => $vendorTypeId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeVendorType(new VendorType($result));

        return $this->_vendorTypesById[$vendorTypeId];
    }

    /**
     * Returns a vendor type by its UID.
     *
     * @param string $uid the vendor type's UID
     * @return VendorType|null either the vendor type or `null`
     */
    public function getVendorTypeByUid(string $uid)
    {
        return ArrayHelper::firstWhere($this->getAllVendorTypes(), 'uid', $uid, true);
    }

    /**
     * Returns whether a vendor type’s vendors have URLs, and if the template path is valid.
     *
     * @param VendorType $vendorType The vendor for which to validate the template.
     * @param int $siteId The site for which to valid for
     * @return bool Whether the template is valid.
     * @throws Exception
     */
    public function isVendorTypeTemplateValid(VendorType $vendorType, int $siteId): bool
    {
        $vendorTypeSiteSettings = $vendorType->getSiteSettings();

        if (isset($vendorTypeSiteSettings[$siteId]) && $vendorTypeSiteSettings[$siteId]->hasUrls) {
            // Set Craft to the site template mode
            $view = Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = Craft::$app->getView()->doesTemplateExist((string)$vendorTypeSiteSettings[$siteId]->template);

            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a new vendor type setting row when a Site is added to Craft.
     *
     * @param SiteEvent $event The event that triggered this.
     */
    public function afterSaveSiteHandler(SiteEvent $event)
    {
        $projectConfig = Craft::$app->getProjectConfig();

        if ($event->isNew) {
            $oldPrimarySiteUid = Db::uidById(CraftTable::SITES, $event->oldPrimarySiteId);
            $existingVendorTypeSettings = $projectConfig->get(self::CONFIG_VENDORTYPES_KEY);

            if (is_array($existingVendorTypeSettings)) {
                foreach ($existingVendorTypeSettings as $vendorTypeUid => $settings) {
                    $primarySiteSettings = $settings['siteSettings'][$oldPrimarySiteUid];
                    $configPath = self::CONFIG_VENDORTYPES_KEY . '.' . $vendorTypeUid . '.siteSettings.' . $event->site->uid;
                    $projectConfig->set($configPath, $primarySiteSettings);
                }
            }
        }
    }

    // Private methods
    // =========================================================================

    /**
     * Memoize a vendor type
     *
     * @param VendorType $vendorType The vendor type to memoize.
     */
    private function _memoizeVendorType(VendorType $vendorType)
    {
        $this->_vendorTypesById[$vendorType->id] = $vendorType;
        $this->_vendorTypesByHandle[$vendorType->handle] = $vendorType;
    }

    /**
     * Returns a Query object prepped for retrieving purchasables.
     *
     * @return Query The query object.
     */
    private function _createVendorTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'vendorTypes.id',
                'vendorTypes.fieldLayoutId',
                'vendorTypes.name',
                'vendorTypes.handle',
                'vendorTypes.uid'
            ])
            ->from([VendorTypeRecord::tableName() . ' vendorTypes']);
    }

    /**
     * Gets a vendor type's record by uid.
     *
     * @param string $uid
     * @return VendorTypeRecord
     */
    private function _getVendorTypeRecord(string $uid): VendorTypeRecord
    {
        if ($vendorType = VendorTypeRecord::findOne(['uid' => $uid])) {
            return $vendorType;
        }

        return new VendorTypeRecord();
    }
}
