<?php

namespace Hametuha;


use Hametuha\ByPath\Authenticate;
use Hametuha\ByPath\Login;
use Hametuha\ByPath\PostType\Client;
use Hametuha\ByPath\PostType\UserToken;
use Hametuha\ByPath\Register;
use Hametuha\Pattern\Singleton;

class ByPath extends Singleton {


	/**
	 *
	 */
	protected function init() {
		Client::get_instance();
		UserToken::get_instance();

		Login::get_instance();
//		Register::get_instance();

		// Auth
		Authenticate::get_instance();
	}





}
