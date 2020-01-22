<?php
/**
 * Craft Commerce Multi Vendor plugin for Craft CMS 3.x
 *
 * Support multi vendors on your Craft Commerce store.
 *
 * @link      https://www.joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace batchnz\craftcommercemultivendor\services;

use batchnz\craftcommercemultivendor\Plugin;
use batchnz\craftcommercemultivendor\models\PlatformSettings as PlatformSettingsModel;
use batchnz\craftcommercemultivendor\records\PlatformSettings as PlatformSettingsRecord;

use Craft;
use craft\base\Component;

/**
 * Platform Service
 *
 * @author    Josh Smith
 * @package   CraftCommerceMultiVendor
 * @since     1.0.0
 */
class Platform extends Component
{
	/**
	 * Returns the platform settings model
	 * @author Josh Smith <josh@batch.nz>
	 * @return PlatformSettingsModel
	 */
	public function getSettings(): PlatformSettingsModel
	{
		$settingsRecord = PlatformSettingsRecord::find()->one();
		return new PlatformSettingsModel($settingsRecord);
	}

	/**
	 * Calculates the platform commission on the passed amount
	 * @author Josh Smith <josh@batch.nz>
	 * @param  float  $amount
	 * @return float
	 */
	public function calcCommission(float $amount = 0.00): float
	{
		$settings = $this->getSettings();

		// Just return the fixed amount
		if( $settings->commissionType === PlatformSettingsModel::COMMISSION_TYPE_AMOUNT ){
			return $settings->commission;
		}

		// Default action is to calcaulte commission as a percentage
		$commission = (($amount/100) * $settings->commission);

		return round($commission, 2);
	}
}
