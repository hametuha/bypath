<?php

namespace Hametuha\ByPath;


use Hametuha\ByPath\PostType\UserToken;
use Hametuha\Pattern\Singleton;

/**
 * Authenticator
 *
 * @package bypath
 */
class Authenticate extends Singleton {

	/**
	 * Did already tried?
	 *
	 * @var bool
	 */
	protected $already_tried = false;

	/**
	 * Initialize
	 */
	protected function init() {
		add_filter( 'determine_current_user', [ $this, 'auth' ] );
	}

	/**
	 * Authenticate user on REST API.
	 *
	 * @param string $user User ID.
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
		if ( ! preg_match( '#^Bypath (.*)$#iu', $header, $matches ) ) {
			return $user;
		}
		// Token found.
		return UserToken::get_user_from_token( $matches[1] );
	}

}
