<?php
/**
 * Plugin Name: کنیبالیزیشن محتوا (Content Cannibalization Scanner)
 * Description: کنیبالیزیشن محتوا + (جدید ۲.۱) گزارش کامل سایت: اسکن بدافزار، تمامیت فایل‌های هسته/افزونه، امنیت، سئو، محتوا و کارایی.
 * Version:     2.2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:      Mahyar Momeni
 * Plugin URI: https://github.com/mahyarrmni/content-cannibalization-scanner
 * Author URI: https://mahyarhub.ir
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-scanner
 * License:     GPL v2 or later
 *
 * نسخه ۲.۲ — اطلاعات تهدید بیرونی + اعتماد به رفع خودکار
 * ------------------------------------------------------------------------
 *  - آسیب‌پذیری افزونه/قالب/هسته: WPVulnerability.net (بدون کلید) یا Wordfence Intelligence v3 (کلید رایگان).
 *  - Google Safe Browsing (با کلید API شما): بررسی علامت‌خوردن سایت توسط گوگل.
 *  - امضاهای قابل به‌روزرسانی از آدرس HTTPS ثابت: فید امضاشده با Ed25519، سنجش ReDoS، ضد downgrade،
 *    امکان غیرفعال‌کردن امضای مثبت کاذب و هش بدافزارهای شناخته‌شده (tools/feed-tool.php).
 *  - پیش‌نمایش (Dry-run) قبل از رفع؛ هر رفع یک «اجرا» با بکاپ فشرده‌ی zip است و کل اجرا با یک دکمه برمی‌گردد.
 *
 * نسخه ۲.۱ — گزارش کامل سایت و اسکن بدافزار (منوی «گزارش سایت و بدافزار»)
 * ------------------------------------------------------------------------
 * فقط‌خواندنی است (هیچ فایلی حذف/ویرایش نمی‌شود). مرحله‌ای/AJAX است و Timeout نمی‌دهد.
 *  - بدافزار: تطبیق فایل‌های هسته و افزونه‌های مخزن رسمی با checksum اصلی wordpress.org؛
 *    امضای وب‌شل/eval/base64/preg_replace-e/ماینر؛ PHP داخل uploads و داخل تصاویر؛
 *    .htaccess و .user.ini مخرب؛ مدیر مخفی؛ drop-in/mu-plugin؛ کرون مشکوک؛
 *    اسکریپت/iframe/لینک مخفی و اسپم داخل نوشته‌ها و تنظیمات دیتابیس؛
 *    Cloaking و ریدایرکت شرطی (مقایسه‌ی صفحه برای گوگل‌بات/کاربر/ورودی از گوگل).
 *  - امنیت/تنظیمات: نسخه‌ی وردپرس/PHP/افزونه‌ها، wp-config، دسترسی‌ها، کاربران، SSL، هدرها،
 *    فایل‌های لو رفته (.env/.git/debug.log/بکاپ)، Directory Listing، XML-RPC، REST users.
 *  - سئو/محتوا: noindex سراسری، robots.txt، sitemap، Soft-404، ریدایرکت https، title/meta/canonical،
 *    عنوان تکراری، محتوای کوتاه، تصویر بدون alt و ...
 *  - کارایی: autoload، revision، transient، postmeta یتیم، کرون.
 *  - رفع خودکار (اختیاری): قرنطینه‌ی برگشت‌پذیر فایل‌های مخرب، جایگزینی فایل هسته/افزونه با نسخه‌ی رسمی
 *    (با تایید checksum)، پاک‌سازی نوشته‌ها با بکاپ، حذف خطوط مخرب .htaccess؛ سنجش سلامت سایت و بازگشت خودکار.
 *  - خروجی CSV/JSON/چاپی، علامت «ایمن است»، اسکن روزانه‌ی اختیاری، اعلان ایمیل/تلگرام.
 *  - هشدار: هیچ اسکنر PHP نمی‌تواند همه‌ی بدافزارها را بگیرد؛ مثبت کاذب ممکن است.
 *
 * نسخه ۱.۹ — پایداری اسکن، ذخیره‌سازی مقیاس‌پذیر و رفع خطاها
 * ------------------------------------------------------------------------
 * ذخیره‌سازی و کارایی
 *  - ردیف‌های ایندکس دیگر در «یک» گزینه‌ی بزرگ wp_options نگهداری نمی‌شوند؛ به
 *    شاردهای ۵۰‌تایی تقسیم شده‌اند. روی هاست‌هایی که max_allowed_packet کوچک دارند
 *    (مثلاً ۴ مگابایت) ذخیره‌ی ایندکس بی‌صدا شکست می‌خورد و اسکن نتیجه‌ای نداشت؛
 *    هر مرحله هم به‌جای بازنویسی کل ایندکس، فقط شاردهای مربوط را می‌نویسد. ایندکس
 *    قدیمی ۱.۸ هنگام ارتقا خودکار منتقل می‌شود.
 *  - هر مرحله‌ی اسکن بودجه‌ی زمانی دارد (پیش‌فرض ۲۰ ثانیه، فیلتر cs_step_time_budget)؛
 *    اگر بررسی زنده‌ی HTTP یا canonical کند شد، مرحله زودتر تمام می‌شود و بقیه در
 *    مرحله‌ی بعد ادامه می‌یابد (Timeout نمی‌دهد).
 *  - مرحله‌ی «تحلیل شباهت» و «canonical» فقط ردیف‌های لازم را از دیتابیس می‌خوانند،
 *    نه کل ایندکس را در هر مرحله. شمارنده‌های وضعیت با یک کوئری GROUP BY ساخته می‌شوند.
 *
 * درستی نتایج
 *  - تعارض‌هایی که در اسکن جدید دیگر دیده نمی‌شوند (مقاله‌ها اصلاح شده‌اند یا امتیاز
 *    زیر آستانه رفته) خودکار «حل‌شده» علامت می‌خورند؛ قبلاً برای همیشه «جدید»
 *    می‌ماندند و آمار داشبورد و اعلان‌ها را باد می‌کردند.
 *  - «حذف و ریدایرکت» فقط وقتی مقاله‌ی مقصد منتشرشده باشد انجام می‌شود (قبلاً
 *    می‌شد به پیش‌نویس/سطل زباله ریدایرکت شود) و دسترسی حذف مقاله را هم بررسی می‌کند.
 *  - تشخیص لینک داخلی بر پایه‌ی هاست است: لینک http/https یا با/بدون www هم داخلی
 *    حساب می‌شود و دامنه‌هایی مثل example.com.evil.com دیگر داخلی شمرده نمی‌شوند.
 *  - خواندن canonical/noindex صفحه: ترتیب صفت‌ها (href قبل از rel) مهم نیست، فقط
 *    داخل <head> جست‌وجو می‌شود و هدر X-Robots-Tag هم بررسی می‌شود. خطای شبکه
 *    فقط ۱۰ دقیقه کش می‌شود (قبلاً ۱۲ ساعت).
 *  - sitemap: در نبودن آدرس دستی، wp-sitemap.xml و sitemap_index.xml و sitemap.xml
 *    امتحان می‌شوند (Yoast/Rank Math/AIOSEO/SEOPress) و سقف واکشی بیشتر شد.
 *  - زمان‌ها (آخرین اسکن، تاریخ تعارض، اعلان) اکنون با منطقه‌ی زمانی سایت نمایش
 *    داده می‌شوند، نه UTC.
 *
 * پایداری اسکن
 *  - قفل بین AJAX و کرون: دو مرحله هم‌زمان اجرا نمی‌شوند (قبلاً df و شمارنده‌ها
 *    دوبار شمرده می‌شد).
 *  - اسکن گیرکرده: دکمه‌ی «لغو اسکن»، تشخیص اسکن راکد (۱۵ دقیقه بدون پیشرفت)،
 *    توقف بعد از ۳ خطای پیاپی با نمایش پیام خطا، و سقف تلاش مجدد در مرورگر
 *    (قبلاً با هر خطای شبکه تا ابد هر ۲ ثانیه درخواست می‌رفت).
 *  - اسکن شروع‌شده از صفحه‌ی تنظیمات، اگر تب بسته شود در پس‌زمینه (کرون) ادامه می‌یابد.
 *
 * امنیت و سازگاری
 *  - صفحه‌ی مقایسه فقط برای کسانی باز می‌شود که حق ویرایش «هر دو» مقاله را دارند
 *    (قبلاً محتوای هر مقاله‌ای، حتی خصوصی، برای هر ویرایشگر قابل دیدن بود).
 *  - سازگار با PHP 8.4 (پارامتر nullable صریح)، پشتیبانی از پلی‌لنگ در کوئری اسکن،
 *    نام جدول AIOSEO بدون حساسیت به حروف، و فایل حذف (uninstall) که همه‌ی
 *    گزینه‌ها و ترنزینت‌ها را پاک می‌کند.
 *  - صفحه‌ی مقایسه همیشه مقاله‌ی با شناسه‌ی کوچک‌تر را «الف» می‌گیرد تا با {A}/{B}
 *    در متن دلایل یکی باشد.
 *
 * (۱.۸: باکس تداخل در ویرایشگر، مقایسه‌ی کنار هم، اعلان ایمیل/تلگرام، ویجت داشبورد،
 * خروجی CSV/PDF، ریشه‌یابی، پشتیبانی AIOSEO/SEOPress، وزن‌های قابل‌ویرایش + پیش‌نمایش.
 * ۱.۷: اسکن مرحله‌ای/AJAX و «حذف و ریدایرکت» با رنک‌مث. ۱.۶: رفع خطای WP_List_Table.)
 *
 * نکته‌ی ارتقا: به‌خاطر اصلاح تعارض‌های راکد، بعد از اولین اسکن ۱.۹ ممکن است
 * تعدادی از موارد قدیمی «حل‌شده» شوند؛ این طبیعی است.
 *
 * محدودیت‌های شناخته‌شده:
 *  - جدول‌های df / انکر / ایندکس کاندیدها هنوز در wp_options‌اند (کوچک‌تر از ردیف‌ها)؛
 *    برای سایت‌های خیلی بزرگ (ده‌ها هزار مقاله) مهاجرت به جدول اختصاصی پیشنهاد می‌شود.
 *  - یکپارچگی رنک‌مث و AIOSEO به ساختار داخلی آن افزونه‌ها وابسته است.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CS_VERSION', '2.2' );
define( 'CS_FILE', __FILE__ );

/* =========================================================================
 * نصب / حذف / کرون
 * ====================================================================== */

register_activation_hook( __FILE__, 'cs_activate' );
if ( ! function_exists( 'cs_activate' ) ) {
function cs_activate() {
	cs_create_table();
	if ( ! wp_next_scheduled( 'cs_daily_scan' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'cs_daily_scan' );
	}
	update_option( 'cs_schema_version', CS_Schema::VERSION, false );
}
}

register_deactivation_hook( __FILE__, 'cs_deactivate' );
if ( ! function_exists( 'cs_deactivate' ) ) {
function cs_deactivate() {
	wp_clear_scheduled_hook( 'cs_daily_scan' );
	wp_clear_scheduled_hook( 'cs_scan_async_step' );
	wp_clear_scheduled_hook( 'cs_audit_daily' );
	wp_clear_scheduled_hook( 'cs_audit_step' );
	wp_clear_scheduled_hook( 'cs_audit_feed_update' );
}
}

register_uninstall_hook( __FILE__, 'cs_uninstall' );
if ( ! function_exists( 'cs_uninstall' ) ) {
function cs_uninstall() {
	global $wpdb;
	$tb = $wpdb->prefix . 'cs_conflicts';
	$wpdb->query( "DROP TABLE IF EXISTS {$tb}" ); // phpcs:ignore
	$opts = array(
		'cs_settings', 'cs_meta', 'cs_schema_version', 'cs_run_seq', 'cs_last_scan',
		'cs_scan_state', 'cs_scan_lock', 'cs_scan_last_error', 'cs_history', 'cs_notify_last',
	);
	foreach ( $opts as $o ) {
		delete_option( $o );
	}
	// همه‌ی blobهای ایندکس (از جمله شاردهای ردیف‌ها) و ترنزینت‌های واکشی
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'cs_blob_' ) . '%',
		$wpdb->esc_like( '_transient_cs_fetch_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cs_fetch_' ) . '%'
	) );
	wp_clear_scheduled_hook( 'cs_daily_scan' );
	wp_clear_scheduled_hook( 'cs_scan_async_step' );
	// ۲.۱: گزارش سایت/بدافزار
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'cs_audit_' ) . '%'
	) );
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_cs_vuln_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cs_vuln_' ) . '%'
	) );
	wp_clear_scheduled_hook( 'cs_audit_daily' );
	wp_clear_scheduled_hook( 'cs_audit_step' );
	wp_clear_scheduled_hook( 'cs_audit_feed_update' );
}
}

if ( ! function_exists( 'cs_create_table' ) ) {
function cs_create_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$tb      = $wpdb->prefix . 'cs_conflicts';
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$tb} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		type VARCHAR(20) NOT NULL,
		post_a BIGINT UNSIGNED NOT NULL,
		post_b BIGINT UNSIGNED NOT NULL DEFAULT 0,
		score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		reasons LONGTEXT NULL,
		suggestions LONGTEXT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'new',
		scan_run BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		notified_at DATETIME NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY cs_pair_unique (type, post_a, post_b),
		KEY status (status),
		KEY score (score)
	) {$charset};";
	dbDelta( $sql );
}
}

if ( ! function_exists( 'cs_start_async_scan' ) ) {
	function cs_start_async_scan() {
		CS_Scan_Async::start();
		if ( ! wp_next_scheduled( 'cs_scan_async_step' ) ) {
			wp_schedule_single_event( time() + 5, 'cs_scan_async_step' );
		}
	}
}
if ( ! function_exists( 'cs_run_async_scan_step' ) ) {
	function cs_run_async_scan_step() {
		$r = CS_Scan_Async::step();
		if ( empty( $r['done'] ) ) {
			wp_schedule_single_event( time() + 5, 'cs_scan_async_step' );
		}
	}
}

if ( function_exists( 'add_action' ) ) {
	// اسکن روزانه هم مرحله‌ای (chunked) اجرا می‌شود تا حتی در پس‌زمینه هم به Timeout نخورد
	add_action( 'cs_daily_scan', 'cs_start_async_scan' );
	add_action( 'cs_scan_async_step', 'cs_run_async_scan_step' );
}

/* =========================================================================
 * ابزارهای عمومی: نام جدول، تنظیمات، ذخیره‌ی blob، نرمال‌سازی آدرس‌ها
 * ====================================================================== */
if ( ! class_exists( 'CS_Util', false ) ) {
final class CS_Util {

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'cs_' . preg_replace( '/[^a-z0-9_]/', '', (string) $name );
	}

	public static function json( $data ) {
		$j = wp_json_encode( $data );
		return false === $j ? '[]' : $j;
	}

	public static function clamp( $v, $min = 0.0, $max = 1.0 ) {
		$v = (float) $v;
		if ( $v < $min ) {
			return $min;
		}
		if ( $v > $max ) {
			return $max;
		}
		return $v;
	}

	public static function hash32( $s ) {
		return (int) sprintf( '%u', crc32( (string) $s ) );
	}

	private static $settings = null;

	public static function settings( $fresh = false ) {
		if ( null === self::$settings || $fresh ) {
			$defaults = array(
				'post_types'  => array( 'post' ),
				'min_score'   => 30,
				'weights'          => array(),
				'live_check'       => false,
				'sitemap_url'      => '',
				'max_posts'        => 3000,
				'batch_size'       => 50,    // تعداد مقاله در هر مرحله‌ی اسکن (برای جلوگیری از Timeout)
				'permanent_delete' => false, // false = انتقال به سطل زباله، true = حذف کامل و غیرقابل بازگشت
				'stemming'         => true,  // ریشه‌یابی واژه‌ها (۱.۸)
				'notify_on'        => false, // اعلان موارد بحرانی جدید (۱.۸)
				'notify_min'       => 80,
				'notify_email_on'  => true,
				'notify_emails'    => '',
				'notify_tg_on'     => false,
				'tg_token'         => '',
				'tg_chat'          => '',
				'tg_base'          => 'https://api.telegram.org',
			);
			$saved           = get_option( 'cs_settings', array() );
			self::$settings  = array_merge( $defaults, is_array( $saved ) ? $saved : array() );
		}
		return self::$settings;
	}

	public static function update_settings( array $data ) {
		$s = array_merge( self::settings( true ), $data );
		update_option( 'cs_settings', $s, false );
		self::$settings = $s;
		return $s;
	}

	/** سطح دسترسی دیدن باکس مقاله/مقایسه/ویجت (سردبیرها هم)؛ مدیریت و تنظیمات همچنان manage_options */
	public static function view_cap() {
		return (string) apply_filters( 'cs_view_capability', 'edit_others_posts' );
	}

	/** عنوان مقاله به‌صورت متن ساده (بدون HTML entity) */
	public static function title( $id ) {
		$t = html_entity_decode( wp_strip_all_tags( (string) get_the_title( (int) $id ) ), ENT_QUOTES, 'UTF-8' );
		return ( '' !== trim( $t ) ) ? $t : '(بدون عنوان) #' . (int) $id;
	}

	/** نمایش زمان (timestamp واقعی UTC) با منطقه‌ی زمانی سایت؛ از date_i18n می‌گذرد تا افزونه‌های تاریخ شمسی هم کار کنند */
	public static function local_date( $format, $ts ) {
		$ts  = (int) $ts;
		$off = 0;
		if ( function_exists( 'wp_timezone' ) ) {
			try {
				$off = (int) wp_timezone()->getOffset( new DateTime( '@' . $ts ) );
			} catch ( \Exception $e ) {
				$off = 0;
			}
		} else {
			$off = (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
		}
		return date_i18n( (string) $format, $ts + $off );
	}

	/** آیا آدرس مطلق متعلق به همین سایت است؟ (بر پایه‌ی هاست؛ scheme و www نادیده گرفته می‌شود) */
	public static function is_internal( $abs_url ) {
		static $home_host = null;
		if ( null === $home_host ) {
			$h         = wp_parse_url( home_url(), PHP_URL_HOST );
			$home_host = $h ? preg_replace( '/^www\./i', '', strtolower( (string) $h ) ) : '';
		}
		$host = wp_parse_url( (string) $abs_url, PHP_URL_HOST );
		if ( ! $host || '' === $home_host ) {
			return false;
		}
		return preg_replace( '/^www\./i', '', strtolower( (string) $host ) ) === $home_host;
	}

	public static function get_blob( $key ) {
		$v = get_option( 'cs_blob_' . $key, null );
		return is_array( $v ) ? $v : array();
	}

	public static function set_blob( $key, array $value ) {
		return update_option( 'cs_blob_' . $key, $value, false );
	}

	/** آدرس نسبی/protocol-relative را به آدرس مطلق تبدیل می‌کند */
	public static function abs_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );
			return ( $scheme ? $scheme : 'https' ) . ':' . $url;
		}
		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}
		return home_url( '/' . ltrim( $url, '/' ) );
	}

	/** از یک آدرس نرمال‌شده (خروجی CS_Compat::norm_url) فقط بخش path نرمال را برمی‌گرداند */
	public static function url_key( $normalized_url ) {
		$path = (string) wp_parse_url( (string) $normalized_url, PHP_URL_PATH );
		return CS_Compat::norm_path( $path );
	}

	/** کلید «سست» برای مقایسه/کش (بدون scheme، بدون www، بدون اسلش انتهایی) */
	public static function loose_url( $normalized_url ) {
		$p    = wp_parse_url( (string) $normalized_url );
		$host = isset( $p['host'] ) ? preg_replace( '/^www\./', '', strtolower( $p['host'] ) ) : '';
		$path = isset( $p['path'] ) ? rtrim( $p['path'], '/' ) : '';
		return $host . $path;
	}

	/** تفاوت‌های دو آدرس را برمی‌گرداند: scheme, host, www, path, slash, query */
	public static function compare_urls( $a, $b ) {
		$pa   = wp_parse_url( (string) $a );
		$pb   = wp_parse_url( (string) $b );
		$diff = array();

		$sa = isset( $pa['scheme'] ) ? strtolower( $pa['scheme'] ) : '';
		$sb = isset( $pb['scheme'] ) ? strtolower( $pb['scheme'] ) : '';
		if ( $sa && $sb && $sa !== $sb ) {
			$diff[] = 'scheme';
		}

		$ha   = isset( $pa['host'] ) ? strtolower( $pa['host'] ) : '';
		$hb   = isset( $pb['host'] ) ? strtolower( $pb['host'] ) : '';
		$bare_a = preg_replace( '/^www\./', '', $ha );
		$bare_b = preg_replace( '/^www\./', '', $hb );
		if ( $bare_a !== $bare_b ) {
			$diff[] = 'host';
		} elseif ( $ha !== $hb ) {
			$diff[] = 'www';
		}

		$pta = isset( $pa['path'] ) ? $pa['path'] : '';
		$ptb = isset( $pb['path'] ) ? $pb['path'] : '';
		if ( rtrim( $pta, '/' ) !== rtrim( $ptb, '/' ) ) {
			$diff[] = 'path';
		} elseif ( $pta !== $ptb ) {
			$diff[] = 'slash';
		}

		$qa = isset( $pa['query'] ) ? $pa['query'] : '';
		$qb = isset( $pb['query'] ) ? $pb['query'] : '';
		if ( $qa !== $qb ) {
			$diff[] = 'query';
		}

		return $diff;
	}
}
}

/* =========================================================================
 * شباهت متن
 * ====================================================================== */
if ( ! class_exists( 'CS_Text', false ) ) {
final class CS_Text {

	public static function jaccard( array $a, array $b ) {
		$a = array_values( array_unique( array_filter( $a, 'strlen' ) ) );
		$b = array_values( array_unique( array_filter( $b, 'strlen' ) ) );
		if ( ! $a || ! $b ) {
			return 0.0;
		}
		$i = count( array_intersect( $a, $b ) );
		$u = count( array_unique( array_merge( $a, $b ) ) );
		return $u > 0 ? $i / $u : 0.0;
	}

	public static function text_sim( $a, $b ) {
		$a = (string) $a;
		$b = (string) $b;
		if ( '' === $a || '' === $b ) {
			return 0.0;
		}
		if ( $a === $b ) {
			return 1.0;
		}
		$pct = 0.0;
		similar_text( $a, $b, $pct );
		$j = self::jaccard( explode( ' ', $a ), explode( ' ', $b ) );
		return max( $pct / 100, $j );
	}
}
}

/* =========================================================================
 * واکشی صفحات خارجی (برای بررسی canonical مقصد) با کش
 * ====================================================================== */
if ( ! class_exists( 'CS_Fetch', false ) ) {
final class CS_Fetch {

	public static function page_cached( $url ) {
		$key    = 'cs_fetch_' . md5( (string) $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$result = self::fetch( $url );
		$st     = (int) $result['status'];
		// خطای شبکه/DNS را کوتاه کش می‌کنیم تا یک قطعی موقت ۱۲ ساعت نتیجه را خراب نکند
		if ( 0 === $st ) {
			$ttl = 10 * MINUTE_IN_SECONDS;
		} elseif ( $st >= 500 ) {
			$ttl = HOUR_IN_SECONDS;
		} else {
			$ttl = 12 * HOUR_IN_SECONDS;
		}
		set_transient( $key, $result, $ttl );
		return $result;
	}

	/** پاک‌کردن همه‌ی نتیجه‌های کش‌شده‌ی واکشی (هنگام ارتقا) */
	public static function purge() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_cs_fetch_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_cs_fetch_' ) . '%'
		) );
	}

	/** صفت‌های یک تگ HTML تک (کوچک‌حرف‌شده‌ی نام صفت) */
	private static function attrs( $tag ) {
		$out = array();
		if ( preg_match_all( '#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#', (string) $tag, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				if ( isset( $x[4] ) && '' !== $x[4] ) {
					$v = $x[4];
				} elseif ( isset( $x[3] ) && '' !== $x[3] ) {
					$v = $x[3];
				} else {
					$v = isset( $x[2] ) ? $x[2] : '';
				}
				$out[ strtolower( $x[1] ) ] = $v;
			}
		}
		return $out;
	}

	private static function fetch( $url ) {
		$out = array( 'status' => 0, 'canonicals' => array(), 'noindex' => false, 'location' => '' );
		if ( '' === trim( (string) $url ) ) {
			return $out;
		}
		$res = wp_safe_remote_get( $url, array(
			'timeout'             => 8,
			'redirection'         => 0,
			'limit_response_size' => 512 * 1024,
			'user-agent'          => 'ContentCannibalizationScanner/' . CS_VERSION,
		) );
		if ( is_wp_error( $res ) ) {
			return $out;
		}
		$code          = (int) wp_remote_retrieve_response_code( $res );
		$out['status'] = $code;
		if ( $code >= 300 && $code < 400 ) {
			$out['location'] = (string) wp_remote_retrieve_header( $res, 'location' );
			return $out;
		}
		if ( 200 !== $code ) {
			return $out;
		}
		foreach ( (array) wp_remote_retrieve_header( $res, 'x-robots-tag' ) as $h ) {
			if ( false !== stripos( (string) $h, 'noindex' ) ) {
				$out['noindex'] = true;
			}
		}
		$body = wp_remote_retrieve_body( $res );
		if ( '' === $body ) {
			return $out;
		}
		$pos  = stripos( $body, '</head>' );
		$head = ( false !== $pos ) ? substr( $body, 0, $pos ) : substr( $body, 0, 200000 );

		if ( preg_match_all( '#<link\b[^>]*>#i', $head, $lm ) ) {
			foreach ( $lm[0] as $tag ) {
				$a = self::attrs( $tag );
				if ( empty( $a['rel'] ) || empty( $a['href'] ) || '' === trim( $a['href'] ) ) {
					continue;
				}
				$rels = preg_split( '/\s+/', strtolower( trim( $a['rel'] ) ) );
				if ( in_array( 'canonical', (array) $rels, true ) ) {
					$out['canonicals'][] = html_entity_decode( trim( $a['href'] ), ENT_QUOTES, 'UTF-8' );
				}
			}
		}
		if ( preg_match_all( '#<meta\b[^>]*>#i', $head, $mm ) ) {
			foreach ( $mm[0] as $tag ) {
				$a = self::attrs( $tag );
				if ( empty( $a['name'] ) || ! isset( $a['content'] ) ) {
					continue;
				}
				if ( in_array( strtolower( trim( $a['name'] ) ), array( 'robots', 'googlebot' ), true ) && false !== stripos( $a['content'], 'noindex' ) ) {
					$out['noindex'] = true;
				}
			}
		}
		return $out;
	}
}
}

/* =========================================================================
 * ابزارهای سازگاری (آدرس‌های فارسی، نرمال‌سازی متن، زبان)
 * ====================================================================== */
if ( ! class_exists( 'CS_Compat', false ) ) {
final class CS_Compat {

	private static $tmemo = array();
	private static $lang  = array();

	public static function strlen( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $s, 'UTF-8' ) : strlen( (string) $s );
	}

	public static function norm_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$url   = preg_replace( '/#.*$/', '', $url );
		$parts = explode( '?', $url, 2 );
		$base  = $parts[0];
		$query = isset( $parts[1] ) ? '?' . $parts[1] : '';
		$pos   = strpos( $base, '://' );
		if ( false === $pos ) {
			if ( 0 === strpos( $base, '//' ) ) {
				$slash = strpos( $base, '/', 2 );
				if ( false === $slash ) {
					$head = $base;
					$path = '';
				} else {
					$head = substr( $base, 0, $slash );
					$path = substr( $base, $slash );
				}
			} else {
				$head = '';
				$path = $base;
			}
		} else {
			$slash = strpos( $base, '/', $pos + 3 );
			if ( false === $slash ) {
				$head = $base;
				$path = '';
			} else {
				$head = substr( $base, 0, $slash );
				$path = substr( $base, $slash );
			}
		}
		return strtolower( $head ) . self::norm_path( $path ) . $query;
	}

	public static function norm_path( $path ) {
		$segs = explode( '/', (string) $path );
		foreach ( $segs as $i => $seg ) {
			$segs[ $i ] = rawurlencode( rawurldecode( $seg ) );
		}
		return implode( '/', $segs );
	}

	public static function loose( $url ) {
		return CS_Util::loose_url( self::norm_url( $url ) );
	}

	public static function same_key( $x, $y ) {
		$x = (string) $x;
		$y = (string) $y;
		if ( '' === $x || '' === $y ) {
			return false;
		}
		return $x === $y || rawurldecode( $x ) === rawurldecode( $y );
	}

	public static function same_path_key( $key, $path ) {
		$key  = (string) $key;
		$path = (string) $path;
		if ( '' === $key || '' === $path ) {
			return false;
		}
		$variants = array( $path, rawurldecode( $path ), self::norm_path( $path ) );
		foreach ( $variants as $v ) {
			if ( self::same_key( $key, $v ) ) {
				return true;
			}
		}
		return false;
	}

	public static function path_lookup( array $map, $key ) {
		$key      = (string) $key;
		$variants = array( $key, rawurldecode( $key ), self::norm_path( $key ) );
		foreach ( $variants as $v ) {
			if ( isset( $map[ $v ] ) ) {
				return (int) $map[ $v ];
			}
		}
		return 0;
	}

	public static function sitemap_has( array $ctx, $path ) {
		if ( empty( $ctx['sm_ok'] ) || empty( $ctx['sm'] ) ) {
			return false;
		}
		$path     = (string) $path;
		$variants = array( $path, rawurldecode( $path ), self::norm_path( $path ) );
		foreach ( $variants as $v ) {
			if ( isset( $ctx['sm'][ CS_Util::hash32( $v ) ] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
	}

	public static function norm_text( $s ) {
		$s = (string) $s;
		if ( '' === $s ) {
			return '';
		}
		if ( isset( self::$tmemo[ $s ] ) ) {
			return self::$tmemo[ $s ];
		}
		$o = strtr( self::lower( $s ), array(
			'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه',
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
		$r = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $o );
		if ( null !== $r ) {
			$o = $r;
		}
		$r = preg_replace( '/[\s\p{P}\p{S}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+/u', ' ', $o );
		if ( null !== $r ) {
			$o = $r;
		}
		$o = trim( $o );
		if ( count( self::$tmemo ) >= 5000 ) {
			self::$tmemo = array();
		}
		self::$tmemo[ $s ] = $o;
		return $o;
	}

	public static function norm_anchor( $s ) {
		$stop  = (array) apply_filters( 'cs_anchor_filler_words', array( 'ی' ) );
		$parts = explode( ' ', self::norm_text( $s ) );
		$out   = array();
		foreach ( $parts as $p ) {
			if ( '' !== $p && ! in_array( $p, $stop, true ) ) {
				$out[] = $p;
			}
		}
		return implode( ' ', $out );
	}

	public static function post_lang( $id ) {
		$id = (int) $id;
		if ( isset( self::$lang[ $id ] ) ) {
			return self::$lang[ $id ];
		}
		$l = '';
		if ( function_exists( 'pll_get_post_language' ) ) {
			$v = pll_get_post_language( $id );
			if ( is_string( $v ) ) {
				$l = $v;
			}
		} elseif ( has_filter( 'wpml_element_language_code' ) ) {
			$v = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => get_post_type( $id ) ) );
			if ( is_string( $v ) ) {
				$l = $v;
			}
		}
		$l = (string) apply_filters( 'cs_post_language', $l, $id );
		if ( count( self::$lang ) >= 20000 ) {
			self::$lang = array();
		}
		self::$lang[ $id ] = $l;
		return $l;
	}
}
}

/* =========================================================================
 * مدل ریسک (Noisy-OR)
 * ====================================================================== */
if ( ! class_exists( 'CS_Risk', false ) ) {
final class CS_Risk {

	public static function combine( array $signals, $damp = 0.35 ) {
		$eff      = array();
		$by_group = array();
		foreach ( $signals as $k => $sg ) {
			$v         = CS_Util::clamp( $sg['w'] * $sg['s'] );
			$eff[ $k ] = $v;
			$g         = isset( $sg['group'] ) ? (string) $sg['group'] : '';
			if ( '' !== $g ) {
				$by_group[ $g ][ $k ] = $v;
			}
		}
		foreach ( $by_group as $members ) {
			arsort( $members );
			$first = true;
			foreach ( $members as $k => $v ) {
				if ( $first ) {
					$first = false;
					continue;
				}
				$eff[ $k ] = $v * $damp;
			}
		}
		$prod = 1.0;
		$sum  = 0.0;
		foreach ( $eff as $v ) {
			$prod *= ( 1.0 - $v );
			$sum  += $v;
		}
		$risk = 1.0 - $prod;
		foreach ( $signals as $k => $sg ) {
			$signals[ $k ]['pts'] = ( $sum > 0 ) ? round( $risk * 100 * $eff[ $k ] / $sum, 1 ) : 0;
		}
		return array( $risk, $signals );
	}

	public static function level( $score ) {
		if ( $score >= 80 ) {
			return 'critical';
		}
		if ( $score >= 60 ) {
			return 'high';
		}
		if ( $score >= 30 ) {
			return 'medium';
		}
		return 'low';
	}

	public static function label( $level ) {
		$l = array( 'critical' => 'بحرانی', 'high' => 'زیاد', 'medium' => 'متوسط', 'low' => 'کم' );
		return isset( $l[ $level ] ) ? $l[ $level ] : $level;
	}

	public static function color( $level ) {
		$c = array( 'critical' => '#c62828', 'high' => '#e65100', 'medium' => '#b7791f', 'low' => '#2e7d32' );
		return isset( $c[ $level ] ) ? $c[ $level ] : '#666';
	}
}
}

