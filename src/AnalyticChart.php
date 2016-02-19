<?php
/**
 * Extra_VC_Ajax_AnalyticChart class file.
 *
 * @package    Eos_VC_Ajax
 */

/**
 *  Analytic Chart
 *  Using C3 chart library for displaying the chart data (C3js.org).
 *
 * @category   Eos
 * @package    Eos_VC_Ajax
 */
class Extra_VC_Ajax_AnalyticChart extends AbstractChart
{
	/**
	 * Chart data keys
	 *
	 * Important!!! Must NOT be overwritten with any other metric key.
	 */
	const PRIMARY_METRIC_KEY = 'primary_metric';
	const SECONDARY_METRIC_KEY = 'secondary_metric';
	const X_AXIS_KEY = 'x';
	const Y_AXIS_KEY = 'y';
	const Y2_AXIS_KEY = 'y2';

	/**
	 * Controlls which y axis should be used in case of same type for primary
	 * and secondary metric.
	 */
	const AXIS_SWITCH_NONE = 'NONE';
	const AXIS_SWITCH_ALWAYS = 'ALWAYS';
	const AXIS_SWITCH_DYNAMIC = 'DYNAMIC';

	/**
	 * Analytic table identifier
	 *
	 * @var string
	 */
	private $_tableIdentifier = null;

	/**
	 * User defined start date
	 *
	 * @var \DateTime
	 */
	private $_startDate = null;

	/**
	 * User defined end date
	 *
	 * @var \DateTime
	 */
	private $_endDate = null;

	/**
	 * Array of given metrics
	 *
	 * @var array
	 */
	private $_metrics = null;

	/**
	 * Callback function from where the data are retrieved
	 *
	 * @var mixed
	 */
	private $_dataSource = null;

	/**
	 * Whether LOD picker should be displayed
	 *
	 * @var boolean
	 */
	private $_useLod = true;

	/**
	 * Holds LOD levels
	 * @var array
	 */
	private $_lodLevels = array();

	/**
	 * Mode for y axis mode
	 * @var string
	 */
	private $_axisSwitchMode = self::AXIS_SWITCH_NONE;

	/**
	 * Scale factor to test if minimal maximum value is within range of max-max
	 * value.
	 * @var number
	 */
	private $_axisSwitchScale = 2;

	/**
	 * Set whether there should be metric picker or not;
	 * @var boolean
	 */
	private $_showMetricPicker = true;

	/**
	 * Set whether there should be gridlines for Y axis
	 * @var boolean
	 */
	private $_showGridLines = false;

	/**
	 * Set display types for the lines
	 * @var array
	 */
	private $_lineTypes = array(
		Extra_VC_Ajax_AnalyticChart::PRIMARY_METRIC_KEY => 'area',
	);

	/**
	 * Point size
	 * @var float
	 */
	private $_pointSize = 2.5;

	/**
	 * Color pattern
	 * @var array
	 */
	private $_colorPattern = array();

	/**
	 * Regions
	 * @var array
	 */
	private $_regions = array();

	/**
	 * Zoom
	 * @var bool
	 */
	private $_zoomEnabled = false;


	/**
	 * Returns array of possible settings for axis switch
	 *
	 * @return array
	 */
	static public function axisSwitchArray()
	{
		return array (
			self::AXIS_SWITCH_NONE,
			self::AXIS_SWITCH_ALWAYS,
			self::AXIS_SWITCH_DYNAMIC,
		);
	}


	/**
	 * Set instance identifier
	 * Load assets via assetsLoad definitions:
	 * [
	 *	'instance' = <headerAssetsControlInstance>
	 *	'methodCss' = <methodNameToAddCssFile>
	 *	'methodJs' = <methodNameToAddJsFile>
	 * ]
	 * Supply session handler definitions similarly to the assets
	 * [
	 *	'instance' = <sessionHandlerInstance>
	 *	'methodSet' = <methodNameToSetData>
	 *	'methodGet' = <methodNameToGetData>
	 * ]
	 *
	 * @param  string 						$identifier
	 * @param  array						$assetsLoad
	 * @param  array						$sessionLoad
	 * @return Extra_VC_Ajax_AnalyticChart
	 */
	public function __construct($identifier, $assetsLoad, $sessionLoad = array())
	{
		// define asset files to include to page head
		$assets = array(
			'css' => array (
				'c3.css',
				'vc_analytic_chart.css',
			),
			'js' => array (
				'metric.formats.js',
				'd3.min.js',
				'c3.min.js',
				'analytic_chart.js',
			),
		);
		parent::__construct($identifier, $assetsLoad, $assets, $sessionLoad);
	}


