<?php
class WPMSS_Subsite_API {
	public function __construct() {
		register_rest_route( 'wpmss/v1', '/create-subsite', [
			'methods'             => 'POST',
			'callback'            => [$this, 'handle_create_subsite'],
			'permission_callback' => function () {
				return true;
			},
		] );
	}

	public function handle_create_subsite( $request ) {
		$data     = $request->get_json_params();
		$required = ['subdomain', 'title', 'admin_email'];
		foreach ( $required as $key ) {
			if ( empty( $data[$key] ) ) {
				return new WP_REST_Response( ["error" => "$key is required"], 400 );
			}
		}

		$result = WPMSS_Subsite_Manager::create_subsite( $data );
		return new WP_REST_Response( $result, $result['success'] ? 200 : 500 );
	}
}
