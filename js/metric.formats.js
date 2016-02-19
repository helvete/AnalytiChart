(function($) {
	$.metricFormats = {
		_ABSOLUTE: 'ABSOLUTE',
		_RELATIVE: 'RELATIVE',
		_CURRENCY: 'CURRENCY',
		_DECIMAL: 'DECIMAL',
		_MINUTES_AND_SECS: 'MINUTES_AND_SECS',
		_HOURS_AND_MINS: 'HOURS_AND_MINS',
		_DAYS_DECIMAL: 'DAYS_DECIMAL',


		formatByType: function(value, type) {
			switch (type) {
			case this._ABSOLUTE:
				return this._formatAbsolute(value);
				break;

			case this._RELATIVE:
				return this._formatRelative(value);
				break;

			case this._MINUTES_AND_SECS:
				return this._formatMinutesSecs(value);
				break;

			case this._HOURS_AND_MINS:
				return this._formatHoursMins(value);
				break;

			case this._DAYS_DECIMAL:
				return this._formatDaysDecimal(value);
				break;

			case this._CURRENCY:
				return this._formatCurrency(value);
				break;

			case this._DECIMAL:
				return this._formatDecimal(value);
				break;
			}
		},


		_formatAbsolute: function(value) {
			return this._number_format(value, 0, ',', ' ');
		},


		_formatDecimal: function(value) {
			return this._number_format(value, 2, ',', ' ');
		},


		_formatRelative: function(value) {
			return this._number_format(value*100, 2, ',', ' ') + '%';
		},


		_formatMinutesSecs: function(value) {
			var minutes = this._number_format(Math.floor(value/60), 0, ',', ' ');
			var seconds = this._number_format(value-(minutes*60), 0, ',', ' ');
			if (seconds < 10) {
				seconds = '0' + seconds;
			} else if (seconds == 60) {
				seconds = 59;
			}
			return minutes + 'm ' + seconds + 's';
		},


		_formatHoursMins: function(value) {
			var hours = this._number_format(Math.floor(value/60/60), 0, ',', ' ');
			var minutes = this._number_format(Math.round((value-(hours*60*60))/60), 0, ',', ' ');
			if (minutes < 10) {
				minutes = '0' + minutes;
			} else if (minutes == 60) {
				minutes = 59;
			}
			return hours + 'h ' + minutes + 'm';
		},


		_formatDaysDecimal: function(value) {
			var days = this._number_format(value/60/60/24, 2, ',', ' ');
			return days + ' dne';
		},


		_formatCurrency: function(value) {
			return this._number_format(value, 2, ',', ' ') + ' Kč';
		},


		_number_format: function(number, decimals, dec_point, thousands_sep) {
			number = (number + '')
				.replace(/[^0-9+\-Ee.]/g, '');
			var n = !isFinite(+number) ? 0 : +number,
				prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
				sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
				dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
				s = '',
				toFixedFix = function(n, prec) {
					var k = Math.pow(10, prec);
					return '' + (Math.round(n * k) / k)
						.toFixed(prec);
				};
			// Fix for IE parseFloat(0.55).toFixed(0) = 0;
			s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
				.split('.');
			if (s[0].length > 3) {
				s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
			}
			if ((s[1] || '')
				.length < prec) {
				s[1] = s[1] || '';
				s[1] += new Array(prec - s[1].length + 1)
					.join('0');
			}
			return s.join(dec);
		}
	}
})(jQuery);
