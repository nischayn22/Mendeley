/**
 * @author Nischay Nahata
 */

jQuery(document).ready( function() {
    // Default tooltip position.
    var ttpos = $.ui.tooltip.prototype.options.position;
	var results = {};

    // Autocomplete widget extension to provide description
    // tooltips.
	$.widget( "app.autocomplete", $.ui.autocomplete, {

		_create: function() {

			this._super();
            
            // After the menu has been created, apply the tooltip
            // widget. The "items" option selects menu items with
            // a title attribute, the position option moves the tooltip
            // to the right of the autocomplete dropdown.
            this.menu.element.tooltip({
                items: "li",
                position: $.extend( {}, ttpos, {
                    my: "left+12",
                    at: "right"
                }),
				content: function() {
					var tooltipHtml = '';
					curr_id = $(this).attr('id');
					$.each(results, function (index, item) {
						if ( curr_id == item.id ) {
							tooltipHtml = '<div><h4>'+ item.value +'</h4>Authors: '+ item.authors +'<br>Year: '+ item.year +'<br><br><b>Abstract:</b><br><p>'+ item.abstract +'</p></div>';
							return false;
						}
					});
					return tooltipHtml;
				}
            });
        },

		// Clean up the tooltip widget when the autocomplete is
        // destroyed.
        _destroy: function() {
            this.menu.element.tooltip( "destroy" );
            this._super();
        },

        // Set the title attribute as the "item.desc" value.
        // This becomes the tooltip content.
        _renderItem: function( ul, item ) {
            return this._super( ul, item )
                .attr( "id", item.id );
        }
    });

	$(".mendeley_input").autocomplete({
		search: function( event, ui ) {
			$( ".mendeley_input" ).addClass( 'loading' );
		},
		source: function( request, response ) {
			 $.ajax({
				url: wgScriptPath + '/api.php?action=pfmendeley&format=json',
				dataType: "json",
				data: request,
				success: function(data){
					results = data.result.autocomplete_results;
					response(data.result.autocomplete_results);
					$( ".mendeley_input" ).removeClass( 'loading' );
				}
			});
		},
		minLength: 2,
		select: function(event, ui) {
			$('.menedeley_id_input').val(ui.item.id);
		}
	});
});