/* =========================================================================
 * ذخیره‌سازی تعارض‌ها
 * ====================================================================== */
if ( ! class_exists( 'CS_Conflicts', false ) ) {
final class CS_Conflicts {

	private static $alias = null;

	private static function use_alias() {
		global $wpdb;
		if ( null !== self::$alias ) {
			return self::$alias;
		}
		self::$alias = false;
		$info        = method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : '';
		if ( '' !== $info && false === stripos( $info, 'mariadb' ) ) {
			self::$alias = version_compare( (string) $wpdb->db_version(), '8.0.20', '>=' );
		}
		return self::$alias;
	}

	public static function swap_ab( $text ) {
		return strtr( (string) $text, array( '{A}' => '{B}', '{B}' => '{A}' ) );
	}

	private static function clean_reasons( array $reasons ) {
		foreach ( $reasons as $k => $r ) {
			if ( is_array( $r ) && isset( $r['text'] ) ) {
				$reasons[ $k ]['text'] = wp_strip_all_tags( (string) $r['text'] );
			}
		}
		return $reasons;
	}

	private static function clean_suggestions( array $sugg ) {
		$out = array();
		foreach ( $sugg as $s ) {
			$out[] = wp_strip_all_tags( (string) $s );
		}
		return $out;
	}

	/** جایگزینی {P123} با عنوان مقاله (خروجی ساده) */
	private static function post_placeholders( $text, $html ) {
		return preg_replace_callback( '/\{P(\d+)\}/', function ( $m ) use ( $html ) {
			$t = CS_Util::title( (int) $m[1] );
			return $html ? '<strong>' . esc_html( $t ) . '</strong>' : $t;
		}, (string) $text );
	}

	/** همان render ولی بدون HTML (برای CSV، ایمیل، تلگرام) */
	public static function plain( $text, array $titles = array() ) {
		$map = array();
		foreach ( $titles as $k => $v ) {
			$map[ '{' . $k . '}' ] = (string) $v;
		}
		$out = $map ? strtr( (string) $text, $map ) : (string) $text;
		return self::post_placeholders( $out, false );
	}

	public static function counts() {
		global $wpdb;
		$tb   = CS_Util::t( 'conflicts' );
		$rows = $wpdb->get_results( "SELECT score, COUNT(*) AS c FROM {$tb} WHERE status NOT IN ('resolved','ignored') GROUP BY score", ARRAY_A );
		$out  = array( 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0 );
		foreach ( (array) $rows as $r ) {
			$level         = CS_Risk::level( (int) $r['score'] );
			$c             = (int) $r['c'];
			$out[ $level ] = ( isset( $out[ $level ] ) ? $out[ $level ] : 0 ) + $c;
			$out['total'] += $c;
		}
		$out['resolved'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tb} WHERE status='resolved'" );
		return $out;
	}

	/** شرط WHERE مشترک بین لیست، CSV و گزارش چاپی؛ خروجی: array( sql, params ) */
	public static function build_where( $status = '', $type = '', $search = '' ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $status && 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( '' !== $type && 'all' !== $type ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s LIMIT 1000", $like ) );
			$ids  = array_map( 'intval', (array) $ids );
			if ( $ids ) {
				$in      = implode( ',', $ids );
				$where[] = "(post_a IN ($in) OR post_b IN ($in))";
			} else {
				$where[] = '1=0';
			}
		}
		return array( implode( ' AND ', $where ), $params );
	}

	/** تعارض‌های یک مقاله (به‌عنوان A یا B) */
	public static function for_post( $post_id, $status = 'new', $limit = 20 ) {
		global $wpdb;
		$tb   = CS_Util::t( 'conflicts' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$tb} WHERE (post_a=%d OR post_b=%d) AND status=%s ORDER BY score DESC, id ASC LIMIT %d",
			(int) $post_id,
			(int) $post_id,
			(string) $status,
			(int) $limit
		), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** ردیف تعارض «محتوای مشابه» برای یک جفت (ترتیب A/B مهم نیست) */
	public static function find_pair( $a, $b ) {
		global $wpdb;
		$tb = CS_Util::t( 'conflicts' );
		$x  = min( (int) $a, (int) $b );
		$y  = max( (int) $a, (int) $b );
		$r  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tb} WHERE type='duplicate' AND post_a=%d AND post_b=%d", $x, $y ), ARRAY_A );
		return is_array( $r ) ? $r : null;
	}

	public static function render( $text, array $titles = array() ) {
		$out = esc_html( (string) $text );
		if ( $titles ) {
			$map = array();
			foreach ( $titles as $k => $v ) {
				$map[ '{' . $k . '}' ] = '<strong>' . esc_html( (string) $v ) . '</strong>';
			}
			$out = strtr( $out, $map );
		}
		return self::post_placeholders( $out, true );
	}

	public static function upsert( $type, $a, $b, $risk, array $reasons, array $sugg, $run ) {
		global $wpdb;
		$tb    = CS_Util::t( 'conflicts' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$score = (int) round( $risk * 100 );
		$a     = (int) $a;
		$b     = (int) $b;

		if ( $b > 0 && $a > $b ) {
			$t    = $a;
			$a    = $b;
			$b    = $t;
			$sugg = array_map( array( 'CS_Conflicts', 'swap_ab' ), $sugg );
		}
		$reasons = self::clean_reasons( $reasons );
		$sugg    = self::clean_suggestions( $sugg );

		if ( self::use_alias() ) {
			$sql = "INSERT INTO {$tb} (type,post_a,post_b,score,reasons,suggestions,status,scan_run,created_at,updated_at)
			 VALUES (%s,%d,%d,%d,%s,%s,'new',%d,%s,%s) AS new_row
			 ON DUPLICATE KEY UPDATE score=new_row.score, reasons=new_row.reasons, suggestions=new_row.suggestions, scan_run=new_row.scan_run, updated_at=new_row.updated_at, notified_at=IF({$tb}.status='resolved',NULL,{$tb}.notified_at), status=IF({$tb}.status='resolved','new',{$tb}.status)";
		} else {
			$sql = "INSERT INTO {$tb} (type,post_a,post_b,score,reasons,suggestions,status,scan_run,created_at,updated_at)
			 VALUES (%s,%d,%d,%d,%s,%s,'new',%d,%s,%s)
			 ON DUPLICATE KEY UPDATE score=VALUES(score), reasons=VALUES(reasons), suggestions=VALUES(suggestions), scan_run=VALUES(scan_run), updated_at=VALUES(updated_at), notified_at=IF({$tb}.status='resolved',NULL,{$tb}.notified_at), status=IF({$tb}.status='resolved','new',{$tb}.status)";
		}

		$wpdb->query( $wpdb->prepare(
			$sql,
			$type,
			$a,
			$b,
			$score,
			CS_Util::json( $reasons ),
			CS_Util::json( $sugg ),
			(int) $run,
			$now,
			$now
		) );
	}

	public static function resolve_canonical_of( $post_id ) {
		global $wpdb;
		$tb = CS_Util::t( 'conflicts' );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$tb} SET status='resolved', updated_at=%s WHERE type='canonical' AND post_a=%d AND status='new'",
			gmdate( 'Y-m-d H:i:s' ),
			(int) $post_id
		) );
	}

	public static function resolve_post( $post_id ) {
		global $wpdb;
		$tb = CS_Util::t( 'conflicts' );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$tb} SET status='resolved', updated_at=%s WHERE (post_a=%d OR post_b=%d) AND status='new'",
			gmdate( 'Y-m-d H:i:s' ),
			(int) $post_id,
			(int) $post_id
		) );
	}

	/**
	 * تعارض‌های «محتوای مشابه» که در اسکن جدید دیگر دیده نشدند (مقاله‌ها اصلاح شده‌اند یا امتیاز
	 * زیر آستانه رفته) «حل‌شده» علامت می‌خورند. فقط جفت‌هایی که هر دو مقاله در این اسکن ایندکس
	 * شده‌اند بررسی می‌شوند تا سقف تعداد مقاله (max_posts) باعث حل‌شدن اشتباهی نشود.
	 * تعارض‌های canonical را خود CS_Canonical::process مدیریت می‌کند.
	 */
	public static function resolve_stale( $run ) {
		global $wpdb;
		$run = (int) $run;
		if ( $run <= 0 ) {
			return 0;
		}
		$known = array_flip( array_map( 'intval', array_values( CS_Indexer::pathmap() ) ) );
		if ( ! $known ) {
			return 0;
		}
		$tb    = CS_Util::t( 'conflicts' );
		$stale = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, post_a, post_b FROM {$tb} WHERE type='duplicate' AND status='new' AND scan_run < %d",
			$run
		), ARRAY_A );
		$ids = array();
		foreach ( (array) $stale as $r ) {
			if ( isset( $known[ (int) $r['post_a'] ], $known[ (int) $r['post_b'] ] ) ) {
				$ids[] = (int) $r['id'];
			}
		}
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in = implode( ',', $chunk );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$tb} SET status='resolved', updated_at=%s WHERE id IN ({$in}) AND status='new'", // phpcs:ignore
				gmdate( 'Y-m-d H:i:s' )
			) );
		}
		return count( $ids );
	}

	public static function delete_post( $post_id ) {
		global $wpdb;
		$tb = CS_Util::t( 'conflicts' );
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$tb} WHERE post_a=%d OR post_b=%d",
			(int) $post_id,
			(int) $post_id
		) );
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$allowed = array( 'new', 'resolved', 'ignored' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		$tb = CS_Util::t( 'conflicts' );
		return $wpdb->update(
			$tb,
			array( 'status' => $status, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		$tb = CS_Util::t( 'conflicts' );
		return $wpdb->delete( $tb, array( 'id' => (int) $id ), array( '%d' ) );
	}

	public static function on_transition( $new, $old, $post ) {
		if ( is_object( $post ) && 'publish' === $old && 'publish' !== $new ) {
			self::resolve_post( (int) $post->ID );
		}
	}

	public static function on_delete( $post_id ) {
		self::delete_post( (int) $post_id );
	}
}
}

/* =========================================================================
 * امتیازدهی جفت مقاله‌ها (کنیبالیزیشن)
 * ====================================================================== */
if ( ! class_exists( 'CS_Scorer', false ) ) {
final class CS_Scorer {

	const GENERIC_ANCHOR_MAX_TARGETS = 25;

	private static $ctx       = null;
	private static $params    = null;
	private static $vec       = array();
	private static $gen_set   = null;
	private static $gen_heads = null;

	public static function default_weights() {
		return array(
			'keyword'   => 0.85,
			'title'     => 0.60,
			'content'   => 0.75,
			'headings'  => 0.45,
			'slug'      => 0.40,
			'taxonomy'  => 0.25,
			'anchors'   => 0.35,
			'canonical' => 0.50,
		);
	}

	/** وزن‌های خام (۰ تا ۱۰۰) → وزن‌های نهایی (۰ تا ۱) با در نظر گرفتن پیش‌فرض‌ها */
	public static function normalize_weights( array $raw ) {
		$pct = array();
		foreach ( self::default_weights() as $k => $v ) {
			$pct[ $k ] = $v * 100;
		}
		$w = array();
		foreach ( array_merge( $pct, $raw ) as $k => $v ) {
			if ( ! isset( $pct[ $k ] ) ) {
				continue;
			}
			$v       = is_numeric( $v ) ? (int) $v : (int) $pct[ $k ];
			$w[ $k ] = max( 0, min( 100, $v ) ) / 100.0;
		}
		return $w;
	}

	/** واژه‌هایی (ریشه) که بیشترین سهم را در شباهت کسینوسی دو مقاله دارند */
	public static function shared_terms( array $a, array $b, $limit = 12 ) {
		$ctx = self::context();
		$va  = self::vec( $a, $ctx['N'], $ctx['df'] );
		$vb  = self::vec( $b, $ctx['N'], $ctx['df'] );
		$out = array();
		if ( empty( $va['w'] ) || empty( $vb['w'] ) || $va['n'] <= 0 || $vb['n'] <= 0 ) {
			return $out;
		}
		foreach ( $va['w'] as $t => $w ) {
			if ( isset( $vb['w'][ $t ] ) ) {
				$out[ $t ] = ( $w * $vb['w'][ $t ] ) / ( $va['n'] * $vb['n'] );
			}
		}
		arsort( $out );
		return array_slice( $out, 0, (int) $limit, true );
	}

	public static function params() {
		if ( null === self::$params ) {
			self::$params = (array) apply_filters( 'cs_scoring_params', array(
				'title_floor'        => 0.30,
				'title_span'         => 0.55,
				'content_floor'      => 0.30,
				'content_span'       => 0.55,
				'slug_floor'         => 0.40,
				'slug_span'          => 0.50,
				'tax_floor'          => 0.20,
				'tax_span'           => 0.60,
				'tax_min_shared'     => 2,
				'series_damp'        => 0.35,
				'group_damp'         => 0.35,
				'resolved_factor'    => 0.35,
				'canon_cos'          => 0.50,
				'dup_cos'            => 0.85,
				'no_canon_both'      => 0.50,
				'no_canon_one'       => 0.30,
				'edge_min'           => 0.35,
				'merge_cos'          => 0.60,
				'merge_kw'           => 0.90,
				'merge_kw_cos'       => 0.40,
				'skip_cross_lang'    => true,
				'candidate_df_ratio' => 0.15,
				'candidate_df_min'   => 50,
			) );
		}
		return self::$params;
	}

	private static function generic_anchor_max_targets() {
		return max( 1, (int) apply_filters( 'cs_generic_anchor_max_targets', self::GENERIC_ANCHOR_MAX_TARGETS ) );
	}

	private static function generic_anchor_set() {
		if ( null === self::$gen_set ) {
			$list = array(
				'ادامه مطلب', 'ادامه', 'ادامه بخوانید', 'ادامه مطلب را بخوانید', 'ادامه را بخوانید', 'بیشتر', 'بیشتر بخوانید',
				'مطالعه بیشتر', 'مشاهده', 'مشاهده بیشتر', 'مشاهده مطلب', 'مشاهده مقاله', 'اینجا', 'همینجا',
				'همین جا', 'اینجا کلیک کنید', 'کلیک کنید', 'کلیک', 'لینک', 'این لینک', 'این مقاله', 'این مطلب', 'این صفحه',
				'اطلاعات بیشتر', 'جزئیات', 'جزییات', 'بیشتر بدانید', 'دانلود', 'ادامه ی مطلب',
				'click here', 'here', 'read more', 'more', 'link', 'this link', 'learn more', 'continue reading', 'see more',
			);
			$set = array();
			foreach ( $list as $x ) {
				$n = CS_Compat::norm_anchor( $x );
				if ( '' !== $n ) {
					$set[ $n ] = true;
				}
			}
			self::$gen_set = $set;
		}
		return self::$gen_set;
	}

	private static function is_generic_anchor( $norm, $raw, $targets ) {
		if ( '' === $norm || is_numeric( $norm ) ) {
			return true;
		}
		if ( CS_Compat::strlen( $norm ) < 3 ) {
			return true;
		}
		if ( preg_match( '#^(https?:)?//#i', trim( (string) $raw ) ) ) {
			return true;
		}
		$set = self::generic_anchor_set();
		if ( isset( $set[ $norm ] ) ) {
			return true;
		}
		return ( is_array( $targets ) && count( $targets ) > self::generic_anchor_max_targets() );
	}

	private static function generic_heading_set() {
		if ( null === self::$gen_heads ) {
			$list = (array) apply_filters( 'cs_generic_headings', array(
				'مقدمه', 'نتیجه گیری', 'نتیجه', 'جمع بندی', 'خلاصه', 'سخن پایانی', 'سخن آخر', 'کلام آخر', 'در پایان',
				'سوالات متداول', 'سوالات پرتکرار', 'سوالات رایج', 'پرسش های متداول', 'پرسش و پاسخ', 'سوالات مطرح شده',
				'منابع', 'فهرست', 'فهرست مطالب', 'معرفی',
				'faq', 'faqs', 'introduction', 'conclusion', 'summary', 'overview', 'final thoughts', 'references',
				'sources', 'contents', 'table of contents',
			) );
			$set = array();
			foreach ( $list as $x ) {
				$n = CS_Compat::norm_text( $x );
				if ( '' !== $n ) {
					$set[ $n ] = true;
				}
			}
			self::$gen_heads = $set;
		}
		return self::$gen_heads;
	}

	public static function context( $fresh = false ) {
		if ( null !== self::$ctx && ! $fresh ) {
			return self::$ctx;
		}
		if ( $fresh ) {
			self::$vec    = array();
			self::$params = null;
		}
		$s   = (array) CS_Util::settings( $fresh );
		$raw = ( isset( $s['weights'] ) && is_array( $s['weights'] ) ) ? $s['weights'] : array();
		$w   = self::normalize_weights( $raw );
		$meta = get_option( 'cs_meta' );
		$N    = ( is_array( $meta ) && ! empty( $meta['N'] ) ) ? (int) $meta['N'] : CS_Indexer::indexed_count();
		$an   = (array) CS_Util::get_blob( 'cs_anchors' );
		$pa   = array();
		$ad   = array();
		foreach ( $an as $anchor => $targets ) {
			if ( ! is_array( $targets ) ) {
				continue;
			}
			$n = CS_Compat::norm_anchor( $anchor );
			if ( self::is_generic_anchor( $n, $anchor, $targets ) ) {
				continue;
			}
			if ( ! isset( $ad[ $n ] ) ) {
				$ad[ $n ] = (string) $anchor;
			}
			foreach ( $targets as $pid => $cnt ) {
				$pid = (int) $pid;
				$pa[ $pid ][ $n ] = ( isset( $pa[ $pid ][ $n ] ) ? $pa[ $pid ][ $n ] : 0 ) + (int) $cnt;
			}
		}
		self::$ctx = array(
			'w'   => $w,
			'N'   => max( 2, $N ),
			'df'  => (array) CS_Util::get_blob( 'cs_df' ),
			'pa'  => $pa,
			'ad'  => $ad,
			'min' => isset( $s['min_score'] ) ? (int) $s['min_score'] : 30,
		);
		return self::$ctx;
	}

	private static function idf( $t, $N, array $df ) {
		$d = isset( $df[ $t ] ) ? max( 1, (int) $df[ $t ] ) : 1;
		return log( ( $N + 1 ) / $d ) + 1.0;
	}

	public static function vec( array $row, $N, array $df ) {
		$id  = (int) $row['post_id'];
		$tf  = isset( $row['tf'] ) ? (array) $row['tf'] : array();
		$sig = ( isset( $row['content_hash'] ) ? (string) $row['content_hash'] : '' ) . '|' . count( $tf );
		if ( isset( self::$vec[ $id ] ) && self::$vec[ $id ]['h'] === $sig ) {
			return self::$vec[ $id ];
		}
		$w = array();
		$n = 0.0;
		foreach ( $tf as $t => $c ) {
			$x       = ( 1 + log( max( 1, (int) $c ) ) ) * self::idf( $t, $N, $df );
			$w[ $t ] = $x;
			$n      += $x * $x;
		}
		$v = array( 'h' => $sig, 'w' => $w, 'n' => sqrt( $n ) );
		if ( count( self::$vec ) >= 3000 ) {
			self::$vec = array_slice( self::$vec, 1500, null, true );
		}
		self::$vec[ $id ] = $v;
		return $v;
	}

	public static function cosine_vec( array $va, array $vb ) {
		if ( empty( $va['w'] ) || empty( $vb['w'] ) || $va['n'] <= 0 || $vb['n'] <= 0 ) {
			return 0.0;
		}
		$x = $va['w'];
		$y = $vb['w'];
		if ( count( $x ) > count( $y ) ) {
			$t = $x;
			$x = $y;
			$y = $t;
		}
		$dot = 0.0;
		foreach ( $x as $t => $w ) {
			if ( isset( $y[ $t ] ) ) {
				$dot += $w * $y[ $t ];
			}
		}
		if ( $dot <= 0 ) {
			return 0.0;
		}
		return min( 1.0, $dot / ( $va['n'] * $vb['n'] ) );
	}

	public static function cosine( array $a, array $b, $N, array $df ) {
		if ( ! $a || ! $b ) {
			return 0.0;
		}
		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;
		foreach ( $a as $t => $c ) {
			$idf = self::idf( $t, $N, $df );
			$wa  = ( 1 + log( max( 1, (int) $c ) ) ) * $idf;
			$na += $wa * $wa;
			if ( isset( $b[ $t ] ) ) {
				$wb   = ( 1 + log( max( 1, (int) $b[ $t ] ) ) ) * $idf;
				$dot += $wa * $wb;
			}
		}
		if ( $dot <= 0 ) {
			return 0.0;
		}
		foreach ( $b as $t => $c ) {
			$idf = self::idf( $t, $N, $df );
			$wb  = ( 1 + log( max( 1, (int) $c ) ) ) * $idf;
			$nb += $wb * $wb;
		}
		return ( $na > 0 && $nb > 0 ) ? min( 1.0, $dot / sqrt( $na * $nb ) ) : 0.0;
	}

	private static function index_keys( array $row, array $v, $top ) {
		$keys = array();
		$w    = $v['w'];
		arsort( $w );
		foreach ( array_keys( array_slice( $w, 0, $top, true ) ) as $t ) {
			$keys[ 't:' . $t ] = 1;
		}
		if ( ! empty( $row['content_hash'] ) ) {
			$keys[ 'h:' . $row['content_hash'] ] = 3;
		}
		if ( ! empty( $row['slug_base'] ) ) {
			$keys[ 's:' . $row['slug_base'] ] = 3;
		}
		foreach ( (array) ( isset( $row['keywords'] ) ? $row['keywords'] : array() ) as $kw ) {
			if ( empty( $kw['key'] ) ) {
				continue;
			}
			$k = CS_Compat::norm_text( $kw['key'] );
			if ( '' === $k ) {
				continue;
			}
			$keys[ 'k:' . $k ] = 3;
			foreach ( explode( ' ', $k ) as $tok ) {
				if ( CS_Compat::strlen( $tok ) >= 3 ) {
					$keys[ 'w:' . CS_Stem::stem( $tok ) ] = 1;
				}
			}
		}
		return $keys;
	}

	public static function build_index( array $rows, $top = 30 ) {
		$ctx = self::context();
		$idx = array();
		foreach ( $rows as $r ) {
			$id = (int) $r['post_id'];
			$v  = self::vec( $r, $ctx['N'], $ctx['df'] );
			foreach ( array_keys( self::index_keys( $r, $v, $top ) ) as $k ) {
				$idx[ $k ][] = $id;
			}
		}
		return $idx;
	}

	public static function candidates( array $row, array $idx, $min_hits = 3, $limit = 200, $top = 30 ) {
		$ctx  = self::context();
		$P    = self::params();
		$self = (int) $row['post_id'];
		$cap  = max( (int) $P['candidate_df_min'], (int) ceil( $ctx['N'] * (float) $P['candidate_df_ratio'] ) );
		$cap  = (int) apply_filters( 'cs_candidate_df_cap', $cap, $ctx['N'] );
		$v    = self::vec( $row, $ctx['N'], $ctx['df'] );
		$hits = array();
		foreach ( self::index_keys( $row, $v, $top ) as $k => $wt ) {
			if ( ! isset( $idx[ $k ] ) || count( $idx[ $k ] ) > $cap ) {
				continue;
			}
			foreach ( $idx[ $k ] as $pid ) {
				if ( $pid === $self ) {
					continue;
				}
				$hits[ $pid ] = ( isset( $hits[ $pid ] ) ? $hits[ $pid ] : 0 ) + $wt;
			}
		}
		$out = array();
		foreach ( $hits as $pid => $h ) {
			if ( $h >= $min_hits ) {
				$out[ $pid ] = $h;
			}
		}
		arsort( $out );
		return array_keys( array_slice( $out, 0, $limit, true ) );
	}

	public static function is_candidate( array $a, array $b ) {
		if ( ! empty( $a['content_hash'] ) && isset( $b['content_hash'] ) && $a['content_hash'] === $b['content_hash'] ) {
			return true;
		}
		if ( ! empty( $a['slug_base'] ) && isset( $b['slug_base'] ) && $a['slug_base'] === $b['slug_base'] ) {
			return true;
		}
		foreach ( (array) $a['keywords'] as $ka ) {
			if ( empty( $ka['key'] ) ) {
				continue;
			}
			foreach ( (array) $b['keywords'] as $kb ) {
				if ( empty( $kb['key'] ) ) {
					continue;
				}
				if ( array_intersect( CS_Stem::tokens( CS_Compat::norm_text( $ka['key'] ) ), CS_Stem::tokens( CS_Compat::norm_text( $kb['key'] ) ) ) ) {
					return true;
				}
			}
		}
		foreach ( array( 'title_norm', 'h1_norm' ) as $f ) {
			$x = isset( $a[ $f ] ) ? CS_Stem::tokens( CS_Compat::norm_text( $a[ $f ] ) ) : array();
			$y = isset( $b[ $f ] ) ? CS_Stem::tokens( CS_Compat::norm_text( $b[ $f ] ) ) : array();
			if ( count( array_intersect( $x, $y ) ) >= 2 ) {
				return true;
			}
		}
		$ta = (array) $a['tf'];
		$tb = (array) $b['tf'];
		return ( $ta && $tb && count( array_intersect_key( $ta, $tb ) ) >= 5 );
	}

	private static function pct( $v ) {
		return round( $v * 100 ) . '٪';
	}

	private static function keyword( array $ka, array $kb ) {
		$best = array( 's' => 0.0, 'text' => '' );
		foreach ( $ka as $i => $a ) {
			$na = isset( $a['key'] ) ? CS_Compat::norm_text( $a['key'] ) : '';
			if ( '' === $na ) {
				continue;
			}
			foreach ( $kb as $j => $b ) {
				$nb = isset( $b['key'] ) ? CS_Compat::norm_text( $b['key'] ) : '';
				if ( '' === $nb ) {
					continue;
				}
				$raw_a = isset( $a['raw'] ) ? (string) $a['raw'] : (string) $a['key'];
				$raw_b = isset( $b['raw'] ) ? (string) $b['raw'] : (string) $b['key'];
				$mult  = ( 0 === $i && 0 === $j ) ? 1.0 : 0.6;
				$pre  = ( 0 === $i && 0 === $j ) ? 'کلمه کلیدی کانونی' : 'کلمه کلیدی (فرعی)';
				$s    = 0.0;
				$txt  = '';
				if ( $na === $nb ) {
					$s   = 1.0;
					$txt = $pre . ' یکسان: «' . $raw_a . '»';
				} else {
					$ta = CS_Stem::tokens( $na );
					$tb = CS_Stem::tokens( $nb );
					$xa = $ta;
					$xb = $tb;
					sort( $xa );
					sort( $xb );
					$ua = array_values( array_unique( $ta ) );
					$ub = array_values( array_unique( $tb ) );
					$in = count( array_intersect( $ua, $ub ) );
					if ( $xa && $xa === $xb ) {
						$s   = 0.92;
						$txt = $pre . ' هم‌ریشه/هم‌معنا (تفاوت فقط در شکل نگارشی یا ترتیب واژه‌ها): «' . $raw_a . '» و «' . $raw_b . '»';
					} elseif ( min( count( $ua ), count( $ub ) ) >= 2 && ( $in === count( $ua ) || $in === count( $ub ) ) ) {
						$s   = 0.75;
						$txt = 'یکی از کلمات کلیدی در دیگری گنجانده شده: «' . $raw_a . '» و «' . $raw_b . '»';
					} else {
						$jc = CS_Text::jaccard( $ua, $ub );
						$s  = CS_Util::clamp( ( $jc - 0.4 ) / 0.5 );
						if ( $s > 0 ) {
							$txt = 'کلمات کلیدی مشابه (' . self::pct( $jc ) . '): «' . $raw_a . '» و «' . $raw_b . '»';
						}
					}
				}
				$s *= $mult;
				if ( $s > $best['s'] ) {
					$best = array( 's' => $s, 'text' => $txt );
				}
			}
		}
		return $best;
	}

	private static function clean_headings( array $h ) {
		$g   = self::generic_heading_set();
		$out = array();
		foreach ( $h as $x ) {
			$n = CS_Compat::norm_text( $x );
			if ( '' === $n || isset( $g[ $n ] ) ) {
				continue;
			}
			$out[ $n ] = $n;
		}
		return array_values( $out );
	}

	private static function headings( array $ha, array $hb ) {
		$ha = self::clean_headings( $ha );
		$hb = self::clean_headings( $hb );
		if ( count( $ha ) < 2 || count( $hb ) < 2 ) {
			return array( 's' => 0.0, 'text' => '' );
		}
		$shared = count( array_intersect( $ha, $hb ) );
		$exact  = $shared / min( count( $ha ), count( $hb ) );
		$ta     = array();
		$tb     = array();
		foreach ( $ha as $h ) {
			foreach ( CS_Stem::tokens( $h ) as $w ) {
				$ta[] = $w;
			}
		}
		foreach ( $hb as $h ) {
			foreach ( CS_Stem::tokens( $h ) as $w ) {
				$tb[] = $w;
			}
		}
		if ( ! $ta || ! $tb ) {
			return array( 's' => 0.0, 'text' => '' );
		}
		$j = CS_Text::jaccard( $ta, $tb );
		$s = max( $exact, CS_Util::clamp( ( $j - 0.25 ) / 0.5 ) );
		if ( $s < 0.15 ) {
			return array( 's' => 0.0, 'text' => '' );
		}
		$txt = ( $shared > 0 )
			? 'هم‌پوشانی سرفصل‌های H2/H3: ' . $shared . ' سرفصل مشترک (شباهت واژگانی ' . self::pct( $j ) . ')'
			: 'هم‌پوشانی واژگان سرفصل‌های H2/H3: ' . self::pct( $j );
		return array( 's' => $s, 'text' => $txt );
	}

	private static function series_words() {
		return (array) apply_filters( 'cs_series_words', array(
			'قسمت', 'بخش', 'فصل', 'جلسه', 'درس', 'گام', 'مرحله', 'part', 'episode', 'ep', 'chapter', 'lesson', 'session', 'step', 'vol', 'volume',
		) );
	}

	private static function series_strip( $t ) {
		$t     = preg_replace( '/\d+/', ' ', (string) $t );
		$words = array_diff( explode( ' ', (string) $t ), self::series_words() );
		return trim( (string) preg_replace( '/\s+/', ' ', implode( ' ', $words ) ) );
	}

	private static function is_series( array $a, array $b ) {
		$ta = CS_Compat::norm_text( isset( $a['title_norm'] ) ? $a['title_norm'] : '' );
		$tb = CS_Compat::norm_text( isset( $b['title_norm'] ) ? $b['title_norm'] : '' );
		if ( '' === $ta || '' === $tb ) {
			return false;
		}
		preg_match_all( '/\d+/', $ta, $ma );
		preg_match_all( '/\d+/', $tb, $mb );
		$da = $ma[0];
		$db = $mb[0];
		if ( ! $da || ! $db || $da === $db ) {
			return false;
		}
		$nonyear = false;
		foreach ( array_merge( array_diff( $da, $db ), array_diff( $db, $da ) ) as $n ) {
			if ( ! preg_match( '/^(13|14|19|20)\d\d$/', $n ) ) {
				$nonyear = true;
				break;
			}
		}
		if ( ! $nonyear ) {
			return false;
		}
		$sa = self::series_strip( $ta );
		$sb = self::series_strip( $tb );
		return ( '' !== $sa && '' !== $sb && CS_Text::text_sim( $sa, $sb ) >= 0.85 );
	}

	private static function effective_canonical( array $r ) {
		$live  = ( isset( $r['live'] ) && is_array( $r['live'] ) ) ? $r['live'] : array();
		$known = ( isset( $live['status'] ) && 200 === (int) $live['status'] );
		$c     = '';
		if ( $known && ! empty( $live['canonicals'] ) ) {
			$c = (string) $live['canonicals'][0];
		} elseif ( ! $known && isset( $r['canonical_meta'] ) && '' !== trim( (string) $r['canonical_meta'] ) ) {
			$c = (string) $r['canonical_meta'];
		}
		$c = CS_Util::abs_url( $c );
		return array(
			'known' => $known,
			'has'   => ( '' !== $c ),
			'key'   => ( '' !== $c ) ? CS_Util::url_key( CS_Compat::norm_url( $c ) ) : '',
		);
	}

	private static function pub_time( array $r ) {
		$t = get_post_time( 'U', true, (int) $r['post_id'] );
		return $t ? (int) $t : PHP_INT_MAX;
	}

	private static function winner_score( array $r ) {
		$v = (int) $r['inbound'] * 3 + (int) $r['word_count'] / 300;
		return (float) apply_filters( 'cs_winner_score', $v, $r );
	}

	private static function empty_result( $edge_w ) {
		$P = self::params();
		return array(
			'risk'        => 0.0,
			'reasons'     => array(),
			'suggestions' => array(),
			'edge'        => ( $edge_w >= $P['edge_min'] ),
			'edge_w'      => $edge_w,
			'core'        => false,
		);
	}

	public static function pair( array $a, array $b, array $ctx ) {
		$W = array_merge( self::default_weights(), ( isset( $ctx['w'] ) && is_array( $ctx['w'] ) ) ? $ctx['w'] : array() );
		$P = self::params();

		if ( ! empty( $P['skip_cross_lang'] ) ) {
			$la = CS_Compat::post_lang( (int) $a['post_id'] );
			$lb = CS_Compat::post_lang( (int) $b['post_id'] );
			if ( '' !== $la && '' !== $lb && $la !== $lb ) {
				return self::empty_result( 0.0 );
			}
		}

		$sig    = array();
		$m      = array();
		$series = self::is_series( $a, $b );
		$s_hit  = false;

		$kw      = self::keyword( (array) $a['keywords'], (array) $b['keywords'] );
		$m['kw'] = $kw['s'];
		if ( $kw['s'] > 0 ) {
			$sig[] = array( 'code' => 'keyword', 'group' => 'lex', 'w' => $W['keyword'], 's' => $kw['s'], 'text' => $kw['text'] );
		}

		$ts = 0.0;
		foreach ( array( 'title_norm', 'h1_norm' ) as $f ) {
			$x = isset( $a[ $f ] ) ? CS_Compat::norm_text( $a[ $f ] ) : '';
			$y = isset( $b[ $f ] ) ? CS_Compat::norm_text( $b[ $f ] ) : '';
			if ( '' !== $x && '' !== $y ) {
				$ts = max( $ts, CS_Text::text_sim( $x, $y ) );
				if ( CS_Stem::enabled() ) {
					$ts = max( $ts, CS_Text::text_sim( CS_Stem::text( $x ), CS_Stem::text( $y ) ) );
				}
			}
		}
		$m['title'] = $ts;
		$s          = CS_Util::clamp( ( $ts - $P['title_floor'] ) / $P['title_span'] );
		if ( $series ) {
			$s *= $P['series_damp'];
		}
		if ( $s > 0 ) {
			$s_hit = $s_hit || $series;
			$sig[] = array( 'code' => 'title', 'group' => 'lex', 'w' => $W['title'], 's' => $s, 'text' => 'شباهت عنوان (Title/H1): ' . self::pct( $ts ) );
		}

		$same = ( '' !== $a['content_hash'] && $a['content_hash'] === $b['content_hash'] && min( (int) $a['word_count'], (int) $b['word_count'] ) >= 80 );
		if ( $same ) {
			$cos = 1.0;
		} else {
			$cos = self::cosine_vec( self::vec( $a, $ctx['N'], $ctx['df'] ), self::vec( $b, $ctx['N'], $ctx['df'] ) );
		}
		$m['cos'] = $cos;
		$s        = CS_Util::clamp( ( $cos - $P['content_floor'] ) / $P['content_span'] );
		if ( $s > 0 ) {
			$sig[] = array(
				'code' => 'content',
				'w'    => $W['content'],
				's'    => $s,
				'text' => $same ? 'متن دو مقاله عیناً یکسان است (هش محتوا برابر)' : 'شباهت محتوا (TF-IDF): ' . self::pct( $cos ),
			);
		}

		$hd = self::headings( (array) $a['heads'], (array) $b['heads'] );
		if ( $hd['s'] > 0 ) {
			$sig[] = array( 'code' => 'headings', 'group' => 'lex', 'w' => $W['headings'], 's' => $hd['s'], 'text' => $hd['text'] );
		}

		$slug_same_base = ( '' !== $a['slug_base'] && $a['slug_base'] === $b['slug_base'] && CS_Compat::strlen( $a['slug_base'] ) >= 3 );
		$s              = 0.0;
		$txt            = '';
		if ( $slug_same_base ) {
			$s   = 1.0;
			$txt = 'اسلاگ‌ها فقط در پسوند عددی تفاوت دارند («' . $a['slug_base'] . '»)';
		} else {
			$sna = CS_Compat::norm_text( $a['slug_norm'] );
			$snb = CS_Compat::norm_text( $b['slug_norm'] );
			if ( '' !== $sna && '' !== $snb ) {
				$sj  = CS_Text::text_sim( $sna, $snb );
				$sj  = max( $sj, CS_Text::jaccard( explode( ' ', $sna ), explode( ' ', $snb ) ) );
				$s   = CS_Util::clamp( ( $sj - $P['slug_floor'] ) / $P['slug_span'] );
				$txt = 'شباهت اسلاگ: ' . self::pct( $sj );
			}
		}
		if ( $series ) {
			$s *= $P['series_damp'];
		}
		if ( $s > 0 ) {
			$s_hit = $s_hit || $series;
			$sig[] = array( 'code' => 'slug', 'group' => 'lex', 'w' => $W['slug'], 's' => $s, 'text' => $txt );
		}

		$taxa = array_map( 'strval', (array) apply_filters( 'cs_taxonomy_terms', (array) $a['tax'], $a ) );
		$taxb = array_map( 'strval', (array) apply_filters( 'cs_taxonomy_terms', (array) $b['tax'], $b ) );
		if ( $taxa && $taxb ) {
			$shared_tax = count( array_intersect( $taxa, $taxb ) );
			if ( $shared_tax >= (int) $P['tax_min_shared'] ) {
				$tj = CS_Text::jaccard( $taxa, $taxb );
				$s  = CS_Util::clamp( ( $tj - $P['tax_floor'] ) / $P['tax_span'] ) * min( 1.0, $shared_tax / 3 );
				if ( $s > 0 ) {
					$sig[] = array( 'code' => 'taxonomy', 'w' => $W['taxonomy'], 's' => $s, 'text' => 'دسته‌بندی/برچسب مشترک: ' . $shared_tax . ' مورد (' . self::pct( $tj ) . ')' );
				}
			}
		}

		$anchors_shared = array();
		$anchors_disp   = array();
		if ( isset( $ctx['pa'][ (int) $a['post_id'] ], $ctx['pa'][ (int) $b['post_id'] ] ) ) {
			$anchors_shared = array_keys( array_intersect_key( $ctx['pa'][ (int) $a['post_id'] ], $ctx['pa'][ (int) $b['post_id'] ] ) );
			$ad             = ( isset( $ctx['ad'] ) && is_array( $ctx['ad'] ) ) ? $ctx['ad'] : array();
			foreach ( $anchors_shared as $k ) {
				$anchors_disp[] = isset( $ad[ $k ] ) ? (string) $ad[ $k ] : (string) $k;
			}
		}
		if ( $anchors_shared ) {
			$n     = count( $anchors_shared );
			$s     = CS_Util::clamp( 0.4 + 0.3 * ( $n - 1 ) );
			$show  = array_slice( $anchors_disp, 0, 3 );
			$sig[] = array(
				'code' => 'anchors',
				'w'    => $W['anchors'],
				's'    => $s,
				'text' => 'انکر تکست مشترک به هر دو مقاله لینک شده: «' . implode( '»، «', $show ) . '»' . ( $n > 3 ? ' و ' . ( $n - 3 ) . ' مورد دیگر' : '' ),
			);
		}

		$core = false;
		foreach ( $sig as $sg ) {
			if ( in_array( $sg['code'], array( 'keyword', 'title', 'content', 'headings', 'slug', 'anchors' ), true ) ) {
				$core = true;
				break;
			}
		}

		$edge_w = max( $cos, $kw['s'] * 0.85, $ts * 0.9 );

		if ( ! $core ) {
			return self::empty_result( $edge_w );
		}

		$ca       = self::effective_canonical( $a );
		$cb       = self::effective_canonical( $b );
		$resolved = ( $ca['has'] && CS_Compat::same_path_key( $ca['key'], $b['path'] ) ) || ( $cb['has'] && CS_Compat::same_path_key( $cb['key'], $a['path'] ) );
		if ( ! $resolved && ( $cos >= $P['canon_cos'] || $same ) ) {
			$noneA = ( $ca['known'] && ! $ca['has'] );
			$noneB = ( $cb['known'] && ! $cb['has'] );
			if ( $noneA && $noneB ) {
				$sig[] = array( 'code' => 'canonical', 'w' => $W['canonical'], 's' => $P['no_canon_both'], 'text' => 'هر دو مقاله در خروجی HTML تگ canonical ندارند' );
			} elseif ( $noneA || $noneB ) {
				$sig[] = array( 'code' => 'canonical', 'w' => $W['canonical'], 's' => $P['no_canon_one'], 'text' => 'یکی از دو مقاله تگ canonical ندارد' );
			} elseif ( $cos >= $P['dup_cos'] ) {
				$sig[] = array( 'code' => 'canonical', 'w' => $W['canonical'], 's' => 1.0, 'text' => 'محتوای تقریباً تکراری (' . self::pct( $cos ) . ') بدون canonical که یکی را به دیگری ارجاع دهد' );
			}
		}

		list( $risk, $sig ) = CS_Risk::combine( $sig, $P['group_damp'] );

		$reasons = array();
		if ( $resolved ) {
			$risk *= $P['resolved_factor'];
			foreach ( $sig as $k => $sg ) {
				$sig[ $k ]['pts'] = round( $sg['pts'] * $P['resolved_factor'], 1 );
			}
		}
		foreach ( $sig as $sg ) {
			$reasons[] = array( 'code' => $sg['code'], 'text' => $sg['text'], 'pts' => $sg['pts'], 'w' => round( $sg['w'], 2 ), 's' => round( $sg['s'], 2 ) );
		}
		if ( $resolved ) {
			$reasons[] = array( 'code' => 'resolved', 'text' => 'یکی از مقاله‌ها با canonical به دیگری اشاره می‌کند؛ ریسک به‌دلیل مدیریت‌شدن ' . round( ( 1 - $P['resolved_factor'] ) * 100 ) . '٪ کاهش یافت', 'pts' => 0, 'w' => 0, 's' => 0 );
		}
		if ( $series && $s_hit ) {
			$reasons[] = array( 'code' => 'series', 'text' => 'عنوان‌ها فقط در شماره‌ی قسمت/بخش تفاوت دارند و به‌نظر می‌رسد بخش‌های یک سری‌اند؛ سیگنال عنوان و اسلاگ کاهش یافت', 'pts' => 0, 'w' => 0, 's' => 0 );
		}

		return array(
			'risk'        => $risk,
			'reasons'     => $reasons,
			'suggestions' => self::suggestions( $a, $b, $m, $sig, $anchors_disp, $resolved, $slug_same_base, $P ),
			'edge'        => ( $edge_w >= $P['edge_min'] ),
			'edge_w'      => $edge_w,
			'core'        => true,
		);
	}

	private static function suggestions( array $a, array $b, array $m, array $sig, array $anchors, $resolved, $slug_same_base, array $P ) {
		$by = array();
		foreach ( $sig as $sg ) {
			$by[ $sg['code'] ] = $sg['s'];
		}
		$out = array();
		if ( $resolved ) {
			$out[] = 'canonical فعلی مسئله را مدیریت کرده است؛ در صورت نیاز لینک‌های داخلی را هم به مقاله‌ی هدف بدهید.';
			return $out;
		}
		$sa = self::winner_score( $a );
		$sb = self::winner_score( $b );
		if ( abs( $sa - $sb ) < 0.01 ) {
			$pa = self::pub_time( $a );
			$pb = self::pub_time( $b );
			if ( $pa !== $pb ) {
				$win = ( $pa < $pb ) ? 'A' : 'B';
			} else {
				$win = ( (int) $a['post_id'] < (int) $b['post_id'] ) ? 'A' : 'B';
			}
		} else {
			$win = ( $sa > $sb ) ? 'A' : 'B';
		}
		$W = '{' . $win . '}';
		$L = '{' . ( 'A' === $win ? 'B' : 'A' ) . '}';

		$kw    = isset( $by['keyword'] ) ? $by['keyword'] : 0;
		$cos   = $m['cos'];
		$merge = ( $cos >= $P['merge_cos'] || ( $kw >= $P['merge_kw'] && $cos >= $P['merge_kw_cos'] ) );

		if ( $merge ) {
			$out[] = "ادغام {$L} در {$W} و ریدایرکت ۳۰۱ از {$L} به {$W}";
			$out[] = "اگر هر دو صفحه باید بمانند: افزودن canonical از {$L} به {$W}";
			$out[] = 'مقاله‌ی برنده به‌صورت تخمینی (لینک ورودی و طول متن) انتخاب شده است؛ پیش از ادغام، کلیک و نمایش هر دو صفحه را در Search Console مقایسه کنید.';
		} elseif ( $kw >= 0.9 ) {
			$out[] = "تغییر کلمه کلیدی کانونی {$L} به عبارتی متمایز با قصد جستجوی (intent) متفاوت";
		} elseif ( $kw >= 0.5 ) {
			$out[] = "کلمه کلیدی {$L} را از {$W} متمایز کنید (بازنویسی تمرکز موضوعی)";
		}
		if ( ! $merge && isset( $by['title'] ) && $by['title'] >= 0.5 ) {
			$out[] = "عنوان و H1 در {$L} را متمایز کنید تا با {$W} یکسان به نظر نرسد";
		}
		if ( isset( $by['headings'] ) && $by['headings'] >= 0.5 && ! $merge ) {
			$out[] = 'سرفصل‌های مشترک H2/H3 را بازنویسی کنید یا یکی از بخش‌های تکراری را حذف/ادغام کنید';
		}
		if ( $slug_same_base && isset( $by['slug'] ) && $by['slug'] >= 0.5 ) {
			$out[] = "اسلاگ {$L} را اصلاح کنید یا با ۳۰۱ به {$W} هدایت کنید";
		}
		if ( $anchors ) {
			$out[] = "اصلاح لینک داخلی: انکرهای مشترک («" . implode( '»، «', array_slice( $anchors, 0, 2 ) ) . "») را فقط به {$W} بدهید یا برای {$L} انکر متفاوت انتخاب کنید";
		}
		if ( isset( $by['canonical'] ) && ! $merge ) {
			$out[] = 'در خروجی هر دو صفحه یک canonical صریح و درست تنظیم کنید';
		}
		if ( ! $out ) {
			$out[] = "محتوای {$L} را با تمرکز متفاوت نسبت به {$W} بازنویسی کنید و لینک داخلی هدفمند بین دو مقاله بگذارید";
		}
		return $out;
	}
}
}

