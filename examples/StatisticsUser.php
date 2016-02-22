<?php

namespace App\Services;

class StatisticsUser extends \Nette\Object
{
	const USERS_TOTAL = 'USERS_TOTAL';
	const USERS_ACTIVE = 'USERS_ACTIVE';

	const DIMENSION_USER = 'users';
	const DIMENSION_SUBSCRIPTION = 'subscriptions';

	/**
	 * Returns human readable name for the given $metric
	 *
	 * @param  string $metric
	 * @return string
	 */
	static public function getLabelFor($metric)
	{
		switch ($metric) {
		case self::USERS_TOTAL:
			return 'total_users_count';
		case self::USERS_ACTIVE:
			return 'active_users_count';
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
		case self::USERS_TOTAL:
			return 'Description USERS_TOTAL';
		case self::USERS_ACTIVE:
			return 'Description USERS_ACTIVE';
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
		case self::DIMENSION_USER:
			return 'User';
		case self::DIMENSION_SUBSCRIPTION:
			return 'Subscription';
		default:
			throw new \Exception("Unsupported dimension '{$dimension}'");
		}
	}


	/**
	 * Retrieve data for the Chart component
	 *
	 * @param  array	$metrics
	 * @param  string	$from
	 * @param  string	$to
	 * @param  string	$lod
	 * @param  array	$dimension
	 * @return array
	 */
	static public function getTimeline($metrics, $from, $to, $lod, $dimensions)
	{
		$dim = empty($dimensions)
			? null
			: reset($dimensions);
		$labels = array();
		$data = array();
		foreach ($metrics as $metric) {
			$metricData = self::_get($metric, $from, $to, $lod, $dim);
			if (empty($labels)) {
				$labels = array_keys($metricData);
			}
			$data[$metric] = array_values($metricData);
		}

		return array(
			'labels' => $labels,
			'columns' => $data,
		);
	}


	/**
	 * Retrieve data for the Table component
	 *
	 * @param  array	$metrics
	 * @param  string	$from
	 * @param  string	$to
	 * @param  string	$lod
	 * @param  array	$dimension
	 * @return array
	 */
	static public function getTabular($metrics, $from, $to, $lod, $dimensions,
		$conditions = array()
	) {
		$data = array();
		do {
			$dim = (string)reset($dimensions);
			$dimItems = self::_getDimensionItems($dim);
			foreach ($dimItems as $item) {
				$filter = $item
					? $item['id']
					: null;
				$temp = array();
				foreach ($metrics as $metric) {
					$metricData = self::_get($metric, $from, $to, $lod, $filter);
					$temp[$metric] = array_sum(array_values($metricData));
					if ($item) {
						$temp[$dim] = $item;
					}
				}
				$data[] = $temp;
			}
			if (!empty($dimensions)) {
				array_shift($dimensions);
			}
		} while (!empty($dimensions));

		return $data;
	}


	/**
	 * Get metrics data. Utilize the lod settings to retrieve data having
	 * adequate granularity
	 *
	 * @param  string	$metric
	 * @param  string	$from
	 * @param  string	$to
	 * @param  string	$lod
	 * @param  string	$dimension
	 * @return array
	 */
	static protected function _get($metric, $from, $to, $lod, $dimension = '')
	{
		static $cache;
		$toDt = new \DateTime($to);

		// create DateInterval string based on lod
		$interval = "P";
		switch ($lod) {
		case 'hour':
			$interval .= "T1H";
			break;
		case 'day':
			$interval .= "1D";
			break;
		case 'week':
			$interval .= "1W";
			break;
		case 'month':
			$interval .= "1M";
			break;
		}
		// utilize cache if present
		if (isset($cache["$metric$from$to$lod$dimension"])) {
			return $cache["$metric$from$to$lod$dimension"];
		}
		// or harvest the data if not cached
		$token = new \DateTime($from);
		$data = array();
		while ($token < $toDt) {
			$data[$token->format('Y-m-d H:i:s')]
				= self::_fetchData($token, $dimension, $metric);
			$token->add(new \DateInterval($interval));
		}
		// cache harvested data
		return $cache["$metric$from$to$lod$dimension"] = $data;
	}


	/**
	 * Fetch data to display
	 *
	 * @param  \DateTime	$seed
	 * @param  string|int	$dimension
	 * @param  string		$metric
	 * @return float
	 */
	static protected function _fetchData($seed, $filter, $metric) {
		$y = (int)$seed->format('y');
		$m = (int)$seed->format('m');
		$d = (int)$seed->format('d');
		$h = (int)$seed->format('h');

		$determinable = $y + $m + $d + $h;
		$return = $determinable % 2 === 1
			? (int)$determinable / 2
			: $determinable;

		$metric = $metric === 'USERS_TOTAL'
			? 1
			: 0.5;

		switch ($filter) {
		case 1:
			return 60*$return/100*$metric;
		case 2:
			return 40*$return/100*$metric;
		default:
			return $return*$metric;
		}

		throw new \Exception('What the heck happened?');
	}


	/**
	 * Get dimension items. Each of these items represents a table row
	 *
	 * @param  string|bool	$dimension
	 * @return array
	 */
	static protected function _getDimensionItems($dimension = false) {
		if (!$dimension) {
			return array(null);
		}

		// get individual items for given dimension. from DB perhaps
		return array(
			['id' => 1, 'value' => 'Item no 1!'],
			['id' => 2, 'value' => 'Item no 2!']
		);
	}
}
