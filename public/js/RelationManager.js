;(function($, window, document, undefined){
/**
* @AUTHOR Francesco Delacqua
*
* gestione delle relazioni tra oggetti:
*
*/

var pluginName = "relationManager",
	defaults = {
		buttonText: ' nuova relazione', //the text to use in the button
		relationBoxSelector: '.relation.element',
		newRelationTriggerSelector: 'a.add-relation',
		newRelationButtonTemplate: '<a href="#" class="btn btn-info btn-small add-relation"><i class="icon-plus icon-white"></i></a>',
		standardWrapperClass: 'alert-info', //class applicata ai box wrapped con wrapIncluded
		relationTemplate: [
			'<div class="relation author">',
				'<input type="text" name="elements[0][pivot][riferimento]" placeholder="riferimento" class="input-xlarge">',
				'<input type="text" name="elements[0][id]" class="remotecombo input-xlarge" data-url="#">',
			'</div>'
		].join(''),
		counterReGExp: /^(.*)\[0\]/i,
		relationWrapperTemplate: [
			'<div class="alert alert-block">',
				'<button type="button" class="close" data-dismiss="alert">&times;</button>',
			'</div>'
		].join(''),
		wrapCallback: function(element){} //funzione eseguita dopo ogni wrap
	};

function Plugin(element, options) {
	this.element = element;
	this.options = $.extend( {}, defaults, options);
	this._defaults = defaults;

	this.init();
}

Plugin.prototype.init = function() {
	var boxes;

	/* ADD TITLE AND BUTTON */
	this.$newRelationButton = $(this.options.newRelationButtonTemplate)
								.css({'margin-top': '3px', 'margin-bottom': '3px'})
								.append(this.options.buttonText)
								.prependTo(this.element);

	this.bindEvents();
	//if(this.options.autoWrap) this.wrapIncluded();
};

Plugin.prototype.bindEvents = function() {
	var self = this;

	self.$newRelationButton.on('click',function(e){
		self.addRelation();
		e.preventDefault();
	});

};

/*
* imposta l'indice della relazione
* - esempio di indice di relazione iniziale: authors[0][id]
* - esempio di indice di relazione modificato: authors[new_1370440760007][id]
*/
function setRelationID (element, id, regExp) {
	var self = this,
		childName,
		replaceFunction;

		replaceFunction = function(match, p1, offset, string) {
			var relationID = '[new_'+id+']';
			return p1+relationID;
		};

		childName = $(element).attr('name');
		childName = childName.replace(regExp, replaceFunction);
		$(element).attr('name', childName);
}

/*
* crea un nuovo box di relazione
*/
Plugin.prototype.addRelation = function() {
	var self = this,
		$relationBox = $(self.options.relationTemplate),
		$relationChildren,
		randomID = new Date().getTime();

	$relationChildren = $relationBox.children();
	$relationChildren.each(function(index, element){
		setRelationID(element, randomID, self.options.counterReGExp);
	});

	self.wrap($relationBox);
};

// wraps the passed element with a modal closable box
Plugin.prototype.wrap = function($elements, wrapClass) {
	var self = this,
		$relationWrapper;

	$elements.each(function(index, element){
		$relationWrapper = $(self.options.relationWrapperTemplate);
		if (wrapClass) $relationWrapper.addClass(wrapClass);
		$relationWrapper.append(element);
		// $relationWrapper.insertAfter(self.$header);
		$relationWrapper.appendTo(self.element);

		//trigger the wrapped event
		$(self.element).trigger('modelmanager.wrapped', $relationWrapper);
		self.options.wrapCallback($relationWrapper);
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