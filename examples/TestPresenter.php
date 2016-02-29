<?php

namespace App\Presenters;

use \Argo22\AnalyticChart\Metric;
use \Argo22\AnalyticChart\DateRangePicker;
use \Argo22\AnalyticChart\Chart;
use \Argo22\AnalyticChart\Table;

class TestPresenter extends \Argo22\Core\Presenters\BasePresenter
{
	/** @var \App\Services\StatisticsUser @inject */
	var $userStatistics;

	/**
	 * Metrics available for each action
	 * CHART - available metrics for selection
	 *
	 * @var array
	 */
	private $_metrics = array(
		'users' => array(
			\App\Services\StatisticsUser::USERS_ACTIVE,
			\App\Services\StatisticsUser::USERS_TOTAL,
		),
	);

	/**
	 * Custom (=other than absolute) types for metrics
	 * TODO
	 *
	 * @var array
	 */
	private $_metricType = array(
		'count' => Metric::RELATIVE,
	);


	/**
	 * List of associated array metrics
	 * CHART - introduce fixed chart secondary metric
	 * TODO: example - leave it there?
	 *
	 * @var array
	 */
	private $_shadow = array(
		\App\Services\StatisticsUser::USERS_ACTIVE =>
			\App\Services\StatisticsUser::USERS_TOTAL,
	);

	/**
	 * Dimensions available for each action
	 * TABLE - changes table rows contents
	 *
	 * @var array
	 */
	private $_dimensions = array(
		'users' => array(
			\App\Services\StatisticsUser::DIMENSION_USER_REFERRAL,
			\App\Services\StatisticsUser::DIMENSION_USER_SOURCE,
			\App\Services\StatisticsUser::DIMENSION_USER_COUNTRY,
		),
	);

	/**
	 * Custom dispalying of average value for metrics (default TRUE)
	 * TABLE - display row values average on table TH
	 * @var array
	 */
	private $_metricDisplayAverage = array(
#		\App\Services\StatisticsUser::USERS_ACTIVE => false,
	);

	/**
	 * Actions definitions used for menu
	 * @var array
	 */
	private $_actions = array(
		'users' => 'Users',
	);


	/**
	 * Debug method for logging issued DB queries. Useful for debugging API
	 */
	public function logQuery(\Nette\Database\Connection $connection, $result) {
		$soubor = fopen("../log/queryLog", "a");
		fwrite($soubor, date('Y-m-d H:i:s') . ": " .$result->getQueryString() . "\n");
		fclose($soubor);
	}
	/**
	 * Default dashboard action
	 *
	 * @return void
	 */
	public function actionDefault()
	{
		// Uncomment these lines to start logging DB queries
	#	$this->getContext()->getByType('\Nette\Database\Connection')
	#		->onQuery[] = array($this, 'logQuery');

		// init chart
		$firstAction = current(array_keys($this->_actions)) . 'Action';
		$vcInstance = $this->$firstAction();

		// grab fetch chart data in case of AJAX request
		$format = $this->getParameter('format');
		if (!empty($format) && $format === 'json') {

			// init item based on its identifier
			$id = $this->getParameter('vcIdentifier');
			$itemName = substr($id, -5);
			if (!in_array($itemName, array('Chart', 'Table'))) {
				throw new \Exception('Unknown item type!');
			}
			$className = '\Argo22\AnalyticChart\\' . $itemName;

			// retrieve the instance as it has already been created
			$item = $className::getInstanceById($id);

			// process AJAX request in case the instance has been found
			if ($item !== null) {
				$this->sendResponse(new \Nette\Application\Responses\JsonResponse(
					$item->process(
						$this->getParameter('vcMethod'),
						$this->getParameters()
					)
				));
			}
			// handle error
			$this->sendResponse(new \Nette\Application\Responses\JsonResponse(
				array(
					'status' => 'error',
					'data' => "Failed to initialize component $id",
				)
			));
		}
	}


