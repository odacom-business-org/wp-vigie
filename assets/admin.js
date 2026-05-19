/* global WPVigieData */
( function () {
	'use strict';

	var CIRCUMFERENCE  = 2 * Math.PI * 54; // 339.292 — matches SVG r="54"
	var reducedMotion  = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* -------------------------------------------------------------------------
	   Utilities
	   ---------------------------------------------------------------------- */

	function esc( val ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( String( val ) ) );
		return d.innerHTML;
	}

	function mkSVG( tag, attrs ) {
		var el = document.createElementNS( 'http://www.w3.org/2000/svg', tag );
		Object.keys( attrs ).forEach( function ( k ) { el.setAttribute( k, attrs[ k ] ); } );
		return el;
	}

	function severityIcon( s ) {
		return s === 'pass' ? '✓' : s === 'warn' ? '⚠' : '✗';
	}

	function scoreClass( n ) {
		return n >= 80 ? 'pass' : n >= 50 ? 'warn' : 'fail';
	}

	function scoreColor( n ) {
		return n >= 80 ? '#10b981' : n >= 50 ? '#f59e0b' : '#ef4444';
	}

	function i18n( key, fallback ) {
		return ( WPVigieData.i18n && WPVigieData.i18n[ key ] ) || fallback;
	}

	/* -------------------------------------------------------------------------
	   Tabs
	   ---------------------------------------------------------------------- */

	var Tabs = ( function () {
		var tabs = [], panels = [];

		function switchTo( id ) {
			tabs.forEach( function ( tab ) {
				tab.setAttribute( 'aria-selected', String( tab.dataset.tab === id ) );
			} );
			panels.forEach( function ( panel ) {
				if ( panel.id === 'wpvigie-tab-' + id ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', '' );
				}
			} );
		}

		function init() {
			var list = document.querySelector( '.wpvigie-tabs' );
			if ( ! list ) { return; }
			tabs   = Array.from( list.querySelectorAll( '[role="tab"]' ) );
			panels = Array.from( document.querySelectorAll( '.wpvigie-tab-panel' ) );
			tabs.forEach( function ( tab ) {
				tab.addEventListener( 'click', function () { switchTo( tab.dataset.tab ); } );
			} );
		}

		return { init: init, switchTo: switchTo };
	}() );

	/* -------------------------------------------------------------------------
	   Counter — 0 → target over duration ms
	   ---------------------------------------------------------------------- */

	var Counter = ( function () {
		function animate( el, target, duration ) {
			if ( reducedMotion ) { el.textContent = target; return; }
			var start = null;
			function step( ts ) {
				if ( ! start ) { start = ts; }
				var p = Math.min( ( ts - start ) / duration, 1 );
				el.textContent = Math.round( target * p );
				if ( p < 1 ) { requestAnimationFrame( step ); }
			}
			requestAnimationFrame( step );
		}
		return { animate: animate };
	}() );

	/* -------------------------------------------------------------------------
	   ScoreRing
	   ---------------------------------------------------------------------- */

	var ScoreRing = ( function () {
		var progressEl, numberEl, statusEl, timeEl;

		function init() {
			progressEl = document.querySelector( '.wpvigie-score__progress' );
			numberEl   = document.querySelector( '.wpvigie-score__number' );
			statusEl   = document.getElementById( 'wpvigie-score-status' );
			timeEl     = document.getElementById( 'wpvigie-score-time' );
		}

		function update( score, timeLabel ) {
			if ( ! progressEl || ! numberEl ) { return; }

			var color  = scoreColor( score );
			var offset = CIRCUMFERENCE * ( 1 - score / 100 );

			progressEl.style.stroke = color;

			if ( reducedMotion ) {
				progressEl.style.strokeDashoffset = offset;
				numberEl.textContent = score;
			} else {
				numberEl.textContent = '0';
				progressEl.style.transition = 'stroke-dashoffset 1500ms cubic-bezier(0.16, 1, 0.3, 1)';
				void progressEl.getBoundingClientRect(); // force reflow
				progressEl.style.strokeDashoffset = offset;
				Counter.animate( numberEl, score, 1500 );
			}

			if ( statusEl ) {
				statusEl.classList.remove( 'wpvigie-score__status--pass', 'wpvigie-score__status--warn', 'wpvigie-score__status--fail' );
				statusEl.classList.add( 'wpvigie-score__status--' + scoreClass( score ) );
				var labels = { pass: i18n( 'healthy', 'Healthy' ), warn: i18n( 'needsAttention', 'Needs attention' ), fail: i18n( 'critical', 'Critical' ) };
				statusEl.textContent = labels[ scoreClass( score ) ] || '';
			}
			if ( timeEl && timeLabel ) {
				timeEl.textContent = timeLabel;
			}
		}

		return { init: init, update: update };
	}() );

	/* -------------------------------------------------------------------------
	   Cards
	   ---------------------------------------------------------------------- */

	var Cards = ( function () {
		var container;

		function init() {
			container = document.getElementById( 'wpvigie-cards' );
		}

		function render( results ) {
			if ( ! container ) { return; }
			container.innerHTML = results.map( function ( check ) {
				var icon = severityIcon( check.severity );
				var rec  = check.recommendation
					? '<p class="wpvigie-card__rec">' + esc( check.recommendation ) + '</p>'
					: '';
				return (
					'<div class="wpvigie-card wpvigie-card--' + esc( check.severity ) + '">' +
					'<div class="wpvigie-card__head">' +
					'<span class="wpvigie-card__icon" aria-hidden="true">' + icon + '</span>' +
					'<h3 class="wpvigie-card__title">' + esc( check.title ) + '</h3>' +
					'</div>' +
					'<div class="wpvigie-card__value">' + esc( check.value ) + '</div>' +
					rec +
					'</div>'
				);
			} ).join( '' );
		}

		return { init: init, render: render };
	}() );

	/* -------------------------------------------------------------------------
	   Chart — hand-rolled SVG line chart, no libraries
	   ---------------------------------------------------------------------- */

	var Chart = ( function () {
		var chartEl;
		var VB_W = 800, VB_H = 180;
		var PAD  = { top: 16, right: 16, bottom: 32, left: 36 };
		var GRID_SCORES = [ 0, 50, 80, 100 ];

		function init() {
			chartEl = document.getElementById( 'wpvigie-chart' );
		}

		function render( data ) {
			if ( ! chartEl ) { return; }
			chartEl.innerHTML = '';
			chartEl.setAttribute( 'viewBox', '0 0 ' + VB_W + ' ' + VB_H );

			if ( ! data || data.length === 0 ) {
				var msg = mkSVG( 'text', { x: VB_W / 2, y: VB_H / 2, 'text-anchor': 'middle', fill: '#94a3b8', 'font-size': '14' } );
				msg.textContent = i18n( 'noHistory', 'No scans yet.' );
				chartEl.appendChild( msg );
				return;
			}

			var cW = VB_W - PAD.left - PAD.right;
			var cH = VB_H - PAD.top  - PAD.bottom;

			var xOf = function ( i ) {
				return PAD.left + ( data.length > 1 ? ( i / ( data.length - 1 ) ) : 0.5 ) * cW;
			};
			var yOf = function ( score ) {
				return PAD.top + cH - ( score / 100 ) * cH;
			};

			// Grid lines + Y labels
			GRID_SCORES.forEach( function ( val ) {
				var y = yOf( val );
				chartEl.appendChild( mkSVG( 'line', { x1: PAD.left, y1: y, x2: VB_W - PAD.right, y2: y, stroke: '#e2e8f0', 'stroke-width': '1' } ) );
				var lbl = mkSVG( 'text', { x: PAD.left - 6, y: y + 4, 'text-anchor': 'end', fill: '#94a3b8', 'font-size': '11' } );
				lbl.textContent = val;
				chartEl.appendChild( lbl );
			} );

			// X-axis labels from actual first/last data dates; deduplicate for single point.
			( data.length === 1 ? [ 0 ] : [ 0, data.length - 1 ] ).forEach( function ( idx, arrIdx ) {
				if ( ! data[ idx ] ) { return; }
				var d      = new Date( data[ idx ].date.replace( ' ', 'T' ) + 'Z' );
				var str    = d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
				var anchor = data.length === 1 ? 'middle' : ( arrIdx === 0 ? 'start' : 'end' );
				var x      = data.length === 1 ? ( VB_W / 2 ) : xOf( idx );
				var lbl    = mkSVG( 'text', { x: x, y: VB_H - 6, 'text-anchor': anchor, fill: '#94a3b8', 'font-size': '11' } );
				lbl.textContent = str;
				chartEl.appendChild( lbl );
			} );

			// Pixel points
			var pts = data.map( function ( d, i ) {
				return { x: xOf( i ), y: yOf( d.score ), score: d.score, date: d.date };
			} );

			// Smooth path (cubic bezier, horizontal control points)
			var smooth = buildPath( pts );

			// Fill area
			var fillD = smooth + ' L ' + pts[ pts.length - 1 ].x + ',' + ( VB_H - PAD.bottom ) + ' L ' + pts[ 0 ].x + ',' + ( VB_H - PAD.bottom ) + ' Z';
			chartEl.appendChild( mkSVG( 'path', { d: fillD, fill: 'rgba(37,99,235,0.08)', stroke: 'none' } ) );

			// Line
			var lineEl = mkSVG( 'path', { d: smooth, fill: 'none', stroke: '#2563eb', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' } );
			chartEl.appendChild( lineEl );

			// Draw animation via stroke-dashoffset
			if ( ! reducedMotion && lineEl.getTotalLength ) {
				var len = lineEl.getTotalLength();
				if ( len > 0 ) {
					lineEl.setAttribute( 'stroke-dasharray', len );
					lineEl.setAttribute( 'stroke-dashoffset', len );
					lineEl.style.transition = 'stroke-dashoffset 800ms ease-out';
					requestAnimationFrame( function () { lineEl.style.strokeDashoffset = '0'; } );
				}
			}

			// Hover dots + tooltip
			var tooltip = getTooltip();
			pts.forEach( function ( pt ) {
				chartEl.appendChild( mkSVG( 'circle', { cx: pt.x, cy: pt.y, r: '3', fill: '#2563eb' } ) );
				var hit = mkSVG( 'circle', { cx: pt.x, cy: pt.y, r: '14', fill: 'transparent', style: 'cursor:default' } );
				hit.addEventListener( 'mouseenter', function () {
					var ds = new Date( pt.date ).toLocaleDateString( undefined, { month: 'short', day: 'numeric', year: 'numeric' } );
					tooltip.textContent = pt.score + ' — ' + ds;
					tooltip.style.display = 'block';
				} );
				hit.addEventListener( 'mouseleave', function () { tooltip.style.display = 'none'; } );
				hit.addEventListener( 'mousemove', function ( e ) {
					var r = chartEl.getBoundingClientRect();
					tooltip.style.left = ( e.clientX - r.left + 14 ) + 'px';
					tooltip.style.top  = ( e.clientY - r.top  - 36 ) + 'px';
				} );
				chartEl.appendChild( hit );
			} );
		}

		function buildPath( pts ) {
			if ( pts.length === 0 ) { return ''; }
			if ( pts.length === 1 ) { return 'M ' + pts[ 0 ].x + ' ' + pts[ 0 ].y; }
			var d = 'M ' + pts[ 0 ].x + ',' + pts[ 0 ].y;
			for ( var i = 1; i < pts.length; i++ ) {
				var p  = pts[ i - 1 ];
				var c  = pts[ i ];
				var mx = ( p.x + c.x ) / 2;
				d += ' C ' + mx + ',' + p.y + ' ' + mx + ',' + c.y + ' ' + c.x + ',' + c.y;
			}
			return d;
		}

		function getTooltip() {
			var wrap = chartEl.parentElement;
			var tt   = wrap.querySelector( '.wpvigie-chart-tooltip' );
			if ( ! tt ) {
				tt = document.createElement( 'div' );
				tt.className = 'wpvigie-chart-tooltip';
				wrap.appendChild( tt );
			}
			return tt;
		}

		return { init: init, render: render };
	}() );

	/* -------------------------------------------------------------------------
	   History Table
	   ---------------------------------------------------------------------- */

	var HistoryTable = ( function () {
		var container;

		function init() {
			container = document.getElementById( 'wpvigie-history-table' );
		}

		function render( scans ) {
			if ( ! container ) { return; }
			if ( ! scans || scans.length === 0 ) {
				container.innerHTML = '<p class="wpvigie-empty">' + esc( i18n( 'noHistory', 'No scans yet.' ) ) + '</p>';
				return;
			}

			var INITIAL_ROWS = 10;
			var rows = scans.map( function ( scan, idx ) {
				var sc    = parseInt( scan.score, 10 );
				var cls   = scoreClass( sc );
				var trig  = scan.trigger || 'manual';
				var date  = new Date( scan.scanned_at.replace( ' ', 'T' ) + 'Z' ).toLocaleString();
				var attrs = idx >= INITIAL_ROWS ? ' class="wpvigie-history-row--hidden"' : '';
				return (
					'<tr' + attrs + '>' +
					'<td>' + esc( date ) + '</td>' +
					'<td><span class="wpvigie-score-badge wpvigie-score-badge--' + cls + '">' + sc + '</span></td>' +
					'<td><span class="wpvigie-trigger-badge wpvigie-trigger-badge--' + esc( trig ) + '">' + esc( trig ) + '</span></td>' +
					'<td><a class="wpvigie-view-btn" href="#" data-scan-id="' + esc( scan.id ) + '">' + esc( i18n( 'view', 'View' ) ) + '</a></td>' +
					'</tr>'
				);
			} ).join( '' );

			var showAllHtml = scans.length > INITIAL_ROWS
				? '<div class="wpvigie-table-footer"><button class="wpvigie-show-all-btn" type="button">' +
				  esc( i18n( 'showAll', 'Show all' ) ) + ' (' + scans.length + ')' +
				  '</button></div>'
				: '';

			container.innerHTML =
				'<table class="wpvigie-table"><thead><tr>' +
				'<th>' + esc( i18n( 'date',    'Date' ) )    + '</th>' +
				'<th>' + esc( i18n( 'score',   'Score' ) )   + '</th>' +
				'<th>' + esc( i18n( 'trigger', 'Trigger' ) ) + '</th>' +
				'<th></th>' +
				'</tr></thead><tbody>' + rows + '</tbody></table>' + showAllHtml;

			container.querySelectorAll( '[data-scan-id]' ).forEach( function ( a ) {
				a.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					loadOneScan( parseInt( a.dataset.scanId, 10 ) );
				} );
			} );

			var showAllBtn = container.querySelector( '.wpvigie-show-all-btn' );
			if ( showAllBtn ) {
				showAllBtn.addEventListener( 'click', function () {
					container.querySelectorAll( '.wpvigie-history-row--hidden' ).forEach( function ( row ) {
						row.classList.remove( 'wpvigie-history-row--hidden' );
					} );
					showAllBtn.parentElement.remove();
				} );
			}
		}

		return { init: init, render: render };
	}() );

	/* -------------------------------------------------------------------------
	   Load one historical scan into the card view
	   ---------------------------------------------------------------------- */

	function loadOneScan( id ) {
		var body = new FormData();
		body.append( 'action',  'wpvigie_get_scan' );
		body.append( 'nonce',   WPVigieData.nonce );
		body.append( 'scan_id', id );
		fetch( WPVigieData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( ! json.success ) { return; }
				var d = json.data;
				var ts = new Date( d.scanned_at.replace( ' ', 'T' ) + 'Z' ).toLocaleString();
				ScoreRing.update( d.score, i18n( 'viewingFrom', 'Viewing scan from' ) + ' ' + ts );
				Cards.render( d.results );
				Tabs.switchTo( 'dashboard' );
			} );
	}

	/* -------------------------------------------------------------------------
	   History loader — uses pre-loaded data on first call, AJAX thereafter
	   ---------------------------------------------------------------------- */

	function loadHistory() {
		if ( WPVigieData.history ) {
			Chart.render( WPVigieData.history.chart_data );
			HistoryTable.render( WPVigieData.history.scans );
			WPVigieData.history = null; // next call hits AJAX
			return;
		}
		var body = new FormData();
		body.append( 'action', 'wpvigie_get_history' );
		body.append( 'nonce',  WPVigieData.nonce );
		fetch( WPVigieData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( json.success ) {
					Chart.render( json.data.chart_data );
					HistoryTable.render( json.data.scans );
				}
			} );
	}

	/* -------------------------------------------------------------------------
	   Settings
	   ---------------------------------------------------------------------- */

	var Settings = ( function () {
		var form, msgEl;

		function init() {
			form  = document.getElementById( 'wpvigie-settings-form' );
			msgEl = document.getElementById( 'wpvigie-settings-msg' );
			if ( ! form ) { return; }
			form.addEventListener( 'submit', function ( e ) { e.preventDefault(); save(); } );
		}

		function save() {
			var body = new FormData( form );
			body.append( 'action', 'wpvigie_save_settings' );
			body.append( 'nonce',  WPVigieData.nonce );
			// FormData omits unchecked checkboxes; make it explicit for the PHP handler
			body.set( 'daily_scan_enabled', form.querySelector( '[name="daily_scan_enabled"]' ).checked ? '1' : '0' );

			var btn = form.querySelector( 'button[type="submit"]' );
			if ( btn ) { btn.disabled = true; }

			fetch( WPVigieData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( json.success && msgEl ) {
						msgEl.textContent = i18n( 'settingsSaved', 'Settings saved.' );
						msgEl.classList.add( 'is-visible' );
						setTimeout( function () { msgEl.classList.remove( 'is-visible' ); }, 3000 );
					}
				} )
				.finally( function () {
					if ( btn ) { btn.disabled = false; }
				} );
		}

		return { init: init };
	}() );

	/* -------------------------------------------------------------------------
	   Scan
	   ---------------------------------------------------------------------- */

	var Scan = ( function () {
		var btn, srLive;

		function init() {
			btn    = document.getElementById( 'wpvigie-run-scan' );
			srLive = document.getElementById( 'wpvigie-sr-live' );
			if ( ! btn ) { return; }
			btn.addEventListener( 'click', run );
		}

		function run() {
			btn.disabled    = true;
			btn.textContent = i18n( 'running', 'Running scan…' );
			btn.classList.add( 'wpvigie-btn--loading' );
			if ( srLive ) { srLive.textContent = i18n( 'running', 'Running scan…' ); }

			var body = new FormData();
			body.append( 'action', 'wpvigie_run_scan' );
			body.append( 'nonce',  WPVigieData.nonce );

			fetch( WPVigieData.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( ! json.success ) { throw new Error( 'scan_failed' ); }
					var d = json.data;
					ScoreRing.update( d.score, i18n( 'lastScan', 'Last scan' ) + ': ' + new Date().toLocaleTimeString() );
					Cards.render( d.results );
					if ( srLive ) { srLive.textContent = 'Scan complete. Score: ' + d.score + ' out of 100.'; }
					loadHistory();
				} )
				.catch( function () {
					if ( srLive ) { srLive.textContent = i18n( 'error', 'Scan failed. Check error log.' ); }
				} )
				.finally( function () {
					btn.disabled    = false;
					btn.textContent = i18n( 'runScan', 'Run Scan' );
					btn.classList.remove( 'wpvigie-btn--loading' );
				} );
		}

		return { init: init };
	}() );

	/* -------------------------------------------------------------------------
	   Boot
	   ---------------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		Tabs.init();
		ScoreRing.init();
		Cards.init();
		Chart.init();
		HistoryTable.init();
		Scan.init();
		Settings.init();
		loadHistory();
	} );

}() );
