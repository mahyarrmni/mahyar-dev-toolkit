<?php
/**
 * Plugin Name:       404 Radar
 * Plugin URI:        https://github.com/mahyarrmni/404-radar
 * Description:       Catches 404 errors, suggests the closest matching page, and fixes them with one-click 301 redirects. Minimal, fast, bilingual (EN/FA).
 * Version:           1.1.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Mahyar Momeni
 * Author URI:        https://mahyarhub.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       404-radar
 */
defined('ABSPATH') || exit;

final class R404 {
	const T = 'r404_logs';

	static function t($en, $fa) { return str_starts_with(get_locale(), 'fa') ? $fa : $en; }
	static function tb() { global $wpdb; return $wpdb->prefix . self::T; }
	static function o() { return wp_parse_args(get_option('r404_opts', []), ['log' => 1, 'bots' => 1, 'auto' => 0, 'days' => 30, 'mail' => 0, 'rules' => '']); }

	static function boot() {
		register_activation_hook(__FILE__, [__CLASS__, 'install']);
		register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('r404_clean'); wp_clear_scheduled_hook('r404_mail'); });
		add_action('r404_mail', [__CLASS__, 'mail']);
		add_action('admin_post_r404_csv', [__CLASS__, 'csv']);
		add_action('template_redirect', [__CLASS__, 'catch404'], 1);
		add_filter('cron_schedules', fn($s) => $s + ['weekly' => ['interval' => WEEK_IN_SECONDS, 'display' => 'Weekly']]);
		add_action('r404_clean', [__CLASS__, 'clean']);
		add_action('admin_menu', [__CLASS__, 'menu']);
		add_action('admin_post_r404_row', [__CLASS__, 'row']);
		add_action('admin_post_r404_set', [__CLASS__, 'save']);
	}

	static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta('CREATE TABLE ' . self::tb() . ' (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			path varchar(191) NOT NULL,
			hits int unsigned NOT NULL DEFAULT 1,
			last_seen datetime NOT NULL,
			ref text NULL,
			target text NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY path (path)
		) ' . $wpdb->get_charset_collate() . ';');
		if (!wp_next_scheduled('r404_clean')) wp_schedule_event(time() + 3600, 'daily', 'r404_clean');
	}

	static function mail() {
		global $wpdb;
		$rows = $wpdb->get_results('SELECT path,hits FROM ' . self::tb() . " WHERE (target IS NULL OR target='') AND last_seen > '" . gmdate('Y-m-d H:i:s', time() - WEEK_IN_SECONDS) . "' ORDER BY hits DESC LIMIT 10");
		if (!$rows) return;
		$b = self::t("Top broken URLs this week:\n\n", "پربازدیدترین لینک‌های شکسته این هفته:\n\n");
		foreach ($rows as $r) $b .= "{$r->hits}×  " . urldecode($r->path) . "\n";
		wp_mail(get_option('admin_email'), '[' . get_bloginfo('name') . '] 404 Radar', $b . "\n" . admin_url('admin.php?page=r404'));
	}

	static function csv() {
		check_admin_referer('r404');
		current_user_can('manage_options') || wp_die();
		global $wpdb;
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=404-radar.csv');
		$h = fopen('php://output', 'w');
		fwrite($h, "\xEF\xBB\xBF");
		fputcsv($h, ['path', 'hits', 'last_seen', 'referrer', 'redirect_to']);
		foreach ($wpdb->get_results('SELECT * FROM ' . self::tb() . ' ORDER BY hits DESC', ARRAY_A) as $r)
			fputcsv($h, [urldecode($r['path']), $r['hits'], $r['last_seen'], $r['ref'], $r['target']]);
		exit;
	}

	static function clean() {
		global $wpdb;
		$wpdb->query($wpdb->prepare('DELETE FROM ' . self::tb() . " WHERE (target IS NULL OR target='') AND last_seen < %s", gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) self::o()['days'])));
	}

	/* ---------- Front: catch + redirect ---------- */
	static function catch404() {
		if (!is_404()) return;
		$o = self::o();
		global $wpdb;
		$tb = self::tb();
		$p = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
		$p = substr(sanitize_text_field(untrailingslashit(rawurldecode($p)) ?: '/'), 0, 190);
		if (preg_match('/\.(css|js|map|png|jpe?g|gif|svg|ico|webp|avif|woff2?|ttf|txt|xml)$/i', $p)) return;

		$row = $wpdb->get_row($wpdb->prepare("SELECT id,target FROM $tb WHERE path=%s", $p));
		if ($row && $row->target) { self::go($row->target); }

		foreach (preg_split('/\R/', (string) $o['rules']) as $ln) {
			if (!str_contains($ln, '=>')) continue;
			[$rx, $to] = array_map('trim', explode('=>', $ln, 2));
			if ($rx === '' || $to === '') continue;
			if (@preg_match('~' . str_replace('~', '\\~', $rx) . '~i', $p, $m) === 1) {
				self::go(preg_replace_callback('/\$(\d)/', fn($x) => $m[$x[1]] ?? '', $to));
			}
		}

		$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$bot = !$ua || preg_match('/bot|crawl|spider|slurp|bing|yandex|baidu|semrush|ahrefs/i', $ua);
		if ($o['log'] && !($o['bots'] && $bot)) {
			$now = current_time('mysql', true);
			$ref = esc_url_raw(wp_get_raw_referer() ?: '');
			if ($row) $wpdb->query($wpdb->prepare("UPDATE $tb SET hits=hits+1,last_seen=%s,ref=IF(%s='',ref,%s) WHERE id=%d", $now, $ref, $ref, $row->id));
			else $wpdb->insert($tb, ['path' => $p, 'last_seen' => $now, 'ref' => $ref]);
			$d = get_option('r404_daily', []);
			$k = gmdate('Y-m-d');
			$d[$k] = ($d[$k] ?? 0) + 1;
			ksort($d);
			update_option('r404_daily', array_slice($d, -14, null, true), false);
		}
		if ($o['auto'] && ($s = self::suggest($p))) { self::go($s); }
	}

	static function go($u) {
		if ($u[0] === '/') $u = home_url($u);
		wp_redirect(esc_url_raw($u), 301);
		exit;
	}

	static function suggest($path) {
		global $wpdb;
		$slug = rawurldecode(basename($path));
		if (strlen($slug) < 3) return '';
		$best = 0; $id = 0;
		foreach ($wpdb->get_results("SELECT ID,post_name FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page','product') ORDER BY ID DESC LIMIT 800") as $r) {
			similar_text($slug, rawurldecode($r->post_name), $pct);
			if ($pct > $best) { $best = $pct; $id = $r->ID; }
		}
		return $best >= 55 ? (string) get_permalink($id) : '';
	}

	/* ---------- Admin ---------- */
	static function menu() {
		add_menu_page('404 Radar', '404 Radar', 'manage_options', 'r404', [__CLASS__, 'page'], 'dashicons-location-alt', 80);
	}

	private static function back($tab = 'dash', $msg = 1) {
		wp_safe_redirect(admin_url("admin.php?page=r404&tab=$tab&done=$msg"));
		exit;
	}

	static function row() {
		check_admin_referer('r404');
		current_user_can('manage_options') || wp_die();
		global $wpdb;
		$id = (int) ($_POST['id'] ?? 0);
		$a = sanitize_key($_POST['act'] ?? '');
		if ($a === 'save') $wpdb->update(self::tb(), ['target' => esc_url_raw(wp_unslash($_POST['to'] ?? '')) ?: sanitize_text_field(wp_unslash($_POST['to'] ?? ''))], ['id' => $id]);
		if ($a === 'clear') $wpdb->update(self::tb(), ['target' => ''], ['id' => $id]);
		if ($a === 'del') $wpdb->delete(self::tb(), ['id' => $id]);
		if ($a === 'wipe') { $wpdb->query('DELETE FROM ' . self::tb() . " WHERE target IS NULL OR target=''"); delete_option('r404_daily'); }
		self::back();
	}

	static function save() {
		check_admin_referer('r404');
		current_user_can('manage_options') || wp_die();
		update_option('r404_opts', [
			'log' => !empty($_POST['log']) ? 1 : 0,
			'bots' => !empty($_POST['bots']) ? 1 : 0,
			'auto' => !empty($_POST['auto']) ? 1 : 0,
			'days' => max(1, min(365, (int) ($_POST['days'] ?? 30))),
			'mail' => !empty($_POST['mail']) ? 1 : 0,
			'rules' => sanitize_textarea_field(wp_unslash($_POST['rules'] ?? '')),
		]);
		wp_clear_scheduled_hook('r404_mail');
		if (!empty($_POST['mail'])) wp_schedule_event(time() + DAY_IN_SECONDS, 'weekly', 'r404_mail');
		self::back('set');
	}

	static function css() {
		return '<style>
.r4{--a:#6366f1;--b:#06b6d4;--t:#0f172a;--m:#64748b;--l:#e2e8f0;max-width:1000px;margin:24px auto;padding:0 12px;color:var(--t);font-size:14px}
.r4 *{box-sizing:border-box}
.r4 h1{font-size:26px;font-weight:800;margin:0;display:flex;align-items:center;gap:10px;padding:0}
.r4 h1 i{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--a),var(--b));display:inline-block;position:relative}
.r4 h1 i:after{content:"";position:absolute;inset:9px;border:3px solid #fff;border-radius:50%;border-top-color:transparent}
.r4 .sub{color:var(--m);margin:6px 0 20px}
.r4 nav{display:inline-flex;background:#fff;border:1px solid var(--l);border-radius:12px;padding:4px;margin-bottom:18px}
.r4 nav a{padding:8px 18px;border-radius:9px;text-decoration:none;color:var(--m);font-weight:600}
.r4 nav a.on{background:linear-gradient(135deg,var(--a),var(--b));color:#fff}
.r4 .g{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:16px}
.r4 .c{background:#fff;border:1px solid var(--l);border-radius:16px;padding:18px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.r4 .n{font-size:30px;font-weight:800;line-height:1}
.r4 .k{color:var(--m);margin-top:6px;font-size:12px}
.r4 .bars{display:flex;align-items:flex-end;gap:6px;height:90px;margin-top:12px}
.r4 .bars b{flex:1;border-radius:6px 6px 2px 2px;background:linear-gradient(180deg,var(--a),var(--b));min-height:3px;opacity:.9}
.r4 table{width:100%;border-collapse:collapse}
.r4 th{text-align:start;color:var(--m);font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:8px 6px}
.r4 td{padding:10px 6px;border-top:1px solid var(--l);vertical-align:middle}
.r4 code{background:#f1f5f9;padding:3px 7px;border-radius:6px;font-size:12px;word-break:break-all}
.r4 input[type=text],.r4 input[type=number]{border:1px solid var(--l);border-radius:10px;padding:7px 10px;min-width:180px;width:100%}
.r4 .f{display:flex;gap:6px;align-items:center}
.r4 button,.r4 .btn{border:0;border-radius:10px;padding:8px 14px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,var(--a),var(--b));color:#fff}
.r4 button.s{background:#f1f5f9;color:var(--m)}
.r4 .ok{display:inline-block;background:#dcfce7;color:#166534;border-radius:99px;padding:2px 9px;font-size:11px;font-weight:700}
.r4 .row{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 0;border-top:1px solid var(--l)}
.r4 .row:first-child{border:0}
.r4 .row small{display:block;color:var(--m);margin-top:3px}
.r4 .sw{position:relative;width:46px;height:26px;flex:none}
.r4 .sw input{opacity:0;position:absolute;inset:0;z-index:2;cursor:pointer;margin:0}
.r4 .sw span{position:absolute;inset:0;background:#cbd5e1;border-radius:99px;transition:.2s}
.r4 .sw span:after{content:"";position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.2s}
.r4 .sw input:checked+span{background:linear-gradient(135deg,var(--a),var(--b))}
.r4 .sw input:checked+span:after{inset-inline-start:23px}
.r4 .toast{background:#0f172a;color:#fff;border-radius:12px;padding:10px 16px;margin-bottom:14px;display:inline-block}
.r4 .empty{text-align:center;color:var(--m);padding:40px 0}
</style>';
	}

	static function page() {
		if (!current_user_can('manage_options')) return;
		global $wpdb;
		$tb = self::tb();
		$tab = ($_GET['tab'] ?? 'dash') === 'set' ? 'set' : 'dash';
		$u = admin_url('admin.php?page=r404');
		$t = [__CLASS__, 't'];
		echo self::css() . '<div class="r4"><h1><i></i>404 Radar</h1><p class="sub">' . esc_html($t('Catch broken links. Fix them in one click.', 'لینک‌های شکسته رو بگیر و با یه کلیک درستشون کن.')) . '</p>';
		if (isset($_GET['done'])) echo '<div class="toast">✓ ' . esc_html($t('Saved', 'ذخیره شد')) . '</div><br>';
		echo '<nav><a class="' . ($tab === 'dash' ? 'on' : '') . '" href="' . esc_url($u) . '">' . esc_html($t('Dashboard', 'داشبورد')) . '</a><a class="' . ($tab === 'set' ? 'on' : '') . '" href="' . esc_url("$u&tab=set") . '">' . esc_html($t('Settings', 'تنظیمات')) . '</a></nav>';

		if ($tab === 'set') {
			$o = self::o();
			$sw = fn($k, $ti, $de) => '<div class="row"><div><b>' . esc_html($ti) . '</b><small>' . esc_html($de) . '</small></div><label class="sw"><input type="checkbox" name="' . $k . '" value="1" ' . checked($o[$k], 1, false) . '><span></span></label></div>';
			echo '<form class="c" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r404_set">';
			wp_nonce_field('r404');
			echo $sw('log', $t('Log 404 errors', 'ثبت خطاهای ۴۰۴'), $t('Record every broken URL visitors hit.', 'هر آدرس شکسته‌ای که بازدیدکننده می‌زنه ثبت بشه.'));
			echo $sw('bots', $t('Ignore bots', 'نادیده گرفتن ربات‌ها'), $t('Skip crawlers so your list stays clean.', 'کراولرها ثبت نشن تا لیست تمیز بمونه.'));
			echo $sw('auto', $t('Smart auto-redirect', 'ریدایرکت هوشمند خودکار'), $t('Automatically send visitors to the closest matching page (301).', 'بازدیدکننده خودکار به نزدیک‌ترین صفحه هدایت بشه (۳۰۱).'));
			echo $sw('mail', $t('Weekly email report', 'گزارش ایمیلی هفتگی'), $t('Top 10 unfixed broken links, sent to the admin email.', 'ده لینک شکسته پربازدید به ایمیل مدیر ارسال می‌شه.'));
			echo '<div class="row" style="display:block"><b>' . esc_html($t('Regex rules', 'قوانین Regex')) . '</b><small style="margin-bottom:8px">' . esc_html($t('One per line: pattern => target. Use $1 for groups. Example: ^/blog/(.*) => /news/$1', 'هر خط یک قانون: الگو => مقصد. برای گروه‌ها از $1 استفاده کن. مثال: ^/blog/(.*) => /news/$1')) . '</small><textarea name="rules" rows="5" style="width:100%;border:1px solid #e2e8f0;border-radius:10px;font-family:monospace;direction:ltr" placeholder="^/old/(.*) => /new/$1">' . esc_textarea($o['rules']) . '</textarea></div>';
			echo '<div class="row"><div><b>' . esc_html($t('Keep logs for (days)', 'نگهداری لاگ‌ها (روز)')) . '</b><small>' . esc_html($t('Unfixed entries older than this are deleted.', 'موارد رفع‌نشده قدیمی‌تر از این حذف می‌شن.')) . '</small></div><input type="number" style="width:90px;min-width:0" name="days" min="1" max="365" value="' . (int) $o['days'] . '"></div>';
			echo '<button>' . esc_html($t('Save changes', 'ذخیره تغییرات')) . '</button></form></div>';
			return;
		}

		$tot = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tb");
		$hits = (int) $wpdb->get_var("SELECT COALESCE(SUM(hits),0) FROM $tb");
		$fixed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tb WHERE target<>''");
		$d = get_option('r404_daily', []);
		$mx = max(1, $d ? max($d) : 1);
		echo '<div class="g">';
		foreach ([[$tot, $t('Broken URLs', 'آدرس شکسته')], [$hits, $t('Total hits', 'کل بازدید خطا')], [$fixed, $t('Fixed', 'رفع‌شده')], [max(0, $tot - $fixed), $t('Need attention', 'نیازمند رسیدگی')]] as $s)
			echo '<div class="c"><div class="n">' . number_format_i18n($s[0]) . '</div><div class="k">' . esc_html($s[1]) . '</div></div>';
		echo '</div><div class="c" style="margin-bottom:16px"><b>' . esc_html($t('Last 14 days', '۱۴ روز اخیر')) . '</b><div class="bars">';
		foreach ($d as $day => $n) echo '<b title="' . esc_attr("$day: $n") . '" style="height:' . max(4, round($n / $mx * 100)) . '%"></b>';
		echo '</div></div><div class="c">';

		$rows = $wpdb->get_results("SELECT * FROM $tb ORDER BY (target<>'') ASC, hits DESC LIMIT 50");
		if (!$rows) { echo '<div class="empty">🎉 ' . esc_html($t('No 404s yet. Your site is healthy.', 'هنوز ۴۰۴ ثبت نشده. سایتت سالمه.')) . '</div></div></div>'; return; }
		echo '<table><tr><th>' . esc_html($t('URL', 'آدرس')) . '</th><th>' . esc_html($t('Hits', 'تعداد')) . '</th><th>' . esc_html($t('Redirect to', 'ریدایرکت به')) . '</th></tr>';
		foreach ($rows as $r) {
			$sg = $r->target ? '' : self::suggest($r->path);
			echo '<tr><td><code>' . esc_html(urldecode($r->path)) . '</code>' . ($r->ref ? '<br><small style="color:#94a3b8">↩ ' . esc_html(wp_parse_url($r->ref, PHP_URL_HOST) ?: '') . '</small>' : '') . '</td><td><b>' . (int) $r->hits . '</b></td><td>';
			echo '<form class="f" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r404_row"><input type="hidden" name="id" value="' . (int) $r->id . '">';
			wp_nonce_field('r404');
			echo '<input type="text" name="to" value="' . esc_attr($r->target ?: $sg) . '" placeholder="/new-page">';
			echo '<button name="act" value="save">' . ($r->target ? '✓' : esc_html($t('Fix', 'رفع'))) . '</button>';
			if ($r->target) echo '<button class="s" name="act" value="clear" title="' . esc_attr($t('Remove redirect', 'حذف ریدایرکت')) . '">↺</button>';
			echo '<button class="s" name="act" value="del" title="' . esc_attr($t('Delete', 'حذف')) . '">✕</button></form>';
			if ($sg) echo '<small style="color:#6366f1">✨ ' . esc_html($t('Suggested match', 'پیشنهاد هوشمند')) . '</small>';
			echo '</td></tr>';
		}
		echo '</table><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:14px"><input type="hidden" name="action" value="r404_row"><input type="hidden" name="id" value="0">';
		wp_nonce_field('r404');
		echo '<a class="btn" style="text-decoration:none;display:inline-block;margin-inline-end:6px" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=r404_csv'), 'r404')) . '">⬇ CSV</a><button class="s" name="act" value="wipe" onclick="return confirm(\'?\')">' . esc_html($t('Clear unfixed logs', 'پاک‌کردن لاگ‌های رفع‌نشده')) . '</button></form></div></div>';
	}
}
R404::boot();
