<?php
/**
 * Plugin Name:       فرم تماس کیو آرتیکل (QA Contact Form)
 * Plugin URI:        https://qarticleproject.ir
 * Description:       فرم تماس حرفه‌ای، سریع و کاملاً قابل‌تنظیم برای وردپرس؛ فیلدساز کشیدنی، اعتبارسنجی ایرانی (موبایل/کدملی/کدپستی)، آپلود فایل، پاسخ خودکار، اعلان تلگرام و وبهوک، ضداسپم چندلایه، خروجی CSV و طراحی زنده بدون کدنویسی.
 * Version:           2.1.0
 * Author:            Mahyar Momeni
 * Author URI: https://mahyarhub.ir
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qa-contact-form
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 *
 * ─────────────────────────────────────────────────────────────────────────
 * نسخه 2.1.0 — بازنویسی کامل هسته
 *
 * این نسخه علاوه بر رفع همه‌ی مشکلات باقی‌مانده از 1.8، ساختار تنظیمات را
 * از یک آرایه‌ی درهم به گروه‌های مستقل تقسیم می‌کند، فیلدساز و اعتبارسنجی را
 * بازنویسی می‌کند و چند قابلیت کلیدی (آپلود فایل، پاسخ خودکار، تلگرام،
 * وبهوک، خروجی CSV، پیش‌تنظیم‌های طراحی) اضافه می‌کند.
 *
 * فهرست کامل رفع مشکلات و تغییرات در فایل CHANGELOG.md کنار همین فایل است.
 * ─────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // دسترسی مستقیم مجاز نیست.
}

define( 'QA_CF_VERSION', '2.1.0' );
define( 'QA_CF_FILE', __FILE__ );
define( 'QA_CF_DIR', plugin_dir_path( __FILE__ ) );
define( 'QA_CF_URL', plugin_dir_url( __FILE__ ) );
define( 'QA_CF_TABLE', 'qa_cf_entries' );
define( 'QA_CF_UPLOAD_DIR', 'qa-cf-uploads' );

/* ══════════════════════════════════════════════════════════════════════
 * ۱) توابع پایه و کمکی
 * ══════════════════════════════════════════════════════════════════════ */

/**
 * سطح دسترسی مورد نیاز برای مدیریت پلاگین. با فیلتر قابل تغییر است تا
 * سایت‌هایی که نقش سفارشی دارند (مثلاً «مدیر فروش») بتوانند بدون
 * دست‌کاری کد به پیام‌ها دسترسی بدهند.
 */
function qa_cf_cap() {
	$cap = apply_filters( 'qa_cf_capability', 'manage_options' );
	return is_string( $cap ) && '' !== $cap ? $cap : 'manage_options';
}

/**
 * برش امن رشته؛ اگر mbstring روی هاست فعال نباشد به substr سقوط می‌کند.
 */
function qa_cf_safe_substr( $str, $length ) {
	$str    = (string) $str;
	$length = (int) $length;
	return function_exists( 'mb_substr' ) ? mb_substr( $str, 0, $length ) : substr( $str, 0, $length );
}

/**
 * طول امن رشته (سازگار با نبود mbstring).
 */
function qa_cf_safe_strlen( $str ) {
	$str = (string) $str;
	return function_exists( 'mb_strlen' ) ? mb_strlen( $str ) : strlen( $str );
}

/**
 * دامنه سایت بدون www — برای راهنمای ایمیل فرستنده.
 */
function qa_cf_site_domain() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = is_string( $host ) ? strtolower( $host ) : '';
	if ( 0 === strpos( $host, 'www.' ) ) {
		$host = substr( $host, 4 );
	}
	return $host;
}

/**
 * تبدیل ارقام فارسی/عربی به ارقام لاتین.
 *
 * بدون این تبدیل، کاربری که با کیبورد فارسی «۰۹۱۲۳۴۵۶۷۸۹» را وارد می‌کند
 * در اعتبارسنجی شماره موبایل/کد ملی رد می‌شد، در حالی که ورودی‌اش کاملاً
 * درست بوده است. این یکی از رایج‌ترین دلایل «فرم کار نمی‌کند» در سایت‌های
 * فارسی است.
 */
function qa_cf_normalize_digits( $str ) {
	$str = (string) $str;
	$map = array(
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		'٫' => '.', '،' => ',',
	);
	return strtr( $str, $map );
}

/**
 * پاک‌سازی نویسه‌های نامرئی و یکسان‌سازی «ی/ک» عربی به فارسی.
 */
function qa_cf_normalize_text( $str ) {
	$str = (string) $str;
	$str = str_replace( array( "\xE2\x80\x8F", "\xE2\x80\x8E", "\xEF\xBB\xBF" ), '', $str ); // RLM/LRM/BOM
	$str = str_replace( array( 'ي', 'ك' ), array( 'ی', 'ک' ), $str );
	return $str;
}

/**
 * فرمت‌کردن اعداد به ارقام فارسی (فقط برای نمایش در پیشخوان).
 */
function qa_cf_fa_num( $number ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return str_replace( $en, $fa, (string) $number );
}

/* ══════════════════════════════════════════════════════════════════════
 * ۲) تنظیمات پیش‌فرض (گروه‌های مستقل)
 * ══════════════════════════════════════════════════════════════════════
 * در نسخه‌های قبل همه‌ی تنظیمات داخل یک option واحد (qa_cf_form_settings)
 * بود و هر تب هنگام ذخیره، کل آرایه را بازنویسی می‌کرد؛ نتیجه این بود که
 * تنظیمات تب‌های دیگر بی‌صدا پاک می‌شد (در 1.8 فقط برای captcha_enabled
 * وصله‌ی موردی زده شده بود). حالا هر تب option مستقل خودش را دارد و همه‌ی
 * ذخیره‌ها هم به‌صورت merge انجام می‌شوند، پس این دسته مشکل از ریشه حذف شده.
 */

function qa_cf_default_form_settings() {
	return array(
		'form_title'         => '',
		'form_desc'          => '',
		'submit_text'        => 'ارسال پیام',
		'submit_loading'     => 'در حال ارسال…',
		'success_msg'        => 'پیام شما با موفقیت ثبت شد. به‌زودی با شما تماس می‌گیریم.',
		'error_msg'          => 'ارسال پیام ناموفق بود. لطفاً دوباره تلاش کنید.',
		'required_msg'       => 'لطفاً فیلدهای الزامی را کامل کنید.',
		'after_submit'       => 'message',   // message | redirect
		'redirect_url'       => '',
		'redirect_delay'     => 2,
		'hide_form_success'  => 0,
		'reset_on_success'   => 1,
		'scroll_on_success'  => 1,
		'live_validation'    => 1,
		'char_counter'       => 1,
		'save_draft'         => 0,
		'normalize_digits'   => 1,
		'consent_enabled'    => 0,
		'consent_text'       => 'با ثبت این فرم، با شرایط و قوانین سایت موافقم.',
		'consent_required'   => 1,
		'always_load_assets' => 0,
	);
}

function qa_cf_default_email_settings() {
	return array(
		'notify_enabled'    => 1,
		'notify_email'      => get_option( 'admin_email' ),
		'email_subject'     => 'پیام جدید از فرم تماس سایت',
		'email_template'    => 'modern',      // modern | simple | plain
		'attach_files'      => 1,
		'from_email'        => '',
		'from_name'         => get_bloginfo( 'name' ),
		'reply_to_mode'     => 'auto',        // auto | fixed | off
		'reply_to_fixed'    => '',
		'autoreply_enabled' => 0,
		'autoreply_subject' => 'پیام شما دریافت شد',
		'autoreply_body'    => "سلام {name} عزیز،\n\nپیام شما با موفقیت دریافت شد و همکاران ما در اولین فرصت پاسخ می‌دهند.\n\nبا احترام،\n{site}",
	);
}

function qa_cf_default_smtp_settings() {
	return array(
		'enabled'    => 0,
		'host'       => '',
		'port'       => '587',
		'encryption' => 'tls',
		'username'   => '',
		'password'   => '',
	);
}

function qa_cf_default_security_settings() {
	return array(
		'captcha_enabled'   => 0,
		'min_fill_seconds'  => 3,
		'rate_limit_max'    => 5,
		'rate_limit_window' => 600,
		'blocked_words'     => '',
		'blocked_ips'       => '',
		'store_entries'     => 1,
		'store_ip'          => 1,
		'store_ua'          => 1,
		'auto_delete_days'  => 0,
		'upload_max_mb'     => 5,
		'upload_types'      => 'jpg,jpeg,png,webp,pdf,zip,doc,docx,xls,xlsx,txt',
	);
}

function qa_cf_default_integration_settings() {
	return array(
		'webhook_enabled'  => 0,
		'webhook_url'      => '',
		'webhook_secret'   => '',
		'telegram_enabled' => 0,
		'telegram_token'   => '',
		'telegram_chat'    => '',
	);
}

function qa_cf_default_design_settings() {
	return array(
		'preset'         => 'custom',
		'primary_color'  => '#0c8a85',
		'accent_color'   => '#0ea5a0',
		'text_color'     => '#0d1117',
		'label_color'    => '#1a2332',
		'field_bg'       => '#f8fafc',
		'border_color'   => '#e2e8f0',
		'error_color'    => '#ef4444',
		'success_color'  => '#16a34a',
		'radius'         => '12',
		'border_width'   => '1.5',
		'button_shape'   => 'pill',
		'button_full'    => 1,
		'button_gradient'=> 1,
		'button_icon'    => 'send',
		'font_family'    => 'vazir',
		'load_font'      => 0,
		'font_size'      => '14.5',
		'input_size'     => 'md',
		'label_style'    => 'top',
		'columns'        => '2',
		'gap'            => '18',
		'width_mode'     => 'full',
		'max_width'      => '640',
		'shadow'         => 1,
		'card_mode'      => 0,
		'card_bg'        => '#ffffff',
		'animation'      => 'fade',
		'color_scheme'   => 'light',
		'direction'      => 'rtl',
	);
}

/**
 * پیش‌تنظیم‌های آماده طراحی (یک کلیک، بدون کدنویسی).
 */
function qa_cf_design_presets() {
	return array(
		'teal'    => array(
			'label'  => 'فیروزه‌ای (پیش‌فرض)',
			'values' => array( 'primary_color' => '#0c8a85', 'accent_color' => '#0ea5a0', 'field_bg' => '#f8fafc', 'border_color' => '#e2e8f0', 'label_color' => '#1a2332', 'text_color' => '#0d1117', 'color_scheme' => 'light', 'card_bg' => '#ffffff' ),
		),
		'royal'   => array(
			'label'  => 'آبی سلطنتی',
			'values' => array( 'primary_color' => '#1d4ed8', 'accent_color' => '#3b82f6', 'field_bg' => '#f8fafc', 'border_color' => '#dbeafe', 'label_color' => '#1e293b', 'text_color' => '#0f172a', 'color_scheme' => 'light', 'card_bg' => '#ffffff' ),
		),
		'sunset'  => array(
			'label'  => 'نارنجی گرم',
			'values' => array( 'primary_color' => '#ea580c', 'accent_color' => '#f97316', 'field_bg' => '#fffbf7', 'border_color' => '#fed7aa', 'label_color' => '#431407', 'text_color' => '#1c1917', 'color_scheme' => 'light', 'card_bg' => '#ffffff' ),
		),
		'violet'  => array(
			'label'  => 'بنفش مدرن',
			'values' => array( 'primary_color' => '#6d28d9', 'accent_color' => '#8b5cf6', 'field_bg' => '#faf9ff', 'border_color' => '#e9e2ff', 'label_color' => '#2e1065', 'text_color' => '#1e1b4b', 'color_scheme' => 'light', 'card_bg' => '#ffffff' ),
		),
		'graphite'=> array(
			'label'  => 'خاکستری مینیمال',
			'values' => array( 'primary_color' => '#111827', 'accent_color' => '#374151', 'field_bg' => '#ffffff', 'border_color' => '#d1d5db', 'label_color' => '#111827', 'text_color' => '#111827', 'color_scheme' => 'light', 'card_bg' => '#ffffff' ),
		),
		'midnight'=> array(
			'label'  => 'تیره (Dark)',
			'values' => array( 'primary_color' => '#14b8a6', 'accent_color' => '#2dd4bf', 'field_bg' => '#111827', 'border_color' => '#1f2937', 'label_color' => '#e5e7eb', 'text_color' => '#f3f4f6', 'color_scheme' => 'dark', 'card_bg' => '#0b1220' ),
		),
	);
}

/**
 * تنظیمات یک فیلد در حالت پیش‌فرض — مرجع واحد کلیدهای مجاز فیلد.
 */
function qa_cf_default_field() {
	return array(
		'key'          => '',
		'label'        => '',
		'type'         => 'text',
		'required'     => 0,
		'width'        => 'half',
		'placeholder'  => '',
		'options'      => '',
		'default'      => '',
		'help'         => '',
		'validate'     => 'none',
		'min'          => '',
		'max'          => '',
		'rows'         => 5,
		'css_class'    => '',
		'autocomplete' => '',
	);
}

/* ══════════════════════════════════════════════════════════════════════
 * ۳) خواندن تنظیمات (همیشه با پیش‌فرض ادغام می‌شوند)
 * ══════════════════════════════════════════════════════════════════════ */

function qa_cf_form_settings() {
	return wp_parse_args( (array) get_option( 'qa_cf_form_settings', array() ), qa_cf_default_form_settings() );
}
function qa_cf_email_settings() {
	return wp_parse_args( (array) get_option( 'qa_cf_email_settings', array() ), qa_cf_default_email_settings() );
}
function qa_cf_smtp_settings() {
	return wp_parse_args( (array) get_option( 'qa_cf_smtp_settings', array() ), qa_cf_default_smtp_settings() );
}
function qa_cf_security_settings() {
	return wp_parse_args( (array) get_option( 'qa_cf_security_settings', array() ), qa_cf_default_security_settings() );
}
function qa_cf_integration_settings() {
	return wp_parse_args( (array) get_option( 'qa_cf_integration_settings', array() ), qa_cf_default_integration_settings() );
}
function qa_cf_design_settings() {
	return wp_parse_args( (array) get_option( 'qa_cf_design_settings', array() ), qa_cf_default_design_settings() );
}
function qa_cf_fields() {
	$fields = get_option( 'qa_cf_fields', null );
	if ( ! is_array( $fields ) || empty( $fields ) ) {
		$fields = QA_CF_DB::default_fields();
	}
	$out = array();
	foreach ( $fields as $field ) {
		if ( is_array( $field ) && ! empty( $field['key'] ) ) {
			$out[] = wp_parse_args( $field, qa_cf_default_field() );
		}
	}
	return $out;
}

/* ══════════════════════════════════════════════════════════════════════
 * ۴) تولید CSS بر اساس تنظیمات طراحی
 * ══════════════════════════════════════════════════════════════════════ */

/**
 * تبدیل رنگ HEX به rgba (fallback مستقل از پشتیبانی مرورگر از color-mix).
 */
function qa_cf_hex_to_rgba( $hex, $alpha = 1 ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return 'rgba(0,0,0,' . (float) $alpha . ')';
	}
	return sprintf(
		'rgba(%d,%d,%d,%s)',
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
		rtrim( rtrim( number_format( (float) $alpha, 2, '.', '' ), '0' ), '.' )
	);
}

/**
 * ساخت بلوک متغیرهای CSS برای یک selector مشخص (فرانت‌اند یا پیش‌نمایش ادمین).
 */
function qa_cf_generate_design_css( $design, $selector = '.qa-cf-wrap' ) {
	$defaults = qa_cf_default_design_settings();
	$design   = wp_parse_args( is_array( $design ) ? $design : array(), $defaults );

	$color_keys = array( 'primary_color', 'accent_color', 'text_color', 'label_color', 'field_bg', 'border_color', 'error_color', 'success_color', 'card_bg' );
	foreach ( $color_keys as $ck ) {
		$safe          = sanitize_hex_color( $design[ $ck ] );
		$design[ $ck ] = $safe ? $safe : $defaults[ $ck ];
	}

	$font_stacks = array(
		'vazir'  => "'Vazirmatn','Vazir',Tahoma,sans-serif",
		'iransans' => "'IRANSans','IRANSansWeb',Tahoma,sans-serif",
		'tahoma' => "Tahoma,'Segoe UI',sans-serif",
		'system' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif",
		'theme'  => 'inherit',
	);
	$font = isset( $font_stacks[ $design['font_family'] ] ) ? $font_stacks[ $design['font_family'] ] : $font_stacks['vazir'];

	$button_radii  = array( 'pill' => '999px', 'rounded' => '14px', 'square' => '4px' );
	$button_radius = isset( $button_radii[ $design['button_shape'] ] ) ? $button_radii[ $design['button_shape'] ] : '999px';

	$sizes = array(
		'sm' => array( '10px', '13px', '13.5px' ),
		'md' => array( '13px', '16px', '14.5px' ),
		'lg' => array( '16px', '18px', '15.5px' ),
	);
	$size    = isset( $sizes[ $design['input_size'] ] ) ? $sizes[ $design['input_size'] ] : $sizes['md'];
	$font_sz = is_numeric( $design['font_size'] ) ? (float) $design['font_size'] . 'px' : $size[2];

	$shadow     = ! empty( $design['shadow'] ) ? '0 10px 26px ' . qa_cf_hex_to_rgba( $design['primary_color'], 0.28 ) : 'none';
	$width_mode = ( isset( $design['width_mode'] ) && 'fixed' === $design['width_mode'] ) ? 'fixed' : 'full';
	$max_width  = 'fixed' === $width_mode ? max( 240, absint( $design['max_width'] ) ) . 'px' : '100%';

	$cols    = in_array( (string) $design['columns'], array( '1', '2', '3' ), true ) ? (string) $design['columns'] : '2';
	$gap     = max( 0, min( 60, absint( $design['gap'] ) ) );
	$radius  = max( 0, min( 40, absint( $design['radius'] ) ) );
	$borderw = is_numeric( $design['border_width'] ) ? max( 0, min( 6, (float) $design['border_width'] ) ) : 1.5;

	$btn_bg = ! empty( $design['button_gradient'] )
		? 'linear-gradient(135deg,' . $design['primary_color'] . ',' . $design['accent_color'] . ')'
		: $design['primary_color'];

	// selector فقط می‌تواند شامل نویسه‌های مجاز CSS باشد (جلوگیری از تزریق).
	$selector = preg_replace( '/[^a-zA-Z0-9 .#_\[\]="-]/', '', (string) $selector );

	$vars = array(
		'--qa-cf-primary'    => $design['primary_color'],
		'--qa-cf-accent'     => $design['accent_color'],
		'--qa-cf-text'       => $design['text_color'],
		'--qa-cf-label'      => $design['label_color'],
		'--qa-cf-field-bg'   => $design['field_bg'],
		'--qa-cf-border'     => $design['border_color'],
		'--qa-cf-error'      => $design['error_color'],
		'--qa-cf-success'    => $design['success_color'],
		'--qa-cf-card-bg'    => $design['card_bg'],
		'--qa-cf-radius'     => $radius . 'px',
		'--qa-cf-btn-radius' => $button_radius,
		'--qa-cf-border-w'   => $borderw . 'px',
		'--qa-cf-font'       => $font,
		'--qa-cf-fs'         => $font_sz,
		'--qa-cf-pad-y'      => $size[0],
		'--qa-cf-pad-x'      => $size[1],
		'--qa-cf-gap'        => $gap . 'px',
		'--qa-cf-maxwidth'   => $max_width,
		'--qa-cf-btn-shadow' => $shadow,
		'--qa-cf-btn-bg'     => $btn_bg,
		'--qa-cf-focus-ring' => qa_cf_hex_to_rgba( $design['accent_color'], 0.16 ),
		'--qa-cf-hover-bd'   => qa_cf_hex_to_rgba( $design['accent_color'], 0.45 ),
		'--qa-cf-err-bg'     => qa_cf_hex_to_rgba( $design['error_color'], 0.07 ),
		'--qa-cf-ok-bg'      => qa_cf_hex_to_rgba( $design['success_color'], 0.08 ),
		'--qa-cf-cols'       => $cols,
	);

	$css = $selector . '{';
	foreach ( $vars as $name => $value ) {
		$css .= $name . ':' . str_replace( array( '}', '<' ), '', (string) $value ) . ';';
	}
	$css .= '}';

	// حالت تیره‌ی خودکار: فقط رنگ سطح‌ها عوض می‌شود، رنگ برند دست‌نخورده می‌ماند.
	if ( 'auto' === $design['color_scheme'] ) {
		$css .= '@media (prefers-color-scheme: dark){' . $selector . '{'
			. '--qa-cf-text:#f3f4f6;--qa-cf-label:#e5e7eb;--qa-cf-field-bg:#111827;'
			. '--qa-cf-border:#1f2937;--qa-cf-card-bg:#0b1220;color-scheme:dark;'
			. '}}';
	} elseif ( 'dark' === $design['color_scheme'] ) {
		$css .= $selector . '{color-scheme:dark;}';
	}

	return $css;
}

/**
 * CSS ثابت فرانت‌اند (کاملاً scope‌شده به .qa-cf-wrap تا با هیچ قالب/
 * صفحه‌سازی تداخل نکند).
 */
