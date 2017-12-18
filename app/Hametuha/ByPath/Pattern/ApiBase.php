<?php

namespace Hametuha\ByPath\Pattern;


use Hametuha\ByPath\PostType\Client;
use Hametuha\Pattern\RestApi;

abstract class ApiBase extends RestApi {

	protected $version = '1';

	protected $namespace = 'bypath';

	/**
	 * Check hash.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return bool|\WP_Error
	 */
	protected function check_general_hash( $request ) {
		// Create array of keys.
		$keys           = [];
		$credentials    = [];
		$string_to_hash = '';
		foreach ( $request->get_params() as $key => $value ) {
			switch ( $key ) {
				case 'client_key':
				case 'token':
					// Skip.
					if ( $value ) {
						$credentials[ $key ] = $value;
					}
					break;
				default:
					$keys[ $key ] = $value;
					break;
			}
		}
		// Check credentials.
		if ( 2 !== count( $credentials ) ) {
			return new \WP_Error( 'bad_request', __( 'Client key or token is not set. Please check your request.', 'bypath' ), [
				'status' => 400,
			] );
		}
		// Sort params alphabetically and concat them all.
		ksort( $keys );
		foreach ( $keys as $value ) {
			$string_to_hash .= $value;
		}
		// Hash string with client secret.
		$client_secret = Client::secret( $credentials['client_key'] );
		if ( ! $client_secret ) {
			return new \WP_Error( 'no_client', __( 'No client found. Please check if request data is proper.', 'bypath' ), [
				'status' => 401,
			] );
		}
		$string_to_hash .= $client_secret;
		$hash = hash( 'sha256', $string_to_hash );
		// Test hash.
		return $credentials['token'] === $hash ? true : new \WP_Error( 'bad_hash', __( 'Failed to pass hash test. Please check your request is valid.', 'bypath' ), [
			'status' => 403,
		] );
	}

}
