var Manager = Manager || {};

/***
 * Contains Ajax request wrapper for sandbox.
 * 3 new methods are added to Manager object:
 *
 * .ajax(url, settings)
 * Use this wrapper instead of jQuery $.Ajax to enable handling of server side
 * errors from sandbox framework.
 *
 * .ajaxRegisterErrorHandler(function, context)
 * To Add new error handler use this method.
 *
 * .ajaxSetSettings(settings, replace)
 * To update / replace settings
 *
 * For initialization references to Manager and jQuery objects are passed
 * as parameters.
 *
 * @param {Object} namespace object to extend (Manager)
 * @param {jQuery} $ jQuery object to be used
 * @return void
 */
;(function(namespace,$) {
var request = {

	/**
	 * Settings object
	 *
	 * @type Object
	 */
	defaultSettings: {
		dataType: "json",
		timeout: 10000
	},
	/**
	 * Stores functions used as error handlers.
	 * Handlers are stored as object with context to be called:
	 *		{"handler": Function, "context": this}
	 *	handler call:
	 *		handler.call(context, jqXHR, textStatus, data)
	 *	Note:
	 *	If textStatus is not success data will contain string with errorThrown.
	 *	Otherwise it will be json data returned from server.
	 *
	 *	Handler example:
	 *	{
	 *		"handler": function(jqXHR, textStatus, data) {
	 *			console.log("Manager Error Handler triggered", data);
	 *		},
	 *		"context": undefined
	 *	}
	 *
	 * @type Array
	 */
	_errorHandlers: [],


	/**
	 * Behaves as standard ajax request, but if returned data status is set
	 * to error - error functions are triggered instead success.
	 * And methods dealing with error state are initialized.
	 *
	 * See jQuery Ajax manual for settings and callback formats.
	 * @see http://api.jquery.com/jQuery.ajax/
	 *
	 * Success, error, complete in settings object are supported.
	 *
	 * Returned promise uses .done, failed, always methods only (jQuery's fallback
	 * .success, .error, .complete are not supported),
	 *
	 * Due to possibility of calling erros from success function errorThrown will
	 * be set to false and 4th parameter data is added.
	 *
	 * Callbacks in settings (function or array of functions):
	 *	success(data, textStatus, jqXHR)
	 *	error(jqXHR, textStatus, errorThrown, data)
	 *	complete(jqXHR, textStatus)
	 *
	 * Callbacks available from returned promise;
	 *	done(data, textStatus, jqXHR)
	 *	fail(jqXHR, textStatus, errorThrown, data)
	 *		If fail was triggered by status: "error" errorThrown is false
	 *		and data argument is set
	 *	always()
	 *
	 *	To set callback on promise (recomended):
	 *	var promise = Manager.ajax(url, settings);
	 *	promise.done(function(data) {});
	 *
	 *
	 *  Asyc flag is supported, but it is strongly recommanded not to use it.
	 *  Ever, or ghost of waiting js threads will haunt you.
	 *
	 *  JSONP is not currently supported.
	 *
	 * @param {String|Object} url
	 * @param {Object} [settings]
	 * @return jQuery.Promise
	 */
	ajax: function(url, settings) {
		var that = this;
		var deferred = $.Deferred();
		// go over settings and store special functions
		var reuse = ["context", "jsonpCallback"];
		// set them always as arrays to simplify resolving
		var reuseArrays = ["success", "error", "complete"];
		var stored = {};

		// if url is not string it settings object
		if (url.substring) {
			settings = (settings === undefined) ? {} : settings;
			settings.url = url;
		} else {
		// set correct setting object
			settings = url;
		}

		for (var i = 0, length = reuse.length; i < length; i++) {
			// get original
			if (typeof settings[reuse[i]] !== 'undefined') {
				stored[reuse[i]] = settings[reuse[i]];
				// remove from settings
				delete(settings[reuse[i]]);
			}
		}

		// console.log(settings);
		// prepare array reuse items, always array if not stored creates empty
		// array
		for (var j = 0, length = reuseArrays.length; j < length; j++) {
			// get original
			if (typeof settings[reuseArrays[j]] !== 'undefined') {
				if ($.isArray(settings[reuseArrays[j]])) {
					stored[reuseArrays[j]] = settings[reuseArrays[j]];
				} else {
					stored[reuseArrays[j]] = [settings[reuseArrays[j]]];
				}
				// remove from settings
				delete(settings[reuseArrays[j]]);
			}
			// add empty array
			else {
				stored[reuseArrays[j]] = [];
			}
		}

		// extend default settings new settings
		settings = $.extend({}, this.defaultSettings, settings);

		// add error / success / complete wrappers
		settings.error = function(jqXHR, textStatus, errorThrown) {
			that.error(deferred, stored, jqXHR, textStatus, errorThrown);
		};
		settings.success = function(data, textStatus, jqXHR) {
			that.success(deferred, stored, data, textStatus, jqXHR);
		};
		settings.complete = function(jqXHR, textStatus) {
			that.complete(deferred, stored, jqXHR, textStatus);
		};

		// start ajax request
		$.ajax(settings);

		// return promise object
		return deferred.promise();
	},


	/**
	 * Error handler, handles error response from server.
	 * Calls error methods given in request settings object and
	 * triggers deferred failed calls.
	 *
	 * @param {jQuery.Deferred} deferred
	 * @param {Object} stored
	 * @param {jqXHR} jqXHR
	 * @param {String} textStatus
	 * @param {String} errorThrown
	 */
	error: function(deferred, stored, jqXHR, textStatus, errorThrown) {
		// get correct context
		var context = (typeof stored.context !== 'undefined') ? stored.context : this;

		// call universal error handlers
		var errorHandlers = this._errorHandlers;
		for (var k=0, length = errorHandlers.length; k < length; k++) {
			var handlerContext = (errorHandlers[k].context === undefined)
				? this : errorHandlers[k].context;
			errorHandlers[k].handler.call(handlerContext, jqXHR, textStatus, errorThrown);
		}

		// call callback function
		for (var i=0, length = stored.error.length; i < length; i++) {
			stored.error[i].call(context, jqXHR, textStatus, errorThrown);
		}
		deferred.rejectWith(context, [jqXHR, textStatus, errorThrown]);
	},


	/**
	 * Ajax request success call.
	 * Only if request answered with status set to "ok"
	 * given success methods and deferred .done() is triggered.
	 *
	 * If status is not "ok" error and .fail is called.
	 * Triggers methods dealing with server
	 *
	 * @param {jQuery.Deferred} deferred
	 * @param {Object} stored Methods stored from original request
	 * @param {Object} data Data from server
	 * @param {String} textStatus Response status
	 * @param {jqXHR} jqXHR
	 */
	success: function(deferred, stored, data, textStatus, jqXHR) {
		// get correct context
		var context = (typeof stored.context !== 'undefined') ? stored.context : this;

		// test if data.success ok
		if (typeof data.status === 'undefined') {
			// malformed
		} else if (data.status === "ok") {
			// call callback function
			for (var i=0, length = stored.success.length; i < length; i++) {
				stored.success[i].call(context, data, textStatus, jqXHR);
			}
			// ok resolve all done callbacks
			deferred.resolveWith(context, [data, textStatus, jqXHR]);
		} else {
			// call universal error handlers
			var errorHandlers = this._errorHandlers;
			for (var k=0, length = errorHandlers.length; k < length; k++) {
				var handlerContext = (errorHandlers[k].context === undefined)
					? this : errorHandlers[k].context;
				errorHandlers[k].handler.call(handlerContext, jqXHR, textStatus, data);
			}

			// call each error method, always set to array
			for (var i=0, length = stored.error.length; i < length; i++) {
				stored.error[i].call(context, jqXHR, textStatus, false, data);
			}
			// deferred trigger fail state
			deferred.rejectWith(context, [jqXHR, textStatus, false, data]);
		}
	},


	/**
	 * Called always after error or success,
	 * Note: promise.always is triggered automatically after deferred
	 * is resolved
	 *
	 * @param {jQuery.Deferred} deferred
	 * @param {Object} stored
	 * @param {jqXHR} jqXHR
	 * @param {String} textStatus
	 */
	complete: function(deferred, stored, jqXHR, textStatus) {
		var context = (typeof stored.context !== 'undefined') ? stored.context : this;

		// call each complete method, always set to array
		for (var i=0, length = stored.complete.length; i < length; i++) {
			stored.complete[i].call(context, jqXHR, textStatus);
		}
	},


	/**
	 * Adds error handler to be run on status error.
	 * Optional context object for handler function can be passed
	 * as second parameter.
	 *
	 * Arguments passed to handler:
	 *	handler(jqXHR, textStatus, data)
	 *
	 * @param {Function} handler Handler function
	 * @param {Object} [context] Context to run handler in
	 * @returns {this}
	 */
	registerErrorHandler: function(handler, context) {
		this._errorHandlers.push({"context": context, "handler": handler});
		return this;
	},


	/**
	 * Updates default ajaxRequest settings.
	 * By default setting object is only updated/extended, if replace is set
	 * to true object is replaced (not copied).
	 *
	 * Error, success and complete function can't e replaced from settings.
	 *
	 * @param {Object} settings
	 * @param {Boolean} [replace=false] If set to true setting object is replaced
	 * @return {request} Fluent inteface
	 */
	setDefaultSettings: function(settings, replace) {
		replace = (replace === undefined) ? false : replace;
		if (replace === true) {
			this.defaultSettings = settings;
		} else {
			$.extend(true, this.defaultSettings, settings);
		}
		return this;
	}
};


/**
 * Provides wrapped Ajax Call, this call will handles Sandbox json
 * responses and calls success function only if header was correct.
 *
 * Returns promise object.
 * http://api.jquery.com/Types/#Promise
 *
 * Phase 1:
 *		handle basic request + call error on success: "error"
 * phase 2:
 *		integrate with other manager components to handle page state
 *
 * @param {String|Object} url
 * @param {Object} [settings]
 * @return jQuery.Promise
 */
namespace.ajax = function(url) {
	return request.ajax(url);
};


/**
 * Registers new ajax Request error handler. Given function will be called
 * if Ajax request fails.
 * Ajax request object is used as context if not provided.
 *
 * 	 Arguments passed to handler:
 *		handler.call(contex, jqXHR, textStatus, data)
 *
 *	Note: if textStatus isn't success data will containt thrown error, and
 *	error occured during server response or json parsing.
 *
 * @param {Function} handler Handler function
 * @param {Object} [context] Optional context for function
 * @returns {namespace} Fluent interface
 */
namespace.ajaxRegisterErrorHandler = function(handler, context) {
	request.registerErrorHandler(handler, context);
	return this;
};


/**
 * Updates or replaces Ajax request settings.
 * Success error or complete functions can't be replaced
 *
 * @param {Object} settings
 * @param {Boolean} [replace=false]
 * @returns {namespace} Fluent interface
 */
namespace.ajaxSetSettings = function(settings, replace) {
	request.setDefaultSettings(settings, replace);
	return this;
};

})(Manager, $);
