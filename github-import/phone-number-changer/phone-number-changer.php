<?php
/**
 * Plugin Name: تعویض شماره تماس (Phone Number Changer)
 * Description: جایگزینی شماره تماس در کل سایت با تمام فرمت‌های رایج نوشتاری (09..، +98..، 0098..، 98.. و غیره). دو حالت دارد: موقت (فقط در خروجی HTML، دیتابیس دست‌نخورده می‌ماند) و دائمی (تغییر واقعی در دیتابیس).
 * Version: 1.1
 * Author: Mahyar Momeni
 * Plugin URI: https://github.com/mahyarrmni/phone-number-changer
 * Author URI: https://mahyarhub.ir
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: phone-number-changer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PNC_VERSION', '1.1' );
define( 'PNC_OPT_OLD', 'pnc_old_number' );
define( 'PNC_OPT_NEW', 'pnc_new_number' );
define( 'PNC_OPT_TEMP_ENABLED', 'pnc_temp_enabled' );

/* ------------------------------------------------------------------
 * توابع کمکی: نرمال‌سازی شماره و تولید حالت‌های مختلف نوشتاری
 * ------------------------------------------------------------------ */

/**
 * ورودی خام کاربر رو به هسته ۱۰ رقمی (بدون صفر و کد کشور) تبدیل می‌کند.
 * مثال: 09123456789 یا +989123456789 یا 989123456789 -> 9123456789
 */
function pnc_normalize_core( $raw ) {
	$digits = preg_replace( '/\D/', '', (string) $raw );

	if ( substr( $digits, 0, 2 ) === '00' ) {
		$digits = substr( $digits, 2 );
	}
	if ( strlen( $digits ) === 12 && substr( $digits, 0, 2 ) === '98' ) {
		$digits = substr( $digits, 2 );
	}
	if ( strlen( $digits ) === 11 && substr( $digits, 0, 1 ) === '0' ) {
		$digits = substr( $digits, 1 );
	}
	return $digits;
}

/**
 * از هسته ۱۰ رقمی، تمام حالت‌های رایج نوشتاری شماره رو تولید می‌کند.
 */
function pnc_generate_variants( $core ) {
	$core = preg_replace( '/\D/', '', (string) $core );
	if ( strlen( $core ) < 10 ) return array();

	$p1 = substr( $core, 0, 3 );
	$p2 = substr( $core, 3, 3 );
	$p3 = substr( $core, 6 );

	return array(
		$core,
		'0' . $core,
		'00' . '98' . $core,
		'98' . $core,
		'+98' . $core,
		'0' . $p1 . ' ' . $p2 . ' ' . $p3,
		'0' . $p1 . '-' . $p2 . '-' . $p3,
		'+98 ' . $p1 . ' ' . $p2 . ' ' . $p3,
		'0' . $p1 . ' ' . $p2 . $p3,
		'0' . $p1 . '.' . $p2 . '.' . $p3,
	);
}

/**
 * آرایه‌ی جفت (قدیم => جدید) رو برای strtr/str_replace آماده می‌کند.
 * strtr با کلیدهای طولانی‌تر رو اول در نظر می‌گیرد، پس نیازی به مرتب‌سازی دستی نیست.
 */
function pnc_get_pairs() {
	$old_core = pnc_normalize_core( get_option( PNC_OPT_OLD, '' ) );
	$new_core = pnc_normalize_core( get_option( PNC_OPT_NEW, '' ) );
	if ( ! $old_core || ! $new_core ) return array();

	$old_variants = pnc_generate_variants( $old_core );
	$new_variants = pnc_generate_variants( $new_core );
	if ( count( $old_variants ) !== count( $new_variants ) ) return array();

	return array_combine( $old_variants, $new_variants );
}

/* ------------------------------------------------------------------
 * حالت موقت: جایگزینی فقط در خروجی نهایی HTML (دیتابیس دست‌نخورده)
 * ------------------------------------------------------------------ */

function pnc_ob_replace( $buffer ) {
	$pairs = pnc_get_pairs();
	if ( empty( $pairs ) ) return $buffer;
	return strtr( $buffer, $pairs );
}

add_action( 'template_redirect', function () {
	if ( is_admin() ) return;
	if ( ! get_option( PNC_OPT_TEMP_ENABLED ) ) return;
	if ( empty( pnc_get_pairs() ) ) return;
	ob_start( 'pnc_ob_replace' );
}, 1 );

/* ------------------------------------------------------------------
 * حالت دائمی: جایگزینی واقعی در دیتابیس (پست‌ها، متا، آپشن‌ها)
 * ------------------------------------------------------------------ */

function pnc_replace_scalar( $value, $pairs ) {
	if ( ! is_string( $value ) ) return $value;
	return strtr( $value, $pairs );
}

/**
 * جایگزینی بازگشتی برای مقادیر سریالایز شده یا آرایه/آبجکت.
 */
