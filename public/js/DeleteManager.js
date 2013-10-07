;(function($, window, document, undefined){
/**
* @AUTHOR Francesco Delacqua
*
* gestione cancellazione modelli
*
* il pulsante deve avere i seguenti attributi:
* - data-delete-url
* - data-model-id
*
*/

var pluginName = "deleteManager",
	defaults = {
		deleteModalTemplate: [
			'<div class="modal hide fade">',
				'<div class="modal-header">',
					'<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>',
					'<h3>Sei sicuro?</h3>',
				'</div><!--/modal-header-->',

				'<div class="modal-body">',
					'<p>Sicuro di voler cancellare questo elemento?</p>',
					'<form action="#" method="POST">',
					'<input type="hidden" value="DELETE" name="_method">',
					'</form>',
				'</div><!--/modal-body-->',

				'<div class="modal-footer">',
					'<button class="btn" data-dismiss="modal" aria-hidden="true">Annulla</button>',
					'<button class="btn btn-danger formsubmitter">Elimina</button>',
				'</div><!--/modal-footer-->',
			'</div>',
		].join(''),
	};


function Plugin(element, options) {
	this.element = element;
	this.options = $.extend( {}, defaults, options);
	this._defaults = defaults;

	this.init();
}

Plugin.prototype.init = function() {
	this.$deleteModal = $(this.options.deleteModalTemplate).appendTo('body');
	this.$deleteForm = this.$deleteModal.find('form');

	this.bindEvents();
};

Plugin.prototype.bindEvents = function() {
	var self = this;

	$(self.element).on('click',function(e){
		var modelID = $(this).data('model-id'),
			url = $(self.element).data('delete-url');
		
		if(url && modelID) {
			self.$deleteForm.attr('action',[url,modelID].join('/'));
			self.$deleteModal.modal();
		}
		e.preventDefault();
	});
	//=============================================
	// bind modal button to form
	self.$deleteModal.find('button.formsubmitter').on('click',function(e){
		self.$deleteForm.submit();
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

$(window).on('load', function () {
	$('[data-delete-url]').each(function () {
		var $dm = $.fn[pluginName].apply($(this),[{}]);
	});
});

}( jQuery, window, document ));