function qa_cf_form_css() {
	return <<<'CSS'
.qa-cf-wrap,.qa-cf-wrap *,.qa-cf-wrap *::before,.qa-cf-wrap *::after{box-sizing:border-box}
.qa-cf-wrap *{max-width:100%}
.qa-cf-wrap{
	direction:rtl;text-align:right;
	font-family:var(--qa-cf-font,'Vazirmatn',Tahoma,sans-serif);
	font-size:var(--qa-cf-fs,14.5px);
	width:100%;max-width:var(--qa-cf-maxwidth,100%);min-width:0;
	color:var(--qa-cf-text,#0d1117);
	overflow-wrap:break-word;word-break:break-word;
	position:relative;
	container-type:inline-size;
}
.qa-cf-wrap.qa-cf-ltr{direction:ltr;text-align:left}
.qa-cf-wrap.qa-cf-card{background:var(--qa-cf-card-bg,#fff);border:1px solid var(--qa-cf-border,#e2e8f0);border-radius:calc(var(--qa-cf-radius,12px) + 6px);padding:clamp(18px,4cqi,32px);box-shadow:0 6px 30px rgba(15,23,42,.07)}
.qa-cf-head{margin-bottom:18px}
.qa-cf-title{font-size:1.35em;font-weight:800;margin:0 0 6px;color:var(--qa-cf-label,#1a2332);line-height:1.6}
.qa-cf-desc{margin:0;color:var(--qa-cf-label,#1a2332);opacity:.75;line-height:1.9;font-size:.95em}
.qa-cf-form{display:block;width:100%;max-width:100%;min-width:0}
.qa-cf-row{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:var(--qa-cf-gap,18px);width:100%;min-width:0}
.qa-cf-field{display:flex;flex-direction:column;gap:5px;min-width:0;max-width:100%;grid-column:span 12}
.qa-cf-w-full{grid-column:span 12}
.qa-cf-w-half{grid-column:span 6}
.qa-cf-w-third{grid-column:span 4}
.qa-cf-cols-1 .qa-cf-w-half,.qa-cf-cols-1 .qa-cf-w-third{grid-column:span 12}
.qa-cf-cols-2 .qa-cf-w-third{grid-column:span 6}
@container (max-width:560px){.qa-cf-w-half,.qa-cf-w-third{grid-column:span 12}}
@media (max-width:600px){.qa-cf-w-half,.qa-cf-w-third{grid-column:span 12}}
.qa-cf-ctrl{display:flex;flex-direction:column-reverse;gap:6px;min-width:0;position:relative}
.qa-cf-field label{font-size:.93em;font-weight:700;color:var(--qa-cf-label,#1a2332);line-height:1.7}
.qa-cf-req{color:var(--qa-cf-error,#ef4444);margin-right:3px;font-weight:700}
.qa-cf-optional{font-weight:500;opacity:.55;font-size:.86em;margin-right:4px}
.qa-cf-help{font-size:.83em;opacity:.7;line-height:1.7;color:var(--qa-cf-label,#1a2332)}
.qa-cf-field input[type="text"],.qa-cf-field input[type="email"],.qa-cf-field input[type="tel"],
.qa-cf-field input[type="url"],.qa-cf-field input[type="number"],.qa-cf-field input[type="date"],
.qa-cf-field select,.qa-cf-field textarea{
	font-family:inherit;font-size:1em;line-height:1.8;color:var(--qa-cf-text,#0d1117);
	background:var(--qa-cf-field-bg,#f8fafc);
	border:var(--qa-cf-border-w,1.5px) solid var(--qa-cf-border,#e2e8f0);
	border-radius:var(--qa-cf-radius,12px);
	padding:var(--qa-cf-pad-y,13px) var(--qa-cf-pad-x,16px);
	outline:none;width:100%;max-width:100%;min-width:0;
	transition:border-color .2s,background .2s,box-shadow .2s;
	-webkit-appearance:none;appearance:none;
}
.qa-cf-field input[type="number"]{-moz-appearance:textfield}
.qa-cf-field input[type="date"]{-webkit-appearance:none}
.qa-cf-field select{
	background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%2364748b' stroke-width='1.8'%3E%3Cpath d='M5.5 8l4.5 4.5L14.5 8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
	background-repeat:no-repeat;background-position:left 14px center;background-size:15px;padding-left:38px;cursor:pointer;
}
.qa-cf-ltr .qa-cf-field select{background-position:right 14px center;padding-left:var(--qa-cf-pad-x,16px);padding-right:38px}
.qa-cf-field textarea{min-height:130px;resize:vertical;display:block}
.qa-cf-field input:hover,.qa-cf-field select:hover,.qa-cf-field textarea:hover{border-color:var(--qa-cf-hover-bd,#94a3b8)}
.qa-cf-field input:focus,.qa-cf-field select:focus,.qa-cf-field textarea:focus{
	border-color:var(--qa-cf-accent,#0ea5a0);
	box-shadow:0 0 0 4px var(--qa-cf-focus-ring,rgba(14,165,160,.16));
}
.qa-cf-field input::placeholder,.qa-cf-field textarea::placeholder{color:currentColor;opacity:.42}
.qa-cf-field.qa-cf-invalid input,.qa-cf-field.qa-cf-invalid select,.qa-cf-field.qa-cf-invalid textarea{
	border-color:var(--qa-cf-error,#ef4444);background:var(--qa-cf-err-bg,rgba(239,68,68,.07));
}
.qa-cf-field.qa-cf-valid input,.qa-cf-field.qa-cf-valid select,.qa-cf-field.qa-cf-valid textarea{border-color:var(--qa-cf-success,#16a34a)}
.qa-cf-error-msg{display:none;font-size:.85em;color:var(--qa-cf-error,#ef4444);font-weight:600;line-height:1.7}
.qa-cf-field.qa-cf-invalid .qa-cf-error-msg{display:block}
.qa-cf-counter{font-size:.8em;opacity:.6;text-align:left;font-variant-numeric:tabular-nums}
.qa-cf-ltr .qa-cf-counter{text-align:right}

/* برچسب شناور */
.qa-cf-label-floating .qa-cf-ctrl{flex-direction:column}
.qa-cf-label-floating .qa-cf-ctrl>label{
	position:absolute;top:0;right:var(--qa-cf-pad-x,16px);
	transform:translateY(calc(var(--qa-cf-pad-y,13px) + 2px));
	pointer-events:none;transition:transform .16s ease,font-size .16s ease,color .16s ease;
	background:transparent;opacity:.75;font-weight:600;
}
.qa-cf-ltr.qa-cf-label-floating .qa-cf-ctrl>label{right:auto;left:var(--qa-cf-pad-x,16px)}
.qa-cf-label-floating .qa-cf-ctrl>input:not(:placeholder-shown)+label,
.qa-cf-label-floating .qa-cf-ctrl>textarea:not(:placeholder-shown)+label,
.qa-cf-label-floating .qa-cf-ctrl>input:focus+label,
.qa-cf-label-floating .qa-cf-ctrl>textarea:focus+label,
.qa-cf-label-floating .qa-cf-ctrl>select+label{
	transform:translateY(-2px);font-size:.78em;opacity:1;color:var(--qa-cf-accent,#0ea5a0);
}
.qa-cf-label-floating .qa-cf-ctrl>input,.qa-cf-label-floating .qa-cf-ctrl>textarea,.qa-cf-label-floating .qa-cf-ctrl>select{
	padding-top:calc(var(--qa-cf-pad-y,13px) + 12px);padding-bottom:calc(var(--qa-cf-pad-y,13px) - 4px);
}
.qa-cf-label-hidden .qa-cf-ctrl>label{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* چک‌باکس و رادیو */
.qa-cf-group-label{display:block;margin-bottom:2px}
.qa-cf-choice-list{display:flex;flex-wrap:wrap;gap:10px 18px;padding:2px 0}
.qa-cf-choice-list.is-stacked{flex-direction:column;gap:10px}
.qa-cf-choice{display:inline-flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;line-height:1.8}
.qa-cf-choice input[type="checkbox"],.qa-cf-choice input[type="radio"]{
	width:19px;height:19px;flex-shrink:0;accent-color:var(--qa-cf-accent,#0ea5a0);cursor:pointer;margin:0;
}
.qa-cf-consent{grid-column:span 12;font-size:.92em}
.qa-cf-consent a{color:var(--qa-cf-accent,#0ea5a0)}

/* آپلود فایل */
.qa-cf-file{position:relative;display:block}
.qa-cf-file input[type="file"]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.qa-cf-file-box{
	display:flex;align-items:center;gap:10px;
	border:var(--qa-cf-border-w,1.5px) dashed var(--qa-cf-border,#e2e8f0);
	border-radius:var(--qa-cf-radius,12px);background:var(--qa-cf-field-bg,#f8fafc);
	padding:var(--qa-cf-pad-y,13px) var(--qa-cf-pad-x,16px);transition:border-color .2s,background .2s;
}
.qa-cf-file input[type="file"]:focus+.qa-cf-file-box{border-color:var(--qa-cf-accent,#0ea5a0);box-shadow:0 0 0 4px var(--qa-cf-focus-ring,rgba(14,165,160,.16))}
.qa-cf-file-box:hover{border-color:var(--qa-cf-hover-bd,#94a3b8)}
.qa-cf-file-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.75;font-size:.92em}
.qa-cf-file-btn{font-weight:700;color:var(--qa-cf-accent,#0ea5a0);font-size:.9em;flex-shrink:0}

/* تیتر بخش */
.qa-cf-heading{grid-column:span 12;margin:4px 0 -4px}
.qa-cf-heading h3{margin:0 0 4px;font-size:1.05em;font-weight:800;color:var(--qa-cf-label,#1a2332)}
.qa-cf-heading p{margin:0;font-size:.88em;opacity:.7;line-height:1.8}
.qa-cf-heading hr{border:0;border-top:1px solid var(--qa-cf-border,#e2e8f0);margin:12px 0 0}

/* هانی‌پات — بدون ایجاد اسکرول افقی */
.qa-cf-hp{position:absolute!important;width:1px!important;height:1px!important;margin:-1px!important;padding:0!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;white-space:nowrap!important;border:0!important;opacity:0}

/* دکمه ارسال */
.qa-cf-actions{margin-top:var(--qa-cf-gap,18px);display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.qa-cf-submit{
	display:inline-flex;align-items:center;justify-content:center;gap:9px;
	background:var(--qa-cf-btn-bg,#0c8a85);color:#fff;border:none;
	border-radius:var(--qa-cf-btn-radius,999px);
	padding:calc(var(--qa-cf-pad-y,13px) + 2px) calc(var(--qa-cf-pad-x,16px) + 12px);
	font-family:inherit;font-size:1.03em;font-weight:800;cursor:pointer;line-height:1.6;
	transition:transform .2s,box-shadow .2s,opacity .2s,filter .2s;
	box-shadow:var(--qa-cf-btn-shadow,none);
}
.qa-cf-btn-full .qa-cf-submit{width:100%}
.qa-cf-submit svg{width:18px;height:18px;flex-shrink:0}
.qa-cf-submit:hover:not(:disabled){transform:translateY(-2px);filter:brightness(1.05)}
.qa-cf-submit:active:not(:disabled){transform:translateY(0)}
.qa-cf-submit:focus-visible{outline:3px solid var(--qa-cf-accent,#0ea5a0);outline-offset:3px}
.qa-cf-submit:disabled{opacity:.72;cursor:wait;transform:none}
.qa-cf-spinner{display:none;width:16px;height:16px;border:2.5px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;flex-shrink:0;animation:qa-cf-spin .7s linear infinite}
.qa-cf-submit.is-loading .qa-cf-spinner{display:inline-block}
.qa-cf-submit.is-loading svg{display:none}
@keyframes qa-cf-spin{to{transform:rotate(360deg)}}

/* پیام نتیجه */
.qa-cf-result{display:none;align-items:flex-start;gap:10px;border-radius:var(--qa-cf-radius,12px);padding:14px 16px;margin-bottom:16px;font-size:.93em;line-height:1.9;width:100%;animation:qa-cf-fade .25s ease}
.qa-cf-result.is-visible{display:flex}
.qa-cf-result::before{flex-shrink:0;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;line-height:1;margin-top:2px;color:#fff}
.qa-cf-result.is-ok{background:var(--qa-cf-ok-bg,#f0fdf4);border:1px solid var(--qa-cf-success,#16a34a);color:var(--qa-cf-success,#16a34a)}
.qa-cf-result.is-ok::before{content:'\2713';background:var(--qa-cf-success,#16a34a)}
.qa-cf-result.is-err{background:var(--qa-cf-err-bg,#fef2f2);border:1px solid var(--qa-cf-error,#ef4444);color:var(--qa-cf-error,#ef4444)}
.qa-cf-result.is-err::before{content:'\0021';background:var(--qa-cf-error,#ef4444)}
@keyframes qa-cf-fade{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

/* انیمیشن ورود فرم (راست‌به‌چپ، متناسب با RTL) */
.qa-cf-anim-fade .qa-cf-form{animation:qa-cf-fade .5s ease both}
.qa-cf-anim-slide .qa-cf-form{animation:qa-cf-slide .5s cubic-bezier(.2,.7,.3,1) both}
@keyframes qa-cf-slide{from{opacity:0;transform:translateX(18px)}to{opacity:1;transform:translateX(0)}}
.qa-cf-ltr.qa-cf-anim-slide .qa-cf-form{animation-name:qa-cf-slide-ltr}
@keyframes qa-cf-slide-ltr{from{opacity:0;transform:translateX(-18px)}to{opacity:1;transform:translateX(0)}}

.qa-cf-wrap.is-done .qa-cf-form{display:none}
.qa-cf-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

@media (max-width:380px){
	.qa-cf-field input,.qa-cf-field select,.qa-cf-field textarea{padding:11px 14px}
	.qa-cf-submit{padding:13px 18px}
}
@media (prefers-reduced-motion:reduce){
	.qa-cf-wrap *,.qa-cf-wrap *::before,.qa-cf-wrap *::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
}
CSS;
}

/**
 * جاوااسکریپت فرانت‌اند.
 *
 * نکات مهم این نسخه:
 * - هیچ وابستگی خارجی ندارد (بدون jQuery).
 * - شنونده‌ی submit به‌صورت delegated روی document ثبت می‌شود، پس فرم‌هایی
 *   که بعد از بارگذاری صفحه به DOM اضافه می‌شوند (پاپ‌آپ الیمنتور، محتوای
 *   AJAX، اسلایدر و…) هم بدون هیچ کار اضافه‌ای کار می‌کنند. در نسخه‌های قبل
 *   اتصال فقط داخل DOMContentLoaded انجام می‌شد و این فرم‌ها «هیچ واکنشی»
 *   نشان نمی‌دادند.
 * - اگر نانس منقضی شده باشد (صفحه‌ی کش‌شده)، یک نانس تازه گرفته و ارسال
 *   را یک‌بار تکرار می‌کند؛ قبلاً کاربر فقط پیام «خطای ارتباط با سرور»
 *   می‌گرفت و فرم عملاً روی سایت‌های دارای کش از کار می‌افتاد.
 */
function qa_cf_form_js() {
	return <<<'JS'
(function () {
	'use strict';

	if (window.__qaCfBooted) { return; }
	window.__qaCfBooted = true;

	var CFG = window.QA_CF || {};
	var RULES = {
		iran_mobile:   /^09\d{9}$/,
		iran_phone:    /^0\d{9,10}$/,
		national_code: /^\d{10}$/,
		postal_code:   /^\d{10}$/,
		numeric:       /^-?\d+(\.\d+)?$/,
		english:       /^[A-Za-z0-9 .,'@_\-]+$/,
		persian:       /^[\u0600-\u06FF\s\u200c.,،؛:!؟\-()0-9]+$/,
		url:           /^https?:\/\/[^\s]+$/i,
		email:         /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/
	};

	function cssEscape(value) {
		if (window.CSS && window.CSS.escape) { return window.CSS.escape(value); }
		return String(value).replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
	}

	function digits(v) {
		return String(v)
			.replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 1776); })
			.replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 1632); });
	}

	function nationalCodeValid(code) {
		if (!/^\d{10}$/.test(code)) { return false; }
		if (/^(\d)\1{9}$/.test(code)) { return false; }
		var sum = 0;
		for (var i = 0; i < 9; i++) { sum += parseInt(code.charAt(i), 10) * (10 - i); }
		var r = sum % 11;
		var c = parseInt(code.charAt(9), 10);
		return (r < 2) ? (c === r) : (c === 11 - r);
	}

	function fieldWrap(el) {
		var n = el;
		while (n && n !== document) {
			if (n.classList && n.classList.contains('qa-cf-field')) { return n; }
			n = n.parentNode;
		}
		return null;
	}

	function setError(wrap, message) {
		if (!wrap) { return; }
		var box = wrap.querySelector('.qa-cf-error-msg');
		if (message) {
			wrap.classList.add('qa-cf-invalid');
			wrap.classList.remove('qa-cf-valid');
			if (box) { box.textContent = message; }
			var input = wrap.querySelector('input,select,textarea');
			if (input) { input.setAttribute('aria-invalid', 'true'); }
		} else {
			wrap.classList.remove('qa-cf-invalid');
			if (box) { box.textContent = ''; }
			var el = wrap.querySelector('input,select,textarea');
			if (el) { el.removeAttribute('aria-invalid'); }
		}
	}

	function validateControl(el) {
		var wrap = fieldWrap(el);
		if (!wrap) { return true; }
		var required = el.hasAttribute('required');
		var rule = wrap.getAttribute('data-validate') || 'none';
		var type = (el.getAttribute('type') || el.tagName).toLowerCase();
		var value = (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? '1' : '') : String(el.value || '').trim();

		// در گروه رادیویی، اگر یکی از گزینه‌ها انتخاب شده باشد کل گروه معتبر است.
		if (el.type === 'radio' && el.name && el.form) {
			var group = el.form.querySelectorAll('input[type="radio"][name="' + cssEscape(el.name) + '"]');
			for (var g = 0; g < group.length; g++) {
				if (group[g].checked) { value = '1'; break; }
			}
		}

		if (required && !value) {
			setError(wrap, CFG.i18n && CFG.i18n.required ? CFG.i18n.required : 'این فیلد الزامی است.');
			return false;
		}
		if (!value) { setError(wrap, ''); return true; }

		var norm = digits(value);

		if (type === 'email' && !RULES.email.test(value)) {
			setError(wrap, CFG.i18n.email || 'ایمیل معتبر نیست.');
			return false;
		}
		if (rule === 'national_code' && !nationalCodeValid(norm)) {
			setError(wrap, CFG.i18n.national_code || 'کد ملی معتبر نیست.');
			return false;
		}
		if (rule !== 'none' && rule !== 'national_code' && RULES[rule] && !RULES[rule].test(norm)) {
			setError(wrap, (CFG.i18n && CFG.i18n[rule]) || 'مقدار واردشده معتبر نیست.');
			return false;
		}

		var min = parseInt(wrap.getAttribute('data-min') || '0', 10);
		var max = parseInt(wrap.getAttribute('data-max') || '0', 10);
		if (min > 0 && value.length < min) {
			setError(wrap, (CFG.i18n.minlen || 'حداقل %d نویسه لازم است.').replace('%d', min));
			return false;
		}
		if (max > 0 && value.length > max) {
			setError(wrap, (CFG.i18n.maxlen || 'حداکثر %d نویسه مجاز است.').replace('%d', max));
			return false;
		}

		setError(wrap, '');
		wrap.classList.add('qa-cf-valid');
		return true;
	}

	function validateForm(form) {
		var ok = true;
		var first = null;
		var controls = form.querySelectorAll('input,select,textarea');
		for (var i = 0; i < controls.length; i++) {
			var el = controls[i];
			if (el.type === 'hidden' || el.name === 'qa_cf_hp' || el.disabled) { continue; }
			if (!validateControl(el)) {
				ok = false;
				if (!first) { first = el; }
			}
		}
		if (first) {
			try { first.focus({ preventScroll: false }); } catch (e) { first.focus(); }
		}
		return ok;
	}

	function showResult(wrap, ok, message) {
		if (!wrap) { return; }
		var box = wrap.querySelector('[data-qa-cf-result]');
		if (!box) { return; }
		box.className = 'qa-cf-result is-visible ' + (ok ? 'is-ok' : 'is-err');
		box.textContent = message || '';
		if (wrap.getAttribute('data-scroll') === '1') {
			try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { box.scrollIntoView(); }
		}
	}

	function draftKey(form) {
		return 'qa_cf_draft_' + (form.getAttribute('data-form-id') || '1') + '_' + location.pathname;
	}

	function saveDraft(form) {
		if (form.getAttribute('data-draft') !== '1') { return; }
		try {
			var data = {};
			form.querySelectorAll('input,textarea,select').forEach(function (el) {
				if (!el.name || el.type === 'hidden' || el.type === 'file' || el.type === 'password' || el.name === 'qa_cf_hp') { return; }
				if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) { data[el.name] = el.value; } }
				else if (el.value) { data[el.name] = el.value; }
			});
			localStorage.setItem(draftKey(form), JSON.stringify(data));
		} catch (e) {}
	}

	function restoreDraft(form) {
		if (form.getAttribute('data-draft') !== '1') { return; }
		try {
			var raw = localStorage.getItem(draftKey(form));
			if (!raw) { return; }
			var data = JSON.parse(raw);
			Object.keys(data).forEach(function (name) {
				var els = form.querySelectorAll('[name="' + cssEscape(name) + '"]');
				els.forEach(function (el) {
					if (el.type === 'checkbox' || el.type === 'radio') { if (el.value === data[name]) { el.checked = true; } }
					else if (!el.value) { el.value = data[name]; }
				});
			});
		} catch (e) {}
	}

	function clearDraft(form) {
		try { localStorage.removeItem(draftKey(form)); } catch (e) {}
	}

	function afterSuccess(form, wrap, data) {
		clearDraft(form);
		if (form.getAttribute('data-reset') === '1') {
			form.reset();
			form.querySelectorAll('.qa-cf-field').forEach(function (f) { f.classList.remove('qa-cf-valid', 'qa-cf-invalid'); });
			form.querySelectorAll('.qa-cf-file-name').forEach(function (n) { n.textContent = n.getAttribute('data-empty') || ''; });
			form.querySelectorAll('.qa-cf-counter').forEach(function (c) { c.textContent = '0 / ' + (c.getAttribute('data-max') || ''); });
		}
		if (form.getAttribute('data-hide') === '1') { wrap.classList.add('is-done'); }
		if (data && data.redirect) {
			var delay = parseInt(form.getAttribute('data-delay') || '2', 10) * 1000;
			setTimeout(function () { window.location.href = data.redirect; }, delay);
		}
	}

	function send(form, retried) {
		var wrap = form.closest('.qa-cf-wrap');
		var btn = form.querySelector('.qa-cf-submit');
		var textEl = form.querySelector('.qa-cf-submit-text');
		var original = textEl ? textEl.textContent : '';
		var loading = form.getAttribute('data-loading') || 'در حال ارسال…';

		if (form.__qaBusy) { return; }
		form.__qaBusy = true;

		if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
		if (textEl) { textEl.textContent = loading; }

		var fd = new FormData(form);
		fd.append('action', 'qa_cf_submit');
		if (CFG.nonce) { fd.set('qa_cf_nonce', CFG.nonce); }

		var xhr = new XMLHttpRequest();
		xhr.open('POST', CFG.ajax_url, true);
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

		if (xhr.upload) {
			xhr.upload.onprogress = function (e) {
				if (e.lengthComputable && textEl && e.total > 200000) {
					var pct = Math.round((e.loaded / e.total) * 100);
					textEl.textContent = loading + ' ' + pct + '٪';
				}
			};
		}

		xhr.onload = function () {
			var json = null;
			try { json = JSON.parse(xhr.responseText); } catch (e) {}

			if (!json || typeof json.success === 'undefined') {
				finish();
				showResult(wrap, false, CFG.i18n.network || 'مشکلی در ارتباط با سرور پیش آمد.');
				return;
			}

			var data = json.data || {};

			// نانس منقضی‌شده (صفحه‌ی کش‌شده): یک نانس تازه بگیر و دقیقاً یک‌بار دوباره تلاش کن.
			if (!json.success && data.code === 'bad_nonce' && !retried) {
				refreshNonce(function (ok) {
					form.__qaBusy = false;
					restore();
					if (ok) { send(form, true); }
					else { showResult(wrap, false, data.message || CFG.i18n.network); }
				});
				return;
			}

			finish();

			if (data.errors) {
				Object.keys(data.errors).forEach(function (key) {
					var f = form.querySelector('.qa-cf-field[data-key="' + cssEscape(key) + '"]');
					setError(f, data.errors[key]);
				});
				var firstBad = form.querySelector('.qa-cf-field.qa-cf-invalid input,.qa-cf-field.qa-cf-invalid select,.qa-cf-field.qa-cf-invalid textarea');
				if (firstBad) { firstBad.focus(); }
			}

			showResult(wrap, !!json.success, data.message || '');
			if (json.success) { afterSuccess(form, wrap, data); }
		};

		xhr.onerror = function () {
			finish();
			showResult(wrap, false, CFG.i18n.network || 'مشکلی در ارتباط با سرور پیش آمد.');
		};

		xhr.send(fd);

		function restore() {
			if (btn) { btn.disabled = false; btn.classList.remove('is-loading'); }
			if (textEl) { textEl.textContent = original; }
		}
		function finish() { form.__qaBusy = false; restore(); }
	}

	function refreshNonce(cb) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', CFG.ajax_url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onload = function () {
			try {
				var json = JSON.parse(xhr.responseText);
				if (json && json.success && json.data && json.data.nonce) {
					CFG.nonce = json.data.nonce;
					cb(true);
					return;
				}
			} catch (e) {}
			cb(false);
		};
		xhr.onerror = function () { cb(false); };
		xhr.send('action=qa_cf_refresh_nonce');
	}

	/* ── شنونده‌های delegated: مستقل از زمان اضافه‌شدن فرم به صفحه ── */

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.hasAttribute || !form.hasAttribute('data-qa-cf-form')) { return; }
		e.preventDefault();
		if (form.hasAttribute('data-preview')) { return; }
		if (form.getAttribute('data-validate-live') === '1' && !validateForm(form)) {
			showResult(form.closest('.qa-cf-wrap'), false, CFG.i18n.fix_errors || 'لطفاً خطاهای مشخص‌شده را برطرف کنید.');
			return;
		}
		send(form, false);
	}, false);

	document.addEventListener('blur', function (e) {
		var el = e.target;
		if (!el || !el.form || !el.form.hasAttribute || !el.form.hasAttribute('data-qa-cf-form')) { return; }
		if (el.form.getAttribute('data-validate-live') !== '1') { return; }
		if (el.type === 'hidden' || el.name === 'qa_cf_hp') { return; }
		validateControl(el);
	}, true);

	document.addEventListener('input', function (e) {
		var el = e.target;
		var form = el && el.form;
		if (!form || !form.hasAttribute || !form.hasAttribute('data-qa-cf-form')) { return; }

		var wrap = fieldWrap(el);
		if (wrap && wrap.classList.contains('qa-cf-invalid')) { validateControl(el); }

		// شمارنده نویسه
		if (wrap) {
			var counter = wrap.querySelector('.qa-cf-counter');
			if (counter) {
				var max = counter.getAttribute('data-max');
				counter.textContent = String(el.value.length) + (max ? ' / ' + max : '');
			}
		}

		// تبدیل خودکار ارقام فارسی به لاتین در فیلدهای عددی
		if (form.getAttribute('data-normalize') === '1' && wrap) {
			var rule = wrap.getAttribute('data-validate') || 'none';
			if (el.type === 'tel' || el.type === 'number' || rule === 'national_code' || rule === 'postal_code' || rule === 'iran_mobile' || rule === 'iran_phone' || rule === 'numeric') {
				var converted = digits(el.value);
				if (converted !== el.value) {
					var pos = el.selectionStart;
					el.value = converted;
					try { el.setSelectionRange(pos, pos); } catch (err) {}
				}
			}
		}

		saveDraft(form);
	}, false);

	document.addEventListener('change', function (e) {
		var el = e.target;
		var form = el && el.form;
		if (!form || !form.hasAttribute || !form.hasAttribute('data-qa-cf-form')) { return; }

		if (el.type === 'file') {
			var wrap = fieldWrap(el);
			var nameEl = wrap ? wrap.querySelector('.qa-cf-file-name') : null;
			var maxMb = parseFloat(el.getAttribute('data-max-mb') || '0');
			if (el.files && el.files.length) {
				var f = el.files[0];
				if (nameEl) { nameEl.textContent = f.name; }
				if (maxMb > 0 && f.size > maxMb * 1024 * 1024) {
					setError(wrap, (CFG.i18n.file_size || 'حجم فایل نباید بیشتر از %s مگابایت باشد.').replace('%s', maxMb));
					el.value = '';
					if (nameEl) { nameEl.textContent = nameEl.getAttribute('data-empty') || ''; }
				} else {
					setError(wrap, '');
				}
			} else if (nameEl) {
				nameEl.textContent = nameEl.getAttribute('data-empty') || '';
			}
		}
		saveDraft(form);
	}, false);

	function enhance() {
		document.querySelectorAll('[data-qa-cf-form]').forEach(function (form) {
			if (form.__qaEnhanced) { return; }
			form.__qaEnhanced = true;
			restoreDraft(form);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', enhance);
	} else {
		enhance();
	}
	// فرم‌هایی که بعداً (پاپ‌آپ/AJAX) اضافه می‌شوند هم پوشش داده شوند.
	window.addEventListener('load', enhance);
	document.addEventListener('qa_cf_refresh', enhance);
})();
JS;
}

/* ══════════════════════════════════════════════════════════════════════
 * ۵) لایه دیتابیس
 * ══════════════════════════════════════════════════════════════════════ */

class QA_CF_DB {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . QA_CF_TABLE;
	}

	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_data LONGTEXT NOT NULL,
			ip_address VARCHAR(100) DEFAULT '',
			user_agent VARCHAR(255) DEFAULT '',
			referer VARCHAR(255) DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'unread',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY status_created (status,created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * آیا جدول پیام‌ها واقعاً در دیتابیس وجود دارد؟
	 * (اگر پلاگین با کپی فایل نصب شده باشد، هوک فعال‌سازی اجرا نمی‌شود و
	 * جدول ساخته نمی‌شود؛ قبلاً نتیجه‌اش این بود که پیام‌ها بی‌صدا از بین
	 * می‌رفتند و کاربر فقط «خطای ثبت» می‌دید.)
	 */
	public static function table_exists( $force = false ) {
		global $wpdb;
		static $exists = null;
		if ( null === $exists || $force ) {
			$table  = self::table_name();
			$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$exists = ( $found === $table );
		}
		return $exists;
	}

	public static function default_fields() {
		$base = qa_cf_default_field();
		return array(
			array_merge( $base, array( 'key' => 'full_name', 'label' => 'نام و نام‌خانوادگی', 'type' => 'text', 'required' => 1, 'width' => 'half', 'autocomplete' => 'name' ) ),
			array_merge( $base, array( 'key' => 'phone', 'label' => 'شماره موبایل', 'type' => 'tel', 'required' => 1, 'width' => 'half', 'validate' => 'iran_mobile', 'placeholder' => '09xxxxxxxxx', 'autocomplete' => 'tel' ) ),
			array_merge( $base, array( 'key' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => 0, 'width' => 'half', 'autocomplete' => 'email' ) ),
			array_merge( $base, array( 'key' => 'subject', 'label' => 'موضوع درخواست', 'type' => 'text', 'required' => 0, 'width' => 'half' ) ),
			array_merge( $base, array( 'key' => 'message', 'label' => 'پیام شما', 'type' => 'textarea', 'required' => 1, 'width' => 'full', 'rows' => 5 ) ),
		);
	}

	public static function field_types() {
		return array(
			'text'           => 'متن کوتاه',
			'email'          => 'ایمیل',
			'tel'            => 'شماره تلفن',
			'number'         => 'عدد',
			'url'            => 'آدرس اینترنتی',
			'date'           => 'تاریخ',
			'textarea'       => 'متن بلند',
			'select'         => 'لیست کشویی',
			'radio'          => 'انتخاب تکی (رادیو)',
			'checkbox'       => 'چک‌باکس تکی',
			'checkbox_group' => 'چند انتخابی',
			'file'           => 'آپلود فایل',
			'heading'        => 'عنوان/جداکننده بخش',
		);
	}

	/**
	 * انواعی که گزینه (options) می‌گیرند.
	 */
	public static function types_with_options() {
		return array( 'select', 'radio', 'checkbox_group' );
	}

	public static function validate_rules() {
		return array(
			'none'          => 'بدون اعتبارسنجی اضافه',
			'iran_mobile'   => 'موبایل ایران (۰۹xxxxxxxxx)',
			'iran_phone'    => 'تلفن ثابت ایران (با کد شهر)',
			'national_code' => 'کد ملی (با بررسی رقم کنترل)',
			'postal_code'   => 'کد پستی ۱۰ رقمی',
			'numeric'       => 'فقط عدد',
			'english'       => 'فقط حروف انگلیسی',
			'persian'       => 'فقط حروف فارسی',
			'url'           => 'آدرس اینترنتی معتبر',
		);
	}

	public static function field_max_length( $type ) {
		$limits = array(
			'text'           => 300,
			'email'          => 190,
			'tel'            => 40,
			'number'         => 30,
			'url'            => 300,
			'date'           => 20,
			'textarea'       => 5000,
			'select'         => 190,
			'radio'          => 190,
			'checkbox'       => 190,
			'checkbox_group' => 600,
			'file'           => 300,
		);
		$max = isset( $limits[ $type ] ) ? $limits[ $type ] : 300;
		return (int) apply_filters( 'qa_cf_field_max_length', $max, $type );
	}

	/**
	 * ثبت یک پیام جدید.
	 *
	 * @param array $fields key => array('label'=>, 'value'=>, 'type'=>)
	 * @return int|false
	 */
	public static function insert_entry( $fields ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			self::create_table();
			self::table_exists( true );
			if ( ! self::table_exists( true ) ) {
				return false;
			}
		}

		$security = qa_cf_security_settings();

		$payload = array(
			'v'      => 3,
			'fields' => $fields,
		);

		$data = array(
			'form_data'  => wp_json_encode( $payload ),
			'ip_address' => ! empty( $security['store_ip'] ) ? self::get_client_ip() : '',
			'user_agent' => ! empty( $security['store_ua'] ) && isset( $_SERVER['HTTP_USER_AGENT'] ) ? qa_cf_safe_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 250 ) : '',
			'referer'    => isset( $_SERVER['HTTP_REFERER'] ) ? qa_cf_safe_substr( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 250 ) : '',
			'status'     => 'unread',
			'created_at' => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert( self::table_name(), $data, array( '%s', '%s', '%s', '%s', '%s', '%s' ) );

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * یکنواخت‌سازی ستون form_data برای نمایش (سازگار با هر سه فرمت v1/v2/v3).
	 */
	public static function normalize_entry_rows( $raw_json ) {
		$data = json_decode( (string) $raw_json, true );
		$rows = array();

		if ( is_array( $data ) && isset( $data['fields'] ) && is_array( $data['fields'] ) ) {
			foreach ( $data['fields'] as $key => $item ) {
				$rows[] = array(
					'key'   => (string) $key,
					'label' => ( is_array( $item ) && isset( $item['label'] ) ) ? $item['label'] : $key,
					'value' => ( is_array( $item ) && array_key_exists( 'value', $item ) ) ? $item['value'] : '',
					'type'  => ( is_array( $item ) && isset( $item['type'] ) ) ? $item['type'] : 'text',
					'url'   => ( is_array( $item ) && isset( $item['url'] ) ) ? $item['url'] : '',
					'path'  => ( is_array( $item ) && isset( $item['path'] ) ) ? $item['path'] : '',
				);
			}
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $label => $value ) {
				$rows[] = array(
					'key'   => sanitize_key( (string) $label ),
					'label' => $label,
					'value' => $value,
					'type'  => 'text',
					'url'   => '',
					'path'  => '',
				);
			}
		}

		return $rows;
	}

	/**
	 * IP کاربر. هدرهای پراکسی فقط با فیلتر صریح معتبر دانسته می‌شوند
	 * (وگرنه هر کاربری می‌تواند با جعل X-Forwarded-For محدودیت نرخ را دور بزند).
	 */
	public static function get_client_ip() {
		$trust = (bool) apply_filters( 'qa_cf_trust_proxy_headers', false );
		$keys  = $trust
			? array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' )
			: array( 'REMOTE_ADDR' );

		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$raw   = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$parts = explode( ',', $raw );
				$ip    = trim( $parts[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	public static function get_entries( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'paged'    => 1,
			'status'   => '',
			'search'   => '',
			'from'     => '',
			'to'       => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'no_limit' => false,
		);
		$args  = wp_parse_args( $args, $defaults );
		$table = self::table_name();

		if ( ! self::table_exists() ) {
			return array();
		}

		list( $where, $params ) = self::build_where( $args );

		$orderby = in_array( $args['orderby'], array( 'created_at', 'id', 'status' ), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}";

		if ( empty( $args['no_limit'] ) ) {
			$offset   = max( 0, ( max( 1, absint( $args['paged'] ) ) - 1 ) * absint( $args['per_page'] ) );
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = absint( $args['per_page'] );
			$params[] = $offset;
		}

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore
		}

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
	}

	public static function count_entries( $args = array() ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$args  = wp_parse_args( $args, array( 'status' => '', 'search' => '', 'from' => '', 'to' => '' ) );
		$table = self::table_name();

		list( $where, $params ) = self::build_where( $args );

		$sql = "SELECT COUNT(id) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
	}

	private static function build_where( $args ) {
		global $wpdb;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'read', 'unread' ), true ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND form_data LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		if ( ! empty( $args['from'] ) ) {
			$where   .= ' AND created_at >= %s';
			$params[] = $args['from'] . ' 00:00:00';
		}
		if ( ! empty( $args['to'] ) ) {
			$where   .= ' AND created_at <= %s';
			$params[] = $args['to'] . ' 23:59:59';
		}

		return array( $where, $params );
	}

	public static function get_entry( $id ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return null;
		}
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore
	}

	public static function update_status( $id, $status ) {
		global $wpdb;
		$status = in_array( $status, array( 'read', 'unread' ), true ) ? $status : 'unread';
		return $wpdb->update( self::table_name(), array( 'status' => $status ), array( 'id' => absint( $id ) ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * حذف پیام‌ها + حذف فایل‌های پیوست همان پیام‌ها (تا فایل‌های یتیم روی
	 * هاست باقی نمانند).
	 */
	public static function delete_entries( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		foreach ( $ids as $id ) {
			$entry = self::get_entry( $id );
			if ( $entry ) {
				QA_CF_Uploads::delete_entry_files( self::normalize_entry_rows( $entry['form_data'] ) );
			}
		}

		$table        = self::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore
	}

	/**
	 * آمار سریع برای داشبورد پلاگین.
	 */
	public static function stats() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array( 'total' => 0, 'unread' => 0, 'week' => 0, 'today' => 0 );
		}
		$table = self::table_name();
		return array(
			'total'  => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" ), // phpcs:ignore
			'unread' => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status='unread'" ), // phpcs:ignore
			'week'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table} WHERE created_at >= %s", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - WEEK_IN_SECONDS ) ) ), // phpcs:ignore
			'today'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table} WHERE created_at >= %s", current_time( 'Y-m-d' ) . ' 00:00:00' ) ), // phpcs:ignore
		);
	}

	/**
	 * حذف خودکار پیام‌های قدیمی (اختیاری — برای انطباق با سیاست حریم خصوصی).
	 */
	public static function purge_old( $days ) {
		global $wpdb;
		$days = absint( $days );
		if ( $days < 1 || ! self::table_exists() ) {
			return 0;
		}
		$table  = self::table_name();
		$before = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );
		$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE created_at < %s LIMIT 500", $before ) ); // phpcs:ignore
		return ! empty( $ids ) ? self::delete_entries( $ids ) : 0;
	}

	public static function drop_table() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۶) اعتبارسنجی مقادیر
 * ══════════════════════════════════════════════════════════════════════ */

class QA_CF_Validator {

	/**
	 * بررسی یک قانون اعتبارسنجی روی مقدار.
	 *
	 * @return true|string true یعنی معتبر، وگرنه متن خطا.
	 */
	public static function check( $rule, $value, $label ) {
		$value = trim( (string) $value );
		if ( '' === $value || 'none' === $rule ) {
			return true;
		}

		$num = qa_cf_normalize_digits( $value );
		$num = preg_replace( '/[\s\-()]/', '', $num );

		switch ( $rule ) {
			case 'iran_mobile':
				$num = preg_replace( '/^(\+98|0098|98)/', '0', $num );
				if ( ! preg_match( '/^09\d{9}$/', $num ) ) {
					return 'شماره موبایل معتبر نیست؛ نمونه درست: 09121234567';
				}
				return true;

			case 'iran_phone':
				if ( ! preg_match( '/^0\d{9,10}$/', $num ) ) {
					return 'شماره تلفن ثابت معتبر نیست؛ کد شهر را هم وارد کنید. نمونه: 02112345678';
				}
				return true;

			case 'national_code':
				return self::national_code( $num ) ? true : 'کد ملی واردشده معتبر نیست.';

			case 'postal_code':
				if ( ! preg_match( '/^\d{10}$/', $num ) || preg_match( '/^(\d)\1{9}$/', $num ) ) {
					return 'کد پستی باید ۱۰ رقم معتبر باشد.';
				}
				return true;

			case 'numeric':
				if ( ! preg_match( '/^-?\d+(\.\d+)?$/', qa_cf_normalize_digits( $value ) ) ) {
					return $label . ' باید فقط شامل عدد باشد.';
				}
				return true;

			case 'english':
				if ( ! preg_match( '/^[A-Za-z0-9 .,\'@_\-]+$/u', $value ) ) {
					return $label . ' باید فقط با حروف انگلیسی نوشته شود.';
				}
				return true;

			case 'persian':
				if ( ! preg_match( '/^[\x{0600}-\x{06FF}\s\x{200c}.,،؛:!؟\-()0-9]+$/u', $value ) ) {
					return $label . ' باید فقط با حروف فارسی نوشته شود.';
				}
				return true;

			case 'url':
				if ( ! preg_match( '#^https?://[^\s]+$#i', $value ) ) {
					return 'آدرس اینترنتی معتبر نیست؛ با http:// یا https:// شروع کنید.';
				}
				return true;
		}

		return true;
	}

	/**
	 * بررسی رقم کنترل کد ملی ایران.
	 */
	public static function national_code( $code ) {
		if ( ! preg_match( '/^\d{10}$/', $code ) ) {
			return false;
		}
		if ( preg_match( '/^(\d)\1{9}$/', $code ) ) {
			return false;
		}
		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $code[ $i ] * ( 10 - $i );
		}
		$rem   = $sum % 11;
		$check = (int) $code[9];
		return ( $rem < 2 ) ? ( $check === $rem ) : ( $check === 11 - $rem );
	}

	/**
	 * نرمال‌سازی شماره موبایل به فرم استاندارد 09xxxxxxxxx.
	 */
	public static function normalize_mobile( $value ) {
		$num = preg_replace( '/[\s\-()]/', '', qa_cf_normalize_digits( $value ) );
		$num = preg_replace( '/^(\+98|0098|98)/', '0', $num );
		return $num;
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۷) آپلود فایل (اختیاری و با محدودیت‌های سخت‌گیرانه)
 * ══════════════════════════════════════════════════════════════════════ */

class QA_CF_Uploads {

	/**
	 * مسیر پایه آپلودهای فرم.
	 */
	public static function base_dir() {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . QA_CF_UPLOAD_DIR;
	}

	public static function base_url() {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['baseurl'] ) . QA_CF_UPLOAD_DIR;
	}

	/**
	 * ساخت پوشه + فایل‌های محافظ (جلوگیری از اجرای اسکریپت و لیست‌شدن فایل‌ها).
	 */
	public static function ensure_dir() {
		$dir = self::base_dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "Options -Indexes\n"
				. "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|pl|py|jsp|asp|aspx|sh|cgi|htaccess)$\">\n"
				. "\tDeny from all\n"
				. "</FilesMatch>\n"
				. "<IfModule mod_php.c>\n\tphp_flag engine off\n</IfModule>\n";
			@file_put_contents( $htaccess, $rules ); // phpcs:ignore
		}

		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, '' ); // phpcs:ignore
		}

		return $dir;
	}

	/**
	 * پسوندهای مجاز بر اساس تنظیمات، فیلترشده با انواع شناخته‌شده وردپرس.
	 * هر پسوندی که وردپرس آن را نمی‌شناسد (از جمله php و مشابه) به‌طور کامل
	 * حذف می‌شود، حتی اگر مدیر آن را دستی وارد کرده باشد.
	 */
	public static function allowed_mimes() {
		$security = qa_cf_security_settings();
		$wanted   = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $security['upload_types'] ) ) ) );
		$all      = wp_get_mime_types();
		$blocked  = array( 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'js', 'html', 'htm', 'svg', 'exe', 'sh', 'bat', 'cgi', 'pl', 'py' );
		$mimes    = array();

		foreach ( $all as $exts => $mime ) {
			$parts = explode( '|', $exts );
			foreach ( $parts as $ext ) {
				if ( in_array( $ext, $wanted, true ) && ! in_array( $ext, $blocked, true ) ) {
					$mimes[ $exts ] = $mime;
					break;
				}
			}
		}

		return apply_filters( 'qa_cf_allowed_upload_mimes', $mimes );
	}

	public static function max_bytes() {
		$security = qa_cf_security_settings();
		$mb       = max( 1, min( 64, absint( $security['upload_max_mb'] ) ) );
		return min( $mb * 1024 * 1024, wp_max_upload_size() );
	}

	/**
	 * پردازش یک فایل آپلودشده.
	 *
	 * @return array|WP_Error array('name','path','url')
	 */
	public static function handle( $file ) {
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return new WP_Error( 'qa_cf_upload', 'فایل ارسالی معتبر نیست.' );
		}

		if ( UPLOAD_ERR_NO_FILE === $file['error'] ) {
			return new WP_Error( 'qa_cf_no_file', '' );
		}
		if ( UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] ) {
			return new WP_Error( 'qa_cf_upload', 'حجم فایل بیش از حد مجاز سرور است.' );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'qa_cf_upload', 'آپلود فایل ناموفق بود. دوباره تلاش کنید.' );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'qa_cf_upload', 'فایل ارسالی معتبر نیست.' );
		}
		if ( $file['size'] > self::max_bytes() ) {
			return new WP_Error( 'qa_cf_upload', sprintf( 'حجم فایل نباید بیشتر از %s مگابایت باشد.', qa_cf_fa_num( round( self::max_bytes() / 1048576, 1 ) ) ) );
		}

		$mimes = self::allowed_mimes();
		if ( empty( $mimes ) ) {
			return new WP_Error( 'qa_cf_upload', 'هیچ نوع فایلی برای آپلود مجاز نشده است.' );
		}

		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error( 'qa_cf_upload', 'این نوع فایل مجاز نیست.' );
		}

		self::ensure_dir();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$dir_filter = function ( $dirs ) {
			$sub           = '/' . QA_CF_UPLOAD_DIR . '/' . gmdate( 'Y/m' );
			$dirs['path']  = $dirs['basedir'] . $sub;
			$dirs['url']   = $dirs['baseurl'] . $sub;
			$dirs['subdir'] = $sub;
			return $dirs;
		};

		// نام فایل غیرقابل‌حدس (جلوگیری از دسترسی مستقیم به پیوست‌های دیگران).
		$name_filter = function ( $f ) {
			$ext     = pathinfo( $f['name'], PATHINFO_EXTENSION );
			$base    = pathinfo( $f['name'], PATHINFO_FILENAME );
			$base    = sanitize_file_name( $base );
			$base    = '' !== $base ? qa_cf_safe_substr( $base, 40 ) : 'file';
			$f['name'] = $base . '-' . wp_generate_password( 12, false, false ) . '.' . strtolower( $ext );
			return $f;
		};

		add_filter( 'upload_dir', $dir_filter );
		add_filter( 'wp_handle_upload_prefilter', $name_filter );

		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $mimes,
			)
		);

		remove_filter( 'upload_dir', $dir_filter );
		remove_filter( 'wp_handle_upload_prefilter', $name_filter );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'qa_cf_upload', $result['error'] );
		}

		self::ensure_dir();

		return array(
			'name' => wp_basename( $result['file'] ),
			'path' => $result['file'],
			'url'  => $result['url'],
		);
	}

	/**
	 * حذف فایل‌های یک پیام هنگام حذف آن پیام.
	 */
	public static function delete_entry_files( $rows ) {
		$base = wp_normalize_path( self::base_dir() );
		foreach ( (array) $rows as $row ) {
			if ( empty( $row['path'] ) ) {
				continue;
			}
			$path = wp_normalize_path( $row['path'] );
			// فقط فایل‌هایی که واقعاً داخل پوشه‌ی خود پلاگین‌اند حذف می‌شوند.
			if ( 0 === strpos( $path, $base ) && file_exists( $path ) ) {
				@unlink( $path ); // phpcs:ignore
			}
		}
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۸) ارسال ایمیل
 * ══════════════════════════════════════════════════════════════════════ */

class QA_CF_Mailer {

	/** آخرین خطای PHPMailer برای نمایش دقیق در «تست ارسال». */
	public static $last_error = '';

	public static function init() {
		add_action(
			'wp_mail_failed',
			function ( $error ) {
				if ( is_wp_error( $error ) ) {
					self::$last_error = $error->get_error_message();
				}
			}
		);
	}

	/**
	 * ایمیل اعلان مدیر.
	 */
	public static function send_entry_notification( $fields, $attachments = array() ) {
		$email = qa_cf_email_settings();

		if ( empty( $email['notify_enabled'] ) ) {
			return true;
		}

		$to      = ! empty( $email['notify_email'] ) ? self::parse_recipients( $email['notify_email'] ) : array( get_option( 'admin_email' ) );
		$subject = ! empty( $email['email_subject'] ) ? $email['email_subject'] : 'پیام جدید از فرم تماس سایت';
		$subject = self::replace_tags( $subject, $fields );

		$plain   = ( 'plain' === $email['email_template'] );
		$body    = $plain ? self::build_plain_body( $fields ) : self::build_email_body( $fields, $email['email_template'] );
		$headers = array( 'Content-Type: ' . ( $plain ? 'text/plain' : 'text/html' ) . '; charset=UTF-8' );

		$reply_to = self::resolve_reply_to( $fields, $email );
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$files = ! empty( $email['attach_files'] ) ? (array) $attachments : array();

		return self::dispatch( $to, $subject, $body, $headers, $files );
	}

	/**
	 * پاسخ خودکار به ارسال‌کننده‌ی فرم.
	 */
	public static function send_autoreply( $fields ) {
		$email = qa_cf_email_settings();

		if ( empty( $email['autoreply_enabled'] ) ) {
			return false;
		}

		$to = self::find_email_in_fields( $fields );
		if ( ! $to ) {
			return false;
		}

		$subject = self::replace_tags( $email['autoreply_subject'], $fields );
		$body    = self::replace_tags( $email['autoreply_body'], $fields );

		$html = '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;font-size:14px;line-height:2;color:#0d1117">'
			. wpautop( esc_html( $body ) )
			. '</div>';

		return self::dispatch( array( $to ), $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function send_test_email( $to ) {
		$email     = qa_cf_email_settings();
		$smtp      = qa_cf_smtp_settings();
		$from_line = ! empty( $email['from_email'] )
			? esc_html( $email['from_name'] ) . ' &lt;' . esc_html( $email['from_email'] ) . '&gt;'
			: 'ایمیل پیش‌فرض سرور (فرستنده سفارشی تنظیم نشده)';

		$body = '<div dir="rtl" style="font-family:Tahoma,sans-serif;font-size:14px;line-height:2">'
			. '<p>این یک ایمیل آزمایشی از فرم تماس سایت است.</p>'
			. '<p>اگر این پیام را دریافت کرده‌اید، تنظیمات ارسال ایمیل درست کار می‌کند.</p>'
			. '<p style="color:#64748b">فرستنده: ' . $from_line . '</p>'
			. '<p style="color:#64748b">روش ارسال: ' . ( ! empty( $smtp['enabled'] ) ? 'SMTP اختصاصی' : 'wp_mail پیش‌فرض' ) . '</p>'
			. '</div>';

		return self::dispatch( array( $to ), 'ایمیل آزمایشی — فرم تماس', $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	private static function dispatch( $to, $subject, $body, $headers, $attachments = array() ) {
		$email    = qa_cf_email_settings();
		$smtp     = qa_cf_smtp_settings();
		$use_smtp = ! empty( $smtp['enabled'] ) && ! empty( $smtp['host'] ) && ! empty( $smtp['username'] ) && ! empty( $smtp['password'] );

		$from_email = ! empty( $email['from_email'] ) ? $email['from_email'] : '';
		$from_name  = ! empty( $email['from_name'] ) ? $email['from_name'] : get_bloginfo( 'name' );

		if ( $use_smtp && empty( $from_email ) && is_email( $smtp['username'] ) ) {
			$from_email = $smtp['username'];
		}

		$configurator = null;

		if ( $use_smtp || ! empty( $from_email ) ) {
			$configurator = function ( $phpmailer ) use ( $smtp, $use_smtp, $from_email, $from_name ) {
				if ( $use_smtp ) {
					$phpmailer->isSMTP();
					$phpmailer->Host     = $smtp['host'];
					$phpmailer->Port     = ! empty( $smtp['port'] ) ? (int) $smtp['port'] : 587;
					$phpmailer->SMTPAuth = true;
					$phpmailer->Username = $smtp['username'];
					$phpmailer->Password = $smtp['password'];
					$phpmailer->CharSet  = 'UTF-8';
					$phpmailer->Timeout  = 20;

					if ( 'none' === $smtp['encryption'] ) {
						$phpmailer->SMTPSecure  = '';
						$phpmailer->SMTPAutoTLS = false;
					} else {
						$phpmailer->SMTPSecure = $smtp['encryption'];
					}
				}

				if ( ! empty( $from_email ) && is_email( $from_email ) ) {
					$phpmailer->setFrom( $from_email, $from_name, false );
				}
			};

			add_action( 'phpmailer_init', $configurator );
		}

		self::$last_error = '';
		$sent             = wp_mail( $to, $subject, $body, $headers, $attachments );

		if ( $configurator ) {
			remove_action( 'phpmailer_init', $configurator );
		}

		return $sent;
	}

	private static function parse_recipients( $raw ) {
		$parts = array_map( 'trim', explode( ',', (string) $raw ) );
		$valid = array_filter( $parts, 'is_email' );
		return ! empty( $valid ) ? array_values( $valid ) : array( get_option( 'admin_email' ) );
	}

	private static function resolve_reply_to( $fields, $email ) {
		$mode = ! empty( $email['reply_to_mode'] ) ? $email['reply_to_mode'] : 'auto';

		if ( 'off' === $mode ) {
			return '';
		}
		if ( 'fixed' === $mode ) {
			return ( ! empty( $email['reply_to_fixed'] ) && is_email( $email['reply_to_fixed'] ) ) ? $email['reply_to_fixed'] : '';
		}
		return self::find_email_in_fields( $fields );
	}

	/**
	 * جایگزینی شورت‌تگ‌ها در موضوع/متن ایمیل:
	 * {site} {date} {ip} {name} {email} و {field:KEY}
	 */
	public static function replace_tags( $text, $fields ) {
		$text = (string) $text;

		$name  = '';
		$mail  = self::find_email_in_fields( $fields );
		$pairs = array();

		foreach ( (array) $fields as $key => $item ) {
			$value = isset( $item['value'] ) ? $item['value'] : '';
			$value = is_array( $value ) ? implode( '، ', $value ) : $value;
			$pairs[ '{field:' . $key . '}' ] = $value;

			$label = isset( $item['label'] ) ? $item['label'] : '';
			if ( '' === $name && ( false !== strpos( $label, 'نام' ) || false !== strpos( (string) $key, 'name' ) ) ) {
				$name = $value;
			}
		}

		$pairs['{site}']  = get_bloginfo( 'name' );
		$pairs['{date}']  = date_i18n( 'Y/m/d H:i' );
		$pairs['{ip}']    = QA_CF_DB::get_client_ip();
		$pairs['{name}']  = $name;
		$pairs['{email}'] = $mail;

		return strtr( $text, $pairs );
	}

	private static function build_email_body( $fields, $template = 'modern' ) {
		$rows = '';
		foreach ( $fields as $item ) {
			$label = isset( $item['label'] ) ? $item['label'] : '';
			$value = isset( $item['value'] ) ? $item['value'] : '';
			$value = is_array( $value ) ? implode( '، ', $value ) : $value;

			if ( ! empty( $item['url'] ) ) {
				$value = '<a href="' . esc_url( $item['url'] ) . '" style="color:#0c8a85">' . esc_html( $value ) . '</a>';
			} else {
				$value = nl2br( esc_html( $value ) );
			}

			$rows .= '<tr>'
				. '<td style="padding:11px 14px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:700;white-space:nowrap;vertical-align:top">' . esc_html( $label ) . '</td>'
				. '<td style="padding:11px 14px;border:1px solid #e2e8f0">' . $value . '</td>'
				. '</tr>';
		}

		$site = esc_html( get_bloginfo( 'name' ) );
		$date = esc_html( date_i18n( 'Y/m/d — H:i' ) );

		if ( 'simple' === $template ) {
			return '<div dir="rtl" style="font-family:Tahoma,sans-serif;font-size:14px;color:#0d1117">'
				. '<h2 style="margin:0 0 14px">پیام جدید از ' . $site . '</h2>'
				. '<table style="border-collapse:collapse;width:100%;max-width:620px">' . $rows . '</table>'
				. '</div>';
		}

		return '<div dir="rtl" style="background:#f1f5f9;padding:24px;font-family:Tahoma,Arial,sans-serif">'
			. '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">'
			. '<div style="background:linear-gradient(135deg,#0c8a85,#0ea5a0);padding:20px 24px;color:#fff">'
			. '<h2 style="margin:0;font-size:18px">پیام جدید از فرم تماس</h2>'
			. '<p style="margin:6px 0 0;font-size:12px;opacity:.85">' . $site . ' — ' . $date . '</p>'
			. '</div>'
			. '<div style="padding:22px 24px">'
			. '<table style="border-collapse:collapse;width:100%;font-size:14px;color:#0d1117">' . $rows . '</table>'
			. '</div>'
			. '<div style="padding:14px 24px;background:#f8fafc;color:#94a3b8;font-size:11px;border-top:1px solid #e2e8f0">'
			. 'این ایمیل به‌صورت خودکار توسط فرم تماس سایت ارسال شده است.'
			. '</div></div></div>';
	}

	private static function build_plain_body( $fields ) {
		$lines = array();
		foreach ( $fields as $item ) {
			$label   = isset( $item['label'] ) ? $item['label'] : '';
			$value   = isset( $item['value'] ) ? $item['value'] : '';
			$value   = is_array( $value ) ? implode( '، ', $value ) : $value;
			$lines[] = $label . ': ' . $value;
		}
		$lines[] = '';
		$lines[] = 'تاریخ: ' . date_i18n( 'Y/m/d H:i' );
		$lines[] = 'سایت: ' . get_bloginfo( 'name' );
		return implode( "\n", $lines );
	}

	public static function find_email_in_fields( $fields ) {
		foreach ( (array) $fields as $item ) {
			$value = isset( $item['value'] ) ? $item['value'] : '';
			if ( is_string( $value ) && is_email( $value ) ) {
				return $value;
			}
		}
		return '';
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۹) اعلان‌های بیرونی (تلگرام و وبهوک)
 * ══════════════════════════════════════════════════════════════════════ */

class QA_CF_Notify {

	public static function dispatch( $fields, $entry_id ) {
		$settings = qa_cf_integration_settings();

		if ( ! empty( $settings['telegram_enabled'] ) && ! empty( $settings['telegram_token'] ) && ! empty( $settings['telegram_chat'] ) ) {
			self::telegram( $fields, $settings );
		}

		if ( ! empty( $settings['webhook_enabled'] ) && ! empty( $settings['webhook_url'] ) ) {
			self::webhook( $fields, $entry_id, $settings );
		}
	}

	/**
	 * اعلان تلگرام — بدون هیچ کتابخانه‌ی جانبی، فقط wp_remote_post.
	 */
	public static function telegram( $fields, $settings ) {
		$lines = array( '📩 *پیام جدید از فرم تماس*', '' );

		foreach ( (array) $fields as $item ) {
			$label   = isset( $item['label'] ) ? $item['label'] : '';
			$value   = isset( $item['value'] ) ? $item['value'] : '';
			$value   = is_array( $value ) ? implode( '، ', $value ) : $value;
			$lines[] = '• ' . self::md_escape( $label ) . ': ' . self::md_escape( $value );
		}

		$lines[] = '';
		$lines[] = '🌐 ' . self::md_escape( get_bloginfo( 'name' ) );
		$lines[] = '🕒 ' . self::md_escape( date_i18n( 'Y/m/d H:i' ) );

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $settings['telegram_token'] ) . '/sendMessage',
			array(
				'timeout' => 12,
				'body'    => array(
					'chat_id'    => $settings['telegram_chat'],
					'text'       => qa_cf_safe_substr( implode( "\n", $lines ), 3900 ),
					'parse_mode' => 'Markdown',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( 'qa_cf_telegram_error', $response->get_error_message(), DAY_IN_SECONDS );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			set_transient( 'qa_cf_telegram_error', 'کد پاسخ تلگرام: ' . $code, DAY_IN_SECONDS );
			return false;
		}

		delete_transient( 'qa_cf_telegram_error' );
		return true;
	}

	private static function md_escape( $text ) {
		return str_replace( array( '_', '*', '`', '[' ), array( '\_', '\*', '\`', '\[' ), (string) $text );
	}

	/**
	 * ارسال داده‌های فرم به یک وبهوک (CRM، Google Sheets، Make، n8n و…).
	 * در صورت تنظیم «کلید امضا»، هدر X-QA-CF-Signature با HMAC-SHA256 اضافه
	 * می‌شود تا سمت گیرنده بتواند اصالت درخواست را بررسی کند.
	 */
	public static function webhook( $fields, $entry_id, $settings ) {
		$url = esc_url_raw( $settings['webhook_url'] );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$data = array();
		foreach ( (array) $fields as $key => $item ) {
			$data[ $key ] = array(
				'label' => isset( $item['label'] ) ? $item['label'] : '',
				'value' => isset( $item['value'] ) ? $item['value'] : '',
			);
		}

		$payload = wp_json_encode(
			array(
				'entry_id'  => (int) $entry_id,
				'site'      => home_url(),
				'site_name' => get_bloginfo( 'name' ),
				'date'      => current_time( 'mysql' ),
				'fields'    => $data,
			)
		);

		$headers = array( 'Content-Type' => 'application/json; charset=utf-8' );

		if ( ! empty( $settings['webhook_secret'] ) ) {
			$headers['X-QA-CF-Signature'] = hash_hmac( 'sha256', $payload, $settings['webhook_secret'] );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 12,
				'blocking' => false,
				'headers'  => $headers,
				'body'     => $payload,
			)
		);

		return ! is_wp_error( $response );
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۱۰) شورت‌کد، رندر فرم و پردازش ارسال
 * ══════════════════════════════════════════════════════════════════════ */

class QA_CF_Shortcode {

	private static $rendered       = false;
	private static $instance_count = 0;
	private static $assets_printed = false;

	public static function init() {
		add_shortcode( 'qa_contact_form', array( __CLASS__, 'render' ) );

		// اگر مدیر گزینه «بارگذاری همیشگی» را فعال کرده باشد (مناسب پاپ‌آپ/
		// هدر/فوتر صفحه‌سازها که خارج از محتوای اصلی رندر می‌شوند).
		add_action(
			'wp',
			function () {
				$settings = qa_cf_form_settings();
				if ( ! empty( $settings['always_load_assets'] ) && ! is_admin() ) {
					self::$rendered = true;
				}
			}
		);

		add_action( 'wp_footer', array( __CLASS__, 'maybe_output_assets' ), PHP_INT_MAX );

		add_action( 'wp_ajax_qa_cf_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_qa_cf_submit', array( __CLASS__, 'handle_submit' ) );

		// نانس تازه برای صفحات کش‌شده.
		add_action( 'wp_ajax_qa_cf_refresh_nonce', array( __CLASS__, 'ajax_refresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_qa_cf_refresh_nonce', array( __CLASS__, 'ajax_refresh_nonce' ) );
	}

	public static function ajax_refresh_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'qa_cf_submit' ) ) );
	}

	public static function assets_config() {
		return array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'qa_cf_submit' ),
			'i18n'     => array(
				'required'      => 'این فیلد الزامی است.',
				'email'         => 'ایمیل واردشده معتبر نیست.',
				'iran_mobile'   => 'شماره موبایل معتبر نیست؛ نمونه: 09121234567',
				'iran_phone'    => 'شماره تلفن ثابت معتبر نیست.',
				'national_code' => 'کد ملی واردشده معتبر نیست.',
				'postal_code'   => 'کد پستی باید ۱۰ رقم باشد.',
				'numeric'       => 'فقط عدد وارد کنید.',
				'english'       => 'فقط با حروف انگلیسی بنویسید.',
				'persian'       => 'فقط با حروف فارسی بنویسید.',
				'url'           => 'آدرس اینترنتی معتبر نیست.',
				'minlen'        => 'حداقل %d نویسه لازم است.',
				'maxlen'        => 'حداکثر %d نویسه مجاز است.',
				'file_size'     => 'حجم فایل نباید بیشتر از %s مگابایت باشد.',
				'fix_errors'    => 'لطفاً خطاهای مشخص‌شده را برطرف کنید.',
				'network'       => 'مشکلی در ارتباط با سرور پیش آمد. لطفاً دوباره تلاش کنید.',
			),
		);
	}

	public static function maybe_output_assets() {
		if ( ! self::$rendered || self::$assets_printed ) {
			return;
		}
		self::$assets_printed = true;

		$design     = qa_cf_design_settings();
		$design_css = qa_cf_generate_design_css( $design, '.qa-cf-wrap' );
		$custom_css = (string) get_option( 'qa_cf_custom_css', '' );

		if ( ! empty( $design['load_font'] ) && 'vazir' === $design['font_family'] ) {
			echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>';
			echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" media="print" onload="this.media=\'all\'">';
		}

		echo '<style id="qa-cf-css">' . $design_css . qa_cf_form_css() . '</style>'; // phpcs:ignore

		if ( '' !== trim( $custom_css ) ) {
			echo '<style id="qa-cf-custom-css">' . wp_strip_all_tags( $custom_css ) . '</style>'; // phpcs:ignore
		}

		echo '<script id="qa-cf-js">window.QA_CF=' . wp_json_encode( self::assets_config() ) . ';' . qa_cf_form_js() . '</script>'; // phpcs:ignore
	}

	/**
	 * تبدیل متن گزینه‌ها (هر خط یک گزینه، با پشتیبانی از «مقدار|برچسب») به آرایه.
	 */
	public static function parse_options( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$out   = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, '|' ) ) {
				$parts = explode( '|', $line, 2 );
				$value = trim( $parts[0] );
				$label = trim( $parts[1] );
			} else {
				$value = $line;
				$label = $line;
			}
			if ( '' === $value ) {
				continue;
			}
			$out[] = array( 'value' => $value, 'label' => '' !== $label ? $label : $value );
		}

		return $out;
	}

	public static function option_values( $raw ) {
		return wp_list_pluck( self::parse_options( $raw ), 'value' );
	}

	/**
	 * رندر فرم.
	 *
	 * @param array $atts title, desc, class, columns, preview
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title'   => null,
				'desc'    => null,
				'class'   => '',
				'columns' => '',
				'preview' => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'qa_contact_form'
		);

		$fields   = qa_cf_fields();
		$form     = qa_cf_form_settings();
		$design   = qa_cf_design_settings();
		$security = qa_cf_security_settings();
		$preview  = ( '1' === (string) $atts['preview'] );

		if ( empty( $fields ) ) {
			return '<p>هنوز هیچ فیلدی برای فرم تعریف نشده است.</p>';
		}

		if ( ! $preview ) {
			self::$rendered = true;
			// اگر شورت‌کد بعد از چاپ فوتر اجرا شود (برخی صفحه‌سازها)، دارایی‌ها
			// را همان‌جا چاپ کن تا فرم بدون CSS/JS نماند.
			$late_assets = did_action( 'wp_footer' ) && ! self::$assets_printed;
		} else {
			$late_assets = false;
		}

		$instance = ++self::$instance_count;
		$id_pre   = 'qacf-' . ( $preview ? 'pv-' : '' ) . $instance;

		$title = ( null !== $atts['title'] ) ? $atts['title'] : $form['form_title'];
		$desc  = ( null !== $atts['desc'] ) ? $atts['desc'] : $form['form_desc'];

		$columns = in_array( (string) $atts['columns'], array( '1', '2', '3' ), true ) ? (string) $atts['columns'] : (string) $design['columns'];

		$classes = array(
			'qa-cf-wrap',
			'qa-cf-cols-' . $columns,
			'qa-cf-label-' . ( in_array( $design['label_style'], array( 'top', 'floating', 'hidden' ), true ) ? $design['label_style'] : 'top' ),
			'qa-cf-anim-' . ( in_array( $design['animation'], array( 'none', 'fade', 'slide' ), true ) ? $design['animation'] : 'fade' ),
		);
		if ( ! empty( $design['card_mode'] ) ) {
			$classes[] = 'qa-cf-card';
		}
		if ( ! empty( $design['button_full'] ) ) {
			$classes[] = 'qa-cf-btn-full';
		}
		if ( 'ltr' === $design['direction'] ) {
			$classes[] = 'qa-cf-ltr';
		}
		if ( ! empty( $atts['class'] ) ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}

		$has_file = false;
		foreach ( $fields as $f ) {
			if ( 'file' === $f['type'] ) {
				$has_file = true;
				break;
			}
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" dir="<?php echo 'ltr' === $design['direction'] ? 'ltr' : 'rtl'; ?>" data-scroll="<?php echo ! empty( $form['scroll_on_success'] ) ? '1' : '0'; ?>">

			<?php if ( '' !== trim( (string) $title ) || '' !== trim( (string) $desc ) ) : ?>
				<div class="qa-cf-head">
					<?php if ( '' !== trim( (string) $title ) ) : ?>
						<h2 class="qa-cf-title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $desc ) ) : ?>
						<p class="qa-cf-desc"><?php echo esc_html( $desc ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="qa-cf-result" data-qa-cf-result role="status" aria-live="polite"></div>

			<form class="qa-cf-form" method="post" novalidate
				<?php echo $has_file ? 'enctype="multipart/form-data"' : ''; ?>
				data-qa-cf-form
				<?php echo $preview ? 'data-preview="1"' : ''; ?>
				data-form-id="<?php echo esc_attr( $instance ); ?>"
				data-loading="<?php echo esc_attr( $form['submit_loading'] ); ?>"
				data-validate-live="<?php echo ! empty( $form['live_validation'] ) ? '1' : '0'; ?>"
				data-normalize="<?php echo ! empty( $form['normalize_digits'] ) ? '1' : '0'; ?>"
				data-draft="<?php echo ! empty( $form['save_draft'] ) ? '1' : '0'; ?>"
				data-reset="<?php echo ! empty( $form['reset_on_success'] ) ? '1' : '0'; ?>"
				data-hide="<?php echo ! empty( $form['hide_form_success'] ) ? '1' : '0'; ?>"
				data-delay="<?php echo absint( $form['redirect_delay'] ); ?>">

				<?php if ( ! $preview ) : ?>
					<?php wp_nonce_field( 'qa_cf_submit', 'qa_cf_nonce', false ); ?>
					<?php
					$ts = time();
					echo '<input type="hidden" name="qa_cf_ts" value="' . esc_attr( $ts ) . '">';
					echo '<input type="hidden" name="qa_cf_ts_sig" value="' . esc_attr( wp_hash( 'qa_cf_ts|' . $ts ) ) . '">';
					?>
				<?php endif; ?>

				<label class="qa-cf-hp" aria-hidden="true">
					<span>این فیلد را خالی بگذارید</span>
					<input type="text" name="qa_cf_hp" tabindex="-1" autocomplete="off" value="">
				</label>

				<div class="qa-cf-row">
					<?php
					foreach ( $fields as $field ) {
						self::render_field( $field, $id_pre );
					}

					if ( ! empty( $security['captcha_enabled'] ) ) {
						self::render_captcha( $id_pre, $preview );
					}

					if ( ! empty( $form['consent_enabled'] ) ) {
						self::render_consent( $id_pre, $form );
					}
					?>
				</div>

				<div class="qa-cf-actions">
					<button type="submit" class="qa-cf-submit">
						<span class="qa-cf-spinner" aria-hidden="true"></span>
						<?php echo self::button_icon( $design['button_icon'] ); // phpcs:ignore ?>
						<span class="qa-cf-submit-text"><?php echo esc_html( $form['submit_text'] ); ?></span>
					</button>
				</div>
			</form>
		</div>
		<?php
		$html = ob_get_clean();

		if ( ! empty( $late_assets ) ) {
			ob_start();
			self::maybe_output_assets();
			$html .= ob_get_clean();
		}

		return $html;
	}

	private static function button_icon( $icon ) {
		$icons = array(
			'send'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>',
			'mail'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>',
			'arrow' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>',
			'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
		);
		return isset( $icons[ $icon ] ) ? $icons[ $icon ] : '';
	}

	/**
	 * رندر یک فیلد.
	 */
	private static function render_field( $field, $id_pre ) {
		$field = wp_parse_args( $field, qa_cf_default_field() );

		$key  = sanitize_key( $field['key'] );
		$type = array_key_exists( $field['type'], QA_CF_DB::field_types() ) ? $field['type'] : 'text';

		if ( '' === $key && 'heading' !== $type ) {
			return;
		}

		$width   = in_array( $field['width'], array( 'full', 'half', 'third' ), true ) ? $field['width'] : 'half';
		$w_class = 'qa-cf-w-' . $width;

		/* ── عنوان/جداکننده بخش ── */
		if ( 'heading' === $type ) {
			echo '<div class="qa-cf-heading">';
			if ( '' !== $field['label'] ) {
				echo '<h3>' . esc_html( $field['label'] ) . '</h3>';
			}
			if ( '' !== $field['help'] ) {
				echo '<p>' . esc_html( $field['help'] ) . '</p>';
			}
			echo '<hr></div>';
			return;
		}

		$id          = $id_pre . '-' . $key;
		$required    = ! empty( $field['required'] );
		$label       = $field['label'];
		$placeholder = $field['placeholder'];
		$maxlength   = QA_CF_DB::field_max_length( $type );
		$min         = absint( $field['min'] );
		$max         = absint( $field['max'] );
		$custom      = $field['css_class'] ? ' ' . sanitize_html_class( $field['css_class'] ) : '';
		$req_mark    = $required ? '<span class="qa-cf-req" aria-hidden="true">*</span>' : '<span class="qa-cf-optional">(اختیاری)</span>';
		$auto        = $field['autocomplete'] ? ' autocomplete="' . esc_attr( $field['autocomplete'] ) . '"' : '';
		$req_attr    = $required ? ' required aria-required="true"' : '';
		$form        = qa_cf_form_settings();
		$counter     = ( ! empty( $form['char_counter'] ) && 'textarea' === $type );

		$attrs = sprintf(
			' class="qa-cf-field %s%s" data-key="%s" data-validate="%s" data-min="%d" data-max="%d"',
			esc_attr( $w_class ),
			esc_attr( $custom ),
			esc_attr( $key ),
			esc_attr( array_key_exists( $field['validate'], QA_CF_DB::validate_rules() ) ? $field['validate'] : 'none' ),
			$min,
			$max
		);

		echo '<div' . $attrs . '>'; // phpcs:ignore

		$label_html = '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ' ' . $req_mark . '</label>';

		switch ( $type ) {

			case 'textarea':
				echo '<div class="qa-cf-ctrl">';
				printf(
					'<textarea id="%1$s" name="%2$s" rows="%3$d" placeholder="%4$s" maxlength="%5$d"%6$s%7$s>%8$s</textarea>',
					esc_attr( $id ),
					esc_attr( $key ),
					max( 2, absint( $field['rows'] ) ),
					esc_attr( '' !== $placeholder ? $placeholder : ' ' ),
					$maxlength,
					$req_attr, // phpcs:ignore
					$auto, // phpcs:ignore
					esc_textarea( $field['default'] )
				);
				echo $label_html; // phpcs:ignore
				echo '</div>';
				if ( $counter ) {
					echo '<div class="qa-cf-counter" data-max="' . esc_attr( $maxlength ) . '">0 / ' . esc_html( $maxlength ) . '</div>';
				}
				break;

			case 'select':
				echo '<div class="qa-cf-ctrl">';
				printf( '<select id="%1$s" name="%2$s"%3$s%4$s>', esc_attr( $id ), esc_attr( $key ), $req_attr, $auto ); // phpcs:ignore
				echo '<option value="">' . esc_html( '' !== $placeholder ? $placeholder : '— انتخاب کنید —' ) . '</option>';
				foreach ( self::parse_options( $field['options'] ) as $opt ) {
					printf(
						'<option value="%1$s"%3$s>%2$s</option>',
						esc_attr( $opt['value'] ),
						esc_html( $opt['label'] ),
						selected( $field['default'], $opt['value'], false )
					);
				}
				echo '</select>';
				echo $label_html; // phpcs:ignore
				echo '</div>';
				break;

			case 'radio':
			case 'checkbox_group':
				$is_radio = ( 'radio' === $type );
				$name     = $is_radio ? $key : $key . '[]';
				echo '<span class="qa-cf-group-label" id="' . esc_attr( $id ) . '-lbl"><label>' . esc_html( $label ) . ' ' . $req_mark . '</label></span>';
				echo '<div class="qa-cf-choice-list" role="group" aria-labelledby="' . esc_attr( $id ) . '-lbl">';
				$i = 0;
				foreach ( self::parse_options( $field['options'] ) as $opt ) {
					$oid = $id . '-' . ( ++$i );
					printf(
						'<label class="qa-cf-choice" for="%1$s"><input type="%2$s" id="%1$s" name="%3$s" value="%4$s"%5$s%6$s><span>%7$s</span></label>',
						esc_attr( $oid ),
						$is_radio ? 'radio' : 'checkbox',
						esc_attr( $name ),
						esc_attr( $opt['value'] ),
						checked( $field['default'], $opt['value'], false ),
						( $is_radio && $required ) ? ' required' : '',
						esc_html( $opt['label'] )
					);
				}
				echo '</div>';
				break;

			case 'checkbox':
				printf(
					'<label class="qa-cf-choice" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s><span>%4$s %5$s</span></label>',
					esc_attr( $id ),
					esc_attr( $key ),
					$req_attr, // phpcs:ignore
					esc_html( $label ),
					$req_mark // phpcs:ignore
				);
				break;

			case 'file':
				$max_mb = round( QA_CF_Uploads::max_bytes() / 1048576, 1 );
				$accept = array();
				foreach ( array_keys( QA_CF_Uploads::allowed_mimes() ) as $exts ) {
					foreach ( explode( '|', $exts ) as $ext ) {
						$accept[] = '.' . $ext;
					}
				}
				echo '<span class="qa-cf-group-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ' ' . $req_mark . '</label></span>';
				echo '<span class="qa-cf-file">';
				printf(
					'<input type="file" id="%1$s" name="%2$s" accept="%3$s" data-max-mb="%4$s"%5$s>',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( implode( ',', array_slice( $accept, 0, 40 ) ) ),
					esc_attr( $max_mb ),
					$req_attr // phpcs:ignore
				);
				echo '<span class="qa-cf-file-box">'
					. '<span class="qa-cf-file-btn">انتخاب فایل</span>'
					. '<span class="qa-cf-file-name" data-empty="' . esc_attr( 'فایلی انتخاب نشده (حداکثر ' . qa_cf_fa_num( $max_mb ) . ' مگابایت)' ) . '">'
					. esc_html( 'فایلی انتخاب نشده (حداکثر ' . qa_cf_fa_num( $max_mb ) . ' مگابایت)' )
					. '</span></span>';
				echo '</span>';
				break;

			default:
				$input_types = array( 'text' => 'text', 'email' => 'email', 'tel' => 'tel', 'number' => 'number', 'url' => 'url', 'date' => 'date' );
				$input_type  = isset( $input_types[ $type ] ) ? $input_types[ $type ] : 'text';
				$extra       = '';
				if ( 'number' === $type ) {
					$extra = ( $field['min'] !== '' ? ' min="' . esc_attr( $field['min'] ) . '"' : '' ) . ( $field['max'] !== '' ? ' max="' . esc_attr( $field['max'] ) . '"' : '' );
				}
				echo '<div class="qa-cf-ctrl">';
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" maxlength="%6$d"%7$s%8$s%9$s>',
					esc_attr( $input_type ),
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( $field['default'] ),
					esc_attr( '' !== $placeholder ? $placeholder : ' ' ),
					$maxlength,
					$req_attr, // phpcs:ignore
					$auto, // phpcs:ignore
					$extra // phpcs:ignore
				);
				echo $label_html; // phpcs:ignore
				echo '</div>';
				break;
		}

		if ( '' !== $field['help'] && 'heading' !== $type ) {
			echo '<small class="qa-cf-help">' . esc_html( $field['help'] ) . '</small>';
		}

		echo '<span class="qa-cf-error-msg" data-error-for="' . esc_attr( $key ) . '" aria-live="polite"></span>';
		echo '</div>';
	}

	/**
	 * چک‌باکس رضایت/قوانین (اختیاری).
	 */
	private static function render_consent( $id_pre, $form ) {
		$id = $id_pre . '-consent';
		echo '<div class="qa-cf-field qa-cf-consent" data-key="qa_cf_consent" data-validate="none" data-min="0" data-max="0">';
		printf(
			'<label class="qa-cf-choice" for="%1$s"><input type="checkbox" id="%1$s" name="qa_cf_consent" value="1"%2$s><span>%3$s</span></label>',
			esc_attr( $id ),
			! empty( $form['consent_required'] ) ? ' required aria-required="true"' : '',
			wp_kses( $form['consent_text'], array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array(), 'em' => array() ) )
		);
		echo '<span class="qa-cf-error-msg" data-error-for="qa_cf_consent" aria-live="polite"></span>';
		echo '</div>';
	}

	/**
	 * کپچای ریاضی بدون وابستگی خارجی. عددها stateless و با wp_hash امضا
	 * می‌شوند تا در سمت کاربر قابل دست‌کاری نباشند.
	 */
	private static function render_captcha( $id_pre, $preview = false ) {
		$num1 = wp_rand( 1, 9 );
		$num2 = wp_rand( 1, 9 );
		$id   = $id_pre . '-captcha';
		?>
		<div class="qa-cf-field qa-cf-w-half" data-key="qa_cf_captcha" data-validate="numeric" data-min="0" data-max="0">
			<div class="qa-cf-ctrl">
				<input type="text" inputmode="numeric" autocomplete="off" id="<?php echo esc_attr( $id ); ?>" name="qa_cf_captcha_answer" placeholder=" " required aria-required="true">
				<label for="<?php echo esc_attr( $id ); ?>">
					<?php printf( 'حاصل %s + %s چند می‌شود؟', esc_html( qa_cf_fa_num( $num1 ) ), esc_html( qa_cf_fa_num( $num2 ) ) ); ?>
					<span class="qa-cf-req" aria-hidden="true">*</span>
				</label>
			</div>
			<?php if ( ! $preview ) : ?>
				<input type="hidden" name="qa_cf_captcha_n1" value="<?php echo esc_attr( $num1 ); ?>">
				<input type="hidden" name="qa_cf_captcha_n2" value="<?php echo esc_attr( $num2 ); ?>">
				<input type="hidden" name="qa_cf_captcha_sig" value="<?php echo esc_attr( self::captcha_sig( $num1, $num2 ) ); ?>">
			<?php endif; ?>
			<span class="qa-cf-error-msg" data-error-for="qa_cf_captcha" aria-live="polite"></span>
		</div>
		<?php
	}

	private static function captcha_sig( $num1, $num2 ) {
		return wp_hash( 'qa_cf_captcha|' . absint( $num1 ) . '|' . absint( $num2 ) );
	}

	private static function verify_captcha() {
		$security = qa_cf_security_settings();
		if ( empty( $security['captcha_enabled'] ) ) {
			return true;
		}

		$num1   = isset( $_POST['qa_cf_captcha_n1'] ) ? absint( $_POST['qa_cf_captcha_n1'] ) : -1;
		$num2   = isset( $_POST['qa_cf_captcha_n2'] ) ? absint( $_POST['qa_cf_captcha_n2'] ) : -1;
		$sig    = isset( $_POST['qa_cf_captcha_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['qa_cf_captcha_sig'] ) ) : '';
		$answer = isset( $_POST['qa_cf_captcha_answer'] ) ? (int) qa_cf_normalize_digits( sanitize_text_field( wp_unslash( $_POST['qa_cf_captcha_answer'] ) ) ) : -1;

		if ( ! hash_equals( self::captcha_sig( $num1, $num2 ), $sig ) ) {
			return false;
		}

		return ( $num1 + $num2 ) === $answer;
	}

	/**
	 * تله زمانی — فاصله بین تولید فرم و ارسال آن.
	 */
	private static function verify_timing() {
		$security    = qa_cf_security_settings();
		$min_seconds = (int) apply_filters( 'qa_cf_min_fill_seconds', absint( $security['min_fill_seconds'] ) );

		if ( $min_seconds < 1 ) {
			return true;
		}

		$ts  = isset( $_POST['qa_cf_ts'] ) ? absint( $_POST['qa_cf_ts'] ) : 0;
		$sig = isset( $_POST['qa_cf_ts_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['qa_cf_ts_sig'] ) ) : '';

		if ( ! $ts || ! hash_equals( wp_hash( 'qa_cf_ts|' . $ts ), $sig ) ) {
			return false;
		}

		$elapsed = time() - $ts;
		return ( $elapsed < 0 || $elapsed >= $min_seconds );
	}

	private static function is_blocked_ip() {
		$security = qa_cf_security_settings();
		$raw      = trim( (string) $security['blocked_ips'] );
		if ( '' === $raw ) {
			return false;
		}

		$ip = QA_CF_DB::get_client_ip();
		if ( '' === $ip ) {
			return false;
		}

		$list = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw ) ) );
		foreach ( $list as $item ) {
			if ( $item === $ip ) {
				return true;
			}
			// پشتیبانی از الگوی ساده‌ی پیشوندی مثل 192.168.*
			if ( false !== strpos( $item, '*' ) ) {
				$pattern = '/^' . str_replace( '\*', '.*', preg_quote( $item, '/' ) ) . '$/';
				if ( preg_match( $pattern, $ip ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function has_blocked_word( $text ) {
		$security = qa_cf_security_settings();
		$raw      = trim( (string) $security['blocked_words'] );
		if ( '' === $raw ) {
			return false;
		}

		$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$words = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw ) ) );

		foreach ( $words as $word ) {
			$word = function_exists( 'mb_strtolower' ) ? mb_strtolower( $word ) : strtolower( $word );
			if ( '' !== $word && false !== strpos( $text, $word ) ) {
				return true;
			}
		}
		return false;
	}

	private static function is_rate_limited() {
		$security = qa_cf_security_settings();
		$max      = (int) apply_filters( 'qa_cf_rate_limit_max', absint( $security['rate_limit_max'] ) );
		$window   = (int) apply_filters( 'qa_cf_rate_limit_window', absint( $security['rate_limit_window'] ) );

		if ( $max <= 0 || $window <= 0 ) {
			return false;
		}

		$ip = QA_CF_DB::get_client_ip();
		if ( '' === $ip ) {
			return false;
		}

		$count = (int) get_transient( self::rate_limit_key( $ip ) );

		return ( $count >= $max );
	}

	private static function rate_limit_key( $ip ) {
		$extra = (string) apply_filters( 'qa_cf_rate_limit_key_extra', '' );
		return 'qa_cf_rl_' . md5( $ip . '|' . $extra );
	}

	/**
	 * شمارنده‌ی محدودیت نرخ فقط بعد از یک ارسال «واقعاً موفق» یک واحد
	 * بالا می‌رود. در نسخه‌های قبل هر بار صدا زدن هندلر (حتی وقتی کاربر
	 * فقط یک فیلد را اشتباه پر کرده بود) شمارنده را زیاد می‌کرد و کاربر
	 * واقعی بعد از چند بار اصلاح خطا، خودش مسدود می‌شد.
	 */
	private static function bump_rate_limit() {
		$security = qa_cf_security_settings();
		$window   = (int) apply_filters( 'qa_cf_rate_limit_window', absint( $security['rate_limit_window'] ) );
		$ip       = QA_CF_DB::get_client_ip();

		if ( '' === $ip || $window <= 0 ) {
			return;
		}

		$key   = self::rate_limit_key( $ip );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, $window );
	}

	/**
	 * پردازش ارسال فرم (AJAX).
	 *
	 * برخلاف نسخه‌های قبل، این متد هرگز با خروجی غیر-JSON خاتمه نمی‌یابد:
	 * بررسی نانس دستی انجام می‌شود تا در صورت انقضا (صفحه‌ی کش‌شده) به‌جای
	 * خروجی «-1» که سمت جاوااسکریپت باعث خطای پارس و پیام مبهم می‌شد، یک
	 * پاسخ JSON با کد bad_nonce برگردد و مرورگر خودش با نانس تازه یک‌بار
	 * دوباره تلاش کند.
	 */
	public static function handle_submit() {
		$nonce = isset( $_POST['qa_cf_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qa_cf_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'qa_cf_submit' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'bad_nonce',
					'message' => 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.',
				)
			);
		}

		$form     = qa_cf_form_settings();
		$security = qa_cf_security_settings();
		$silent   = array( 'message' => $form['success_msg'] );

		if ( self::is_blocked_ip() ) {
			wp_send_json_success( $silent );
		}

		if ( self::is_rate_limited() ) {
			wp_send_json_error( array( 'message' => 'تعداد درخواست‌های شما زیاد بوده است. لطفاً چند دقیقه دیگر دوباره تلاش کنید.' ) );
		}

		// هانی‌پات و تله زمانی: بی‌صدا «موفق» نشان داده می‌شوند تا بات متوجه
		// مسدود شدن نشود و برای دور زدن تلاش نکند.
		if ( ! empty( $_POST['qa_cf_hp'] ) ) {
			wp_send_json_success( $silent );
		}
		if ( ! self::verify_timing() ) {
			wp_send_json_success( $silent );
		}

		if ( ! self::verify_captcha() ) {
			wp_send_json_error(
				array(
					'message' => 'پاسخ سؤال امنیتی درست نیست.',
					'errors'  => array( 'qa_cf_captcha' => 'پاسخ درست نیست؛ دوباره محاسبه کنید.' ),
				)
			);
		}

		if ( apply_filters( 'qa_cf_is_spam', false, $_POST ) ) {
			wp_send_json_success( $silent );
		}

		if ( ! empty( $form['consent_enabled'] ) && ! empty( $form['consent_required'] ) && empty( $_POST['qa_cf_consent'] ) ) {
			wp_send_json_error(
				array(
					'message' => 'برای ارسال فرم باید با شرایط موافقت کنید.',
					'errors'  => array( 'qa_cf_consent' => 'تأیید این مورد الزامی است.' ),
				)
			);
		}

		$fields = qa_cf_fields();
		if ( empty( $fields ) ) {
			wp_send_json_error( array( 'message' => 'فرم در دسترس نیست.' ) );
		}

		do_action( 'qa_cf_before_submit' );

		$collected  = array();
		$errors     = array();
		$file_queue = array();
		$all_text   = '';

		foreach ( $fields as $field ) {
			$key  = sanitize_key( $field['key'] );
			$type = array_key_exists( $field['type'], QA_CF_DB::field_types() ) ? $field['type'] : 'text';

			if ( '' === $key || 'heading' === $type ) {
				continue;
			}

			$label    = '' !== $field['label'] ? $field['label'] : $key;
			$required = ! empty( $field['required'] );
			$max_len  = QA_CF_DB::field_max_length( $type );
			$rule     = array_key_exists( $field['validate'], QA_CF_DB::validate_rules() ) ? $field['validate'] : 'none';

			/* ── فایل ── */
			if ( 'file' === $type ) {
				$has_file = isset( $_FILES[ $key ] ) && isset( $_FILES[ $key ]['error'] ) && UPLOAD_ERR_NO_FILE !== $_FILES[ $key ]['error'];
				if ( ! $has_file ) {
					if ( $required ) {
						$errors[ $key ] = 'انتخاب فایل الزامی است.';
					}
					continue;
				}
				$file_queue[ $key ] = array( 'label' => $label );
				continue;
			}

			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

			/* ── چند انتخابی ── */
			if ( 'checkbox_group' === $type ) {
				$allowed  = self::option_values( $field['options'] );
				$selected = array();
				foreach ( (array) $raw as $item ) {
					$item = qa_cf_safe_substr( sanitize_text_field( $item ), 190 );
					if ( in_array( $item, $allowed, true ) ) {
						$selected[] = $item;
					}
				}
				if ( $required && empty( $selected ) ) {
					$errors[ $key ] = 'انتخاب حداقل یک گزینه الزامی است.';
				}
				if ( ! empty( $selected ) ) {
					$collected[ $key ] = array( 'label' => $label, 'value' => $selected, 'type' => $type );
					$all_text         .= ' ' . implode( ' ', $selected );
				}
				continue;
			}

			$value = is_array( $raw ) ? '' : (string) $raw;
			$value = qa_cf_normalize_text( $value );

			if ( ! empty( $form['normalize_digits'] ) && in_array( $type, array( 'tel', 'number', 'date' ), true ) ) {
				$value = qa_cf_normalize_digits( $value );
			}
			if ( ! empty( $form['normalize_digits'] ) && in_array( $rule, array( 'iran_mobile', 'iran_phone', 'national_code', 'postal_code', 'numeric' ), true ) ) {
				$value = qa_cf_normalize_digits( $value );
			}

			switch ( $type ) {
				case 'email':
					$value = qa_cf_safe_substr( sanitize_email( $value ), $max_len );
					if ( $required && '' === $value ) {
						$errors[ $key ] = $label . ' الزامی است.';
					} elseif ( '' !== $value && ! is_email( $value ) ) {
						$errors[ $key ] = 'ایمیل واردشده معتبر نیست.';
					}
					break;

				case 'textarea':
					$value = qa_cf_safe_substr( sanitize_textarea_field( $value ), $max_len );
					if ( $required && '' === trim( $value ) ) {
						$errors[ $key ] = $label . ' الزامی است.';
					}
					break;

				case 'checkbox':
					$value = ! empty( $value ) ? 'بله' : '';
					if ( $required && '' === $value ) {
						$errors[ $key ] = 'تأیید «' . $label . '» الزامی است.';
					}
					break;

				case 'select':
				case 'radio':
					$value   = qa_cf_safe_substr( sanitize_text_field( $value ), $max_len );
					$allowed = self::option_values( $field['options'] );
					if ( '' !== $value && ! empty( $allowed ) && ! in_array( $value, $allowed, true ) ) {
						$value          = '';
						$errors[ $key ] = 'گزینه انتخاب‌شده معتبر نیست.';
					}
					if ( $required && '' === $value && ! isset( $errors[ $key ] ) ) {
						$errors[ $key ] = $label . ' الزامی است.';
					}
					break;

				case 'url':
					$value = qa_cf_safe_substr( esc_url_raw( $value ), $max_len );
					if ( $required && '' === $value ) {
						$errors[ $key ] = $label . ' الزامی است.';
					}
					break;

				case 'number':
					$value = qa_cf_safe_substr( sanitize_text_field( $value ), $max_len );
					if ( $required && '' === trim( $value ) ) {
						$errors[ $key ] = $label . ' الزامی است.';
					} elseif ( '' !== $value && ! is_numeric( $value ) ) {
						$errors[ $key ] = $label . ' باید عدد باشد.';
					} else {
						if ( '' !== $value && '' !== $field['min'] && is_numeric( $field['min'] ) && (float) $value < (float) $field['min'] ) {
							$errors[ $key ] = $label . ' نباید کمتر از ' . $field['min'] . ' باشد.';
						}
						if ( '' !== $value && '' !== $field['max'] && is_numeric( $field['max'] ) && (float) $value > (float) $field['max'] ) {
							$errors[ $key ] = $label . ' نباید بیشتر از ' . $field['max'] . ' باشد.';
						}
					}
					break;

				default:
					$value = qa_cf_safe_substr( sanitize_text_field( $value ), $max_len );
					if ( $required && '' === trim( $value ) ) {
						$errors[ $key ] = $label . ' الزامی است.';
					}
					break;
			}

			// قوانین اعتبارسنجی اضافه (موبایل/کدملی/کدپستی و…)
			if ( ! isset( $errors[ $key ] ) && '' !== trim( (string) $value ) && 'none' !== $rule ) {
				$check = QA_CF_Validator::check( $rule, $value, $label );
				if ( true !== $check ) {
					$errors[ $key ] = $check;
				} elseif ( 'iran_mobile' === $rule ) {
					$value = QA_CF_Validator::normalize_mobile( $value );
				}
			}

			// حداقل/حداکثر طول (برای فیلدهای متنی)
			if ( ! isset( $errors[ $key ] ) && in_array( $type, array( 'text', 'textarea' ), true ) && '' !== trim( (string) $value ) ) {
				$len = qa_cf_safe_strlen( $value );
				if ( absint( $field['min'] ) > 0 && $len < absint( $field['min'] ) ) {
					$errors[ $key ] = sprintf( '%s باید حداقل %s نویسه باشد.', $label, qa_cf_fa_num( absint( $field['min'] ) ) );
				} elseif ( absint( $field['max'] ) > 0 && $len > absint( $field['max'] ) ) {
					$errors[ $key ] = sprintf( '%s باید حداکثر %s نویسه باشد.', $label, qa_cf_fa_num( absint( $field['max'] ) ) );
				}
			}

			if ( '' !== trim( (string) $value ) ) {
				$collected[ $key ] = array( 'label' => $label, 'value' => $value, 'type' => $type );
				$all_text         .= ' ' . $value;
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => $form['required_msg'],
					'errors'  => $errors,
				)
			);
		}

		// کلمات مسدود — مثل هانی‌پات بی‌صدا رد می‌شود.
		if ( self::has_blocked_word( $all_text ) ) {
			wp_send_json_success( $silent );
		}

		/* ── آپلود فایل‌ها (فقط بعد از موفق‌بودن بقیه اعتبارسنجی‌ها تا فایل
		 *    یتیم روی هاست باقی نماند) ── */
		$attachments = array();

		foreach ( $file_queue as $key => $meta ) {
			$result = QA_CF_Uploads::handle( $_FILES[ $key ] ); // phpcs:ignore

			if ( is_wp_error( $result ) ) {
				// پاک‌سازی فایل‌هایی که در همین ارسال آپلود شده‌اند.
				foreach ( $attachments as $done ) {
					if ( file_exists( $done ) ) {
						@unlink( $done ); // phpcs:ignore
					}
				}
				wp_send_json_error(
					array(
						'message' => $result->get_error_message() ? $result->get_error_message() : 'آپلود فایل ناموفق بود.',
						'errors'  => array( $key => $result->get_error_message() ),
					)
				);
			}

			$collected[ $key ] = array(
				'label' => $meta['label'],
				'value' => $result['name'],
				'type'  => 'file',
				'url'   => $result['url'],
				'path'  => $result['path'],
			);
			$attachments[] = $result['path'];
		}

		if ( ! empty( $form['consent_enabled'] ) && ! empty( $_POST['qa_cf_consent'] ) ) {
			$collected['qa_cf_consent'] = array(
				'label' => 'تأیید شرایط و قوانین',
				'value' => 'بله',
				'type'  => 'checkbox',
			);
		}

		if ( empty( $collected ) ) {
			wp_send_json_error( array( 'message' => $form['required_msg'] ) );
		}

		$collected = apply_filters( 'qa_cf_submission_fields', $collected );

		/* ── ذخیره و اعلان‌ها ── */
		$entry_id = 0;

		if ( ! empty( $security['store_entries'] ) ) {
			$entry_id = QA_CF_DB::insert_entry( $collected );

			if ( false === $entry_id ) {
				wp_send_json_error( array( 'message' => $form['error_msg'] ) );
			}
		}

		$mailed = QA_CF_Mailer::send_entry_notification( $collected, $attachments );

		// اگر ذخیره‌سازی خاموش است و ایمیل هم ارسال نشد، پیام موفقیت دروغ نگو.
		if ( empty( $security['store_entries'] ) && ! $mailed ) {
			wp_send_json_error( array( 'message' => $form['error_msg'] ) );
		}

		QA_CF_Mailer::send_autoreply( $collected );
		QA_CF_Notify::dispatch( $collected, $entry_id );

		self::bump_rate_limit();

		do_action( 'qa_cf_after_submit', $entry_id, $collected );

		$response = array( 'message' => $form['success_msg'] );

		if ( 'redirect' === $form['after_submit'] && ! empty( $form['redirect_url'] ) ) {
			$response['redirect'] = esc_url_raw( $form['redirect_url'] );
		}

		wp_send_json_success( $response );
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۱۱) استایل و اسکریپت پیشخوان
 * ══════════════════════════════════════════════════════════════════════ */

function qa_cf_admin_css() {
	return <<<'CSS'
.qa-cf-admin{--qa-p:#0c8a85;--qa-a:#0ea5a0;--qa-bd:#e2e8f0;--qa-bg:#f6f8fa;--qa-txt:#0f172a;--qa-mut:#64748b;max-width:1400px}
.qa-cf-admin *{box-sizing:border-box}
.qa-cf-admin .qa-cf-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;background:linear-gradient(135deg,#0c8a85,#0ea5a0);color:#fff;border-radius:16px;padding:20px 24px;margin:16px 0 0;box-shadow:0 10px 30px rgba(12,138,133,.22)}
.qa-cf-admin .qa-cf-hero h1{color:#fff;margin:0;font-size:20px;font-weight:800;display:flex;align-items:center;gap:10px}
.qa-cf-admin .qa-cf-hero p{margin:6px 0 0;opacity:.9;font-size:13px}
.qa-cf-admin .qa-cf-hero .dashicons{font-size:26px;width:26px;height:26px}
.qa-cf-shortcode-box{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.35);color:#fff;border-radius:10px;padding:9px 14px;cursor:pointer;font-family:inherit;font-size:13px;transition:background .2s}
.qa-cf-shortcode-box:hover{background:rgba(255,255,255,.28)}
.qa-cf-shortcode-box code{background:transparent;color:#fff;font-size:13px;direction:ltr}
.qa-cf-copy-label{font-size:11px;opacity:.85;border-right:1px solid rgba(255,255,255,.4);padding-right:8px}

.qa-cf-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0}
.qa-cf-stat{background:#fff;border:1px solid var(--qa-bd);border-radius:12px;padding:14px 16px}
.qa-cf-stat b{display:block;font-size:24px;font-weight:800;color:var(--qa-p);line-height:1.4}
.qa-cf-stat span{font-size:12px;color:var(--qa-mut)}

.qa-cf-admin .qa-cf-tabs{margin:18px 0 0;border-bottom:1px solid var(--qa-bd);display:flex;flex-wrap:wrap;gap:4px;padding:0}
.qa-cf-admin .qa-cf-tabs .nav-tab{display:inline-flex;align-items:center;gap:6px;border-radius:10px 10px 0 0;margin:0;font-weight:600;font-size:13.5px}
.qa-cf-admin .qa-cf-tabs .nav-tab .dashicons{font-size:17px;width:17px;height:17px}
.qa-cf-admin .qa-cf-tabs .nav-tab-active{background:#fff;border-bottom-color:#fff;color:var(--qa-p)}

.qa-cf-card{background:#fff;border:1px solid var(--qa-bd);border-radius:14px;padding:22px 24px;margin-top:18px;box-shadow:0 1px 3px rgba(15,23,42,.04)}
.qa-cf-card>h2:first-child{margin-top:0}
.qa-cf-section-title{font-size:15px;font-weight:800;margin:26px 0 8px;padding-bottom:8px;border-bottom:1px dashed var(--qa-bd);display:flex;align-items:center;gap:8px}
.qa-cf-section-title:first-of-type{margin-top:0}
.qa-cf-admin hr{border:0;border-top:1px solid var(--qa-bd);margin:26px 0}
.qa-cf-notice-box{background:#f0fdfc;border:1px solid rgba(14,165,160,.35);border-right:4px solid var(--qa-a);border-radius:10px;padding:12px 16px;margin:12px 0;font-size:13px;line-height:2}
.qa-cf-warn-box{background:#fffbeb;border:1px solid #fde68a;border-right:4px solid #f59e0b;border-radius:10px;padding:12px 16px;margin:12px 0;font-size:13px;line-height:2}

/* فیلدساز */
.qa-cf-field-rows{display:flex;flex-direction:column;gap:10px;margin:14px 0}
.qa-cf-field-row{background:#fff;border:1px solid var(--qa-bd);border-radius:12px;transition:box-shadow .2s,border-color .2s}
.qa-cf-field-row:hover{border-color:#cbd5e1;box-shadow:0 2px 10px rgba(15,23,42,.06)}
.qa-cf-field-row.is-dragging{opacity:.45;border-style:dashed}
.qa-cf-row-head{display:flex;align-items:center;gap:10px;padding:10px 12px;flex-wrap:wrap}
.qa-cf-drag-handle{cursor:grab;color:#94a3b8;flex-shrink:0}
.qa-cf-drag-handle:active{cursor:grabbing}
.qa-cf-row-head .qacf-f-label{flex:1 1 200px;min-width:160px}
.qa-cf-row-head .qacf-f-type{flex:0 0 160px}
.qa-cf-row-head input[type=text],.qa-cf-row-head select{height:34px}
.qa-cf-req-toggle{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;color:var(--qa-mut);white-space:nowrap}
.qa-cf-row-actions{display:flex;gap:4px;margin-right:auto}
.qa-cf-row-actions .button-link{padding:4px;color:#94a3b8;text-decoration:none}
.qa-cf-row-actions .button-link:hover{color:var(--qa-p)}
.qa-cf-row-actions .qa-cf-remove-field:hover{color:#dc2626}
.qa-cf-row-body{display:none;padding:4px 14px 16px;border-top:1px dashed var(--qa-bd);margin-top:2px}
.qa-cf-field-row.is-open .qa-cf-row-body{display:block}
.qa-cf-row-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px 16px;margin-top:12px}
.qa-cf-row-grid label{display:flex;flex-direction:column;gap:4px;font-size:12.5px;font-weight:600;color:#334155}
.qa-cf-row-grid input,.qa-cf-row-grid select,.qa-cf-row-grid textarea{width:100%;font-size:13px}
.qa-cf-row-grid .qacf-col-2{grid-column:span 2}
.qa-cf-empty{padding:26px;text-align:center;color:var(--qa-mut);border:2px dashed var(--qa-bd);border-radius:12px}

/* پیش‌تنظیم‌های طراحی */
.qa-cf-presets{display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 18px}
.qa-cf-preset{display:flex;align-items:center;gap:8px;border:1px solid var(--qa-bd);background:#fff;border-radius:10px;padding:8px 12px;cursor:pointer;font-size:12.5px;font-family:inherit;transition:border-color .2s,transform .15s}
.qa-cf-preset:hover{border-color:var(--qa-a);transform:translateY(-1px)}
.qa-cf-preset .qa-cf-dots{display:inline-flex;gap:3px}
.qa-cf-preset i{width:13px;height:13px;border-radius:50%;display:block}

.qa-cf-style-layout{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);gap:18px;align-items:start}
@media (max-width:1100px){.qa-cf-style-layout{grid-template-columns:1fr}}
.qa-cf-style-preview{position:sticky;top:42px}
.qa-cf-preview-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.qa-cf-preview-frame{border:1px solid var(--qa-bd);border-radius:14px;padding:22px;background:#fff;overflow:auto;transition:max-width .25s ease;margin:0 auto;max-width:100%}
.qa-cf-preview-frame.is-mobile{max-width:380px}
.qa-cf-preview-frame.is-dark{background:#0b1220}

.qa-cf-segmented{display:inline-flex;background:#f1f5f9;border-radius:10px;padding:3px;gap:2px}
.qa-cf-segmented-opt{position:relative;display:inline-flex}
.qa-cf-segmented-opt input{position:absolute;opacity:0;inset:0;cursor:pointer}
.qa-cf-segmented-opt span,.qa-cf-device-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:12.5px;cursor:pointer;border:0;background:transparent;font-family:inherit;color:#475569;transition:background .18s,color .18s}
.qa-cf-segmented-opt input:checked+span,.qa-cf-device-btn.is-active{background:#fff;color:var(--qa-p);font-weight:700;box-shadow:0 1px 3px rgba(15,23,42,.1)}
.qa-cf-device-btn .dashicons{font-size:15px;width:15px;height:15px}

.qa-cf-range-val{display:inline-block;min-width:52px;font-size:12px;color:var(--qa-mut);margin-right:8px}
.qa-cf-admin .form-table th{width:220px;font-size:13.5px}
.qa-cf-admin .form-table td .description{font-size:12.5px;line-height:1.9}
.qa-cf-toggle-heading{display:inline-flex;align-items:center;gap:8px;font-size:15px;font-weight:800;cursor:pointer}
.qa-cf-test-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
#qa-cf-test-email-result{font-size:13px}
.qa-cf-ok-text{color:#15803d;font-weight:600}
.qa-cf-err-text{color:#b91c1c;font-weight:600}

.qa-cf-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:700}
.qa-cf-badge-unread{background:#fef3c7;color:#92400e}
.qa-cf-badge-read{background:#dcfce7;color:#166534}
.qa-cf-entries-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.qa-cf-filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:12.5px}
.qa-cf-filters input[type=date]{height:32px}
.qa-cf-entry-table th{background:#f8fafc;width:220px}
.qa-cf-inline-delete-form{display:inline}
.qa-cf-inline-delete-form .button-link{color:#b91c1c;text-decoration:none}
.qa-cf-sticky-save{position:sticky;bottom:0;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);border-top:1px solid var(--qa-bd);padding:12px 0;margin-top:22px;z-index:5;display:flex;gap:12px;align-items:center}
.qa-cf-sticky-save .button-primary{min-width:180px}
.qa-cf-hint{font-size:12px;color:var(--qa-mut)}
.qa-cf-tools-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
.qa-cf-tool{border:1px solid var(--qa-bd);border-radius:12px;padding:16px}
.qa-cf-tool h3{margin:0 0 6px;font-size:14px}
.qa-cf-tool p{margin:0 0 12px;font-size:12.5px;color:var(--qa-mut);line-height:1.9}
.qa-cf-danger{border-color:#fecaca;background:#fef2f2}
CSS;
}

function qa_cf_admin_js() {
	return <<<'JS'
(function () {
	'use strict';

	var CFG = window.QA_CF_ADMIN || {};

	document.addEventListener('DOMContentLoaded', function () {
		disablePreviewSubmit();
		initFieldBuilder();
		initDesign();
		initToggles();
		initTestEmail();
		initCopyShortcode();
		initColorPickers();
		initConfirms();
	});

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

	function disablePreviewSubmit() {
		$$('#qa-cf-preview-frame form').forEach(function (form) {
			form.addEventListener('submit', function (e) { e.preventDefault(); });
		});
	}

	/* ─────────────── فیلدساز ─────────────── */

	function initFieldBuilder() {
		var container = $('#qa-cf-field-rows');
		var tpl = $('#qa-cf-field-row-template');
		if (!container || !tpl) { return; }

		var fields = window.QA_CF_FIELDS || [];
		fields.forEach(function (f) { addRow(container, f); });
		refreshEmpty(container);

		var addBtn = $('#qa-cf-add-field');
		if (addBtn) {
			addBtn.addEventListener('click', function () {
				var row = addRow(container, { type: 'text', width: 'half', required: 0, rows: 5, validate: 'none' });
				row.classList.add('is-open');
				var labelInput = $('.qacf-f-label', row);
				if (labelInput) { labelInput.focus(); }
				refreshEmpty(container);
				row.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		}

		var form = $('#qa-cf-fields-form');
		if (form) {
			form.addEventListener('submit', function (e) {
				var data = collect(container);
				if (!data.length) {
					e.preventDefault();
					window.alert('حداقل یک فیلد برای فرم لازم است.');
					return;
				}
				$('#qa-cf-fields-json').value = JSON.stringify(data);
			});
		}
	}

	function refreshEmpty(container) {
		var empty = $('#qa-cf-fields-empty');
		if (empty) { empty.style.display = container.children.length ? 'none' : 'block'; }
	}

	function addRow(container, field) {
		field = field || {};
		var tpl = $('#qa-cf-field-row-template');
		var frag = document.createElement('div');
		frag.innerHTML = tpl.innerHTML.trim();
		var row = frag.firstElementChild;

		set(row, '.qacf-f-label', field.label || '');
		set(row, '.qacf-f-key', field.key || '');
		set(row, '.qacf-f-type', field.type || 'text');
		set(row, '.qacf-f-width', field.width || 'half');
		set(row, '.qacf-f-placeholder', field.placeholder || '');
		set(row, '.qacf-f-default', field.default || '');
		set(row, '.qacf-f-help', field.help || '');
		set(row, '.qacf-f-options', field.options || '');
		set(row, '.qacf-f-validate', field.validate || 'none');
		set(row, '.qacf-f-min', field.min || '');
		set(row, '.qacf-f-max', field.max || '');
		set(row, '.qacf-f-rows', field.rows || 5);
		set(row, '.qacf-f-class', field.css_class || '');
		set(row, '.qacf-f-autocomplete', field.autocomplete || '');

		var req = $('.qacf-f-required', row);
		if (req) { req.checked = !!Number(field.required); }

		container.appendChild(row);
		bindRow(row, container);
		syncRowUi(row);
		return row;
	}

	function set(row, sel, value) {
		var el = $(sel, row);
		if (el) { el.value = value; }
	}
	function get(row, sel) {
		var el = $(sel, row);
		return el ? el.value : '';
	}

	function bindRow(row, container) {
		var typeSelect = $('.qacf-f-type', row);
		if (typeSelect) { typeSelect.addEventListener('change', function () { syncRowUi(row); }); }

		var labelInput = $('.qacf-f-label', row);
		var keyInput = $('.qacf-f-key', row);
		if (labelInput && keyInput) {
			labelInput.addEventListener('blur', function () {
				if (!keyInput.value.trim() && labelInput.value.trim()) {
					keyInput.value = slugify(labelInput.value);
				}
				var title = $('.qa-cf-row-title', row);
				if (title) { title.textContent = labelInput.value; }
			});
		}

		var toggle = $('.qa-cf-toggle-row', row);
		if (toggle) {
			toggle.addEventListener('click', function () { row.classList.toggle('is-open'); });
		}

		var dup = $('.qa-cf-dup-field', row);
		if (dup) {
			dup.addEventListener('click', function () {
				var copy = collectRow(row);
				copy.key = '';
				copy.label = copy.label + ' (کپی)';
				var newRow = addRow(container, copy);
				container.insertBefore(newRow, row.nextSibling);
			});
		}

		var del = $('.qa-cf-remove-field', row);
		if (del) {
			del.addEventListener('click', function () {
				if (window.confirm(CFG.confirm_del || 'این فیلد حذف شود؟')) {
					row.remove();
					refreshEmpty(container);
				}
			});
		}

		row.addEventListener('dragstart', function () { row.classList.add('is-dragging'); });
		row.addEventListener('dragend', function () { row.classList.remove('is-dragging'); });
	}

	function syncRowUi(row) {
		var type = get(row, '.qacf-f-type');
		var withOptions = (type === 'select' || type === 'radio' || type === 'checkbox_group');
		show($('.qacf-wrap-options', row), withOptions);
		show($('.qacf-wrap-placeholder', row), ['text', 'email', 'tel', 'number', 'url', 'textarea', 'select'].indexOf(type) > -1);
		show($('.qacf-wrap-validate', row), ['text', 'tel', 'number', 'textarea', 'url'].indexOf(type) > -1);
		show($('.qacf-wrap-rows', row), type === 'textarea');
		show($('.qacf-wrap-minmax', row), ['text', 'textarea', 'number'].indexOf(type) > -1);
		show($('.qacf-wrap-default', row), ['text', 'email', 'tel', 'number', 'url', 'textarea', 'select', 'radio'].indexOf(type) > -1);
		show($('.qacf-wrap-autocomplete', row), ['text', 'email', 'tel', 'url'].indexOf(type) > -1);
		show($('.qacf-f-required-wrap', row), type !== 'heading');

		var title = $('.qa-cf-row-title', row);
		if (title) { title.textContent = get(row, '.qacf-f-label'); }
	}

	function show(el, visible) {
		if (el) { el.style.display = visible ? '' : 'none'; }
	}

	function slugify(text) {
		var map = { 'آ': 'a', 'ا': 'a', 'ب': 'b', 'پ': 'p', 'ت': 't', 'ث': 's', 'ج': 'j', 'چ': 'ch', 'ح': 'h', 'خ': 'kh', 'د': 'd', 'ذ': 'z', 'ر': 'r', 'ز': 'z', 'ژ': 'zh', 'س': 's', 'ش': 'sh', 'ص': 's', 'ض': 'z', 'ط': 't', 'ظ': 'z', 'ع': 'a', 'غ': 'gh', 'ف': 'f', 'ق': 'gh', 'ک': 'k', 'گ': 'g', 'ل': 'l', 'م': 'm', 'ن': 'n', 'و': 'v', 'ه': 'h', 'ی': 'y' };
		var out = String(text).trim().toLowerCase().split('').map(function (ch) {
			return map[ch] !== undefined ? map[ch] : ch;
		}).join('');
		out = out.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
		return out || ('field_' + Date.now().toString().slice(-5));
	}

	function collectRow(row) {
		return {
			key: get(row, '.qacf-f-key'),
			label: get(row, '.qacf-f-label'),
			type: get(row, '.qacf-f-type'),
			width: get(row, '.qacf-f-width'),
			placeholder: get(row, '.qacf-f-placeholder'),
			'default': get(row, '.qacf-f-default'),
			help: get(row, '.qacf-f-help'),
			options: get(row, '.qacf-f-options'),
			validate: get(row, '.qacf-f-validate'),
			min: get(row, '.qacf-f-min'),
			max: get(row, '.qacf-f-max'),
			rows: get(row, '.qacf-f-rows'),
			css_class: get(row, '.qacf-f-class'),
			autocomplete: get(row, '.qacf-f-autocomplete'),
			required: $('.qacf-f-required', row) && $('.qacf-f-required', row).checked ? 1 : 0
		};
	}

	function collect(container) {
		var out = [];
		$$('.qa-cf-field-row', container).forEach(function (row) {
			var data = collectRow(row);
			if (!data.label.trim()) { return; }
			if (!data.key.trim()) { data.key = slugify(data.label); }
			out.push(data);
		});
		return out;
	}

	document.addEventListener('dragover', function (e) {
		var container = $('#qa-cf-field-rows');
		if (!container) { return; }
		var dragging = $('.is-dragging', container);
		if (!dragging) { return; }
		e.preventDefault();
		var after = afterElement(container, e.clientY);
		if (!after) { container.appendChild(dragging); }
		else { container.insertBefore(dragging, after); }
	});

	function afterElement(container, y) {
		var rows = $$('.qa-cf-field-row:not(.is-dragging)', container);
		var closest = { offset: Number.NEGATIVE_INFINITY, el: null };
		rows.forEach(function (child) {
			var box = child.getBoundingClientRect();
			var offset = y - box.top - box.height / 2;
			if (offset < 0 && offset > closest.offset) { closest = { offset: offset, el: child }; }
		});
		return closest.el;
	}

	/* ─────────────── طراحی و پیش‌نمایش زنده ─────────────── */

	var FONTS = {
		vazir: "'Vazirmatn','Vazir',Tahoma,sans-serif",
		iransans: "'IRANSans','IRANSansWeb',Tahoma,sans-serif",
		tahoma: "Tahoma,'Segoe UI',sans-serif",
		system: "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif",
		theme: 'inherit'
	};
	var BTN_RADII = { pill: '999px', rounded: '14px', square: '4px' };
	var SIZES = { sm: ['10px', '13px'], md: ['13px', '16px'], lg: ['16px', '18px'] };

	function hexToRgba(hex, alpha) {
		hex = String(hex || '').replace('#', '');
		if (hex.length === 3) { hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2]; }
		if (hex.length !== 6) { return 'rgba(0,0,0,' + alpha + ')'; }
		var r = parseInt(hex.substr(0, 2), 16), g = parseInt(hex.substr(2, 2), 16), b = parseInt(hex.substr(4, 2), 16);
		return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
	}

	function initDesign() {
		var form = $('#qa-cf-style-form');
		var frame = $('#qa-cf-preview-frame');
		if (!form || !frame) { return; }

		function val(name) {
			var el = form.querySelector('[name="' + name + '"]');
			if (!el) { return ''; }
			if (el.type === 'checkbox') { return el.checked ? 1 : 0; }
			var radios = form.querySelectorAll('[name="' + name + '"]');
			if (radios.length > 1 && radios[0].type === 'radio') {
				for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { return radios[i].value; } }
				return '';
			}
			return el.value;
		}

		function apply() {
			var wrap = $('.qa-cf-wrap', frame);
			if (!wrap) { return; }

			var primary = val('primary_color') || '#0c8a85';
			var accent = val('accent_color') || '#0ea5a0';
			var size = SIZES[val('input_size')] || SIZES.md;

			var vars = {
				'--qa-cf-primary': primary,
				'--qa-cf-accent': accent,
				'--qa-cf-text': val('text_color'),
				'--qa-cf-label': val('label_color'),
				'--qa-cf-field-bg': val('field_bg'),
				'--qa-cf-border': val('border_color'),
				'--qa-cf-error': val('error_color'),
				'--qa-cf-success': val('success_color'),
				'--qa-cf-card-bg': val('card_bg'),
				'--qa-cf-radius': (val('radius') || 12) + 'px',
				'--qa-cf-btn-radius': BTN_RADII[val('button_shape')] || '999px',
				'--qa-cf-border-w': (val('border_width') || 1.5) + 'px',
				'--qa-cf-font': FONTS[val('font_family')] || FONTS.vazir,
				'--qa-cf-fs': (val('font_size') || 14.5) + 'px',
				'--qa-cf-pad-y': size[0],
				'--qa-cf-pad-x': size[1],
				'--qa-cf-gap': (val('gap') || 18) + 'px',
				'--qa-cf-maxwidth': val('width_mode') === 'fixed' ? (val('max_width') || 640) + 'px' : '100%',
				'--qa-cf-btn-shadow': Number(val('shadow')) ? '0 10px 26px ' + hexToRgba(primary, 0.28) : 'none',
				'--qa-cf-btn-bg': Number(val('button_gradient')) ? 'linear-gradient(135deg,' + primary + ',' + accent + ')' : primary,
				'--qa-cf-focus-ring': hexToRgba(accent, 0.16),
				'--qa-cf-hover-bd': hexToRgba(accent, 0.45),
				'--qa-cf-err-bg': hexToRgba(val('error_color') || '#ef4444', 0.07),
				'--qa-cf-ok-bg': hexToRgba(val('success_color') || '#16a34a', 0.08)
			};

			Object.keys(vars).forEach(function (k) {
				if (vars[k] !== '' && vars[k] !== undefined) { wrap.style.setProperty(k, vars[k]); }
			});

			['1', '2', '3'].forEach(function (c) { wrap.classList.remove('qa-cf-cols-' + c); });
			wrap.classList.add('qa-cf-cols-' + (val('columns') || '2'));

			['top', 'floating', 'hidden'].forEach(function (s) { wrap.classList.remove('qa-cf-label-' + s); });
			wrap.classList.add('qa-cf-label-' + (val('label_style') || 'top'));

			['none', 'fade', 'slide'].forEach(function (a) { wrap.classList.remove('qa-cf-anim-' + a); });
			wrap.classList.add('qa-cf-anim-' + (val('animation') || 'fade'));

			wrap.classList.toggle('qa-cf-card', !!Number(val('card_mode')));
			wrap.classList.toggle('qa-cf-btn-full', !!Number(val('button_full')));
			wrap.classList.toggle('qa-cf-ltr', val('direction') === 'ltr');
			wrap.setAttribute('dir', val('direction') === 'ltr' ? 'ltr' : 'rtl');
			frame.classList.toggle('is-dark', val('color_scheme') === 'dark');

			var maxRow = $('#qa-cf-maxwidth-row');
			if (maxRow) { maxRow.style.display = val('width_mode') === 'fixed' ? '' : 'none'; }
		}

		form.addEventListener('input', function (e) {
			apply();
			var out = e.target.parentNode ? e.target.parentNode.querySelector('.qa-cf-range-val') : null;
			if (out && e.target.type === 'range') { out.textContent = e.target.value + (e.target.getAttribute('data-unit') || ''); }
		});
		form.addEventListener('change', apply);

		$$('.qa-cf-preset').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var values = JSON.parse(btn.getAttribute('data-values') || '{}');
				Object.keys(values).forEach(function (name) {
					var el = form.querySelector('[name="' + name + '"]');
					if (!el) { return; }
					el.value = values[name];
					if (window.jQuery && el.classList.contains('qa-cf-color-field')) {
						window.jQuery(el).wpColorPicker('color', values[name]);
					}
				});
				apply();
			});
		});

		$$('#qa-cf-preview-device .qa-cf-device-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				$$('#qa-cf-preview-device .qa-cf-device-btn').forEach(function (b) { b.classList.remove('is-active'); });
				btn.classList.add('is-active');
				frame.classList.toggle('is-mobile', btn.getAttribute('data-device') === 'mobile');
			});
		});

		apply();
	}

	/* ─────────────── سوییچ‌های وابسته ─────────────── */

	function initToggles() {
		bindDependent('#qa-cf-smtp-enabled', '#qa-cf-smtp-fields');
		bindDependent('#qa-cf-autoreply-enabled', '#qa-cf-autoreply-fields');
		bindDependent('#qa-cf-webhook-enabled', '#qa-cf-webhook-fields');
		bindDependent('#qa-cf-telegram-enabled', '#qa-cf-telegram-fields');
		bindDependent('#qa-cf-consent-enabled', '#qa-cf-consent-fields');

		var replyMode = $('#qa-cf-reply-mode');
		if (replyMode) {
			var syncReply = function () {
				var row = $('#qa-cf-reply-fixed-row');
				if (row) { row.style.display = replyMode.value === 'fixed' ? '' : 'none'; }
			};
			replyMode.addEventListener('change', syncReply);
			syncReply();
		}

		var after = $('#qa-cf-after-submit');
		if (after) {
			var syncAfter = function () {
				$$('.qa-cf-redirect-row').forEach(function (row) {
					row.style.display = after.value === 'redirect' ? '' : 'none';
				});
			};
			after.addEventListener('change', syncAfter);
			syncAfter();
		}
	}

	function bindDependent(toggleSel, targetSel) {
		var toggle = $(toggleSel);
		var target = $(targetSel);
		if (!toggle || !target) { return; }
		var sync = function () { target.style.display = toggle.checked ? '' : 'none'; };
		toggle.addEventListener('change', sync);
		sync();
	}

	/* ─────────────── تست ایمیل ─────────────── */

	function initTestEmail() {
		var btn = $('#qa-cf-test-email-btn');
		var input = $('#qa-cf-test-email-to');
		var out = $('#qa-cf-test-email-result');
		if (!btn) { return; }

		btn.addEventListener('click', function () {
			btn.disabled = true;
			out.textContent = 'در حال ارسال…';
			out.className = '';

			var body = new FormData();
			body.append('action', 'qa_cf_test_email');
			body.append('nonce', CFG.nonce);
			body.append('to', input ? input.value : '');

			fetch(CFG.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					out.textContent = (json.data && json.data.message) ? json.data.message : '';
					out.className = json.success ? 'qa-cf-ok-text' : 'qa-cf-err-text';
				})
				.catch(function () {
					out.textContent = 'ارتباط با سرور برقرار نشد.';
					out.className = 'qa-cf-err-text';
				})
				.finally(function () { btn.disabled = false; });
		});
	}

	/* ─────────────── ابزارهای کوچک ─────────────── */

	function initCopyShortcode() {
		var btn = $('#qa-cf-copy-shortcode');
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			var text = btn.getAttribute('data-shortcode');
			var label = $('.qa-cf-copy-label', btn);
			var done = function () {
				if (label) {
					label.textContent = 'کپی شد ✓';
					setTimeout(function () { label.textContent = 'کپی'; }, 1800);
				}
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done).catch(fallback);
			} else { fallback(); }

			function fallback() {
				var ta = document.createElement('textarea');
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try { document.execCommand('copy'); done(); } catch (e) {}
				document.body.removeChild(ta);
			}
		});
	}

	function initColorPickers() {
		if (!window.jQuery || !window.jQuery.fn.wpColorPicker) { return; }
		window.jQuery('.qa-cf-color-field').each(function () {
			var $el = window.jQuery(this);
			$el.wpColorPicker({
				change: function () {
					setTimeout(function () { $el.get(0).dispatchEvent(new Event('input', { bubbles: true })); }, 30);
				},
				clear: function () {
					setTimeout(function () { $el.get(0).dispatchEvent(new Event('input', { bubbles: true })); }, 30);
				}
			});
		});
	}

	function initConfirms() {
		$$('[data-confirm]').forEach(function (el) {
			el.addEventListener('click', function (e) {
				if (!window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); }
			});
		});
		$$('form[data-confirm-submit]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				if (!window.confirm(form.getAttribute('data-confirm-submit'))) { e.preventDefault(); }
			});
		});
	}
})();
JS;
}

/* ══════════════════════════════════════════════════════════════════════
 * ۱۲) جدول پیام‌های دریافتی
 * ══════════════════════════════════════════════════════════════════════
 * کلاس WP_List_Table به‌صورت خودکار در همه درخواست‌ها بارگذاری نمی‌شود،
 * پس هم تعریف کلاس داخل is_admin() است و هم قبلش صریحاً require می‌شود.
 */

if ( is_admin() ) {

	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	class QA_CF_List_Table extends WP_List_Table {

		public function __construct() {
			parent::__construct(
				array(
					'singular' => 'entry',
					'plural'   => 'entries', // نانس bulk => bulk-entries
					'ajax'     => false,
				)
			);
		}

		public function get_columns() {
			return array(
				'cb'         => '<input type="checkbox" />',
				'summary'    => 'خلاصه پیام',
				'status'     => 'وضعیت',
				'ip_address' => 'IP',
				'created_at' => 'تاریخ دریافت',
			);
		}

		protected function get_sortable_columns() {
			return array(
				'created_at' => array( 'created_at', true ),
				'status'     => array( 'status', false ),
			);
		}

		protected function column_cb( $item ) {
			return sprintf( '<input type="checkbox" name="entry_ids[]" value="%d" />', absint( $item['id'] ) );
		}

		protected function column_status( $item ) {
			return ( 'unread' === $item['status'] )
				? '<span class="qa-cf-badge qa-cf-badge-unread">خوانده‌نشده</span>'
				: '<span class="qa-cf-badge qa-cf-badge-read">خوانده‌شده</span>';
		}

		protected function column_ip_address( $item ) {
			return ! empty( $item['ip_address'] ) ? esc_html( $item['ip_address'] ) : '<span style="color:#94a3b8">—</span>';
		}

		protected function column_created_at( $item ) {
			return esc_html( mysql2date( 'Y/m/d — H:i', $item['created_at'] ) );
		}

		protected function column_summary( $item ) {
			$rows    = QA_CF_DB::normalize_entry_rows( $item['form_data'] );
			$preview = array();

			$i = 0;
			foreach ( $rows as $row ) {
				if ( $i >= 2 ) {
					break;
				}
				$value     = is_array( $row['value'] ) ? implode( '، ', $row['value'] ) : $row['value'];
				$preview[] = '<strong>' . esc_html( $row['label'] ) . ':</strong> ' . esc_html( wp_trim_words( (string) $value, 9 ) );
				$i++;
			}

			if ( empty( $preview ) ) {
				$preview[] = '<em>بدون محتوا</em>';
			}

			$view_url = add_query_arg(
				array(
					'page'     => 'qa-cf-entries',
					'action'   => 'view',
					'entry_id' => absint( $item['id'] ),
				),
				admin_url( 'admin.php' )
			);

			/*
			 * عملیات حذف با متد POST انجام می‌شود، ولی دکمه‌اش داخل فرم
			 * فیلترها (که GET است) قرار دارد. به‌جای فرم تودرتو — که در
			 * نسخه ۱.۸ باعث شده بود دکمه حذف تکی اصلاً کار نکند، چون
			 * مرورگرها فرم داخلی را کاملاً نادیده می‌گیرند — از ویژگی
			 * استاندارد HTML5 یعنی form="..." استفاده شده: دکمه به فرم
			 * POST دیگری که بیرون این فرم است متصل می‌شود.
			 */
			$actions = array(
				'view'   => sprintf( '<a href="%s">مشاهده</a>', esc_url( $view_url ) ),
				'toggle' => sprintf(
					'<button type="submit" form="qa-cf-entry-action" name="qa_cf_toggle_status" value="%d" class="button-link">%s</button>',
					absint( $item['id'] ),
					'unread' === $item['status'] ? 'علامت‌گذاری خوانده‌شده' : 'علامت‌گذاری خوانده‌نشده'
				),
				'delete' => sprintf(
					'<button type="submit" form="qa-cf-entry-action" name="qa_cf_delete_single" value="%d" class="button-link" style="color:#b91c1c" data-confirm="این پیام برای همیشه حذف شود؟">حذف</button>',
					absint( $item['id'] )
				),
			);

			return '<a href="' . esc_url( $view_url ) . '" style="text-decoration:none;color:inherit">' . implode( ' — ', $preview ) . '</a>' . $this->row_actions( $actions );
		}

		protected function get_bulk_actions() {
			return array(
				'mark_read'   => 'علامت‌گذاری خوانده‌شده',
				'mark_unread' => 'علامت‌گذاری خوانده‌نشده',
				'delete'      => 'حذف',
			);
		}

		public function prepare_items() {
			$per_page = (int) apply_filters( 'qa_cf_entries_per_page', 20 );

			$args = array(
				'per_page' => $per_page,
				'paged'    => $this->get_pagenum(),
				'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
				'status'   => isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '',
				'from'     => isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '',
				'to'       => isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '',
				'orderby'  => isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at',
				'order'    => isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
			);

			$this->items           = QA_CF_DB::get_entries( $args );
			$total_items           = QA_CF_DB::count_entries( $args );
			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
					'total_pages' => $per_page > 0 ? (int) ceil( $total_items / $per_page ) : 1,
				)
			);
		}

		public function no_items() {
			echo 'هیچ پیامی با این فیلترها پیدا نشد.';
		}
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۱۳) پیشخوان مدیریت
 * ══════════════════════════════════════════════════════════════════════ */

class QA_CF_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		/*
		 * همه‌ی عملیات تغییردهنده‌ی وضعیت روی admin_init انجام می‌شوند، یعنی
		 * قبل از شروع چاپ صفحه‌ی پیشخوان. در نسخه‌های قبل این کارها داخل
		 * تابع رندر صفحه بود و wp_safe_redirect بعد از ارسال هدرها اجرا
		 * می‌شد؛ نتیجه‌اش این بود که ریدایرکت بی‌صدا شکست می‌خورد و در حالت
		 * WP_DEBUG هم پیام «headers already sent» ظاهر می‌شد.
		 */
		add_action( 'admin_init', array( __CLASS__, 'handle_entry_actions' ) );

		add_action( 'admin_post_qa_cf_save_fields', array( __CLASS__, 'save_fields' ) );
		add_action( 'admin_post_qa_cf_save_style', array( __CLASS__, 'save_style' ) );
		add_action( 'admin_post_qa_cf_save_email', array( __CLASS__, 'save_email' ) );
		add_action( 'admin_post_qa_cf_save_security', array( __CLASS__, 'save_security' ) );
		add_action( 'admin_post_qa_cf_save_integrations', array( __CLASS__, 'save_integrations' ) );
		add_action( 'admin_post_qa_cf_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_qa_cf_export_settings', array( __CLASS__, 'export_settings' ) );
		add_action( 'admin_post_qa_cf_import_settings', array( __CLASS__, 'import_settings' ) );
		add_action( 'admin_post_qa_cf_reset_settings', array( __CLASS__, 'reset_settings' ) );
		add_action( 'admin_post_qa_cf_purge_entries', array( __CLASS__, 'purge_entries' ) );
		add_action( 'admin_post_qa_cf_save_tools', array( __CLASS__, 'save_tools' ) );

		add_action( 'wp_ajax_qa_cf_test_email', array( __CLASS__, 'ajax_test_email' ) );
	}

	/* ───────────────────────── منو و دارایی‌ها ───────────────────────── */

	public static function tabs() {
		return array(
			'qa-cf-fields'       => array( 'label' => 'فیلدها و متن‌ها', 'icon' => 'dashicons-forms' ),
			'qa-cf-style'        => array( 'label' => 'ظاهر فرم', 'icon' => 'dashicons-art' ),
			'qa-cf-email'        => array( 'label' => 'ایمیل', 'icon' => 'dashicons-email' ),
			'qa-cf-security'     => array( 'label' => 'امنیت و حریم خصوصی', 'icon' => 'dashicons-shield' ),
			'qa-cf-integrations' => array( 'label' => 'اتصال‌ها', 'icon' => 'dashicons-admin-links' ),
			'qa-cf-entries'      => array( 'label' => 'پیام‌های دریافتی', 'icon' => 'dashicons-email-alt' ),
			'qa-cf-tools'        => array( 'label' => 'ابزارها', 'icon' => 'dashicons-admin-tools' ),
		);
	}

	public static function register_menu() {
		$cap = qa_cf_cap();

		add_menu_page( 'فرم تماس', 'فرم تماس', $cap, 'qa-cf-fields', array( __CLASS__, 'page_fields' ), 'dashicons-email-alt', 30 );

		$unread = QA_CF_DB::count_entries( array( 'status' => 'unread' ) );
		$badge  = $unread > 0 ? sprintf( ' <span class="awaiting-mod">%d</span>', $unread ) : '';

		$pages = array(
			'qa-cf-fields'       => array( 'فیلدها و متن‌ها', 'page_fields' ),
			'qa-cf-style'        => array( 'ظاهر فرم', 'page_style' ),
			'qa-cf-email'        => array( 'ایمیل', 'page_email' ),
			'qa-cf-security'     => array( 'امنیت', 'page_security' ),
			'qa-cf-integrations' => array( 'اتصال‌ها', 'page_integrations' ),
			'qa-cf-entries'      => array( 'پیام‌های دریافتی' . $badge, 'page_entries' ),
			'qa-cf-tools'        => array( 'ابزارها', 'page_tools' ),
		);

		foreach ( $pages as $slug => $data ) {
			add_submenu_page( 'qa-cf-fields', wp_strip_all_tags( $data[0] ), $data[0], $cap, $slug, array( __CLASS__, $data[1] ) );
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'qa-cf' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_register_style( 'qa-cf-admin', false, array( 'wp-color-picker' ), QA_CF_VERSION );
		wp_enqueue_style( 'qa-cf-admin' );
		wp_add_inline_style( 'qa-cf-admin', qa_cf_admin_css() . qa_cf_generate_design_css( qa_cf_design_settings(), '#qa-cf-preview-frame .qa-cf-wrap' ) . qa_cf_form_css() );

		wp_enqueue_script( 'wp-color-picker' );
		wp_register_script( 'qa-cf-admin', false, array( 'jquery', 'wp-color-picker' ), QA_CF_VERSION, true );
		wp_enqueue_script( 'qa-cf-admin' );
		wp_add_inline_script( 'qa-cf-admin', qa_cf_admin_js() );
		wp_localize_script(
			'qa-cf-admin',
			'QA_CF_ADMIN',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'qa_cf_admin_nonce' ),
				'confirm_del' => 'این فیلد حذف شود؟',
			)
		);
	}

	public static function admin_notices() {
		if ( ! isset( $_GET['page'] ) || false === strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'qa-cf' ) ) {
			return;
		}

		$messages = array(
			'saved'    => array( 'success', 'تغییرات با موفقیت ذخیره شد.' ),
			'deleted'  => array( 'success', 'پیام(های) انتخاب‌شده حذف شدند.' ),
			'updated'  => array( 'success', 'وضعیت پیام(ها) به‌روزرسانی شد.' ),
			'imported' => array( 'success', 'تنظیمات با موفقیت وارد شد.' ),
			'reset'    => array( 'success', 'تنظیمات به حالت پیش‌فرض بازگشت.' ),
			'purged'   => array( 'success', 'پیام‌های قدیمی حذف شدند.' ),
			'badfile'  => array( 'error', 'فایل واردشده معتبر نیست.' ),
			'nodata'   => array( 'warning', 'داده‌ای برای خروجی گرفتن وجود ندارد.' ),
		);

		$code = isset( $_GET['qa_cf_msg'] ) ? sanitize_key( wp_unslash( $_GET['qa_cf_msg'] ) ) : '';

		if ( $code && isset( $messages[ $code ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $code ][0] ),
				esc_html( $messages[ $code ][1] )
			);
		}

		if ( ! empty( $_GET['qa_cf_domain_warn'] ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>ایمیل فرستنده روی دامنه‌ی <code>%1$s</code> تنظیم شده که با دامنه‌ی سایت (<code>%2$s</code>) یکی نیست. احتمال افتادن ایمیل‌ها در پوشه اسپم بالا می‌رود.</p></div>',
				esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['qa_cf_domain_warn'] ) ) ) ),
				esc_html( qa_cf_site_domain() )
			);
		}

		if ( ! QA_CF_DB::table_exists() ) {
			echo '<div class="notice notice-error"><p>جدول پیام‌ها در دیتابیس ساخته نشده است. یک‌بار پلاگین را غیرفعال و دوباره فعال کنید.</p></div>';
		}

		$tg_error = get_transient( 'qa_cf_telegram_error' );
		if ( $tg_error ) {
			printf( '<div class="notice notice-warning is-dismissible"><p>آخرین اعلان تلگرام ارسال نشد: %s</p></div>', esc_html( $tg_error ) );
		}
	}

	private static function render_header( $active ) {
		$stats = QA_CF_DB::stats();
		?>
		<div class="qa-cf-hero">
			<div>
				<h1><span class="dashicons dashicons-email-alt"></span> فرم تماس کیو آرتیکل</h1>
				<p>نسخه <?php echo esc_html( qa_cf_fa_num( QA_CF_VERSION ) ); ?> — فیلدها، ظاهر، ایمیل و امنیت فرم را از همین‌جا مدیریت کن.</p>
			</div>
			<button type="button" class="qa-cf-shortcode-box" id="qa-cf-copy-shortcode" data-shortcode="[qa_contact_form]">
				<span class="dashicons dashicons-shortcode"></span> <code>[qa_contact_form]</code>
				<span class="qa-cf-copy-label">کپی</span>
			</button>
		</div>

		<div class="qa-cf-stats">
			<div class="qa-cf-stat"><b><?php echo esc_html( qa_cf_fa_num( $stats['total'] ) ); ?></b><span>کل پیام‌ها</span></div>
			<div class="qa-cf-stat"><b><?php echo esc_html( qa_cf_fa_num( $stats['unread'] ) ); ?></b><span>خوانده‌نشده</span></div>
			<div class="qa-cf-stat"><b><?php echo esc_html( qa_cf_fa_num( $stats['week'] ) ); ?></b><span>۷ روز اخیر</span></div>
			<div class="qa-cf-stat"><b><?php echo esc_html( qa_cf_fa_num( $stats['today'] ) ); ?></b><span>امروز</span></div>
		</div>

		<nav class="nav-tab-wrapper qa-cf-tabs">
			<?php foreach ( self::tabs() as $slug => $tab ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="nav-tab <?php echo $active === $slug ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span> <?php echo esc_html( $tab['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private static function open_page( $active ) {
		echo '<div class="wrap qa-cf-admin">';
		self::render_header( $active );
	}

	private static function close_page() {
		echo '</div>';
	}

	private static function sticky_save( $label = 'ذخیره تغییرات', $hint = '' ) {
		?>
		<div class="qa-cf-sticky-save">
			<button type="submit" class="button button-primary button-large"><?php echo esc_html( $label ); ?></button>
			<?php if ( $hint ) : ?>
				<span class="qa-cf-hint"><?php echo esc_html( $hint ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function guard() {
		if ( ! current_user_can( qa_cf_cap() ) ) {
			wp_die( 'شما اجازه دسترسی به این بخش را ندارید.' );
		}
	}

	private static function redirect( $page, $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	/* ───────────────── عملیات روی پیام‌ها (قبل از چاپ صفحه) ───────────────── */

	public static function handle_entry_actions() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( 'qa-cf-entries' !== $page ) {
			return;
		}
		if ( ! current_user_can( qa_cf_cap() ) ) {
			return;
		}

		$back = wp_get_referer();
		$back = $back ? remove_query_arg( array( 'action', 'action2', 'entry_ids', '_wpnonce', '_wp_http_referer', 'qa_cf_msg' ), $back ) : admin_url( 'admin.php?page=qa-cf-entries' );

		// حذف تکی (POST)
		if ( ! empty( $_POST['qa_cf_delete_single'] ) ) {
			check_admin_referer( 'qa_cf_entry_actions' );
			QA_CF_DB::delete_entries( array( absint( $_POST['qa_cf_delete_single'] ) ) );
			wp_safe_redirect( add_query_arg( 'qa_cf_msg', 'deleted', $back ) );
			exit;
		}

		// تغییر وضعیت تکی (POST)
		if ( ! empty( $_POST['qa_cf_toggle_status'] ) ) {
			check_admin_referer( 'qa_cf_entry_actions' );
			$id    = absint( $_POST['qa_cf_toggle_status'] );
			$entry = QA_CF_DB::get_entry( $id );
			if ( $entry ) {
				QA_CF_DB::update_status( $id, 'unread' === $entry['status'] ? 'read' : 'unread' );
			}
			wp_safe_redirect( add_query_arg( 'qa_cf_msg', 'updated', $back ) );
			exit;
		}

		// عملیات گروهی
		$action = '';
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( ! empty( $_REQUEST[ $key ] ) && '-1' !== $_REQUEST[ $key ] ) {
				$action = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
				break;
			}
		}

		if ( ! in_array( $action, array( 'delete', 'mark_read', 'mark_unread' ), true ) || empty( $_REQUEST['entry_ids'] ) ) {
			return;
		}

		check_admin_referer( 'bulk-entries' );

		$ids = array_filter( array_map( 'absint', (array) $_REQUEST['entry_ids'] ) );

		if ( 'delete' === $action ) {
			QA_CF_DB::delete_entries( $ids );
			$msg = 'deleted';
		} else {
			$status = ( 'mark_read' === $action ) ? 'read' : 'unread';
			foreach ( $ids as $id ) {
				QA_CF_DB::update_status( $id, $status );
			}
			$msg = 'updated';
		}

		$redirect = remove_query_arg( array( 'action', 'action2', 'entry_ids', '_wpnonce', '_wp_http_referer' ) );
		wp_safe_redirect( add_query_arg( 'qa_cf_msg', $msg, $redirect ) );
		exit;
	}

	/* ───────────────── خروجی CSV ───────────────── */

	public static function export_csv() {
		self::guard();
		check_admin_referer( 'qa_cf_export_csv' );

		$entries = QA_CF_DB::get_entries(
			array(
				'no_limit' => true,
				'status'   => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
				'from'     => isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '',
				'to'       => isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '',
				'order'    => 'DESC',
			)
		);

		if ( empty( $entries ) ) {
			self::redirect( 'qa-cf-tools', array( 'qa_cf_msg' => 'nodata' ) );
		}

		// ستون‌ها را از روی همه‌ی پیام‌ها بساز تا هیچ فیلدی جا نیفتد.
		$columns = array();
		$rowsets = array();

		foreach ( $entries as $entry ) {
			$rows = QA_CF_DB::normalize_entry_rows( $entry['form_data'] );
			$map  = array();
			foreach ( $rows as $row ) {
				$columns[ $row['key'] ] = $row['label'];
				$map[ $row['key'] ]     = is_array( $row['value'] ) ? implode( ' | ', $row['value'] ) : $row['value'];
			}
			$rowsets[ $entry['id'] ] = $map;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=qa-contact-form-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM برای نمایش درست فارسی در اکسل

		$header = array_merge( array( 'شناسه', 'تاریخ', 'وضعیت', 'IP' ), array_values( $columns ) );
		fputcsv( $out, $header );

		foreach ( $entries as $entry ) {
			$line = array(
				$entry['id'],
				mysql2date( 'Y/m/d H:i', $entry['created_at'] ),
				'unread' === $entry['status'] ? 'خوانده‌نشده' : 'خوانده‌شده',
				isset( $entry['ip_address'] ) ? $entry['ip_address'] : '',
			);
			foreach ( array_keys( $columns ) as $key ) {
				$line[] = isset( $rowsets[ $entry['id'] ][ $key ] ) ? $rowsets[ $entry['id'] ][ $key ] : '';
			}
			fputcsv( $out, $line );
		}

		fclose( $out );
		exit;
	}

	/* ═════════════════ تب ۱: فیلدها و متن‌ها ═════════════════ */

	public static function page_fields() {
		self::guard();

		$fields      = qa_cf_fields();
		$form        = qa_cf_form_settings();
		$field_types = QA_CF_DB::field_types();
		$rules       = QA_CF_DB::validate_rules();

		self::open_page( 'qa-cf-fields' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="qa-cf-fields-form">
			<?php wp_nonce_field( 'qa_cf_save_fields' ); ?>
			<input type="hidden" name="action" value="qa_cf_save_fields">
			<input type="hidden" name="fields_json" id="qa-cf-fields-json" value="">

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-forms"></span> فیلدهای فرم</h2>
				<p class="description">با کشیدن دستگیره‌ی کنار هر فیلد ترتیب را عوض کن. برای دیدن تنظیمات پیشرفته‌ی هر فیلد (اعتبارسنجی، راهنما، مقدار پیش‌فرض و…) روی آیکن تنظیمات آن کلیک کن.</p>

				<div id="qa-cf-field-rows" class="qa-cf-field-rows"></div>
				<div id="qa-cf-fields-empty" class="qa-cf-empty" style="display:none">هنوز فیلدی اضافه نشده است.</div>

				<p><button type="button" class="button button-secondary" id="qa-cf-add-field">+ افزودن فیلد جدید</button></p>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-editor-textcolor"></span> متن‌های فرم</h2>
				<table class="form-table">
					<tr>
						<th><label for="qa-cf-form-title">عنوان بالای فرم</label></th>
						<td>
							<input type="text" id="qa-cf-form-title" name="form_title" class="regular-text" value="<?php echo esc_attr( $form['form_title'] ); ?>">
							<p class="description">خالی بگذار تا عنوانی نمایش داده نشود.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-form-desc">توضیح کوتاه زیر عنوان</label></th>
						<td><input type="text" id="qa-cf-form-desc" name="form_desc" class="large-text" value="<?php echo esc_attr( $form['form_desc'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-submit-text">متن دکمه ارسال</label></th>
						<td><input type="text" id="qa-cf-submit-text" name="submit_text" class="regular-text" value="<?php echo esc_attr( $form['submit_text'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-submit-loading">متن دکمه هنگام ارسال</label></th>
						<td><input type="text" id="qa-cf-submit-loading" name="submit_loading" class="regular-text" value="<?php echo esc_attr( $form['submit_loading'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-success-msg">پیام موفقیت</label></th>
						<td><input type="text" id="qa-cf-success-msg" name="success_msg" class="large-text" value="<?php echo esc_attr( $form['success_msg'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-error-msg">پیام خطای ثبت</label></th>
						<td><input type="text" id="qa-cf-error-msg" name="error_msg" class="large-text" value="<?php echo esc_attr( $form['error_msg'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-required-msg">پیام خطای اعتبارسنجی</label></th>
						<td>
							<input type="text" id="qa-cf-required-msg" name="required_msg" class="large-text" value="<?php echo esc_attr( $form['required_msg'] ); ?>">
							<p class="description">وقتی یک یا چند فیلد درست پر نشده باشند، بالای فرم نمایش داده می‌شود (خطای دقیق هر فیلد زیر خودش می‌آید).</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-yes-alt"></span> رفتار بعد از ارسال موفق</h2>
				<table class="form-table">
					<tr>
						<th><label for="qa-cf-after-submit">بعد از ارسال چه اتفاقی بیفتد؟</label></th>
						<td>
							<select id="qa-cf-after-submit" name="after_submit">
								<option value="message" <?php selected( $form['after_submit'], 'message' ); ?>>نمایش پیام موفقیت در همان صفحه</option>
								<option value="redirect" <?php selected( $form['after_submit'], 'redirect' ); ?>>انتقال به یک صفحه دیگر (مثلاً صفحه تشکر)</option>
							</select>
						</td>
					</tr>
					<tr class="qa-cf-redirect-row">
						<th><label for="qa-cf-redirect-url">آدرس صفحه مقصد</label></th>
						<td><input type="url" id="qa-cf-redirect-url" name="redirect_url" class="regular-text" dir="ltr" placeholder="https://example.com/thank-you" value="<?php echo esc_attr( $form['redirect_url'] ); ?>"></td>
					</tr>
					<tr class="qa-cf-redirect-row">
						<th><label for="qa-cf-redirect-delay">تأخیر قبل از انتقال (ثانیه)</label></th>
						<td><input type="number" id="qa-cf-redirect-delay" name="redirect_delay" class="small-text" min="0" max="20" value="<?php echo esc_attr( $form['redirect_delay'] ); ?>"></td>
					</tr>
					<tr>
						<th>گزینه‌های تکمیلی</th>
						<td>
							<label><input type="checkbox" name="reset_on_success" value="1" <?php checked( $form['reset_on_success'], 1 ); ?>> خالی‌کردن فرم بعد از ارسال موفق</label><br>
							<label><input type="checkbox" name="hide_form_success" value="1" <?php checked( $form['hide_form_success'], 1 ); ?>> پنهان‌کردن کامل فرم بعد از ارسال موفق</label><br>
							<label><input type="checkbox" name="scroll_on_success" value="1" <?php checked( $form['scroll_on_success'], 1 ); ?>> اسکرول خودکار به پیام نتیجه</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-admin-users"></span> تجربه کاربری</h2>
				<table class="form-table">
					<tr>
						<th>کمک به کاربر هنگام پر کردن</th>
						<td>
							<label><input type="checkbox" name="live_validation" value="1" <?php checked( $form['live_validation'], 1 ); ?>> اعتبارسنجی زنده (نمایش خطای هر فیلد بلافاصله بعد از خروج از آن)</label><br>
							<label><input type="checkbox" name="char_counter" value="1" <?php checked( $form['char_counter'], 1 ); ?>> نمایش شمارنده نویسه برای فیلدهای متن بلند</label><br>
							<label><input type="checkbox" name="normalize_digits" value="1" <?php checked( $form['normalize_digits'], 1 ); ?>> تبدیل خودکار ارقام فارسی به لاتین (۰۹۱۲ ← 0912)</label><br>
							<label><input type="checkbox" name="save_draft" value="1" <?php checked( $form['save_draft'], 1 ); ?>> ذخیره موقت نوشته‌های کاربر در مرورگر خودش (اگر صفحه بسته شد، از دست نرود)</label>
						</td>
					</tr>
					<tr>
						<th>بارگذاری دارایی‌ها</th>
						<td>
							<label><input type="checkbox" name="always_load_assets" value="1" <?php checked( $form['always_load_assets'], 1 ); ?>> بارگذاری CSS/JS فرم در همه صفحات سایت</label>
							<p class="description">به‌طور پیش‌فرض، CSS و JS فقط در صفحاتی که فرم در آن‌ها هست بارگذاری می‌شوند (سریع‌تر). فقط اگر فرم را داخل پاپ‌آپ یا محتوایی می‌گذاری که با AJAX بارگذاری می‌شود، این گزینه را روشن کن.</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-privacy"></span> چک‌باکس موافقت با قوانین</h2>
				<table class="form-table">
					<tr>
						<th>فعال‌سازی</th>
						<td>
							<label><input type="checkbox" id="qa-cf-consent-enabled" name="consent_enabled" value="1" <?php checked( $form['consent_enabled'], 1 ); ?>> نمایش چک‌باکس موافقت در انتهای فرم</label>
						</td>
					</tr>
				</table>
				<table class="form-table" id="qa-cf-consent-fields">
					<tr>
						<th><label for="qa-cf-consent-text">متن موافقت</label></th>
						<td>
							<input type="text" id="qa-cf-consent-text" name="consent_text" class="large-text" value="<?php echo esc_attr( $form['consent_text'] ); ?>">
							<p class="description">می‌توانی از تگ <code>&lt;a href="..."&gt;</code> برای لینک‌دادن به صفحه قوانین استفاده کنی.</p>
						</td>
					</tr>
					<tr>
						<th>الزامی بودن</th>
						<td><label><input type="checkbox" name="consent_required" value="1" <?php checked( $form['consent_required'], 1 ); ?>> بدون تیک‌زدن این گزینه، فرم ارسال نشود</label></td>
					</tr>
				</table>
			</div>

			<?php self::sticky_save( 'ذخیره فیلدها و تنظیمات', 'شورت‌کد [qa_contact_form] را در هر صفحه‌ای قرار بده.' ); ?>
		</form>

		<script type="text/template" id="qa-cf-field-row-template">
			<?php self::field_row_template( $field_types, $rules ); ?>
		</script>
		<script>window.QA_CF_FIELDS = <?php echo wp_json_encode( array_values( $fields ) ); ?>;</script>
		<?php
		self::close_page();
	}

	private static function field_row_template( $field_types, $rules ) {
		?>
		<div class="qa-cf-field-row" draggable="true">
			<div class="qa-cf-row-head">
				<span class="qa-cf-drag-handle dashicons dashicons-move" title="جابه‌جایی"></span>
				<input type="text" class="qacf-f-label regular-text" placeholder="برچسب فیلد؛ مثلاً نام و نام‌خانوادگی">
				<select class="qacf-f-type">
					<?php foreach ( $field_types as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="qacf-f-width">
					<option value="half">نیم ردیف</option>
					<option value="full">تمام ردیف</option>
					<option value="third">یک‌سوم ردیف</option>
				</select>
				<label class="qa-cf-req-toggle qacf-f-required-wrap">
					<input type="checkbox" class="qacf-f-required"> الزامی
				</label>
				<span class="qa-cf-row-actions">
					<button type="button" class="button-link qa-cf-toggle-row" title="تنظیمات پیشرفته"><span class="dashicons dashicons-admin-generic"></span></button>
					<button type="button" class="button-link qa-cf-dup-field" title="تکثیر فیلد"><span class="dashicons dashicons-admin-page"></span></button>
					<button type="button" class="button-link qa-cf-remove-field" title="حذف فیلد"><span class="dashicons dashicons-trash"></span></button>
				</span>
			</div>

			<div class="qa-cf-row-body">
				<div class="qa-cf-row-grid">
					<label>نام فنی (key)
						<input type="text" class="qacf-f-key" dir="ltr" placeholder="خودکار ساخته می‌شود">
					</label>
					<label class="qacf-wrap-placeholder">متن راهنمای داخل فیلد
						<input type="text" class="qacf-f-placeholder">
					</label>
					<label class="qacf-wrap-default">مقدار پیش‌فرض
						<input type="text" class="qacf-f-default">
					</label>
					<label class="qacf-wrap-validate">اعتبارسنجی
						<select class="qacf-f-validate">
							<?php foreach ( $rules as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="qacf-wrap-minmax">حداقل (طول/مقدار)
						<input type="number" class="qacf-f-min" min="0">
					</label>
					<label class="qacf-wrap-minmax">حداکثر (طول/مقدار)
						<input type="number" class="qacf-f-max" min="0">
					</label>
					<label class="qacf-wrap-rows">تعداد خطوط
						<input type="number" class="qacf-f-rows" min="2" max="20">
					</label>
					<label class="qacf-wrap-autocomplete">تکمیل خودکار مرورگر
						<select class="qacf-f-autocomplete">
							<option value="">پیش‌فرض</option>
							<option value="name">نام کامل</option>
							<option value="given-name">نام</option>
							<option value="family-name">نام خانوادگی</option>
							<option value="email">ایمیل</option>
							<option value="tel">تلفن</option>
							<option value="organization">نام شرکت</option>
							<option value="street-address">آدرس</option>
							<option value="postal-code">کد پستی</option>
							<option value="off">غیرفعال</option>
						</select>
					</label>
					<label>کلاس CSS اختصاصی
						<input type="text" class="qacf-f-class" dir="ltr">
					</label>
					<label class="qacf-col-2">متن راهنما (زیر فیلد)
						<input type="text" class="qacf-f-help">
					</label>
					<label class="qacf-wrap-options qacf-col-2">گزینه‌ها — هر خط یک گزینه (برای مقدار متفاوت با برچسب: <code dir="ltr">value|برچسب</code>)
						<textarea class="qacf-f-options" rows="4"></textarea>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	public static function save_fields() {
		self::guard();
		check_admin_referer( 'qa_cf_save_fields' );

		$max_fields     = 80;
		$max_label_len  = 190;
		$max_option_len = 4000;

		$fields_json = isset( $_POST['fields_json'] ) ? wp_unslash( $_POST['fields_json'] ) : '[]';
		$fields_raw  = json_decode( (string) $fields_json, true );
		$clean       = array();

		if ( is_array( $fields_raw ) ) {
			$fields_raw = array_slice( $fields_raw, 0, $max_fields );
			$used_keys  = array();

			foreach ( $fields_raw as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$label = isset( $field['label'] ) ? qa_cf_safe_substr( sanitize_text_field( $field['label'] ), $max_label_len ) : '';
				if ( '' === $label ) {
					continue;
				}

				$key = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : sanitize_key( $label );
				if ( '' === $key ) {
					$key = 'field_' . ( count( $clean ) + 1 );
				}

				// کلیدهای رزروشده‌ی خود فرم نباید با کلید فیلدها تداخل کنند.
				$reserved = array( 'action', 'qa_cf_nonce', 'qa_cf_hp', 'qa_cf_ts', 'qa_cf_ts_sig', 'qa_cf_consent', 'qa_cf_captcha_answer', 'qa_cf_captcha_n1', 'qa_cf_captcha_n2', 'qa_cf_captcha_sig' );
				if ( in_array( $key, $reserved, true ) ) {
					$key = 'f_' . $key;
				}

				$base = $key;
				$i    = 2;
				while ( in_array( $key, $used_keys, true ) ) {
					$key = $base . '_' . $i;
					$i++;
				}
				$used_keys[] = $key;

				$type = ( isset( $field['type'] ) && array_key_exists( $field['type'], QA_CF_DB::field_types() ) ) ? $field['type'] : 'text';
				$rule = ( isset( $field['validate'] ) && array_key_exists( $field['validate'], QA_CF_DB::validate_rules() ) ) ? $field['validate'] : 'none';

				$clean[] = array(
					'key'          => $key,
					'label'        => $label,
					'type'         => $type,
					'required'     => ! empty( $field['required'] ) ? 1 : 0,
					'width'        => ( isset( $field['width'] ) && in_array( $field['width'], array( 'full', 'half', 'third' ), true ) ) ? $field['width'] : 'half',
					'placeholder'  => isset( $field['placeholder'] ) ? qa_cf_safe_substr( sanitize_text_field( $field['placeholder'] ), $max_label_len ) : '',
					'options'      => isset( $field['options'] ) ? qa_cf_safe_substr( sanitize_textarea_field( $field['options'] ), $max_option_len ) : '',
					'default'      => isset( $field['default'] ) ? qa_cf_safe_substr( sanitize_text_field( $field['default'] ), $max_label_len ) : '',
					'help'         => isset( $field['help'] ) ? qa_cf_safe_substr( sanitize_text_field( $field['help'] ), 300 ) : '',
					'validate'     => $rule,
					'min'          => isset( $field['min'] ) && '' !== $field['min'] ? absint( $field['min'] ) : '',
					'max'          => isset( $field['max'] ) && '' !== $field['max'] ? absint( $field['max'] ) : '',
					'rows'         => isset( $field['rows'] ) ? max( 2, min( 20, absint( $field['rows'] ) ) ) : 5,
					'css_class'    => isset( $field['css_class'] ) ? sanitize_html_class( $field['css_class'] ) : '',
					'autocomplete' => isset( $field['autocomplete'] ) ? qa_cf_safe_substr( sanitize_text_field( $field['autocomplete'] ), 40 ) : '',
				);
			}
		}

		if ( ! empty( $clean ) ) {
			update_option( 'qa_cf_fields', $clean );
		}

		$form = qa_cf_form_settings();

		$form['form_title']         = self::post_text( 'form_title' );
		$form['form_desc']          = self::post_text( 'form_desc' );
		$form['submit_text']        = self::post_text( 'submit_text', 'ارسال پیام' );
		$form['submit_loading']     = self::post_text( 'submit_loading', 'در حال ارسال…' );
		$form['success_msg']        = self::post_text( 'success_msg', 'پیام شما با موفقیت ثبت شد.' );
		$form['error_msg']          = self::post_text( 'error_msg', 'ارسال پیام ناموفق بود.' );
		$form['required_msg']       = self::post_text( 'required_msg', 'لطفاً فیلدهای الزامی را کامل کنید.' );
		$form['after_submit']       = ( isset( $_POST['after_submit'] ) && 'redirect' === $_POST['after_submit'] ) ? 'redirect' : 'message';
		$form['redirect_url']       = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '';
		$form['redirect_delay']     = isset( $_POST['redirect_delay'] ) ? min( 20, absint( $_POST['redirect_delay'] ) ) : 2;
		$form['reset_on_success']   = ! empty( $_POST['reset_on_success'] ) ? 1 : 0;
		$form['hide_form_success']  = ! empty( $_POST['hide_form_success'] ) ? 1 : 0;
		$form['scroll_on_success']  = ! empty( $_POST['scroll_on_success'] ) ? 1 : 0;
		$form['live_validation']    = ! empty( $_POST['live_validation'] ) ? 1 : 0;
		$form['char_counter']       = ! empty( $_POST['char_counter'] ) ? 1 : 0;
		$form['normalize_digits']   = ! empty( $_POST['normalize_digits'] ) ? 1 : 0;
		$form['save_draft']         = ! empty( $_POST['save_draft'] ) ? 1 : 0;
		$form['always_load_assets'] = ! empty( $_POST['always_load_assets'] ) ? 1 : 0;
		$form['consent_enabled']    = ! empty( $_POST['consent_enabled'] ) ? 1 : 0;
		$form['consent_required']   = ! empty( $_POST['consent_required'] ) ? 1 : 0;
		$form['consent_text']       = isset( $_POST['consent_text'] )
			? wp_kses( wp_unslash( $_POST['consent_text'] ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array(), 'em' => array() ) )
			: '';

		update_option( 'qa_cf_form_settings', $form );

		self::redirect( 'qa-cf-fields', array( 'qa_cf_msg' => 'saved' ) );
	}

	/**
	 * خواندن امن یک فیلد متنی از POST با مقدار جایگزین.
	 */
	private static function post_text( $key, $fallback = '' ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}
		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		return '' !== $value ? $value : $fallback;
	}

	/* ═════════════════ تب ۲: ظاهر فرم ═════════════════ */

	public static function page_style() {
		self::guard();

		$css     = (string) get_option( 'qa_cf_custom_css', '' );
		$design  = qa_cf_design_settings();
		$presets = qa_cf_design_presets();

		self::open_page( 'qa-cf-style' );
		?>
		<div class="qa-cf-style-layout">
			<div class="qa-cf-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="qa-cf-style-form">
					<?php wp_nonce_field( 'qa_cf_save_style' ); ?>
					<input type="hidden" name="action" value="qa_cf_save_style">

					<h2 class="qa-cf-section-title"><span class="dashicons dashicons-admin-appearance"></span> پیش‌تنظیم‌های آماده</h2>
					<p class="description">با یک کلیک یک پالت رنگی کامل را اعمال کن، بعد در صورت نیاز جزئیات را تغییر بده.</p>
					<div class="qa-cf-presets">
						<?php foreach ( $presets as $preset ) : ?>
							<button type="button" class="qa-cf-preset" data-values="<?php echo esc_attr( wp_json_encode( $preset['values'] ) ); ?>">
								<span class="qa-cf-dots">
									<i style="background:<?php echo esc_attr( $preset['values']['primary_color'] ); ?>"></i>
									<i style="background:<?php echo esc_attr( $preset['values']['accent_color'] ); ?>"></i>
									<i style="background:<?php echo esc_attr( $preset['values']['field_bg'] ); ?>;border:1px solid #e2e8f0"></i>
								</span>
								<?php echo esc_html( $preset['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<h2 class="qa-cf-section-title"><span class="dashicons dashicons-art"></span> رنگ‌ها</h2>
					<table class="form-table">
						<?php
						$colors = array(
							'primary_color' => 'رنگ اصلی (دکمه)',
							'accent_color'  => 'رنگ فوکوس / تأکید',
							'label_color'   => 'رنگ برچسب فیلدها',
							'text_color'    => 'رنگ متن ورودی‌ها',
							'field_bg'      => 'پس‌زمینه ورودی‌ها',
							'border_color'  => 'رنگ حاشیه ورودی‌ها',
							'error_color'   => 'رنگ خطا',
							'success_color' => 'رنگ موفقیت',
							'card_bg'       => 'پس‌زمینه کادر فرم',
						);
						foreach ( $colors as $key => $label ) :
							?>
							<tr>
								<th><label for="qa-cf-d-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
								<td><input type="text" id="qa-cf-d-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="qa-cf-color-field" value="<?php echo esc_attr( $design[ $key ] ); ?>"></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th><label for="qa-cf-d-scheme">حالت رنگی</label></th>
							<td>
								<select id="qa-cf-d-scheme" name="color_scheme">
									<option value="light" <?php selected( $design['color_scheme'], 'light' ); ?>>روشن</option>
									<option value="dark" <?php selected( $design['color_scheme'], 'dark' ); ?>>تیره</option>
									<option value="auto" <?php selected( $design['color_scheme'], 'auto' ); ?>>خودکار (هماهنگ با تنظیمات دستگاه کاربر)</option>
								</select>
								<p class="description">در حالت «خودکار»، اگر دستگاه کاربر روی تم تیره باشد، سطح‌های فرم خودبه‌خود تیره می‌شوند و رنگ برند دست‌نخورده می‌ماند.</p>
							</td>
						</tr>
					</table>

					<h2 class="qa-cf-section-title"><span class="dashicons dashicons-editor-table"></span> چیدمان و اندازه</h2>
					<table class="form-table">
						<tr>
							<th><label for="qa-cf-d-columns">تعداد ستون‌های فرم</label></th>
							<td>
								<select id="qa-cf-d-columns" name="columns">
									<option value="1" <?php selected( $design['columns'], '1' ); ?>>تک‌ستونی</option>
									<option value="2" <?php selected( $design['columns'], '2' ); ?>>دوستونی (پیشنهادی)</option>
									<option value="3" <?php selected( $design['columns'], '3' ); ?>>سه‌ستونی</option>
								</select>
								<p class="description">روی موبایل همیشه تک‌ستونی نمایش داده می‌شود.</p>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-label-style">نمایش برچسب فیلدها</label></th>
							<td>
								<select id="qa-cf-d-label-style" name="label_style">
									<option value="top" <?php selected( $design['label_style'], 'top' ); ?>>بالای فیلد (استاندارد)</option>
									<option value="floating" <?php selected( $design['label_style'], 'floating' ); ?>>شناور داخل فیلد (مدرن)</option>
									<option value="hidden" <?php selected( $design['label_style'], 'hidden' ); ?>>پنهان (فقط متن راهنما)</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-input-size">اندازه ورودی‌ها</label></th>
							<td>
								<select id="qa-cf-d-input-size" name="input_size">
									<option value="sm" <?php selected( $design['input_size'], 'sm' ); ?>>کوچک</option>
									<option value="md" <?php selected( $design['input_size'], 'md' ); ?>>متوسط</option>
									<option value="lg" <?php selected( $design['input_size'], 'lg' ); ?>>بزرگ</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-gap">فاصله بین فیلدها</label></th>
							<td>
								<input type="range" id="qa-cf-d-gap" name="gap" min="6" max="40" data-unit="px" value="<?php echo esc_attr( $design['gap'] ); ?>">
								<span class="qa-cf-range-val"><?php echo esc_html( $design['gap'] ); ?>px</span>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-radius">گردی گوشه‌ها</label></th>
							<td>
								<input type="range" id="qa-cf-d-radius" name="radius" min="0" max="30" data-unit="px" value="<?php echo esc_attr( $design['radius'] ); ?>">
								<span class="qa-cf-range-val"><?php echo esc_html( $design['radius'] ); ?>px</span>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-borderw">ضخامت حاشیه</label></th>
							<td>
								<input type="range" id="qa-cf-d-borderw" name="border_width" min="0" max="4" step="0.5" data-unit="px" value="<?php echo esc_attr( $design['border_width'] ); ?>">
								<span class="qa-cf-range-val"><?php echo esc_html( $design['border_width'] ); ?>px</span>
							</td>
						</tr>
						<tr>
							<th><label>عرض فرم</label></th>
							<td>
								<div class="qa-cf-segmented" role="radiogroup">
									<label class="qa-cf-segmented-opt">
										<input type="radio" name="width_mode" value="full" <?php checked( $design['width_mode'], 'full' ); ?>><span>تمام عرض</span>
									</label>
									<label class="qa-cf-segmented-opt">
										<input type="radio" name="width_mode" value="fixed" <?php checked( $design['width_mode'], 'fixed' ); ?>><span>عرض ثابت</span>
									</label>
								</div>
							</td>
						</tr>
						<tr id="qa-cf-maxwidth-row">
							<th><label for="qa-cf-d-maxwidth">حداکثر عرض (px)</label></th>
							<td><input type="number" id="qa-cf-d-maxwidth" name="max_width" min="280" max="1400" step="10" value="<?php echo esc_attr( $design['max_width'] ); ?>"></td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-direction">جهت فرم</label></th>
							<td>
								<select id="qa-cf-d-direction" name="direction">
									<option value="rtl" <?php selected( $design['direction'], 'rtl' ); ?>>راست‌به‌چپ (فارسی)</option>
									<option value="ltr" <?php selected( $design['direction'], 'ltr' ); ?>>چپ‌به‌راست (انگلیسی)</option>
								</select>
							</td>
						</tr>
					</table>

					<h2 class="qa-cf-section-title"><span class="dashicons dashicons-editor-textcolor"></span> فونت</h2>
					<table class="form-table">
						<tr>
							<th><label for="qa-cf-d-font">فونت فرم</label></th>
							<td>
								<select id="qa-cf-d-font" name="font_family">
									<option value="vazir" <?php selected( $design['font_family'], 'vazir' ); ?>>وزیرمتن</option>
									<option value="iransans" <?php selected( $design['font_family'], 'iransans' ); ?>>ایران‌سنس</option>
									<option value="tahoma" <?php selected( $design['font_family'], 'tahoma' ); ?>>تاهوما</option>
									<option value="system" <?php selected( $design['font_family'], 'system' ); ?>>فونت سیستم</option>
									<option value="theme" <?php selected( $design['font_family'], 'theme' ); ?>>فونت قالب سایت (inherit)</option>
								</select>
								<p class="description">اگر مطمئن نیستی قالب کدام فونت را دارد، «فونت قالب سایت» امن‌ترین انتخاب است.</p>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-loadfont">بارگذاری وزیرمتن از CDN</label></th>
							<td>
								<label><input type="checkbox" id="qa-cf-d-loadfont" name="load_font" value="1" <?php checked( $design['load_font'], 1 ); ?>> اگر قالب سایت وزیرمتن ندارد، از CDN بارگذاری شود</label>
								<p class="description">فقط وقتی فونت «وزیرمتن» انتخاب شده باشد اعمال می‌شود. اگر قالب خودش این فونت را لود می‌کند، خاموش بگذار.</p>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-fontsize">اندازه فونت (px)</label></th>
							<td><input type="number" id="qa-cf-d-fontsize" name="font_size" min="11" max="22" step="0.5" value="<?php echo esc_attr( $design['font_size'] ); ?>"></td>
						</tr>
					</table>

					<h2 class="qa-cf-section-title"><span class="dashicons dashicons-button"></span> دکمه و جلوه‌ها</h2>
					<table class="form-table">
						<tr>
							<th><label for="qa-cf-d-shape">شکل دکمه</label></th>
							<td>
								<select id="qa-cf-d-shape" name="button_shape">
									<option value="pill" <?php selected( $design['button_shape'], 'pill' ); ?>>کاملاً گرد</option>
									<option value="rounded" <?php selected( $design['button_shape'], 'rounded' ); ?>>گوشه‌گرد ملایم</option>
									<option value="square" <?php selected( $design['button_shape'], 'square' ); ?>>گوشه‌تیز</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-icon">آیکن دکمه</label></th>
							<td>
								<select id="qa-cf-d-icon" name="button_icon">
									<option value="" <?php selected( $design['button_icon'], '' ); ?>>بدون آیکن</option>
									<option value="send" <?php selected( $design['button_icon'], 'send' ); ?>>ارسال (هواپیما)</option>
									<option value="mail" <?php selected( $design['button_icon'], 'mail' ); ?>>پاکت نامه</option>
									<option value="arrow" <?php selected( $design['button_icon'], 'arrow' ); ?>>فلش</option>
									<option value="check" <?php selected( $design['button_icon'], 'check' ); ?>>تیک</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>جلوه‌های دکمه</th>
							<td>
								<label><input type="checkbox" name="button_full" value="1" <?php checked( $design['button_full'], 1 ); ?>> دکمه تمام‌عرض</label><br>
								<label><input type="checkbox" name="button_gradient" value="1" <?php checked( $design['button_gradient'], 1 ); ?>> پس‌زمینه گرادیانی</label><br>
								<label><input type="checkbox" name="shadow" value="1" <?php checked( $design['shadow'], 1 ); ?>> سایه نرم زیر دکمه</label>
							</td>
						</tr>
						<tr>
							<th>کادر دور فرم</th>
							<td><label><input type="checkbox" name="card_mode" value="1" <?php checked( $design['card_mode'], 1 ); ?>> نمایش فرم داخل یک کارت با پس‌زمینه و سایه</label></td>
						</tr>
						<tr>
							<th><label for="qa-cf-d-anim">انیمیشن ورود فرم</label></th>
							<td>
								<select id="qa-cf-d-anim" name="animation">
									<option value="none" <?php selected( $design['animation'], 'none' ); ?>>بدون انیمیشن</option>
									<option value="fade" <?php selected( $design['animation'], 'fade' ); ?>>محو تدریجی</option>
									<option value="slide" <?php selected( $design['animation'], 'slide' ); ?>>ورود از سمت راست</option>
								</select>
								<p class="description">برای کاربرانی که در سیستم‌عامل «کاهش حرکت» را فعال کرده‌اند، انیمیشن‌ها خودکار غیرفعال می‌شوند.</p>
							</td>
						</tr>
					</table>

					<h2 class="qa-cf-section-title"><span class="dashicons dashicons-editor-code"></span> CSS سفارشی (پیشرفته)</h2>
					<p class="description">
						این کد <strong>بعد از</strong> تنظیمات بالا چاپ می‌شود، پس همیشه اولویت دارد.
						کلاس‌های اصلی: <code>.qa-cf-wrap</code>، <code>.qa-cf-form</code>، <code>.qa-cf-field</code>، <code>.qa-cf-submit</code>
					</p>
					<textarea name="custom_css" rows="10" class="large-text code" style="direction:ltr;text-align:left" spellcheck="false"><?php echo esc_textarea( $css ); ?></textarea>

					<?php self::sticky_save( 'ذخیره ظاهر فرم' ); ?>
				</form>
			</div>

			<div class="qa-cf-card qa-cf-style-preview">
				<div class="qa-cf-preview-head">
					<div>
						<h2 class="qa-cf-section-title" style="border:0;margin:0;padding:0">پیش‌نمایش زنده</h2>
						<p class="description">با تغییر کنترل‌ها، همین‌جا و بدون ذخیره‌کردن به‌روز می‌شود.</p>
					</div>
					<div class="qa-cf-segmented" id="qa-cf-preview-device">
						<button type="button" class="qa-cf-device-btn is-active" data-device="desktop"><span class="dashicons dashicons-desktop"></span> دسکتاپ</button>
						<button type="button" class="qa-cf-device-btn" data-device="mobile"><span class="dashicons dashicons-smartphone"></span> موبایل</button>
					</div>
				</div>
				<div class="qa-cf-preview-frame" id="qa-cf-preview-frame">
					<?php echo QA_CF_Shortcode::render( array( 'preview' => '1' ) ); // phpcs:ignore ?>
				</div>
			</div>
		</div>
		<?php
		self::close_page();
	}

	public static function save_style() {
		self::guard();
		check_admin_referer( 'qa_cf_save_style' );

		$defaults = qa_cf_default_design_settings();
		$design   = qa_cf_design_settings();

		foreach ( array( 'primary_color', 'accent_color', 'text_color', 'label_color', 'field_bg', 'border_color', 'error_color', 'success_color', 'card_bg' ) as $key ) {
			$value           = isset( $_POST[ $key ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ) : '';
			$design[ $key ] = $value ? $value : $defaults[ $key ];
		}

		$design['radius']          = isset( $_POST['radius'] ) ? min( 40, absint( $_POST['radius'] ) ) : $defaults['radius'];
		$design['border_width']    = isset( $_POST['border_width'] ) && is_numeric( $_POST['border_width'] ) ? min( 6, max( 0, (float) $_POST['border_width'] ) ) : $defaults['border_width'];
		$design['gap']             = isset( $_POST['gap'] ) ? min( 60, absint( $_POST['gap'] ) ) : $defaults['gap'];
		$design['font_size']       = isset( $_POST['font_size'] ) && is_numeric( $_POST['font_size'] ) ? min( 26, max( 10, (float) $_POST['font_size'] ) ) : $defaults['font_size'];
		$design['max_width']       = isset( $_POST['max_width'] ) ? min( 1400, max( 240, absint( $_POST['max_width'] ) ) ) : $defaults['max_width'];
		$design['button_shape']    = self::post_enum( 'button_shape', array( 'pill', 'rounded', 'square' ), $defaults['button_shape'] );
		$design['button_icon']     = self::post_enum( 'button_icon', array( '', 'send', 'mail', 'arrow', 'check' ), $defaults['button_icon'] );
		$design['font_family']     = self::post_enum( 'font_family', array( 'vazir', 'iransans', 'tahoma', 'system', 'theme' ), $defaults['font_family'] );
		$design['input_size']      = self::post_enum( 'input_size', array( 'sm', 'md', 'lg' ), $defaults['input_size'] );
		$design['label_style']     = self::post_enum( 'label_style', array( 'top', 'floating', 'hidden' ), $defaults['label_style'] );
		$design['columns']         = self::post_enum( 'columns', array( '1', '2', '3' ), $defaults['columns'] );
		$design['width_mode']      = self::post_enum( 'width_mode', array( 'full', 'fixed' ), $defaults['width_mode'] );
		$design['animation']       = self::post_enum( 'animation', array( 'none', 'fade', 'slide' ), $defaults['animation'] );
		$design['color_scheme']    = self::post_enum( 'color_scheme', array( 'light', 'dark', 'auto' ), $defaults['color_scheme'] );
		$design['direction']       = self::post_enum( 'direction', array( 'rtl', 'ltr' ), $defaults['direction'] );
		$design['shadow']          = ! empty( $_POST['shadow'] ) ? 1 : 0;
		$design['button_full']     = ! empty( $_POST['button_full'] ) ? 1 : 0;
		$design['button_gradient'] = ! empty( $_POST['button_gradient'] ) ? 1 : 0;
		$design['card_mode']       = ! empty( $_POST['card_mode'] ) ? 1 : 0;
		$design['load_font']       = ! empty( $_POST['load_font'] ) ? 1 : 0;

		update_option( 'qa_cf_design_settings', $design );

		// CSS سفارشی: هم تگ‌ها حذف می‌شوند، هم دنباله‌ی </style صریحاً، هم سقف طول.
		$css = isset( $_POST['custom_css'] ) ? (string) wp_unslash( $_POST['custom_css'] ) : '';
		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '/<\/?style/i', '', $css );
		$css = qa_cf_safe_substr( $css, 20000 );
		update_option( 'qa_cf_custom_css', $css );

		self::redirect( 'qa-cf-style', array( 'qa_cf_msg' => 'saved' ) );
	}

	private static function post_enum( $key, $allowed, $fallback ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}
		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/* ═════════════════ تب ۳: ایمیل ═════════════════ */

	public static function page_email() {
		self::guard();

		$email  = qa_cf_email_settings();
		$smtp   = qa_cf_smtp_settings();
		$domain = qa_cf_site_domain();

		self::open_page( 'qa-cf-email' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'qa_cf_save_email' ); ?>
			<input type="hidden" name="action" value="qa_cf_save_email">

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-email"></span> گیرنده — پیام‌ها به کجا برسند</h2>
				<table class="form-table">
					<tr>
						<th>ارسال ایمیل اعلان</th>
						<td>
							<label><input type="checkbox" name="notify_enabled" value="1" <?php checked( $email['notify_enabled'], 1 ); ?>> با هر ارسال فرم، یک ایمیل اعلان فرستاده شود</label>
							<p class="description">اگر فقط می‌خواهی پیام‌ها در پیشخوان ذخیره شوند (بدون ایمیل)، این گزینه را خاموش کن.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-notify-email">آدرس گیرنده</label></th>
						<td>
							<input type="text" id="qa-cf-notify-email" name="notify_email" class="regular-text" dir="ltr" value="<?php echo esc_attr( $email['notify_email'] ); ?>">
							<p class="description">چند آدرس را با کاما جدا کن: <code dir="ltr">sales@example.com, support@example.com</code></p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-email-subject">موضوع ایمیل</label></th>
						<td>
							<input type="text" id="qa-cf-email-subject" name="email_subject" class="regular-text" value="<?php echo esc_attr( $email['email_subject'] ); ?>">
							<p class="description">شورت‌تگ‌های مجاز: <code dir="ltr">{site}</code> <code dir="ltr">{name}</code> <code dir="ltr">{email}</code> <code dir="ltr">{date}</code> <code dir="ltr">{field:KEY}</code></p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-email-template">قالب ایمیل</label></th>
						<td>
							<select id="qa-cf-email-template" name="email_template">
								<option value="modern" <?php selected( $email['email_template'], 'modern' ); ?>>مدرن (رنگی و کارت‌مانند)</option>
								<option value="simple" <?php selected( $email['email_template'], 'simple' ); ?>>ساده (جدول بدون تزئین)</option>
								<option value="plain" <?php selected( $email['email_template'], 'plain' ); ?>>متن خالص (بدون HTML)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>پیوست فایل‌ها</th>
						<td><label><input type="checkbox" name="attach_files" value="1" <?php checked( $email['attach_files'], 1 ); ?>> فایل‌های آپلودشده به ایمیل اعلان پیوست شوند</label></td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-admin-site"></span> فرستنده</h2>
				<div class="qa-cf-notice-box">
					دامنه سایت: <strong dir="ltr"><?php echo esc_html( $domain ); ?></strong> — برای این‌که ایمیل‌ها در اسپم نیفتند، آدرس فرستنده باید روی همین دامنه باشد؛ مثلاً <code dir="ltr">info@<?php echo esc_html( $domain ); ?></code>
				</div>
				<table class="form-table">
					<tr>
						<th><label for="qa-cf-from-email">ایمیل فرستنده</label></th>
						<td>
							<input type="email" id="qa-cf-from-email" name="from_email" class="regular-text" dir="ltr" placeholder="info@<?php echo esc_attr( $domain ); ?>" value="<?php echo esc_attr( $email['from_email'] ); ?>">
							<p class="description">خالی بگذار تا از فرستنده پیش‌فرض سرور (یا نام کاربری SMTP) استفاده شود.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-from-name">نام فرستنده</label></th>
						<td><input type="text" id="qa-cf-from-name" name="from_name" class="regular-text" value="<?php echo esc_attr( $email['from_name'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-reply-mode">پاسخ به (Reply-To)</label></th>
						<td>
							<select id="qa-cf-reply-mode" name="reply_to_mode">
								<option value="auto" <?php selected( $email['reply_to_mode'], 'auto' ); ?>>خودکار — ایمیلی که کاربر در فرم نوشته</option>
								<option value="fixed" <?php selected( $email['reply_to_mode'], 'fixed' ); ?>>آدرس ثابت</option>
								<option value="off" <?php selected( $email['reply_to_mode'], 'off' ); ?>>خاموش</option>
							</select>
							<p class="description">با حالت «خودکار»، در ایمیل اعلان فقط کافی است Reply بزنی تا پاسخ مستقیم برای کاربر برود.</p>
						</td>
					</tr>
					<tr id="qa-cf-reply-fixed-row">
						<th><label for="qa-cf-reply-fixed">آدرس ثابت Reply-To</label></th>
						<td><input type="email" id="qa-cf-reply-fixed" name="reply_to_fixed" class="regular-text" dir="ltr" value="<?php echo esc_attr( $email['reply_to_fixed'] ); ?>"></td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title">
					<label class="qa-cf-toggle-heading">
						<input type="checkbox" id="qa-cf-autoreply-enabled" name="autoreply_enabled" value="1" <?php checked( $email['autoreply_enabled'], 1 ); ?>>
						پاسخ خودکار به ارسال‌کننده
					</label>
				</h2>
				<p class="description">بلافاصله بعد از ثبت فرم، یک ایمیل تشکر برای خود کاربر فرستاده می‌شود (در صورتی که در فرم ایمیل وارد کرده باشد).</p>
				<table class="form-table" id="qa-cf-autoreply-fields">
					<tr>
						<th><label for="qa-cf-autoreply-subject">موضوع</label></th>
						<td><input type="text" id="qa-cf-autoreply-subject" name="autoreply_subject" class="regular-text" value="<?php echo esc_attr( $email['autoreply_subject'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-autoreply-body">متن پیام</label></th>
						<td>
							<textarea id="qa-cf-autoreply-body" name="autoreply_body" rows="7" class="large-text"><?php echo esc_textarea( $email['autoreply_body'] ); ?></textarea>
							<p class="description">شورت‌تگ‌ها: <code dir="ltr">{name}</code> <code dir="ltr">{email}</code> <code dir="ltr">{site}</code> <code dir="ltr">{date}</code> <code dir="ltr">{field:KEY}</code></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title">
					<label class="qa-cf-toggle-heading">
						<input type="checkbox" id="qa-cf-smtp-enabled" name="smtp_enabled" value="1" <?php checked( $smtp['enabled'], 1 ); ?>>
						SMTP اختصاصی (اختیاری)
					</label>
				</h2>
				<p class="description">فقط وقتی لازم است که ایمیل‌های سایت ارسال نمی‌شوند یا در اسپم می‌افتند.</p>
				<table class="form-table" id="qa-cf-smtp-fields">
					<tr>
						<th><label for="qa-cf-smtp-host">SMTP Host</label></th>
						<td><input type="text" id="qa-cf-smtp-host" name="host" class="regular-text" dir="ltr" placeholder="mail.<?php echo esc_attr( $domain ); ?>" value="<?php echo esc_attr( $smtp['host'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-smtp-port">پورت</label></th>
						<td>
							<input type="number" id="qa-cf-smtp-port" name="port" class="small-text" value="<?php echo esc_attr( $smtp['port'] ); ?>">
							<p class="description">معمولاً ۵۸۷ برای TLS و ۴۶۵ برای SSL.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-smtp-encryption">رمزنگاری</label></th>
						<td>
							<select id="qa-cf-smtp-encryption" name="encryption">
								<option value="tls" <?php selected( $smtp['encryption'], 'tls' ); ?>>TLS</option>
								<option value="ssl" <?php selected( $smtp['encryption'], 'ssl' ); ?>>SSL</option>
								<option value="none" <?php selected( $smtp['encryption'], 'none' ); ?>>بدون رمزنگاری</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-smtp-username">نام کاربری</label></th>
						<td><input type="text" id="qa-cf-smtp-username" name="username" class="regular-text" dir="ltr" placeholder="info@<?php echo esc_attr( $domain ); ?>" value="<?php echo esc_attr( $smtp['username'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-smtp-password">رمز عبور</label></th>
						<td>
							<input type="password" id="qa-cf-smtp-password" name="password" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo ! empty( $smtp['password'] ) ? '•••••••• (برای تغییر، رمز جدید را بنویس)' : ''; ?>">
							<?php if ( ! empty( $smtp['password'] ) ) : ?>
								<p class="description">رمز ذخیره‌شده به‌دلایل امنیتی نمایش داده نمی‌شود. خالی بگذار تا دست‌نخورده بماند.</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<?php self::sticky_save( 'ذخیره تنظیمات ایمیل' ); ?>
		</form>

		<div class="qa-cf-card">
			<h2 class="qa-cf-section-title"><span class="dashicons dashicons-megaphone"></span> تست ارسال ایمیل</h2>
			<p class="description">قبل از انتشار فرم، یک ایمیل آزمایشی بفرست تا از درست‌کارکردن تنظیمات مطمئن شوی. (ابتدا تغییرات بالا را ذخیره کن.)</p>
			<p class="qa-cf-test-row">
				<input type="email" id="qa-cf-test-email-to" class="regular-text" dir="ltr" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
				<button type="button" class="button button-secondary" id="qa-cf-test-email-btn">ارسال ایمیل آزمایشی</button>
				<span id="qa-cf-test-email-result"></span>
			</p>
		</div>
		<?php
		self::close_page();
	}

	public static function save_email() {
		self::guard();
		check_admin_referer( 'qa_cf_save_email' );

		$defaults = qa_cf_default_email_settings();
		$email    = qa_cf_email_settings();

		$email['notify_enabled']    = ! empty( $_POST['notify_enabled'] ) ? 1 : 0;
		$email['notify_email']      = isset( $_POST['notify_email'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_email'] ) ) : $defaults['notify_email'];
		$email['email_subject']     = self::post_text( 'email_subject', $defaults['email_subject'] );
		$email['email_template']    = self::post_enum( 'email_template', array( 'modern', 'simple', 'plain' ), 'modern' );
		$email['attach_files']      = ! empty( $_POST['attach_files'] ) ? 1 : 0;
		$email['from_email']        = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
		$email['from_name']         = self::post_text( 'from_name', $defaults['from_name'] );
		$email['reply_to_mode']     = self::post_enum( 'reply_to_mode', array( 'auto', 'fixed', 'off' ), 'auto' );
		$email['reply_to_fixed']    = isset( $_POST['reply_to_fixed'] ) ? sanitize_email( wp_unslash( $_POST['reply_to_fixed'] ) ) : '';
		$email['autoreply_enabled'] = ! empty( $_POST['autoreply_enabled'] ) ? 1 : 0;
		$email['autoreply_subject'] = self::post_text( 'autoreply_subject', $defaults['autoreply_subject'] );
		$email['autoreply_body']    = isset( $_POST['autoreply_body'] ) ? qa_cf_safe_substr( sanitize_textarea_field( wp_unslash( $_POST['autoreply_body'] ) ), 4000 ) : $defaults['autoreply_body'];

		update_option( 'qa_cf_email_settings', $email );

		// رمز SMTP فقط وقتی جایگزین می‌شود که مقدار جدیدی وارد شده باشد.
		$existing_smtp = qa_cf_smtp_settings();
		$new_password  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		update_option(
			'qa_cf_smtp_settings',
			array(
				'enabled'    => ! empty( $_POST['smtp_enabled'] ) ? 1 : 0,
				'host'       => isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '',
				'port'       => isset( $_POST['port'] ) ? absint( $_POST['port'] ) : 587,
				'encryption' => self::post_enum( 'encryption', array( 'tls', 'ssl', 'none' ), 'tls' ),
				'username'   => isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '',
				'password'   => '' !== $new_password ? $new_password : $existing_smtp['password'],
			),
			false // autoload=false تا رمز در بازدیدهای عادی سایت لود نشود.
		);

		$args = array( 'qa_cf_msg' => 'saved' );

		if ( ! empty( $email['from_email'] ) ) {
			$parts       = explode( '@', $email['from_email'] );
			$from_domain = strtolower( end( $parts ) );
			if ( $from_domain && $from_domain !== qa_cf_site_domain() ) {
				$args['qa_cf_domain_warn'] = rawurlencode( $from_domain );
			}
		}

		self::redirect( 'qa-cf-email', $args );
	}

	public static function ajax_test_email() {
		check_ajax_referer( 'qa_cf_admin_nonce', 'nonce' );

		if ( ! current_user_can( qa_cf_cap() ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
		}

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( empty( $to ) || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$ok = QA_CF_Mailer::send_test_email( $to );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => 'ایمیل آزمایشی به ' . $to . ' ارسال شد. صندوق ورودی و پوشه اسپم را بررسی کن.' ) );
		}

		$detail = QA_CF_Mailer::$last_error ? ' (' . QA_CF_Mailer::$last_error . ')' : '';
		wp_send_json_error( array( 'message' => 'ارسال ناموفق بود. تنظیمات فرستنده/SMTP را بررسی کن.' . $detail ) );
	}

	/* ═════════════════ تب ۴: امنیت و حریم خصوصی ═════════════════ */

	public static function page_security() {
		self::guard();
		$s = qa_cf_security_settings();

		self::open_page( 'qa-cf-security' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'qa_cf_save_security' ); ?>
			<input type="hidden" name="action" value="qa_cf_save_security">

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-shield"></span> ضداسپم</h2>
				<div class="qa-cf-notice-box">
					هانی‌پات (فیلد نامرئی) همیشه فعال است و نیازی به تنظیم ندارد. لایه‌های زیر روی آن اضافه می‌شوند.
				</div>
				<table class="form-table">
					<tr>
						<th>سؤال ریاضی ساده</th>
						<td>
							<label><input type="checkbox" name="captcha_enabled" value="1" <?php checked( $s['captcha_enabled'], 1 ); ?>> پرسیدن یک جمع ساده قبل از ارسال</label>
							<p class="description">بدون نیاز به کلید API یا سرویس خارجی. اگر اسپم زیادی دریافت می‌کنی، روشن کن.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-minfill">حداقل زمان پر کردن فرم</label></th>
						<td>
							<input type="number" id="qa-cf-minfill" name="min_fill_seconds" class="small-text" min="0" max="60" value="<?php echo esc_attr( $s['min_fill_seconds'] ); ?>"> ثانیه
							<p class="description">فرم‌هایی که سریع‌تر از این مدت ارسال شوند، بی‌صدا نادیده گرفته می‌شوند (بات‌ها فرم را در کسری از ثانیه پر می‌کنند). مقدار ۰ یعنی غیرفعال.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-rl-max">حداکثر ارسال از هر IP</label></th>
						<td>
							<input type="number" id="qa-cf-rl-max" name="rate_limit_max" class="small-text" min="0" max="100" value="<?php echo esc_attr( $s['rate_limit_max'] ); ?>">
							ارسال در هر
							<input type="number" name="rate_limit_window" class="small-text" min="60" max="86400" step="60" value="<?php echo esc_attr( $s['rate_limit_window'] ); ?>"> ثانیه
							<p class="description">مقدار ۰ برای «حداکثر ارسال» یعنی محدودیت غیرفعال است.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-blocked-words">کلمات مسدود</label></th>
						<td>
							<textarea id="qa-cf-blocked-words" name="blocked_words" rows="4" class="large-text" placeholder="هر خط یک کلمه"><?php echo esc_textarea( $s['blocked_words'] ); ?></textarea>
							<p class="description">اگر متن فرم شامل یکی از این کلمات باشد، ارسال بی‌صدا رد می‌شود (فرستنده پیام موفقیت می‌بیند ولی چیزی ثبت نمی‌شود).</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-blocked-ips">IP های مسدود</label></th>
						<td>
							<textarea id="qa-cf-blocked-ips" name="blocked_ips" rows="3" class="large-text" dir="ltr" placeholder="192.168.1.5&#10;10.0.0.*"><?php echo esc_textarea( $s['blocked_ips'] ); ?></textarea>
							<p class="description">هر خط یک IP. برای مسدودکردن یک محدوده می‌توانی از <code dir="ltr">*</code> استفاده کنی.</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-privacy"></span> ذخیره‌سازی و حریم خصوصی</h2>
				<table class="form-table">
					<tr>
						<th>ذخیره پیام‌ها در دیتابیس</th>
						<td>
							<label><input type="checkbox" name="store_entries" value="1" <?php checked( $s['store_entries'], 1 ); ?>> پیام‌ها در پیشخوان ذخیره شوند</label>
							<p class="description">اگر خاموش شود، پیام‌ها فقط ایمیل می‌شوند و هیچ داده‌ای در سایت نگهداری نمی‌شود.</p>
						</td>
					</tr>
					<tr>
						<th>داده‌های جانبی</th>
						<td>
							<label><input type="checkbox" name="store_ip" value="1" <?php checked( $s['store_ip'], 1 ); ?>> ذخیره IP فرستنده</label><br>
							<label><input type="checkbox" name="store_ua" value="1" <?php checked( $s['store_ua'], 1 ); ?>> ذخیره مرورگر/دستگاه (User-Agent)</label>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-autodelete">حذف خودکار پیام‌های قدیمی</label></th>
						<td>
							بعد از <input type="number" id="qa-cf-autodelete" name="auto_delete_days" class="small-text" min="0" max="3650" value="<?php echo esc_attr( $s['auto_delete_days'] ); ?>"> روز
							<p class="description">مقدار ۰ یعنی هرگز حذف نشوند. این کار روزی یک‌بار و به‌صورت خودکار انجام می‌شود.</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title"><span class="dashicons dashicons-upload"></span> آپلود فایل</h2>
				<table class="form-table">
					<tr>
						<th><label for="qa-cf-upload-max">حداکثر حجم هر فایل</label></th>
						<td>
							<input type="number" id="qa-cf-upload-max" name="upload_max_mb" class="small-text" min="1" max="64" value="<?php echo esc_attr( $s['upload_max_mb'] ); ?>"> مگابایت
							<p class="description">سقف واقعی سرور شما: <strong><?php echo esc_html( qa_cf_fa_num( round( wp_max_upload_size() / 1048576, 1 ) ) ); ?> مگابایت</strong> — مقدار بیشتر از این عملاً اعمال نمی‌شود.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-upload-types">پسوندهای مجاز</label></th>
						<td>
							<input type="text" id="qa-cf-upload-types" name="upload_types" class="large-text" dir="ltr" value="<?php echo esc_attr( $s['upload_types'] ); ?>">
							<p class="description">با کاما جدا کن. پسوندهای اجراشدنی (php، js، exe و…) حتی اگر اینجا نوشته شوند، به‌صورت سخت‌گیرانه رد می‌شوند.</p>
						</td>
					</tr>
				</table>
			</div>

			<?php self::sticky_save( 'ذخیره تنظیمات امنیتی' ); ?>
		</form>
		<?php
		self::close_page();
	}

	public static function save_security() {
		self::guard();
		check_admin_referer( 'qa_cf_save_security' );

		$defaults = qa_cf_default_security_settings();
		$s        = qa_cf_security_settings();

		$s['captcha_enabled']   = ! empty( $_POST['captcha_enabled'] ) ? 1 : 0;
		$s['min_fill_seconds']  = isset( $_POST['min_fill_seconds'] ) ? min( 60, absint( $_POST['min_fill_seconds'] ) ) : $defaults['min_fill_seconds'];
		$s['rate_limit_max']    = isset( $_POST['rate_limit_max'] ) ? min( 100, absint( $_POST['rate_limit_max'] ) ) : $defaults['rate_limit_max'];
		$s['rate_limit_window'] = isset( $_POST['rate_limit_window'] ) ? max( 60, min( DAY_IN_SECONDS, absint( $_POST['rate_limit_window'] ) ) ) : $defaults['rate_limit_window'];
		$s['blocked_words']     = isset( $_POST['blocked_words'] ) ? qa_cf_safe_substr( sanitize_textarea_field( wp_unslash( $_POST['blocked_words'] ) ), 4000 ) : '';
		$s['blocked_ips']       = isset( $_POST['blocked_ips'] ) ? qa_cf_safe_substr( sanitize_textarea_field( wp_unslash( $_POST['blocked_ips'] ) ), 4000 ) : '';
		$s['store_entries']     = ! empty( $_POST['store_entries'] ) ? 1 : 0;
		$s['store_ip']          = ! empty( $_POST['store_ip'] ) ? 1 : 0;
		$s['store_ua']          = ! empty( $_POST['store_ua'] ) ? 1 : 0;
		$s['auto_delete_days']  = isset( $_POST['auto_delete_days'] ) ? min( 3650, absint( $_POST['auto_delete_days'] ) ) : 0;
		$s['upload_max_mb']     = isset( $_POST['upload_max_mb'] ) ? max( 1, min( 64, absint( $_POST['upload_max_mb'] ) ) ) : $defaults['upload_max_mb'];

		$types             = isset( $_POST['upload_types'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['upload_types'] ) ) ) : $defaults['upload_types'];
		$types             = preg_replace( '/[^a-z0-9,]/', '', $types );
		$s['upload_types'] = '' !== $types ? $types : $defaults['upload_types'];

		update_option( 'qa_cf_security_settings', $s );

		self::redirect( 'qa-cf-security', array( 'qa_cf_msg' => 'saved' ) );
	}

	/* ═════════════════ تب ۵: اتصال‌ها ═════════════════ */

	public static function page_integrations() {
		self::guard();
		$i = qa_cf_integration_settings();

		self::open_page( 'qa-cf-integrations' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'qa_cf_save_integrations' ); ?>
			<input type="hidden" name="action" value="qa_cf_save_integrations">

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title">
					<label class="qa-cf-toggle-heading">
						<input type="checkbox" id="qa-cf-telegram-enabled" name="telegram_enabled" value="1" <?php checked( $i['telegram_enabled'], 1 ); ?>>
						اعلان تلگرام
					</label>
				</h2>
				<p class="description">با هر پیام جدید، یک نوتیفیکیشن فوری در تلگرام بگیر — بدون نیاز به چک‌کردن ایمیل.</p>
				<table class="form-table" id="qa-cf-telegram-fields">
					<tr>
						<th><label for="qa-cf-tg-token">توکن ربات</label></th>
						<td>
							<input type="text" id="qa-cf-tg-token" name="telegram_token" class="large-text" dir="ltr" value="<?php echo esc_attr( $i['telegram_token'] ); ?>">
							<p class="description">از <code dir="ltr">@BotFather</code> یک ربات بساز و توکن آن را اینجا بگذار.</p>
						</td>
					</tr>
					<tr>
						<th><label for="qa-cf-tg-chat">شناسه چت (Chat ID)</label></th>
						<td>
							<input type="text" id="qa-cf-tg-chat" name="telegram_chat" class="regular-text" dir="ltr" value="<?php echo esc_attr( $i['telegram_chat'] ); ?>">
							<p class="description">شناسه عددی حساب یا کانال خودت. با ربات <code dir="ltr">@userinfobot</code> می‌توانی آن را پیدا کنی. برای کانال، ربات باید ادمین کانال باشد.</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="qa-cf-card">
				<h2 class="qa-cf-section-title">
					<label class="qa-cf-toggle-heading">
						<input type="checkbox" id="qa-cf-webhook-enabled" name="webhook_enabled" value="1" <?php checked( $i['webhook_enabled'], 1 ); ?>>
						وبهوک (اتصال به CRM و ابزارهای دیگر)
					</label>
				</h2>
				<p class="description">داده‌های هر فرم به‌صورت JSON به آدرس دلخواه ارسال می‌شود؛ مناسب اتصال به CRM، گوگل‌شیت، Make، n8n و Zapier.</p>
				<table class="form-table" id="qa-cf-webhook-fields">
					<tr>
						<th><label for="qa-cf-wh-url">آدرس وبهوک</label></th>
						<td><input type="url" id="qa-cf-wh-url" name="webhook_url" class="large-text" dir="ltr" placeholder="https://" value="<?php echo esc_attr( $i['webhook_url'] ); ?>"></td>
					</tr>
					<tr>
						<th><label for="qa-cf-wh-secret">کلید امضا (اختیاری)</label></th>
						<td>
							<input type="text" id="qa-cf-wh-secret" name="webhook_secret" class="regular-text" dir="ltr" value="<?php echo esc_attr( $i['webhook_secret'] ); ?>">
							<p class="description">در صورت پر بودن، هدر <code dir="ltr">X-QA-CF-Signature</code> با HMAC-SHA256 ارسال می‌شود تا سمت گیرنده اصالت درخواست را بررسی کند.</p>
						</td>
					</tr>
				</table>
			</div>

			<?php self::sticky_save( 'ذخیره اتصال‌ها' ); ?>
		</form>
		<?php
		self::close_page();
	}

	public static function save_integrations() {
		self::guard();
		check_admin_referer( 'qa_cf_save_integrations' );

		$i = qa_cf_integration_settings();

		$i['telegram_enabled'] = ! empty( $_POST['telegram_enabled'] ) ? 1 : 0;
		$i['telegram_token']   = isset( $_POST['telegram_token'] ) ? preg_replace( '/[^A-Za-z0-9:_\-]/', '', (string) wp_unslash( $_POST['telegram_token'] ) ) : '';
		$i['telegram_chat']    = isset( $_POST['telegram_chat'] ) ? preg_replace( '/[^A-Za-z0-9@_\-]/', '', (string) wp_unslash( $_POST['telegram_chat'] ) ) : '';
		$i['webhook_enabled']  = ! empty( $_POST['webhook_enabled'] ) ? 1 : 0;
		$i['webhook_url']      = isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '';
		$i['webhook_secret']   = isset( $_POST['webhook_secret'] ) ? qa_cf_safe_substr( sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ), 190 ) : '';

		update_option( 'qa_cf_integration_settings', $i, false );
		delete_transient( 'qa_cf_telegram_error' );

		self::redirect( 'qa-cf-integrations', array( 'qa_cf_msg' => 'saved' ) );
	}

	/* ═════════════════ تب ۶: پیام‌های دریافتی ═════════════════ */

	public static function page_entries() {
		self::guard();

		if ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && ! empty( $_GET['entry_id'] ) ) {
			self::open_page( 'qa-cf-entries' );
			self::render_single_entry( absint( $_GET['entry_id'] ) );
			self::close_page();
			return;
		}

		$list_table = new QA_CF_List_Table();
		$list_table->prepare_items();

		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

		self::open_page( 'qa-cf-entries' );
		?>
		<div class="qa-cf-card">
			<form method="get">
				<input type="hidden" name="page" value="qa-cf-entries">
				<div class="qa-cf-entries-toolbar">
					<div class="qa-cf-filters">
						<label for="qa-cf-status-filter">وضعیت:</label>
						<select name="status" id="qa-cf-status-filter">
							<option value="" <?php selected( $status, '' ); ?>>همه</option>
							<option value="unread" <?php selected( $status, 'unread' ); ?>>خوانده‌نشده</option>
							<option value="read" <?php selected( $status, 'read' ); ?>>خوانده‌شده</option>
						</select>
						<label for="qa-cf-from">از تاریخ:</label>
						<input type="date" id="qa-cf-from" name="from" value="<?php echo esc_attr( $from ); ?>">
						<label for="qa-cf-to">تا:</label>
						<input type="date" id="qa-cf-to" name="to" value="<?php echo esc_attr( $to ); ?>">
						<button type="submit" class="button">اعمال فیلتر</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=qa-cf-entries' ) ); ?>" class="button button-link">حذف فیلترها</a>
					</div>
					<?php $list_table->search_box( 'جست‌وجو در پیام‌ها', 'qa-cf-search' ); ?>
				</div>
				<?php $list_table->display(); ?>
			</form>
		</div>

		<?php
		/*
		 * فرم POST مستقل برای عملیات تک‌ردیفی. دکمه‌های داخل جدول با ویژگی
		 * form="qa-cf-entry-action" به این فرم وصل می‌شوند؛ این کار از فرم
		 * تودرتو (که مرورگرها آن را نادیده می‌گیرند و در نسخه ۱.۸ باعث
		 * ازکارافتادن دکمه حذف شده بود) جلوگیری می‌کند.
		 */
		?>
		<form id="qa-cf-entry-action" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=qa-cf-entries' ) ); ?>">
			<input type="hidden" name="page" value="qa-cf-entries">
			<?php wp_nonce_field( 'qa_cf_entry_actions' ); ?>
		</form>
		<?php
		self::close_page();
	}

	private static function render_single_entry( $id ) {
		$entry = QA_CF_DB::get_entry( $id );

		if ( ! $entry ) {
			echo '<div class="qa-cf-card"><p>پیام مورد نظر پیدا نشد.</p><p><a href="' . esc_url( admin_url( 'admin.php?page=qa-cf-entries' ) ) . '">بازگشت به لیست پیام‌ها</a></p></div>';
			return;
		}

		if ( 'unread' === $entry['status'] ) {
			QA_CF_DB::update_status( $id, 'read' );
			$entry['status'] = 'read';
		}

		$rows     = QA_CF_DB::normalize_entry_rows( $entry['form_data'] );
		$back_url = admin_url( 'admin.php?page=qa-cf-entries' );
		$reply_to = '';

		foreach ( $rows as $row ) {
			if ( is_string( $row['value'] ) && is_email( $row['value'] ) ) {
				$reply_to = $row['value'];
				break;
			}
		}
		?>
		<div class="qa-cf-card">
			<h2 class="qa-cf-section-title">
				<span class="dashicons dashicons-email-alt"></span>
				پیام #<?php echo esc_html( qa_cf_fa_num( $id ) ); ?>
			</h2>

			<p>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button">&rarr; بازگشت به لیست</a>
				<?php if ( $reply_to ) : ?>
					<a href="mailto:<?php echo esc_attr( $reply_to ); ?>" class="button button-primary">پاسخ با ایمیل</a>
				<?php endif; ?>
			</p>

			<table class="widefat qa-cf-entry-table">
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<th><?php echo esc_html( $row['label'] ); ?></th>
							<td>
								<?php
								$value = is_array( $row['value'] ) ? implode( '، ', $row['value'] ) : $row['value'];
								if ( ! empty( $row['url'] ) ) {
									printf(
										'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
										esc_url( $row['url'] ),
										esc_html( $value )
									);
								} else {
									echo nl2br( esc_html( $value ) );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th>تاریخ دریافت</th>
						<td><?php echo esc_html( mysql2date( 'Y/m/d — H:i', $entry['created_at'] ) ); ?></td>
					</tr>
					<tr>
						<th>آدرس IP</th>
						<td dir="ltr"><?php echo ! empty( $entry['ip_address'] ) ? esc_html( $entry['ip_address'] ) : '—'; ?></td>
					</tr>
					<tr>
						<th>صفحه ارسال</th>
						<td dir="ltr"><?php echo ! empty( $entry['referer'] ) ? '<a href="' . esc_url( $entry['referer'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $entry['referer'] ) . '</a>' : '—'; ?></td>
					</tr>
					<tr>
						<th>مرورگر / دستگاه</th>
						<td dir="ltr" style="font-size:12px;color:#64748b"><?php echo ! empty( $entry['user_agent'] ) ? esc_html( $entry['user_agent'] ) : '—'; ?></td>
					</tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=qa-cf-entries' ) ); ?>" style="margin-top:18px" data-confirm-submit="این پیام برای همیشه حذف شود؟">
				<input type="hidden" name="page" value="qa-cf-entries">
				<?php wp_nonce_field( 'qa_cf_entry_actions' ); ?>
				<button type="submit" name="qa_cf_toggle_status" value="<?php echo absint( $id ); ?>" class="button">علامت‌گذاری به‌عنوان خوانده‌نشده</button>
				<button type="submit" name="qa_cf_delete_single" value="<?php echo absint( $id ); ?>" class="button" style="color:#b91c1c">حذف این پیام</button>
			</form>
		</div>
		<?php
	}

	/* ═════════════════ تب ۷: ابزارها ═════════════════ */

	public static function page_tools() {
		self::guard();

		$delete_on_uninstall = (int) get_option( 'qa_cf_delete_on_uninstall', 0 );
		$security            = qa_cf_security_settings();

		self::open_page( 'qa-cf-tools' );
		?>
		<div class="qa-cf-card">
			<h2 class="qa-cf-section-title"><span class="dashicons dashicons-download"></span> خروجی و پشتیبان</h2>
			<div class="qa-cf-tools-grid">

				<div class="qa-cf-tool">
					<h3>خروجی اکسل (CSV) از پیام‌ها</h3>
					<p>همه پیام‌ها را با فیلتر دلخواه دانلود کن. فایل با کدگذاری UTF-8 و BOM ساخته می‌شود تا فارسی در اکسل درست نمایش داده شود.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'qa_cf_export_csv' ); ?>
						<input type="hidden" name="action" value="qa_cf_export_csv">
						<p>
							<select name="status">
								<option value="">همه پیام‌ها</option>
								<option value="unread">فقط خوانده‌نشده</option>
								<option value="read">فقط خوانده‌شده</option>
							</select>
						</p>
						<p>
							از <input type="date" name="from"> تا <input type="date" name="to">
						</p>
						<button type="submit" class="button button-primary">دانلود CSV</button>
					</form>
				</div>

				<div class="qa-cf-tool">
					<h3>پشتیبان تنظیمات</h3>
					<p>یک فایل JSON از همه تنظیمات فرم (فیلدها، ظاهر، ایمیل، امنیت) بگیر تا روی سایت دیگری وارد کنی. رمز SMTP در فایل خروجی قرار نمی‌گیرد.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'qa_cf_export_settings' ); ?>
						<input type="hidden" name="action" value="qa_cf_export_settings">
						<button type="submit" class="button">دانلود فایل تنظیمات</button>
					</form>
				</div>

				<div class="qa-cf-tool">
					<h3>ورود تنظیمات</h3>
					<p>فایل JSON گرفته‌شده از همین پلاگین را انتخاب کن. تنظیمات فعلی جایگزین می‌شوند (پیام‌های ذخیره‌شده دست‌نخورده می‌مانند).</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" data-confirm-submit="تنظیمات فعلی با فایل انتخابی جایگزین شوند؟">
						<?php wp_nonce_field( 'qa_cf_import_settings' ); ?>
						<input type="hidden" name="action" value="qa_cf_import_settings">
						<p><input type="file" name="settings_file" accept=".json,application/json" required></p>
						<button type="submit" class="button">ورود تنظیمات</button>
					</form>
				</div>

			</div>
		</div>

		<div class="qa-cf-card">
			<h2 class="qa-cf-section-title"><span class="dashicons dashicons-info"></span> وضعیت سیستم</h2>
			<table class="widefat qa-cf-entry-table">
				<tbody>
					<tr><th>نسخه پلاگین</th><td dir="ltr"><?php echo esc_html( QA_CF_VERSION ); ?></td></tr>
					<tr><th>نسخه PHP</th><td dir="ltr"><?php echo esc_html( PHP_VERSION ); ?></td></tr>
					<tr><th>نسخه وردپرس</th><td dir="ltr"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
					<tr><th>جدول پیام‌ها</th><td><?php echo QA_CF_DB::table_exists() ? '<span class="qa-cf-ok-text">ساخته شده ✓</span>' : '<span class="qa-cf-err-text">ساخته نشده ✗</span>'; ?></td></tr>
					<tr><th>افزونه mbstring</th><td><?php echo function_exists( 'mb_substr' ) ? '<span class="qa-cf-ok-text">فعال ✓</span>' : 'غیرفعال (پلاگین با substr کار می‌کند)'; ?></td></tr>
					<tr><th>حداکثر آپلود سرور</th><td dir="ltr"><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></td></tr>
					<tr><th>پوشه آپلود فرم</th><td><?php echo file_exists( QA_CF_Uploads::base_dir() ) ? '<span class="qa-cf-ok-text">آماده ✓</span>' : 'هنوز ساخته نشده (با اولین آپلود ساخته می‌شود)'; ?></td></tr>
					<tr><th>کران وردپرس</th><td><?php echo wp_next_scheduled( 'qa_cf_daily_cleanup' ) ? '<span class="qa-cf-ok-text">زمان‌بندی شده ✓</span>' : 'زمان‌بندی نشده'; ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="qa-cf-card qa-cf-danger">
			<h2 class="qa-cf-section-title"><span class="dashicons dashicons-warning"></span> عملیات حساس</h2>
			<div class="qa-cf-tools-grid">

				<div class="qa-cf-tool">
					<h3>حذف پیام‌های قدیمی</h3>
					<p>
						<?php if ( absint( $security['auto_delete_days'] ) > 0 ) : ?>
							پیام‌های قدیمی‌تر از <strong><?php echo esc_html( qa_cf_fa_num( absint( $security['auto_delete_days'] ) ) ); ?> روز</strong> حذف می‌شوند (همان مقداری که در تب امنیت تنظیم کرده‌ای).
						<?php else : ?>
							ابتدا در تب «امنیت و حریم خصوصی» تعداد روزها را مشخص کن.
						<?php endif; ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm-submit="پیام‌های قدیمی حذف شوند؟ این کار برگشت‌پذیر نیست.">
						<?php wp_nonce_field( 'qa_cf_purge_entries' ); ?>
						<input type="hidden" name="action" value="qa_cf_purge_entries">
						<button type="submit" class="button" <?php disabled( absint( $security['auto_delete_days'] ) < 1 ); ?>>اجرای حذف قدیمی‌ها</button>
					</form>
				</div>

				<div class="qa-cf-tool">
					<h3>بازگشت به تنظیمات پیش‌فرض</h3>
					<p>همه تنظیمات (فیلدها، ظاهر، ایمیل، امنیت) به حالت اولیه برمی‌گردند. پیام‌های ذخیره‌شده حذف نمی‌شوند.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm-submit="همه تنظیمات به حالت پیش‌فرض برگردند؟">
						<?php wp_nonce_field( 'qa_cf_reset_settings' ); ?>
						<input type="hidden" name="action" value="qa_cf_reset_settings">
						<button type="submit" class="button">بازنشانی تنظیمات</button>
					</form>
				</div>

				<div class="qa-cf-tool">
					<h3>هنگام حذف کامل پلاگین</h3>
					<p>اگر این گزینه روشن باشد، با حذف پلاگین از وردپرس، جدول پیام‌ها، فایل‌های آپلودشده و تمام تنظیمات هم پاک می‌شوند.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'qa_cf_save_tools' ); ?>
						<input type="hidden" name="action" value="qa_cf_save_tools">
						<p>
							<label>
								<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $delete_on_uninstall, 1 ); ?>>
								با حذف پلاگین، همه داده‌ها هم پاک شوند
							</label>
						</p>
						<button type="submit" class="button">ذخیره این گزینه</button>
					</form>
				</div>

			</div>
		</div>
		<?php
		self::close_page();
	}

	public static function save_tools() {
		self::guard();
		check_admin_referer( 'qa_cf_save_tools' );
		update_option( 'qa_cf_delete_on_uninstall', ! empty( $_POST['delete_on_uninstall'] ) ? 1 : 0 );
		self::redirect( 'qa-cf-tools', array( 'qa_cf_msg' => 'saved' ) );
	}

	public static function purge_entries() {
		self::guard();
		check_admin_referer( 'qa_cf_purge_entries' );

		$security = qa_cf_security_settings();
		QA_CF_DB::purge_old( $security['auto_delete_days'] );

		self::redirect( 'qa-cf-tools', array( 'qa_cf_msg' => 'purged' ) );
	}

	public static function export_settings() {
		self::guard();
		check_admin_referer( 'qa_cf_export_settings' );

		$smtp = qa_cf_smtp_settings();
		unset( $smtp['password'] ); // رمز هرگز در فایل خروجی نوشته نمی‌شود.

		$data = array(
			'plugin'       => 'qa-contact-form',
			'version'      => QA_CF_VERSION,
			'exported_at'  => current_time( 'mysql' ),
			'fields'       => qa_cf_fields(),
			'form'         => qa_cf_form_settings(),
			'email'        => qa_cf_email_settings(),
			'smtp'         => $smtp,
			'security'     => qa_cf_security_settings(),
			'integrations' => qa_cf_integration_settings(),
			'design'       => qa_cf_design_settings(),
			'custom_css'   => (string) get_option( 'qa_cf_custom_css', '' ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=qa-contact-form-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function import_settings() {
		self::guard();
		check_admin_referer( 'qa_cf_import_settings' );

		if ( empty( $_FILES['settings_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['settings_file']['tmp_name'] ) ) {
			self::redirect( 'qa-cf-tools', array( 'qa_cf_msg' => 'badfile' ) );
		}

		$raw  = file_get_contents( $_FILES['settings_file']['tmp_name'] ); // phpcs:ignore
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || empty( $data['plugin'] ) || 'qa-contact-form' !== $data['plugin'] ) {
			self::redirect( 'qa-cf-tools', array( 'qa_cf_msg' => 'badfile' ) );
		}

		if ( ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
			$clean = array();
			foreach ( $data['fields'] as $field ) {
				if ( ! is_array( $field ) || empty( $field['label'] ) ) {
					continue;
				}
				$field         = wp_parse_args( $field, qa_cf_default_field() );
				$field['key']  = sanitize_key( $field['key'] ? $field['key'] : $field['label'] );
				$field['type'] = array_key_exists( $field['type'], QA_CF_DB::field_types() ) ? $field['type'] : 'text';
				if ( '' !== $field['key'] ) {
					$clean[] = $field;
				}
			}
			if ( ! empty( $clean ) ) {
				update_option( 'qa_cf_fields', $clean );
			}
		}

		$map = array(
			'form'         => array( 'qa_cf_form_settings', 'qa_cf_default_form_settings' ),
			'email'        => array( 'qa_cf_email_settings', 'qa_cf_default_email_settings' ),
			'security'     => array( 'qa_cf_security_settings', 'qa_cf_default_security_settings' ),
			'integrations' => array( 'qa_cf_integration_settings', 'qa_cf_default_integration_settings' ),
			'design'       => array( 'qa_cf_design_settings', 'qa_cf_default_design_settings' ),
		);

		foreach ( $map as $key => $target ) {
			if ( ! empty( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				// فقط کلیدهای شناخته‌شده پذیرفته می‌شوند.
				$defaults = call_user_func( $target[1] );
				$values   = array_intersect_key( $data[ $key ], $defaults );
				update_option( $target[0], wp_parse_args( $values, $defaults ) );
			}
		}

		if ( isset( $data['custom_css'] ) ) {
			update_option( 'qa_cf_custom_css', qa_cf_safe_substr( wp_strip_all_tags( (string) $data['custom_css'] ), 20000 ) );
		}

		self::redirect( 'qa-cf-tools', array( 'qa_cf_msg' => 'imported' ) );
	}

	public static function reset_settings() {
		self::guard();
		check_admin_referer( 'qa_cf_reset_settings' );

		update_option( 'qa_cf_fields', QA_CF_DB::default_fields() );
		update_option( 'qa_cf_form_settings', qa_cf_default_form_settings() );
		update_option( 'qa_cf_email_settings', qa_cf_default_email_settings() );
		update_option( 'qa_cf_security_settings', qa_cf_default_security_settings() );
		update_option( 'qa_cf_design_settings', qa_cf_default_design_settings() );
		update_option( 'qa_cf_custom_css', '' );

		self::redirect( 'qa-cf-tools', array( 'qa_cf_msg' => 'reset' ) );
	}
}

/* ══════════════════════════════════════════════════════════════════════
 * ۱۴) فعال‌سازی، ارتقا، کران و حذف
 * ══════════════════════════════════════════════════════════════════════ */

function qa_cf_activate() {
	QA_CF_DB::create_table();
	QA_CF_DB::table_exists( true );

	$defaults = array(
		'qa_cf_fields'               => QA_CF_DB::default_fields(),
		'qa_cf_form_settings'        => qa_cf_default_form_settings(),
		'qa_cf_email_settings'       => qa_cf_default_email_settings(),
		'qa_cf_security_settings'    => qa_cf_default_security_settings(),
		'qa_cf_design_settings'      => qa_cf_default_design_settings(),
		'qa_cf_custom_css'           => '',
		'qa_cf_delete_on_uninstall'  => 0,
	);

	foreach ( $defaults as $option => $value ) {
		if ( false === get_option( $option, false ) ) {
			update_option( $option, $value );
		}
	}

	if ( false === get_option( 'qa_cf_smtp_settings', false ) ) {
		update_option( 'qa_cf_smtp_settings', qa_cf_default_smtp_settings(), false );
	}
	if ( false === get_option( 'qa_cf_integration_settings', false ) ) {
		update_option( 'qa_cf_integration_settings', qa_cf_default_integration_settings(), false );
	}

	QA_CF_Uploads::ensure_dir();

	if ( ! wp_next_scheduled( 'qa_cf_daily_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'qa_cf_daily_cleanup' );
	}

	qa_cf_migrate_legacy();

	update_option( 'qa_cf_db_version', QA_CF_VERSION );
}
register_activation_hook( QA_CF_FILE, 'qa_cf_activate' );

function qa_cf_deactivate() {
	$timestamp = wp_next_scheduled( 'qa_cf_daily_cleanup' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'qa_cf_daily_cleanup' );
	}
}
register_deactivation_hook( QA_CF_FILE, 'qa_cf_deactivate' );

/**
 * انتقال تنظیمات نسخه‌های ۱.x به ساختار گروه‌بندی‌شده‌ی ۲.x.
 *
 * تا نسخه ۱.۸ همه‌چیز داخل qa_cf_form_settings بود. این تابع کلیدهای
 * ایمیل و ضداسپم را به option های اختصاصی خودشان منتقل می‌کند و فیلدهای
 * قدیمی را با ساختار جدید فیلد (که کلیدهای بیشتری دارد) هماهنگ می‌کند.
 * چند بار اجرا شدن این تابع مشکلی ایجاد نمی‌کند (idempotent است).
 */
function qa_cf_migrate_legacy() {
	$legacy = get_option( 'qa_cf_form_settings', array() );
	$legacy = is_array( $legacy ) ? $legacy : array();

	if ( false === get_option( 'qa_cf_email_settings', false ) ) {
		$email = qa_cf_default_email_settings();
		foreach ( array( 'notify_email', 'email_subject', 'from_email', 'from_name', 'reply_to_mode', 'reply_to_fixed' ) as $key ) {
			if ( isset( $legacy[ $key ] ) && '' !== $legacy[ $key ] ) {
				$email[ $key ] = $legacy[ $key ];
			}
		}
		update_option( 'qa_cf_email_settings', $email );
	}

	if ( false === get_option( 'qa_cf_security_settings', false ) ) {
		$security = qa_cf_default_security_settings();
		if ( isset( $legacy['captcha_enabled'] ) ) {
			$security['captcha_enabled'] = ! empty( $legacy['captcha_enabled'] ) ? 1 : 0;
		}
		update_option( 'qa_cf_security_settings', $security );
	}

	// کلیدهای ایمیل از آرایه‌ی فرم پاک می‌شوند تا دو منبع حقیقت نداشته باشیم.
	$form = wp_parse_args( $legacy, qa_cf_default_form_settings() );
	foreach ( array( 'notify_email', 'email_subject', 'from_email', 'from_name', 'reply_to_mode', 'reply_to_fixed', 'captcha_enabled' ) as $key ) {
		unset( $form[ $key ] );
	}
	update_option( 'qa_cf_form_settings', $form );

	// هماهنگ‌سازی ساختار فیلدها با کلیدهای جدید.
	$fields = get_option( 'qa_cf_fields', array() );
	if ( is_array( $fields ) && ! empty( $fields ) ) {
		$clean = array();
		foreach ( $fields as $field ) {
			if ( is_array( $field ) && ! empty( $field['key'] ) ) {
				$clean[] = wp_parse_args( $field, qa_cf_default_field() );
			}
		}
		if ( ! empty( $clean ) ) {
			update_option( 'qa_cf_fields', $clean );
		}
	}
}

function qa_cf_maybe_upgrade() {
	if ( get_option( 'qa_cf_db_version' ) === QA_CF_VERSION ) {
		return;
	}

	QA_CF_DB::create_table();
	QA_CF_DB::table_exists( true );

	if ( false === get_option( 'qa_cf_fields', false ) ) {
		update_option( 'qa_cf_fields', QA_CF_DB::default_fields() );
	}

	qa_cf_migrate_legacy();

	// اطمینان از وجود همه‌ی گروه‌های تنظیمات.
	update_option( 'qa_cf_form_settings', qa_cf_form_settings() );
	update_option( 'qa_cf_email_settings', qa_cf_email_settings() );
	update_option( 'qa_cf_security_settings', qa_cf_security_settings() );
	update_option( 'qa_cf_design_settings', qa_cf_design_settings() );
	update_option( 'qa_cf_smtp_settings', qa_cf_smtp_settings(), false );
	update_option( 'qa_cf_integration_settings', qa_cf_integration_settings(), false );

	if ( false === get_option( 'qa_cf_custom_css', false ) ) {
		update_option( 'qa_cf_custom_css', '' );
	}
	if ( false === get_option( 'qa_cf_delete_on_uninstall', false ) ) {
		update_option( 'qa_cf_delete_on_uninstall', 0 );
	}

	if ( ! wp_next_scheduled( 'qa_cf_daily_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'qa_cf_daily_cleanup' );
	}

	update_option( 'qa_cf_db_version', QA_CF_VERSION );
}
add_action( 'plugins_loaded', 'qa_cf_maybe_upgrade', 20 );

/**
 * نگهداشت روزانه: حذف خودکار پیام‌های قدیمی (در صورت فعال بودن).
 */
function qa_cf_daily_cleanup() {
	$security = qa_cf_security_settings();
	if ( absint( $security['auto_delete_days'] ) > 0 ) {
		QA_CF_DB::purge_old( $security['auto_delete_days'] );
	}
}
add_action( 'qa_cf_daily_cleanup', 'qa_cf_daily_cleanup' );

/**
 * حذف کامل پلاگین.
 */
function qa_cf_uninstall() {
	if ( ! get_option( 'qa_cf_delete_on_uninstall', 0 ) ) {
		return;
	}

	QA_CF_DB::drop_table();

	$options = array(
		'qa_cf_fields',
		'qa_cf_form_settings',
		'qa_cf_email_settings',
		'qa_cf_smtp_settings',
		'qa_cf_security_settings',
		'qa_cf_integration_settings',
		'qa_cf_design_settings',
		'qa_cf_custom_css',
		'qa_cf_delete_on_uninstall',
		'qa_cf_db_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// حذف فایل‌های آپلودشده‌ی فرم.
	$dir = QA_CF_Uploads::base_dir();
	if ( is_dir( $dir ) ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				@rmdir( $file->getRealPath() ); // phpcs:ignore
			} else {
				@unlink( $file->getRealPath() ); // phpcs:ignore
			}
		}
		@rmdir( $dir ); // phpcs:ignore
	}

	$timestamp = wp_next_scheduled( 'qa_cf_daily_cleanup' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'qa_cf_daily_cleanup' );
	}
}
register_uninstall_hook( QA_CF_FILE, 'qa_cf_uninstall' );

/* ══════════════════════════════════════════════════════════════════════
 * ۱۵) راه‌اندازی
 * ══════════════════════════════════════════════════════════════════════ */

function qa_cf_load_textdomain() {
	load_plugin_textdomain( 'qa-contact-form', false, dirname( plugin_basename( QA_CF_FILE ) ) . '/languages' );
}
add_action( 'init', 'qa_cf_load_textdomain' );

function qa_cf_plugin_action_links( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=qa-cf-fields' ) ) . '">تنظیمات</a>',
		'<a href="' . esc_url( admin_url( 'admin.php?page=qa-cf-entries' ) ) . '">پیام‌ها</a>'
	);
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( QA_CF_FILE ), 'qa_cf_plugin_action_links' );

QA_CF_Mailer::init();
QA_CF_Shortcode::init();

if ( is_admin() ) {
	QA_CF_Admin::init();
}