function pnc_replace_recursive( $data, $pairs ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $k => $v ) {
			$data[ $k ] = pnc_replace_recursive( $v, $pairs );
		}
		return $data;
	}
	if ( is_object( $data ) ) {
		foreach ( $data as $k => $v ) {
			$data->$k = pnc_replace_recursive( $v, $pairs );
		}
		return $data;
	}
	return pnc_replace_scalar( $data, $pairs );
}

function pnc_process_value( $value, $pairs ) {
	if ( is_serialized( $value ) ) {
		$unserialized = @unserialize( $value );
		if ( $unserialized !== false || $value === 'b:0;' ) {
			$replaced = pnc_replace_recursive( $unserialized, $pairs );
			return serialize( $replaced );
		}
	}
	return pnc_replace_scalar( $value, $pairs );
}

/**
 * اجرای جایگزینی روی دیتابیس. اگر $dry_run true باشد، چیزی ذخیره نمی‌شود
 * و فقط گزارش برمی‌گردد.
 */
function pnc_run_database_replace( $dry_run = true ) {
	global $wpdb;
	$pairs = pnc_get_pairs();
	if ( empty( $pairs ) ) {
		return array( 'error' => 'شماره قدیم یا جدید معتبر نیست.' );
	}
	$old_variants = array_keys( $pairs );

	$targets = array(
		array( 'table' => $wpdb->posts,    'column' => 'post_content', 'id' => 'ID' ),
		array( 'table' => $wpdb->posts,    'column' => 'post_excerpt', 'id' => 'ID' ),
		array( 'table' => $wpdb->posts,    'column' => 'post_title',   'id' => 'ID' ),
		array( 'table' => $wpdb->postmeta, 'column' => 'meta_value',   'id' => 'meta_id' ),
		array( 'table' => $wpdb->options,  'column' => 'option_value', 'id' => 'option_id' ),
	);

	$total_rows = 0;
	$report     = array();

	foreach ( $targets as $t ) {
		$table  = $t['table'];
		$column = $t['column'];
		$idcol  = $t['id'];

		$like_clauses = array();
		$like_params  = array();
		foreach ( $old_variants as $v ) {
			$like_clauses[] = "$column LIKE %s";
			$like_params[]  = '%' . $wpdb->esc_like( $v ) . '%';
		}
		$sql  = "SELECT $idcol AS row_id, $column AS val FROM $table WHERE " . implode( ' OR ', $like_clauses );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $like_params ) );

		foreach ( $rows as $row ) {
			$original = $row->val;
			$updated  = pnc_process_value( $original, $pairs );

			if ( $updated !== $original ) {
				$total_rows++;
				$report[] = array(
					'table'  => $table,
					'column' => $column,
					'id'     => $row->row_id,
					'before' => mb_substr( wp_strip_all_tags( (string) $original ), 0, 100 ),
					'after'  => mb_substr( wp_strip_all_tags( (string) $updated ), 0, 100 ),
				);
				if ( ! $dry_run ) {
					$wpdb->update( $table, array( $column => $updated ), array( $idcol => $row->row_id ) );
				}
			}
		}
	}

	return array(
		'total'  => $total_rows,
		'report' => array_slice( $report, 0, 200 ), // برای جلوگیری از سنگین شدن خروجی
		'dry_run'=> $dry_run,
	);
}

