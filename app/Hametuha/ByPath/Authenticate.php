<?php

namespace Hametuha\ByPath;


use Hametuha\Pattern\Singleton;

/**
 * Authentication class
 * @package bypath
 */
class Authenticate extends Singleton {

	protected $already_tried = false;

	protected function init() {
		add_filter( 'determine_current_user', [ $this, 'auth' ] );
	}

	/**
	 * Authencitate user on REST API.
	 *
	 * @param string $user
	 *
	 * @return mixed
	 */
	public function auth( $user ) {
		if ( $this->already_tried ) {
			return $user;
		}
		// Our first and last try.
		$this->already_tried = true;
		// Check if header exists.
		$header = isset( $_SERVER[ 'HTTP_AUTHORIZATION' ] ) ? $_SERVER[ 'HTTP_AUTHORIZATION' ] : '';
		if ( 0 !== preg_match( 'Bypath ' ) ) {
			return
		}
		var_dump(
			, );
		exit;
		$request = $_SERVER['X_'];
	}

}