	/**
	 * Magic method: traps calls for actions and init_components
	 *
	 * @param  string $name
	 * @param  array  $arguments
	 * @return mixed
	 */
	public function __call($methodName, $args)
	{
		// check if the method name ends with Action
		$needle = 'Action';
		$endsWith = substr($methodName, -strlen($needle)) === $needle;

		$needle = 'initComponent_';
		$startsWith = substr($methodName, 0, strlen($needle)) === $needle;

		while ($endsWith || $startsWith) {
			// trap for actions
			if ($endsWith) {
				$action = substr($methodName, 0, -strlen('Action'));
				if ( ! isset($this->_actions[$action])) {
					throw new \Exception('guardina');
					break;
				}

				$this->_showStats($action);
				return;
			}

			if ( ! $startsWith) {
				break;
			}

			// trap for init components
			// we handle only chart/table
			$type = strtolower(substr($methodName, -5));

			if ( ! in_array($type, array('chart', 'table'))) {
				throw new \Exception('guardina');
				break;
			}

			$id = $methodName;
			// remove trailing type
			$id = substr($id, 0, -5);
			// remove prefix
			$id = substr($id, strlen('initComponent_'));

			$isUser = substr($id, 0, strlen('user')) === 'user';
#			$isContract =
#				substr($id, 0, strlen('contract')) === 'contract';

			if ( ! ($isUser)) { // || $isContract)) {
				throw new \Exception('guardina');
				break;
			}

			$target = "_build{$type}";

			return $this->$target($isUser ? 'user' : 'user'/*TODO*/, $id);

			// just to be consistent
			break;
		}

		// let parent handle that
		return parent::__call($methodName, $args);
	}


	/**
	 * trap for *Action, build appropriate components
	 *
	 * @param  string $for id of the template
	 * @return void
	 */
	private function _showStats($for)
	{
		$dateRangePicker = $this->_getReportDateRangePicker();
		$this->template->dateRangePicker = $dateRangePicker;

		$initMethod = "initComponent_{$for}Chart";
		$this->template->analyticChart = $this->$initMethod();
		$initMethod = "initComponent_{$for}Table";
		$this->template->analyticTable = $this->$initMethod();

		$this->template->menu = $this->_actions;
		$this->template->activeMenuItem = $for;
	}


	/**
	 * Builds daterangepicker component
	 *
	 * @return Eos_VC_DateRangePicker
	 */
	private function _getReportDateRangePicker()
	{
		static $cache;
		if ( ! is_null($cache)) {
			return $cache;
		}

		$dateRangePicker = new DateRangePicker(
			'UserDateRangePicker',
			$this->_assetsLoader(),
			$this->_sessionLoader('UserDateRangePicker')
		);

		$dateRangePicker
			->addPredefinedRange(DateRangePicker::RANGE_LAST_SEVEN_DAYS)
			->addPredefinedRange(DateRangePicker::RANGE_LAST_THIRTY_DAYS)
			->setOutputDateFormat('Y-m-d')
			->setMaxDate('today')
			->setDefaultRange('today -1 Month', 'today');

		return $cache = $dateRangePicker;
	}


	/**
	 * Builds chart according to the definition
	 * @param  [type] $type   [description]
	 * @param  [type] $source [description]
	 * @return [type]		  [description]
	 */
	private function _buildChart($source, $id)
	{
		$componentName = "{$id}Chart";

		$sourceClass = '\App\Services\Statistics' . ucfirst($source);

		$chart = new Chart(
			$componentName,
			$this->_assetsLoader(),
			$this->_sessionLoader($componentName)
		);

		// adjust based on data granularity
		$chart->enableLodLevel(0,1,1,1);

		// we will use same axis for both metrics, if the secondary metric's
		// values are at max 6 times greater than primary ones
		$chart->setAxisSwitch(6.0);

		// set relation with analytic table component
		$chart->setTableIdentifier("{$id}Table");

		$metrics = array();

		foreach ($this->_metrics[$id] as $metric) {
			$type = isset($this->_metricType[$metric])
				? $this->_metricType[$metric]
				: Metric::ABSOLUTE;

			$metrics[$metric] = array(
				'caption' => $sourceClass::getLabelFor($metric),
				'type' => $type,
			);

			// add metric if present
			if (isset($this->_shadow[$metric])) {
				$metrics[$metric]['shadowMetrics'] = array(
					$this->_shadow[$metric] => array(
						'caption' =>
							$sourceClass::getLabelFor($this->_shadow[$metric]),
						'type' => Metric::RELATIVE,
					)
				);
			}
		}

		$dateRangePicker = $this->_getReportDateRangePicker();

		$chart->setMetrics($metrics);

		$chart->setDataSource(array($this, "{$source}GraphSource"));
		$chart->setStartDate(
			new \DateTime($dateRangePicker->getCurrentDateStart()));
		$chart->setEndDate(
			new \DateTime($dateRangePicker->getCurrentDateEnd()));

		return $chart;
	}


