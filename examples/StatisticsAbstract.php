<?php

namespace App\Services;

class StatisticsAbstract extends \Nette\Object
{
	/**
 	 * Instance holder
 	 * @var self
 	 */
	static private $_instance;

	/**
 	 * Class construct
 	 * Store new instance
 	 */
 	public function __construct() {
		self::$_instance = $this;
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
	public function getTimeline($metrics, $from, $to, $lod, $dimensions)
	{
		$dim = empty($dimensions)
			? null
			: reset($dimensions);
		$labels = array();
		$data = array();
		foreach ($metrics as $metric) {
			$metricData = $this->_get($metric, $from, $to, $lod, $dim);
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
	public function getTabular($metrics, $from, $to, $lod, $dimensions)
	{
		foreach (range(1, 2) as $number) {
			$varName = "dim{$number}Items";
			$val = count($dimensions) > 0
				? array_shift($dimensions)
				: null;
			$var = "d{$number}Name";
			$$var = $val;
			$$varName = $this->_getDimensionItems($val);
		}

		$data = array();
		foreach ($dim1Items as $dim1Item) {
			foreach ($dim2Items as $dim2Item) {
				$d1 = isset($dim1Item['id'])
					? $dim1Item['id']
					: null;
				$d2 = isset($dim2Item['id'])
					? $dim2Item['id']
					: null;

				$nonZero = 0;
				$dimensionMetrics = array();
				foreach ($metrics as $metric) {
					$metricData
						= $this->_get($metric, $from, $to, $lod, $d1, $d2);
					$value = array_sum(array_values($metricData));
					$dimensionMetrics[$metric] = $value;
					$nonZero = $nonZero > 0
						? $nonZero
						: (double) $value;
				}
				if (!is_null($d1)) {
					$dimensionMetrics[$d1Name] = $dim1Item;
				}
				if (!is_null($d2)) {
					$dimensionMetrics[$d2Name] = $dim2Item;
				}
				// add only rows having value of at least on column non-zero
				if ($nonZero > 0) {
					$data[] = $dimensionMetrics;
				}
			}
		}

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
	protected function _get($metric, $from, $to, $lod, $d1 = '', $d2 = '')
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
		if (isset($cache["$metric$from$to$lod$d1$d2"])) {
			return $cache["$metric$from$to$lod$d1$d2"];
		}
		// or harvest the data if not cached
		$token = new \DateTime($from);
		$data = array();
		while ($token < $toDt) {
			$end = clone $token;
			$end->add(new \DateInterval($interval));

			$data[$token->format('Y-m-d H:i:s')]
				= $this->_fetchData($token, $end, $metric, $d1, $d2);
			$token = $end;
		}
		// cache harvested data
		return $cache["$metric$from$to$lod$d1$d2"] = $data;
	}


	/**
 	 * Get statistics class instance.
 	 * Singleton pattern utilised in order to be able to feed chart/table
 	 * data source callback
 	 *
 	 * @return self
 	 */
	static public function getInstance()
	{
		return self::$_instance;
	}
}