/* ------------------------------------------------------------------
 * پنل مدیریت
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_management_page(
		'تعویض شماره تماس',
		'تعویض شماره تماس',
		'manage_options',
		'phone-number-changer',
		'pnc_render_admin_page'
	);
} );

add_action( 'admin_init', function () {
	if ( ! isset( $_POST['pnc_save_settings'] ) ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;
	check_admin_referer( 'pnc_save_settings_action' );

	update_option( PNC_OPT_OLD, sanitize_text_field( $_POST['pnc_old'] ?? '' ) );
	update_option( PNC_OPT_NEW, sanitize_text_field( $_POST['pnc_new'] ?? '' ) );
	update_option( PNC_OPT_TEMP_ENABLED, isset( $_POST['pnc_temp_enabled'] ) ? 1 : 0 );

	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div>';
	} );
} );

function pnc_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$old          = get_option( PNC_OPT_OLD, '' );
	$new          = get_option( PNC_OPT_NEW, '' );
	$temp_enabled = get_option( PNC_OPT_TEMP_ENABLED );
	$pairs        = pnc_get_pairs();
	?>
	<div class="wrap" dir="rtl" style="max-width:800px">
		<h1>تعویض شماره تماس <small style="font-size:13px;color:#777">نسخه <?php echo esc_html( PNC_VERSION ); ?></small></h1>

		<form method="post">
			<?php wp_nonce_field( 'pnc_save_settings_action' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="pnc_old">شماره قدیم</label></th>
					<td>
						<input type="text" id="pnc_old" name="pnc_old" value="<?php echo esc_attr( $old ); ?>" class="regular-text" placeholder="09123456789" required>
						<p class="description">به هر فرمتی وارد کن (با یا بدون صفر، +98، فاصله). خودکار تشخیص داده می‌شود.</p>
					</td>
				</tr>
				<tr>
					<th><label for="pnc_new">شماره جدید</label></th>
					<td>
						<input type="text" id="pnc_new" name="pnc_new" value="<?php echo esc_attr( $new ); ?>" class="regular-text" placeholder="09129876543" required>
					</td>
				</tr>
				<tr>
					<th>حالت موقت (زنده)</th>
					<td>
						<label>
							<input type="checkbox" name="pnc_temp_enabled" value="1" <?php checked( $temp_enabled, 1 ); ?>>
							فعال باشد
						</label>
						<p class="description">
							در این حالت شماره فقط در <strong>خروجی نهایی صفحه</strong> (هدر، فوتر، محتوا، ویجت، همه‌جا) جایگزین می‌شود
							و دیتابیس دست‌نخورده باقی می‌ماند. هر وقت غیرفعالش کنی، شماره قدیم دوباره برمی‌گردد. کاملاً برگشت‌پذیر و بی‌خطر است.
						</p>
					</td>
				</tr>
			</table>
			<p><button type="submit" name="pnc_save_settings" class="button button-primary">ذخیره تنظیمات</button></p>
		</form>

		<hr>

		<h2>حالت دائمی (تغییر واقعی دیتابیس)</h2>
		<p class="description">
			این بخش شماره رو مستقیماً توی جدول‌های پست‌ها، متا و آپشن‌ها جایگزین می‌کند و <strong>غیرقابل بازگشت</strong> است
			(مگر با بکاپ). قبل از «اجرای دائمی»، حتماً «بررسی» بزن و از دیتابیس بکاپ بگیر.
		</p>

		<?php if ( empty( $pairs ) ) : ?>
			<p style="color:#b32d2e">اول شماره قدیم و جدید رو بالا ذخیره کن.</p>
		<?php else : ?>
			<p>
				<button type="button" class="button" id="pnc-dry-run">بررسی (Dry Run — بدون تغییر)</button>
				<button type="button" class="button button-primary" id="pnc-apply" style="background:#b32d2e;border-color:#b32d2e">
					اجرای دائمی روی دیتابیس
				</button>
			</p>
			<div id="pnc-result" style="margin-top:15px"></div>
		<?php endif; ?>
	</div>

	<script>
	(function(){
		const nonce = '<?php echo esc_js( wp_create_nonce( 'pnc_ajax_action' ) ); ?>';
		const ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		const resultBox = document.getElementById('pnc-result');

		function renderReport(data) {
			if (data.error) {
				resultBox.innerHTML = '<p style="color:#b32d2e">' + data.error + '</p>';
				return;
			}
			let html = '<p><strong>' + (data.dry_run ? 'حالت بررسی — چیزی تغییر نکرد.' : 'اجرا شد.') + '</strong> ';
			html += 'تعداد ردیف‌های ' + (data.dry_run ? 'قابل تغییر' : 'تغییریافته') + ': ' + data.total + '</p>';
			if (data.report && data.report.length) {
				html += '<table class="widefat"><thead><tr><th>جدول</th><th>ستون</th><th>ID</th><th>قبل</th><th>بعد</th></tr></thead><tbody>';
				data.report.forEach(function(r){
					html += '<tr><td>' + r.table + '</td><td>' + r.column + '</td><td>' + r.id + '</td>' +
						'<td style="max-width:250px;word-break:break-word">' + r.before + '</td>' +
						'<td style="max-width:250px;word-break:break-word">' + r.after + '</td></tr>';
				});
				html += '</tbody></table>';
			}
			resultBox.innerHTML = html;
		}

		function callAjax(action) {
			resultBox.innerHTML = '<p>در حال پردازش…</p>';
			const body = new URLSearchParams({ action: action, nonce: nonce });
			fetch(ajaxUrl, { method: 'POST', body: body })
				.then(r => r.json())
				.then(res => renderReport(res.data || res))
				.catch(() => { resultBox.innerHTML = '<p style="color:#b32d2e">خطا در ارتباط.</p>'; });
		}

		const dryBtn = document.getElementById('pnc-dry-run');
		if (dryBtn) dryBtn.addEventListener('click', function(){ callAjax('pnc_dry_run'); });

		const applyBtn = document.getElementById('pnc-apply');
		if (applyBtn) applyBtn.addEventListener('click', function(){
			if (!confirm('مطمئنی؟ این کار مستقیم دیتابیس رو تغییر می‌ده و برگشت‌پذیر نیست (مگر با بکاپ). قبلش بکاپ گرفتی؟')) return;
			callAjax('pnc_apply');
		});
	})();
	</script>
	<?php
}

add_action( 'wp_ajax_pnc_dry_run', function () {
	check_ajax_referer( 'pnc_ajax_action', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'دسترسی ندارید' );
	wp_send_json_success( pnc_run_database_replace( true ) );
} );

add_action( 'wp_ajax_pnc_apply', function () {
	check_ajax_referer( 'pnc_ajax_action', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'دسترسی ندارید' );
	wp_send_json_success( pnc_run_database_replace( false ) );
} );
