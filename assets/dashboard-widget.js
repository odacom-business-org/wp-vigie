( function () {
	'use strict';

	var reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.wpvw-ring__progress' ).forEach( function ( el ) {
			var target = el.getAttribute( 'data-offset' );
			if ( ! target ) { return; }

			if ( reducedMotion ) {
				el.setAttribute( 'stroke-dashoffset', target );
				return;
			}

			// Transition from full circumference (ring hidden) → target offset
			el.style.transition = 'stroke-dashoffset 1000ms cubic-bezier(0.16, 1, 0.3, 1)';
			void el.getBoundingClientRect(); // force reflow so transition fires
			el.style.strokeDashoffset = target;
		} );
	} );

}() );
