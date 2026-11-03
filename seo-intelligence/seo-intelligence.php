<?php
/**
 * Plugin Name: SEO Intelligence & Search Console Analyzer
 * Plugin URI:  https://github.com/mahyarrmni/seo-intelligence
 * Description: Analyzes real Google Search Console data to surface organic traffic, CTR, and ranking opportunities. Read-only analytics tool — never generates fake traffic, clicks, or search behavior.
 * Version:     2.2.0
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * Author:      Mahyar Momeni
 * Author URI: https://mahyarhub.ir
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seo-intelligence
 * Domain Path: /languages
 *
 * @package SEO_Intelligence
 */

namespace SEO_Intelligence;

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------
// Core constants.
// ---------------------------------------------------------------------
define( 'SEOI_VERSION', '2.2.0' );
define( 'SEOI_PLUGIN_FILE', __FILE__ );
define( 'SEOI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEOI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SEOI_MIN_PHP', '8.1' );
define( 'SEOI_MIN_WP', '6.0' );

/**
 * PHP version guard. The plugin refuses to load on unsupported PHP
 * versions instead of triggering a fatal error mid-request.
 */
function seoi_php_version_check(): bool {
	if ( version_compare( PHP_VERSION, SEOI_MIN_PHP, '<' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-error"><p>' .
					esc_html(
						sprintf(
							/* translators: 1: required PHP version 2: current PHP version */
							__( 'SEO Intelligence requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP or disable this plugin.', 'seo-intelligence' ),
							SEOI_MIN_PHP,
							PHP_VERSION
						)
					) .
					'</p></div>';
			}
		);
		return false;
	}
	return true;
}

/**
 * Autoloader for plugin classes following the SEO_Intelligence namespace.
 */
spl_autoload_register(
	static function ( string $class ): void {
		if ( ! str_starts_with( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative   = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
		$parts      = explode( '\\', $relative );
		$class_name = array_pop( $parts );
		$file       = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		// The "Admin" sub-namespace lives under /admin/ at the plugin
		// root (mirroring the folder layout), everything else lives
		// under /includes/<lowercased-subnamespace>/.
		if ( ! empty( $parts ) && 'Admin' === $parts[0] ) {
			$remaining = array_slice( $parts, 1 );
			$subdir    = empty( $remaining ) ? '' : strtolower( implode( '/', $remaining ) ) . '/';
			$path      = SEOI_PLUGIN_DIR . 'admin/' . $subdir . $file;
		} else {
			$subdir = empty( $parts ) ? '' : strtolower( implode( '/', $parts ) ) . '/';
			$path   = SEOI_PLUGIN_DIR . 'includes/' . $subdir . $file;
		}

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Integrity guard. If the plugin folder is incomplete (for example a partial
 * upload), show an admin notice instead of a fatal "class not found" error.
 */
function seoi_files_present(): bool {
	if ( file_exists( SEOI_PLUGIN_DIR . 'includes/class-plugin.php' ) ) {
		return true;
	}
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'SEO Intelligence: plugin files are incomplete (includes/ folder is missing). Please reinstall the plugin from the full release ZIP.', 'seo-intelligence' ) .
				'</p></div>';
		}
	);
	return false;
}

/**
 * Boot the plugin once all plugins are loaded.
 */
function seoi_bootstrap(): void {
	if ( ! seoi_php_version_check() ) {
		return;
	}
	if ( ! seoi_files_present() ) {
		return;
	}

	Plugin::instance()->run();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\seoi_bootstrap' );

/**
 * Activation hook — creates database tables and schedules cron.
 */
function seoi_activate(): void {
	if ( ! seoi_php_version_check() || ! seoi_files_present() ) {
		return;
	}
	require_once SEOI_PLUGIN_DIR . 'includes/class-plugin.php';
	Plugin::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\seoi_activate' );

/**
 * Deactivation hook — clears scheduled cron events only.
 * Data is intentionally preserved; full cleanup happens in uninstall.php.
 */
function seoi_deactivate(): void {
	if ( ! file_exists( SEOI_PLUGIN_DIR . 'includes/class-plugin.php' ) ) {
		return;
	}
	require_once SEOI_PLUGIN_DIR . 'includes/class-plugin.php';
	Plugin::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\seoi_deactivate' );
