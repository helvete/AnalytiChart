<?php

namespace Argo22\AnalyticChart;

/**
 * Class for creating date range picker.
 *
 * Time Format:
 * - The date format input is date('Y-m-d'). Example: 2011-11-06
 * - If no output date format is specified, the default output format is
 *	 date('d M y')
 * - If no date entry date format is specified, the default format is
 *	 date('Y/m/d')
 */
class DateRangePicker extends ComponentAbstract
{
	const RANGE_YESTERDAY = 'YESTERDAY';
	const RANGE_LAST_SEVEN_DAYS = 'LAST_SEVEN_DAYS';
	const RANGE_LAST_THIRTY_DAYS = 'LAST_THIRTY_DAYS';
	const RANGE_LAST_365_DAYS = 'LAST_365_DAYS';
	const RANGE_LAST_WEEK = 'LAST_WEEK';
	const RANGE_LAST_MONTH = 'LAST_MONTH';
	const RANGE_LAST_YEAR = 'LAST_YEAR';
	const RANGE_WEEK_TO_DATE = 'WEEK_TO_DATE';
	const RANGE_MONTH_TO_DATE = 'MONTH_TO_DATE';
	const RANGE_YEAR_TO_DATE = 'YEAR_TO_DATE';
	const RANGE_ALL_TIME = 'ALL_TIME';

	const MODE_SINGLE = 'SINGLE';
	const MODE_RANGE = 'RANGE';

	/**
	 * Indicates whether to ignore invalid dates or pass them
	 * to the component. Invalid dates:
	 *	- do not match the input format 'Y-m-d'
	 *	- are out of the date range limits
	 *
	 * @var int
	 */
	const FLAG_DISABLE_INVALID_DATES = 1;

	/**
	 * Max date of the range restriction
	 * Unlimited by default
	 *
	 * @var date
	 */
	protected $_maxDate = null;

	/**
	 * Min date of the range restriction
	 * Unlimited by default
	 *
	 * @var date
	 */
	protected $_minDate = null;

	/**
	 * Start date of the selected range
	 *
	 * @var date
	 */
	protected $_startDate = null;

	/**
	 * End date of the selected range
	 *
	 * @var date
	 */
	protected $_endDate = null;

	/**
	 * Combined predefined and custom ranges
	 *
	 * @var array
	 */
	protected $_definedRange;

	/**
	 * Internal date format
	 *
	 * @var string
	 */
	protected $_format;

	/**
	 * Output date format
	 *
	 * @var string
	 */
	protected $_outputFormat;

	/**
	 * Date entry date format
	 *
	 * @var string
	 */
	protected $_dateEntryFormat;

	/**
	 * Submit action link
	 *
	 * @var string
	 */
	protected $_actionLink = null;

	/**
	 * Picking date mode
	 *
	 * @var string
	 */
	protected $_mode = null;

	/**
	 * Active flags of component
	 *
	 * @var int
	 */
	protected $_flags = 0;

	/**
	 * Is default range set? Otherwise the user custom range is selected.
	 *
	 * @var int
	 */
	protected $_isSetDefaultRange = false;


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
	 * @param  string						$identifier
	 * @param  array						$assetsLoad
	 * @param  array						$sessionLoad
	 */
	public function __construct($identifier, $assetsLoad, $sessionLoad = array())
	{
		// define asset files to include to page head
		$assets = array(
			'css' => array (
				'datepicker.css',
			),
			'js' => array (
				'datepicker.js',
				'jquery.dateentry.min.js',
				'jquery.mousewheel.min.js',
			),
		);
		parent::__construct($identifier, $assetsLoad, $assets, $sessionLoad);

		$this->_currentDateRange = array();

		// set default time format
		$this->_format = 'd M Y';

		$this->_dateEntryFormat = array(
			'entry' => 'ymd/',
			'smarty' => '%Y/%m/%d',
			'initial' => 2
		);

		$this->_handlePostData();
	}


	/**
	 * Return true if no flags are set, otherwise return false
	 *
	 * @return bool
	 */
	public function hasEmptyFlags()
	{
		return $this->_flags == 0;
	}


	/**
	 * Add code to ignoreqs settings
	 *
	 * @param  integer $code
	 * @return void
	 */
	public function addFlag($code)
	{
		$this->_flags |= $code;
	}


	/**
	 * Remove code from flags settings
	 *
	 * @param  integer $code
	 * @return void
	 */
	public function removeFlag($code)
	{
		$this->_flags &= ~$code;
	}


