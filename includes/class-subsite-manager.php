<?php
class WPMSS_Subsite_Manager {

	public static function create_subsite( $data ) {
		$subdomain      = strtolower( sanitize_user( $data['subdomain'] ) );
		$title          = sanitize_text_field( $data['title'] );
		$email          = sanitize_email( $data['admin_email'] );
		$admin_username = sanitize_user( $data['admin_username'] );
		$admin_password = $data['admin_password'];

		$network      = get_network();
		$domain_parts = parse_url( $network->siteurl ?? $network->domain );
		$main_domain  = $domain_parts['host'] ?? $network->domain;
		$domain       = $subdomain . '.' . $main_domain;
		$path         = '/';

		if ( domain_exists( $domain, $path, $network->id ) ) {
			return ['success' => false, 'message' => 'A subsite with this subdomain already exists.'];
		}

		// Create blog
		$blog_id = wpmu_create_blog( $domain, $path, $title, get_current_user_id(), ['public' => 1], $network->id );
		if ( is_wp_error( $blog_id ) ) {
			return ['success' => false, 'message' => $blog_id->get_error_message()];
		}

		// Create separate DB
		$db_name = 'wp_subsite_' . $blog_id;
		if ( ! WPMSS_Helpers::setup_new_database( $blog_id, $db_name ) ) {
			return ['success' => false, 'message' => 'Database creation failed.'];
		}
		WPMSS_Helpers::save_db_map( $domain, $db_name );

		// Create or get admin user
		$user_id = username_exists( $admin_username );
		if ( ! $user_id ) {
			$user_id = wp_create_user( $admin_username, $admin_password, $email );
			if ( is_wp_error( $user_id ) ) {
				return ['success' => false, 'message' => $user_id->get_error_message()];
			}
		}

		// Add user to subsite as admin
		add_user_to_blog( $blog_id, $user_id, 'administrator' );

		// Switch to subsite to configure it
		switch_to_blog( $blog_id );
		$default_theme = 'twentytwenty';
		if ( wp_get_theme( $default_theme )->exists() ) {
			switch_theme( $default_theme );
		}

// Activate plugin if it exists
		$default_plugin = 'wp-crontrol/wp-crontrol.php';
		if ( file_exists( WP_PLUGIN_DIR . '/' . $default_plugin ) ) {
			activate_plugin( $default_plugin, '', false, true );
		}

		// Create custom upload folder (e.g., wp-content/uploads/wp_subsite_11)
		$upload_dir = wp_upload_dir(); // Runs the upload_dir filter
		wp_mkdir_p( $upload_dir['basedir'] );

		restore_current_blog();

		return [
			'success'   => true,
			'message'   => 'Subsite created successfully',
			'site_id'   => $blog_id,
			'subdomain' => $subdomain,
			'site_url'  => 'https://' . $domain,
			'admin'     => [
				'username' => $admin_username,
				'password' => $admin_password,
				'email'    => $email,
			],
		];

	}

}
