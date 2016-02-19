// dummy console if no normal is present
if( ! window.console){ window.console = {log: function(){} }; }

(function($) {
	$.analyticChart = {
		/**
		 * Creates new object of analyticChart with analyticChart as prototype
		 *
		 * @param {Object} settings
		 * @return {analyticChart}
		 */
		create: function(settings) {
			var newObject = Object.create(this);
			newObject._init(settings);
			return newObject;
		},

		formatDate : function(d, tooltip){
			if (typeof tooltip == 'undefined') {
				tooltip = false;
			};

			var format;
			switch(this._getLod()) {
			case 'hour': format = d3.time.format("%Y-%m-%d %H:%M:%S"); break;
			case 'day': format = d3.time.format("%Y-%m-%d"); break;
			case 'week':
				// for tooltip we will show date from-to of the week
				if (tooltip) {
					// convert day to the 0=monday 6=sunday
					var normalizedDay = 1000*60*60*24*( d.getDay() == 0 ? 6 : d.getDay() -1)
					var first = new Date(d.getTime() - normalizedDay);
					var last = new Date(first.getTime() +  1000*60*60*24*6);

					var formatFirst = first.getMonth() != last.getMonth()
						? "%d. %m."
						: "%d.";
					var formatLast = "%d. %m. %Y";

					return d3.time.format(formatFirst)(first) + " - " +
						d3.time.format(formatLast)(last);
				};

				format = d3.time.format("%W/%Y");
				break;
			case 'month': format = d3.time.format("%m/%Y"); break;
			default: console.log('unsupported LOD'); return '?';
			}

			return format(d);
		},

		/**
		 * Creates separated settings and data variables
		 * (detaches instance from prototype where needed)
		 *
		 * @constructor
		 * @param {Object} settings
		 */
		_init: function(settings) {
			// default text wrapper setting
			var defaults = {
				tableIdentifier: null
			};

			// merge custom and default settings
			settings = $.extend({}, defaults, settings);

			this.container = settings.container;
			this.identifier = settings.identifier;
			this.tableIdentifier = settings.tableIdentifier;
			this.metrics = settings.metrics;
			this.shadowMetrics = settings.shadowMetrics;
			this.primary_metric_key = settings.primary_metric_key;
			this.secondary_metric_key = settings.secondary_metric_key;
			this.primary_metric = $('select[name='+settings.primary_metric_key+']', settings.container);
			this.secondary_metric = $('select[name='+settings.secondary_metric_key+']', settings.container);

			this.selected_primary_metric = this.primary_metric.val();
			this.selected_secondary_metric = this.secondary_metric.val();

			var that = this;
			$(document).ready(function() {
				that.chart = c3.generate(settings.chartSettings);
				$(that.chart).ready(function() {
					that._initChartControls();
					that._getChartData();
				});
			});
		},


		_initChartControls: function() {
			this.primary_metric.on('change', {'that': this}, this._getChartData);
			this.secondary_metric.on('change', {'that': this}, this._getChartData);
			$('.plottingSelection li', this.container)
				.on('click', {'that': this}, this._setLod);
		},


		_setLod: function(event) {
			var that = event.data.that;

			if ($(this).data('value') == $('.plottingSelection li.selected', that.container).data('value')) {
				return;
			}

			$('.plottingSelection li.selected').removeClass('selected');

			$(this).addClass('selected');

			that._getChartData(event);
		},

		_getLod: function(){
			return $('.plottingSelection li.selected', this.container)
				.data('value');
		},

		_getChartData: function(event) {
			if (typeof event === 'undefined') {
				var that = this;
			} else {
				var that = event.data.that;

				// reload analytic table (if present)
				var analyticTable = window[that.tableIdentifier];
				if (analyticTable) {
					analyticTable._getTableData();
				};
			}

			that.selected_primary_metric = that.primary_metric.val();
			that.selected_secondary_metric = that.secondary_metric.val();

			var data = {
				'vcIdentifier': that.identifier,
				'vcInstanceIdentifier': '',
				'vcMethod': 'loadData',
				'format': 'json',
				'lod': $('.plottingSelection li.selected', that.container).data('value')
			};
			data[that.primary_metric_key] = that.primary_metric.val();
			data[that.secondary_metric_key] = that.secondary_metric.val();

			Manager.ajax({
				type: "POST",
				data: data,
				success: function(result) {
					var axes = result.data.axes || {};
					if (result.data.columns.length > 0) {
						// support both date and datetime in the input
						// convert everything to the datetime
						var formatTime = d3.time.format("%Y-%m-%d %H:%M:%S");
						var formatDate = d3.time.format("%Y-%m-%d");

						// we are interested only in formatting the axis labels
						var header = result.data.columns[0];

						for (var i = 1; i < header.length; i++) {
							// try to parse time first, fallback to date only
							var unified = formatTime.parse(header[i]);
							if (unified == null) {
								unified = formatDate.parse(header[i]);
							};
							// format as datetime if possible, leave unchanged
							// if unable to parse dattime
							header[i] = unified == null
								? header[i]
								: formatTime(unified) ;
						};

						result.data.columns[0] = header;

						// manage y2 visibility
						var showY2 = false;
						// check if secondary or shadow metric column is present
						// and for y2 axis
						result.data.columns.forEach(function(column, index) {
							if (showY2) {
								return;
							}
							if ((column[0] === that.secondary_metric_key
								|| that.shadowMetrics.indexOf(column[0]) > -1)
								&& axes[column[0]] && axes[column[0]] === 'y2'
							) {
								showY2 = true;
							}
						});
						// disables ticks legend
						that.chart.internal.config.axis_y2_show = showY2;
						// hide|show axis
						that.chart.internal.axes.y2.style("visibility", (showY2)? 'visible' : 'hidden');
					};
					if (result.data.validation) {
						that.chart.load({
							unload: true,
							columns: result.data.columns,
							axes: axes
						});
						that.chart.data.names(result.data.names);
					}
				}
			});
		},


		_removeAnalyticTableRow: function(key) {
			var that = this;
			that.chart.unload({
				ids: key
			});
		},


		_addAnalyticTableRow: function(key, dimensions) {
			var that = this;

			var data = {
				'vcIdentifier': that.identifier,
				'vcInstanceIdentifier': '',
				'vcMethod': 'loadTableRowData',
				'format': 'json',
				'lod': that._getLod(),
				'key': key,
				'primary_dimension': dimensions.primary_dimension,
				'primary_dimension_id': dimensions.primary_dimension_id,
				'secondary_dimension': dimensions.secondary_dimension,
				'secondary_dimension_id': dimensions.secondary_dimension_id
			};
			data[that.primary_metric_key] = that.primary_metric.val();

			Manager.ajax({
				type: "POST",
				data: data,
				success: function(result) {
					if (result.data.validation) {
						that.chart.load({
							columns: result.data.columns
						});

						var caption = dimensions.primary_dimension_name;
						if (dimensions.secondary_dimension_name) {
							caption = caption + ' - ' + dimensions.secondary_dimension_name;
						}
						var names = [];
						names[key] = caption;
						that.chart.data.names(names);
					}
				}
			});
		},


		_formatMetric: function(d, metric) {
			var selectedMetric = (metric == this.secondary_metric_key)
				? this.selected_secondary_metric : this.selected_primary_metric;
			var type = (!this.metrics[selectedMetric])
				? $.metricFormats._RELATIVE : this.metrics[selectedMetric].type;
			return $.metricFormats.formatByType(d, type);
		}
	}
})(jQuery);
