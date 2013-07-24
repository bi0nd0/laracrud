;(function($, window, document, undefined){
/**
* @AUTHOR Francesco Delacqua
*
* gestione dei remotes:
*  - crea una remote combobox
*
* dipende dalla plugin jquery.select2.js
*
*/

var pluginName = "remoteCombo",
	defaults = {
		'remoteInputSelector'		: 'input[type=text].mm-remote',
		'resultsKey'				: 'results', //used in ajax results function
		'resultsKeySingular'		: 'result', //used in initSelection function
		'sel2AllowClear'			: true,
		'sel2Placeholder'			: 'cerca un elemento',
		'formatResultCallback'		: function(item) {
			return [
				'<span>',
				'cambiami',
				'</span>'
			].join('');
		},
		'formatSelectionCallback'	: function(item) {
			return 'cambiami';
		}
	};

Plugin.prototype.initSelect2 = function(url) {
	var self = this,
		$sel2;

	$sel2 = $(self.element).select2({
		allowClear: self.options.sel2AllowClear,
		placeholder: self.options.sel2Placeholder,
		minimumInputLength: 2,
		ajax: { // instead of writing the function to execute the request we use Select2's convenient helper
			url: url,
			dataType: 'json',
			data: function (term, page) {
				return {
					q: term, // search term
					page_limit: 10,
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data[self.options.resultsKey]};
			}
		},
		initSelection: function(element, callback) {
			// the input tag has a value attribute preloaded that points to a preselected movie's id
			// this function resolves that id attribute to an object that select2 can render
			// using its formatResult renderer - that way the movie name is shown preselected
			 var id=$(element).val();
			 if (id!=="") {
			 	$.ajax(url+"/"+id, {
					data: {
						// apikey: "ju6z9mjyajq2djue3gbvv26t"
					},
					dataType: "json"
				}).done(function(data) { callback(data[self.options.resultsKeySingular]); });
			}
		},
		formatResult: self.options.formatResultCallback,
		formatSelection: self.options.formatSelectionCallback,
		dropdownCssClass: "s2_dropdown", // apply css that makes the dropdown taller
		escapeMarkup: function (m) { return m; } // we do not want to escape markup since we are displaying html in results
	});

	/*$sel2.on('change select2-highlight', function(e){
		console.log(e);
	});*/
}

function Plugin(element, options) {
	this.element = element;
	this.options = $.extend( {}, defaults, options);
	this._defaults = defaults;

	this.init();
}

Plugin.prototype.init = function() {
	var self = this;

	self.remoteUrl = $(self.element).data('url');
	self.initSelect2(self.remoteUrl);

	self.bindEvents();
};

Plugin.prototype.bindEvents = function() {
	var self = this;

	$(self.element).on('click',function(e){
		e.preventDefault();
	});

};

$.fn[pluginName] = function(options){
	return this.each(function(){
		if ( !$.data(this, "plugin_" + pluginName )) {
			$.data( this, "plugin_" + pluginName,
			new Plugin( this, options )); //salvo un riferimento alla plugin nell'elemento
		}
	});
};

}( jQuery, window, document ));