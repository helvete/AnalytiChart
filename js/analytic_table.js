(function($) {
	$.analyticTable = {
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
				chart: null
			};

			// merge custom and default settings
			settings = $.extend({}, defaults, settings);

			this.container = settings.container;
			this.identifier = settings.identifier;
			this.table = settings.table;
			this.columns = settings.columns;
			this.chart = settings.chart;
			this.primary_dimension_key = settings.primary_dimension_key;
			this.secondary_dimension_key = settings.secondary_dimension_key;
			this.secondary_dimension = $('select[name='+settings.secondary_dimension_key+']', settings.container);

			var that = this;
			$(document).ready(function() {
				that._initTableControls();
				that._getTableData();

				// highlight selected metrics on particular table changes
				that.table.on('page.dt order.dt length.dt', function() {
					that._highlightMetrics();
				});
			});
		},


		_initTableControls: function() {
			var that = this;

			$('.primaryDimension li:not(:first-child)', this.container)
				.on('click', {'that': this}, this._setPrimaryDimension);
			this.secondary_dimension.on('change', {'that': this}, this._getTableData);

			// secondary dimension button
			$('.secondaryDimension .selectDropdown', this.container).hide();

			$('.secondaryDimensionButton', this.container).on('click', function() {
				var dropdown = $(this).siblings('.selectDropdown');
				if ($('.secondaryDimension select', this.container).val() != '') {
					$(this).html('Sekundární dimenze<i class="fa fa-chevron-down">&nbsp;</i>');
					$('dd a .value:contains("")', dropdown).trigger('click');
				} else {
					dropdown.show();
					$(this).hide();
					$('dt a', dropdown).trigger('click');
				}
			});

			$('.secondaryDimension select', this.container).on('change', function() {
				that.chart._getChartData();
				var dropdown = $(this).siblings('.selectDropdown');
				dropdown.hide();
				if ($(this).val() == '') {
					var text = 'Sekundární dimenze<i class="fa fa-chevron-down">&nbsp;</i>';
				} else {
					var text = $('option:selected', this).text() + '<i class="fa fa-times">&nbsp;</i>';
				}
				$('.secondaryDimensionButton', this.container).html(text).show();
			});
		},


		_setPrimaryDimension: function(event) {
			var that = event.data.that;

			if ($(this).data('value') == $('.primaryDimension li.selected', that.container).data('value')) {
				return;
			}

			$('.primaryDimension li.selected').removeClass('selected');

			$(this).addClass('selected');

			that.chart._getChartData();
			that._getTableData(event);
		},


		_getTableData: function(event) {
			if (typeof event === 'undefined') {
				var that = this;
			} else {
				var that = event.data.that;
			}

			var data = {
				'vcIdentifier': that.identifier,
				'vcInstanceIdentifier': '',
				'vcMethod': 'loadData',
				'format': 'json'
			}

			if (that.chart) {
				data.lod = that.chart._getLod();
			};

			data[that.primary_dimension_key] = $('.primaryDimension li.selected', that.container).data('value');;
			data[that.secondary_dimension_key] = that.secondary_dimension.val();

			Manager.ajax({
				type: "POST",
				data: data,
				success: function(result) {
					if (result.data.validation) {
						// save result data for table draw callback
						that.data = result.data;

						that.table.clear();
						that.table.rows.add(result.data.rows);
						that.table.draw();

						var secondaryColumn = that.table.column(2);
						if (result.data.secondary_dimension_active) {
							secondaryColumn.visible(true);
						} else {
							secondaryColumn.visible(false);
						}

						$.each(result.data.summary, function(column, value) {
							if (that.columns[column]) {
								$('th.' + column, that.container).html(
									$.metricFormats.formatByType(value['total'], that.columns[column].type));
								if (value['average']) {
									$('th.' + column, that.container).append(
										$('<span>Average: '+$.metricFormats.formatByType(value['average'], that.columns[column].type)+'</span>'));
								}
							}
						});

						$('th.primary', that.container).html(result.data.primary_dimension_caption + '<i class="fa fa-lg">&nbsp;</i>');
						$('th.secondary', that.container).html(result.data.secondary_dimension_caption + '<i class="fa fa-lg">&nbsp;</i>');

						// highlight selected metrics
						that._highlightMetrics();
					}
				}
			});
		},


		_highlightMetrics: function() {
			var that = this;

			// get selected analytic chart metrics
			var primaryMetric = that.chart.primary_metric.val().toLowerCase();
			var secondaryMetric = that.chart.secondary_metric.val().toLowerCase();

			// remove previous highlight
			$('table.analyticTable td.highlight').removeClass('highlight');

			// if selected, highlight primary metric (should be always selected)
			if (primaryMetric) {
				// set small delay because the table structure might not be yet
				// reloaded
				window.setTimeout(function() {
					$('table.analyticTable td.' + primaryMetric).addClass('highlight');
				}, 1);
			}

			// if selected, highlight secondary metric
			if (secondaryMetric) {
				// set small delay because the table structure might not be yet
				// reloaded
				window.setTimeout(function() {
					$('table.analyticTable td.' + secondaryMetric).addClass('highlight');
				}, 1);
			}
		},


		_createRowSelector: function(row, index) {
			var check = $('<input type="checkbox">')
			$('td', row).eq(0).addClass('checkboxRow').html(check);
			if (this.chart && this.chart !== null) {
				check.on('click', {'that': this, 'row':row, 'index':index},
					this._assignRowToChart);
			}
		},


		_assignRowToChart: function(event) {
			var that = event.data.that;
			var row = event.data.row;
			var index = event.data.index;

			var key = 'row' + index;
			if (!$(this).is(':checked')) {
				that.chart._removeAnalyticTableRow(key);
				return;
			}
			var primary = $('.primaryDimension li.selected', that.container).data('value');
			var primaryID = that.data.primary_dimension_ids[index];
			var primaryName = $('td', row).eq(1).text();
			var secondary;
			var secondaryID;
			var secondaryName;
			if (that.data.secondary_dimension_active) {
				secondary = that.secondary_dimension.val();
				secondaryID = that.data.secondary_dimension_ids[index];
				secondaryName = $('td', row).eq(2).text();
			}
			that.chart._addAnalyticTableRow(key, {
				'primary_dimension': primary,
				'primary_dimension_id': primaryID,
				'secondary_dimension': secondary,
				'secondary_dimension_id': secondaryID,
				'primary_dimension_name': primaryName,
				'secondary_dimension_name': secondaryName
			});
		}
	}
})(jQuery);