	/**
	 * Builds chart according to the definition
	 * @param  [type] $type   [description]
	 * @param  [type] $source [description]
	 * @return [type]		  [description]
	 */
	private function _buildTable($source, $id)
	{
		$componentName = "{$id}Table";

		$sourceClass = '\App\Services\Statistics' . ucfirst($source);

		$table = new Table(
			$componentName,
			$this->_assetsLoader(),
			$this->_sessionLoader($componentName)
		);

		// set relation with analytic chart component
		$table->setChartIdentifier("{$id}Chart");

		$primaryDimensions = array();

		foreach ($this->_dimensions[$id] as $dimension) {
			$primaryDimensions[$dimension] = array(
				'caption' =>
					$sourceClass::getLabelForDimension($dimension),
			);
		}

		$secondaryDimensions = array();

		foreach ($this->_dimensions as $dimensionsForAction) {
			foreach ($dimensionsForAction as $dimension) {
				$secondaryDimensions[$dimension] = array(
					'caption' =>
						$sourceClass::getLabelForDimension($dimension),
				);
			}
		}

#		// unset dimensions not available for this source type
#		if ($source === 'lead') {
#			unset($secondaryDimensions[Eos_Statistics_Contract::DIMENSION_DISTRIBUTION]);
#		} else {
#			unset($secondaryDimensions[Eos_Statistics_Lead::DIMENSION_SAVINGS_BIN]);
#		}

		$table->setPrimaryDimensions($primaryDimensions);
		$table->setSecondaryDimensions($secondaryDimensions);

		$columns = array();

		foreach ($this->_metrics[$id] as $metric) {
			$type = isset($this->_metricType[$metric])
				? $this->_metricType[$metric]
				: Metric::ABSOLUTE;
			$displayAverage = isset($this->_metricDisplayAverage[$metric])
				? $this->_metricDisplayAverage[$metric]
				: true;

			$columns[$metric] = array(
				'caption' => $sourceClass::getLabelFor($metric),
				'description' => $sourceClass::getDescFor($metric),
				'type' => $type,
				'align' => 'right',
				'displayAverage' => $displayAverage,
			);
		}

		$table->setColumns($columns);

		$dateRangePicker = $this->_getReportDateRangePicker();

		$table->setDataSource(array($this, "{$source}TableSource"));
		$table->setStartDate(
			new \DateTime($dateRangePicker->getCurrentDateStart()));
		$table->setEndDate(
			new \DateTime($dateRangePicker->getCurrentDateEnd()));

		return $table;
	}


	/**
	 * Fetch instance of object capable of appending JS and CSS files
	 * to page head. Also supply names of indiviual methods to use.
	 *
	 * @return array
	 */
	private function _assetsLoader(){
		return array(
			'instance' => $this['header'],
			'methodCss' => 'addCss',
			'methodJs' => 'addJs',
		);
	}


	/**
	 * Fetch instance of object capable of getting and setting session params.
	 * Also supply names of indiviual methods to use.
	 *
	 * @return array
	 */
	private function _sessionLoader($name){
		return array(
			'instance' => $this->getSession($name),
			'methodSet' => 'offsetSet',
			'methodGet' => 'offsetGet',
		);
	}


	/**
	 * Callback target for user graph
	 *
	 * @param  array $params
	 * @return array
	 */
	static public function userGraphSource($params)
	{
		$lodMapping = Chart::getLodMapping();

		$stats = \App\Services\StatisticsUser::getInstance();
		return $stats->getTimeline(
			$params['metrics'],
			$params['startDate'],
			$params['endDate'],
			array_search($lodMapping[$params['lod']], $lodMapping),
			$params['dimensions']
		);
	}


	/**
	 * Callback target for user table
	 *
	 * @param  array $params
	 * @return array
	 */
	static public function userTableSource($params)
	{
		$lodMapping = Table::getLodMapping();

		$stats = \App\Services\StatisticsUser::getInstance();
		return $stats->getTabular(
			$params['metrics'],
			$params['startDate'],
			$params['endDate'],
			array_search($lodMapping[$params['lod']], $lodMapping),
			$params['dimensions']
		);
	}
}
