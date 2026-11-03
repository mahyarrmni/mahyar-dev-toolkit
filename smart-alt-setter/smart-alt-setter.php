<?php
/**
 * Plugin Name:       آلت تصویر مهیار (Smart Alt Setter)
 * Plugin URI:        https://github.com/mahyarrmni/smart-alt-setter
 * Description:       افزودن گروهی متن ALT فقط به تصاویری که در پست‌ها/صفحات منتشرشده استفاده شده‌اند و هنوز ALT ندارند.
 * Version:           1.1.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Mahyar Momeni
 * Author URI:        https://mahyarhub.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smart-alt-setter
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'mahyar_alt_setter_menu' );

function mahyar_alt_setter_menu() {
	add_menu_page(
		'آلت تصویر مهیار',
		'آلت تصویر مهیار',
		'manage_options',
		'mahyar-alt-setter',
		'mahyar_alt_setter_page',
		'dashicons-format-image',
		90
	);
}

/**
 * IDs of images referenced (via the wp-image-N class) inside published posts and pages.
 *
 * @return int[]
 */
function mahyar_alt_setter_used_image_ids() {
	global $wpdb;

	$post_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND post_status = 'publish'"
	);

	$used = array();
	foreach ( $post_ids as $post_id ) {
		$content = get_post_field( 'post_content', $post_id );
		if ( preg_match_all( '/wp-image-([0-9]+)/', (string) $content, $matches ) ) {
			$used = array_merge( $used, $matches[1] );
		}
	}

	return array_values( array_unique( array_map( 'absint', $used ) ) );
}

function mahyar_alt_setter_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result_msg = '';

	if ( isset( $_POST['mahyar_alt_submit'] ) ) {
		check_admin_referer( 'mahyar_alt_setter_action' );

		$alt_texts = array();

		if ( ! empty( $_FILES['csv_file']['tmp_name'] ) && is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			$file = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
			if ( $file ) {
				while ( ( $line = fgetcsv( $file ) ) !== false ) {
					$alt_texts[] = sanitize_text_field( trim( (string) ( $line[0] ?? '' ) ) );
				}
				fclose( $file );
			}
		} elseif ( isset( $_POST['alt_texts'] ) ) {
			$alt_texts = array_map(
				'sanitize_text_field',
				array_map( 'trim', explode( "\n", wp_unslash( $_POST['alt_texts'] ) ) )
			);
		}

		$alt_texts = array_values( array_filter( $alt_texts, 'strlen' ) );

		$i = 0;
		foreach ( mahyar_alt_setter_used_image_ids() as $img_id ) {
			if ( $i >= count( $alt_texts ) ) {
				break;
			}
			$current_alt = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
			if ( '' === trim( (string) $current_alt ) ) {
				update_post_meta( $img_id, '_wp_attachment_image_alt', $alt_texts[ $i ] );
				$i++;
			}
		}

		$result_msg = "<div class='notice notice-success is-dismissible'><p>متن ALT به " . (int) $i . ' تصویر استفاده‌شده افزوده شد.</p></div>';
	}

	// Live count so you know how many ALT lines to prepare.
	$missing = 0;
	foreach ( mahyar_alt_setter_used_image_ids() as $img_id ) {
		if ( '' === trim( (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true ) ) ) {
			$missing++;
		}
	}
	?>
	<div class="wrap" dir="rtl">
		<h1>آلت تصویر مهیار</h1>
		<?php echo wp_kses_post( $result_msg ); ?>
		<p>تعداد تصاویر استفاده‌شده در نوشته‌ها/برگه‌های منتشرشده که هنوز ALT ندارند: <strong><?php echo (int) $missing; ?></strong></p>
		<p class="description">⚠️ متن‌ها <strong>به ترتیب</strong> به تصاویر داده می‌شوند و ابزار محتوای تصویر را نمی‌فهمد. ترتیب تصاویر همان ترتیب پیدا شدن در نوشته‌هاست. قبل از اجرا بکاپ بگیر و بعد از اجرا چند نمونه را بررسی کن.</p>
		<form method="post" enctype="multipart/form-data" style="background:#fff;padding:20px;border:1px solid #ccc;border-radius:10px;max-width:700px;">
			<?php wp_nonce_field( 'mahyar_alt_setter_action' ); ?>
			<h2>وارد کردن ALT به صورت دستی</h2>
			<textarea name="alt_texts" rows="10" style="width:100%;font-family:monospace;" placeholder="هر ALT در یک خط وارد شود..."></textarea>

			<h2 style="margin-top:30px;">یا آپلود فایل CSV</h2>
			<p class="description">ستون اول هر ردیف به‌عنوان یک متن ALT خوانده می‌شود.</p>
			<input type="file" name="csv_file" accept=".csv" style="margin-bottom:20px;"><br>

			<input type="submit" name="mahyar_alt_submit" value="افزودن ALTها" class="button button-primary button-large">
		</form>
	</div>
	<?php
}
