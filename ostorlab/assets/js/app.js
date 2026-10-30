/* Ostorlab admin: analysis runner, connection tests, small form helpers. */
( function () {
	'use strict';
	// AJAX helper. The answer is read as text: a PHP error page (HTML) becomes a readable error, not a crash.
	const post = ( action, data ) => {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', OST.nonce );
		Object.entries( data || {} ).forEach( ( [ k, v ] ) => body.append( k, v ) );
		return fetch( OST.ajax, { method: 'POST', body, credentials: 'same-origin' } ).then( ( r ) => r.text() ).then( ( t ) => {
			try {
				return JSON.parse( t.replace( /^[\s\S]*?(\{"success")/, '$1' ) );
			} catch ( e ) {
				const text = t.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim().slice( 0, 180 );
				return { success: false, crashed: true, data: { msg: 'خطای سرور: ' + ( text || 'پاسخ خالی' ) } };
			}
		} ).catch( () => ( { success: false, crashed: true, data: { msg: 'اتصال به سرور قطع شد' } } ) );
	};
	const esc = ( s ) => String( s == null ? '' : s ).replace( /[&<>"]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' } )[ c ] );
	const fa = ( s ) => s.replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] );

	// Analysis runner: one step per request until the job is done or paused.
	// A server crash inside a step (HTML error page instead of JSON) is shown
	// inline, the step is recorded as failed on the server and the run goes on;
	// after 3 failures in a row it stops and waits for the user. Nothing restarts
	// by itself on page load.
	const run = document.getElementById( 'ost-run' );
	if ( run ) {
		const id = run.dataset.id;
		const list = run.querySelector( '.ost-steps' );
		const bar = run.querySelector( '.ost-progress i' );
		const note = run.querySelector( '.ost-run-note' );
		const buttons = run.querySelectorAll( 'button' );
		let fails = 0;
		const say = ( msg, kind ) => {
			note.hidden = ! msg;
			note.className = 'ost-run-note ost-flash ' + ( kind || 'is-err' );
			note.textContent = msg || '';
		};
		const render = ( job ) => {
			const steps = Object.values( job.steps || {} );
			const done = steps.filter( ( s ) => s.status === 'done' || s.status === 'error' ).length;
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
		// Read the answer as text first: a PHP fatal error comes back as an HTML page.
		const call = ( action, data ) => {
			const body = new FormData();
			body.append( 'action', action );
			body.append( 'nonce', OST.nonce );
			Object.entries( data ).forEach( ( [ k, v ] ) => body.append( k, v ) );
			return fetch( OST.ajax, { method: 'POST', body, credentials: 'same-origin' } ).then( ( r ) => r.text() ).then( ( t ) => {
				try {
					return JSON.parse( t.replace( /^[\s\S]*?(\{"success")/, '$1' ) );
				} catch ( e ) {
					const text = t.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim().slice( 0, 180 );
					return { success: false, crashed: true, data: { msg: text || 'پاسخ خالی از سرور' } };
				}
			} ).catch( () => ( { success: false, crashed: true, data: { msg: 'اتصال به سرور قطع شد' } } ) );
		};
		const tick = ( resume ) => call( 'ost_job_tick', { id, resume: resume ? 1 : 0 } ).then( ( r ) => {
			if ( ! r.success ) {
				fails++;
				if ( fails >= 3 || ! r.crashed ) {
					lock( false );
					say( 'تحلیل متوقف شد: ' + ( r.data && r.data.msg ? r.data.msg : 'خطای نامشخص' ) + ' — «ادامه تحلیل» را بزنید؛ مراحل انجام‌شده تکرار نمی‌شوند و مرحله خراب رد می‌شود.' );
					return;
				}
				say( 'یک مرحله با خطای سرور متوقف شد؛ ثبت شد و تحلیل از مرحله بعد ادامه دارد…', 'is-warn' );
				return tick( true );
			}
			fails = 0;
			render( r.data );
			if ( r.data.status === 'running' ) {
				return tick( false );
			}
			lock( false );
			if ( r.data.status === 'paused' ) {
				say( r.data.msg, 'is-warn' );
				return;
			}
			window.location.reload();
		} );
		const start = ( fresh ) => {
			lock( true );
			say( '' );
			call( 'ost_job_start', { id, fresh: fresh ? 1 : 0 } ).then( ( r ) => {
				if ( ! r.success ) {
					lock( false );
					say( r.data.msg );
					return;
				}
				render( r.data );
				tick( false );
			} );
		};
		run.querySelectorAll( '[data-run]' ).forEach( ( b ) => b.addEventListener( 'click', () => {
			if ( b.dataset.run === 'fresh' && ! confirm( 'همه داده‌ها از نو گرفته شود؟ (پاسخ‌های ذخیره‌شده سئو سیگنال سهمیه مصرف نمی‌کنند)' ) ) {
				return;
			}
			start( b.dataset.run === 'fresh' );
		} ) );
		run.querySelectorAll( '[data-resume]' ).forEach( ( b ) => b.addEventListener( 'click', () => {
			lock( true );
			say( '' );
			fails = 0;
			tick( true );
		} ) );
		if ( run.dataset.autorun === '1' ) {
			const u = new URL( window.location.href );
			[ 'autorun', 'ost_msg', 'ost_ok' ].forEach( ( k ) => u.searchParams.delete( k ) );
			history.replaceState( null, '', u.toString() );
			start( false );
		}
	}

	// Screaming Frog wizard: 5 steps, each unlocked by the previous one's confirmation or test.
	const wiz = document.getElementById( 'ost-sf' );
	if ( wiz ) {
		const id = wiz.dataset.id;
		let st = JSON.parse( wiz.dataset.state );
		let timer = null;
		const sf = ( data ) => post( 'ost_sf', Object.assign( { id }, data ) ).then( ( r ) => {
			if ( ! r.success ) {
				throw new Error( ( r.data && r.data.msg ) || 'خطا' );
			}
			st = r.data;
			paint();
			return st;
		} );
		const stageLabel = { waiting: 'در انتظار', crawling: 'در حال خزش…', uploaded: 'رسید ✓', failed: 'ناموفق — با اجرای دوباره ادامه می‌دهد', skipped: 'رد شد (بدون خزش)' };
		const doneOf = ( n ) => [ st.installed, st.downloaded, st.connected && st.sf_found, st.urls_ok, st.all_done ][ n - 1 ];
		const paint = () => {
			let current = 0;
			wiz.querySelectorAll( '.ost-wsteps > li' ).forEach( ( li ) => {
				const n = Number( li.dataset.step );
				const done = !! doneOf( n );
				const isCur = ! done && ! current;
				if ( isCur ) {
					current = n;
				}
				li.className = done ? 'is-done' + ( 5 === n ? ' is-open' : '' ) : ( isCur ? 'is-current' : 'is-locked' );
				li.querySelector( '.st' ).textContent = done ? 'انجام شد ✓' : ( isCur ? 'مرحله فعلی' : 'قفل' );
			} );
			wiz.querySelectorAll( '[data-os-pick]' ).forEach( ( b ) => b.classList.toggle( 'on', b.dataset.osPick === st.os ) );
			wiz.querySelectorAll( '[data-os-show]' ).forEach( ( el ) => ( el.hidden = el.dataset.osShow !== st.os ) );
			const ping = wiz.querySelector( '[data-ping]' );
			if ( st.ping ) {
				ping.className = 'ost-live ' + ( st.sf_found ? 'is-ok' : 'is-warn' );
				ping.innerHTML = st.sf_found
					? '✓ اتصال برقرار شد — کامپیوتر <b>' + esc( st.ping.host ) + '</b> (' + esc( st.ping.os ) + ')، ' + esc( st.ping.ago ) + '<br>اسکریمینگ فراگ پیدا شد: <code dir="ltr">' + esc( st.ping.sf_path ) + '</code>'
					: '⚠ اتصال برقرار شد ولی اسکریمینگ فراگ روی کامپیوتر <b>' + esc( st.ping.host ) + '</b> پیدا نشد. مسیر برنامه را با SpiderPath بدهید (راهنمای پایین) و تست را دوباره اجرا کنید.';
			}
			const urlCell = ( d ) => {
				if ( d.skip ) {
					return '<span class="ost-muted">خزیده نمی‌شود</span>';
				}
				if ( d.urls === null ) {
					return '<span class="ost-muted">بررسی نشده</span>';
				}
				if ( d.url_error ) {
					return '<span class="ost-warn-t">از سرور باز نشد؛ اسکریمینگ فراگ از صفحه اصلی شروع می‌کند</span><br><small class="ost-muted">' + esc( d.url_error ) + '</small>';
				}
				return fa( String( d.urls ) ) + ' صفحه' + ( d.traffic ? ' <small class="ost-muted">(' + fa( String( d.traffic ) ) + ' صفحه پرترافیک)</small>' : '' );
			};
			wiz.querySelector( '[data-urls]' ).innerHTML = st.domains.map( ( d ) => '<tr' + ( d.skip ? ' class="is-skip"' : '' ) + '><td><input type="checkbox" data-skip="' + esc( d.domain ) + '"' + ( d.skip ? '' : ' checked' ) + '></td><td dir="ltr">' + esc( d.domain ) + '</td><td>' + ( d.role === 'client' ? 'کارفرما' : 'رقیب' ) + '</td><td>' + urlCell( d ) + '</td></tr>' ).join( '' );
			wiz.querySelector( '[data-crawl]' ).innerHTML = st.domains.map( ( d ) => '<tr class="s-' + d.stage + '"><td dir="ltr">' + esc( d.domain ) + '</td><td>' + ( stageLabel[ d.stage ] || d.stage ) + ( d.note ? '<br><small>' + esc( d.note ) + '</small>' : '' ) + '</td><td>' + ( d.got.length ? d.got.map( ( g ) => ( { internal: 'صفحات', inlinks: 'لینک‌ها', redirects: 'ریدایرکت‌ها' } )[ g ] ).join( '، ' ) : '—' ) + '</td><td>' + ( d.pages ? fa( String( d.pages ) ) : '—' ) + '</td></tr>' ).join( '' );
			wiz.querySelector( '[data-final]' ).hidden = ! st.all_done;
			// Poll while the step needs the agent (3: test, 5: crawl).
			clearInterval( timer );
			if ( current === 3 || current === 5 || ( st.all_done && current === 0 && false ) ) {
				timer = setInterval( () => sf( { do: 'state' } ).catch( () => {} ), current === 3 ? 3000 : 5000 );
			}
		};
		wiz.addEventListener( 'change', ( e ) => {
			const c = e.target.closest( '[data-skip]' );
			if ( c ) {
				sf( { do: 'skip', domain: c.dataset.skip, on: c.checked ? '' : '1' } ).catch( () => sf( { do: 'state' } ) );
			}
		} );
		wiz.addEventListener( 'click', ( e ) => {
			const b = e.target.closest( '[data-sf],[data-os-pick],[data-sf-download]' );
			if ( ! b ) {
				return;
			}
			if ( b.dataset.osPick ) {
				sf( { do: 'os', os: b.dataset.osPick } );
			} else if ( b.hasAttribute( 'data-sf-download' ) ) {
				setTimeout( () => sf( { do: 'state' } ), 2500 );
			} else if ( b.dataset.sf === 'urls' ) {
				const limit = wiz.querySelector( '[data-limit]' ).value;
				b.disabled = true;
				const doms = st.domains.filter( ( d ) => ! d.skip ).map( ( d ) => d.domain );
				const next = ( i ) => {
					if ( i >= doms.length ) {
						b.disabled = false;
						const live = st.domains.filter( ( d ) => ! d.skip );
						const ok = live.length > 0 && live.every( ( d ) => d.urls > 0 );
						return ok ? sf( { do: 'urls_ok' } ) : null;
					}
					b.textContent = 'بررسی ' + doms[ i ] + '…';
					return sf( { do: 'urls', domain: doms[ i ], limit } ).catch( () => sf( { do: 'state' } ) ).then( () => next( i + 1 ) );
				};
				next( 0 ).then( () => ( b.textContent = 'بررسی دوباره' ) );
			} else if ( b.dataset.sf === 'analyze' ) {
				b.disabled = true;
				b.textContent = 'در حال تحلیل…';
				sf( { do: 'analyze' } ).then( () => window.location.reload() );
			} else if ( b.dataset.sf ) {
				if ( b.dataset.confirm && ! confirm( b.dataset.confirm ) ) {
					return;
				}
				sf( { do: b.dataset.sf } );
			}
		} );
		paint();
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
	document.querySelectorAll( '[data-confirm]:not([data-sf])' ).forEach( ( el ) => el.addEventListener( 'click', ( e ) => {
		if ( ! confirm( el.dataset.confirm ) ) {
			e.preventDefault();
		}
	} ) );

	// Money inputs: thousands separators while typing.
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

	// Mobile navigation.
	const shell = document.querySelector( '.ost-shell' );
	document.querySelectorAll( '[data-ost-open-nav]' ).forEach( ( b ) => b.addEventListener( 'click', () => shell.classList.add( 'nav-open' ) ) );
	document.querySelectorAll( '[data-ost-close-nav]' ).forEach( ( b ) => b.addEventListener( 'click', () => shell.classList.remove( 'nav-open' ) ) );

	// Copy buttons (client link).
	document.querySelectorAll( '[data-copy]' ).forEach( ( b ) => b.addEventListener( 'click', () => {
		const done = () => {
			const t = b.textContent;
			b.textContent = '✓ کپی شد';
			setTimeout( () => ( b.textContent = t ), 1600 );
		};
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( b.dataset.copy ).then( done );
		} else {
			const i = document.createElement( 'input' );
			i.value = b.dataset.copy;
			document.body.appendChild( i );
			i.select();
			document.execCommand( 'copy' );
			i.remove();
			done();
		}
	} ) );

	// Sales stage «lost»: ask for the reason first.
	document.querySelectorAll( '.ost-salesbar [data-lost]' ).forEach( ( b ) => b.addEventListener( 'click', ( e ) => {
		const sel = b.closest( 'form' ).querySelector( '.ost-lost-reason' );
		if ( sel.hidden ) {
			e.preventDefault();
			sel.hidden = false;
			sel.focus();
			b.textContent = 'ثبت «رد شد» با این دلیل';
		}
	} ) );

	// Dashboard: search, status chips and funnel rows filter the proposals table.
	const list = document.querySelector( '.ost-list tbody' );
	if ( list ) {
		const search = document.querySelector( '[data-search]' );
		const none = document.querySelector( '.ost-noresult' );
		let statuses = '';
		const norm = ( s ) => s.replace( /ي/g, 'ی' ).replace( /ك/g, 'ک' ).replace( /\u200c/g, ' ' ).toLowerCase().trim();
		const apply = () => {
			const q = norm( search.value || '' );
			const allowed = statuses ? statuses.split( ',' ) : null;
			let shown = 0;
			list.querySelectorAll( 'tr' ).forEach( ( tr ) => {
				const ok = ( ! allowed || allowed.includes( tr.dataset.status ) ) && ( ! q || tr.dataset.text.includes( q ) );
				tr.hidden = ! ok;
				shown += ok ? 1 : 0;
			} );
			none.hidden = shown > 0;
		};
		search.addEventListener( 'input', apply );
		const chips = document.querySelectorAll( '[data-chips] button' );
		const pick = ( value ) => {
			statuses = value;
			chips.forEach( ( c ) => c.classList.toggle( 'on', c.dataset.filter === value ) );
			apply();
		};
		chips.forEach( ( c ) => c.addEventListener( 'click', () => pick( c.dataset.filter ) ) );
		document.querySelectorAll( '.ost-funnel-row' ).forEach( ( r ) => r.addEventListener( 'click', () => {
			pick( r.dataset.filter );
			list.closest( '.ost-panel' ).scrollIntoView( { behavior: 'smooth' } );
		} ) );
	}
}() );
