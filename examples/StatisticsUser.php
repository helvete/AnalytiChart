<?php

namespace App\Services;

class StatisticsUser extends \Argo22\AnalyticChart\StatisticsAbstract
{
	/** @var \Argo22\Modules\Core\Api\Account\Model **/
	private $_collection;
	/** @var \App\Services\GeoIp **/
	private $_geoIp;

	static private $_dbCache;

	public function __construct(
		\Argo22\Modules\Core\Api\Account\Collection $coll,
		\Argo22\Modules\Core\Api\GeoIp $geo
	) {
		$this->_collection = $coll;
		$this->_geoIp = $geo;

		parent::__construct('user');
	}

	const USERS_ACTIVE = 'USERS_ACTIVE';
	const USERS_TOTAL = 'USERS_TOTAL';

	const DIMENSION_USER_REFERRAL = 'user_referral';
	const DIMENSION_USER_SOURCE = 'user_source';
	const DIMENSION_USER_COUNTRY = 'user_country';

	/**
	 * Returns human readable name for the given $metric
	 *
	 * @param  string $metric
	 * @return string
	 */
	static public function getLabelFor($metric)
	{
		switch ($metric) {
		case self::USERS_ACTIVE:
			return 'Active Users count';
		case self::USERS_TOTAL:
			return 'Created Accounts count';
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
		case self::USERS_ACTIVE:
			return 'Count of users who have successfully completed account '
				. 'activation';
		case self::USERS_TOTAL:
			return 'Count of users who created an account';
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
		case self::DIMENSION_USER_REFERRAL:
			return 'Referral users';
		case self::DIMENSION_USER_SOURCE:
			return 'Source of users';
		case self::DIMENSION_USER_COUNTRY:
			return 'Country of origin';
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
		if (is_null(self::$_dbCache)) {
			$col = clone $this->_collection;
			$col = $col->getTable();
			$col->select('inviter_account_id, registration_source')
				->select('country_code, created, state')
				->order("created ASC");

			self::$_dbCache = $col->fetchAssoc('created');
		}
		$data = 0;
		foreach (self::$_dbCache as $record) {
			if ($record['created'] < $start) {
				continue;
			}
			if ($record['created'] > $end) {
				break;
			}

			// evaluate record
			$add = false;
			switch ($metric) {
			case self::USERS_ACTIVE:
				if ($record['state']
					=== \Argo22\Modules\Core\Api\Account\Model::STATE_ACTIVE
				) {
					$add = true;
				}
				break;
			case self::USERS_TOTAL:
				$add = true;
				break;
			}

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
	static private function _hasInviter($record, $current, $val){
		if ($current === false) {
			return false;
		}
		if ($val === ($record['inviter_account_id'] === null)) {
			return true;
		}
		return false;
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
	static private function _hasSource($record, $current, $val){
		if ($current === false) {
			return false;
		}
		if ($record['registration_source'] === $val) {
			return true;
		}
		return false;
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
	static private function _hasCountry($record, $current, $val){
		if ($current === false) {
			return false;
		}
		if ($record['country_code'] === $val) {
			return true;
		}
		return false;
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
		case self::DIMENSION_USER_REFERRAL:
			return array(
				array(
					'id' => serialize(array('_hasInviter', true)),
					'value' => 'Invited users',
				),
				array(
					'id' => serialize(array('_hasInviter', false)),
					'value' => 'Non-invited users',
				),
			);
		case self::DIMENSION_USER_SOURCE:
			return array(
				array(
					'id' => serialize(array(
						'_hasSource',
						\Argo22\Modules\Core\Api\Account\Model::SOURCE_APP
					)),
					'value' => 'Mobile App',
				),
				array(
					'id' => serialize(array(
						'_hasSource',
						\Argo22\Modules\Core\Api\Account\Model::SOURCE_WEB
					)),
					'value' => 'Microsite',
				),
			);
		case self::DIMENSION_USER_COUNTRY:
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
			$c = clone $this->_collection;
			$ctr = $c->getTable()
				->select('country_code')
				->group('country_code');
			$cache = array_keys($ctr->fetchAssoc('country_code'));
		}

		return $cache;
	}
}
