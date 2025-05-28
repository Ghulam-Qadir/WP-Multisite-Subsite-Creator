<?php
/**
 * Plugin Name: WP Multisite Subsite Creator
 * Description: Automatically creates subsites in a WordPress multisite with separate databases and uploads.
 * Version: 1.0.0
 * Author: Qadir
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPMSS_PATH', plugin_dir_path( __FILE__ ) );

// Load required files
require_once WPMSS_PATH . 'includes/class-subsite-api.php';
require_once WPMSS_PATH . 'includes/class-subsite-manager.php';
require_once WPMSS_PATH . 'includes/helpers.php';

if ( ! is_multisite() ) {
	add_action( 'admin_notices', array( 'WP_Multisite_Subsite_Creator', 'display_notices' ) );
}

class WP_Multisite_Subsite_Creator {

	private static $_instance = null;

	/**
	 * Get the single instance of the class
	 */
	public static function instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;

	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register hooks
	 */
	private function init_hooks() {
		add_action( 'rest_api_init', [$this, 'register_api'] );
		add_filter( 'upload_dir', [$this, 'modify_upload_dir'] );

	}

	public static function display_notices() {
		self::admin_error_notices();
	}

	/**
	 * Register the REST API
	 */
	public function register_api() {
		new WPMSS_Subsite_API();
	}

	/**
	 * Modify the upload directory path for subsites
	 */
	public function modify_upload_dir( $dirs ) {
		if ( is_multisite() && ! is_main_site() ) {
			$blog_id       = get_current_blog_id();
			$custom_folder = 'wp_subsite_' . $blog_id;

			$upload_base = WP_CONTENT_DIR . '/uploads';
			$upload_url  = content_url( 'uploads' );

			$dirs['path']    = $upload_base . '/' . $custom_folder;
			$dirs['url']     = $upload_url . '/' . $custom_folder;
			$dirs['basedir'] = $upload_base;
			$dirs['baseurl'] = $upload_url;
			$dirs['subdir']  = ''; // remove /sites/xx etc.
		}
		return $dirs;
	}

	/**
	 * Activation hook
	 */
	public static function activate( $network_wide ) {
		if ( ! is_multisite() ) {
			return;
		} else {
			self::maybe_update_wp_config();
		}
	}

	private static function maybe_update_wp_config() {
		$config_file = ABSPATH . 'wp-config.php';
		copy( $config_file, $config_file . '.bak' );

		if ( ! is_writable( $config_file ) ) {
			update_option( 'wpmss_config_status', 'not_writable' );
			return;
		}

		$current = file_get_contents( $config_file );

		$marker_start = "// WPMSS Multisite Setup - Start";
		$marker_end   = "// WPMSS Multisite Setup - End";

		if ( strpos( $current, $marker_start ) !== false ) {
			update_option( 'wpmss_config_status', 'already_present' );
			return;
		}

		// Define snippet
		$config_snippet = "\n$marker_start\n" .
			"if ( isset( \$_SERVER['HTTP_HOST'] ) ) {\n" .
			"\t\$dbMapFile = dirname(__FILE__) . '/wp-content/wpmss-db-map.json';\n" .
			"\tif ( file_exists( \$dbMapFile ) ) {\n" .
			"\t\t\$map = json_decode( file_get_contents( \$dbMapFile ), true );\n" .
			"\t\t\$host = \$_SERVER['HTTP_HOST'];\n" .
			"\t\tif ( isset( \$map[\$host] ) ) {\n" .
			"\t\t\tdefine( 'DB_NAME', \$map[\$host] );\n" .
			"\t\t} else {\n" .
			"\t\t\tdefine( 'DB_NAME', 'gravityformsplugin' );\n" .
			"\t\t}\n" .
			"\t}\n" .
			"}\n" .
			"$marker_end\n";

		// Replace hardcoded DB_NAME define if exists
		$current = preg_replace(
			"/define\s*\(\s*['\"]DB_NAME['\"].*?\);/",
			"// define( 'DB_NAME', 'gravityformsplugin' ); // replaced by plugin",
			$current
		);

		// Insert before wp-settings.php
		$updated = preg_replace(
			"/(require_once\s+ABSPATH\s*\.\s*'wp-settings\.php'\s*;)/",
			$config_snippet . "\n\n$1",
			$current
		);

		if ( null !== $updated ) {
			file_put_contents( $config_file, $updated );
			update_option( 'wpmss_config_status', 'written' );
		} else {
			update_option( 'wpmss_config_status', 'regex_failed' );
		}
	}

	public static function admin_error_notices() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><strong>WP Multisite Subsite Creator:</strong> This plugin requires WordPress Multisite to be enabled.</p>
			<p><a href="https://wordpress.org/documentation/article/create-a-network/" target="_blank" rel="noopener noreferrer">Learn how to enable Multisite in WordPress.</a></p>
		</div>

		<div class="notice notice-success is-dismissible">
			<p><strong>WP Multisite Subsite Creator:</strong> Multisite is enabled and plugin is configured successfully.</p>
		</div>
		<?php
}

	/**
	 * Deactivation hook
	 */
	public static function deactivate( $network_wide ) {
		// Reserved for future cleanup tasks
	}

	/**
	 * Uninstall hook
	 */
	public static function uninstall() {
		if ( __FILE__ !== WP_UNINSTALL_PLUGIN ) {
			return;
		}
		// Reserved for uninstall cleanup
	}
}

// Bootstrap the plugin
add_action( 'plugins_loaded', ['WP_Multisite_Subsite_Creator', 'instance'] );
register_activation_hook( __FILE__, ['WP_Multisite_Subsite_Creator', 'activate'] );
register_deactivation_hook( __FILE__, ['WP_Multisite_Subsite_Creator', 'deactivate'] );
register_uninstall_hook( __FILE__, ['WP_Multisite_Subsite_Creator', 'uninstall'] );
