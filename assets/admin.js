(function () {
	'use strict';

	var btn       = document.getElementById( 'wpvigie-run-scan' );
	var resultsEl = document.getElementById( 'wpvigie-results' );

	if ( ! btn || ! resultsEl ) {
		return;
	}

	btn.addEventListener( 'click', function () {
		var originalText = btn.textContent;

		btn.disabled    = true;
		btn.textContent = WPVigieData.i18n.running;
		resultsEl.innerHTML = '';

		var body = new FormData();
		body.append( 'action', 'wpvigie_run_scan' );
		body.append( 'nonce', WPVigieData.nonce );

		fetch( WPVigieData.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			body:        body,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json.success ) {
					throw new Error( 'scan_failed' );
				}
				renderResults( json.data );
			} )
			.catch( function () {
				resultsEl.innerHTML =
					'<p class="notice notice-error" style="padding:8px 12px">' +
					esc( WPVigieData.i18n.error ) +
					'</p>';
			} )
			.finally( function () {
				btn.disabled    = false;
				btn.textContent = originalText;
			} );
	} );

	// Escape a value for safe insertion into HTML.
	function esc( value ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( String( value ) ) );
		return div.innerHTML;
	}

	function severityIcon( severity ) {
		if ( 'pass' === severity ) { return '✓'; } // ✓
		if ( 'warn' === severity ) { return '⚠'; } // ⚠
		return '✗';                                // ✗
	}

	function renderResults( data ) {
		var score = data.score;
		var scoreClass = 'fail';
		if ( score >= 80 ) {
			scoreClass = 'pass';
		} else if ( score >= 50 ) {
			scoreClass = 'warn';
		}

		var html =
			'<div class="wpvigie-score wpvigie-score--' + esc( scoreClass ) + '"' +
			' role="img" aria-label="Score ' + esc( score ) + ' out of 100">' +
			'<span class="wpvigie-score__number">' + esc( score ) + '</span>' +
			'<span class="wpvigie-score__total">/ 100</span>' +
			'</div>';

		html += '<div class="wpvigie-checks">';

		data.results.forEach( function ( check ) {
			html +=
				'<div class="wpvigie-check wpvigie-check--' + esc( check.severity ) + '">' +
				'<span class="wpvigie-check__icon" aria-hidden="true">' + severityIcon( check.severity ) + '</span>' +
				'<div class="wpvigie-check__body">' +
				'<strong class="wpvigie-check__title">' + esc( check.title ) + '</strong>' +
				'<span class="wpvigie-check__value">' + esc( check.value ) + '</span>';

			if ( check.recommendation ) {
				html += '<p class="wpvigie-check__rec">' + esc( check.recommendation ) + '</p>';
			}

			html += '</div></div>';
		} );

		html += '</div>';
		resultsEl.innerHTML = html;
	}

}() );