/* =========================================================================
 * مشکلات کنونیکال هر مقاله
 * ====================================================================== */
if ( ! class_exists( 'CS_Canonical', false ) ) {
final class CS_Canonical {

	const MEMO_MAX = 500;

	private static $ctx       = null;
	private static $memo      = array();
	private static $checks    = 0;
	private static $processed = 0;
	private static $partial   = false;

	public static function ctx( $fresh = false ) {
		if ( null === self::$ctx || $fresh ) {
			$sm        = (array) CS_Util::get_blob( 'cs_sitemap' );
			$s         = (array) CS_Util::settings( $fresh );
			self::$ctx = array(
				'sm_ok' => ! empty( $sm['ok'] ),
				'sm'    => isset( $sm['keys'] ) ? array_flip( array_map( 'intval', (array) $sm['keys'] ) ) : array(),
				'min'   => isset( $s['min_score'] ) ? (int) $s['min_score'] : 30,
			);
		}
		return self::$ctx;
	}

	public static function reset_budget() {
		self::$checks = 0;
	}

	public static function evaluate( array $row, array $ctx ) {
		self::$partial = false;

		$id       = (int) $row['post_id'];
		$perma    = get_permalink( $id );
		$live     = ( isset( $row['live'] ) && is_array( $row['live'] ) ) ? $row['live'] : array();
		$has_live = ! empty( $live['status'] );
		$status   = $has_live ? (int) $live['status'] : 0;
		$sig      = array();
		$sugg     = array();
		$add      = function ( $code, $w, $text, $tip = '' ) use ( &$sig, &$sugg ) {
			$sig[] = array( 'code' => $code, 'w' => $w, 's' => 1.0, 'text' => $text );
			if ( '' !== $tip ) {
				$sugg[] = $tip;
			}
		};

		$meta_raw = isset( $row['canonical_meta'] ) ? trim( (string) $row['canonical_meta'] ) : '';
		$meta_abs = ( '' !== $meta_raw ) ? CS_Util::abs_url( $meta_raw ) : '';
		$noindex  = ! empty( $live['noindex'] ) || ! empty( $row['seo_noindex'] );
		$in_sm    = CS_Compat::sitemap_has( $ctx, $row['path'] );

		$canons = array();
		if ( $has_live && 200 === $status ) {
			foreach ( (array) ( isset( $live['canonicals'] ) ? $live['canonicals'] : array() ) as $u ) {
				$u = CS_Util::abs_url( $u );
				if ( '' !== $u ) {
					$canons[] = $u;
				}
			}
		} elseif ( ! $has_live && '' !== $meta_abs ) {
			$canons = array( $meta_abs );
		}

		if ( $has_live && $status >= 400 ) {
			$add( 'http_error', 0.85, 'صفحه‌ی مقاله برای ربات‌ها در دسترس نیست (HTTP ' . $status . ') با اینکه منتشر شده است', 'علت خطای ' . $status . ' را بررسی کنید (کش، فایروال، ریدایرکت یا تنظیمات پرمالینک)' );
		} elseif ( $has_live && $status >= 300 ) {
			$add( 'self_redirect', 0.55, 'خود مقاله ریدایرکت می‌شود (HTTP ' . $status . ( ! empty( $live['location'] ) ? ' → ' . $live['location'] : '' ) . ')', 'پرمالینک مقاله با آدرس نهایی یکی نیست؛ آدرس/پرمالینک را یکسان کنید یا مقاله را از نقشه‌ی سایت حذف کنید' );
		} else {
			$judge_missing = ( $has_live && 200 === $status );
			if ( $judge_missing && ! $canons && ! $noindex ) {
				$add( 'missing', 0.45, 'در خروجی HTML مقاله تگ canonical وجود ندارد', 'یک canonical خودارجاع در افزونه‌ی سئو فعال کنید (یا بررسی کنید قالب rel_canonical را حذف نکرده باشد)' );
			}
			if ( count( $canons ) >= 2 ) {
				$uniq = array_unique( array_map( array( 'CS_Compat', 'loose' ), $canons ) );
				if ( count( $uniq ) > 1 ) {
					$add( 'multi_diff', 0.85, 'چند تگ canonical با آدرس‌های متفاوت در خروجی وجود دارد (تداخل قالب و افزونه‌ی سئو)', 'فقط یک منبع canonical را فعال بگذارید؛ قالب/افزونه‌ی اضافه را غیرفعال کنید' );
				} else {
					$add( 'multi_same', 0.5, 'تگ canonical به‌صورت تکراری چاپ شده است (' . count( $canons ) . ' بار)', 'خروجی تکراری canonical را از قالب یا افزونه‌ی دوم حذف کنید' );
				}
			}

			$moved = false;
			if ( $canons ) {
				$primary = $canons[0];
				$diff    = CS_Util::compare_urls( CS_Compat::norm_url( $primary ), CS_Compat::norm_url( $perma ) );
				$moved   = in_array( 'host', $diff, true ) || in_array( 'path', $diff, true );
				if ( ! $moved ) {
					if ( in_array( 'scheme', $diff, true ) ) {
						$add( 'scheme', 0.5, 'ناسازگاری http/https بین canonical و پرمالینک', 'canonical و آدرس سایت (Site URL) را روی https یکسان کنید' );
					}
					if ( in_array( 'www', $diff, true ) ) {
						$add( 'www', 0.4, 'ناسازگاری www / non-www بین canonical و پرمالینک', 'یک نسخه‌ی دامنه را انتخاب و بقیه را ۳۰۱ کنید' );
					}
					if ( in_array( 'slash', $diff, true ) ) {
						$add( 'slash', 0.4, 'ناسازگاری اسلش انتهایی بین canonical و پرمالینک', 'ساختار پرمالینک و canonical را از نظر اسلش انتهایی یکسان کنید' );
					}
					if ( in_array( 'query', $diff, true ) ) {
						$add( 'query', 0.4, 'canonical دارای پارامتر (query string) است و با پرمالینک فرق دارد', 'پارامترهای اضافه را از canonical حذف کنید' );
					}
				} elseif ( in_array( 'host', $diff, true ) ) {
					$add( 'external', 0.6, 'canonical به دامنه‌ی دیگری اشاره می‌کند: ' . $primary, 'اگر عمدی نیست، canonical را به پرمالینک همین مقاله برگردانید' );
				} else {
					self::analyze_target( $row, $primary, $add );
				}
				if ( $moved && $in_sm ) {
					$add( 'in_sitemap', 0.4, 'مقاله به آدرس دیگری canonical داده ولی هنوز در نقشه‌ی سایت (sitemap) هست', 'مقاله‌ی canonical‌شده را از sitemap خارج کنید' );
				}
			}
			if ( '' !== $meta_abs && $canons && CS_Compat::loose( $canons[0] ) !== CS_Compat::loose( $meta_abs ) ) {
				$add( 'meta_mismatch', 0.5, 'canonical ثبت‌شده در افزونه‌ی سئو با canonical واقعی خروجی یکی نیست', 'خروجی واقعی را در سورس صفحه بررسی کنید؛ احتمالاً قالب یا افزونه‌ی دیگری آن را بازنویسی می‌کند' );
			}
			if ( $noindex && $canons && $moved ) {
				$add( 'noindex_canonical', 0.55, 'مقاله همزمان noindex است و canonical آن به آدرس دیگری اشاره می‌کند (سیگنال متناقض)', 'canonical را به خود مقاله برگردانید یا اگر مقاله باید ایندکس شود، noindex را بردارید' );
			}
			if ( $noindex && $in_sm ) {
				$add( 'noindex_sitemap', 0.35, 'مقاله noindex است ولی در نقشه‌ی سایت آمده', 'مقاله‌ی noindex را از sitemap خارج کنید' );
			}
		}

		if ( ! $sig ) {
			return array( 'risk' => 0.0, 'reasons' => array(), 'suggestions' => array(), 'partial' => self::$partial );
		}
		list( $risk, $sig ) = CS_Risk::combine( $sig );
		$reasons = array();
		foreach ( $sig as $sg ) {
			$reasons[] = array( 'code' => $sg['code'], 'text' => $sg['text'], 'pts' => $sg['pts'], 'w' => round( $sg['w'], 2 ), 's' => 1 );
		}
		return array( 'risk' => $risk, 'reasons' => $reasons, 'suggestions' => array_values( array_unique( $sugg ) ), 'partial' => self::$partial );
	}

	private static function analyze_target( array $row, $primary, $add ) {
		$pm   = CS_Indexer::pathmap();
		$tkey = CS_Util::url_key( CS_Compat::norm_url( $primary ) );
		$id   = (int) $row['post_id'];
		$tid  = CS_Compat::path_lookup( (array) $pm, $tkey );

		if ( $tid > 0 && $tid === $id ) {
			return;
		}
		if ( $tid > 0 ) {
			$add( 'to_other', 0.55, 'canonical به مقاله‌ی دیگری اشاره می‌کند: {P' . $tid . '}', 'اگر ادغام مقاله‌هاست، لینک‌های داخلی را هم به مقاله‌ی هدف بدهید و مقاله‌ی فعلی را از sitemap حذف کنید؛ در غیر این صورت canonical را به خود مقاله برگردانید' );
			$rows = CS_Indexer::load_rows( array( $tid ) );
			if ( isset( $rows[ $tid ] ) ) {
				$t  = $rows[ $tid ];
				$tl = ( isset( $t['live'] ) && is_array( $t['live'] ) ) ? $t['live'] : array();
				if ( ! empty( $t['seo_noindex'] ) || ! empty( $tl['noindex'] ) ) {
					$add( 'target_noindex', 0.9, 'مقاله‌ی هدف canonical ({P' . $tid . '}) دارای noindex است', 'canonical را به صفحه‌ای قابل ایندکس بدهید یا noindex هدف را بردارید' );
				}
				if ( ! empty( $tl['status'] ) && (int) $tl['status'] >= 300 ) {
					$add( 'target_bad', 0.85, 'مقاله‌ی هدف canonical ({P' . $tid . '}) خطا/ریدایرکت می‌دهد (HTTP ' . (int) $tl['status'] . ')', 'canonical را به آدرس نهایی و سالم تغییر دهید' );
				}
				if ( ! empty( $tl['canonicals'][0] ) ) {
					$tk = CS_Util::url_key( CS_Compat::norm_url( CS_Util::abs_url( $tl['canonicals'][0] ) ) );
					if ( ! CS_Compat::same_key( $tk, $t['path'] ) ) {
						$add( 'chain', 0.6, 'زنجیره‌ی canonical: هدف ({P' . $tid . '}) هم به آدرس دیگری canonical می‌دهد', 'canonical را مستقیماً به مقصد نهایی بدهید' );
					}
				}
			}
			return;
		}

		$mk = CS_Compat::loose( $primary );
		if ( isset( self::$memo[ $mk ] ) ) {
			$chk = self::$memo[ $mk ];
		} else {
			$max = (int) apply_filters( 'cs_max_external_checks', 40 );
			if ( self::$checks >= $max ) {
				self::$partial = true;
				return;
			}
			self::$checks++;
			$chk = CS_Fetch::page_cached( $primary );
			if ( count( self::$memo ) >= self::MEMO_MAX ) {
				self::$memo = array();
			}
			self::$memo[ $mk ] = $chk;
		}
		$st = (int) $chk['status'];
		if ( 0 === $st ) {
			return;
		}
		if ( $st >= 400 ) {
			$add( 'target_bad', 0.9, 'آدرس canonical خطای HTTP ' . $st . ' می‌دهد: ' . $primary, 'canonical را به یک آدرس سالم و ایندکس‌پذیر تغییر دهید' );
		} elseif ( $st >= 300 ) {
			$add( 'target_redirect', 0.7, 'آدرس canonical ریدایرکت می‌شود (HTTP ' . $st . '): ' . $primary, 'canonical باید به آدرس نهایی (بدون ریدایرکت) اشاره کند' );
		} elseif ( ! empty( $chk['noindex'] ) ) {
			$add( 'target_noindex', 0.9, 'آدرس هدف canonical دارای noindex است: ' . $primary, 'canonical را به صفحه‌ی قابل ایندکس بدهید' );
		} else {
			$add( 'to_other_url', 0.5, 'canonical به آدرسی غیر از پرمالینک اشاره می‌کند: ' . $primary, 'اگر عمدی نیست، canonical را به پرمالینک همین مقاله برگردانید' );
		}
	}

	public static function process( array $row, array $ctx, $run ) {
		$win = (int) apply_filters( 'cs_external_check_window', 25 );
		if ( $win > 0 && self::$processed > 0 && 0 === self::$processed % $win ) {
			self::reset_budget();
		}
		self::$processed++;

		$res = self::evaluate( $row, $ctx );
		if ( $res['reasons'] && $res['risk'] * 100 >= $ctx['min'] ) {
			CS_Conflicts::upsert( 'canonical', (int) $row['post_id'], 0, $res['risk'], $res['reasons'], $res['suggestions'], $run );
			return true;
		}
		if ( ! empty( $res['partial'] ) ) {
			return false;
		}
		CS_Conflicts::resolve_canonical_of( (int) $row['post_id'] );
		return false;
	}
}
}

/* =========================================================================
 * مهاجرت دیتابیس (برای ارتقا از نصب‌های احتمالی قدیمی‌تر بدون UNIQUE KEY)
 * ====================================================================== */
if ( ! class_exists( 'CS_Schema', false ) ) {
final class CS_Schema {

	const OPTION  = 'cs_schema_version';
	const VERSION = '2.2';

	public static function maybe_upgrade() {
		if ( get_option( self::OPTION ) === self::VERSION ) {
			return;
		}
		// مهاجرت‌ها فقط در ادمین/کرون اجرا می‌شوند تا درخواست‌های بازدیدکنندگان سنگین نشوند
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}
		self::ensure_unique_key();
		self::ensure_columns();
		CS_Indexer::migrate_legacy_rows();
		CS_Fetch::purge();
		update_option( self::OPTION, self::VERSION, false );
	}

	/** ۱.۸: ستون notified_at؛ ردیف‌های موجود «اعلان‌شده» علامت می‌خورند تا پیام انبوه نیاید */
	private static function ensure_columns() {
		global $wpdb;
		$tb  = CS_Util::t( 'conflicts' );
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$tb} LIKE %s", 'notified_at' ) );
		if ( $col ) {
			return;
		}
		$wpdb->query( "ALTER TABLE {$tb} ADD COLUMN notified_at DATETIME NULL AFTER updated_at" );
		$wpdb->query( $wpdb->prepare( "UPDATE {$tb} SET notified_at=%s WHERE notified_at IS NULL", gmdate( 'Y-m-d H:i:s' ) ) );
	}

	private static function ensure_unique_key() {
		global $wpdb;
		$tb = CS_Util::t( 'conflicts' );

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tb ) );
		if ( ! $exists ) {
			cs_create_table();
			return;
		}

		$idx = $wpdb->get_results( "SHOW INDEX FROM {$tb} WHERE Key_name = 'cs_pair_unique'" );
		if ( $idx ) {
			return;
		}

		$wpdb->query(
			"DELETE t1 FROM {$tb} t1 INNER JOIN {$tb} t2
			 ON t1.type = t2.type AND t1.post_a = t2.post_a AND t1.post_b = t2.post_b AND t1.id < t2.id"
		);
		$wpdb->query( "ALTER TABLE {$tb} ADD UNIQUE KEY cs_pair_unique (type, post_a, post_b)" );
	}
}
}

/* =========================================================================
 * ایندکسر: عبور از پست‌های سایت و ساخت ردیف‌های مورد نیاز موتور امتیازدهی
 * ====================================================================== */
