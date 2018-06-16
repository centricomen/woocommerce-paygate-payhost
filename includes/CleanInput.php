<?php

	if( ! class_exists( 'CleanInput' ) ) {

		class CleanInput {


			public static function validCardNumber ( $cardNumber ) {
				$number		= str_replace( ' ', '', $cardNumber );
				$number		= trim( $number );

				if( strlen( $number ) < 16 )
					return false;

				if( ! is_numeric( $number ) )
					return false;

				# format should be 16 digits with no spaces or special characters
				return $number;
			}


			public static function validCardExpiry ( $cardExpiry ) {
				$expiry		= '';
				if( ! empty( $cardExpiry ) ) {
					if( strpos( $cardExpiry, '/') !== false ) {
						$expiry		= explode( '/', $cardExpiry );
						$numberValidation	= true;
						foreach ( $expiry as $expiryNumber ) {
							if( is_numeric( $expiryNumber ) && strlen( $expiryNumber ) < 2 ) {
								$numberValidation = false;
								break;
							}
						}

						if( $numberValidation == false )
							return -1;

						$expiry[1]	= '20' . trim( $expiry[1] );

						# check if the year is in the future
						if( intval( date('Y') ) > intval( $expiry[1] ) )
							return -2;

						$expiry		= implode( '', $expiry );
						$expiry		= str_replace( ' ', '', $expiry );
						$expiry		= trim( $expiry );
					} else return false;
				}

				# format should be MMYYYY

				return $expiry;
			}


			public static function validCardCVV ( $cardCVV ) {
				$cvv		= str_replace( ' ', '', $cardCVV );
				$cvv		= trim( $cvv );

				if( strlen( $cvv ) < 3 )
					return false;

				if( ! is_numeric( $cvv ) )
					return false;

				# format should be 3 or 4 digits with no spaces or special characters
				return $cvv;
			}


		}
		
	}
