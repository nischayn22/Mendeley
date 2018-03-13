/**
 * @author Nischay Nahata
 */

jQuery(document).ready( function() {
	$(".mendeley_input").autocomplete({
		source: function( request, response ) {
			 $.ajax({
				url: wgScriptPath + '/api.php?action=pfmendeley&format=json',
				dataType: "json",
				data: request,
				success: function(data){
					response(data.result.autcomplete_results);
				}
			});
		},
		minLength: 2,
		select: function(event, ui) {
			$(this).val(ui.item.full_label);
			$(this).parent().find('.menedeley_id_input').val(ui.item.id);
		}
	});
});