<?php
/**
 * Extra_AnalyticUtils class file.
 *
 * @package    Extra
 */

/**
 *  Analytic Utils
 *  Holds utility functions for statistics
 *
 * @category   Eos
 * @package    Extra
 */
class Extra_AnalyticUtils
{
	/**
	 * Truncates date to given precision
	 *
	 * TODO: unit tests for this
	 *
	 * @param  string $date
	 * @param  string $type
	 * @return string
	 */
	static public function truncateDate($date, $type)
	{
		$d = new Datetime($date);

		switch ($type) {
		case 'hour':
			return $d->format('Y-m-d H:00:00');

		case 'day':
			return $d->format('Y-m-d');

		case 'week':
			// converted day of the week, so 0 => Monday, 6 => Sunday
			$dayOfTheWeek = ((int) $d->format('w') + 6) % 7 ;
			// reset day to the start of the week
			$d->modify("-{$dayOfTheWeek} days");
			return $d->format('Y-m-d');

		case 'month':
			return $d->format('Y-m-01');

		default:
			throw new Exception("Invalid date type '{$type}'");
		}
	}
}