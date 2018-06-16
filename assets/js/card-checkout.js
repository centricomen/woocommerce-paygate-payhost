( function( $ ) {
	
	$( document ).ready( function() {
		
		
		var errors = '';
		var appendCheckoutCardErrors	= function ( message ) {
			errors += '<li>' + message + '</li>';
		};
		
		var formatCardInput				= function ( input ) {
			input = input.split( ' ' ).join( '' );
			input = input.trim();
			
			return input;
		};
		
		$( 'form.checkout' ).on( 'checkout_place_order', function() {
			var formValues		= $( this ).serializeArray();
			//console.log( formValues );
			
			if( typeof formValues.paygatenonce !== 'undefined' ) {
				/*errors = '';
				var cardExpiry = formatCardInput ( $( '#paygate_payhost-card-expiry' ) .val() );
				cardExpiry = cardExpiry.split( '/' );
				
				var validCardCVV		= $.payment.validateCardCVC( formatCardInput ( $( '#paygate_payhost-card-cvv' ) .val() ) );
				var validCardNumber		= $.payment.validateCardNumber( formatCardInput ( $( '#paygate_payhost-card-number' ) .val() ) );
				var validCardExpiry		= $.payment.validateCardExpiry( cardExpiry[0], cardExpiry[1] );
				
				if( ! validCardNumber )
					appendCheckoutCardErrors( 'Credit card number is invalid' );
					
				if( ! validCardExpiry )
					appendCheckoutCardErrors( 'Credit card expiry date must be a valid future date' );
					
				if( ! validCardCVV )
					appendCheckoutCardErrors( 'Credit card CVV invalid' );
				
				if( validCardNumber && validCardExpiry && validCardCVV ) {
					return true;
				} else {
					$( '#paygate-errors' ).remove();
					$( this ).prepend( '<div class="woocommerce-error" id="paygate-errors"><ul>' + errors + '</ul></div>' );
					$( 'html, body' ).animate({
						scrollTop		: ( $( '#paygate-errors' ).offset().top - $( '#paygate-errors' ).height() )
					});
					
					return false;
				}*/
			}
			
		});
		
		
		
	} );

}) ( jQuery );