	/**
	 * Test if is desired code in flags
	 *
	 * @param  integer $code
	 * @return bool
	 */
	public function hasFlag($code)
	{
		return (bool)($this->_flags & $code);
	}


	/**
	 * Return the 'disable invalid dates' setting
	 *
	 * @return bool
	 */
	public function getDisableInvalidDates()
	{
		return $this->hasFlag(self::FLAG_DISABLE_INVALID_DATES);
	}


	/**
	 * Set the 'disable invalid dates'
	 *
	 * @param  bool $state
	 * @return void
	 */
	public function setDisableInvalidDates($state)
	{
		if ($state) {
			$this->addFlag(self::FLAG_DISABLE_INVALID_DATES);

			// replace an invalid date with the date from session
			if (!$this->_validateDate($this->_startDate)) {
				$this->_startDate = $this->_getProperty('startDate');
			}
			if (!$this->_validateDate($this->_endDate)) {
				$this->_endDate = $this->_getProperty('endDate');
			}
		} else {
			$this->removeFlag(self::FLAG_DISABLE_INVALID_DATES);
		}
	}


	/**
	 * Rewrite the default range that has been set in constructor
	 *
	 * @param  date $startDate
	 * @param  date $endDate
	 * @return self
	 */
	public function setDefaultRange($startDate, $endDate)
	{
		// date range has not been previously set or is in default
		if ($this->_isSetDefaultRange) {
			$startDate = new \DateTime($startDate);
			$endDate = new \DateTime($endDate);
			$this->_startDate = $startDate->format($this->_format);
			$this->_endDate = $endDate->format($this->_format);
		}
		return $this;
	}


	/**
	 * Sets the maximum date
	 *
	 * @param  date $maxDate
	 * @return self
	 */
	public function setMaxDate($maxDate)
	{
		// if a range is default then shift it according to the max date
		// and exit
		if ($this->_isSetDefaultRange && $this->_endDate > $maxDate) {
			$date = new \DateTime($maxDate);
			$this->_endDate = $date->format($this->_format);
			$this->_startDate = $date->modify('-6 days')
				->format($this->_format);
			$this->_maxDate = $maxDate;
			return $this;
		}

		// set the max date
		$this->_maxDate = $maxDate;

		// replace an invalid date with the date from session
		if ($this->getDisableInvalidDates()
				&& !$this->_validateDate($this->_startDate)) {
			$this->_startDate = $this->_getProperty('startDate');
		}
		if ($this->getDisableInvalidDates()
				&& !$this->_validateDate($this->_endDate)) {
			$this->_endDate = $this->_getProperty('endDate');
		}

		return $this;
	}


	/**
	 * Sets the minimum date
	 *
	 * @param date $minDate
	 * @return self
	 */
	public function setMinDate($minDate)
	{
		// set the min date first
		$this->_minDate = $minDate;

		// replace an invalid date with the date from session
		if ($this->getDisableInvalidDates()
				&& !$this->_validateDate($this->_startDate)) {
			$this->_startDate = $this->_getProperty('startDate');
		}
		if ($this->getDisableInvalidDates()
				&& !$this->_validateDate($this->_endDate)) {
			$this->_endDate = $this->_getProperty('endDate');
		}

		return $this;
	}


