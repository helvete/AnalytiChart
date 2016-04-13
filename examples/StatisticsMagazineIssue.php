<?php

namespace App\Services;

class StatisticsMagazineIssue extends \Argo22\AnalyticChart\StatisticsAbstract
{
	/** @var \App\Models\StatMagazineIssueRead\Collection **/
	private $_statRead;
	/** @var \App\Models\StatMagazineIssueDownload\Collection **/
	private $_statDownload;
	/** @var \App\Services\GeoIp **/
	private $_geoIp;
	/** @var \App\Models\Subscription\Collection @inject **/
	var $subscriptions;
	/** @var \App\Models\Magazine\Collection @inject **/
	var $mags;
	/** @var \App\Models\MagazineIssue\Collection @inject **/
	var $issues;

	static private $_dbCache;

	public function __construct(
		\App\Models\StatMagazineIssueRead\Collection $collRead,
		\App\Models\StatMagazineIssueDownload\Collection $collDown,
		\Argo22\Modules\Core\Api\GeoIp $geo
	) {
		$this->_statRead = $collRead;
		$this->_statDownload = $collDown;
		$this->_geoIp = $geo;

		parent::__construct('magazineIssue');
	}

	const ISSUES_READ = 'ISSUES_READ';
	const ISSUES_DOWNLOADED = 'ISSUES_DOWNLOADED';

	const DIMENSION_MI_DEVICE = 'issue_device';
	const DIMENSION_MI_COUNTRY = 'issue_country';
	const DIMENSION_MI_SUBSCRIPTION = 'issue_subscription';
	const DIMENSION_MI_MAGAZINE = 'issue_magazine';
	const DIMENSION_MI_MAGAZINE_ISSUE = 'issue_magazine_issue';

	/**
	 * Returns human readable name for the given $metric
	 *
	 * @param  string $metric
	 * @return string
	 */
	static public function getLabelFor($metric)
	{
		switch ($metric) {
		case self::ISSUES_READ:
			return 'Magazine issues read';
		case self::ISSUES_DOWNLOADED:
			return 'Magazine issues downloaded';
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
		case self::ISSUES_READ:
			return 'Count of magazine issues that have been read';
		case self::ISSUES_DOWNLOADED:
			return 'Count of magazine issues that have been downloaded';
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
		case self::DIMENSION_MI_DEVICE:
			return 'Platform';
		case self::DIMENSION_MI_COUNTRY:
			return 'Country';
		case self::DIMENSION_MI_SUBSCRIPTION:
			return 'Subscription';
		case self::DIMENSION_MI_MAGAZINE:
			return 'Magazine';
		case self::DIMENSION_MI_MAGAZINE_ISSUE:
			return 'Magazine issue';
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
			if ($metric === self::ISSUES_READ) {
				$collectionName = "_statRead";
				$cn = "stat_magazine_issue_read";
			} else {
				$collectionName = "_statDownload";
				$cn = "stat_magazine_issue_download";
			}
			$col = clone $this->$collectionName;
			$col = $col->getTable();
			$col->select('is_apple, country_code, subscription.code AS code')
				->select('magazine.name AS mag, magazine_issue.name AS issue')
				->select("{$cn}.created AS created")
				->order("{$cn}.created ASC");

			self::$_dbCache[$metric] = $col->fetchAssoc('created');
		}
		$data = 0;
		foreach (self::$_dbCache[$metric] as $record) {
			if ($record['created'] < $start) {
				continue;
			}
			if ($record['created'] > $end) {
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
	static private function _hasMagazine($record, $current, $val) {
		return parent::_evalVal($current, $record['mag'], $val);
	}
	static private function _hasIssue($record, $current, $val) {
		return parent::_evalVal($current, $record['issue'], $val);
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
		case self::DIMENSION_MI_DEVICE:
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
		case self::DIMENSION_MI_COUNTRY:
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

		case self::DIMENSION_MI_SUBSCRIPTION:
			$return = array();
			foreach ($this->_getSubs() as $subs) {
				$return[] = array(
					'id' => serialize(array('_hasSubscription', $subs)),
					'value' => $subs,
				);
			}

			return $return;

		case self::DIMENSION_MI_MAGAZINE:
			$return = array();
			foreach ($this->_getMags() as $mag) {
				$return[] = array(
					'id' => serialize(array('_hasMagazine', $mag)),
					'value' => $mag,
				);
			}

			return $return;

		case self::DIMENSION_MI_MAGAZINE_ISSUE:
			$return = array();
			foreach ($this->_getIssues() as $mag) {
				$return[] = array(
					'id' => serialize(array('_hasIssue', $mag)),
					'value' => $mag,
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
			$c = clone $this->_statRead;
			$ctr = $c->getTable()
				->select('country_code')
				->group('country_code');

			$d = clone $this->_statDownload;
			$dtr = $d->getTable()
				->select('country_code')
				->group('country_code');

			$cache = array_unique(array_merge(
				array_keys($ctr->fetchAssoc('country_code')),
				array_keys($dtr->fetchAssoc('country_code'))
			));
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


	/**
	 * Get available magazines
	 *
	 * @return array
	 */
	private function _getMags() {

		static $cache;
		if (is_null($cache)) {
			$s = $this->mags->getTable()
				->select('name');
			$cache = array_keys($s->fetchAssoc('name'));
		}

		return $cache;
	}


	/**
	 * Get available magazines
	 *
	 * @return array
	 */
	private function _getIssues() {

		static $cache;
		if (is_null($cache)) {
			$s = $this->issues->getTable()
				->select('name')
				->group('id');
			$cache = array_keys($s->fetchAssoc('name'));
		}

		return $cache;
	}
}
