/* Ostorlab admin: analysis runner, connection tests, small form helpers. */
( function () {
	'use strict';
	const post = ( action, data ) => {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', OST.nonce );
		Object.entries( data || {} ).forEach( ( [ k, v ] ) => body.append( k, v ) );
		return fetch( OST.ajax, { method: 'POST', body, credentials: 'same-origin' } ).then( ( r ) => r.json() );
	};
	const esc = ( s ) => String( s == null ? '' : s ).replace( /[&<>"]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' } )[ c ] );

	// Analysis runner: one step per request until the job is done or paused.
	const run = document.getElementById( 'ost-run' );
	if ( run ) {
		const id = run.dataset.id;
		const list = run.querySelector( '.ost-steps' );
		const bar = run.querySelector( '.ost-progress i' );
		const buttons = run.querySelectorAll( 'button' );
		const render = ( job ) => {
			const steps = Object.values( job.steps || {} );
			const done = steps.filter( ( s ) => s.status !== 'pending' ).length;
			bar.style.width = ( steps.length ? Math.round( 100 * done / steps.length ) : 0 ) + '%';
			let active = false;
			list.innerHTML = steps.map( ( s ) => {
				let cls = s.status;
				if ( s.status === 'pending' && ! active && job.status === 'running' ) {
					cls = 'active';
					active = true;
				}
				return '<li class="' + cls + '"><b>' + esc( s.label ) + '</b><small>' + esc( s.msg ) + '</small></li>';
			} ).join( '' );
		};
		const lock = ( on ) => buttons.forEach( ( b ) => ( b.disabled = on ) );
		const tick = ( resume ) => post( 'ost_job_tick', { id, resume: resume ? 1 : 0 } ).then( ( r ) => {
			if ( ! r.success ) {
				throw new Error( ( r.data && r.data.msg ) || 'خطا' );
			}
			render( r.data );
			if ( r.data.status === 'running' ) {
				return tick( false );
			}
			lock( false );
			if ( r.data.status === 'paused' ) {
				alert( r.data.msg );
			}
			window.location.reload();
		} ).catch( ( e ) => {
			lock( false );
			alert( 'اجرای تحلیل متوقف شد: ' + e.message + '\nدوباره «به‌روزرسانی تحلیل» را بزنید؛ مراحل انجام‌شده تکرار نمی‌شوند.' );
		} );
		const start = ( fresh ) => {
			lock( true );
			post( 'ost_job_start', { id, fresh: fresh ? 1 : 0 } ).then( ( r ) => {
				if ( ! r.success ) {
					lock( false );
					alert( r.data.msg );
					return;
				}
				render( r.data );
				tick( false );
			} );
		};
		run.querySelectorAll( '[data-run]' ).forEach( ( b ) => b.addEventListener( 'click', () => {
			if ( b.dataset.run === 'fresh' && ! confirm( 'همه داده‌ها از نو گرفته شود؟ (پاسخ‌های کش‌شده سئو سیگنال سهمیه مصرف نمی‌کنند)' ) ) {
				return;
			}
			start( b.dataset.run === 'fresh' );
		} ) );
		run.querySelectorAll( '[data-resume]' ).forEach( ( b ) => b.addEventListener( 'click', () => {
			lock( true );
			tick( true );
		} ) );
		if ( run.dataset.autorun === '1' ) {
			history.replaceState( null, '', window.location.href.replace( /&autorun=1/, '' ) );
			start( false );
		} else if ( run.dataset.status === 'running' ) {
			lock( true );
			tick( false );
		}
	}

	// Connection tests.
	document.querySelectorAll( '[data-test]' ).forEach( ( b ) => b.addEventListener( 'click', () => {
		const out = document.querySelector( '[data-out="' + b.dataset.test + '"]' );
		out.className = 'ost-test-out';
		out.textContent = 'در حال بررسی…';
		b.disabled = true;
		post( 'ost_test', { svc: b.dataset.test } ).then( ( r ) => {
			b.disabled = false;
			const d = r.data || {};
			out.className = 'ost-test-out ' + ( d.ok ? 'ok' : 'bad' );
			out.textContent = ( d.ok ? '✓ ' : '✗ ' ) + ( d.msg || '' );
		} );
	} ) );

	// Rewrite texts with AI.
	const ai = document.getElementById( 'ost-ai-texts' );
	if ( ai ) {
		ai.addEventListener( 'click', () => {
			if ( ! confirm( 'متن‌ها با هوش مصنوعی بازنویسی شود؟ ویرایش‌های دستی ذخیره‌شده حفظ می‌شوند.' ) ) {
				return;
			}
			ai.disabled = true;
			ai.textContent = 'در حال نوشتن… (تا ۲ دقیقه)';
			post( 'ost_ai_texts', { id: ai.dataset.id } ).then( ( r ) => {
				alert( r.data.msg );
				window.location.reload();
			} );
		} );
	}

	// Confirm links / buttons.
	document.querySelectorAll( '[data-confirm]' ).forEach( ( el ) => el.addEventListener( 'click', ( e ) => {
		if ( ! confirm( el.dataset.confirm ) ) {
			e.preventDefault();
		}
	} ) );

	// Money inputs: thousands separators while typing.
	const fa = ( s ) => s.replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] );
	document.querySelectorAll( '.ost-money' ).forEach( ( el ) => el.addEventListener( 'input', () => {
		const raw = el.value.replace( /[۰-۹]/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ).replace( /[^\d]/g, '' );
		el.value = raw ? fa( Number( raw ).toLocaleString( 'en-US' ).replace( /,/g, '٬' ) ) : '';
	} ) );

	// Keyword / competitor counters.
	document.querySelectorAll( 'textarea[data-count]' ).forEach( ( ta ) => {
		const out = document.querySelector( '.ost-count[data-for="' + ta.dataset.count + '"]' );
		const upd = () => {
			const n = ta.value.split( /[\n,،]/ ).filter( ( l ) => l.trim() ).length;
			const ok = ta.dataset.count === 'kw' ? n >= 5 && n <= 7 : n >= 3 && n <= 5;
			out.textContent = fa( String( n ) ) + ' مورد' + ( ok ? ' ✓' : ta.dataset.count === 'kw' ? ' (پیشنهاد ۵ تا ۷)' : ' (پیشنهاد ۳ تا ۵)' );
		};
		ta.addEventListener( 'input', upd );
		upd();
	} );

	// Image pickers (media library): logo and presenter photo.
	document.querySelectorAll( '[data-media]' ).forEach( ( btn ) => btn.addEventListener( 'click', () => {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		const target = btn.dataset.media;
		const frame = wp.media( { title: 'انتخاب تصویر', library: { type: 'image' }, multiple: false } );
		frame.on( 'select', () => {
			const url = frame.state().get( 'selection' ).first().toJSON().url;
			document.getElementById( target ).value = url;
			document.getElementById( target + '-img' ).src = url;
		} );
		frame.open();
	} ) );
}() );
