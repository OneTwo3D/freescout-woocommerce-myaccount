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
	// Helpers
	// -------------------------------------------------------------------------
	function escHtml( str ) {
		return $( '<span>' ).text( str ).html();
	}

} )( jQuery );