	/**
	 * Setter for analytic table identifier. Enable interaction
	 * with instance of Extra_VC_Ajax_AnalyticTable.
	 *
	 * @param  string	 				$identifier
	 * @return Extra_VC_Ajax_AnalyticTable
	 */
	public function setTableIdentifier($identifier)
	{
		$this->_tableIdentifier = $identifier;
		return $this;
	}


	/**
	 * Setter for start date
	 *
	 * @param  \DateTime 				$start
	 * @return Extra_VC_Ajax_AnalyticChart
	 */
	public function setStartDate(\DateTime $start)
	{
		$this->_startDate = $start;
		return $this;
	}


	/**
	 * Setter for end date
	 *
	 * @param  \DateTime 				$end
	 * @return Extra_VC_Ajax_AnalyticChart
	 */
	public function setEndDate(\DateTime $end)
	{
		$this->_endDate = $end;
		return $this;
	}


	/**
	 * Setter for chart metrics
	 *
	 * array(
	 *   'product' => array(
	 *     'caption' => 'Product',
	 *     'type' => 'ABSOLUTE',
	 *     'shadowMetrics' => array(
	 *       'sold' => array(
	 *         'caption' => 'Sold %',
	 *         'type' => 'RELATIVE',
	 *       ),
	 *     ),
	 *   ),
	 *   'partner' => array(
	 *     'caption' => 'Partner',
	 *   ...
	 * )
	 *
	 *
	 * @param  array 					$metrics
	 * @return Extra_VC_Ajax_AnalyticChart
	 */
	public function setMetrics($metrics)
	{
		$this->_metrics = $metrics;
		return $this;
	}


	/**
	 * Setter for callback function returning data
	 *
	 * Data source could be:
	 * - Class::method
	 * - array(instance, 'function')
	 *
	 * @param  mixed 					$dataSource
	 * @return Extra_VC_Ajax_AnalyticChart
	 */
	public function setDataSource($dataSource)
	{
		$this->_dataSource = $dataSource;
		return $this;
	}


	/**
	 * Allows to set Y axis switch mode.
	 *
	 * Settings Options:
	 * - string AnalyticsChart::AXIS_SWITCH_
	 * - number, maximal scale of min-max value, will use AXIS_SWITCH_DYNAMIC mode
	 *
	 * Possible Modes:
	 *	- AXIS_SWITCH_NONE  - use y2 axis always
	 *  - AXIS_SWITCH_ALWAYS - in case metric types are the same use same y axis
	 *  - AXIS_SWITCH_DYNAMIC - in case metric types are the same and scale up
	 * condition is not met use y2 axis.
	 *
	 * DYNAMIC
	 * Separate axis are used if minimal of datas max values scaled up by
	 * _axisSwitchScale is smaller than  maximum value from max values.
	 *  if min(max(dataSetA),max(dataSetB))*_axisSwitchScale < max(max(dataSetA),max(dataSetB))
	 *		use y2
	 *
	 * @param mixed $settings
	 */
	public function setAxisSwitch($settings)
	{
		if (is_numeric($settings)) {
			$this->_axisSwitchMode = self::AXIS_SWITCH_DYNAMIC;
			$this->_axisSwitchScale = $settings;
		} else {
			if (!in_array($settings, self::axisSwitchArray())) {
				throw new Extra_InvalidParam("settings");
			}
			$this->_axisSwitchMode = $settings;
		}
		return $this;
	}


