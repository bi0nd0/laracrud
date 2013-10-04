{{ HTML::style( URL::asset('packages/whitegolem/laracrud/js/vendors/select2-3.4.0/select2.css') ) }}
{{ HTML::script( URL::asset('packages/whitegolem/laracrud/js/vendors/select2-3.4.0/select2.js') ) }}
{{ HTML::script( URL::asset('packages/whitegolem/laracrud/js/DeleteManager.js') ) }}
{{ HTML::script( URL::asset('packages/whitegolem/laracrud/js/RemoteCombo.js') ) }}
{{ HTML::script( URL::asset('packages/whitegolem/laracrud/js/RelationManager.js') ) }}
{{ HTML::script( URL::asset('packages/whitegolem/laracrud/js/CreationManager.js') ) }}
<script>
(function($){
	$(function(){
		/**
		* chede conferma ed elimina un elemento
		*/
		$('.delete_trigger').deleteManager({});
	});
})(jQuery);
</script>