	/**
	 * Adds a predefined date range
	 *
	 * @param  string $predefinedRange
	 * @return self
	 */
	public function addPredefinedRange($predefinedRange)
	{
		$range = array();

		$today = new \DateTime('today');

		// maximum date is not set, assume that it is today
		$maxDate = (empty($this->_maxDate))
			? $today->format($this->_format)
			: date($this->_format, strtotime("$this->_maxDate"));

		// minimum date is not set, assume the last day from all date range.
		// It is 1st January of the last year.
		$minDate = (empty($this->_minDate))
			? date($this->_format, mktime(0, 0, 0, 1, 1, date('Y')-1))
			: date($this->_format, strtotime("$this->_minDate"));

		switch ($predefinedRange) {
		case self::RANGE_YESTERDAY :
			$yesterday = new \DateTime('yesterday');

			$range['startDate'] = $yesterday->format($this->_format);
			$range['endDate'] = $yesterday->format($this->_format);
			$range['label'] = 'Yesterday';
			$range['value'] = 'yesterday';

			break;
		case self::RANGE_LAST_SEVEN_DAYS :
			$startDate = new \DateTime('today -6 day');

			$range['startDate'] = $startDate->format($this->_format);
			$range['endDate'] = $today->format($this->_format);
			$range['label'] = 'Last 7 days';
			$range['value'] = 'last7days';

			break;
		case self::RANGE_LAST_THIRTY_DAYS :
			$startDate = new \DateTime('today -29 day');

			$range['startDate'] = $startDate->format($this->_format);
			$range['endDate'] = $today->format($this->_format);
			$range['label'] = 'Last 30 days';
			$range['value'] = 'last30days';

			break;
		case self::RANGE_LAST_365_DAYS :
			$startDate = new \DateTime('today -364 day');

			$range['startDate'] = $startDate->format($this->_format);
			$range['endDate'] = $today->format($this->_format);
			$range['label'] = 'Last 365 days';
			$range['value'] = 'last365days';

			break;
		case self::RANGE_LAST_WEEK :
			$end = new \DateTime('last Sunday');
			$start = new \DateTime('last Sunday -6 day');

			$range['startDate'] = $start->format($this->_format);
			$range['endDate'] = $end->format($this->_format);
			$range['label'] = 'Last Week';
			$range['value'] = 'lastweek';

			break;
		case self::RANGE_LAST_MONTH :
			$start = new \DateTime('first day of previous month');
			$end = new \DateTime('last day of previous month');

			$range['startDate'] = $start->format($this->_format);
			$range['endDate'] = $end->format($this->_format);
			$range['label'] = 'Last Month';
			$range['value'] = 'lastmonth';

			break;
		case self::RANGE_LAST_YEAR :
			$start = new \DateTime('first day of January previous year');
			$end = new \DateTime('last day of December previous year');

			$range['startDate'] = $start->format($this->_format);
			$range['endDate'] = $end->format($this->_format);
			$range['label'] = 'Last Year';
			$range['value'] = 'lastyear';

			break;
		case self::RANGE_WEEK_TO_DATE :
			$start = new \DateTime('last Monday');

			$range['startDate'] = $start->format($this->_format);
			$range['endDate'] = $today->format($this->_format);
			$range['label'] = 'Week to Date';
			$range['value'] = 'weektoDate';

			break;
		case self::RANGE_MONTH_TO_DATE :
			$start = new \DateTime('first day of this month');

			$range['startDate'] = $start->format($this->_format);
			$range['endDate'] = $today->format($this->_format);
			$range['label'] = 'Month to Date';
			$range['value'] = 'monthtoDate';

			break;
		case self::RANGE_YEAR_TO_DATE :
			$start = new \DateTime('first day of January this year');

			$range['startDate'] = $start->format($this->_format);
			$range['endDate'] = $today->format($this->_format);
			$range['label'] = 'Year to Date';
			$range['value'] = 'yeartodate';

			break;
		case self::RANGE_ALL_TIME :
			$range['startDate'] = $minDate;
			$range['endDate'] = $maxDate;
			$range['label'] = 'All time';
			$range['value'] = 'alltime';

			break;
		}

		$this->_definedRange[] = $range;
		return $this;
	}


	/**
	 * Adds a custom date range
	 *
	 * @param  date $startDate
	 * @param  date $endDate
	 * @param  string $label
	 * @return self
	 */
	public function addCustomRange($startDate, $endDate, $label)
	{
		$range = array();
		$range['startDate'] = date($this->_format, strtotime($startDate));
		$range['endDate'] = date($this->_format, strtotime($endDate));
		$range['label'] = $label;

		// remove spaces and concatenate strings
		$value = explode(' ', strtolower($label));
		$value = implode('', $value);

		$range['value'] = $value;

		$this->_definedRange[] = $range;
		return $this;
	}


	/**
	 * Sets the start date
	 *
	 * @param  date $startDate
	 * @return self
	 */
	public function setStartDate($startDate)
	{
		if (!$this->getDisableInvalidDates()
			|| $this->_validateDate($startDate)
		) {
			$this->_startDate = date($this->_format, strtotime($startDate));
			$this->_isSetDefaultRange = false;
		}

		return $this;
	}


	/**
	 * Sets the end date
	 *
	 * @param  date $endDate
	 * @return self
	 */
	public function setEndDate($endDate)
	{
		if (!$this->getDisableInvalidDates()
			|| $this->_validateDate($endDate)
		) {
			$this->_endDate = date($this->_format, strtotime($endDate));
			$this->_isSetDefaultRange = false;
		}

		return $this;
	}