	/**
	 * Returns a structure representing the analytic chart and its content. This
	 * structure can be passed to a template (view) and the actual frontend
	 * of the chart should be created there.
	 *
	 * @return array
	 */
	public function generateStructure()
	{
		$templateData = array();
		$templateData['identifier'] = $this->_identifier;
		$templateData['tableIdentifier'] = $this->_tableIdentifier;

		// set lod
		$this->_setDefaultProperty('lod', 'day');
		$templateData['lod'] = $activeLod = $this->_getProperty('lod');
		$lods = self::getLodMapping();

		$res = array();
		foreach ($lods as $key => $value) {
			// if the given LOD is blocked or not allowed to display, skip it
			if ( ! $this->isLodLevelEnabled($key)) {
				continue;
			}

			$class = array();
			if ($key === $activeLod) {
				$class[] = 'selected';
			}

			$res[$key] = array(
				'caption' => $value,
				'class' => implode(' ', $class),
			);
		}
		$templateData['lods'] = $res;

		// set metrics
		$templateData['metrics'] = $this->_metrics;
		if (!is_null($key = $this->_getProperty('selectedPrimaryMetricKey'))) {
			$templateData['selectedPrimaryMetric'] = $this->_metrics[$key];
			$templateData['selectedPrimaryMetricKey'] = $key;
		} else if (!empty($this->_metrics)) {
			$templateData['selectedPrimaryMetric'] = current($this->_metrics);
			$templateData['selectedPrimaryMetricKey'] = key($this->_metrics);
		}
		$templateData['selectedSecondaryMetricKey'] =
			$this->_getProperty('selectedSecondaryMetricKey');

		// set shadow metrics
		$shadowMetrics = array();
		foreach ($this->_metrics as $metric) {
			if (!empty($metric['shadowMetrics'])) {
				foreach ($metric['shadowMetrics'] as $key => $shadowMetric) {
					$shadowMetrics[] = $key;
				}
			}
		}
		$templateData['shadowMetrics'] = array_unique($shadowMetrics);

		$templateData['lineTypes'] = $this->_lineTypes;

		$templateData['pointSize'] = $this->_pointSize;

		$templateData['showGridLines'] = $this->_showGridLines;

		$templateData['colorPattern'] = $this->_colorPattern;

		$templateData['regions'] = $this->_regions;

		$templateData['zoomEnabled'] = $this->_zoomEnabled;

		return $templateData;
	}


	/**
	 * Action handler for processing ajax actions
	 *
	 * @param  string $action
	 * @return string
	 */
	public function process($action, $params = array())
	{
		return $this->{"_".$action}($params);
	}


	/**
	 * Returns whether Lod picker should be displayed
	 *
	 * @return bool
	 */
	public function shouldShowLod()
	{
		return $this->_useLod;
	}


	/**
	 * Sets whether Lod picker should be displayed
	 *
	 * @param bool $value
	 * @return $this
	 */
	public function setShowLod($value = true)
	{
		$this->_useLod = $value;
		return $this;
	}


	/**
	 * Sets whether metric picker picker should be displayed
	 *
	 * @param bool $value
	 * @return $this
	 */
	public function setShowMetricPicker($value = true)
	{
		$this->_showMetricPicker = $value;
		return $this;
	}


	/**
	 * Sets whether gridlines for Y axe should be displayed
	 *
	 * @param bool $value
	 * @return $this
	 */
	public function setShowGridLines($value = true)
	{
		$this->_showGridLines = $value;
		return $this;
	}


	/**
	 * Set point size
	 *
	 * @param float $value
	 * @return $this
	 */
	public function setPointSize($value = 2.5)
	{
		$this->_pointSize = $value;
		return $this;
	}


	/**
	 * Set color pattern
	 *
	 * @param array $values
	 * @return $this
	 */
	public function setColorPattern($values)
	{
		$this->_colorPattern = $values;
		return $this;
	}


	/**
	 * Set regions
	 *
	 * @param array $values
	 * @return $this
	 */
	public function setRegions($values)
	{
		$this->_regions = $values;
		return $this;
	}


	/**
	 * Enable/disable zoom
	 *
	 * @param bool $value
	 * @return $this
	 */
	public function enableZoom($value = false)
	{
		$this->_zoomEnabled = $value;
		return $this;
	}


	/**
	 * Whether we want to display metric picker.
	 * Returns false if there is only one or metric picker is disabled
	 *
	 * @return bool
	 */
	public function shouldShowMetricPicker()
	{
		return (count($this->_metrics) > 1) && $this->_showMetricPicker;
	}


