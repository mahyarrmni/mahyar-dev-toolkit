<?php
/**
 * Plugin Name:       آلت گروهی تصویر (Bulk Image Alt)
 * Plugin URI:        https://github.com/mahyarrmni/bulk-image-alt
 * Description:       افزودن گروهی متن ALT به تصاویر کتابخانه‌ی رسانه که ALT ندارند، همراه با گزارش تصاویر بدون ALT و امکان پاک‌کردن همه‌ی ALTها (با تأیید).
 * Version:           1.3.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Mahyar Momeni
 * Author URI:        https://mahyarhub.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bulk-image-alt
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'bulk_image_alt_menu' );

function bulk_image_alt_menu() {
	add_menu_page(
		'آلت گروهی تصویر',
		'آلت گروهی تصویر',
		'manage_options',
		'bulk-image-alt',
		'bulk_image_alt_page',
		'dashicons-images-alt2'
	);
}

function bulk_image_alt_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$result_msg = '';

	// Destructive action: wipe every ALT text (nonce + explicit confirmation).
	if ( isset( $_POST['delete_all_alts'] ) ) {
		check_admin_referer( 'bulk_image_alt_delete' );

		if ( isset( $_POST['confirm_delete'] ) && 'yes' === $_POST['confirm_delete'] ) {
			$image_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );
			foreach ( $image_ids as $img_id ) {
				delete_post_meta( $img_id, '_wp_attachment_image_alt' );
			}
			$result_msg .= "<div class='notice notice-success'><p>تمام متون ALT با موفقیت حذف شدند.</p></div>";
		} else {
			$result_msg .= "<div class='notice notice-error'><p>لطفاً برای حذف ALT تایید را بزنید.</p></div>";
		}
	}

	// Add ALT texts to images that have none.
	if ( isset( $_POST['bia_submit'] ) ) {
		check_admin_referer( 'bulk_image_alt_add' );

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

		$image_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );

		$i = 0;
		foreach ( $image_ids as $img_id ) {
			if ( $i >= count( $alt_texts ) ) {
				break;
			}
			$current_alt = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
			if ( '' === trim( (string) $current_alt ) ) {
				update_post_meta( $img_id, '_wp_attachment_image_alt', $alt_texts[ $i ] );
				$i++;
			}
		}
		$result_msg .= "<div class='notice notice-success'><p>متن ALT به " . (int) $i . ' تصویر بدون ALT اضافه شد.</p></div>';
	}

	// Report: counts and the list of images without ALT.
	$all_images      = $wpdb->get_results( "SELECT ID, post_title, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );
	$total           = count( $all_images );
	$with_alt        = 0;
	$without_alt     = 0;
	$missing_alt_list = array();

	foreach ( $all_images as $image ) {
		$alt = get_post_meta( $image->ID, '_wp_attachment_image_alt', true );
		if ( '' !== trim( (string) $alt ) ) {
			$with_alt++;
		} else {
			$without_alt++;
			$missing_alt_list[] = $image;
		}
	}
	?>
	<div class="wrap" dir="rtl">
		<h1>آلت گروهی تصویر</h1>
		<?php echo wp_kses_post( $result_msg ); ?>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'bulk_image_alt_add' ); ?>
			<h2>افزودن متن ALT</h2>
			<p>برای افزودن متن ALT به تصاویر بدون ALT، یک فایل CSV (ستون اول) آپلود کن یا متن‌ها را دستی وارد کن.</p>
			<p class="description">⚠️ متن‌ها <strong>به ترتیب</strong> به تصاویر داده می‌شوند و ابزار محتوای تصویر را نمی‌فهمد. قبل از اجرا بکاپ بگیر.</p>
			<textarea name="alt_texts" rows="10" cols="80" placeholder="هر ALT در یک خط وارد شود..."></textarea><br><br>
			<input type="file" name="csv_file" accept=".csv"><br><br>
			<input type="submit" name="bia_submit" value="افزودن ALTها" class="button button-primary">
		</form>

		<hr>

		<form method="post" style="margin-top:30px;">
			<?php wp_nonce_field( 'bulk_image_alt_delete' ); ?>
			<h2 style="color:red;">حذف تمام متن‌های ALT</h2>
			<p>این عمل غیرقابل بازگشت است. برای ادامه لطفاً تایید کن:</p>
			<label><input type="checkbox" name="confirm_delete" value="yes"> تایید حذف</label><br><br>
			<input type="submit" name="delete_all_alts" value="حذف تمام ALTها" class="button" style="background:red;color:#fff;">
		</form>

		<hr>

		<h2>گزارش ALT تصاویر</h2>
		<p><strong>تعداد کل تصاویر:</strong> <?php echo (int) $total; ?><br>
		<strong>با ALT:</strong> <?php echo (int) $with_alt; ?><br>
		<strong>بدون ALT:</strong> <?php echo (int) $without_alt; ?></p>

		<?php if ( $without_alt > 0 ) : ?>
		<h3>تصاویر بدون ALT:</h3>
		<ul style="max-height:300px;overflow:auto;border:1px solid #ccc;padding:10px;">
			<?php foreach ( $missing_alt_list as $img ) : ?>
				<li><a href="<?php echo esc_url( $img->guid ); ?>" target="_blank" rel="noopener">[<?php echo (int) $img->ID; ?>] <?php echo esc_html( $img->post_title ? $img->post_title : 'بدون عنوان' ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
	<?php
}
