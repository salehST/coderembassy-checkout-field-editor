/* global CA_Frontend, jQuery */
( function ( $ ) {
	'use strict';

	var CA = window.CA_Frontend || {};

	// Track the active customer type in JS so updated_checkout can re-apply
	// the correct visibility even if WC replaces parts of the page HTML.
	var currentType = '';

	/* ---------------------------------------------------------------
	 * Apply customer-type field visibility.
	 *
	 * Custom fields: rendered with data-ca-types="slug1,slug2" on the
	 *   <p id="fieldkey_field"> wrapper. Empty = show for all types.
	 *
	 * Native WC fields: restriction map passed via CA_Frontend.native_types
	 *   e.g. { billing_company: ['company'], billing_vat: ['company'] }
	 * ------------------------------------------------------------- */
	function applyTypeVisibility( slug ) {
		if ( ! slug ) {
			return;
		}

		// 1. Custom CA fields with data-ca-types attribute.
		$( '[data-ca-types]' ).each( function () {
			var raw   = String( $( this ).attr( 'data-ca-types' ) || '' );
			var types = raw.split( ',' ).filter( Boolean );
			if ( types.length === 0 ) {
				return; // No restriction — always visible.
			}
			$( this ).toggle( types.indexOf( slug ) !== -1 );
		} );

		// 2. Native WC fields with type restrictions from PHP.
		var nativeTypes = CA.native_types || {};
		$.each( nativeTypes, function ( fieldKey, types ) {
			if ( ! types || ! types.length ) {
				return;
			}
			$( '#' + fieldKey + '_field' ).toggle( types.indexOf( slug ) !== -1 );
		} );
	}

	/* ---------------------------------------------------------------
	 * Customer type switcher — click handler.
	 * Only syncs to server (no update_checkout) — field visibility is
	 * handled entirely client-side to avoid the WC AJAX update loop.
	 * ------------------------------------------------------------- */
	$( document ).on( 'click', '.ca-type-btn', function () {
		var $btn = $( this );
		var slug = String( $btn.data( 'type' ) || '' );
		if ( ! slug ) {
			return;
		}

		currentType = slug;
		$( '.ca-type-btn' ).removeClass( 'is-active active' );
		$btn.addClass( 'is-active' );

		// Keep the hidden form input in sync so the server always knows the
		// active type during wc-ajax=checkout without relying on the session.
		$( '#ca-current-type-input' ).val( slug );

		// Apply visibility immediately — no page flash or waiting for server.
		applyTypeVisibility( slug );

		// Sync customer type to server session (fire-and-forget).
		$.post( CA.ajax_url, {
			action:         'ca_set_customer_type',
			type:           slug,
			_ca_type_nonce: CA.type_nonce,
		} );
	} );

	/* ---------------------------------------------------------------
	 * On page load — apply visibility for the initially-active type.
	 * Also re-apply after any WC checkout update so that fragments
	 * rebuilt by WC do not reveal fields that should be hidden.
	 * ------------------------------------------------------------- */
	$( function () {
		var $active = $( '.ca-type-btn.is-active' );
		if ( $active.length ) {
			currentType = String( $active.data( 'type' ) || '' );
		}
		if ( currentType ) {
			applyTypeVisibility( currentType );
		}

		// Re-apply after WC rebuilds order-review fragments.
		// Use currentType (not the DOM) to be safe if WC replaced the switcher HTML.
		$( document.body ).on( 'updated_checkout', function () {
			if ( currentType ) {
				applyTypeVisibility( currentType );
			}
			applyHiddenFields();
		} );
	} );

	/* ---------------------------------------------------------------
	 * Custom price field — trigger fee recalculation on input.
	 * ------------------------------------------------------------- */
	var customPriceTimer;
	$( document ).on( 'input', '[data-field-type="custom_price"]', function () {
		clearTimeout( customPriceTimer );
		customPriceTimer = setTimeout( function () {
			$( 'body' ).trigger( 'update_checkout' );
		}, 500 );
	} );

	/* ---------------------------------------------------------------
	 * Hide server-rendered hidden fields (conditional logic).
	 * ------------------------------------------------------------- */
	function applyHiddenFields() {
		$( '.ca-field-hidden' ).closest( '.form-row' ).hide();
	}

	applyHiddenFields();

}( jQuery ) );
