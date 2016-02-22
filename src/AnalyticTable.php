<?php

namespace Argo22\AnalyticChart;

/**
 * Analytic Table
 * Using datatables library for displaying the data (http://datatables.net).
 */
class Table extends ComponentAbstract
{
	/**
	 * Table column keys
	 *
	 * Important!!! Must NOT be overwritten with any other column key.
	 */
	const PRIMARY_DIMENSION_KEY = 'primary_dimension';
	const SECONDARY_DIMENSION_KEY = 'secondary_dimension';
	const CHECKBOX_COLUMN_KEY = 'checkbox';

	/**
	 * Analytic chart identifier
	 *
	 * @var string
	 */
	private $_chartIdentifier = null;

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
	 * Array of primary dimensions
	 *
	 * @var array
	 */
	private $_primaryDimensions = null;

	/**
	 * Array of secondary dimensions
	 *
	 * @var array
	 */
	private $_secondaryDimensions = null;

	/**
	 * Array of table columns (metrics)
	 *
	 * @var array
	 */
	private $_columns = array();

	/**
	 * Callback function from where the data are retrieved
	 *
	 * @var mixed
	 */
	private $_dataSource = null;


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
	 */
	public function __construct($identifier, $assetsLoad, $sessionLoad = array())
	{
		// define asset files to include to page head
		$assets = array(
			'css' => array (
				'vc_analytic_table.css',
			),
			'js' => array (
				'metric.formats.js',
				'jquery.dataTables.min.js',
				'analytic_table.js',
			),
		);
		parent::__construct($identifier, $assetsLoad, $assets, $sessionLoad);
	}


	/**
	 * Setter for analytic chart identifier. Enable interaction
	 * with instance of \Argo22\AnalyticChart\Chart
	 *
	 * @param  string	 				$identifier
	 * @return self
	 */
	public function setChartIdentifier($identifier)
	{
		$this->_chartIdentifier = $identifier;
		return $this;
	}


	/**
	 * Setter for start date
	 *
	 * @param  \DateTime 				$start
	 * @return self
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
	 * @return self
	 */
	public function setEndDate(\DateTime $end)
	{
		$this->_endDate = $end;
		return $this;
	}


	/**
	 * Setter for table primary dimensions
	 *
	 * array(
	 *   'dimensionKey' => array(
	 *     'caption' => 'Dimension 1'
	 *   ),
	 *   'dimensionKey2' => array(
	 *     'caption' => 'Dimension 2',
	 *   ...
	 * )
	 *
	 * @param  array 					$dimensions
	 * @return self
	 */
	public function setPrimaryDimensions($dimensions)
	{
		$this->_primaryDimensions = $dimensions;
		return $this;
	}


	/**
	 * Setter for table secondary dimensions
	 *
	 * array(
	 *   'dimensionKey' => array(
	 *     'caption' => 'Dimension 1'
	 *   ),
	 *   'dimensionKey2' => array(
	 *     'caption' => 'Dimension 2',
	 *   ...
	 * )
	 *
	 * @param  array 					$dimensions
	 * @return self
	 */
	public function setSecondaryDimensions($dimensions)
	{
		$this->_secondaryDimensions = $dimensions;
		return $this;
	}


