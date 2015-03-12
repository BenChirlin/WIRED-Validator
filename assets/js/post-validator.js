// Admin JS just for editing post singles

(function($) {

	// Subtract input value length from limit and update target counter and classes accordingly
	function checkLength( input, target, min, limit, label ) {
		// Check limit
		// Subtract number of chars in HTML stripped input value from the limit
		var inputLength = $( '<p>' + $( input ).val() + '</p>' ).text().length;
		var charLeft = limit - inputLength;
		var limitTarget = $( target ).find( '.char-counter' );
		limitTarget.html( charLeft );
		if ( charLeft < 0 ) {
			limitTarget.addClass( 'error' );
			$( '#publish' ).addClass( 'disabled' );
			// Add notice at top of edit screen if not already there
			if ( $( '#wpcontent .over-' + label ).length === 0 ) {
				$( '<div class="error over-' + label + '" id="notice"><p>Your ' + label + ' has exceeded the character limit. Please shorten it before you save or publish.</p></div>' ).insertAfter( '#wpcontent h2' );
			}
			return;
		}
		else {
			limitTarget.removeClass( 'error' );
			$( '#publish' ).removeClass( 'disabled' );
			$( '#wpcontent .over-' + label ).remove();
		}

		// Check min
		if ( inputLength < min ) {
			limitTarget.addClass( 'error' );
			$( '#publish' ).addClass( 'disabled' );
			// Add notice at top of edit screen if not already there
			if ( $( '#wpcontent .under-' + label ).length === 0 ) {
				$( '<div class="error under-' + label + '" id="notice"><p>Your ' + label + ' is below the character minimum. Please lengthen it before you save or publish.</p></div>' ).insertAfter( '#wpcontent h2' );
			}
			return;
		}
		else {
			limitTarget.removeClass( 'error' );
			$( '#publish' ).removeClass( 'disabled' );
			$( '#wpcontent .under-' + label ).remove();
		}
	}

	$(document).ready(function() {
		// Default JS limits for title and excerpt length on posts for cards
		var titleValidate = false;
		var titleMin = 20;
		var titleLimit = 80;
		var excerptValidate = false;
		var excerptMin = 40;
		var excerptLimit = 140;

		// Check for limits options from wp_localize_script in admin.php
		if ( limitopts ) {
			titleValidate = 'checked' === limitopts.title_validate ? true : false;
			titleMin = limitopts.title_min;
			titleLimit = limitopts.title_limit;
			excerptValidate = 'checked' === limitopts.excerpt_validate ? true : false;
			excerptMin = limitopts.excerpt_min;
			excerptLimit = limitopts.excerpt_limit;
		}

		if ( $( '#post' ).length ) {
			// Update length count on title
			// Check on load, then on every keypress
			if ( titleValidate ) {
				$( '#titlewrap' ).append('<span class="char-counter">' + titleLimit + '</span>');

				checkLength( '#titlediv #title', '#titlewrap', titleMin, titleLimit, 'title' );
				$( '#titlediv #title' ).on( 'input', function() {
					checkLength( this, '#titlewrap', titleMin, titleLimit, 'title' );
				} );
			}

			// Update length count on excerpt
			// Check on load, then on every keypress
			if ( excerptValidate ) {
				$( '#postexcerpt .inside' ).append('<span class="char-counter">' + excerptLimit + '</span>');

				checkLength( '#postexcerpt #excerpt', '#postexcerpt .inside', excerptMin, excerptLimit, 'excerpt' );
				$( '#postexcerpt #excerpt' ).on( 'input', function() {
					checkLength( this, '#postexcerpt .inside', excerptMin, excerptLimit, 'excerpt' );
				} );
			}
		}
	});
})(jQuery);
