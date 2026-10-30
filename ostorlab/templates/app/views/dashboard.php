<?php
/**
 * Dashboard: sales pipeline of the proposals, follow-ups due, every proposal
 * with filters, data-source quotas and recent activity.
 *
 * @package Ostorlab
 */

defined( 'ABSPATH' ) || exit;

$items = ost_proposals();
$pipe  = ost_pipeline( $items );
$user  = wp_get_current_user();
$today = gmdate( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore
$stages = array(
	'draft'       => array( 'draft', 'running', 'paused' ),
	'ready'       => array( 'ready' ),
	'sent'        => array( 'sent' ),
	'negotiating' => array( 'negotiating' ),
	'won'         => array( 'won' ),
	'lost'        => array( 'lost' ),
);
$stage_n = array();
foreach ( $stages as $k => $sts ) {
	$stage_n[ $k ] = array_sum( array_intersect_key( $pipe['by'], array_flip( $sts ) ) );
}
$max_stage = max( 1, max( $stage_n ) );
// Recent activity over all proposals.
$feed = array();
foreach ( $items as $p ) {
	foreach ( array_slice( ost_log_entries( $p['id'] ), 0, 6 ) as $e ) {
		$feed[] = array( 'p' => $p, 'e' => $e );
	}
}
usort( $feed, function ( $a, $b ) {
	return $b['e']['t'] <=> $a['e']['t'];
} );
$feed = array_slice( $feed, 0, 9 );
$ssu  = ost_ss_usage();
?>
<header class="ost-head">
	<div>
		<p class="ost-crumb">سلام <?php echo esc_html( $user->display_name ); ?> 👋</p>
		<h1>داشبورد پروپوزال‌ها</h1>
		<p class="ost-meta"><span><?php echo esc_html( ost_jdate() ); ?></span><span><?php echo esc_html( ost_num( $pipe['month'] ) ); ?> پروپوزال این ماه</span></p>
	</div>
	<div class="ost-head-act">
		<a class="ost-btn primary lg" href="<?php echo esc_url( ost_url( 'new' ) ); ?>"><?php echo ost_icon( 'plus' ); // phpcs:ignore ?> پروپوزال جدید</a>
	</div>
</header>

<?php if ( ! ost_ss_connected() ) : ?>
	<div class="ost-flash is-err">برای شروع، کلید API سئو سیگنال را در <a href="<?php echo esc_url( ost_url( 'settings' ) ); ?>">تنظیمات › اتصال‌ها</a> وارد کنید<?php echo ost_sokan_active() ? ' یا «استفاده از تنظیمات سکان» را روشن کنید' : ''; ?>.</div>
<?php endif; ?>

<?php if ( ! $items ) : ?>
	<section class="ost-empty is-hero">
		<span class="ost-hero-ic"><?php echo ost_icon( 'astrolabe', 44 ); // phpcs:ignore ?></span>
		<h2>اولین پروپوزال را بسازید</h2>
		<p>۵ تا ۷ کلمه کلیدی کارفرما و ۳ تا ۵ رقیب را وارد کنید؛ اسطرلاب بازار جستجو، رقبا، رپورتاژها، وضعیت فنی، استراتژی شروع، هزینه‌ها و نقشه راه را می‌سازد و یک پروپوزال اسلایدی آماده تحویل می‌دهد.</p>
		<a class="ost-btn primary lg" href="<?php echo esc_url( ost_url( 'new' ) ); ?>"><?php echo ost_icon( 'plus' ); // phpcs:ignore ?> پروپوزال جدید</a>
	</section>
<?php else : ?>

<section class="ost-kpis big">
	<div><span>پروپوزال‌ها</span><b><?php echo esc_html( ost_num( $pipe['count'] ) ); ?></b><small><?php echo esc_html( ost_num( $pipe['month'] ) ); ?> مورد این ماه</small></div>
	<div><span>منتظر پاسخ کارفرما</span><b><?php echo esc_html( ost_num( $pipe['by']['sent'] + $pipe['by']['negotiating'] ) ); ?></b><small>ارزش: <?php echo esc_html( ost_toman( $pipe['open_value'], true ) ); ?></small></div>
	<div class="hl"><span>نرخ موفقیت</span><b><?php echo esc_html( null === $pipe['win_rate'] ? '—' : ost_pct( $pipe['win_rate'] ) ); ?></b><small><?php echo esc_html( ost_num( $pipe['by']['won'] ) . ' موفق از ' . ost_num( $pipe['by']['won'] + $pipe['by']['lost'] ) . ' تصمیم‌گرفته' ); ?></small></div>
	<div><span>ارزش قراردادهای بسته‌شده</span><b><?php echo esc_html( ost_compact( $pipe['won_value'] ) ); ?></b><small>دستمزد تیم: <?php echo esc_html( ost_toman( $pipe['won_fees'], true ) ); ?></small></div>
</section>

<div class="ost-grid g-dash">
	<div>
		<section class="ost-panel">
			<h2>قیف فروش</h2>
			<div class="ost-funnel">
				<?php foreach ( $stages as $k => $sts ) : $sl = ost_status_label( 'draft' === $k ? 'draft' : $k ); ?>
					<button type="button" class="ost-funnel-row" data-filter="<?php echo esc_attr( implode( ',', $sts ) ); ?>">
						<span><?php echo esc_html( 'draft' === $k ? 'پیش‌نویس و در تحلیل' : $sl[0] ); ?></span>
						<i class="c-<?php echo esc_attr( $sl[1] ); ?>" style="width:<?php echo (int) max( 3, round( 100 * $stage_n[ $k ] / $max_stage ) ); ?>%"></i>
						<b><?php echo esc_html( ost_num( $stage_n[ $k ] ) ); ?></b>
					</button>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="ost-panel">
			<div class="ost-panel-head">
				<h2>همه پروپوزال‌ها</h2>
				<div class="ost-filters">
					<input type="search" placeholder="جستجوی کارفرما یا دامنه…" data-search>
					<div class="ost-chips" data-chips>
						<button type="button" class="on" data-filter="">همه</button>
						<button type="button" data-filter="draft,running,paused,ready">آماده‌سازی</button>
						<button type="button" data-filter="sent,negotiating">منتظر پاسخ</button>
						<button type="button" data-filter="won">موفق</button>
						<button type="button" data-filter="lost">ناموفق</button>
					</div>
				</div>
			</div>
			<div class="ost-table-wrap">
				<table class="ost-table ost-list">
					<thead><tr><th>کارفرما</th><th>وضعیت</th><th>ارزش پکیج</th><th>بازدید کارفرما</th><th>پیگیری</th><th>آخرین تغییر</th><th>مسئول</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $items as $p ) : $sl = ost_status_label( $p['status'] ); $v = ost_value( $p['id'] ); $vw = ost_views( $p['id'] ); $fu = ost_followup( $p['id'] ); $au = get_userdata( (int) get_post_field( 'post_author', $p['id'] ) ); ?>
						<tr data-status="<?php echo esc_attr( $p['status'] ); ?>" data-text="<?php echo esc_attr( ost_norm( $p['title'] . ' ' . $p['inputs']['domain'] ) ); ?>">
							<td><a class="ost-client" href="<?php echo esc_url( ost_proposal_url( $p['id'] ) ); ?>"><b><?php echo esc_html( $p['title'] ); ?></b><small><?php echo esc_html( $p['inputs']['domain'] ?: 'بدون سایت' ); ?> · <?php echo esc_html( ost_num( count( $p['inputs']['keywords'] ) ) ); ?> کلمه · <?php echo esc_html( ost_num( count( $p['inputs']['competitors'] ) ) ); ?> رقیب</small></a></td>
							<td><span class="ost-pill <?php echo esc_attr( $sl[1] ); ?>"><?php echo esc_html( $sl[0] ); ?></span></td>
							<td><?php echo $v['total'] ? '<b>' . esc_html( ost_compact( $v['total'] ) ) . '</b><small class="ost-muted d-block">' . esc_html( ost_compact( $v['monthly'] ) ) . ' ماهانه</small>' : '<span class="ost-muted">—</span>'; ?></td>
							<td><?php echo $vw['count'] ? esc_html( ost_num( $vw['count'] ) . ' بار' ) . '<small class="ost-muted d-block">' . esc_html( ost_ago( $vw['last'] ) ) . '</small>' : '<span class="ost-muted">—</span>'; ?></td>
							<td><?php echo $fu['date'] ? '<span class="' . ( $fu['date'] <= $today ? 'ost-due' : '' ) . '">' . esc_html( ost_jdate_ymd( $fu['date'] ) ) . '</span>' : '<span class="ost-muted">—</span>'; ?></td>
							<td><span class="ost-muted"><?php echo esc_html( ost_ago( $p['modified'] ) ); ?></span></td>
							<td><span class="ost-avatar" title="<?php echo esc_attr( $au ? $au->display_name : '' ); ?>"><?php echo esc_html( $au ? mb_substr( $au->display_name, 0, 1 ) : '؟' ); ?></span></td>
							<td class="ost-row-act">
								<?php if ( ost_value( $p['id'] )['total'] ) : ?><a class="ost-icon-btn" target="_blank" href="<?php echo esc_url( ost_url( 'proposal/' . $p['id'] . '/deck' ) ); ?>" title="پیش‌نمایش"><?php echo ost_icon( 'eye', 16 ); // phpcs:ignore ?></a><?php endif; ?>
								<a class="ost-icon-btn" href="<?php echo esc_url( ost_action_url( 'ost_duplicate', array( 'id' => $p['id'] ) ) ); ?>" title="کپی برای کارفرمای دیگر"><?php echo ost_icon( 'copy', 16 ); // phpcs:ignore ?></a>
								<a class="ost-icon-btn is-danger" href="<?php echo esc_url( ost_action_url( 'ost_delete', array( 'id' => $p['id'] ) ) ); ?>" data-confirm="این پروپوزال و همه داده‌هایش حذف شود؟" title="حذف"><?php echo ost_icon( 'trash', 16 ); // phpcs:ignore ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="ost-muted ost-noresult" hidden>موردی پیدا نشد.</p>
			</div>
		</section>
	</div>

	<aside>
		<section class="ost-panel <?php echo $pipe['due'] ? 'is-attention' : ''; ?>">
			<h2><?php echo ost_icon( 'clock', 18 ); // phpcs:ignore ?> پیگیری‌های امروز</h2>
			<?php if ( $pipe['due'] ) : ?>
				<ul class="ost-due-list">
					<?php foreach ( array_slice( $pipe['due'], 0, 8 ) as $d ) : ?>
						<li><a href="<?php echo esc_url( ost_proposal_url( $d['p']['id'], 'activity' ) ); ?>"><b><?php echo esc_html( $d['p']['title'] ); ?></b><small class="<?php echo $d['late'] ? 'ost-due' : ''; ?>"><?php echo esc_html( ( $d['late'] ? 'عقب‌افتاده · ' : 'امروز · ' ) . ( $d['f']['note'] ?: ost_status_label( $d['p']['status'] )[0] ) ); ?></small></a></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="ost-muted">پیگیری عقب‌افتاده‌ای ندارید. برای هر پروپوزال ارسال‌شده از تب «پیگیری» یادآور بگذارید.</p>
			<?php endif; ?>
		</section>

		<section class="ost-panel">
			<h2>فعالیت‌های اخیر</h2>
			<ul class="ost-feed">
				<?php foreach ( $feed as $f ) : ?>
					<li class="t-<?php echo esc_attr( $f['e']['type'] ?? 'event' ); ?>"><a href="<?php echo esc_url( ost_proposal_url( $f['p']['id'], 'activity' ) ); ?>"><b><?php echo esc_html( $f['p']['title'] ); ?></b> <?php echo esc_html( mb_substr( $f['e']['text'], 0, 90 ) ); ?></a><small><?php echo esc_html( ost_ago( $f['e']['t'] ) ); ?></small></li>
				<?php endforeach; ?>
				<?php if ( ! $feed ) : ?><li class="ost-muted">هنوز فعالیتی ثبت نشده.</li><?php endif; ?>
			</ul>
		</section>

		<section class="ost-panel">
			<h2>منابع داده امروز</h2>
			<ul class="ost-sources">
				<li class="<?php echo ost_ss_connected() ? 'ok' : 'off'; ?>">سئو سیگنال: <?php echo esc_html( ost_num( ost_ss_remaining() ) ); ?> درخواست باقی‌مانده<?php echo $ssu['sokan'] ? ' (سکان امروز ' . esc_html( ost_num( $ssu['sokan'] ) ) . ')' : ''; ?></li>
				<li class="<?php echo ost_ai_connected() ? 'ok' : 'off'; ?>">هوش مصنوعی: <?php echo ost_ai_connected() ? 'متصل · ' . esc_html( ost_num( ost_ai_usage()['cost'], 2 ) ) . ' دلار این ماه' : 'متصل نیست'; ?></li>
				<li class="<?php echo ost_semrush_connected() ? 'ok' : 'off'; ?>">سمراش: <?php echo ost_semrush_connected() ? 'متصل' : 'هنوز متصل نیست'; ?></li>
				<li class="<?php echo ost_google_connected() ? 'ok' : 'off'; ?>">سرچ کنسول: <?php echo ost_google_connected() ? 'متصل' : 'متصل نیست'; ?></li>
			</ul>
		</section>

		<?php if ( $pipe['lost_reasons'] ) : ?>
			<section class="ost-panel">
				<h2>چرا پروپوزال‌ها رد شدند؟</h2>
				<ul class="ost-bars">
					<?php $lr_max = max( $pipe['lost_reasons'] ); foreach ( $pipe['lost_reasons'] as $r => $n ) : ?>
						<li><span><?php echo esc_html( ost_lost_reasons()[ $r ] ?? $r ); ?></span><i style="width:<?php echo (int) round( 100 * $n / $lr_max ); ?>%"></i><b><?php echo esc_html( ost_num( $n ) ); ?></b></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</aside>
</div>
<?php endif; ?>
