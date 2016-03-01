<?php

namespace App\Presenters;

use \Argo22\AnalyticChart\Metric;
use \Argo22\AnalyticChart\DateRangePicker;
use \Argo22\AnalyticChart\Chart;
use \Argo22\AnalyticChart\Table;

class StatisticsPresenter extends \Argo22\Core\Presenters\BasePresenter
{
	/** @var \App\Services\StatisticsUser @inject */
	var $userStatistics;
	/** @var \App\Services\StatisticsMagazineIssue @inject */
	var $miStatistics;
	/** @var \App\Services\StatisticsSubscription @inject */
	var $subsStatistics;

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
		'magissue' => array(
			\App\Services\StatisticsMagazineIssue::ISSUES_READ,
			\App\Services\StatisticsMagazineIssue::ISSUES_DOWNLOADED,
		),
		'subs' => array(
			\App\Services\StatisticsSubscription::SUBSCRIPTIONS_NEW,
		),
	);

	/**
	 * Custom (=other than absolute) types for metrics
	 * TABLE - metric type to append '%' or so in the table
	 *
	 * @var array
	 */
	private $_metricType = array(
		'count' => Metric::RELATIVE,
	);

	/**
	 * List of associated array metrics
	 * CHART - introduce fixed chart secondary metric
	 *
	 * @var array
	 */
	private $_shadow = array(
		\App\Services\StatisticsUser::USERS_ACTIVE =>
			\App\Services\StatisticsUser::USERS_TOTAL,
	);

	/**
	 * Dimensions available for each action
	 * TABLE - array of available dimensions for chart view
	 *
	 * @var array
	 */
	private $_dimensions = array(
		'users' => array(
			\App\Services\StatisticsUser::DIMENSION_USER_REFERRAL,
			\App\Services\StatisticsUser::DIMENSION_USER_SOURCE,
			\App\Services\StatisticsUser::DIMENSION_USER_COUNTRY,
		),
		'magissue' => array(
			\App\Services\StatisticsMagazineIssue::DIMENSION_MI_DEVICE,
			\App\Services\StatisticsMagazineIssue::DIMENSION_MI_COUNTRY,
			\App\Services\StatisticsMagazineIssue::DIMENSION_MI_SUBSCRIPTION,
			\App\Services\StatisticsMagazineIssue::DIMENSION_MI_MAGAZINE,
			\App\Services\StatisticsMagazineIssue::DIMENSION_MI_MAGAZINE_ISSUE,
		),
		'subs' => array(
			\App\Services\StatisticsSubscription::DIMENSION_SUBS_DEVICE,
			\App\Services\StatisticsSubscription::DIMENSION_SUBS_COUNTRY,
			\App\Services\StatisticsSubscription::DIMENSION_SUBS_SUBSCRIPTION,
		),
	);

	/**
	 * Custom displaying of average value for metrics (default TRUE)
	 * TABLE - display row values average on table TH
	 *
	 * @var array
	 */
	private $_metricDisplayAverage = array(
		//\App\Services\StatisticsUser::USERS_ACTIVE => false,
	);

	/**
	 * Actions definitions used for menu
	 * @var array
	 */
	private $_actions = array(
		'users' => 'Users',
		'magissue' => 'Magazine issues',
		'subs' => 'Subscriptions',
	);

	public function actionUsers()
	{
		$this->actionDefault('users');
	}
	public function actionMagissue()
	{
		$this->actionDefault('magissue');
	}
	public function actionSubs()
	{
		$this->actionDefault('subs');
	}

	/**
	 * Default dashboard action
	 *
	 * @param  string	$id
	 * @return void
	 */
	public function actionDefault($id = false)
	{
		// force the same template for all statistics sections
		$this->template->setFile(WWW_DIR
			.'/../app/presenters/templates/Statistics/default.latte'
		);
		// init chart
		$firstAction = $id === false
			? current(array_keys($this->_actions)) . 'Action'
			: "{$id}Action";
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

			$isUser = substr($id, 0, strlen('users')) === 'users';
			$isIssue = substr($id, 0, strlen('magissue')) === 'magissue';
			$isSubs = substr($id, 0, strlen('subs')) === 'subs';

			if ( ! ($isUser || $isIssue || $isSubs)) {
				throw new \Exception('guardina');
				break;
			}

			$target = "_build{$type}";
			$token = '';
			switch (true) {
			case $isUser:
				$token = 'user';
				break;
			case $isIssue:
				$token = 'magazineIssue';
				break;
			case $isSubs:
				$token = 'subscription';
				break;
			}

			return $this->$target($token, $id);

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

		$primaryDimensions = $secondaryDimensions = array();
		foreach ($this->_dimensions[$id] as $dimension) {
			$primaryDimensions[$dimension] = array(
				'caption' =>
					$sourceClass::getLabelForDimension($dimension),
			);
			$secondaryDimensions[$dimension] = array(
				'caption' =>
					$sourceClass::getLabelForDimension($dimension),
			);
		}
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
		return self::_loadData($params, 'User', 'getTimeline');
	}


	/**
	 * Callback target for user table
	 *
	 * @param  array $params
	 * @return array
	 */
	static public function userTableSource($params)
	{
		return self::_loadData($params, 'User', 'getTabular');
	}


	/**
	 * Callback target for magazine issue graph
	 *
	 * @param  array $params
	 * @return array
	 */
	static public function magazineIssueGraphSource($params)
	{
		return self::_loadData($params, 'MagazineIssue', 'getTimeline');
	}


	/**
	 * Callback target for magazine issue table
	 *
	 * @param  array $params
	 * @return array
	 */
	static public function magazineIssueTableSource($params)
	{
		return self::_loadData($params, 'MagazineIssue', 'getTabular');
	}


	/**
	 * Callback target for subscriptions graph
	 *
	 * @param  array $params
	 * @return array
	 */
	static public function subscriptionGraphSource($params)
	{
		return self::_loadData($params, 'Subscription', 'getTimeline');
	}


	/**
	 * Callback target for subscriptions table
	 *
	 * @param  array $params
	 * @return array
	 */
	static public function subscriptionTableSource($params)
	{
		return self::_loadData($params, 'Subscription', 'getTabular');
	}


	/**
	 * Automate data loading
	 *
	 * @param  array $params
	 * @return array
	 */
	static private function _loadData($params, $clSubName, $methName)
	{
		$className = "\App\Services\Statistics$clSubName";
		$stats = $className::getInstance(lcfirst($clSubName));
		$lodMapping = Chart::getLodMapping();

		return $stats->$methName(
			$params['metrics'],
			$params['startDate'],
			$params['endDate'],
			array_search($lodMapping[$params['lod']], $lodMapping),
			$params['dimensions']
		);
	}
}