	/**
	 * Returns true if the given given LOD level is enable
	 *
	 * @param  string $level
	 * @return bool
	 */
	public function isLodLevelEnabled($level)
	{
		return isset($this->_lodLevels[$level])
			? $this->_lodLevels[$level]
			: false;
	}


	/**
	 * Sets LOD levels
	 * @param  [type] $hour  [description]
	 * @param  [type] $day   [description]
	 * @param  [type] $week  [description]
	 * @param  [type] $month [description]
	 * @return [type]        [description]
	 */
	public function enableLodLevel($hour, $day, $week, $month)
	{
		// start from beginning
		$seed = array();

		// add some data
		$seed['hour'] = $hour;
		$seed['day'] = $day;
		$seed['week'] = $week;
		$seed['month'] = $month;

		// profit
		$this->_lodLevels = $seed;
	}


	/**
	 * Sets $type of the line for given metric
	 *
	 * @param  $metric string
	 * @param  $type   string e.g. area, line, etc., refer to C3 doc for more
	 * @return this
	 */
	public function setLineType($metric, $type)
	{
		$this->_lineTypes[$metric] = $type;
		return $this;
	}


	/**
	 * Ajax function returns complete data for chart
	 *
	 * @return void
	 */
	private function _loadData($params = array())
	{
		$def = array(
			'primary' => self::PRIMARY_METRIC_KEY,
			'secondary' => self::SECONDARY_METRIC_KEY,
			'lod' => 'lod',
		);
		foreach ($def as $varName => $requestKey) {
			if (!isset($params[$requestKey])) {
				throw new \Exception("Missing _loadData() init param $requestKey");
			}
			$$varName = $params[$requestKey];
		}

		// save selected values
		$this->_setProperty('lod', $lod);
		$this->_setProperty('selectedPrimaryMetricKey', $primary);
		if (!is_null($secondary)) {
			$this->_setProperty('selectedSecondaryMetricKey', $secondary);
		}

		// set metrics
		$metrics = array();
		$metrics[] = $primary;
		if (!empty($secondary) && $secondary != $primary) {
			$metrics[] = $secondary;
		}
		// do not display other shadow metrics if secondary metric is active
		if (empty($secondary) && !empty($this->_metrics[$primary]['shadowMetrics'])) {
			foreach ($this->_metrics[$primary]['shadowMetrics'] as $key => $metric) {
				$metrics[] = $key;
			}
		}

		// set secondary metric axes
		$axes = array();
		$primaryMetric = $this->_getMetric($primary);

		// get chart data from callback
		$data = $this->_getSourceData($metrics, $lod);

		// prepare chart data
		$names = array();
		$columns = array();
		$columns[] = array_merge(array(self::X_AXIS_KEY), $data['labels']);

		// get min and max values from primary metric
		$primaryExtremes = array();
		if ($this->_axisSwitchMode === self::AXIS_SWITCH_DYNAMIC) {
			$primaryColumn = $data['columns'][$primary];
			$primaryExtremes = array(min($primaryColumn), max($primaryColumn));
		}

		foreach ($data['columns'] as $key => $column) {
			// get current metric options
			$metric = $this->_getMetric($key);

			// set metric chart specific key
			if ($key == $primary) {
				$chartKey = self::PRIMARY_METRIC_KEY;
			} else if ($key == $secondary) {
				$chartKey = self::SECONDARY_METRIC_KEY;
			} else {
				$chartKey = $key;
			}

			// set data axis for all except primary metric
			if ($key !== $primary) {
				$axes[$chartKey] = $this->_getYAxis($metric, $primaryMetric, $column, $primaryExtremes);
			}

			// set data captions
			$names[$chartKey] = $metric['caption'];

			// set data
			$columns[] = array_merge(array($chartKey), $column);
		}

		return array(
			'status' => 'ok',
			'data' => array(
				'validation' => true,
				'columns' => $columns,
				'names' => $names,
				'axes' => $axes,
		));
	}