	/**
	 * Get the current start date in range
	 *
	 * @return date
	 */
	public function getCurrentDateStart()
	{
		// set to default if output format is not specified
		$format = empty($this->_outputFormat)
			? $this->_format
			: $this->_outputFormat;

		return date($format, strtotime($this->_startDate));
	}


	/**
	 * Get the current end date in range
	 *
	 * @return date
	 */
	public function getCurrentDateEnd()
	{
		// set to default if output format is not specified
		$format = empty($this->_outputFormat)
			? $this->_format
			: $this->_outputFormat;

		return date($format, strtotime($this->_endDate));
	}


	/**
	 * Sets the format of the output date
	 *
	 * @param  string $format
	 * @return self
	 */
	public function setOutputDateFormat($format)
	{
		$this->_outputFormat = $format;

		return $this;
	}


	/**
	 * Sets the action link
	 *
	 * @param  string $link
	 * @return self
	 */
	public function setActionLink($link)
	{
		$this->_actionLink = $link;

		return $this;
	}


	/**
	 * Sets the format of the date entry date
	 *
	 * @param  string $entryFormat - consists of three characters indicating the
	 * order of the fields followed by one or more separator characters
	 * @param  string $smartyFormat - the same date format in smarty format
	 * @param  string $initialField - The field to highlight initially
	 *
	 * Example formats:
	 *
	 * <code>
	 * ('ymd/', '%Y/%m/%d', 2) -> '2012/02/07' - default
	 * ('dmy-', '%d-%m-%Y', 0) -> '07-02-2012'
	 * ('dmY-', '%d-%m-%y', 0) -> '07-02-12'
	 * ('dny ', '%d %b %Y', 0) -> '07 Feb 2012'
	 * ...
	 * </code>
	 *
	 * @return self
	 */
	public function setDateEntryDateFormat($entryFormat, $smartyFormat,
		$initialField = 2
	) {
		$this->_dateEntryFormat = array(
			'entry' => $entryFormat,
			'smarty' => $smartyFormat,
			'initial' => $initialField,
		);

		return $this;
	}


	/**
	 * Returns a structure containing the initialized values.
	 * Dates with no value are set to null.
	 * The predefined and custom range are combined in the 'definedRange' key
	 *
	 * Example:
	 *
	 * <code>
	 * Array {
	 *	['minDate'] => date
	 *	['maxDate'] => date
	 *	['definedRange'] => Array {
	 *		 [0] => Array {
	 *			['startDate'] => date
	 *			['endDate'] => date
	 *			['label'] => string
	 *			['value'] => string
	 *		 }
	 *		 [1]=> Array {
	 *			...
	 *		 } ...
	 *	 }
	 *	 ['currentDateRange'] => Array {
	 *		['startDate'] => date
	 *		['endDate'] => date
	 *	 }
	 *	 ['initialDateRange'] => string
	 * }
	 * </code>
	 *
	 * @return array
	 */
	public function generateStructure()
	{
		$settings = array();

		// disable fields that are out of range
		if (!is_null($this->_definedRange)) {
			$this->_setDateRangeDisplay();
		}

		$settings['definedRange'] = $this->_definedRange;

		$dates = array();

		// set start and end dates into the template
		$dates['startDate'] =  $this->_startDate;
		$dates['endDate'] = $this->_endDate;

		// save start and end dates to session only if they are not default
		if (!$this->_isSetDefaultRange) {
			$this->_setProperty('startDate', $this->_startDate);
			$this->_setProperty('endDate', $this->_endDate);
		}

		// set dates choose limitation if any
		if (!is_null($this->_maxDate)) {
			$settings['maxDate'] = date($this->_format,
				strtotime($this->_maxDate));
		}
		if (!is_null($this->_minDate)) {
			$settings['minDate'] = date($this->_format,
				strtotime($this->_minDate));
		}

		$settings['currentDateRange'] = $dates;

		$initialDateRange = ($dates['startDate'] == $dates['endDate'])
			? $dates['startDate']
			: $dates['startDate'].' - '.$dates['endDate'];

		$settings['initialDateRange'] = $initialDateRange;

		// set date entry format
		$settings['dateEntryFormat'] = $this->_dateEntryFormat;

		// set action link
		$settings['actionLink'] = $this->_actionLink;

		// set mode - 'range' as default
		if (is_null($this->_mode)) {
			$this->setMode(self::MODE_RANGE);
		}
		$settings['mode'] = $this->_mode;

		return $settings;
	}


