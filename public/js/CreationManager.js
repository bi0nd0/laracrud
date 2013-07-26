;(function($, window, document, undefined){
/**
* @AUTHOR Francesco Delacqua
*
* gestione creazione modelli asincrona
*
* il pulsante deve avere i seguenti attributi:
* - data-url lurl per il submit dei dati
* - data-fields-selector la stringa usata per ritrovare l'elemento che contiene i campi del form (solitamente è un contenitore hidden)
* - data-model-id (in caso di editing) TODO!
*
* struttura tipica:
*	<div class="creation-wrapper">
*		<a href="#" class="btn btn-info create_trigger" data-url="/places" data-fields-selector="#placesFields" data-model-id="">Nuovo luogo</a>
*		<div id="placesFields" class="fields hidden">
*			<input name="title">
*			...
*			...
*		</div>
*	</div>
*
*/

var pluginName = "creationManager",
	defaults = {
		modalTemplate: [
			'<div class="modal hide fade">',
				'<div class="modal-header">',
					'<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>',
					'<h3>Nuovo elemento</h3>',
				'</div><!--/modal-header-->',

				'<div class="modal-body">',
				'</div><!--/modal-body-->',

				'<div class="modal-footer">',
					'<button class="btn" data-dismiss="modal" aria-hidden="true">Annulla</button>',
					'<button class="btn btn-success formsubmitter">Salva</button>',
				'</div><!--/modal-footer-->',
			'</div>',
		].join(''),
		fieldContainerSelector: '.control-group', //usando control-group posso sfruttare la classe error di twitter bootstrap
		resetOnShow: true, //resetta i campi quando visualizzo il modal
		initCallback: function(){}
	};


function Plugin(element, options) {
	this.element = element;
	this.options = $.extend( {}, defaults, options);
	this._defaults = defaults;


	this.init();
}

Plugin.prototype.init = function() {
	var fieldsSelector = $(this.element).data('fields-selector') ,
		$fields = $(fieldsSelector).contents().detach();

	this.$modal = $(this.options.modalTemplate).appendTo('body');
	this.$form = $('<form/>',{ method: 'POST' }).append($fields);
	this.$modal.find('.modal-body').append(this.$form);

	this.bindEvents();

	this.options.initCallback.call(this);
};

Plugin.prototype.bindEvents = function() {
	var self = this;

	$(self.element).on('click',function(e){
		var modelID = $(this).data('model-id'),
			url = $(self.element).data('url') || '',
			action = (url && modelID) ? [url,modelID].join('/') : url
		

		self.$form.attr('action',action);
		if(self.options.resetOnShow) self.$form.find('[name]').val(''); //resetta il valore dei campi
		self.$modal.modal();


		e.preventDefault();
	});

	self.$modal.on('shown', function(e){
		$(self.element).trigger('shown');
	})
	//=============================================
	// bind modal button to form
	self.$modal.find('button.formsubmitter').on('click',function(e){
		self.$form.submit();
		e.preventDefault();
	});
	// manage form submission
	self.$form.on('submit',function(e){
		var $form = $(this),
			$fields = $form.find('[name]'),
			params = {};

		$fields.each(function(index, element) {
			$(this).parents(self.options.fieldContainerSelector).removeClass('error'); //tolgo eventuali classi di errore dal contenitore del campo
			params[this.name] = $(this).val(); //prendo il valore di ogni campo e lo savo in params
		});

		$.ajax({
			url: self.$form.attr('action'),
			data: params,
			dataType: "json",
			type: 'POST'
		}).done(function(data, textStatus, jqXHR){
			/**
			* in caso di successo chiudo il modal e innesco il trigger 'save'
			*/
			self.$modal.modal('hide');
			$(self.element).trigger('save',data);
		}).fail(function(jqXHR, textStatus, errorThrown){
			/**
			* in caso di errore durante il salvataggio li segnalo
			* gli errori sono contenuti nella variabile errors, un oggetto che ha come chiavi
			* i nomi dei campi che li contengono
			* se i campi sono contenuti in un wrapper con classe corrispondente a quella specificata
			* nell'opzione 'fieldContainerSelector', a questo viene applicata una classe error
			*/
			var errors = jqXHR.responseJSON.errors,
				error_key, error_value,
				$field, $field_container;

			for(error_key in errors) {
				error_value = errors[error_key][0]; //prendo solo il primo errore dell'array
				$field = $form.find('[name='+error_key+']').attr('placeholder', error_value);
				$field_container = $field.parents(self.options.fieldContainerSelector).addClass('error');
			}
		});

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