	/**
	 * Setter for table columns
	 *
	 * array(
	 *   'column1' => array(
	 *     'caption' => 'Column 1',
	 *     'align' => 'right',
	 *     'alignHeader' => 'right'
	 *   ),
	 *   'column2' => array(
	 *     'caption' => 'Column 2',
	 *   ...
	 * )
	 *
	 * @param  array 					$columns
	 * @return self
	 */
	public function setColumns($columns)
	{
		$this->_columns = $columns;
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
	 * @return self
	 */
	public function setDataSource($dataSource)
	{
		$this->_dataSource = $dataSource;
		return $this;
	}


	/**
	 * Returns a structure representing the analytic table and its content. This
	 * structure can be passed to a template (view) and the actual frontend
	 * of the chart should be created there.
	 *
	 * @return array
	 */
	public function generateStructure()
	{
		$templateData = array();
		$templateData['identifier'] = $this->_identifier;
		$templateData['chartIdentifier'] = $this->_chartIdentifier;

		// set dimensions
		$templateData['primaryDimensions'] = $this->_primaryDimensions;
		if (!is_null($key = $this->_getProperty('selectedPrimaryDimensionKey'))) {
			$templateData['selectedPrimaryDimension'] = $this->_primaryDimensions[$key];
			$templateData['selectedPrimaryDimensionKey'] = $key;
		} else if (!empty($this->_primaryDimensions)) {
			$templateData['selectedPrimaryDimension'] = current($this->_primaryDimensions);
			$templateData['selectedPrimaryDimensionKey'] = key($this->_primaryDimensions);
		}

		$templateData['secondaryDimensions'] = $this->_secondaryDimensions;
		$secondaryKey =	$this->_getProperty('selectedSecondaryDimensionKey');
		$templateData['selectedSecondaryDimensionKey'] = $secondaryKey;
		if (!empty($secondaryKey)) {
			$templateData['selectedSecondaryDimension'] =
				$this->_secondaryDimensions[$secondaryKey];
		}

		// set columns
		$templateData['columns'] = $this->_columns;


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
	 * Ajax fucntion returns complete data for the table
	 *
	 * @return void
	 */
	private function _loadData($params = array())
	{
		$def = array(
			'primary' => self::PRIMARY_DIMENSION_KEY,
			'secondary' => self::SECONDARY_DIMENSION_KEY,
			'lod' => 'lod',
		);
		foreach ($def as $varName => $requestKey) {
			if (!isset($params[$requestKey])) {
				throw new \Exception("Missing _loadData() init param $requestKey");
			}
			$$varName = $params[$requestKey];
		}

		// save selected values
		$this->_setProperty('selectedPrimaryDimensionKey', $primary);
		if (!is_null($secondary)) {
			$this->_setProperty('selectedSecondaryDimensionKey', $secondary);
		}

		// set primary dimension
		$dimensions = array();
		$dimensions[] = $primary;
		$primaryDimensionCaption =
			$this->_primaryDimensions[$primary]['caption'];

		// set secondary dimension
		$secondaryDimensionActive = false;
		$secondaryDimensionCaption = '';
		if (!empty($secondary) && $secondary != $primary) {
			$secondaryDimensionActive = true;
			$dimensions[] = $secondary;
			$secondaryDimensionCaption =
				$this->_secondaryDimensions[$secondary]['caption'];
		}

		// get chart data from callback
		$data = $this->_getSourceData($lod, $dimensions);

		$primaryIds = array();
		$secondaryIds = array();
		foreach ($data as $k => $row) {
			// handle primary dimension
			$primaryIds[] = $row[$primary]['id'];
			$data[$k][self::PRIMARY_DIMENSION_KEY] = $row[$primary]['value'];
			unset($data[$k][$primary]);

			// handle secondary dimension
			if (isset($row[$secondary])) {
				$secondaryIds[] = $row[$secondary]['id'];
				$data[$k][self::SECONDARY_DIMENSION_KEY] = $row[$secondary]['value'];
				unset($data[$k][$secondary]);
			} else {
				$data[$k][self::SECONDARY_DIMENSION_KEY] = null;
			}

			// set checkbox column
			$data[$k][self::CHECKBOX_COLUMN_KEY] = null;
		}

		// compute average
		$total = $this->_getSourceData($lod);
		$total = array_pop($total);
		$count = count($data);
		$summary = array();
		foreach ($this->_columns as $key => $settings) {
			$summary[$key]['total'] = $total[$key];
			if ($settings['displayAverage'] === true) {
				$summary[$key]['average'] =
					$count > 0
					? $total[$key] / $count
					: 0;
			}
		}

		return array(
			'status' => 'ok',
			'data' => array(
				'validation' => true,
				'summary' => $summary,
				'rows' => $data,
				'primary_dimension_ids' => $primaryIds,
				'primary_dimension_caption' => $primaryDimensionCaption,
				'secondary_dimension_active' => $secondaryDimensionActive,
				'secondary_dimension_ids' => $secondaryIds,
				'secondary_dimension_caption' => $secondaryDimensionCaption
		));
	}


	/**
	 * Pass given parameters into the callback method and return the data
	 *
	 * @param  array 	$dimensions
	 * @return array
	 */
	private function _getSourceData($lod = false, $dimensions = array())
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

		if ($lod) {
			$startDate = Utils::truncateDate($startDate->format('Y-m-d'), $lod);
			$endDate = Utils::truncateDate($endDate->format('Y-m-d'), $lod);
		} else {
			$startDate = $startDate->format('Y-m-d');
			$endDate = $endDate->format('Y-m-d');
		}

		$params = array(
			'startDate' => $startDate,
			'endDate' => $endDate,
			'dimensions' => $dimensions,
			'metrics' => array_keys($this->_columns)
		);

		if ($lod) {
			$params['lod'] = $lod;
		}

		return call_user_func_array($this->_dataSource, array($params));
	}
}