if ( ! class_exists( 'CS_Indexer', false ) ) {
final class CS_Indexer {

	public static function indexed_count() {
		$meta = get_option( 'cs_meta' );
		return ( is_array( $meta ) && ! empty( $meta['N'] ) ) ? (int) $meta['N'] : 0;
	}

	public static function pathmap() {
		return CS_Util::get_blob( 'cs_pathmap' );
	}

	/** تعداد مقاله در هر شارد ردیف‌ها (هر شارد یک گزینه‌ی جدا در wp_options است) */
	const ROW_SHARD = 50;

	private static function row_shard( $id ) {
		return (int) floor( (int) $id / self::ROW_SHARD );
	}

	private static function row_key( $shard ) {
		return 'cs_rows_' . (int) $shard;
	}

	/** پاک‌کردن همه‌ی ردیف‌های ایندکس (شاردها + ساختار قدیمی ۱.۸) */
	public static function clear_rows() {
		foreach ( array_keys( CS_Util::get_blob( 'cs_rows_idx' ) ) as $sh ) {
			delete_option( 'cs_blob_' . self::row_key( $sh ) );
		}
		delete_option( 'cs_blob_cs_rows_idx' );
		delete_option( 'cs_blob_cs_rows' );
	}

	/** تعداد ردیف‌های ذخیره‌شده */
	public static function rows_count() {
		return (int) array_sum( array_map( 'intval', CS_Util::get_blob( 'cs_rows_idx' ) ) );
	}

	/** ردیف‌ها را در شاردها ذخیره/جایگزین می‌کند؛ ردیف‌های قبلیِ جایگزین‌شده را برمی‌گرداند */
	public static function save_rows( array $rows ) {
		$by = array();
		foreach ( $rows as $id => $row ) {
			$by[ self::row_shard( $id ) ][ (int) $id ] = $row;
		}
		$idx  = CS_Util::get_blob( 'cs_rows_idx' );
		$prev = array();
		foreach ( $by as $sh => $items ) {
			$cur = CS_Util::get_blob( self::row_key( $sh ) );
			foreach ( $items as $id => $row ) {
				if ( isset( $cur[ $id ] ) ) {
					$prev[ $id ] = $cur[ $id ];
				}
				$cur[ $id ] = $row;
			}
			CS_Util::set_blob( self::row_key( $sh ), $cur );
			$idx[ $sh ] = count( $cur );
		}
		CS_Util::set_blob( 'cs_rows_idx', $idx );
		return $prev;
	}

	/**
	 * ردیف‌های ایندکس؛ بدون آرگومان همه‌ی ردیف‌ها، با آرگومان فقط شناسه‌های خواسته‌شده
	 * (فقط شاردهای لازم خوانده می‌شود). توجه: آرایه‌ی خالی یعنی «همه».
	 */
	public static function load_rows( array $ids = array() ) {
		$idx = CS_Util::get_blob( 'cs_rows_idx' );
		$out = array();
		if ( ! $ids ) {
			$shards = array_keys( $idx );
			sort( $shards );
			foreach ( $shards as $sh ) {
				foreach ( CS_Util::get_blob( self::row_key( $sh ) ) as $id => $row ) {
					$out[ (int) $id ] = $row;
				}
			}
			return $out;
		}
		$want = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$want[ self::row_shard( $id ) ][] = $id;
		}
		foreach ( $want as $sh => $list ) {
			if ( ! isset( $idx[ $sh ] ) ) {
				continue;
			}
			$cur = CS_Util::get_blob( self::row_key( $sh ) );
			foreach ( $list as $id ) {
				if ( isset( $cur[ $id ] ) ) {
					$out[ $id ] = $cur[ $id ];
				}
			}
		}
		return $out;
	}

	/** انتقال ردیف‌های ایندکس ۱.۸ (یک گزینه‌ی بزرگ) به شاردها */
	public static function migrate_legacy_rows() {
		$legacy = get_option( 'cs_blob_cs_rows', null );
		if ( null === $legacy ) {
			return;
		}
		if ( is_array( $legacy ) && $legacy && ! CS_Util::get_blob( 'cs_rows_idx' ) ) {
			self::save_rows( $legacy );
		}
		delete_option( 'cs_blob_cs_rows' );
	}

	public static function stopwords() {
		return (array) apply_filters( 'cs_stopwords', array(
			'از', 'به', 'با', 'در', 'که', 'این', 'آن', 'را', 'و', 'یا', 'برای', 'است', 'هست', 'بود', 'شد', 'شود', 'می',
			'های', 'ها', 'هم', 'تا', 'بر', 'یک', 'دو', 'اگر', 'چون', 'چرا', 'کدام', 'چه', 'هر', 'نیز', 'دیگر', 'بین',
			'روی', 'زیر', 'بالا', 'کنار', 'طور', 'گونه', 'مانند', 'مثل', 'باید', 'نباید', 'کرد', 'کرده', 'کنیم', 'کنید',
			'نمی', 'مان', 'تان', 'شان', 'اند', 'ایم', 'اید', 'ای', 'تر', 'ترین', 'ام', 'اش', 'خود', 'همین', 'همان', 'چنین', 'بسیار', 'خیلی',
			'داشت', 'آمد', 'رفت', 'داد', 'گرفت', 'خواست', 'توانست', 'دانست', 'گفت',
			'آیا', 'بی', 'پس', 'ولی', 'اما', 'یعنی', 'چند', 'همه', 'هیچ', 'فقط', 'دارد', 'دارند', 'داشت', 'باشد', 'شده', 'شوند',
			'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'are', 'was', 'were', 'be', 'been',
			'with', 'this', 'that', 'it', 'as', 'by', 'at', 'from', 'you', 'your', 'we', 'our',
		) );
	}

	/** فهرست post_id هایی که باید ایندکس شوند، بر اساس تنظیمات فعلی */
	public static function get_target_ids( ?array $s = null ) {
		$s          = null === $s ? CS_Util::settings() : $s;
		$post_types = ! empty( $s['post_types'] ) ? (array) $s['post_types'] : array( 'post' );
		$max_posts  = max( 50, (int) $s['max_posts'] );

		$q = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $max_posts,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'lang'           => '', // پلی‌لنگ: همه‌ی زبان‌ها (در کرون/ادمین فیلتر زبان جاری نخورد)
		) );
		$ids = array_map( 'intval', $q->posts );
		wp_reset_postdata();
		return $ids;
	}

	/**
	 * ساخت ردیف برای یک دسته (batch) از post_id ها؛ برای اسکن مرحله‌ای استفاده می‌شود.
	 * اگر $deadline (microtime) گذشت، مرحله زودتر تمام می‌شود و $used تعداد پردازش‌شده‌ها را می‌دهد.
	 */
	public static function index_batch( array $ids, array $s, $deadline = 0.0, &$used = null ) {
		CS_SEO::prefetch( $ids );
		$rows = array();
		$n    = 0;
		foreach ( $ids as $id ) {
			if ( $deadline > 0 && $n > 0 && microtime( true ) > $deadline ) {
				break;
			}
			$n++;
			$row = self::build_row( (int) $id, $s );
			if ( $row ) {
				$rows[ (int) $id ] = $row;
			}
		}
		$used = $n;
		return $rows;
	}

	/** ادغام یک دسته ردیف تازه‌ساخته با شاردها و blobهای pathmap/df (تجمعی؛ تکرار یک مقاله df را دوبار نمی‌شمارد) */
	public static function merge_rows_batch( array $new_rows ) {
		if ( ! $new_rows ) {
			return;
		}
		$prev    = self::save_rows( $new_rows );
		$pathmap = CS_Util::get_blob( 'cs_pathmap' );
		$df      = CS_Util::get_blob( 'cs_df' );
		foreach ( $prev as $old ) {
			$old_tf = ( isset( $old['tf'] ) && is_array( $old['tf'] ) ) ? $old['tf'] : array();
			foreach ( array_keys( $old_tf ) as $t ) {
				if ( isset( $df[ $t ] ) ) {
					if ( $df[ $t ] <= 1 ) {
						unset( $df[ $t ] );
					} else {
						$df[ $t ]--;
					}
				}
			}
		}
		foreach ( $new_rows as $id => $row ) {
			if ( '' !== $row['path'] ) {
				$pathmap[ $row['path'] ] = $id;
			}
			foreach ( array_keys( $row['tf'] ) as $t ) {
				$df[ $t ] = ( isset( $df[ $t ] ) ? $df[ $t ] : 0 ) + 1;
			}
		}
		CS_Util::set_blob( 'cs_pathmap', $pathmap );
		CS_Util::set_blob( 'cs_df', $df );
	}

	/** انکرهای داخلی یک دسته از پست‌ها را می‌سازد (به pathmap کامل نیاز دارد) */
	public static function anchors_batch( array $ids, array $pathmap ) {
		$anchors = array();
		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			if ( ! preg_match_all( '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', (string) $post->post_content, $lm, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $lm as $m ) {
				$href = html_entity_decode( $m[1] );
				$text = trim( wp_strip_all_tags( $m[2] ) );
				if ( '' === $text || 0 === strpos( $href, '#' ) ) {
					continue;
				}
				$abs = CS_Util::abs_url( $href );
				if ( ! CS_Util::is_internal( $abs ) ) {
					continue;
				}
				$path   = CS_Compat::norm_path( (string) wp_parse_url( $abs, PHP_URL_PATH ) );
				$target = CS_Compat::path_lookup( $pathmap, $path );
				if ( $target <= 0 || $target === $id ) {
					continue;
				}
				$anchors[ $text ][ $target ] = ( isset( $anchors[ $text ][ $target ] ) ? $anchors[ $text ][ $target ] : 0 ) + 1;
			}
		}
		return $anchors;
	}

	/** ادغام انکرهای یک دسته با blob کامل cs_anchors */
	public static function merge_anchors_batch( array $partial ) {
		if ( ! $partial ) {
			return;
		}
		$anchors = CS_Util::get_blob( 'cs_anchors' );
		foreach ( $partial as $text => $targets ) {
			foreach ( $targets as $tid => $cnt ) {
				$anchors[ $text ][ $tid ] = ( isset( $anchors[ $text ][ $tid ] ) ? $anchors[ $text ][ $tid ] : 0 ) + (int) $cnt;
			}
		}
		CS_Util::set_blob( 'cs_anchors', $anchors );
	}

	/** پس از تکمیل انکرها، تعداد لینک ورودی هر مقاله را محاسبه و شارد‌به‌شارد در ردیف‌ها ذخیره می‌کند */
	public static function finalize_inbound() {
		$anchors = CS_Util::get_blob( 'cs_anchors' );
		$inbound = array();
		foreach ( $anchors as $targets ) {
			foreach ( $targets as $tid => $cnt ) {
				$inbound[ $tid ] = ( isset( $inbound[ $tid ] ) ? $inbound[ $tid ] : 0 ) + (int) $cnt;
			}
		}
		foreach ( array_keys( CS_Util::get_blob( 'cs_rows_idx' ) ) as $sh ) {
			$cur     = CS_Util::get_blob( self::row_key( $sh ) );
			$changed = false;
			foreach ( $cur as $id => $row ) {
				$v = isset( $inbound[ $id ] ) ? (int) $inbound[ $id ] : 0;
				if ( ! isset( $row['inbound'] ) || (int) $row['inbound'] !== $v ) {
					$cur[ $id ]['inbound'] = $v;
					$changed               = true;
				}
			}
			if ( $changed ) {
				CS_Util::set_blob( self::row_key( $sh ), $cur );
			}
		}
	}

	/**
	 * ساخت کامل ایندکس در یک اجرا (بدون chunk). برای سایت‌های بزرگ به‌جای این،
	 * از CS_Scan_Async (مرحله‌ای/AJAX) استفاده کنید تا با خطای Timeout مواجه نشوید.
	 * این متد برای WP-CLI یا استفاده‌ی برنامه‌نویسی مستقیم نگه داشته شده است.
	 */
	public static function build() {
		$s   = CS_Util::settings( true );
		$ids = self::get_target_ids( $s );

		CS_Indexer::clear_rows();
		CS_Util::set_blob( 'cs_pathmap', array() );
		CS_Util::set_blob( 'cs_df', array() );
		CS_Util::set_blob( 'cs_anchors', array() );

		self::merge_rows_batch( self::index_batch( $ids, $s ) );

		$pathmap = self::pathmap();
		self::merge_anchors_batch( self::anchors_batch( $ids, $pathmap ) );
		self::finalize_inbound();

		update_option( 'cs_meta', array( 'N' => count( $ids ) ), false );
		self::refresh_sitemap( $s );

		return count( $ids );
	}

	private static function build_row( $id, array $s ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return null;
		}

		$content_raw = (string) $post->post_content;
		$title       = (string) get_the_title( $id );
		$perma       = get_permalink( $id );
		$path        = CS_Compat::norm_path( (string) wp_parse_url( (string) $perma, PHP_URL_PATH ) );

		$heads = array();
		if ( preg_match_all( '#<h[23][^>]*>(.*?)</h[23]>#is', $content_raw, $hm ) ) {
			foreach ( $hm[1] as $h ) {
				$h = trim( wp_strip_all_tags( $h ) );
				if ( '' !== $h ) {
					$heads[] = $h;
				}
			}
		}

		$text       = wp_strip_all_tags( strip_shortcodes( $content_raw ) );
		$text       = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		$word_count = ( '' === $text ) ? 0 : count( preg_split( '/\s+/u', $text ) );

		$norm = CS_Compat::norm_text( $text );
		$tf   = array();
		if ( '' !== $norm ) {
			$stop = array_flip( self::stopwords() );
			$toks = explode( ' ', $norm );
			$n    = 0;
			$stem = CS_Stem::enabled();
			foreach ( $toks as $tok ) {
				if ( '' === $tok || CS_Compat::strlen( $tok ) < 3 || isset( $stop[ $tok ] ) ) {
					continue;
				}
				if ( $stem ) {
					$tok = CS_Stem::stem( $tok );
					if ( '' === $tok || isset( $stop[ $tok ] ) ) {
						continue;
					}
				}
				$tf[ $tok ] = ( isset( $tf[ $tok ] ) ? $tf[ $tok ] : 0 ) + 1;
				$n++;
				if ( $n >= 4000 ) {
					break;
				}
			}
			if ( count( $tf ) > 400 ) {
				arsort( $tf );
				$tf = array_slice( $tf, 0, 400, true );
			}
		}

		$slug      = (string) $post->post_name;
		$slug_base = (string) preg_replace( '/-\d+$/', '', $slug );
		$slug_norm = str_replace( array( '-', '_' ), ' ', $slug );

		$kw = self::focus_keywords( $id );

		$tax = array();
		foreach ( array( 'category', 'post_tag' ) as $tx ) {
			if ( ! taxonomy_exists( $tx ) ) {
				continue;
			}
			$terms = get_the_terms( $id, $tx );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $t ) {
					$tax[] = $tx . ':' . $t->slug;
				}
			}
		}

		$canonical_meta = self::seo_meta( $id, 'canonical' );
		$noindex        = self::seo_meta( $id, 'noindex' );

		$live = array();
		if ( ! empty( $s['live_check'] ) && $perma ) {
			$live = CS_Fetch::page_cached( $perma );
		}

		return array(
			'post_id'        => $id,
			'path'           => $path,
			'title_norm'     => $title,
			'h1_norm'        => $title,
			'heads'          => $heads,
			'slug_base'      => $slug_base,
			'slug_norm'      => $slug_norm,
			'keywords'       => $kw,
			'tax'            => $tax,
			'content_hash'   => ( $word_count >= 20 ) ? md5( $norm ) : '',
			'word_count'     => $word_count,
			'tf'             => $tf,
			'inbound'        => 0,
			'canonical_meta' => $canonical_meta,
			'seo_noindex'    => (bool) $noindex,
			'live'           => $live,
		);
	}

	private static function focus_keywords( $id ) {
		return CS_SEO::focus_keywords( $id );
	}

	private static function seo_meta( $id, $type ) {
		if ( 'canonical' === $type ) {
			return CS_SEO::canonical( $id );
		}
		if ( 'noindex' === $type ) {
			return CS_SEO::noindex( $id );
		}
		return '';
	}

	public static function refresh_sitemap( array $s ) {
		if ( ! empty( $s['sitemap_url'] ) ) {
			$roots = array( $s['sitemap_url'] );
		} else {
			$roots = array( home_url( '/wp-sitemap.xml' ), home_url( '/sitemap_index.xml' ), home_url( '/sitemap.xml' ) );
		}
		$roots    = (array) apply_filters( 'cs_sitemap_roots', $roots );
		$deadline = microtime( true ) + 25;
		$keys     = array();
		foreach ( $roots as $root ) {
			$keys = self::crawl_sitemap( (string) $root, $deadline );
			if ( $keys || microtime( true ) > $deadline ) {
				break;
			}
		}
		CS_Util::set_blob( 'cs_sitemap', array(
			'ok'      => ! empty( $keys ),
			'keys'    => array_values( array_unique( $keys ) ),
			'checked' => time(),
		) );
	}

	/** یک sitemap (یا sitemapindex) را می‌خزد و هش مسیر همه‌ی <loc> ها را برمی‌گرداند */
	private static function crawl_sitemap( $root, $deadline ) {
		$seen   = array();
		$keys   = array();
		$queue  = array( $root );
		$budget = 40;
		while ( $queue && $budget > 0 && microtime( true ) < $deadline ) {
			$url = array_shift( $queue );
			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$budget--;
			$res = wp_safe_remote_get( $url, array( 'timeout' => 8 ) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				continue;
			}
			$body = wp_remote_retrieve_body( $res );
			if ( '' === $body || ! preg_match_all( '#<loc>(.*?)</loc>#is', $body, $lm ) ) {
				continue;
			}
			$is_index = ( false !== stripos( $body, '<sitemapindex' ) );
			foreach ( $lm[1] as $loc ) {
				$loc = trim( (string) preg_replace( '#^<!\[CDATA\[|\]\]>$#', '', trim( $loc ) ) );
				$loc = html_entity_decode( $loc, ENT_QUOTES, 'UTF-8' );
				if ( '' === $loc ) {
					continue;
				}
				if ( $is_index ) {
					$queue[] = $loc;
				} else {
					$path = CS_Compat::norm_path( (string) wp_parse_url( $loc, PHP_URL_PATH ) );
					if ( '' !== $path ) {
						$keys[] = CS_Util::hash32( $path );
					}
				}
			}
		}
		return $keys;
	}
}
}

/* =========================================================================
 * اجرای اسکن کامل
 * ====================================================================== */
if ( ! class_exists( 'CS_Scan', false ) ) {
final class CS_Scan {

	public static function run() {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore
		}

		CS_Indexer::build();

		$run = (int) get_option( 'cs_run_seq', 0 ) + 1;
		update_option( 'cs_run_seq', $run, false );

		$ctx  = CS_Scorer::context( true );
		$rows = CS_Indexer::load_rows();
		$idx  = CS_Scorer::build_index( $rows );

		$done = array();
		foreach ( $rows as $id => $row ) {
			$cands = CS_Scorer::candidates( $row, $idx );
			foreach ( $cands as $other ) {
				if ( $other <= $id || ! isset( $rows[ $other ] ) ) {
					continue;
				}
				$pk = $id . ':' . $other;
				if ( isset( $done[ $pk ] ) ) {
					continue;
				}
				$done[ $pk ] = true;
				$res         = CS_Scorer::pair( $row, $rows[ $other ], $ctx );
				if ( $res['core'] && $res['risk'] * 100 >= $ctx['min'] ) {
					CS_Conflicts::upsert( 'duplicate', $id, $other, $res['risk'], $res['reasons'], $res['suggestions'], $run );
				}
			}
		}

		$cctx = CS_Canonical::ctx( true );
		CS_Canonical::reset_budget();
		foreach ( $rows as $row ) {
			CS_Canonical::process( $row, $cctx, $run );
		}

		self::finish( $run );
		return array( 'posts' => count( $rows ), 'run' => $run );
	}

	/** پایان اسکن (هم نسخه‌ی یک‌مرحله‌ای، هم مرحله‌ای): زمان اسکن، آمار روزانه، اعلان */
	public static function finish( $run ) {
		update_option( 'cs_last_scan', time(), false );
		try {
			CS_Conflicts::resolve_stale( $run );
		} catch ( \Throwable $e ) {
			// خطا در پاک‌سازی موارد راکد نباید اسکن را خراب کند
		}
		try {
			CS_Stats::snapshot();
			CS_Notify::after_scan( (int) $run );
		} catch ( \Throwable $e ) {
			// خطای اعلان/آمار نباید اسکن را خراب کند
		}
	}
}
}

/* =========================================================================
 * اجرای مرحله‌ای (chunked) اسکن — برای جلوگیری از خطای Request Timeout
 *
 * به‌جای انجام کل اسکن در یک درخواست HTTP (که روی سایت‌های بزرگ به سقف
 * زمانی وب‌سرور/پراکسی می‌خورد)، اسکن به مراحل کوچک تقسیم می‌شود و هر مرحله
 * فقط چند ثانیه طول می‌کشد. دکمه‌ی «اجرای اسکن الان» این مراحل را با AJAX
 * پشت سر هم صدا می‌زند؛ اسکن خودکار روزانه هم با زنجیره‌ای از
 * wp_schedule_single_event (هر بار چند ثانیه بعد از قبلی) همین کار را در
 * پس‌زمینه انجام می‌دهد، بدون این‌که یک درخواست طولانی بسازد.
 * ====================================================================== */
if ( ! class_exists( 'CS_Scan_Async', false ) ) {
final class CS_Scan_Async {

	const STATE_OPTION = 'cs_scan_state';
	const LOCK_OPTION  = 'cs_scan_lock';
	const LOCK_TTL     = 120;  // ثانیه؛ قفلِ قدیمی‌تر از این «مرده» حساب می‌شود
	const STALE_AFTER  = 900;  // ثانیه؛ اسکنِ بدون پیشرفتِ بیشتر از این «راکد» است و اسکن تازه جایگزینش می‌شود
	const MAX_ERRORS   = 3;    // بعد از این‌همه خطای پیاپی اسکن متوقف می‌شود

	private static function is_active( $state ) {
		return is_array( $state ) && ! empty( $state['phase'] ) && ! in_array( $state['phase'], array( 'done', 'idle' ), true );
	}

	/**
	 * شروع یک اسکن تازه. اگر اسکنی در جریان و «زنده» باشد همان را برمی‌گرداند؛
	 * اسکن راکد (STALE_AFTER) یا $force باعث شروع دوباره می‌شود.
	 */
	public static function start( $force = false ) {
		$existing = get_option( self::STATE_OPTION );
		if ( self::is_active( $existing ) && ! $force ) {
			$beat = isset( $existing['updated'] ) ? (int) $existing['updated'] : ( isset( $existing['started'] ) ? (int) $existing['started'] : 0 );
			if ( ( time() - $beat ) < self::STALE_AFTER ) {
				return $existing;
			}
		}

		$s = CS_Util::settings( true );
		CS_Stem::reset();
		$ids = CS_Indexer::get_target_ids( $s );

		CS_Indexer::clear_rows();
		CS_Util::set_blob( 'cs_pathmap', array() );
		CS_Util::set_blob( 'cs_df', array() );
		CS_Util::set_blob( 'cs_anchors', array() );
		CS_Util::set_blob( 'cs_pair_index', array() );
		delete_option( 'cs_scan_last_error' );

		$run = (int) get_option( 'cs_run_seq', 0 ) + 1;
		update_option( 'cs_run_seq', $run, false );

		$state = array(
			'phase'   => $ids ? 'index' : 'done',
			'ids'     => $ids,
			'cursor'  => 0,
			'total'   => count( $ids ),
			'run'     => $run,
			'started' => time(),
			'updated' => time(),
			'errors'  => 0,
		);
		update_option( self::STATE_OPTION, $state, false );
		if ( ! $ids ) {
			update_option( 'cs_last_scan', time(), false );
		}
		return $state;
	}

	/** لغو اسکن در جریان (داده‌ی ایندکس نیمه‌کاره هم با اسکن بعدی بازسازی می‌شود) */
	public static function cancel() {
		delete_option( self::STATE_OPTION );
		wp_clear_scheduled_hook( 'cs_scan_async_step' );
		self::unlock();
	}

	/* ---------- قفل: جلوگیری از اجرای هم‌زمان دو مرحله (AJAX و کرون) ---------- */

	private static function lock() {
		global $wpdb;
		$now = time();
		$ok  = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO `{$wpdb->options}` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' ) /* CS LOCK */",
			self::LOCK_OPTION,
			(string) $now
		) );
		if ( $ok ) {
			return true;
		}
		$held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s", self::LOCK_OPTION ) );
		if ( $held > 0 && ( $now - $held ) > self::LOCK_TTL ) {
			// قفل مرده: با مقایسه‌ی مقدار قبلی (اتمیک) تصاحبش می‌کنیم
			$took = $wpdb->query( $wpdb->prepare(
				"UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND option_value = %s",
				(string) $now,
				self::LOCK_OPTION,
				(string) $held
			) );
			return (bool) $took;
		}
		return false;
	}

	private static function unlock() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name = %s", self::LOCK_OPTION ) );
	}

	/** یک مرحله (batch) از اسکن جاری را اجرا می‌کند (با قفل و بودجه‌ی زمانی) */
	public static function step() {
		$state = get_option( self::STATE_OPTION );
		if ( ! self::is_active( $state ) ) {
			return self::run_step(); // idle/done: چیزی برای قفل‌کردن نیست
		}
		if ( ! self::lock() ) {
			return array(
				'phase'    => $state['phase'],
				'progress' => self::progress( $state ),
				'done'     => false,
				'busy'     => true,
				'message'  => self::phase_label( $state['phase'] ),
			);
		}
		try {
			return self::run_step();
		} catch ( \Throwable $e ) {
			return self::fail( $e );
		} finally {
			self::unlock();
		}
	}

	/** خطای یک مرحله: تا MAX_ERRORS بار دوباره تلاش می‌شود، بعد اسکن با پیام خطا متوقف می‌شود */
	private static function fail( $e ) {
		$state = get_option( self::STATE_OPTION );
		$msg   = wp_strip_all_tags( $e->getMessage() );
		if ( ! self::is_active( $state ) ) {
			return array( 'phase' => 'idle', 'progress' => 0, 'done' => true, 'failed' => true, 'message' => 'خطا در اسکن: ' . $msg );
		}
		$n = ( isset( $state['errors'] ) ? (int) $state['errors'] : 0 ) + 1;
		if ( $n >= self::MAX_ERRORS ) {
			delete_option( self::STATE_OPTION );
			wp_clear_scheduled_hook( 'cs_scan_async_step' );
			update_option( 'cs_scan_last_error', array( 'time' => time(), 'message' => $msg ), false );
			return array( 'phase' => 'idle', 'progress' => 0, 'done' => true, 'failed' => true, 'message' => 'اسکن به‌دلیل خطای مکرر متوقف شد: ' . $msg );
		}
		$state['errors']  = $n;
		$state['updated'] = time();
		update_option( self::STATE_OPTION, $state, false );
		return array( 'phase' => $state['phase'], 'progress' => self::progress( $state ), 'done' => false, 'message' => 'خطا در این مرحله؛ تلاش دوباره…' );
	}

	private static function run_step() {
		$state = get_option( self::STATE_OPTION );
		if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
			return array( 'phase' => 'idle', 'progress' => 0, 'done' => true, 'message' => 'اسکنی در جریان نیست.' );
		}
		if ( 'done' === $state['phase'] ) {
			return array( 'phase' => 'done', 'progress' => 100, 'done' => true, 'message' => 'اسکن قبلاً تمام شده است.' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 ); // phpcs:ignore
		}
		$deadline = microtime( true ) + max( 3.0, (float) apply_filters( 'cs_step_time_budget', 20.0 ) );
		$s        = CS_Util::settings();
		$batch    = max( 10, (int) $s['batch_size'] );
		$ids      = isset( $state['ids'] ) ? (array) $state['ids'] : array();
		$total    = max( 1, (int) $state['total'] );
		$cursor   = (int) $state['cursor'];

		switch ( $state['phase'] ) {

			case 'index':
				$slice = array_slice( $ids, $cursor, $batch );
				$used  = count( $slice );
				if ( $slice ) {
					CS_Indexer::merge_rows_batch( CS_Indexer::index_batch( $slice, $s, $deadline, $used ) );
				}
				$cursor += $used;
				if ( $cursor >= $total ) {
					$n = CS_Indexer::rows_count();
					update_option( 'cs_meta', array( 'N' => ( $n > 0 ? $n : $total ) ), false );
					$state['phase'] = 'anchors';
					$cursor         = 0;
				}
				break;

			case 'anchors':
				$slice = array_slice( $ids, $cursor, $batch );
				if ( $slice ) {
					$pathmap = CS_Indexer::pathmap();
					CS_Indexer::merge_anchors_batch( CS_Indexer::anchors_batch( $slice, $pathmap ) );
				}
				$cursor += count( $slice );
				if ( $cursor >= $total ) {
					CS_Indexer::finalize_inbound();
					$state['phase'] = 'sitemap';
					$cursor         = 0;
				}
				break;

			case 'sitemap':
				CS_Indexer::refresh_sitemap( $s );
				$state['phase'] = 'score';
				$cursor         = 0;
				break;

			case 'score':
				if ( 0 === $cursor ) {
					CS_Util::set_blob( 'cs_pair_index', CS_Scorer::build_index( CS_Indexer::load_rows() ) );
				}
				$idx   = CS_Util::get_blob( 'cs_pair_index' );
				$ctx   = CS_Scorer::context( true );
				$slice = array_slice( $ids, $cursor, $batch );
				$mine  = $slice ? CS_Indexer::load_rows( $slice ) : array();
				$cands = array();
				$need  = array();
				foreach ( $slice as $id ) {
					if ( ! isset( $mine[ $id ] ) ) {
						continue;
					}
					$list = array();
					foreach ( CS_Scorer::candidates( $mine[ $id ], $idx ) as $other ) {
						if ( $other > $id ) {
							$list[]         = $other;
							$need[ $other ] = true;
						}
					}
					$cands[ $id ] = $list;
				}
				// توجه: load_rows با آرایه‌ی خالی «همه» را برمی‌گرداند، پس فقط وقتی $need پر است صدا می‌زنیم
				$others = $need ? CS_Indexer::load_rows( array_keys( $need ) ) : array();
				$used   = 0;
				foreach ( $slice as $id ) {
					if ( $used > 0 && microtime( true ) > $deadline ) {
						break;
					}
					$used++;
					if ( empty( $cands[ $id ] ) ) {
						continue;
					}
					foreach ( $cands[ $id ] as $other ) {
						if ( ! isset( $others[ $other ] ) ) {
							continue;
						}
						$res = CS_Scorer::pair( $mine[ $id ], $others[ $other ], $ctx );
						if ( $res['core'] && $res['risk'] * 100 >= $ctx['min'] ) {
							CS_Conflicts::upsert( 'duplicate', $id, $other, $res['risk'], $res['reasons'], $res['suggestions'], $state['run'] );
						}
					}
				}
				$cursor += $used;
				if ( $cursor >= $total ) {
					CS_Util::set_blob( 'cs_pair_index', array() );
					$state['phase'] = 'canonical';
					$cursor         = 0;
				}
				break;

			case 'canonical':
				$slice = array_slice( $ids, $cursor, $batch );
				$mine  = $slice ? CS_Indexer::load_rows( $slice ) : array();
				$cctx  = CS_Canonical::ctx( true );
				CS_Canonical::reset_budget();
				$used = 0;
				foreach ( $slice as $id ) {
					if ( $used > 0 && microtime( true ) > $deadline ) {
						break;
					}
					$used++;
					if ( isset( $mine[ $id ] ) ) {
						CS_Canonical::process( $mine[ $id ], $cctx, $state['run'] );
					}
				}
				$cursor += $used;
				if ( $cursor >= $total ) {
					CS_Scan::finish( $state['run'] );
					$state['phase'] = 'done';
					$cursor         = 0;
				}
				break;
		}

		$state['cursor']  = $cursor;
		$state['updated'] = time();
		$state['errors']  = 0;
		update_option( self::STATE_OPTION, $state, false );

		return array(
			'phase'    => $state['phase'],
			'progress' => self::progress( $state ),
			'done'     => ( 'done' === $state['phase'] ),
			'message'  => self::phase_label( $state['phase'] ),
		);
	}

	public static function current_state() {
		$state = get_option( self::STATE_OPTION );
		return is_array( $state ) ? $state : array( 'phase' => 'idle' );
	}

	private static function progress( array $state ) {
		if ( 'done' === $state['phase'] ) {
			return 100;
		}
		$weights = array( 'index' => 35, 'anchors' => 20, 'sitemap' => 5, 'score' => 30, 'canonical' => 10 );
		$order   = array( 'index', 'anchors', 'sitemap', 'score', 'canonical' );
		$done_w  = 0;
		$cur_w   = 0;
		foreach ( $order as $p ) {
			if ( $p === $state['phase'] ) {
				$cur_w = $weights[ $p ];
				break;
			}
			$done_w += $weights[ $p ];
		}
		$total = max( 1, (int) $state['total'] );
		$frac  = ( isset( $state['cursor'] ) ? (int) $state['cursor'] : 0 ) / $total;
		return (int) min( 99, round( $done_w + $cur_w * $frac ) );
	}

	private static function phase_label( $phase ) {
		$map = array(
			'index'     => 'در حال ایندکس‌کردن مقاله‌ها…',
			'anchors'   => 'در حال بررسی لینک‌های داخلی…',
			'sitemap'   => 'در حال بررسی نقشه‌ی سایت…',
			'score'     => 'در حال تحلیل شباهت مقاله‌ها…',
			'canonical' => 'در حال بررسی canonical…',
			'done'      => 'اسکن تمام شد.',
		);
		return isset( $map[ $phase ] ) ? $map[ $phase ] : $phase;
	}
}
}

/* =========================================================================
 * ریدایرکت ۳۰۱ با استفاده از رنک‌مث (RankMath)
 *
 * توجه: ساختار داخلی رنک‌مث ممکن است بین نسخه‌ها کمی تفاوت داشته باشد.
 * این کلاس اول روش رسمی (کلاس Redirection خود رنک‌مث) را امتحان می‌کند و
 * در صورت نبودن آن، مستقیم در جدول دیتابیس رنک‌مث درج می‌کند. اگر رنک‌مث
 * فعال نباشد یا ماژول ریدایرکت آن روشن نباشد، create() مقدار false
 * برمی‌گرداند و — طبق طراحی — مقاله‌ی مبدأ حذف نخواهد شد (برای جلوگیری از
 * از دست رفتن محتوا بدون ریدایرکت).
 * ====================================================================== */
if ( ! class_exists( 'CS_RankMath_Redirect', false ) ) {
final class CS_RankMath_Redirect {

	public static function is_available() {
		if ( class_exists( '\RankMath\Redirections\Redirection' ) ) {
			return true;
		}
		global $wpdb;
		$tb = $wpdb->prefix . 'rank_math_redirections';
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tb ) );
	}

	/** ایجاد ریدایرکت؛ خروجی: true در صورت موفقیت، false در غیر این صورت */
	public static function create( $from_path, $to_url, $code = 301 ) {
		$from_path = '/' . ltrim( (string) $from_path, '/' );
		$to_url    = (string) $to_url;
		if ( '' === trim( $from_path, '/' ) || '' === $to_url ) {
			return false;
		}

		if ( class_exists( '\RankMath\Redirections\Redirection' ) ) {
			try {
				$redirection = \RankMath\Redirections\Redirection::from( array(
					array( 'pattern' => ltrim( $from_path, '/' ), 'comparison' => 'exact' ),
				), $to_url, (int) $code );
				if ( $redirection ) {
					if ( method_exists( $redirection, 'set_status' ) ) {
						$redirection->set_status( 'active' );
					}
					if ( method_exists( $redirection, 'save' ) ) {
						$saved = $redirection->save();
						if ( $saved ) {
							return true;
						}
					}
				}
			} catch ( \Throwable $e ) {
				// اگر API کلاس در این نسخه از رنک‌مث فرق دارد، به روش مستقیم می‌رویم
			}
		}

		return self::insert_direct( $from_path, $to_url, (int) $code );
	}

	private static function insert_direct( $from_path, $to_url, $code ) {
		global $wpdb;
		$tb     = $wpdb->prefix . 'rank_math_redirections';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tb ) );
		if ( ! $exists ) {
			return false;
		}
		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert( $tb, array(
			'sources'     => maybe_serialize( array( array( 'pattern' => ltrim( $from_path, '/' ), 'comparison' => 'exact' ) ) ),
			'url_to'      => $to_url,
			'header_code' => (int) $code,
			'hits'        => 0,
			'status'      => 'active',
			'created'     => $now,
			'updated'     => $now,
		) );
		if ( $ok ) {
			delete_transient( 'rank_math_redirections' );
			return true;
		}
		return false;
	}
}
}

