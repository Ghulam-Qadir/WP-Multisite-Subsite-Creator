<?php

class WPMSS_Helpers {

	public static function setup_new_database( $blog_id, $db_name ) {
		if ( ! self::create_database( $db_name ) ) {
			return false;
		}

		if ( ! self::clone_subsite_schema_to_db( $blog_id, $db_name ) ) {
			return false;
		}

		return true;
	}

	public static function create_database( $name ) {
		global $wpdb;
		$sql = "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
		return $wpdb->query( $sql ) !== false;
	}

	public static function clone_subsite_schema_to_db( $blog_id, $target_db ) {
		global $wpdb;

		$main_db        = DB_NAME;
		$subsite_prefix = $wpdb->base_prefix . $blog_id . '_';
		$global_tables  = [
			$wpdb->base_prefix . 'users',
			$wpdb->base_prefix . 'usermeta',
			$wpdb->base_prefix . 'site',
			$wpdb->base_prefix . 'sitemeta',
			$wpdb->base_prefix . 'blogs',
		];

		// Create the target DB if it doesn't exist
		$wpdb->query( "CREATE DATABASE IF NOT EXISTS `$target_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" );

		// Get subsite-specific tables
		$subsite_tables = $wpdb->get_col(
			$wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $subsite_prefix ) . '%' )
		);

		if ( empty( $subsite_tables ) ) {
			return ['success' => false, 'message' => 'No subsite tables found.'];
		}

		$tables_to_clone = array_merge( $subsite_tables, $global_tables );

		foreach ( $tables_to_clone as $table ) {
			$table_parts  = explode( '.', $table );
			$table_name   = end( $table_parts );
			$target_table = "`$target_db`.`$table_name`";

			$create_result = $wpdb->get_row( "SHOW CREATE TABLE `$table_name`", ARRAY_A );
			if ( ! isset( $create_result['Create Table'] ) ) {
				error_log( "Could not get CREATE TABLE for $table_name" );
				continue;
			}

			$create_sql = str_replace(
				"CREATE TABLE `$table_name`",
				"CREATE TABLE $target_table",
				$create_result['Create Table']
			);

			if ( $wpdb->query( $create_sql ) === false ) {
				error_log( "Failed to create table $target_table" );
				continue;
			}

			if ( $wpdb->query( "INSERT INTO $target_table SELECT * FROM `$main_db`.`$table_name`" ) === false ) {
				error_log( "Failed to copy data to $target_table" );
			}
		}

		// 🔥 Delete only the subsite-specific tables from the main DB
		foreach ( $subsite_tables as $table ) {
			$table_name = end( explode( '.', $table ) );
			$wpdb->query( "DROP TABLE IF EXISTS `$main_db`.`$table_name`" );
		}

		return ['success' => true, 'message' => 'Tables copied and subsite tables removed from main database.'];
	}

	public static function save_db_map( $domain, $db_name ) {
		$map_file = WP_CONTENT_DIR . '/wpmss-db-map.json';

		$map          = file_exists( $map_file ) ? json_decode( file_get_contents( $map_file ), true ) : [];
		$map[$domain] = $db_name;

		file_put_contents( $map_file, json_encode( $map, JSON_PRETTY_PRINT ) );
	}

	public static function get_db_name_for_domain( $domain ) {
		$map_file = WP_CONTENT_DIR . '/wpmss-db-map.json';

		if ( ! file_exists( $map_file ) ) {
			return false;
		}

		$map = json_decode( file_get_contents( $map_file ), true );
		return $map[$domain] ?? false;
	}
}
