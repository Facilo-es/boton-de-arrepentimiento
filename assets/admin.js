/* Botón Claro admin: confirm prompts, style picker, colour check and the live preview. No outside requests. */
( function () {
	'use strict';

	var cfg = window.bclaroAdmin || {};

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}
	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}

	// Confirm before actions that send something or delete.
	document.addEventListener( 'submit', function ( e ) {
		var f = e.target;
		if ( f && f.getAttribute && f.getAttribute( 'data-bclaro-confirm' ) ) {
			if ( ! window.confirm( f.getAttribute( 'data-bclaro-confirm' ) ) ) {
				e.preventDefault();
			}
		}
	}, true );

	var styleIn = $( '#bclaro-style' );
	var colorIn = $( '#bclaro-color' );
	var frame = $( '#bclaro-frame' );
	var vp = $( '#bclaro-vp' );
	var lee = $( '#bclaro-lee' );
	var timer = null;
	var seq = 0;

	function frameDoc() {
		try {
			return frame && frame.contentDocument;
		} catch ( err ) {
			return null;
		}
	}

	// Show or hide each place in the preview, following the checkboxes.
	function applyPlaces() {
		var d = frameDoc();
		if ( ! d || ! d.body ) {
			return;
		}
		$$( '[data-bclaro-place]' ).forEach( function ( box ) {
			var p = box.getAttribute( 'data-bclaro-place' );
			var sel = 'franja' === p ? '.bclaro-franja' : ( 'pill' === p ? '.bclaro-pill' : '[data-bclaro="baja"]' );
			$$( sel, d ).forEach( function ( el ) {
				el.style.display = box.checked ? '' : 'none';
			} );
		} );
	}

	function setFrameCss( css ) {
		var d = frameDoc();
		if ( ! d || ! d.head ) {
			return;
		}
		var tag = d.getElementById( 'bclaro-botones-inline-css' );
		if ( ! tag ) {
			tag = d.createElement( 'style' );
			tag.id = 'bclaro-botones-inline-css';
			d.head.appendChild( tag );
		}
		tag.textContent = css;
	}

	// Ask WordPress for the CSS of the chosen style and colour.
	function refresh( color ) {
		if ( ! cfg.ajax || ! styleIn ) {
			return;
		}
		var mine = ++seq;
		var body = new window.FormData();
		body.append( 'action', 'bclaro_css' );
		body.append( '_ajax_nonce', cfg.nonce || '' );
		body.append( 'style', styleIn.value );
		body.append( 'color', color || ( colorIn ? colorIn.value : '' ) );
		body.append( 'tiles', '1' );
		window.fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( mine !== seq || ! res || ! res.success ) {
					return;
				}
				var data = res.data || {};
				var msg = data.msg || {};
				if ( lee && msg.text ) {
					lee.textContent = msg.text;
					lee.classList.toggle( 'mal', !! msg.bad );
				}
				if ( msg.bad ) {
					return; // Keep the last colour that reads well.
				}
				if ( color && colorIn ) {
					colorIn.value = color;
				}
				var tiles = data.tiles || {};
				Object.keys( tiles ).forEach( function ( slug ) {
					var t = document.getElementById( 'bclaro-tile-css-' + slug );
					if ( t ) {
						t.textContent = tiles[ slug ];
					}
				} );
				if ( data.css ) {
					setFrameCss( data.css );
				}
			} )
			.catch( function () {} );
	}

	function later( color ) {
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			refresh( color );
		}, 150 );
	}

	function pickStyle( slug ) {
		if ( ! styleIn ) {
			return;
		}
		styleIn.value = slug;
		$$( '.st8' ).forEach( function ( b ) {
			var on = b.getAttribute( 'data-e' ) === slug;
			b.classList.toggle( 'on', on );
			b.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		} );
	}

	function pickColor( c ) {
		$$( '.sw' ).forEach( function ( b ) {
			var on = b.getAttribute( 'data-c' ).toLowerCase() === String( c ).toLowerCase();
			b.classList.toggle( 'on', on );
			b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest ? e.target : null;
		if ( ! t ) {
			return;
		}
		var st = t.closest( '.st8' );
		if ( st ) {
			e.preventDefault();
			pickStyle( st.getAttribute( 'data-e' ) );
			refresh();
			return;
		}
		var sw = t.closest( '.sw' );
		if ( sw ) {
			e.preventDefault();
			pickColor( sw.getAttribute( 'data-c' ) );
			refresh( sw.getAttribute( 'data-c' ) );
			return;
		}
		if ( t.closest( '#bclaro-volver' ) ) {
			e.preventDefault();
			var rec = $( '.rec[data-style]' );
			if ( rec ) {
				pickStyle( rec.getAttribute( 'data-style' ) );
				pickColor( rec.getAttribute( 'data-color' ) );
				refresh( rec.getAttribute( 'data-color' ) );
			}
			return;
		}
		var seg = t.closest( '.seg button' );
		if ( seg ) {
			e.preventDefault();
			$$( 'button', seg.parentNode ).forEach( function ( b ) {
				b.classList.toggle( 'on', b === seg );
				b.setAttribute( 'aria-pressed', b === seg ? 'true' : 'false' );
			} );
			if ( vp ) {
				vp.classList.toggle( 'cel', 'cel' === seg.getAttribute( 'data-v' ) );
			}
			fit();
		}
	} );

	var otro = $( '#bclaro-otro' );
	if ( otro ) {
		otro.addEventListener( 'input', function () {
			pickColor( otro.value );
			later( otro.value );
		} );
	}

	$$( '[data-bclaro-place]' ).forEach( function ( box ) {
		box.addEventListener( 'change', applyPlaces );
	} );

	// Scale the preview: a 1000 px wide page on computer, 360 x 650 on phone.
	function fit() {
		if ( ! vp || ! frame ) {
			return;
		}
		var k;
		if ( vp.classList.contains( 'cel' ) ) {
			k = Math.min( 1, vp.clientHeight / 650 );
			frame.style.width = '360px';
			frame.style.height = '650px';
			frame.style.marginLeft = ( -180 * k ) + 'px';
		} else {
			k = vp.clientWidth / 1000;
			frame.style.width = '1000px';
			frame.style.height = Math.ceil( vp.clientHeight / k ) + 'px';
			frame.style.marginLeft = '0';
		}
		frame.style.transform = 'scale(' + k + ')';
	}

	if ( frame ) {
		frame.addEventListener( 'load', function () {
			applyPlaces();
			refresh();
		} );
		window.addEventListener( 'resize', fit );
		fit();
	}
}() );