	/**
	 * Set which value of defined range is displayed and which value is disabled
	 * Set startDate and endDate range with used defined value minDate and
	 * maxDate
	 *
	 * @return void
	 */
	protected function _setDateRangeDisplay()
	{
		foreach ($this->_definedRange as $k => $range) {
			if (!empty($this->_maxDate) && !empty($this->_minDate)) {
				if ($range['value'] == 'alltime') {
					// set range in predefined all time date range
					$this->_definedRange[$k]['startDate']
						= date($this->_format, strtotime($this->_minDate));
					$this->_definedRange[$k]['endDate']
						= date($this->_format, strtotime($this->_maxDate));
				} else {
					// find maxDate day in date range with data
					if (strtotime($this->_maxDate)
						< strtotime($range['startDate'])
					) {
						$this->_definedRange[$k]['disabled'] = true;
						continue;
					} elseif (strtotime($this->_maxDate)
						< strtotime($range['endDate'])) {
						$this->_definedRange[$k]['endDate']
							= date($this->_format, strtotime($this->_maxDate));
					}

					// find minDate day in date range with data
					if (strtotime($this->_minDate)
						> strtotime($range['endDate'])) {
						$this->_definedRange[$k]['disabled'] = true;
					} elseif (strtotime($this->_minDate)
						> strtotime($range['startDate'])) {
						$this->_definedRange[$k]['startDate']
							= date($this->_format, strtotime($this->_minDate));
					}
				}
			}
		}
	}


	/**
	 * Handle newly chosen date range
	 *
	 * @return void
	 */
	protected function _handlePostData()
	{
		// get POST data
		$newDateRange = empty($_POST['dateRange'])
			? null
			: $_POST['dateRange'];

		$this->_startDate = $this->_getProperty('startDate');
		$this->_endDate = $this->_getProperty('endDate');

		// has chosen date range in input field
		if (!(empty($newDateRange))) {
			// split dates
			$dates = explode('-', $newDateRange);

			$format = empty($this->_outputFormat)
				? $this->_format
				: $this->_outputFormat;

			$startDate = new \DateTime(trim($dates[0]));
			$this->_startDate = $startDate->format($format);

			// check if it is not selected only one day
			if (!empty($dates[1])) {
				$endDate = new \DateTime(trim($dates[1]));
				$this->_endDate = $endDate->format($format);
			} else {
				$this->_endDate = $startDate->format($format);
			}
		}
		// else set default range of 7 days if the dates are empty
		else if (empty($this->_startDate) && empty($this->_endDate)) {
			$startDate = new \DateTime('today -7 day');
			$endDate = new \DateTime('yesterday');

			$this->_startDate = $startDate->format($this->_format);
			$this->_endDate = $endDate->format($this->_format);
			$this->_isSetDefaultRange = true;
		}
	}


	/**
	 * Sets the picking mode
	 *
	 * @param  string $mode
	 * @return self
	 */
	public function setMode($mode)
	{
		switch ($mode) {
		case self::MODE_RANGE:
			$this->_mode = 'range';
			break;
		case self::MODE_SINGLE:
			$this->_mode = 'single';
			break;
		default:
			throw new \Exception("Unknown mode '$mode' in DateRangePicker");
		}

		return $this;
	}


	/**
	 * Corresponding DateInterval - from start to end of the date range
	 *
	 * @return DateInterval
	 */
	public function getDateDiff()
	{
		return date_diff(new \DateTime($this->_startDate),
			new \DateTime($this->_endDate));
	}


	/**
	 * Function validates date if match the input format
	 * and the limits of date range picker.
	 * IMPROVE: Maybe superfluously paranoid?
	 *
	 * @param  string $date
	 * @return bool
	 */
	protected function _validateDate($date)
	{
		$date = new \DateTime($date);
		$dateYmd = $date->format('Y-m-d');

		// validate date format
		if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
			return false;
		}
		list($year, $month, $day) = sscanf($dateYmd, '%d-%d-%d');
		if (!checkdate($month, $day, $year)) {
			return false;
		}

		// validate limits
		if (!empty($this->_minDate) && $date < new \DateTime($this->_minDate)) {
			return false;
		}
		if (!empty($this->_maxDate) && $date > new \DateTime($this->_maxDate)) {
			return false;
		}

		return true;
	}
}
