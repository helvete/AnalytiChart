<?php

namespace App\Services;

class StatisticsSubscription extends StatisticsAbstract
{
	/** @var \App\Services\GeoIp **/
	private $_geoIp;
	/** @var \App\Models\Subscription\Collection @inject **/
	var $subscriptions;
	/** @var \App\Models\UserSubscription\Collection @inject **/
	var $uhs;

	static private $_dbCache;

	public function __construct(\App\Services\GeoIp $geo) {
		$this->_geoIp = $geo;

		parent::__construct('subscription');
	}

	const SUBSCRIPTIONS_NEW = 'SUBSCRIPTIONS_NEW';

	const DIMENSION_SUBS_DEVICE = 'subscription_device';
	const DIMENSION_SUBS_COUNTRY = 'subscription_country';
	const DIMENSION_SUBS_SUBSCRIPTION = 'subscription_subscription';

	/**
	 * Returns human readable name for the given $metric
	 *
	 * @param  string $metric
	 * @return string
	 */
	static public function getLabelFor($metric)
	{
		switch ($metric) {
		case self::SUBSCRIPTIONS_NEW:
			return 'New subscriptions';
		default:
			throw new \Exception("Unsupported metric '{$metric}'");
		}
	}


	/**
	 * Returns human readable description for the given $metric
	 *
	 * @param  string $metric
	 * @return string
	 */
	static public function getDescFor($metric)
	{
		switch ($metric) {
		case self::SUBSCRIPTIONS_NEW:
			return 'Count of purchased new subscriptions';
		default:
			throw new \Exception("Unsupported metric '{$metric}'");
		}
	}


	/**
	 * Returns human readable name for the given $dimension
	 *
	 * @param  string $dimension
	 * @return string
	 */
	public static function getLabelForDimension($dimension)
	{
		switch ($dimension) {
		case self::DIMENSION_SUBS_DEVICE:
			return 'Platform';
		case self::DIMENSION_SUBS_COUNTRY:
			return 'Country';
		case self::DIMENSION_SUBS_SUBSCRIPTION:
			return 'Subscription';
		default:
			throw new \Exception("Unsupported dimension '{$dimension}'");
		}
	}


	/**
	 * Fetch data to display
	 *
	 * @param  \DateTime	$start
	 * @param  \DateTime	$end
	 * @param  string|int	$dimension
	 * @param  string		$metric
	 * @return float
	 */
	protected function _fetchData($start, $end, $metric, $d1, $d2) {
		if (!isset(self::$_dbCache[$metric])) {
			$col = clone $this->uhs;
			$col = $col->getTable();
			$col->select('start_date, user_id, is_apple, user_subscription.id')
				->select('country_code')
				->select('subscription.code AS code')
				->order('start_date ASC');

			self::$_dbCache[$metric] = $col->fetchAssoc('id');
		}
		$data = 0;
		foreach (self::$_dbCache[$metric] as $record) {
			if ($record['start_date'] < $start) {
				continue;
			}
				if ($record['start_date'] >= $end) {
				break;
			}

			// evaluate record
			$add = true;
			if ($d1) {
				list($methName, $val) = unserialize($d1);
				$add = self::$methName($record, $add, $val);
			}
			if ($d2) {
				list($methName, $val) = unserialize($d2);
				$add = self::$methName($record, $add, $val);
			}

			$data = $add
				? $data + 1
				: $data;
		}

		return $data;
	}


	/**
	 * Helper functions to evaluate whether to include the record based on
	 * expected evaluation result and current evaluation state
	 *
	 * @param  \Argo22\Core\DataModel\Model $record
	 * @param  bool							$current
	 * @param  string|bool|int				$val
	 * @return bool
	 */
	static private function _hasDevice($record, $current, $val){
		return parent::_evalVal($current, $record['is_apple'], $val);
	}
	static private function _hasCountry($record, $current, $val){
		return parent::_evalVal($current, $record['country_code'], $val);
	}
	static private function _hasSubscription($record, $current, $val){
		return parent::_evalVal($current, $record['code'], $val);
	}


	/**
	 * Get dimension items. Each of these items represents a table row
	 *
	 * @param  string|bool	$dimension
	 * @return array
	 */
	protected function _getDimensionItems($dimension = false) {
		if (!$dimension) {
			return array(null);
		}
		switch ($dimension) {
		case self::DIMENSION_SUBS_DEVICE:
			return array(
				array(
					'id' => serialize(array('_hasDevice', 1)),
					'value' => 'Ios',
				),
				array(
					'id' => serialize(array('_hasDevice', 0)),
					'value' => 'Android',
				),
				array(
					'id' => serialize(array('_hasDevice', null)),
					'value' => '<i>Unknown</i>',
				),
			);
		case self::DIMENSION_SUBS_COUNTRY:
			$return = array();
			foreach ($this->_getCountries() as $countryCode) {
				if ($countryCode == '') {
					$return[] = array(
						'id' => serialize(array(
							'_hasCountry',
							null
						)),
						'value' => 'Unknown',
					);
					continue;
				}
				$return[] = array(
					'id' => serialize(array(
						'_hasCountry',
						$countryCode
					)),
					'value'
						=> $this->_geoIp->getCountryNameForIsoCode($countryCode),
				);
			}

			return $return;

		case self::DIMENSION_SUBS_SUBSCRIPTION:
			$return = array();
			foreach ($this->_getSubs() as $subs) {
				$return[] = array(
					'id' => serialize(array('_hasSubscription', $subs)),
					'value' => $subs,
				);
			}

			return $return;
		}
	}


	/**
	 * Get available user country codes which to select from
	 *
	 * @return array
	 */
	private function _getCountries() {

		static $cache;
		if (is_null($cache)) {
			$c = clone $this->uhs;
			$ctr = $c->getTable()
				->select('country_code')
				->group('country_code');
			$cache = array_keys($ctr->fetchAssoc('country_code'));
		}

		return $cache;
	}


	/**
	 * Get available subscriptions
	 *
	 * @return array
	 */
	private function _getSubs() {

		static $cache;
		if (is_null($cache)) {
			$s = $this->subscriptions->getTable()
				->select('code');
			$cache = array_keys($s->fetchAssoc('code'));
		}

		return $cache;
	}
}

