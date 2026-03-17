/**
 * FreeScout WooCommerce My Account – frontend JavaScript.
 *
 * Handles:
 *  - AJAX submission of the reply form.
 *  - AJAX submission of the new-ticket form.
 */
/* global jQuery, fswa */
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Reply form
	// -------------------------------------------------------------------------
	$( document ).on( 'submit', '#fswa-reply', function ( e ) {
		e.preventDefault();

		var $form   = $( this );
		var $submit = $form.find( '#fswa-reply-submit' );
		var $body   = $form.find( '#fswa-reply-body' );
		var $ok     = $( '#fswa-reply-success' );
		var $err    = $( '#fswa-reply-error' );

		// Hide previous notices.
		$ok.hide();
		$err.hide();

		if ( ! $.trim( $body.val() ) ) {
			$err.text( fswa.i18n.emptyReply ).show();
			$body.focus();
			return;
		}

		$submit.prop( 'disabled', true ).text( fswa.i18n.sending );

		$.ajax( {
			url     : fswa.ajaxUrl,
			type    : 'POST',
			data    : $form.serialize(),
			dataType: 'json',
		} ).done( function ( response ) {
			if ( response.success ) {
				$body.val( '' );
				$ok.text( response.data.message ).show();

				// Append the new message to the thread.
				if ( response.data.thread ) {
					appendThread( response.data.thread );
				}

				// Scroll thread into view.
				var $thread = $( '#fswa-thread' );
				if ( $thread.length ) {
					$( 'html, body' ).animate( {
						scrollTop: $thread.offset().top,
					}, 400 );
				}
			} else {
				$err.text( ( response.data && response.data.message ) || fswa.i18n.replyError ).show();
			}
		} ).fail( function () {
			$err.text( fswa.i18n.replyError ).show();
		} ).always( function () {
			$submit.prop( 'disabled', false ).text( fswa.i18n.send );
		} );
	} );

	/**
	 * Append a newly created thread item to the thread container.
	 *
	 * @param {Object} thread  Thread object returned from the API.
	 */
	function appendThread( thread ) {
		var $thread = $( '#fswa-thread' );
		if ( ! $thread.length ) {
			return;
		}

		// Remove empty-state paragraph if present.
		$thread.find( '.fswa-thread__empty' ).remove();

		var body = thread.body || '';
		var date = thread.createdAt
			? new Date( thread.createdAt ).toLocaleString()
			: '';

		var html = '<div class="fswa-message fswa-message--customer">'
			+ '<div class="fswa-message__meta">'
			+ '<span class="fswa-message__author">' + escHtml( thread.createdBy && thread.createdBy.firstName ? thread.createdBy.firstName : '' ) + '</span>'
			+ ( date ? '<span class="fswa-message__date">' + escHtml( date ) + '</span>' : '' )
			+ '</div>'
			+ '<div class="fswa-message__body">' + body + '</div>'
			+ '</div>';

		$thread.append( html );
	}

	// -------------------------------------------------------------------------
	// New ticket form
	// -------------------------------------------------------------------------
	$( document ).on( 'submit', '#fswa-new-ticket-form', function ( e ) {
		e.preventDefault();

		var $form   = $( this );
		var $submit = $( '#fswa-new-ticket-submit' );
		var $ok     = $( '#fswa-new-ticket-success' );
		var $err    = $( '#fswa-new-ticket-error' );

		$ok.hide();
		$err.hide();

		$submit.prop( 'disabled', true ).text( fswa.i18n.sending );

		$.ajax( {
			url     : fswa.ajaxUrl,
			type    : 'POST',
			data    : $form.serialize(),
			dataType: 'json',
		} ).done( function ( response ) {
			if ( response.success ) {
				$ok.text( response.data.message ).show();

				// Redirect to the new ticket (or list) after a short delay.
				if ( response.data.redirect ) {
					setTimeout( function () {
						window.location.href = response.data.redirect;
					}, 1200 );
				}
			} else {
				$err.text( ( response.data && response.data.message ) || fswa.i18n.replyError ).show();
				$submit.prop( 'disabled', false ).text( fswa.i18n.send );
			}
		} ).fail( function () {
			$err.text( fswa.i18n.replyError ).show();
			$submit.prop( 'disabled', false ).text( fswa.i18n.send );
		} );
	} );

	// -------------------------------------------------------------------------
	// -------------------------------------------------------------------------
	// Knowledge Base live-search autocomplete
	// -------------------------------------------------------------------------
	var kbSearchTimer = null;
	var kbActiveIndex = -1;

	$( document ).on( 'input', '#fswa-kb-search-input', function () {
		var $input       = $( this );
		var $suggestions = $( '#fswa-kb-suggestions' );
		var query        = $.trim( $input.val() );

		clearTimeout( kbSearchTimer );
		kbActiveIndex = -1;

		if ( query.length < 2 ) {
			hideSuggestions( $suggestions );
			return;
		}

		kbSearchTimer = setTimeout( function () {
			$.ajax( {
				url     : fswa.ajaxUrl,
				type    : 'POST',
				dataType: 'json',
				data    : {
					action : 'fswa_kb_search',
					nonce  : fswa.nonce,
					query  : query,
				},
			} ).done( function ( response ) {
				if ( ! response.success || ! response.data.articles.length ) {
					hideSuggestions( $suggestions );
					return;
				}

				var items = '';
				$.each( response.data.articles, function ( i, article ) {
					items += '<li class="fswa-kb-search__suggestion" role="option" id="fswa-kb-suggestion-' + i + '">'
						+ '<a href="' + escHtml( article.url ) + '">'
						+ '<svg class="fswa-kb-search__suggestion-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'
						+ escHtml( article.title )
						+ '</a></li>';
				} );

				$suggestions.html( items ).removeAttr( 'hidden' );
				$input.attr( 'aria-expanded', 'true' );
			} ).fail( function () {
				hideSuggestions( $suggestions );
			} );
		}, 280 );
	} );

	// Keyboard navigation within suggestions.
	$( document ).on( 'keydown', '#fswa-kb-search-input', function ( e ) {
		var $suggestions = $( '#fswa-kb-suggestions' );
		var $items       = $suggestions.find( '.fswa-kb-search__suggestion' );
		var count        = $items.length;

		if ( ! count ) {
			return;
		}

		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			kbActiveIndex = ( kbActiveIndex + 1 ) % count;
			updateActiveSuggestion( $items );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			kbActiveIndex = ( kbActiveIndex - 1 + count ) % count;
			updateActiveSuggestion( $items );
		} else if ( e.key === 'Enter' && kbActiveIndex >= 0 ) {
			e.preventDefault();
			var href = $items.eq( kbActiveIndex ).find( 'a' ).attr( 'href' );
			if ( href ) {
				window.location.href = href;
			}
		} else if ( e.key === 'Escape' ) {
			hideSuggestions( $suggestions );
		}
	} );

	// Close suggestions on outside click.
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#fswa-kb-search-wrap, .fswa-kb-search' ).length ) {
			hideSuggestions( $( '#fswa-kb-suggestions' ) );
		}
	} );

	function hideSuggestions( $list ) {
		$list.attr( 'hidden', '' ).empty();
		$( '#fswa-kb-search-input' ).removeAttr( 'aria-expanded' );
		kbActiveIndex = -1;
	}

	function updateActiveSuggestion( $items ) {
		$items.removeClass( 'fswa-kb-search__suggestion--active' );
		if ( kbActiveIndex >= 0 ) {
			$items.eq( kbActiveIndex ).addClass( 'fswa-kb-search__suggestion--active' );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------
	function escHtml( str ) {
		return $( '<span>' ).text( str ).html();
	}

} )( jQuery );