/* =========================================================================
 * ریشه‌یابی سبک فارسی (و انگلیسی) — جایگزین split ساده روی فاصله
 * ------------------------------------------------------------------------
 * سه لایه:
 *  ۱) افعال: جدول جفت‌های (ریشه‌ی گذشته ← ریشه‌ی حال) برای پرکاربردترین افعال؛
 *     «نوشتن / نوشته / نوشت / می‌نویسد / بنویسید / ننوشتم» همه به «نوشت» می‌رسند.
 *  ۲) اسم/صفت: حذف پسوندهای جمع، تفضیلی، «ی» و «ه» به‌صورت محافظه‌کارانه
 *     (حداقل طول ریشه ۳ نویسه) تا واژه‌های نامرتبط با هم یکی نشوند.
 *  ۳) انگلیسی: فقط جمع (s/es/ies) و ing/ed در واژه‌های بلند.
 * ریشه فقط برای «مقایسه» استفاده می‌شود و هیچ‌جا به کاربر نمایش داده نمی‌شود.
 * ====================================================================== */
if ( ! class_exists( 'CS_Stem', false ) ) {
final class CS_Stem {

	private static $memo    = array();
	private static $past    = null;
	private static $pres    = null;
	private static $enabled = null;

	/**
	 * جدول افعال: ریشه‌ی گذشته => ریشه‌ی حال.
	 * ریشه‌ی حالِ خالی یعنی فقط صورت‌های گذشته (نوشت، نوشتن، نوشته…) شناخته شود؛
	 * برای ریشه‌های حالی که با واژه‌های رایج هم‌شکل‌اند (خر، بر، رس، خواب، کار…) عمداً خالی گذاشته شده تا خطا نکند.
	 */
	private static function verb_table() {
		return (array) apply_filters( 'cs_stem_verbs', array(
			'نوشت' => 'نویس', 'رفت' => '', 'آمد' => 'آی', 'دید' => 'بین', 'گفت' => 'گو',
			'کرد' => 'کن', 'شد' => 'شو', 'داد' => 'ده', 'خورد' => 'خور', 'گرفت' => 'گیر',
			'ساخت' => 'ساز', 'شناخت' => 'شناس', 'دانست' => 'دان', 'خواست' => 'خواه', 'خواند' => 'خوان',
			'فروخت' => 'فروش', 'بست' => 'بند', 'یافت' => 'یاب', 'برد' => '', 'آورد' => 'آور',
			'ماند' => 'مان', 'نشست' => 'نشین', 'ایستاد' => 'ایست', 'افتاد' => 'افت', 'زد' => 'زن',
			'کشید' => '', 'پرداخت' => 'پرداز', 'انداخت' => 'انداز', 'ریخت' => 'ریز', 'داشت' => 'دار',
			'توانست' => 'توان', 'پذیرفت' => 'پذیر', 'گذاشت' => 'گذار', 'گذشت' => 'گذر', 'رساند' => 'رسان',
			'رسید' => '', 'پرسید' => 'پرس', 'فهمید' => 'فهم', 'چید' => 'چین', 'خرید' => '',
			'پخت' => '', 'دوخت' => 'دوز', 'سوخت' => 'سوز', 'آموخت' => 'آموز', 'آفرید' => 'آفرین',
			'فرستاد' => 'فرست', 'نمود' => 'نمای', 'شمرد' => 'شمار', 'گشت' => 'گرد', 'کاست' => 'کاه',
			'بخشید' => 'بخش', 'پیوست' => 'پیوند', 'شکست' => 'شکن', 'کوشید' => 'کوش', 'خندید' => 'خند',
			'خوابید' => '', 'شنید' => 'شنو', 'پوشید' => 'پوش', 'رسانید' => 'رسان', 'سپرد' => 'سپار',
			'افزود' => 'افزای', 'آزمود' => 'آزمای', 'نگاشت' => 'نگار', 'برداشت' => 'بردار', 'بود' => 'باش',
			'گزید' => 'گزین',
		) );
	}

	private static function load_verbs() {
		if ( null !== self::$past ) {
			return;
		}
		self::$past = array();
		self::$pres = array();
		foreach ( self::verb_table() as $past => $pres ) {
			$past = trim( str_replace( "\xE2\x80\x8C", '', (string) $past ) );
			$pres = trim( str_replace( "\xE2\x80\x8C", '', (string) $pres ) );
			if ( '' === $past ) {
				continue;
			}
			self::$past[ $past ] = $past;
			if ( '' !== $pres && ! isset( self::$pres[ $pres ] ) ) {
				self::$pres[ $pres ] = $past;
			}
		}
	}

	public static function enabled() {
		if ( null === self::$enabled ) {
			$s             = CS_Util::settings();
			self::$enabled = ! isset( $s['stemming'] ) || ! empty( $s['stemming'] );
		}
		return self::$enabled;
	}

	public static function reset() {
		self::$enabled = null;
		self::$memo    = array();
	}

	/** ریشه‌ی یک توکن نرمال‌شده (خروجی CS_Compat::norm_text تک‌کلمه‌ای) */
	public static function stem( $tok ) {
		$tok = (string) $tok;
		if ( '' === $tok || ! self::enabled() ) {
			return $tok;
		}
		if ( isset( self::$memo[ $tok ] ) ) {
			return self::$memo[ $tok ];
		}
		$out = self::compute( $tok );
		$out = (string) apply_filters( 'cs_stem_token', $out, $tok );
		if ( count( self::$memo ) >= 60000 ) {
			self::$memo = array();
		}
		self::$memo[ $tok ] = $out;
		return $out;
	}

	/** آرایه‌ای از توکن‌ها → آرایه‌ای از ریشه‌ها (بدون خالی) */
	public static function tokens( $norm_text ) {
		$out = array();
		foreach ( explode( ' ', (string) $norm_text ) as $t ) {
			if ( '' === $t ) {
				continue;
			}
			$s = self::stem( $t );
			if ( '' !== $s ) {
				$out[] = $s;
			}
		}
		return $out;
	}

	/** رشته‌ی نرمال‌شده → رشته‌ی ریشه‌ها */
	public static function text( $norm_text ) {
		return implode( ' ', self::tokens( $norm_text ) );
	}

	private static function compute( $t ) {
		$t = str_replace( "\xE2\x80\x8C", '', $t );
		if ( preg_match( '/^[a-z]+$/', $t ) ) {
			return self::english( $t );
		}
		if ( ! preg_match( '/[\x{0600}-\x{06FF}]/u', $t ) ) {
			return $t;
		}
		$len = CS_Compat::strlen( $t );
		if ( $len < 3 ) {
			return $t;
		}
		$v = self::verb_lemma( $t );
		if ( null !== $v ) {
			return $v;
		}
		return self::strip_suffixes( $t );
	}

	private static function english( $t ) {
		$n = strlen( $t );
		if ( $n > 5 && 'ies' === substr( $t, -3 ) ) {
			return substr( $t, 0, -3 ) . 'y';
		}
		if ( $n > 6 && 'ing' === substr( $t, -3 ) ) {
			return substr( $t, 0, -3 );
		}
		if ( $n > 5 && 'ed' === substr( $t, -2 ) ) {
			return substr( $t, 0, -2 );
		}
		if ( $n > 4 && 'es' === substr( $t, -2 ) && preg_match( '/(s|x|z|ch|sh)es$/', $t ) ) {
			return substr( $t, 0, -2 );
		}
		if ( $n > 3 && 's' === substr( $t, -1 ) && ! preg_match( '/(ss|us|is)$/', $t ) ) {
			return substr( $t, 0, -1 );
		}
		return $t;
	}

	private static function ends_with( $s, $suf ) {
		$l = strlen( $suf );
		return $l > 0 && strlen( $s ) >= $l && substr( $s, -$l ) === $suf;
	}

	private static function starts_with( $s, $pre ) {
		return '' === $pre || 0 === strpos( $s, $pre );
	}

	private static function cut_end( $s, $suf ) {
		return substr( $s, 0, strlen( $s ) - strlen( $suf ) );
	}

	/** اگر توکن صورتی از یکی از افعال جدول باشد، ریشه‌ی گذشته‌ی آن را برمی‌گرداند؛ وگرنه null */
	private static function verb_lemma( $t ) {
		self::load_verbs();
		$past_pre = array( '', 'نمی', 'می', 'ن' );
		$past_suf = array( '', 'ه', 'ن', 'م', 'ی', 'یم', 'ید', 'ند' );
		$pres_pre = array( '', 'نمی', 'می', 'ب', 'ن' );
		$pres_suf = array( '', 'م', 'د', 'یم', 'ید', 'ند' );
		$two_suf  = array( 'د', 'ند', 'ید', 'یم' ); // ریشه‌های دوحرفی (کن، ده، شو، گو، زن، آی) فقط با این پسوندها

		foreach ( array( '', 'نمی', 'می', 'ن', 'ب' ) as $pre ) {
			if ( '' !== $pre && ! self::starts_with( $t, $pre ) ) {
				continue;
			}
			$rest = ( '' === $pre ) ? $t : substr( $t, strlen( $pre ) );
			if ( CS_Compat::strlen( $rest ) < 2 ) {
				continue;
			}
			foreach ( array( '', 'ه', 'ن', 'م', 'ی', 'د', 'یم', 'ید', 'ند' ) as $suf ) {
				if ( '' !== $suf && ! self::ends_with( $rest, $suf ) ) {
					continue;
				}
				$core = ( '' === $suf ) ? $rest : self::cut_end( $rest, $suf );
				$cl   = CS_Compat::strlen( $core );
				if ( $cl < 2 ) {
					continue;
				}

				if ( isset( self::$past[ $core ] ) && in_array( $pre, $past_pre, true ) && in_array( $suf, $past_suf, true ) ) {
					return self::$past[ $core ];
				}

				if ( isset( self::$pres[ $core ] ) && in_array( $pre, $pres_pre, true ) && in_array( $suf, $pres_suf, true ) ) {
					if ( '' === $pre && '' === $suf ) {
						continue; // ریشه‌ی حالِ تنها (ساز، گیر، بین…) فعل حساب نمی‌شود
					}
					if ( '' === $suf && ( 'می' === $pre || 'نمی' === $pre ) ) {
						continue; // «می‌دان» فعل نیست؛ (میدان = square)
					}
					if ( $cl <= 2 && ! in_array( $suf, $two_suf, true ) ) {
						continue;
					}
					return self::$pres[ $core ];
				}
			}
		}
		return null;
	}

	private static function strip_suffixes( $t ) {
		$order = array( 'ترین', 'تر', 'های', 'ها', 'ات', 'گان', 'ان', 'ی', 'ه' );
		for ( $i = 0; $i < 3; $i++ ) {
			$changed = false;
			foreach ( $order as $suf ) {
				if ( ! self::ends_with( $t, $suf ) ) {
					continue;
				}
				$stem = self::cut_end( $t, $suf );
				if ( CS_Compat::strlen( $stem ) >= 3 ) {
					$t       = $stem;
					$changed = true;
					break;
				}
			}
			if ( ! $changed ) {
				break;
			}
		}
		return $t;
	}
}
}

/* =========================================================================
 * خواندن داده‌های سئو از افزونه‌های Yoast / Rank Math / All in One SEO / SEOPress
 * ------------------------------------------------------------------------
 * فقط از افزونه‌هایی که «فعال‌اند» خوانده می‌شود (تا متای باقی‌مانده از افزونه‌ی
 * قبلی، canonical یا noindex غلط تولید نکند). اگر هیچ‌کدام شناسایی نشد،
 * از همه خوانده می‌شود. با فیلتر cs_seo_plugins قابل تغییر است.
 * ====================================================================== */