	/**
	 * Returns y axis to use for given non-primary metric.
	 *
	 * @param array $metric Metric data of tested column
	 * @param array $primaryMetric Primary metric
	 * @param array $column Dataset
	 * @param array $primaryExtremes Min and max values of primary dataset
	 * @return string
	 */
	private function _getYAxis($metric, $primaryMetric, $column, $primaryExtremes)
	{
		if ($this->_axisSwitchMode === self::AXIS_SWITCH_ALWAYS) {
			return ($primaryMetric['type'] == $metric['type'])
				? self::Y_AXIS_KEY : self::Y2_AXIS_KEY;
		}
		if ($this->_axisSwitchMode === self::AXIS_SWITCH_DYNAMIC) {
			if ($primaryMetric['type'] !== $metric['type']) {
				return self::Y2_AXIS_KEY;
			}
			// get data
			$localMin = min($column);
			$localMax = max($column);
			$minMin = min($localMin, $primaryExtremes[0]);

			// normalize to positive values
			$localMax = $localMax - $minMin;
			$primaryMax = $primaryExtremes[1] - $minMin;
			// compare scaled min-max value
			if (min($localMax,$primaryMax) * $this->_axisSwitchScale < max($localMax, $primaryMax)) {
				// use separate axis if scaled-up still lower than second maximum
				return self::Y2_AXIS_KEY;
			} else {
				return self::Y_AXIS_KEY;
			}
		}
		return self::Y2_AXIS_KEY;
	}


	/**
	 * Return metric settings from the structured array
	 *
	 * @param  string $key
	 * @return string
	 */
	private function _getMetric($key)
	{
		if (isset($this->_metrics[$key])) {
			return $this->_metrics[$key];
		}

		foreach ($this->_metrics as $metric) {
			if (isset($metric['shadowMetrics'])
				&& isset($metric['shadowMetrics'][$key])
			) {
				return $metric['shadowMetrics'][$key];
			}
		}
	}


	/**
	 * Ajax function returns one data column from
	 * the given Analytic table row data.
	 *
	 * @return void
	 */
	private function _loadTableRowData($params = array())
	{
		$def = array(
			'rowKey' => 'key',
			'primaryMetric' => self::PRIMARY_METRIC_KEY,
			'primaryDimension' => 'primary_dimension',
			'primaryDimensionID' => 'primary_dimension_id',
			'secondaryDimension' =>'secondary_dimension',
			'secondaryDimensionID' => 'secondary_dimension_id',
			'lod' => 'lod',
		);
		// secondary dimension is not mandatory
		foreach (array('secondary_dimension', 'secondary_dimension_id') as $i) {
			$params[$i] = empty($params[$i])
				? false
				: $params[$i];
		}
		foreach ($def as $varName => $requestKey) {
			if (!isset($params[$requestKey])) {
				throw new \Exception(
					"Missing _loadTableRowData() init param $requestKey");
			}
			$$varName = $params[$requestKey];
		}

		// set primary metric
		$metrics = array($primaryMetric);

		// set dimensions
		$dimensions = array(
			$primaryDimension => $primaryDimensionID
		);
		if (!empty($secondaryDimension)) {
			$dimensions[$secondaryDimension] = $secondaryDimensionID;
		}

		// get chart data from callback
		$data = $this->_getSourceData($metrics, $lod, $dimensions);

		$columns = array();
		foreach ($data['columns'] as $key => $column) {
			$columns[] = array_merge(array($rowKey), $column);
		}

		return array(
			'status' => 'ok',
			'data' => array(
				'validation' => true,
				'columns' => $columns
		));
	}


	/**
	 * Pass given parameters into the callback method and return the data
	 *
	 * @param  array 	$metrics
	 * @param  string 	$lod
	 * @param  array 	$dimensions
	 * @return array
	 */
	private function _getSourceData($metrics, $lod,
		$dimensions = array())
	{
		// set start date
		if (empty($this->_startDate)) {
			$startDate = new \DateTime('today');
			$startDate->modify('-30 days');
		} else {
			$startDate = $this->_startDate;
		}

		// set end date
		if (empty($this->_endDate)) {
			$endDate = new \DateTime('today');
		} else {
			$endDate = $this->_endDate;
		}

		$start = Extra_AnalyticUtils::
			truncateDate($startDate->format('Y-m-d'), $lod);
		$end = Extra_AnalyticUtils::
			truncateDate($endDate->format('Y-m-d'), $lod);

		$params = array(
			'startDate' => $start,
			'endDate' => $end,
			'metrics' => $metrics,
			'lod' => $lod,
			'dimensions' => $dimensions
		);

		return call_user_func_array($this->_dataSource, array($params));
	}
}