if ( ! class_exists( 'CS_SEO', false ) ) {
final class CS_SEO {

	private static $aio       = array();
	private static $aio_table = null;
	private static $active    = null;

	public static function labels() {
		return array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'All in One SEO',
			'seopress' => 'SEOPress',
		);
	}

	/** کلید افزونه‌های سئوی فعال (yoast, rankmath, aioseo, seopress) */
	public static function active() {
		if ( null !== self::$active ) {
			return self::$active;
		}
		$a = array();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$a[] = 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ) {
			$a[] = 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$a[] = 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ) ) {
			$a[] = 'seopress';
		}
		self::$active = array_values( array_intersect( array_keys( self::labels() ), (array) apply_filters( 'cs_seo_plugins', $a ) ) );
		return self::$active;
	}

	/** افزونه‌هایی که باید داده‌شان خوانده شود (فعال‌ها؛ در نبودِ هیچ‌کدام همه) */
	private static function sources() {
		$a = self::active();
		return $a ? $a : array_keys( self::labels() );
	}

	/* ---------- All in One SEO: جدول wp_aioseo_posts ---------- */

	private static function aio_table() {
		global $wpdb;
		if ( null === self::$aio_table ) {
			$tb              = $wpdb->prefix . 'aioseo_posts';
			$found           = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tb ) );
			self::$aio_table = ( $found && 0 === strcasecmp( (string) $found, $tb ) ) ? (string) $found : '';
		}
		return self::$aio_table;
	}

	/** یک‌جا خواندن ردیف AIOSEO همه‌ی مقاله‌ها (به‌جای یک کوئری برای هر مقاله) */
	public static function prefetch( array $ids ) {
		global $wpdb;
		self::$aio = array();
		if ( ! in_array( 'aioseo', self::sources(), true ) || '' === self::aio_table() ) {
			return;
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in   = implode( ',', $chunk );
			$rows = $wpdb->get_results( 'SELECT * FROM ' . self::aio_table() . " WHERE post_id IN ({$in})", ARRAY_A ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				self::$aio[ (int) $r['post_id'] ] = $r;
			}
			foreach ( $chunk as $id ) {
				if ( ! isset( self::$aio[ $id ] ) ) {
					self::$aio[ $id ] = array();
				}
			}
		}
	}

	private static function aio_row( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( isset( self::$aio[ $id ] ) ) {
			return self::$aio[ $id ];
		}
		if ( '' === self::aio_table() ) {
			return array();
		}
		$r                = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::aio_table() . ' WHERE post_id = %d', $id ), ARRAY_A ); // phpcs:ignore
		self::$aio[ $id ] = is_array( $r ) ? $r : array();
		return self::$aio[ $id ];
	}

	/* ---------- کلمات کلیدی ---------- */

	private static function push( array &$out, array &$seen, $kw ) {
		$kw = trim( (string) $kw );
		if ( '' === $kw ) {
			return;
		}
		$k = CS_Compat::norm_text( $kw );
		if ( '' === $k || isset( $seen[ $k ] ) ) {
			return;
		}
		$seen[ $k ] = true;
		$out[]      = array( 'key' => $kw, 'raw' => $kw );
	}

	public static function focus_keywords( $id ) {
		$id   = (int) $id;
		$out  = array();
		$seen = array();
		foreach ( self::sources() as $src ) {
			if ( 'yoast' === $src ) {
				$y = get_post_meta( $id, '_yoast_wpseo_focuskw', true );
				if ( is_string( $y ) ) {
					self::push( $out, $seen, $y );
				}
			} elseif ( 'rankmath' === $src ) {
				$rm = get_post_meta( $id, 'rank_math_focus_keyword', true );
				if ( is_string( $rm ) ) {
					foreach ( explode( ',', $rm ) as $k ) {
						self::push( $out, $seen, $k );
					}
				}
			} elseif ( 'aioseo' === $src ) {
				$row = self::aio_row( $id );
				if ( ! empty( $row['keyphrases'] ) ) {
					$kp = is_array( $row['keyphrases'] ) ? $row['keyphrases'] : json_decode( (string) $row['keyphrases'], true );
					if ( is_array( $kp ) ) {
						if ( ! empty( $kp['focus']['keyphrase'] ) ) {
							self::push( $out, $seen, $kp['focus']['keyphrase'] );
						}
						if ( ! empty( $kp['additional'] ) && is_array( $kp['additional'] ) ) {
							foreach ( $kp['additional'] as $ad ) {
								if ( is_array( $ad ) && ! empty( $ad['keyphrase'] ) ) {
									self::push( $out, $seen, $ad['keyphrase'] );
								}
							}
						}
					}
				}
			} elseif ( 'seopress' === $src ) {
				$sp = get_post_meta( $id, '_seopress_analysis_target_kw', true );
				if ( is_string( $sp ) ) {
					foreach ( explode( ',', $sp ) as $k ) {
						self::push( $out, $seen, $k );
					}
				}
			}
		}
		return (array) apply_filters( 'cs_post_keywords', $out, $id );
	}

	/* ---------- canonical و noindex ---------- */

	public static function canonical( $id ) {
		$id = (int) $id;
		foreach ( self::sources() as $src ) {
			$v = '';
			if ( 'yoast' === $src ) {
				$v = get_post_meta( $id, '_yoast_wpseo_canonical', true );
			} elseif ( 'rankmath' === $src ) {
				$v = get_post_meta( $id, 'rank_math_canonical_url', true );
			} elseif ( 'aioseo' === $src ) {
				$row = self::aio_row( $id );
				$v   = isset( $row['canonical_url'] ) ? $row['canonical_url'] : '';
			} elseif ( 'seopress' === $src ) {
				$v = get_post_meta( $id, '_seopress_robots_canonical', true );
			}
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				return trim( $v );
			}
		}
		return '';
	}

	public static function noindex( $id ) {
		$id = (int) $id;
		foreach ( self::sources() as $src ) {
			if ( 'yoast' === $src ) {
				if ( '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
					return true;
				}
			} elseif ( 'rankmath' === $src ) {
				$v = get_post_meta( $id, 'rank_math_robots', true );
				if ( is_array( $v ) && in_array( 'noindex', $v, true ) ) {
					return true;
				}
			} elseif ( 'aioseo' === $src ) {
				$row = self::aio_row( $id );
				// robots_default=1 یعنی «از تنظیمات کلی پیروی کن»؛ فقط وقتی سفارشی شده و noindex روشن است
				if ( isset( $row['robots_noindex'] ) && '1' === (string) $row['robots_noindex'] && isset( $row['robots_default'] ) && '0' === (string) $row['robots_default'] ) {
					return true;
				}
			} elseif ( 'seopress' === $src ) {
				if ( 'yes' === get_post_meta( $id, '_seopress_robots_index', true ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
}

/* =========================================================================
 * آمار و روند هفتگی (برای ویجت داشبورد)
 * ====================================================================== */
if ( ! class_exists( 'CS_Stats', false ) ) {
final class CS_Stats {

	const OPT = 'cs_history';

	/** بعد از هر اسکن، خلاصه‌ی وضعیت آن روز ذخیره می‌شود (هر روز یک رکورد) */
	public static function snapshot() {
		$c = CS_Conflicts::counts();
		$h = get_option( self::OPT, array() );
		$h = is_array( $h ) ? $h : array();

		$h[ gmdate( 'Y-m-d' ) ] = array(
			'ts' => time(),
			'c'  => (int) $c['critical'],
			'h'  => (int) $c['high'],
			'm'  => (int) $c['medium'],
			'l'  => (int) $c['low'],
			't'  => (int) $c['total'],
		);
		ksort( $h );
		if ( count( $h ) > 120 ) {
			$h = array_slice( $h, -120, null, true );
		}
		update_option( self::OPT, $h, false );
	}

	public static function history() {
		$h = get_option( self::OPT, array() );
		return is_array( $h ) ? $h : array();
	}

	public static function summary() {
		global $wpdb;
		$tb    = CS_Util::t( 'conflicts' );
		$c     = CS_Conflicts::counts();
		$week  = 7 * 86400;
		$since = gmdate( 'Y-m-d H:i:s', time() - $week );

		$new_week = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$tb} WHERE status='new' AND score >= 80 AND created_at >= %s",
			$since
		) );

		$hist = self::history();
		$cut  = gmdate( 'Y-m-d', time() - $week );
		$prev = null;
		foreach ( $hist as $day => $snap ) {
			if ( $day <= $cut ) {
				$prev = $snap;
			}
		}
		$delta = is_array( $prev ) ? (int) $c['critical'] - (int) $prev['c'] : null;

		$spark = array();
		foreach ( array_slice( $hist, -14, null, true ) as $snap ) {
			$spark[] = (int) $snap['c'];
		}

		$top = $wpdb->get_results(
			"SELECT * FROM {$tb} WHERE status='new' AND score >= 60 ORDER BY score DESC, updated_at DESC LIMIT 5",
			ARRAY_A
		);

		return array(
			'counts'   => $c,
			'new_week' => $new_week,
			'delta'    => $delta,
			'spark'    => $spark,
			'top'      => is_array( $top ) ? $top : array(),
			'last'     => (int) get_option( 'cs_last_scan', 0 ),
		);
	}

	/** نمودار خطی کوچک (SVG) از آرایه‌ی اعداد */
	public static function sparkline( array $vals, $w = 120, $h = 32 ) {
		$n = count( $vals );
		if ( $n < 2 ) {
			return '';
		}
		$max = max( max( $vals ), 1 );
		$pts = array();
		foreach ( $vals as $i => $v ) {
			$x     = round( $i * ( $w - 4 ) / ( $n - 1 ) + 2, 1 );
			$y     = round( $h - 3 - ( $v / $max ) * ( $h - 6 ), 1 );
			$pts[] = $x . ',' . $y;
		}
		return '<svg width="' . (int) $w . '" height="' . (int) $h . '" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" aria-hidden="true"><polyline fill="none" stroke="#c62828" stroke-width="2" points="' . esc_attr( implode( ' ', $pts ) ) . '"/></svg>';
	}
}
}

/* =========================================================================
 * ویجت داشبورد وردپرس
 * ====================================================================== */
if ( ! class_exists( 'CS_Widget', false ) ) {
final class CS_Widget {

	public static function register() {
		if ( ! current_user_can( CS_Util::view_cap() ) ) {
			return;
		}
		wp_add_dashboard_widget( 'cs_dashboard_widget', 'وضعیت کنیبالیزیشن محتوا', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		$s = CS_Stats::summary();
		$c = $s['counts'];

		if ( ! $s['last'] ) {
			echo '<p>هنوز اسکنی انجام نشده است.';
			if ( current_user_can( 'manage_options' ) ) {
				echo ' <a href="' . esc_url( admin_url( 'admin.php?page=' . CS_Settings_Page::SLUG ) ) . '">اجرای اولین اسکن</a>';
			}
			echo '</p>';
			return;
		}

		if ( null === $s['delta'] ) {
			$trend = '<span style="color:#646970;">مقایسه با هفته‌ی قبل: هنوز داده‌ی کافی نیست</span>';
		} elseif ( $s['delta'] > 0 ) {
			$trend = '<span style="color:#c62828;">▲ ' . (int) $s['delta'] . ' مورد بیشتر از هفته‌ی قبل</span>';
		} elseif ( $s['delta'] < 0 ) {
			$trend = '<span style="color:#2e7d32;">▼ ' . abs( (int) $s['delta'] ) . ' مورد کمتر از هفته‌ی قبل</span>';
		} else {
			$trend = '<span style="color:#646970;">بدون تغییر نسبت به هفته‌ی قبل</span>';
		}
		?>
		<div class="cs-w">
			<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
				<div style="flex:1 1 90px;text-align:center;background:#fdf1f1;border-radius:8px;padding:8px;">
					<div style="font-size:24px;font-weight:700;color:#c62828;line-height:1.2;"><?php echo (int) $c['critical']; ?></div>
					<div style="font-size:11px;color:#646970;">بحرانی باز</div>
				</div>
				<div style="flex:1 1 90px;text-align:center;background:#fff6ec;border-radius:8px;padding:8px;">
					<div style="font-size:24px;font-weight:700;color:#e65100;line-height:1.2;"><?php echo (int) $s['new_week']; ?></div>
					<div style="font-size:11px;color:#646970;">بحرانی جدید این هفته</div>
				</div>
				<div style="flex:1 1 90px;text-align:center;background:#f6f7f7;border-radius:8px;padding:8px;">
					<div style="font-size:24px;font-weight:700;color:#3c434a;line-height:1.2;"><?php echo (int) $c['total']; ?></div>
					<div style="font-size:11px;color:#646970;">در انتظار بررسی</div>
				</div>
			</div>
			<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;font-size:12px;">
				<div><?php echo $trend; // phpcs:ignore ?></div>
				<div><?php echo CS_Stats::sparkline( $s['spark'] ); // phpcs:ignore ?></div>
			</div>
			<?php if ( $s['top'] ) : ?>
				<div style="font-size:12px;font-weight:600;margin-bottom:4px;">بالاترین ریسک‌ها</div>
				<ul style="margin:0;">
					<?php
					foreach ( $s['top'] as $r ) :
						$score = (int) $r['score'];
						$level = CS_Risk::level( $score );
						$color = CS_Risk::color( $level );
						$a     = (int) $r['post_a'];
						$b     = (int) $r['post_b'];
						$label = CS_Util::title( $a );
						if ( $b > 0 ) {
							$label .= ' ⟷ ' . CS_Util::title( $b );
						}
						$link = ( $b > 0 ) ? CS_Compare::url( $a, $b ) : get_edit_post_link( $a );
						?>
						<li style="margin-bottom:5px;">
							<span style="display:inline-block;min-width:34px;text-align:center;border-radius:999px;padding:0 6px;font-size:11px;font-weight:700;color:<?php echo esc_attr( $color ); ?>;background:<?php echo esc_attr( $color ); ?>1a;"><?php echo $score; ?></span>
							<?php if ( $link ) : ?>
								<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( wp_trim_words( $label, 14, '…' ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( wp_trim_words( $label, 14, '…' ) ); ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p style="margin:10px 0 0;font-size:12px;color:#646970;">
				آخرین اسکن: <?php echo esc_html( CS_Util::local_date( 'Y/m/d H:i', $s['last'] ) ); ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					· <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CS_Admin_Menu::SLUG ) ); ?>">گزارش کامل</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}
}

/* =========================================================================
 * اعلان ایمیل / تلگرام برای موارد بحرانی جدید
 * ------------------------------------------------------------------------
 * بعد از هر اسکن، موارد «جدید» که امتیازشان ≥ آستانه است و قبلاً اعلان نشده‌اند
 * (ستون notified_at خالی) در یک پیام خلاصه ارسال می‌شوند. اگر مورد حل‌شده دوباره
 * ظاهر شود، دوباره اعلان می‌شود. اگر همه‌ی کانال‌ها خطا بدهند، اعلان‌نشده می‌ماند
 * و اسکن بعدی دوباره تلاش می‌کند.
 * ====================================================================== */
if ( ! class_exists( 'CS_Notify', false ) ) {
final class CS_Notify {

	public static function after_scan( $run ) {
		$s = CS_Util::settings( true );
		if ( empty( $s['notify_on'] ) ) {
			return 0;
		}
		global $wpdb;
		$tb  = CS_Util::t( 'conflicts' );
		$min = max( 1, min( 100, (int) $s['notify_min'] ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$tb} WHERE status='new' AND score >= %d AND notified_at IS NULL ORDER BY score DESC, id ASC LIMIT 200",
			$min
		), ARRAY_A );
		if ( ! $rows ) {
			return 0;
		}

		$text   = self::compose( $rows );
		$errors = array();
		$sent   = self::dispatch( $text['subject'], $text['body'], $s, $errors );
		if ( $sent > 0 ) {
			$ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
			$in  = implode( ',', $ids );
			$wpdb->query( $wpdb->prepare( "UPDATE {$tb} SET notified_at=%s WHERE id IN ({$in})", gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore
		}
		update_option( 'cs_notify_last', array( 'time' => time(), 'count' => count( $rows ), 'sent' => $sent, 'errors' => $errors ), false );
		return $sent;
	}

	private static function compose( array $rows ) {
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$total = count( $rows );
		$lines = array();
		$lines[] = 'اسکن جدید کنیبالیزیشن محتوا: ' . $total . ' مورد با ریسک بالا پیدا شد.';
		$lines[] = '';

		$i = 0;
		foreach ( $rows as $r ) {
			if ( ++$i > 10 ) {
				break;
			}
			$score = (int) $r['score'];
			$a     = CS_Util::title( (int) $r['post_a'] );
			$b     = ( (int) $r['post_b'] > 0 ) ? CS_Util::title( (int) $r['post_b'] ) : '';
			$line  = $i . ') [' . $score . '٪ ' . CS_Risk::label( CS_Risk::level( $score ) ) . '] ' . $a;
			if ( '' !== $b ) {
				$line .= '  ⟷  ' . $b;
			} else {
				$line .= '  (مشکل canonical)';
			}
			$lines[] = $line;

			$reasons = json_decode( (string) $r['reasons'], true );
			if ( is_array( $reasons ) && $reasons ) {
				usort( $reasons, function ( $x, $y ) {
					return ( isset( $y['pts'] ) ? (float) $y['pts'] : 0 ) <=> ( isset( $x['pts'] ) ? (float) $x['pts'] : 0 );
				} );
				$titles  = array( 'A' => $a, 'B' => $b );
				$lines[] = '   دلیل اصلی: ' . CS_Conflicts::plain( isset( $reasons[0]['text'] ) ? $reasons[0]['text'] : '', $titles );
			}
			if ( (int) $r['post_b'] > 0 ) {
				$lines[] = '   مقایسه: ' . CS_Compare::url( (int) $r['post_a'], (int) $r['post_b'] );
			}
			$lines[] = '';
		}
		if ( $total > 10 ) {
			$lines[] = 'و ' . ( $total - 10 ) . ' مورد دیگر…';
			$lines[] = '';
		}
		$lines[] = 'گزارش کامل: ' . admin_url( 'admin.php?page=' . CS_Admin_Menu::SLUG );

		return array(
			'subject' => '[' . $site . '] ' . $total . ' مورد بحرانی جدید در کنیبالیزیشن محتوا',
			'body'    => implode( "\n", $lines ),
		);
	}

	/** تعداد کانال‌های موفق را برمی‌گرداند؛ خطاها در $errors (کلید = نام کانال) */
	public static function dispatch( $subject, $body, array $s, array &$errors ) {
		$ok = 0;

		if ( ! empty( $s['notify_email_on'] ) ) {
			$to = array();
			foreach ( preg_split( '/[\s,;،]+/u', (string) $s['notify_emails'], -1, PREG_SPLIT_NO_EMPTY ) as $e ) {
				if ( is_email( $e ) ) {
					$to[] = $e;
				}
			}
			if ( ! $to ) {
				$to = array( get_option( 'admin_email' ) );
			}
			$sent = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
			if ( $sent ) {
				$ok++;
			} else {
				$errors['email'] = 'wp_mail ارسال را ناموفق اعلام کرد (تنظیمات ایمیل/SMTP هاست را بررسی کنید)';
			}
		}

		if ( ! empty( $s['notify_tg_on'] ) ) {
			$err = self::telegram( $subject . "\n\n" . $body, $s );
			if ( true === $err ) {
				$ok++;
			} else {
				$errors['telegram'] = (string) $err;
			}
		}
		return $ok;
	}

	/** true در صورت موفقیت، وگرنه رشته‌ی خطا */
	private static function telegram( $text, array $s ) {
		$token = trim( (string) $s['tg_token'] );
		$chat  = trim( (string) $s['tg_chat'] );
		if ( '' === $token || '' === $chat ) {
			return 'توکن ربات یا شناسه‌ی چت (Chat ID) وارد نشده است';
		}
		$base = trim( (string) $s['tg_base'] );
		$base = ( '' === $base ) ? 'https://api.telegram.org' : rtrim( $base, '/' );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text, 'UTF-8' ) > 3800 ) {
			$text = mb_substr( $text, 0, 3800, 'UTF-8' ) . '…';
		}
		$res = wp_remote_post( $base . '/bot' . $token . '/sendMessage', array(
			'timeout' => 12,
			'body'    => array(
				'chat_id'                  => $chat,
				'text'                     => $text,
				'disable_web_page_preview' => 'true',
			),
		) );
		if ( is_wp_error( $res ) ) {
			return 'اتصال به تلگرام ناموفق بود: ' . $res->get_error_message();
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 === $code && is_array( $json ) && ! empty( $json['ok'] ) ) {
			return true;
		}
		$desc = ( is_array( $json ) && ! empty( $json['description'] ) ) ? (string) $json['description'] : 'HTTP ' . $code;
		return 'تلگرام خطا برگرداند: ' . $desc;
	}

	/** پیام آزمایشی به همه‌ی کانال‌های فعال (مستقل از وجود مورد بحرانی) */
	public static function send_test() {
		$s      = CS_Util::settings( true );
		$errors = array();
		$site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$sent   = self::dispatch(
			'[' . $site . '] پیام آزمایشی کنیبالیزیشن محتوا',
			'این یک پیام آزمایشی است. اگر آن را می‌بینید، اعلان‌های کنیبالیزیشن محتوا درست کار می‌کنند.',
			$s,
			$errors
		);
		$active = ( ! empty( $s['notify_email_on'] ) ? 1 : 0 ) + ( ! empty( $s['notify_tg_on'] ) ? 1 : 0 );
		return array( 'active' => $active, 'sent' => $sent, 'errors' => $errors );
	}
}
}

/* =========================================================================
 * باکس داخل صفحه‌ی ویرایش مقاله
 * ====================================================================== */
if ( ! class_exists( 'CS_Metabox', false ) ) {
final class CS_Metabox {

	public static function register() {
		if ( ! current_user_can( CS_Util::view_cap() ) ) {
			return;
		}
		$s = CS_Util::settings();
		foreach ( (array) $s['post_types'] as $pt ) {
			add_meta_box( 'cs_conflicts_box', 'تداخل محتوا (کنیبالیزیشن)', array( __CLASS__, 'render' ), $pt, 'side', 'high' );
		}
	}

	public static function render( $post ) {
		$id   = (int) $post->ID;
		$last = (int) get_option( 'cs_last_scan', 0 );
		if ( ! $last ) {
			echo '<p style="margin:0;">هنوز اسکنی انجام نشده است.</p>';
			return;
		}
		$rows = CS_Conflicts::for_post( $id, 'new', 30 );
		$dups = 0;
		$cans = 0;
		foreach ( $rows as $r ) {
			if ( 'canonical' === $r['type'] ) {
				$cans++;
			} else {
				$dups++;
			}
		}

		if ( ! $rows ) {
			echo '<p style="margin:0;color:#2e7d32;font-weight:600;">✓ تداخلی برای این مقاله ثبت نشده است.</p>';
		} else {
			$head = array();
			if ( $dups ) {
				$head[] = 'این مقاله با <strong>' . (int) $dups . '</strong> مقاله‌ی دیگر تداخل دارد';
			}
			if ( $cans ) {
				$head[] = ( $dups ? 'و ' : 'این مقاله ' ) . '<strong>' . (int) $cans . '</strong> مشکل canonical دارد';
			}
			echo '<p style="margin:0 0 8px;">' . implode( ' ', $head ) . '.</p>'; // phpcs:ignore
			echo '<ul style="margin:0;padding:0;list-style:none;">';
			$shown = 0;
			foreach ( $rows as $r ) {
				if ( ++$shown > 6 ) {
					break;
				}
				$score = (int) $r['score'];
				$level = CS_Risk::level( $score );
				$color = CS_Risk::color( $level );
				$badge = '<span style="display:inline-block;border-radius:999px;padding:0 8px;font-size:11px;font-weight:700;color:' . esc_attr( $color ) . ';background:' . esc_attr( $color ) . '1a;border:1px solid ' . esc_attr( $color ) . '55;">' . esc_html( CS_Risk::label( $level ) ) . ' · ' . $score . '</span>';

				echo '<li style="padding:8px 0;border-top:1px solid #f0f0f1;">';
				if ( 'canonical' === $r['type'] ) {
					$reasons = json_decode( (string) $r['reasons'], true );
					$first   = ( is_array( $reasons ) && isset( $reasons[0]['text'] ) ) ? CS_Conflicts::plain( $reasons[0]['text'], array( 'A' => CS_Util::title( $id ), 'B' => '' ) ) : 'مشکل canonical';
					echo $badge . ' <span style="font-size:12px;">' . esc_html( wp_trim_words( $first, 16, '…' ) ) . '</span>'; // phpcs:ignore
				} else {
					$other = ( (int) $r['post_a'] === $id ) ? (int) $r['post_b'] : (int) $r['post_a'];
					$edit  = get_edit_post_link( $other );
					echo $badge . ' '; // phpcs:ignore
					if ( $edit ) {
						echo '<a href="' . esc_url( $edit ) . '" style="font-weight:600;">' . esc_html( CS_Util::title( $other ) ) . '</a>';
					} else {
						echo '<strong>' . esc_html( CS_Util::title( $other ) ) . '</strong>';
					}
					echo '<div style="margin-top:3px;font-size:12px;"><a href="' . esc_url( CS_Compare::url( $id, $other ) ) . '" target="_blank" rel="noopener">مقایسه‌ی کنار هم و بخش‌های مشترک</a></div>';
				}
				echo '</li>';
			}
			echo '</ul>';
			if ( count( $rows ) > 6 && current_user_can( 'manage_options' ) ) {
				echo '<p style="margin:8px 0 0;font-size:12px;"><a href="' . esc_url( admin_url( 'admin.php?page=' . CS_Admin_Menu::SLUG . '&s=' . rawurlencode( CS_Util::title( $id ) ) ) ) . '">مشاهده‌ی همه در گزارش</a></p>';
			}
		}
		echo '<p style="margin:10px 0 0;font-size:11px;color:#8c8f94;">نتیجه‌ی آخرین اسکن (' . esc_html( CS_Util::local_date( 'Y/m/d H:i', $last ) ) . '). تغییرات همین ویرایش در اسکن بعدی دیده می‌شود.</p>';
	}
}
}

/* =========================================================================
 * مقایسه‌ی کنار هم دو مقاله با هایلایت عبارت‌های مشترک
 * ====================================================================== */
if ( ! class_exists( 'CS_Compare', false ) ) {
final class CS_Compare {

	const SLUG  = 'cs-compare';
	const NONCE = 'cs_cmp';

	public static function url( $a, $b ) {
		return add_query_arg( array( 'page' => self::SLUG, 'a' => (int) $a, 'b' => (int) $b ), admin_url( 'admin.php' ) );
	}

	public static function register() {
		// صفحه‌ی مخفی (بدون آیتم منو)؛ از باکس مقاله و گزارش باز می‌شود
		add_submenu_page( 'options.php', 'مقایسه‌ی دو مقاله', 'مقایسه‌ی دو مقاله', CS_Util::view_cap(), self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function maybe_handle_actions() {
		if ( empty( $_GET['page'] ) || self::SLUG !== $_GET['page'] || empty( $_GET['cs_cmp_action'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( self::NONCE ) ) {
			return;
		}
		$act = sanitize_key( wp_unslash( $_GET['cs_cmp_action'] ) ); // phpcs:ignore
		$cid = isset( $_GET['cid'] ) ? (int) $_GET['cid'] : 0; // phpcs:ignore
		$map = array( 'resolve' => 'resolved', 'ignore' => 'ignored', 'reopen' => 'new' );
		if ( $cid > 0 && isset( $map[ $act ] ) ) {
			CS_Conflicts::set_status( $cid, $map[ $act ] );
		}
		wp_safe_redirect( self::url( isset( $_GET['a'] ) ? (int) $_GET['a'] : 0, isset( $_GET['b'] ) ? (int) $_GET['b'] : 0 ) ); // phpcs:ignore
		exit;
	}

	/* ---------- تحلیل متن ---------- */

	private static function paragraphs( $post_id ) {
		$c = (string) get_post_field( 'post_content', (int) $post_id );
		$c = strip_shortcodes( $c );
		$c = preg_replace( '#</(p|div|h[1-6]|li|blockquote|tr|figcaption)>|<br\s*/?>#i', "\n", $c );
		$c = html_entity_decode( wp_strip_all_tags( (string) $c ), ENT_QUOTES, 'UTF-8' );
		$out = array();
		foreach ( preg_split( "/\n+/", (string) $c ) as $p ) {
			$p = trim( (string) preg_replace( '/[ \t\x{00A0}]+/u', ' ', $p ) );
			if ( '' !== $p ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/** کلید مقایسه‌ی یک واژه‌ی نمایشی: ریشه‌ی بلندترین بخشِ نرمال‌شده؛ برای علامت نگارشی خالی */
	private static function key_of( $display ) {
		$n = CS_Compat::norm_text( $display );
		if ( '' === $n ) {
			return '';
		}
		$best = '';
		foreach ( explode( ' ', $n ) as $part ) {
			if ( CS_Compat::strlen( $part ) > CS_Compat::strlen( $best ) ) {
				$best = $part;
			}
		}
		return CS_Stem::stem( $best );
	}

	private static function tokenize( array $paras, $max ) {
		$toks   = array();
		$count  = 0;
		$trunc  = false;
		foreach ( $paras as $pi => $p ) {
			$row = array();
			foreach ( preg_split( '/\s+/u', $p, -1, PREG_SPLIT_NO_EMPTY ) as $w ) {
				if ( $count >= $max ) {
					$trunc = true;
					break 2;
				}
				$row[] = array( 'd' => $w, 'k' => self::key_of( $w ) );
				$count++;
			}
			$toks[ $pi ] = $row;
		}
		return array( 'toks' => $toks, 'truncated' => $trunc, 'count' => $count );
	}

	private static function is_strong( $k, array $stop ) {
		return '' !== $k && ! isset( $stop[ $k ] ) && CS_Compat::strlen( $k ) >= 3;
	}

	/** مجموعه‌ی عبارت‌های n‌واژه‌ای (shingle) یک متن */
	private static function shingle_set( array $toks, $n, array $stop ) {
		$set = array();
		foreach ( $toks as $row ) {
			$seq = array();
			foreach ( $row as $t ) {
				if ( '' !== $t['k'] ) {
					$seq[] = $t['k'];
				}
			}
			$c = count( $seq );
			for ( $i = 0; $i + $n <= $c; $i++ ) {
				$win = array_slice( $seq, $i, $n );
				$st  = 0;
				foreach ( $win as $k ) {
					if ( self::is_strong( $k, $stop ) ) {
						$st++;
					}
				}
				if ( $st >= 2 ) {
					$set[ implode( "\x1f", $win ) ] = true;
				}
			}
		}
		return $set;
	}

	/** واژه‌های متن A را که عضو عبارتی مشترک با مجموعه‌ی B هستند علامت می‌زند */
	private static function mark( array $toks, array $other_set, $n, array $stop ) {
		$hl = array();
		foreach ( $toks as $pi => $row ) {
			$idx = array();
			foreach ( $row as $ti => $t ) {
				if ( '' !== $t['k'] ) {
					$idx[] = $ti;
				}
			}
			$c = count( $idx );
			for ( $i = 0; $i + $n <= $c; $i++ ) {
				$win_i = array_slice( $idx, $i, $n );
				$keys  = array();
				foreach ( $win_i as $ti ) {
					$keys[] = $row[ $ti ]['k'];
				}
				if ( isset( $other_set[ implode( "\x1f", $keys ) ] ) ) {
					foreach ( $win_i as $ti ) {
						$hl[ $pi ][ $ti ] = true;
					}
				}
			}
			// علامت نگارشی تنها بین دو واژه‌ی هایلایت‌شده هم هایلایت شود (برای یکپارچگی)
			if ( ! empty( $hl[ $pi ] ) ) {
				foreach ( $row as $ti => $t ) {
					if ( '' === $t['k'] && ! empty( $hl[ $pi ][ $ti - 1 ] ) && isset( $row[ $ti + 1 ] ) && ! empty( $hl[ $pi ][ $ti + 1 ] ) ) {
						$hl[ $pi ][ $ti ] = true;
					}
				}
			}
		}
		return $hl;
	}

	private static function render_column( array $toks, array $hl, array &$runs ) {
		$html = '';
		foreach ( $toks as $pi => $row ) {
			$p    = '';
			$open = false;
			$run  = array();
			$n    = count( $row );
			foreach ( $row as $ti => $t ) {
				$on = ! empty( $hl[ $pi ][ $ti ] );
				if ( $on ) {
					if ( ! $open ) {
						$p   .= '<mark class="cs-hl">';
						$open = true;
					}
					$p    .= esc_html( $t['d'] );
					$run[] = $t['d'];
					if ( $ti + 1 < $n && ! empty( $hl[ $pi ][ $ti + 1 ] ) ) {
						$p .= ' ';
					} else {
						$p   .= '</mark> ';
						$open = false;
						if ( count( $run ) >= 6 ) {
							$runs[] = implode( ' ', $run );
						}
						$run = array();
					}
				} else {
					$p .= esc_html( $t['d'] ) . ' ';
				}
			}
			$html .= '<p dir="auto">' . $p . '</p>';
		}
		return $html;
	}

	public static function analyze( $a, $b ) {
		$n    = max( 3, min( 8, (int) apply_filters( 'cs_compare_shingle', 4 ) ) );
		$max  = max( 500, (int) apply_filters( 'cs_compare_max_tokens', 8000 ) );
		$stop = array_flip( CS_Indexer::stopwords() );

		$ta = self::tokenize( self::paragraphs( $a ), $max );
		$tb = self::tokenize( self::paragraphs( $b ), $max );

		$sa = self::shingle_set( $ta['toks'], $n, $stop );
		$sb = self::shingle_set( $tb['toks'], $n, $stop );
		$ha = self::mark( $ta['toks'], $sb, $n, $stop );
		$hb = self::mark( $tb['toks'], $sa, $n, $stop );

		$cnt = function ( array $toks, array $hl ) {
			$hit = 0;
			$all = 0;
			foreach ( $toks as $pi => $row ) {
				foreach ( $row as $ti => $t ) {
					if ( '' === $t['k'] ) {
						continue;
					}
					$all++;
					if ( ! empty( $hl[ $pi ][ $ti ] ) ) {
						$hit++;
					}
				}
			}
			return array( $hit, $all );
		};
		list( $hit_a, $all_a ) = $cnt( $ta['toks'], $ha );
		list( $hit_b, $all_b ) = $cnt( $tb['toks'], $hb );

		$runs_a = array();
		$runs_b = array();
		$html_a = self::render_column( $ta['toks'], $ha, $runs_a );
		$html_b = self::render_column( $tb['toks'], $hb, $runs_b );

		usort( $runs_a, function ( $x, $y ) {
			return strlen( $y ) <=> strlen( $x );
		} );

		// نگاشت ریشه → شکل ظاهری (برای نمایش واژه‌های مشترک به‌صورت خوانا)
		$surface = array();
		foreach ( $ta['toks'] as $row ) {
			foreach ( $row as $t ) {
				if ( '' !== $t['k'] && ! isset( $surface[ $t['k'] ] ) ) {
					$surface[ $t['k'] ] = trim( (string) preg_replace( '/[\s\p{P}\p{S}]+/u', ' ', $t['d'] ) );
				}
			}
		}

		return array(
			'html_a'    => $html_a,
			'html_b'    => $html_b,
			'pct_a'     => $all_a ? (int) round( $hit_a * 100 / $all_a ) : 0,
			'pct_b'     => $all_b ? (int) round( $hit_b * 100 / $all_b ) : 0,
			'words_a'   => $all_a,
			'words_b'   => $all_b,
			'trunc_a'   => $ta['truncated'],
			'trunc_b'   => $tb['truncated'],
			'runs'      => array_slice( array_values( array_unique( $runs_a ) ), 0, 6 ),
			'surface'   => $surface,
			'shingle_n' => $n,
		);
	}

	/* ---------- صفحه ---------- */

	private static function terms_of( $id ) {
		$out = array();
		foreach ( array( 'category', 'post_tag' ) as $tx ) {
			if ( ! taxonomy_exists( $tx ) ) {
				continue;
			}
			$ts = get_the_terms( (int) $id, $tx );
			if ( is_array( $ts ) ) {
				foreach ( $ts as $t ) {
					$out[] = $t->name;
				}
			}
		}
		return $out;
	}

	private static function meta_rows( $id, $row ) {
		$kw = array();
		if ( is_array( $row ) && ! empty( $row['keywords'] ) ) {
			foreach ( (array) $row['keywords'] as $k ) {
				if ( ! empty( $k['raw'] ) ) {
					$kw[] = $k['raw'];
				}
			}
		}
		$post = get_post( (int) $id );
		return array(
			'نامک (slug)'            => $post ? urldecode( (string) $post->post_name ) : '—',
			'تاریخ انتشار'           => $post ? date_i18n( 'Y/m/d', (int) get_post_time( 'U', false, $post ) ) : '—',
			'تعداد کلمات'            => ( is_array( $row ) && isset( $row['word_count'] ) ) ? (string) (int) $row['word_count'] : '—',
			'لینک‌های داخلی ورودی'   => ( is_array( $row ) && isset( $row['inbound'] ) ) ? (string) (int) $row['inbound'] : '—',
			'کلمه(های) کلیدی'        => $kw ? implode( '، ', $kw ) : '—',
			'دسته‌ها و برچسب‌ها'     => self::terms_of( $id ) ? implode( '، ', self::terms_of( $id ) ) : '—',
			'canonical ثبت‌شده'      => ( is_array( $row ) && ! empty( $row['canonical_meta'] ) ) ? (string) $row['canonical_meta'] : '—',
			'noindex'                => ( is_array( $row ) && ! empty( $row['seo_noindex'] ) ) ? 'بله' : 'خیر',
		);
	}

	public static function render() {
		if ( ! current_user_can( CS_Util::view_cap() ) ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}
		$a = isset( $_GET['a'] ) ? (int) $_GET['a'] : 0; // phpcs:ignore
		$b = isset( $_GET['b'] ) ? (int) $_GET['b'] : 0; // phpcs:ignore
		// مقاله‌ی با شناسه‌ی کوچک‌تر همیشه «الف» (= {A} در متن دلایل و پیشنهادها) است
		if ( $a > $b && $b > 0 ) {
			$swap = $a;
			$a    = $b;
			$b    = $swap;
		}
		$pa = get_post( $a );
		$pb = get_post( $b );
		// محتوای مقاله‌ها فقط برای کسی نمایش داده می‌شود که حق ویرایش هر دو را دارد (مثلاً مقاله‌ی خصوصی نباید لو برود)
		if ( $pa && $pb && ( ! current_user_can( 'edit_post', $a ) || ! current_user_can( 'edit_post', $b ) ) ) {
			wp_die( 'دسترسی به یکی از این دو مقاله برای شما مجاز نیست.', 403 );
		}
		echo '<div class="wrap cs-cmp">';
		if ( ! $pa || ! $pb || $a === $b ) {
			echo '<h1>مقایسه‌ی دو مقاله</h1><div class="notice notice-error"><p>شناسه‌ی مقاله‌ها معتبر نیست.</p></div></div>';
			return;
		}

		$rows     = CS_Indexer::load_rows( array( $a, $b ) );
		$ra       = isset( $rows[ $a ] ) ? $rows[ $a ] : null;
		$rb       = isset( $rows[ $b ] ) ? $rows[ $b ] : null;
		$conf     = CS_Conflicts::find_pair( $a, $b );
		$an       = self::analyze( $a, $b );
		// {A} و {B} در متن دلایل/پیشنهادها یعنی post_a و post_b همان ردیف دیتابیس (شناسه‌ی کوچک‌تر = A)
		$titles   = array( 'A' => CS_Util::title( min( $a, $b ) ), 'B' => CS_Util::title( max( $a, $b ) ) );
		self::styles();
		?>
		<h1>مقایسه‌ی کنار هم دو مقاله</h1>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CS_Admin_Menu::SLUG ) ); ?>">← بازگشت به گزارش تعارض‌ها</a></p>
		<?php endif; ?>

		<?php if ( $conf ) :
			$score = (int) $conf['score'];
			$level = CS_Risk::level( $score );
			$color = CS_Risk::color( $level );
			$rs    = json_decode( (string) $conf['reasons'], true );
			$sg    = json_decode( (string) $conf['suggestions'], true );
			?>
			<div class="cs-cmp-box">
				<div class="cs-cmp-head">
					<span class="cs-badge" style="background:<?php echo esc_attr( $color ); ?>1a;color:<?php echo esc_attr( $color ); ?>;border:1px solid <?php echo esc_attr( $color ); ?>55;"><?php echo esc_html( CS_Risk::label( $level ) . ' · ' . $score ); ?></span>
					<strong>ریسک ثبت‌شده در آخرین اسکن</strong>
					<span class="cs-cmp-status">وضعیت: <?php echo esc_html( 'new' === $conf['status'] ? 'جدید' : ( 'resolved' === $conf['status'] ? 'حل‌شده' : 'نادیده گرفته‌شده' ) ); ?></span>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<span class="cs-cmp-actions">
							<?php
							foreach ( array( 'resolve' => 'حل‌شده', 'ignore' => 'نادیده بگیر', 'reopen' => 'بازگردانی' ) as $act => $label ) {
								if ( ( 'resolve' === $act && 'resolved' === $conf['status'] ) || ( 'ignore' === $act && 'ignored' === $conf['status'] ) || ( 'reopen' === $act && 'new' === $conf['status'] ) ) {
									continue;
								}
								$u = wp_nonce_url( add_query_arg( array( 'page' => self::SLUG, 'a' => $a, 'b' => $b, 'cs_cmp_action' => $act, 'cid' => (int) $conf['id'] ), admin_url( 'admin.php' ) ), self::NONCE );
								echo '<a class="button button-small" href="' . esc_url( $u ) . '">' . esc_html( $label ) . '</a> ';
							}
							?>
						</span>
					<?php endif; ?>
				</div>
				<?php if ( is_array( $rs ) && $rs ) : ?>
					<ul class="cs-reason-list">
						<?php foreach ( $rs as $r ) : ?>
							<li><span class="cs-pts"><?php echo esc_html( isset( $r['pts'] ) ? $r['pts'] : '' ); ?>٪</span> <?php echo CS_Conflicts::render( isset( $r['text'] ) ? $r['text'] : '', $titles ); // phpcs:ignore ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( is_array( $sg ) && $sg ) : ?>
					<div class="cs-sugg-title">پیشنهادها</div>
					<ul class="cs-sugg-list">
						<?php foreach ( $sg as $x ) : ?>
							<li><?php echo CS_Conflicts::render( $x, $titles ); // phpcs:ignore ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="notice notice-info inline"><p>برای این دو مقاله تعارضی در گزارش ثبت نشده است؛ مقایسه‌ی زیر فقط بر پایه‌ی متن فعلی دو مقاله است.</p></div>
		<?php endif; ?>

		<div class="cs-cmp-stats">
			<div class="cs-cmp-stat"><div class="n"><?php echo (int) $an['pct_a']; ?>٪</div><div class="l">از متن مقاله‌ی «الف» در «ب» هم آمده</div></div>
			<div class="cs-cmp-stat"><div class="n"><?php echo (int) $an['pct_b']; ?>٪</div><div class="l">از متن مقاله‌ی «ب» در «الف» هم آمده</div></div>
			<div class="cs-cmp-stat"><div class="n"><?php echo (int) $an['words_a']; ?></div><div class="l">واژه در «الف»</div></div>
			<div class="cs-cmp-stat"><div class="n"><?php echo (int) $an['words_b']; ?></div><div class="l">واژه در «ب»</div></div>
		</div>
		<p class="description">هایلایت زرد یعنی عبارتی <?php echo (int) $an['shingle_n']; ?>واژه‌ای (یا بلندتر) با همین ترتیب، در مقاله‌ی دیگر هم وجود دارد. شکل‌های مختلف یک واژه (مثلاً «مقاله» و «مقالات») یکی حساب می‌شوند.</p>

		<table class="widefat striped cs-cmp-table">
			<thead><tr><th style="width:170px;"></th><th>الف</th><th>ب</th></tr></thead>
			<tbody>
				<tr>
					<th>عنوان</th>
					<td><strong><?php echo esc_html( CS_Util::title( $a ) ); ?></strong><div class="row-actions-inline"><a href="<?php echo esc_url( (string) get_edit_post_link( $a ) ); ?>">ویرایش</a> | <a href="<?php echo esc_url( (string) get_permalink( $a ) ); ?>" target="_blank" rel="noopener">مشاهده</a></div></td>
					<td><strong><?php echo esc_html( CS_Util::title( $b ) ); ?></strong><div class="row-actions-inline"><a href="<?php echo esc_url( (string) get_edit_post_link( $b ) ); ?>">ویرایش</a> | <a href="<?php echo esc_url( (string) get_permalink( $b ) ); ?>" target="_blank" rel="noopener">مشاهده</a></div></td>
				</tr>
				<?php
				$ma = self::meta_rows( $a, $ra );
				$mb = self::meta_rows( $b, $rb );
				foreach ( $ma as $label => $va ) :
					?>
					<tr><th><?php echo esc_html( $label ); ?></th><td dir="auto"><?php echo esc_html( $va ); ?></td><td dir="auto"><?php echo esc_html( $mb[ $label ] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$shared_heads = array();
		if ( $ra && $rb ) {
			$na = array();
			foreach ( (array) $ra['heads'] as $h ) {
				$na[ CS_Compat::norm_text( $h ) ] = $h;
			}
			foreach ( (array) $rb['heads'] as $h ) {
				$k = CS_Compat::norm_text( $h );
				if ( '' !== $k && isset( $na[ $k ] ) ) {
					$shared_heads[] = $na[ $k ];
				}
			}
			$shared_heads = array_values( array_unique( $shared_heads ) );
		}
		$terms = array();
		if ( $ra && $rb ) {
			foreach ( array_keys( CS_Scorer::shared_terms( $ra, $rb, 14 ) ) as $t ) {
				$terms[] = isset( $an['surface'][ $t ] ) ? $an['surface'][ $t ] : $t;
			}
		}
		if ( $shared_heads || $terms || $an['runs'] ) :
			?>
			<div class="cs-cmp-cols cs-cmp-info">
				<?php if ( $terms ) : ?>
					<div class="cs-cmp-box"><div class="cs-sugg-title">واژه‌هایی که بیشترین سهم را در شباهت محتوا دارند</div>
						<div class="cs-chips"><?php foreach ( $terms as $t ) { echo '<span class="cs-chip">' . esc_html( $t ) . '</span>'; } ?></div></div>
				<?php endif; ?>
				<?php if ( $shared_heads ) : ?>
					<div class="cs-cmp-box"><div class="cs-sugg-title">سرفصل‌های (H2/H3) مشترک</div>
						<ul class="cs-sugg-list"><?php foreach ( array_slice( $shared_heads, 0, 10 ) as $h ) { echo '<li>' . esc_html( $h ) . '</li>'; } ?></ul></div>
				<?php endif; ?>
				<?php if ( $an['runs'] ) : ?>
					<div class="cs-cmp-box"><div class="cs-sugg-title">بلندترین عبارت‌های مشترک</div>
						<ul class="cs-sugg-list"><?php foreach ( $an['runs'] as $r ) { echo '<li dir="auto">' . esc_html( wp_trim_words( $r, 30, '…' ) ) . '</li>'; } ?></ul></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="cs-cmp-cols">
			<div class="cs-cmp-col">
				<h3>الف — <?php echo esc_html( CS_Util::title( $a ) ); ?></h3>
				<div class="cs-cmp-body"><?php echo $an['html_a']; // phpcs:ignore ?></div>
				<?php if ( $an['trunc_a'] ) : ?><p class="description">متن بلند بود؛ فقط ابتدای آن نمایش و بررسی شد.</p><?php endif; ?>
			</div>
			<div class="cs-cmp-col">
				<h3>ب — <?php echo esc_html( CS_Util::title( $b ) ); ?></h3>
				<div class="cs-cmp-body"><?php echo $an['html_b']; // phpcs:ignore ?></div>
				<?php if ( $an['trunc_b'] ) : ?><p class="description">متن بلند بود؛ فقط ابتدای آن نمایش و بررسی شد.</p><?php endif; ?>
			</div>
		</div>
		</div>
		<?php
	}

	private static function styles() {
		?>
		<style>
			.cs-cmp { max-width: 1400px; }
			.cs-cmp .cs-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
			.cs-cmp-box { background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:12px 16px; margin:12px 0; }
			.cs-cmp-head { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
			.cs-cmp-status { color:#646970; font-size:12px; }
			.cs-cmp-actions { margin-inline-start:auto; }
			.cs-cmp .cs-reason-list, .cs-cmp .cs-sugg-list { margin:8px 0 0; padding-inline-start:18px; font-size:13px; }
			.cs-cmp .cs-reason-list li, .cs-cmp .cs-sugg-list li { margin-bottom:6px; }
			.cs-cmp .cs-pts { display:inline-block; min-width:34px; font-size:11px; color:#8c8f94; }
			.cs-cmp .cs-sugg-title { font-size:12px; font-weight:600; color:#3c434a; }
			.cs-cmp .row-actions-inline { font-size:12px; color:#787c82; }
			.cs-cmp-stats { display:flex; flex-wrap:wrap; gap:12px; margin:14px 0 6px; }
			.cs-cmp-stat { flex:1 1 150px; background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:10px 14px; text-align:center; }
			.cs-cmp-stat .n { font-size:24px; font-weight:700; line-height:1.2; }
			.cs-cmp-stat .l { font-size:12px; color:#646970; }
			.cs-cmp-table { margin:12px 0; }
			.cs-cmp-table th { font-weight:600; }
			.cs-cmp-cols { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px; }
			.cs-cmp-info { grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); }
			.cs-cmp-info .cs-cmp-box { margin:0; }
			.cs-cmp-col h3 { margin:0 0 8px; font-size:14px; }
			.cs-cmp-body { background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:14px 16px; max-height:72vh; overflow:auto; line-height:2; font-size:14px; }
			.cs-cmp-body p { margin:0 0 12px; }
			mark.cs-hl { background:#fff1a8; color:inherit; padding:1px 0; border-radius:3px; }
			.cs-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
			.cs-chip { background:#f0f6fc; border:1px solid #c5d9ed; color:#1d4d7c; border-radius:999px; padding:1px 10px; font-size:12px; }
			@media (max-width: 900px) { .cs-cmp-cols { grid-template-columns:1fr; } }
		</style>
		<?php
	}
}
}

/* =========================================================================
 * خروجی CSV و گزارش قابل چاپ / PDF
 * ------------------------------------------------------------------------
 * PDF: گزارش به‌صورت صفحه‌ی HTML راست‌به‌چپ باز می‌شود و پنجره‌ی چاپ مرورگر
 * (Save as PDF) اجرا می‌شود. به این ترتیب حروف‌چینی فارسی توسط خود مرورگر
 * انجام می‌شود و نیازی به کتابخانه‌ی PDF (و فونت جداگانه) نیست.
 * ====================================================================== */
if ( ! class_exists( 'CS_Export', false ) ) {
final class CS_Export {

	const NONCE = 'cs_export';

	/** آدرس خروجی با همان فیلترهای فعلی صفحه‌ی گزارش */
	public static function url( $action ) {
		$args = array( 'action' => $action );
		foreach ( array( 'cs_status', 'cs_type', 's' ) as $k ) {
			if ( isset( $_REQUEST[ $k ] ) && '' !== $_REQUEST[ $k ] ) { // phpcs:ignore
				$args[ $k ] = sanitize_text_field( wp_unslash( $_REQUEST[ $k ] ) ); // phpcs:ignore
			}
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::NONCE );
	}

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی مجاز نیست.', 403 );
		}
		check_admin_referer( self::NONCE );
	}

	private static function filters() {
		$status = isset( $_GET['cs_status'] ) ? sanitize_key( wp_unslash( $_GET['cs_status'] ) ) : ''; // phpcs:ignore
		$type   = isset( $_GET['cs_type'] ) ? sanitize_key( wp_unslash( $_GET['cs_type'] ) ) : ''; // phpcs:ignore
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		return array( $status, $type, $search );
	}

	private static function fetch( $limit, $offset ) {
		global $wpdb;
		$tb                 = CS_Util::t( 'conflicts' );
		list( $st, $ty, $q ) = self::filters();
		list( $where, $params ) = CS_Conflicts::build_where( $st, $ty, $q );
		$sql      = "SELECT * FROM {$tb} WHERE {$where} ORDER BY score DESC, id ASC LIMIT %d OFFSET %d";
		$params[] = (int) $limit;
		$params[] = (int) $offset;
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore
		return is_array( $rows ) ? $rows : array();
	}

	private static function status_label( $s ) {
		$m = array( 'new' => 'جدید', 'resolved' => 'حل‌شده', 'ignored' => 'نادیده گرفته‌شده' );
		return isset( $m[ $s ] ) ? $m[ $s ] : (string) $s;
	}

	private static function type_label( $t ) {
		return 'canonical' === $t ? 'کنونیکال' : 'محتوای مشابه';
	}

	/** جلوگیری از اجرای فرمول در Excel (CSV injection) */
	private static function safe( $v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $v;
		}
		return $v;
	}

	private static function flatten( array $r ) {
		$a      = (int) $r['post_a'];
		$b      = (int) $r['post_b'];
		$titles = array( 'A' => CS_Util::title( $a ), 'B' => $b > 0 ? CS_Util::title( $b ) : '' );
		$rs     = json_decode( (string) $r['reasons'], true );
		$sg     = json_decode( (string) $r['suggestions'], true );
		$reason = array();
		foreach ( (array) $rs as $x ) {
			if ( isset( $x['text'] ) ) {
				$reason[] = '(' . ( isset( $x['pts'] ) ? $x['pts'] : 0 ) . '٪) ' . CS_Conflicts::plain( $x['text'], $titles );
			}
		}
		$sugg = array();
		foreach ( (array) $sg as $x ) {
			$sugg[] = CS_Conflicts::plain( (string) $x, $titles );
		}
		return array( $titles, $reason, $sugg );
	}

	public static function csv() {
		self::guard();
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cannibalization-report-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM تا Excel فارسی را درست باز کند
		$put = function ( array $row ) use ( $out ) {
			fputcsv( $out, array_map( array( __CLASS__, 'safe' ), $row ), ',', '"', '\\' );
		};
		$put( array( 'شناسه', 'نوع', 'ریسک (٪)', 'سطح', 'وضعیت', 'مقاله‌ی الف', 'نشانی الف', 'مقاله‌ی ب', 'نشانی ب', 'دلایل', 'پیشنهادها', 'آخرین به‌روزرسانی' ) );

		$offset = 0;
		do {
			$rows = self::fetch( 500, $offset );
			foreach ( $rows as $r ) {
				list( $titles, $reason, $sugg ) = self::flatten( $r );
				$a     = (int) $r['post_a'];
				$b     = (int) $r['post_b'];
				$score = (int) $r['score'];
				$t     = strtotime( (string) $r['updated_at'] . ' UTC' );
				$put( array(
					(int) $r['id'],
					self::type_label( $r['type'] ),
					$score,
					CS_Risk::label( CS_Risk::level( $score ) ),
					self::status_label( $r['status'] ),
					$titles['A'],
					(string) get_permalink( $a ),
					$titles['B'],
					$b > 0 ? (string) get_permalink( $b ) : '',
					implode( ' | ', $reason ),
					implode( ' | ', $sugg ),
					$t ? CS_Util::local_date( 'Y-m-d H:i', $t ) : '',
				) );
			}
			$offset += 500;
		} while ( count( $rows ) === 500 );

		fclose( $out );
		exit;
	}

	public static function report() {
		self::guard();
		$max  = max( 50, (int) apply_filters( 'cs_report_max_rows', 500 ) );
		$rows = self::fetch( $max, 0 );
		$c    = CS_Conflicts::counts();
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		list( $st, $ty, $q ) = self::filters();

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>گزارش کنیبالیزیشن محتوا — <?php echo esc_html( $site ); ?></title>
<style>
	@page { size: A4; margin: 12mm; }
	* { box-sizing: border-box; }
	body { font-family: Tahoma, "Vazirmatn", "IRANSans", Arial, sans-serif; font-size: 12px; color: #1d2327; margin: 0; padding: 16px; line-height: 1.8; }
	h1 { font-size: 20px; margin: 0 0 4px; }
	.sub { color: #646970; margin-bottom: 14px; }
	.bar { background:#f0f6fc; border:1px solid #c5d9ed; border-radius:8px; padding:10px 14px; margin-bottom:14px; }
	.bar button { font-family: inherit; font-size: 13px; padding: 4px 14px; cursor: pointer; }
	.stats { display:flex; gap:8px; margin-bottom:14px; }
	.stat { flex:1; border:1px solid #dcdcde; border-radius:8px; padding:6px; text-align:center; }
	.stat b { display:block; font-size:18px; }
	table { width:100%; border-collapse: collapse; }
	th, td { border:1px solid #dcdcde; padding:6px 8px; vertical-align: top; text-align: right; }
	th { background:#f6f7f7; }
	tr { page-break-inside: avoid; }
	.badge { display:inline-block; border-radius:999px; padding:0 8px; font-weight:700; white-space:nowrap; }
	ul { margin:4px 0 0; padding-inline-start:16px; }
	.pts { color:#8c8f94; font-size:10px; }
	.small { color:#646970; font-size:11px; }
	@media print { .bar { display:none; } body { padding:0; } }
</style>
</head>
<body>
	<div class="bar">
		<button type="button" onclick="window.print()">چاپ / ذخیره به‌عنوان PDF</button>
		<span class="small">در پنجره‌ی چاپ، مقصد را «Save as PDF» بگذارید.</span>
	</div>
	<h1>گزارش کنیبالیزیشن محتوا</h1>
	<div class="sub"><?php echo esc_html( $site ); ?> · تاریخ گزارش: <?php echo esc_html( date_i18n( 'Y/m/d H:i' ) ); ?>
		<?php if ( $st || $ty || $q ) : ?>· فیلتر: <?php echo esc_html( trim( ( $st && 'all' !== $st ? 'وضعیت=' . self::status_label( $st ) . ' ' : '' ) . ( $ty && 'all' !== $ty ? 'نوع=' . self::type_label( $ty ) . ' ' : '' ) . ( $q ? 'جست‌وجو=' . $q : '' ) ) ); ?><?php endif; ?>
	</div>
	<div class="stats">
		<div class="stat"><b style="color:#c62828"><?php echo (int) $c['critical']; ?></b>بحرانی</div>
		<div class="stat"><b style="color:#e65100"><?php echo (int) $c['high']; ?></b>زیاد</div>
		<div class="stat"><b style="color:#b7791f"><?php echo (int) $c['medium']; ?></b>متوسط</div>
		<div class="stat"><b style="color:#2e7d32"><?php echo (int) $c['low']; ?></b>کم</div>
		<div class="stat"><b><?php echo (int) $c['total']; ?></b>در انتظار بررسی</div>
		<div class="stat"><b style="color:#2e7d32"><?php echo (int) $c['resolved']; ?></b>حل‌شده</div>
	</div>
	<table>
		<thead><tr><th style="width:34px;">#</th><th style="width:70px;">ریسک</th><th style="width:24%;">مقاله(ها)</th><th>دلایل</th><th style="width:26%;">پیشنهادها</th><th style="width:60px;">وضعیت</th></tr></thead>
		<tbody>
		<?php
		$i = 0;
		foreach ( $rows as $r ) :
			$i++;
			list( $titles, $reason, $sugg ) = self::flatten( $r );
			$score = (int) $r['score'];
			$level = CS_Risk::level( $score );
			$color = CS_Risk::color( $level );
			?>
			<tr>
				<td><?php echo (int) $i; ?></td>
				<td><span class="badge" style="color:<?php echo esc_attr( $color ); ?>;background:<?php echo esc_attr( $color ); ?>1a;"><?php echo esc_html( CS_Risk::label( $level ) . ' ' . $score ); ?></span><div class="small"><?php echo esc_html( self::type_label( $r['type'] ) ); ?></div></td>
				<td><strong><?php echo esc_html( $titles['A'] ); ?></strong><?php if ( '' !== $titles['B'] ) : ?><div class="small">در برابر</div><strong><?php echo esc_html( $titles['B'] ); ?></strong><?php endif; ?></td>
				<td><ul><?php foreach ( $reason as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; } ?></ul></td>
				<td><ul><?php foreach ( $sugg as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; } ?></ul></td>
				<td><?php echo esc_html( self::status_label( $r['status'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="6" style="text-align:center;">موردی برای نمایش نیست.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php if ( count( $rows ) >= $max ) : ?>
		<p class="small">فقط <?php echo (int) $max; ?> مورد اول (بر پایه‌ی بیشترین ریسک) در این گزارش آمده است؛ برای فهرست کامل از خروجی CSV استفاده کنید.</p>
	<?php endif; ?>
	<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 500); });</script>
</body>
</html>
		<?php
		exit;
	}
}
}

/* =========================================================================
 * پیش‌نمایش تغییر وزن‌ها روی جفت‌مقاله‌های نمونه (بدون ذخیره)
 * ====================================================================== */
if ( ! class_exists( 'CS_Preview', false ) ) {
final class CS_Preview {

	public static function labels() {
		return array(
			'keyword'   => 'کلمه کلیدی کانونی',
			'title'     => 'شباهت عنوان',
			'content'   => 'شباهت محتوا (TF-IDF)',
			'headings'  => 'هم‌پوشانی سرفصل‌ها',
			'slug'      => 'شباهت اسلاگ',
			'taxonomy'  => 'دسته‌بندی/برچسب مشترک',
			'anchors'   => 'انکر تکست مشترک',
			'canonical' => 'ضعف canonical',
		);
	}

	public static function ajax() {
		check_ajax_referer( 'cs_preview', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
		}
		$raw   = ( isset( $_POST['weights'] ) && is_array( $_POST['weights'] ) ) ? wp_unslash( $_POST['weights'] ) : array(); // phpcs:ignore
		$pairs = isset( $_POST['pairs'] ) ? sanitize_text_field( wp_unslash( $_POST['pairs'] ) ) : ''; // phpcs:ignore
		wp_send_json_success( self::run( $raw, $pairs ) );
	}

	private static function parse_pairs( $text ) {
		$text = strtr( (string) $text, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		) );
		$out = array();
		if ( preg_match_all( '/(\d+)\s*-\s*(\d+)/', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$a = (int) $x[1];
				$b = (int) $x[2];
				if ( $a > 0 && $b > 0 && $a !== $b ) {
					$out[ min( $a, $b ) . '-' . max( $a, $b ) ] = array( min( $a, $b ), max( $a, $b ), true );
				}
			}
		}
		return array_slice( array_values( $out ), 0, 10 );
	}

	/** نمونه‌ی خودکار: چند مورد از بالاترین ریسک‌ها + چند مورد پراکنده در بازه‌ی متوسط/پایین */
	private static function auto_pairs( $limit = 12 ) {
		global $wpdb;
		$tb  = CS_Util::t( 'conflicts' );
		$all = $wpdb->get_results( "SELECT post_a, post_b FROM {$tb} WHERE type='duplicate' ORDER BY score DESC, id ASC LIMIT 400", ARRAY_A );
		$all = is_array( $all ) ? $all : array();
		$n   = count( $all );
		$sel = array();
		if ( $n <= $limit ) {
			$sel = $all;
		} else {
			$head = (int) ceil( $limit * 0.45 );
			$sel  = array_slice( $all, 0, $head );
			$rest = $limit - $head;
			$span = $n - $head;
			for ( $i = 0; $i < $rest; $i++ ) {
				$sel[] = $all[ $head + (int) floor( $i * $span / $rest ) ];
			}
		}
		$out = array();
		foreach ( $sel as $r ) {
			$out[] = array( (int) $r['post_a'], (int) $r['post_b'], false );
		}
		return $out;
	}

	public static function run( array $raw, $pairs_text = '' ) {
		$rows = CS_Indexer::load_rows();
		if ( ! $rows ) {
			return array( 'empty' => true, 'message' => 'هنوز ایندکسی ساخته نشده است؛ یک‌بار «اجرای اسکن الان» را بزنید تا جفت‌های نمونه در دسترس باشند.' );
		}
		$cur      = CS_Scorer::context();
		$new      = $cur;
		$new['w'] = CS_Scorer::normalize_weights( $raw );
		$labels   = self::labels();

		$pairs = array_merge( self::parse_pairs( $pairs_text ), self::auto_pairs() );
		$seen  = array();
		$items = array();
		$sum   = array( 'above_cur' => 0, 'above_new' => 0, 'delta' => 0.0, 'level_changes' => 0 );

		foreach ( $pairs as $p ) {
			$a = (int) $p[0];
			$b = (int) $p[1];
			$k = min( $a, $b ) . '-' . max( $a, $b );
			if ( isset( $seen[ $k ] ) || ! isset( $rows[ $a ], $rows[ $b ] ) ) {
				continue;
			}
			$seen[ $k ] = true;

			$rc = CS_Scorer::pair( $rows[ $a ], $rows[ $b ], $cur );
			$rn = CS_Scorer::pair( $rows[ $a ], $rows[ $b ], $new );
			$sc = $rc['core'] ? (int) round( $rc['risk'] * 100 ) : 0;
			$sn = $rn['core'] ? (int) round( $rn['risk'] * 100 ) : 0;

			$sig = array();
			foreach ( $rn['reasons'] as $r ) {
				if ( isset( $labels[ $r['code'] ] ) && (float) $r['pts'] > 0 ) {
					$sig[] = array( 'l' => $labels[ $r['code'] ], 'p' => (float) $r['pts'] );
				}
			}
			usort( $sig, function ( $x, $y ) {
				return $y['p'] <=> $x['p'];
			} );

			$items[] = array(
				'a'      => $a,
				'b'      => $b,
				'ta'     => CS_Util::title( $a ),
				'tb'     => CS_Util::title( $b ),
				'cur'    => $sc,
				'new'    => $sn,
				'custom' => ! empty( $p[2] ),
				'top'    => array_slice( $sig, 0, 3 ),
				'url'    => CS_Compare::url( $a, $b ),
			);

			if ( $sc >= $cur['min'] ) {
				$sum['above_cur']++;
			}
			if ( $sn >= $cur['min'] ) {
				$sum['above_new']++;
			}
			$sum['delta'] += ( $sn - $sc );
			if ( CS_Risk::level( $sc ) !== CS_Risk::level( $sn ) ) {
				$sum['level_changes']++;
			}
		}

		$n = count( $items );
		return array(
			'empty'   => ( 0 === $n ),
			'message' => ( 0 === $n ) ? 'جفت نمونه‌ای پیدا نشد؛ ابتدا اسکن را اجرا کنید یا شناسه‌ی دو مقاله را دستی وارد کنید.' : '',
			'items'   => $items,
			'summary' => array(
				'n'             => $n,
				'min'           => (int) $cur['min'],
				'above_cur'     => $sum['above_cur'],
				'above_new'     => $sum['above_new'],
				'avg_delta'     => $n ? round( $sum['delta'] / $n, 1 ) : 0,
				'level_changes' => $sum['level_changes'],
			),
		);
	}
}
}

/* =========================================================================
 * بخش‌های افزوده‌ی صفحه‌ی تنظیمات (۱.۸): وزن سیگنال‌ها + پیش‌نمایش، ریشه‌یابی،
 * افزونه‌های سئو، اعلان‌ها
 * ====================================================================== */
if ( ! class_exists( 'CS_Settings_Extra', false ) ) {
final class CS_Settings_Extra {

	/** مقادیر جدید فرم (بعد از بررسی nonce توسط CS_Settings_Page::maybe_save) */
	public static function from_post() {
		$out      = array();
		$defaults = CS_Scorer::default_weights();
		$in       = ( isset( $_POST['weights'] ) && is_array( $_POST['weights'] ) ) ? wp_unslash( $_POST['weights'] ) : array(); // phpcs:ignore
		$w        = array();
		foreach ( $defaults as $k => $v ) {
			$w[ $k ] = ( isset( $in[ $k ] ) && is_numeric( $in[ $k ] ) ) ? max( 0, min( 100, (int) $in[ $k ] ) ) : (int) round( $v * 100 );
		}
		$out['weights']         = $w;
		$out['stemming']        = ! empty( $_POST['stemming'] ); // phpcs:ignore
		$out['notify_on']       = ! empty( $_POST['notify_on'] ); // phpcs:ignore
		$out['notify_min']      = isset( $_POST['notify_min'] ) ? max( 1, min( 100, (int) $_POST['notify_min'] ) ) : 80; // phpcs:ignore
		$out['notify_email_on'] = ! empty( $_POST['notify_email_on'] ); // phpcs:ignore
		$out['notify_emails']   = isset( $_POST['notify_emails'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_emails'] ) ) : ''; // phpcs:ignore
		$out['notify_tg_on']    = ! empty( $_POST['notify_tg_on'] ); // phpcs:ignore
		$out['tg_chat']         = isset( $_POST['tg_chat'] ) ? sanitize_text_field( wp_unslash( $_POST['tg_chat'] ) ) : ''; // phpcs:ignore
		$out['tg_base']         = isset( $_POST['tg_base'] ) ? esc_url_raw( wp_unslash( $_POST['tg_base'] ) ) : ''; // phpcs:ignore
		$tok                    = isset( $_POST['tg_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['tg_token'] ) ) ) : ''; // phpcs:ignore
		if ( '' !== $tok ) {
			$out['tg_token'] = $tok; // خالی‌گذاشتن = بدون تغییر
		}
		if ( ! empty( $_POST['tg_token_clear'] ) ) { // phpcs:ignore
			$out['tg_token'] = '';
		}
		return $out;
	}

	public static function maybe_test_notify() {
		if ( empty( $_POST['cs_notify_test'] ) || empty( $_POST['cs_settings_nonce'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cs_settings_nonce'] ) ), 'cs_settings' ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore
			return;
		}
		$r = CS_Notify::send_test();
		add_action( 'admin_notices', function () use ( $r ) {
			if ( 0 === $r['active'] ) {
				echo '<div class="notice notice-warning"><p>هیچ کانال اعلانی (ایمیل/تلگرام) فعال نیست؛ یکی را فعال و دوباره امتحان کنید.</p></div>';
				return;
			}
			if ( $r['sent'] === $r['active'] ) {
				echo '<div class="notice notice-success"><p>پیام آزمایشی برای همه‌ی کانال‌های فعال ارسال شد.</p></div>';
				return;
			}
			$labels = array( 'email' => 'ایمیل', 'telegram' => 'تلگرام' );
			echo '<div class="notice notice-error"><p><strong>ارسال پیام آزمایشی کامل نبود:</strong></p><ul style="list-style:disc;margin-inline-start:20px;">';
			foreach ( $r['errors'] as $ch => $msg ) {
				echo '<li>' . esc_html( ( isset( $labels[ $ch ] ) ? $labels[ $ch ] : $ch ) . ': ' . $msg ) . '</li>';
			}
			echo '</ul></div>';
		} );
	}

	public static function render( array $s ) {
		$defaults = CS_Scorer::default_weights();
		$cur      = CS_Scorer::normalize_weights( ( isset( $s['weights'] ) && is_array( $s['weights'] ) ) ? $s['weights'] : array() );
		$labels   = CS_Preview::labels();
		$seo      = CS_SEO::active();
		$seo_lbl  = CS_SEO::labels();
		$names    = array();
		foreach ( $seo as $k ) {
			$names[] = $seo_lbl[ $k ];
		}
		?>
		<h2 class="title">وزن سیگنال‌ها و پیش‌نمایش</h2>
		<p class="description">وزن هر سیگنال (۰ تا ۱۰۰) تعیین می‌کند آن سیگنال چقدر در امتیاز ریسک نقش دارد. با جابه‌جاکردن اسلایدرها، نتیجه روی چند جفت مقاله‌ی نمونه (از آخرین اسکن) بلافاصله محاسبه می‌شود؛ چیزی ذخیره نمی‌شود تا «ذخیره‌ی تنظیمات» را بزنید. امتیازهای ثبت‌شده در گزارش با اسکن بعدی به‌روز می‌شوند.</p>
		<table class="form-table">
			<?php foreach ( $defaults as $k => $v ) : ?>
				<tr>
					<th><?php echo esc_html( $labels[ $k ] ); ?></th>
					<td>
						<input type="range" min="0" max="100" step="5" name="weights[<?php echo esc_attr( $k ); ?>]" value="<?php echo (int) round( $cur[ $k ] * 100 ); ?>" data-cs-w data-key="<?php echo esc_attr( $k ); ?>" data-def="<?php echo (int) round( $v * 100 ); ?>" style="vertical-align:middle;width:260px;" />
						<input type="number" min="0" max="100" value="<?php echo (int) round( $cur[ $k ] * 100 ); ?>" data-cs-n data-key="<?php echo esc_attr( $k ); ?>" class="small-text" />
						<span class="description">پیش‌فرض: <?php echo (int) round( $v * 100 ); ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th>جفت‌های دلخواه</th>
				<td>
					<input type="text" id="cs-prev-pairs" class="regular-text" dir="ltr" placeholder="12-45, 30-31" />
					<button type="button" class="button" id="cs-prev-reset">بازنشانی وزن‌ها به پیش‌فرض</button>
					<p class="description">اختیاری: شناسه‌ی دو مقاله به‌شکل <code>12-45</code> (چند جفت با ویرگول). این جفت‌ها بالای نمونه‌های خودکار می‌آیند.</p>
				</td>
			</tr>
		</table>
		<div id="cs-prev" style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:12px 16px;max-width:1000px;margin-bottom:16px;">
			<div id="cs-prev-sum" style="font-size:13px;margin-bottom:8px;color:#3c434a;">در حال محاسبه…</div>
			<div style="overflow-x:auto;"><table class="widefat striped" id="cs-prev-tbl" style="display:none;">
				<thead><tr><th>جفت مقاله</th><th style="width:70px;">فعلی</th><th style="width:70px;">جدید</th><th style="width:70px;">تغییر</th><th>مهم‌ترین سیگنال‌ها (با وزن‌های جدید)</th></tr></thead>
				<tbody></tbody>
			</table></div>
		</div>

		<h2 class="title">ریشه‌یابی و افزونه‌های سئو</h2>
		<table class="form-table">
			<tr>
				<th>ریشه‌یابی واژه‌های فارسی</th>
				<td>
					<label><input type="checkbox" name="stemming" <?php checked( ! isset( $s['stemming'] ) || ! empty( $s['stemming'] ) ); ?> /> شکل‌های مختلف یک واژه یکی حساب شوند («نوشتن»، «نوشته»، «می‌نویسد»؛ «مقاله»، «مقالات»)</label>
					<p class="description">روی شباهت محتوا، عنوان، سرفصل‌ها و کلمه‌های کلیدی اثر دارد. تغییر این گزینه از اسکن بعدی اعمال می‌شود (و در پیش‌نمایش بالا دیده نمی‌شود).</p>
				</td>
			</tr>
			<tr>
				<th>افزونه‌های سئوی شناسایی‌شده</th>
				<td>
					<?php if ( $names ) : ?>
						<?php echo esc_html( implode( '، ', $names ) ); ?>
					<?php else : ?>
						<em>هیچ‌کدام از Yoast / Rank Math / All in One SEO / SEOPress فعال شناسایی نشد؛ داده‌ی سئو از همه‌ی آن‌ها خوانده می‌شود.</em>
					<?php endif; ?>
					<p class="description">کلمه‌ی کلیدی، canonical و noindex فقط از افزونه‌ی(های) فعال خوانده می‌شود.</p>
				</td>
			</tr>
		</table>

		<h2 class="title">اعلان موارد بحرانی</h2>
		<table class="form-table">
			<tr>
				<th>فعال‌سازی</th>
				<td><label><input type="checkbox" name="notify_on" <?php checked( ! empty( $s['notify_on'] ) ); ?> /> بعد از هر اسکن، اگر مورد جدیدی با ریسک بالا پیدا شد خبر بده</label></td>
			</tr>
			<tr>
				<th><label for="cs_notify_min">حداقل ریسک برای اعلان</label></th>
				<td><input type="number" min="1" max="100" id="cs_notify_min" name="notify_min" value="<?php echo esc_attr( $s['notify_min'] ); ?>" class="small-text" /> ٪ <span class="description">(۸۰ = «بحرانی»)</span></td>
			</tr>
			<tr>
				<th>ایمیل</th>
				<td>
					<label><input type="checkbox" name="notify_email_on" <?php checked( ! empty( $s['notify_email_on'] ) ); ?> /> ارسال ایمیل</label><br />
					<input type="text" name="notify_emails" value="<?php echo esc_attr( $s['notify_emails'] ); ?>" class="regular-text" dir="ltr" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
					<p class="description">چند نشانی را با ویرگول جدا کنید؛ خالی = ایمیل مدیر سایت.</p>
				</td>
			</tr>
			<tr>
				<th>تلگرام</th>
				<td>
					<label><input type="checkbox" name="notify_tg_on" <?php checked( ! empty( $s['notify_tg_on'] ) ); ?> /> ارسال به تلگرام</label><br />
					<input type="password" name="tg_token" value="" autocomplete="new-password" class="regular-text" dir="ltr" placeholder="<?php echo ! empty( $s['tg_token'] ) ? '•••••• (ذخیره شده؛ برای تغییر، توکن جدید را بنویسید)' : 'توکن ربات (از @BotFather)'; ?>" /><br />
					<input type="text" name="tg_chat" value="<?php echo esc_attr( $s['tg_chat'] ); ?>" class="regular-text" dir="ltr" placeholder="Chat ID (مثلاً 123456789 یا -100…)" /><br />
					<input type="url" name="tg_base" value="<?php echo esc_attr( $s['tg_base'] ); ?>" class="regular-text" dir="ltr" placeholder="https://api.telegram.org" />
					<?php if ( ! empty( $s['tg_token'] ) ) : ?>
						<label style="display:block;margin-top:4px;"><input type="checkbox" name="tg_token_clear" value="1" /> حذف توکن ذخیره‌شده</label>
					<?php endif; ?>
					<p class="description">اگر سرور شما به api.telegram.org دسترسی ندارد (فیلتر)، آدرس یک پراکسی/رله‌ی سازگار با Bot API را در فیلد آخر بنویسید.</p>
				</td>
			</tr>
			<tr>
				<th>آزمایش</th>
				<td>
					<button type="submit" name="cs_notify_test" value="1" class="button">ذخیره و ارسال پیام آزمایشی</button>
					<?php $last = get_option( 'cs_notify_last' ); ?>
					<?php if ( is_array( $last ) && ! empty( $last['time'] ) ) : ?>
						<p class="description">آخرین اعلان خودکار: <?php echo esc_html( CS_Util::local_date( 'Y/m/d H:i', (int) $last['time'] ) ); ?> — <?php echo (int) $last['count']; ?> مورد، <?php echo (int) $last['sent'] > 0 ? 'ارسال شد' : 'ارسال ناموفق'; ?>
						<?php
						if ( ! empty( $last['errors'] ) && is_array( $last['errors'] ) ) {
							echo ' (' . esc_html( implode( ' | ', $last['errors'] ) ) . ')';
						}
						?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<script>
		(function () {
			var root = document.getElementById('cs-prev');
			if (!root) { return; }
			var ajax  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'cs_preview' ) ); ?>;
			var ranges = document.querySelectorAll('[data-cs-w]');
			var nums   = document.querySelectorAll('[data-cs-n]');
			var pairs  = document.getElementById('cs-prev-pairs');
			var sum    = document.getElementById('cs-prev-sum');
			var tbl    = document.getElementById('cs-prev-tbl');
			var tbody  = tbl.querySelector('tbody');
			var timer  = null, seq = 0;
			var colors = { critical: '#c62828', high: '#e65100', medium: '#b7791f', low: '#2e7d32' };
			function lvl(n) { return n >= 80 ? 'critical' : (n >= 60 ? 'high' : (n >= 30 ? 'medium' : 'low')); }
			function badge(n) {
				var s = document.createElement('span');
				s.textContent = n;
				s.style.cssText = 'display:inline-block;min-width:34px;text-align:center;border-radius:999px;padding:0 8px;font-weight:700;color:' + colors[lvl(n)] + ';background:' + colors[lvl(n)] + '1a;';
				return s;
			}
			function schedule() { clearTimeout(timer); timer = setTimeout(run, 600); }
			function run() {
				var my = ++seq;
				var b = new URLSearchParams();
				b.append('action', 'cs_preview_weights');
				b.append('nonce', nonce);
				ranges.forEach(function (r) { b.append('weights[' + r.getAttribute('data-key') + ']', r.value); });
				b.append('pairs', pairs.value);
				sum.textContent = 'در حال محاسبه…';
				fetch(ajax, { method: 'POST', credentials: 'same-origin', body: b })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (my !== seq) { return; }
						if (!res.success) { sum.textContent = 'خطا در پیش‌نمایش.'; return; }
						draw(res.data);
					})
					.catch(function () { if (my === seq) { sum.textContent = 'خطای شبکه در پیش‌نمایش.'; } });
			}
			function draw(d) {
				tbody.innerHTML = '';
				if (d.empty) { tbl.style.display = 'none'; sum.textContent = d.message; return; }
				var m = d.summary;
				sum.textContent = 'روی ' + m.n + ' جفت نمونه: تعداد جفت‌های بالای آستانه‌ی گزارش (' + m.min + ') از ' + m.above_cur + ' به ' + m.above_new + ' می‌رسد؛ میانگین تغییر امتیاز ' + (m.avg_delta > 0 ? '+' : '') + m.avg_delta + '؛ ' + m.level_changes + ' جفت سطح ریسکشان عوض می‌شود.';
				d.items.forEach(function (it) {
					var tr = document.createElement('tr');
					var td = document.createElement('td');
					var a = document.createElement('a');
					a.href = it.url; a.target = '_blank'; a.rel = 'noopener';
					a.textContent = it.ta + '  ⟷  ' + it.tb;
					td.appendChild(a);
					if (it.custom) { var c = document.createElement('em'); c.textContent = ' (دلخواه)'; td.appendChild(c); }
					tr.appendChild(td);
					var t1 = document.createElement('td'); t1.appendChild(badge(it.cur)); tr.appendChild(t1);
					var t2 = document.createElement('td'); t2.appendChild(badge(it.new)); tr.appendChild(t2);
					var t3 = document.createElement('td');
					var dl = it.new - it.cur;
					t3.textContent = dl === 0 ? '—' : (dl > 0 ? '▲ +' + dl : '▼ ' + dl);
					t3.style.color = dl > 0 ? '#c62828' : (dl < 0 ? '#2e7d32' : '#646970');
					tr.appendChild(t3);
					var t4 = document.createElement('td');
					t4.style.fontSize = '12px';
					t4.textContent = it.top.map(function (x) { return x.l + ' ' + x.p; }).join(' · ');
					tr.appendChild(t4);
					tbody.appendChild(tr);
				});
				tbl.style.display = '';
			}
			ranges.forEach(function (r) {
				r.addEventListener('input', function () {
					document.querySelector('[data-cs-n][data-key="' + r.getAttribute('data-key') + '"]').value = r.value;
					schedule();
				});
			});
			nums.forEach(function (n) {
				n.addEventListener('input', function () {
					var v = Math.max(0, Math.min(100, parseInt(n.value || '0', 10) || 0));
					document.querySelector('[data-cs-w][data-key="' + n.getAttribute('data-key') + '"]').value = v;
					schedule();
				});
			});
			pairs.addEventListener('input', schedule);
			document.getElementById('cs-prev-reset').addEventListener('click', function () {
				ranges.forEach(function (r) {
					r.value = r.getAttribute('data-def');
					document.querySelector('[data-cs-n][data-key="' + r.getAttribute('data-key') + '"]').value = r.getAttribute('data-def');
				});
				schedule();
			});
			run();
		})();
		</script>
		<?php
	}
}
}

/* =========================================================================
 * منوی مدیریت
 * ====================================================================== */
if ( ! class_exists( 'CS_Admin_Menu', false ) ) {
final class CS_Admin_Menu {

	const SLUG = 'cs-conflicts';

	public static function register() {
		add_menu_page(
			'کنیبالیزیشن محتوا',
			'کنیبالیزیشن',
			'manage_options',
			self::SLUG,
			array( 'CS_Admin_Page', 'render' ),
			'dashicons-analytics',
			58
		);
	}

	public static function handle_actions() {
		if ( empty( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] ) { // phpcs:ignore
			return;
		}
		CS_Admin_Page::maybe_handle_actions();
	}
}
}

/* =========================================================================
 * جدول تعارض‌ها
 * ====================================================================== */
if ( is_admin() && ! class_exists( 'CS_Conflicts_List_Table', false ) ) {
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
final class CS_Conflicts_List_Table extends WP_List_Table {

	private $counts = array();

	public function __construct() {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		parent::__construct( array( 'singular' => 'conflict', 'plural' => 'conflicts', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'risk'    => 'ریسک',
			'pair'    => 'مقاله(ها)',
			'type'    => 'نوع',
			'reasons' => 'دلایل و پیشنهادها',
			'status'  => 'وضعیت',
			'actions' => 'اقدام',
			'date'    => 'تاریخ',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'risk' => array( 'score', true ),
			'date' => array( 'updated_at', false ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_risk( $item ) {
		$score = (int) $item['score'];
		$level = CS_Risk::level( $score );
		$color = CS_Risk::color( $level );
		$label = CS_Risk::label( $level );
		return sprintf(
			'<span class="cs-badge" style="background:%1$s1a;color:%1$s;border:1px solid %1$s55;">%2$s · %3$d</span>',
			esc_attr( $color ),
			esc_html( $label ),
			$score
		);
	}

	protected function column_pair( $item ) {
		$a_id = (int) $item['post_a'];
		$b_id = (int) $item['post_b'];
		$cid  = (int) $item['id'];
		$is_dup = ( 'duplicate' === $item['type'] && $b_id > 0 );

		$a = self::post_link( $a_id, $is_dup ? $b_id : 0, $cid, 'delrdr_atob' );
		if ( $b_id > 0 ) {
			$b = self::post_link( $b_id, $is_dup ? $a_id : 0, $cid, 'delrdr_btoa' );
			$cmp = '';
			if ( $is_dup && current_user_can( CS_Util::view_cap() ) ) {
				$cmp = '<div class="row-actions-inline" style="margin-top:4px;"><a href="' . esc_url( CS_Compare::url( $a_id, $b_id ) ) . '">مقایسه‌ی کنار هم و بخش‌های مشترک</a></div>';
			}
			return '<div class="cs-pair"><div>' . $a . '</div><div class="cs-pair-vs">در برابر</div><div>' . $b . '</div>' . $cmp . '</div>';
		}
		return '<div class="cs-pair">' . $a . '</div>';
	}

	/**
	 * $other_id/$redirect_action فقط برای جفت‌های «محتوای مشابه» پر می‌شوند
	 * تا دکمه‌ی «حذف و ریدایرکت» درست کنار ویرایش/مشاهده نمایش داده شود.
	 */
	private static function post_link( $post_id, $other_id = 0, $conflict_id = 0, $redirect_action = '' ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return '<em>—</em>';
		}
		$title = get_the_title( $post_id );
		$title = ( '' !== trim( (string) $title ) ) ? $title : '(بدون عنوان) #' . $post_id;
		$edit  = get_edit_post_link( $post_id );
		$view  = get_permalink( $post_id );
		$out   = '<strong>' . esc_html( $title ) . '</strong>';
		$links = array();
		if ( $edit ) {
			$links[] = '<a href="' . esc_url( $edit ) . '">ویرایش</a>';
		}
		if ( $view ) {
			$links[] = '<a href="' . esc_url( $view ) . '" target="_blank" rel="noopener">مشاهده</a>';
		}
		if ( $other_id > 0 && $conflict_id > 0 && $redirect_action ) {
			$other_title = get_the_title( $other_id );
			$other_title = ( '' !== trim( (string) $other_title ) ) ? $other_title : ( '#' . $other_id );
			$base        = remove_query_arg( array( 'cs_action', 'id', '_wpnonce' ) );
			$url         = wp_nonce_url( add_query_arg( array( 'cs_action' => $redirect_action, 'id' => $conflict_id ), $base ), CS_Admin_Page::NONCE );
			$confirm     = sprintf( 'این مقاله حذف (%s) و به‌صورت ۳۰۱ به «%s» ریدایرکت می‌شود (با رنک‌مث). مطمئنید؟', CS_Util::settings()['permanent_delete'] ? 'به‌طور کامل و غیرقابل بازگشت' : 'انتقال به سطل زباله', $other_title );
			$links[]     = '<a href="' . esc_url( $url ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');" style="color:#b32d2e;">حذف و ریدایرکت</a>';
		}
		if ( $links ) {
			$out .= '<div class="row-actions-inline">' . implode( ' | ', $links ) . '</div>';
		}
		return $out;
	}

	protected function column_type( $item ) {
		$map = array( 'canonical' => 'کنونیکال', 'duplicate' => 'محتوای مشابه' );
		$t   = (string) $item['type'];
		return '<span class="cs-type">' . esc_html( isset( $map[ $t ] ) ? $map[ $t ] : $t ) . '</span>';
	}

	protected function column_reasons( $item ) {
		$titles = array(
			'A' => get_the_title( (int) $item['post_a'] ),
			'B' => ( (int) $item['post_b'] > 0 ) ? get_the_title( (int) $item['post_b'] ) : '',
		);
		$reasons = json_decode( (string) $item['reasons'], true );
		$sugg    = json_decode( (string) $item['suggestions'], true );
		$reasons = is_array( $reasons ) ? $reasons : array();
		$sugg    = is_array( $sugg ) ? $sugg : array();

		$html  = '<details class="cs-details"><summary>' . count( $reasons ) . ' دلیل' . ( $sugg ? ' · ' . count( $sugg ) . ' پیشنهاد' : '' ) . '</summary>';
		$html .= '<ul class="cs-reason-list">';
		foreach ( $reasons as $r ) {
			$text  = isset( $r['text'] ) ? $r['text'] : '';
			$pts   = isset( $r['pts'] ) ? $r['pts'] : '';
			$html .= '<li><span class="cs-pts">' . esc_html( $pts ) . '٪</span> ' . CS_Conflicts::render( $text, $titles ) . '</li>';
		}
		$html .= '</ul>';
		if ( $sugg ) {
			$html .= '<div class="cs-sugg-title">پیشنهادها</div><ul class="cs-sugg-list">';
			foreach ( $sugg as $sgt ) {
				$html .= '<li>' . CS_Conflicts::render( $sgt, $titles ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '</details>';
		return $html;
	}

	protected function column_status( $item ) {
		$map = array(
			'new'      => array( 'جدید', '#b7791f' ),
			'resolved' => array( 'حل‌شده', '#2e7d32' ),
			'ignored'  => array( 'نادیده گرفته‌شده', '#616161' ),
		);
		$s = isset( $map[ $item['status'] ] ) ? $map[ $item['status'] ] : array( $item['status'], '#666' );
		return '<span class="cs-status" style="color:' . esc_attr( $s[1] ) . '">' . esc_html( $s[0] ) . '</span>';
	}

	protected function column_actions( $item ) {
		$id   = (int) $item['id'];
		$base = remove_query_arg( array( 'cs_action', 'id', '_wpnonce' ) );
		$mk   = function ( $action, $label ) use ( $base, $id ) {
			$url = add_query_arg( array( 'cs_action' => $action, 'id' => $id ), $base );
			$url = wp_nonce_url( $url, CS_Admin_Page::NONCE );
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		};
		$links = array();
		if ( 'resolved' !== $item['status'] ) {
			$links[] = $mk( 'resolve', 'حل‌شده' );
		}
		if ( 'ignored' !== $item['status'] ) {
			$links[] = $mk( 'ignore', 'نادیده بگیر' );
		}
		if ( 'new' !== $item['status'] ) {
			$links[] = $mk( 'reopen', 'بازگردانی' );
		}
		$links[] = '<span style="color:#b32d2e;">' . $mk( 'delete', 'حذف' ) . '</span>';
		return implode( ' | ', $links );
	}

	protected function column_date( $item ) {
		$t = strtotime( (string) $item['updated_at'] . ' UTC' );
		return $t ? esc_html( CS_Util::local_date( 'Y/m/d H:i', $t ) ) : '—';
	}

	protected function column_default( $item, $col ) {
		return isset( $item[ $col ] ) ? esc_html( $item[ $col ] ) : '';
	}

	protected function get_bulk_actions() {
		return array(
			'resolve' => 'علامت‌گذاری به‌عنوان حل‌شده',
			'ignore'  => 'نادیده گرفتن',
			'reopen'  => 'بازگردانی به جدید',
			'delete'  => 'حذف',
		);
	}

	public function prepare_items() {
		global $wpdb;
		$tb = CS_Util::t( 'conflicts' );

		$per_page = 20;
		$paged    = max( 1, (int) ( isset( $_REQUEST['paged'] ) ? $_REQUEST['paged'] : 1 ) ); // phpcs:ignore
		$status   = isset( $_REQUEST['cs_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['cs_status'] ) ) : ''; // phpcs:ignore
		$type     = isset( $_REQUEST['cs_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['cs_type'] ) ) : ''; // phpcs:ignore
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore

		list( $where_sql, $params ) = CS_Conflicts::build_where( $status, $type, $search );

		$sort_col  = ( isset( $_REQUEST['orderby'] ) && 'date' === $_REQUEST['orderby'] ) ? 'updated_at' : 'score'; // phpcs:ignore
		$sort_dir  = ( isset( $_REQUEST['order'] ) && 'asc' === strtolower( (string) $_REQUEST['order'] ) ) ? 'ASC' : 'DESC'; // phpcs:ignore
		$offset    = ( $paged - 1 ) * $per_page;

		$sql_count = "SELECT COUNT(*) FROM {$tb} WHERE {$where_sql}";
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : (int) $wpdb->get_var( $sql_count );

		$sql      = "SELECT * FROM {$tb} WHERE {$where_sql} ORDER BY {$sort_col} {$sort_dir} LIMIT %d OFFSET %d";
		$q_params = array_merge( $params, array( $per_page, $offset ) );
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $q_params ), ARRAY_A );

		$this->items            = is_array( $rows ) ? $rows : array();
		$this->_column_headers  = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );

		$this->counts = self::load_counts();
	}

	public static function load_counts() {
		return CS_Conflicts::counts();
	}

	public function counts() {
		return $this->counts;
	}
}
}

/* =========================================================================
 * صفحه‌ی مدیریت تعارض‌ها
 * ====================================================================== */
if ( ! class_exists( 'CS_Admin_Page', false ) ) {
final class CS_Admin_Page {

	const NONCE = 'cs_admin_action';

	public static function maybe_handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['cs_action'], $_GET['id'] ) && check_admin_referer( self::NONCE ) ) { // phpcs:ignore
			$action = sanitize_key( wp_unslash( $_GET['cs_action'] ) ); // phpcs:ignore
			$id     = (int) $_GET['id']; // phpcs:ignore

			if ( in_array( $action, array( 'delrdr_atob', 'delrdr_btoa' ), true ) ) {
				self::handle_delete_redirect( $action, $id );
				wp_safe_redirect( remove_query_arg( array( 'cs_action', 'id', '_wpnonce' ) ) );
				exit;
			}

			self::apply_action( $action, array( $id ) );
			wp_safe_redirect( remove_query_arg( array( 'cs_action', 'id', '_wpnonce' ) ) );
			exit;
		}

		if ( isset( $_POST['ids'] ) && isset( $_POST['cs_bulk_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cs_bulk_nonce'] ) ), self::NONCE ) ) { // phpcs:ignore
			$action = ( isset( $_POST['action'] ) && '-1' !== $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ( isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '' ); // phpcs:ignore
			$ids    = array_map( 'intval', (array) $_POST['ids'] ); // phpcs:ignore
			if ( $action && $ids ) {
				self::apply_action( $action, $ids );
			}
			wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'ids', 'cs_bulk_nonce' ) ) );
			exit;
		}
	}

	/** $id اینجا شناسه‌ی ردیف تعارض است (نه پست)؛ از روی آن post_a/post_b را پیدا می‌کنیم */
	private static function handle_delete_redirect( $action, $conflict_id ) {
		global $wpdb;
		$tb  = CS_Util::t( 'conflicts' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tb} WHERE id=%d", (int) $conflict_id ), ARRAY_A );
		$uid = get_current_user_id();

		if ( ! $row || 'duplicate' !== $row['type'] || (int) $row['post_b'] <= 0 ) {
			set_transient( 'cs_admin_notice_' . $uid, array( false, 'این عملیات فقط برای تعارض‌های نوع «محتوای مشابه» با دو مقاله ممکن است.' ), 60 );
			return;
		}

		if ( 'delrdr_atob' === $action ) {
			$from_id = (int) $row['post_a'];
			$to_id   = (int) $row['post_b'];
		} else {
			$from_id = (int) $row['post_b'];
			$to_id   = (int) $row['post_a'];
		}

		list( $ok, $msg ) = self::delete_and_redirect( $from_id, $to_id );
		set_transient( 'cs_admin_notice_' . $uid, array( $ok, $msg ), 60 );
	}

	/**
	 * مقاله‌ی $from_id را حذف/به سطل زباله منتقل می‌کند و آدرس آن را با ۳۰۱
	 * (از طریق رنک‌مث) به $to_id ریدایرکت می‌کند. اگر ساخت ریدایرکت شکست
	 * بخورد، مقاله عمداً حذف نمی‌شود تا محتوا بدون جایگزین از دست نرود.
	 */
	private static function delete_and_redirect( $from_id, $to_id ) {
		$from_id = (int) $from_id;
		$to_id   = (int) $to_id;
		if ( $from_id <= 0 || $to_id <= 0 || $from_id === $to_id ) {
			return array( false, 'شناسه‌ی مقاله نامعتبر است.' );
		}
		if ( ! current_user_can( 'delete_post', $from_id ) ) {
			return array( false, 'اجازه‌ی حذف مقاله‌ی مبدأ را ندارید.' );
		}
		if ( 'publish' !== get_post_status( $to_id ) ) {
			return array( false, 'مقاله‌ی مقصد منتشر نشده (یا حذف شده) است؛ ریدایرکت به آن ساخته نشد و مقاله‌ی مبدأ حذف نشد.' );
		}
		$to_url = get_permalink( $to_id );
		if ( ! $to_url ) {
			return array( false, 'مقاله‌ی مقصد پیدا نشد (شاید قبلاً حذف شده).' );
		}
		$from_perma = get_permalink( $from_id );
		$from_path  = $from_perma ? wp_parse_url( $from_perma, PHP_URL_PATH ) : '';
		if ( ! $from_path ) {
			return array( false, 'مسیر مقاله‌ی مبدأ پیدا نشد.' );
		}

		if ( ! CS_RankMath_Redirect::is_available() ) {
			return array( false, 'افزونه‌ی رنک‌مث فعال نیست یا ماژول «ریدایرکت‌ها»ی آن روشن نیست؛ برای جلوگیری از حذف محتوا بدون جایگزین، مقاله حذف نشد.' );
		}
		$ok = CS_RankMath_Redirect::create( $from_path, $to_url, 301 );
		if ( ! $ok ) {
			return array( false, 'ساخت ریدایرکت در رنک‌مث ناموفق بود؛ مقاله حذف نشد.' );
		}

		$s = CS_Util::settings();
		if ( ! empty( $s['permanent_delete'] ) ) {
			wp_delete_post( $from_id, true );
			$how = 'به‌طور کامل حذف';
		} else {
			wp_trash_post( $from_id );
			$how = 'به سطل زباله منتقل';
		}

		return array( true, "مقاله {$how} شد و ریدایرکت ۳۰۱ آن به مقاله‌ی مقصد با رنک‌مث ساخته شد." );
	}

	private static function apply_action( $action, array $ids ) {
		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'resolve':
					CS_Conflicts::set_status( $id, 'resolved' );
					break;
				case 'ignore':
					CS_Conflicts::set_status( $id, 'ignored' );
					break;
				case 'reopen':
					CS_Conflicts::set_status( $id, 'new' );
					break;
				case 'delete':
					CS_Conflicts::delete( $id );
					break;
			}
		}
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table = new CS_Conflicts_List_Table();
		$table->prepare_items();
		$counts = $table->counts();

		$status = isset( $_REQUEST['cs_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['cs_status'] ) ) : 'all'; // phpcs:ignore
		$type   = isset( $_REQUEST['cs_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['cs_type'] ) ) : 'all'; // phpcs:ignore
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore

		self::styles();
		$uid    = get_current_user_id();
		$notice = get_transient( 'cs_admin_notice_' . $uid );
		if ( $notice ) {
			delete_transient( 'cs_admin_notice_' . $uid );
		}
		?>
		<div class="wrap cs-wrap">
			<h1 class="cs-title"><span class="dashicons dashicons-analytics"></span> کنیبالیزیشن محتوا</h1>
			<p class="cs-subtitle">مقاله‌هایی که در نتایج جستجو با هم رقابت می‌کنند یا مشکل canonical دارند.</p>

			<?php if ( $notice ) : ?>
				<div class="notice <?php echo $notice[0] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
					<p><?php echo esc_html( $notice[1] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="cs-stats">
				<div class="cs-stat cs-stat-critical"><div class="cs-stat-num"><?php echo (int) $counts['critical']; ?></div><div class="cs-stat-label">بحرانی</div></div>
				<div class="cs-stat cs-stat-high"><div class="cs-stat-num"><?php echo (int) $counts['high']; ?></div><div class="cs-stat-label">زیاد</div></div>
				<div class="cs-stat cs-stat-medium"><div class="cs-stat-num"><?php echo (int) $counts['medium']; ?></div><div class="cs-stat-label">متوسط</div></div>
				<div class="cs-stat cs-stat-low"><div class="cs-stat-num"><?php echo (int) $counts['low']; ?></div><div class="cs-stat-label">کم</div></div>
				<div class="cs-stat cs-stat-total"><div class="cs-stat-num"><?php echo (int) $counts['total']; ?></div><div class="cs-stat-label">در انتظار بررسی</div></div>
				<div class="cs-stat cs-stat-resolved"><div class="cs-stat-num"><?php echo (int) $counts['resolved']; ?></div><div class="cs-stat-label">حل‌شده</div></div>
			</div>

			<form method="get" class="cs-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( CS_Admin_Menu::SLUG ); ?>" />
				<select name="cs_status">
					<option value="all" <?php selected( $status, 'all' ); ?>>همه‌ی وضعیت‌ها</option>
					<option value="new" <?php selected( $status, 'new' ); ?>>جدید</option>
					<option value="resolved" <?php selected( $status, 'resolved' ); ?>>حل‌شده</option>
					<option value="ignored" <?php selected( $status, 'ignored' ); ?>>نادیده گرفته‌شده</option>
				</select>
				<select name="cs_type">
					<option value="all" <?php selected( $type, 'all' ); ?>>همه‌ی انواع</option>
					<option value="duplicate" <?php selected( $type, 'duplicate' ); ?>>محتوای مشابه</option>
					<option value="canonical" <?php selected( $type, 'canonical' ); ?>>کنونیکال</option>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جست‌وجو در عنوان مقاله…" />
				<button class="button">اعمال فیلتر</button>
				<?php if ( 'all' !== $status || 'all' !== $type || '' !== $search ) : ?>
					<a class="button-link cs-clear" href="<?php echo esc_url( remove_query_arg( array( 'cs_status', 'cs_type', 's' ) ) ); ?>">پاک‌کردن فیلترها</a>
				<?php endif; ?>
				<a class="button" style="margin-inline-start:auto;" href="<?php echo esc_url( CS_Export::url( 'cs_export_csv' ) ); ?>">خروجی CSV</a>
				<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( CS_Export::url( 'cs_print_report' ) ); ?>">گزارش چاپی / PDF</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CS_Settings_Page::SLUG ) ); ?>">تنظیمات و اجرای اسکن</a>
			</form>

			<form method="post">
				<?php wp_nonce_field( self::NONCE, 'cs_bulk_nonce' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	private static function styles() {
		?>
		<style>
			.cs-wrap { max-width: 1200px; }
			.cs-title { display:flex; align-items:center; gap:8px; }
			.cs-subtitle { color:#646970; margin-top:-4px; }
			.cs-stats { display:flex; flex-wrap:wrap; gap:12px; margin:20px 0 24px; }
			.cs-stat { flex:1 1 130px; background:#fff; border:1px solid #e2e4e7; border-radius:10px; padding:14px 16px; text-align:center; box-shadow:0 1px 2px rgba(0,0,0,.03); }
			.cs-stat-num { font-size:26px; font-weight:700; line-height:1.2; }
			.cs-stat-label { font-size:12px; color:#646970; margin-top:2px; }
			.cs-stat-critical .cs-stat-num { color:#c62828; }
			.cs-stat-high .cs-stat-num { color:#e65100; }
			.cs-stat-medium .cs-stat-num { color:#b7791f; }
			.cs-stat-low .cs-stat-num { color:#2e7d32; }
			.cs-stat-resolved .cs-stat-num { color:#2e7d32; }
			.cs-stat-total { border-color:#c3c4c7; }
			.cs-filters { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:14px; }
			.cs-filters select, .cs-filters input[type=search] { min-height:32px; }
			.cs-clear { color:#b32d2e; align-self:center; }
			.cs-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
			.cs-type { display:inline-block; background:#f0f0f1; border-radius:6px; padding:2px 8px; font-size:12px; }
			.cs-status { font-weight:600; font-size:12px; }
			.cs-pair { line-height:1.5; }
			.cs-pair-vs { font-size:11px; color:#8c8f94; margin:2px 0; }
			.row-actions-inline { font-size:12px; color:#787c82; }
			.row-actions-inline a { text-decoration:none; }
			.cs-details summary { cursor:pointer; color:#2271b1; font-size:13px; }
			.cs-reason-list, .cs-sugg-list { margin:8px 0 0; padding-inline-start:18px; font-size:13px; }
			.cs-reason-list li, .cs-sugg-list li { margin-bottom:6px; }
			.cs-pts { display:inline-block; min-width:34px; font-size:11px; color:#8c8f94; }
			.cs-sugg-title { margin-top:10px; font-size:12px; font-weight:600; color:#3c434a; }
			@media (max-width: 782px) { .cs-stats { gap:8px; } .cs-stat { flex:1 1 45%; } }
		</style>
		<?php
	}
}
}

/* =========================================================================
 * صفحه‌ی تنظیمات + اجرای اسکن دستی
 * ====================================================================== */
if ( ! class_exists( 'CS_Settings_Page', false ) ) {
final class CS_Settings_Page {

	const SLUG = 'cs-settings';

	public static function register() {
		add_submenu_page( CS_Admin_Menu::SLUG, 'تنظیمات کنیبالیزیشن', 'تنظیمات', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function maybe_save() {
		if ( empty( $_POST['cs_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cs_settings_nonce'] ) ), 'cs_settings' ) ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$post_types  = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) $_POST['post_types'] ) : array(); // phpcs:ignore
		$min_score   = isset( $_POST['min_score'] ) ? max( 0, min( 100, (int) $_POST['min_score'] ) ) : 30; // phpcs:ignore
		$live_check  = ! empty( $_POST['live_check'] ); // phpcs:ignore
		$sitemap_url = isset( $_POST['sitemap_url'] ) ? esc_url_raw( wp_unslash( $_POST['sitemap_url'] ) ) : ''; // phpcs:ignore
		$max_posts   = isset( $_POST['max_posts'] ) ? max( 50, (int) $_POST['max_posts'] ) : 3000; // phpcs:ignore
		$batch_size  = isset( $_POST['batch_size'] ) ? max( 10, min( 500, (int) $_POST['batch_size'] ) ) : 50; // phpcs:ignore
		$perm_delete = ! empty( $_POST['permanent_delete'] ); // phpcs:ignore

		CS_Util::update_settings( CS_Settings_Extra::from_post() + array(
			'post_types'       => $post_types ? $post_types : array( 'post' ),
			'min_score'        => $min_score,
			'live_check'       => $live_check,
			'sitemap_url'      => $sitemap_url,
			'max_posts'        => $max_posts,
			'batch_size'       => $batch_size,
			'permanent_delete' => $perm_delete,
		) );

		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div>';
		} );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s         = CS_Util::settings( true );
		$all_types = get_post_types( array( 'public' => true ), 'objects' );
		$state     = CS_Scan_Async::current_state();
		$in_progress = ! empty( $state['phase'] ) && ! in_array( $state['phase'], array( 'idle', 'done' ), true );
		?>
		<div class="wrap">
			<h1>تنظیمات کنیبالیزیشن محتوا</h1>

			<?php $lerr = get_option( 'cs_scan_last_error' ); ?>
			<?php if ( is_array( $lerr ) && ! empty( $lerr['message'] ) ) : ?>
				<div class="notice notice-error"><p>آخرین اسکن به‌دلیل خطا متوقف شد<?php echo ! empty( $lerr['time'] ) ? ' (' . esc_html( CS_Util::local_date( 'Y/m/d H:i', (int) $lerr['time'] ) ) . ')' : ''; ?>: <?php echo esc_html( (string) $lerr['message'] ); ?></p></div>
			<?php endif; ?>

			<div id="cs-scan-box" style="margin:16px 0;padding:16px;background:#fff;border:1px solid #e2e4e7;border-radius:8px;max-width:560px;">
				<button type="button" id="cs-scan-btn" class="button button-primary" <?php disabled( $in_progress ); ?>>اجرای اسکن الان</button>
				<button type="button" id="cs-scan-cancel" class="button" style="margin-inline-start:6px;display:<?php echo $in_progress ? 'inline-block' : 'none'; ?>;">لغو اسکن</button>
				<span id="cs-scan-status" style="margin-inline-start:10px;color:#646970;"></span>
				<div id="cs-scan-bar-wrap" style="display:<?php echo $in_progress ? 'block' : 'none'; ?>;margin-top:10px;background:#f0f0f1;border-radius:6px;overflow:hidden;height:10px;">
					<div id="cs-scan-bar" style="height:100%;width:0;background:#2271b1;transition:width .3s;"></div>
				</div>
				<?php $last = get_option( 'cs_last_scan' ); ?>
				<?php if ( $last ) : ?>
					<div style="margin-top:8px;color:#646970;font-size:12px;">آخرین اسکن کامل: <?php echo esc_html( CS_Util::local_date( 'Y/m/d H:i', (int) $last ) ); ?></div>
				<?php endif; ?>
				<p style="color:#8c8f94;font-size:12px;margin-top:10px;">
					اسکن به‌صورت مرحله‌ای (چند مقاله در هر مرحله) اجرا می‌شود تا روی سایت‌های بزرگ به خطای «Request Timeout» نخورید.
					می‌توانید این صفحه را باز بگذارید تا پیشرفت را ببینید، یا حتی ببندید — یک اسکن خودکار روزانه هم در پس‌زمینه
					(بدون نیاز به باز بودن این صفحه) به همین صورت مرحله‌ای اجرا می‌شود.
				</p>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'cs_settings', 'cs_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th>نوع محتوا</th>
						<td>
							<?php
							foreach ( $all_types as $pt ) :
								if ( 'attachment' === $pt->name ) {
									continue;
								}
								?>
								<label style="display:inline-block;margin-inline-end:14px;">
									<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $s['post_types'], true ) ); ?> />
									<?php echo esc_html( $pt->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><label for="cs_min_score">حداقل امتیاز ریسک برای گزارش</label></th>
						<td><input type="number" min="0" max="100" id="cs_min_score" name="min_score" value="<?php echo esc_attr( $s['min_score'] ); ?>" class="small-text" /> ٪</td>
					</tr>
					<tr>
						<th>بررسی زنده‌ی HTTP</th>
						<td><label><input type="checkbox" name="live_check" <?php checked( ! empty( $s['live_check'] ) ); ?> /> در زمان ایندکس، وضعیت HTTP و canonical واقعی هر صفحه هم بررسی شود (کندتر است)</label></td>
					</tr>
					<tr>
						<th><label for="cs_sitemap">آدرس Sitemap</label></th>
						<td><input type="url" id="cs_sitemap" name="sitemap_url" value="<?php echo esc_attr( $s['sitemap_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/wp-sitemap.xml' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="cs_max_posts">سقف تعداد مقاله در هر اسکن</label></th>
						<td><input type="number" min="50" id="cs_max_posts" name="max_posts" value="<?php echo esc_attr( $s['max_posts'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="cs_batch_size">تعداد مقاله در هر مرحله‌ی اسکن</label></th>
						<td>
							<input type="number" min="10" max="500" id="cs_batch_size" name="batch_size" value="<?php echo esc_attr( $s['batch_size'] ); ?>" class="small-text" />
							<p class="description">اگر باز هم Timeout می‌بینید، این عدد را کمتر کنید (مثلاً ۲۰)؛ روی هاست قوی می‌توانید بیشترش کنید تا اسکن سریع‌تر تمام شود.</p>
						</td>
					</tr>
					<tr>
						<th>نحوه‌ی حذف در «حذف و ریدایرکت»</th>
						<td>
							<label><input type="checkbox" name="permanent_delete" <?php checked( ! empty( $s['permanent_delete'] ) ); ?> /> حذف کامل و غیرقابل بازگشت (به‌جای انتقال به سطل زباله)</label>
							<p class="description">پیش‌فرض: انتقال به سطل زباله (قابل بازگردانی). ریدایرکت ۳۰۱ در رنک‌مث در هر دو حالت ساخته می‌شود.</p>
						</td>
					</tr>
				</table>
				<?php CS_Settings_Extra::render( $s ); ?>
				<?php submit_button( 'ذخیره‌ی تنظیمات' ); ?>
			</form>
		</div>
		<script>
		(function(){
			var btn      = document.getElementById('cs-scan-btn');
			var cancel   = document.getElementById('cs-scan-cancel');
			var status   = document.getElementById('cs-scan-status');
			var barWrap  = document.getElementById('cs-scan-bar-wrap');
			var bar      = document.getElementById('cs-scan-bar');
			var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'cs_scan_ajax' ) ); ?>;
			var resuming = <?php echo $in_progress ? 'true' : 'false'; ?>;
			var fails    = 0;
			var stopped  = false;

			function post(action) {
				var body = new URLSearchParams();
				body.append('action', action);
				body.append('nonce', nonce);
				return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function(r){ return r.json(); });
			}

			function finish(msg) {
				btn.disabled = false;
				cancel.style.display = 'none';
				status.textContent = msg;
			}

			function step() {
				if (stopped) { return; }
				post('cs_scan_step').then(function(res){
					if (stopped) { return; }
					if (!res.success) {
						finish('خطا: ' + (res.data && res.data.message ? res.data.message : 'نامشخص'));
						return;
					}
					fails = 0;
					var d = res.data;
					if (d.failed) {
						finish(d.message);
						return;
					}
					bar.style.width = d.progress + '%';
					status.textContent = d.message + ' (' + d.progress + '٪)';
					if (d.done) {
						finish('اسکن با موفقیت تمام شد.');
						setTimeout(function(){ window.location.reload(); }, 1200);
					} else {
						setTimeout(step, d.busy ? 1500 : 400);
					}
				}).catch(function(){
					fails++;
					if (fails > 5) {
						finish('ارتباط با سرور چند بار پشت‌سرهم ناموفق بود. اسکن در همان نقطه می‌ماند؛ چند دقیقه بعد همین صفحه را باز کنید تا ادامه یابد، یا «لغو اسکن» را بزنید.');
						cancel.style.display = 'inline-block';
						return;
					}
					status.textContent = 'خطای شبکه؛ تلاش دوباره (' + fails + ')…';
					setTimeout(step, 2000 * fails);
				});
			}

			btn.addEventListener('click', function(){
				stopped = false;
				fails = 0;
				btn.disabled = true;
				cancel.style.display = 'inline-block';
				barWrap.style.display = 'block';
				bar.style.width = '0%';
				status.textContent = 'در حال شروع…';
				post('cs_scan_start').then(function(res){
					if (!res.success) {
						finish('خطا در شروع اسکن.');
						return;
					}
					step();
				}).catch(function(){
					finish('خطا در شروع اسکن (ارتباط با سرور).');
				});
			});

			cancel.addEventListener('click', function(){
				if (!window.confirm('اسکن در جریان لغو شود؟ (نتیجه‌ی اسکن قبلی در گزارش می‌ماند)')) { return; }
				stopped = true;
				post('cs_scan_cancel').then(function(){
					barWrap.style.display = 'none';
					finish('اسکن لغو شد.');
				}).catch(function(){
					finish('لغو اسکن با خطا روبه‌رو شد؛ صفحه را دوباره باز کنید.');
				});
			});

			if (resuming) {
				status.textContent = 'ادامه‌ی اسکن قبلی…';
				step();
			}
		})();
		</script>
		<?php
	}
}

}

/* =========================================================================
 * هوک‌ها
 * ====================================================================== */
if ( ! function_exists( 'cs_ajax_scan_start' ) ) {
	function cs_ajax_scan_start() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'cs_scan_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
		}
		$state = CS_Scan_Async::start();
		// اگر مدیر تب را ببندد، کرون اسکن را ادامه می‌دهد (قفل از اجرای هم‌زمان با AJAX جلوگیری می‌کند)
		if ( ! empty( $state['phase'] ) && 'done' !== $state['phase'] && ! wp_next_scheduled( 'cs_scan_async_step' ) ) {
			wp_schedule_single_event( time() + 60, 'cs_scan_async_step' );
		}
		wp_send_json_success( array( 'phase' => $state['phase'], 'progress' => 0, 'total' => $state['total'] ) );
	}
}
if ( ! function_exists( 'cs_ajax_scan_cancel' ) ) {
	function cs_ajax_scan_cancel() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'cs_scan_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
		}
		CS_Scan_Async::cancel();
		wp_send_json_success( array( 'cancelled' => true ) );
	}
}
if ( ! function_exists( 'cs_ajax_scan_step' ) ) {
	function cs_ajax_scan_step() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'cs_scan_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
		}
		wp_send_json_success( CS_Scan_Async::step() );
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'transition_post_status', array( 'CS_Conflicts', 'on_transition' ), 10, 3 );
	add_action( 'before_delete_post', array( 'CS_Conflicts', 'on_delete' ) );
	add_action( 'plugins_loaded', array( 'CS_Schema', 'maybe_upgrade' ) );
	add_action( 'admin_menu', array( 'CS_Admin_Menu', 'register' ) );
	add_action( 'admin_menu', array( 'CS_Settings_Page', 'register' ) );
	add_action( 'admin_init', array( 'CS_Admin_Menu', 'handle_actions' ) );
	add_action( 'admin_init', array( 'CS_Settings_Page', 'maybe_save' ) );
	add_action( 'admin_init', array( 'CS_Settings_Extra', 'maybe_test_notify' ) );
	add_action( 'admin_init', array( 'CS_Compare', 'maybe_handle_actions' ) );
	add_action( 'admin_menu', array( 'CS_Compare', 'register' ) );
	add_action( 'add_meta_boxes', array( 'CS_Metabox', 'register' ) );
	add_action( 'wp_dashboard_setup', array( 'CS_Widget', 'register' ) );
	add_action( 'admin_post_cs_export_csv', array( 'CS_Export', 'csv' ) );
	add_action( 'admin_post_cs_print_report', array( 'CS_Export', 'report' ) );
	add_action( 'wp_ajax_cs_preview_weights', array( 'CS_Preview', 'ajax' ) );
	// اسکن مرحله‌ای (chunked) از طریق AJAX — جایگزین اجرای مسدودکننده‌ی قبلی که Timeout می‌داد
	add_action( 'wp_ajax_cs_scan_start', 'cs_ajax_scan_start' );
	add_action( 'wp_ajax_cs_scan_step', 'cs_ajax_scan_step' );
	add_action( 'wp_ajax_cs_scan_cancel', 'cs_ajax_scan_cancel' );
}

/* =========================================================================
 * ۲.۱ — گزارش کامل سایت و اسکن بدافزار
 * ====================================================================== */
if ( function_exists( 'add_action' ) ) {
	$cs_inc = dirname( __FILE__ ) . '/includes/';
	$cs_ok = true;
	foreach ( array( 'class-cs-audit.php', 'class-cs-audit-checks.php', 'class-cs-audit-page.php', 'class-cs-audit-fix.php', 'class-cs-audit-feed.php', 'class-cs-audit-intel.php' ) as $cs_f ) {
		if ( ! is_readable( $cs_inc . $cs_f ) ) {
			$cs_ok = false;
		}
	}
	if ( $cs_ok ) {
		require_once $cs_inc . 'class-cs-audit-feed.php';
		require_once $cs_inc . 'class-cs-audit-intel.php';
		require_once $cs_inc . 'class-cs-audit.php';
		require_once $cs_inc . 'class-cs-audit-checks.php';
		require_once $cs_inc . 'class-cs-audit-fix.php';
		require_once $cs_inc . 'class-cs-audit-page.php';
		CS_Audit_Page::hooks();
	}
	unset( $cs_inc, $cs_ok, $cs_f );
}
