<?php

namespace Hametuha\ByPath\PostType;


use Hametuha\Pattern\Singleton;

/**
 * Client class
 *
 * @package bypath
 */
class Client extends Singleton {

	const TOKEN_KEY_LENGTH = 24;

	const TOKEN_SECRET_LENGTH = 48;

	const POST_TYPE = 'bypath';

	/**
	 * Initialize
	 */
	protected function init() {
		// Register post type.
		add_action( 'init', function() {
			register_post_type( self::POST_TYPE, [
				'label'   => __( 'External Client', 'bypath' ),
				'public'  => false,
				'show_ui' => true,
				'supports' => [ 'title', 'excerpt' ],
				'menu_icon' => 'dashicons-lock',
				'menu_position' => 90,
				'capabilities' => [
					'edit_post'		 => 'manage_options',
					'read_post'		 => 'manage_options',
					'delete_post'		 => 'manage_options',
					'edit_posts'		 => 'manage_options',
					'edit_others_posts'	 => 'manage_options',
					'publish_posts'		 => 'manage_options',
					'read_private_posts'	 => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'edit_published_posts'   => 'manage_options',
				],
			] );
		}, 1 );
		// Render meta box.
		add_action( 'add_meta_boxes', function( $post_type, $post ) {
			if ( self::POST_TYPE !== $post_type ) {
				return;
			}
			add_meta_box( 'bypath-credentials', __( 'Bypath Credentials', 'bypath' ), [ $this, 'do_meta_box' ], $post_type, 'advanced', 'high' );
		}, 10, 2 );
		// Token handler.
		add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
		// Add token list to list table.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', function( $columns ) {
			$new_column = [];
			foreach ( $columns as $key => $value ) {
				$new_column[ $key ] = $value;
				if ( 'title' === $key ) {
					$new_column['client_key'] = __( 'Client Key', 'bypath' );
				}
			}
			return $new_column;
		} );
		// Render columns.
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'manage_columns' ], 10, 2 );
	}

	/**
	 * Save or generate token.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_post( $post_id, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}
		// Assure client key existence and it'll be never changed.
		$client_key = get_post_meta( $post_id, '_client_key', true );
		if ( ! $client_key ) {
			// No client key. Should be genereated.
			$client_key = wp_generate_password( self::TOKEN_KEY_LENGTH, false );
			update_post_meta( $post_id, '_client_key', $client_key );
		}
		// Client secret.
		$generate_secret = false;
		$current_secret  = get_post_meta( $post_id, '_client_secret', true );
		if ( isset( $_REQUEST['_bypath_nonce'], $_REQUEST['bypath-regen'] ) && wp_verify_nonce( $_REQUEST['_bypath_nonce'], 'bypath_nonce' ) && $_REQUEST['bypath-regen'] ) {
			// Explicitly specified to generate.
			$generate_secret = true;
			add_post_meta( $post_id, '_bypath_history', [
				'modified' => current_time( 'mysql', true ),
				'former'   => $current_secret,
				'author'   => get_current_user_id(),
			] );
		} elseif ( ! $current_secret ) {
			// No token.
			$generate_secret = true;
		}
		if ( $generate_secret ) {
			update_post_meta( $post_id, '_client_secret', wp_generate_password( self::TOKEN_SECRET_LENGTH, false ) );
		}
		// If some change occured, clear secret cache.
		if ( $http_response_header || ( 'publish' !== $post->post_status ) ) {
			wp_cache_delete( $client_key, 'bypath' );
		}
	}

	/**
	 * Render metabox for credentials
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function do_meta_box( $post ) {
		$client_key    = get_post_meta( $post->ID, '_client_key', true );
		$client_secret = get_post_meta( $post->ID, '_client_secret', true );
		wp_nonce_field( 'bypath_nonce', '_bypath_nonce' );
		?>
		<p>
			<label><?php esc_html_e( 'Client Key', 'bypath' ) ?></label><br />
			<input type="text" placeholder="<?php esc_attr_e( 'Generated after saved.', 'bypath' ) ?>"
				   readonly value="<?php echo esc_attr( $client_key ) ?>" class="regular-text" style="width: 100%; -webkit-box-sizing: border-box;" />
		</p>
		<p>
			<label><?php esc_html_e( 'Client Secret', 'bypath' ) ?></label><br />
			<input type="text" placeholder="<?php esc_attr_e( 'Generated after saved.', 'bypath' ) ?>"
				   readonly value="<?php echo esc_attr( $client_secret ) ?>" class="regular-text" style="width: 100%; -webkit-box-sizing: border-box;" />
		</p>
		<p>
			<label>
				<input type="checkbox" value="1" name="bypath-regen" />
				<?php esc_html_e( 'Regenerate Secret', 'bypath' ) ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Render column content
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function manage_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'client_key':
				$key = get_post_meta( $post_id, '_' . $column, true );
				if ( $key ) {
					printf( '<code>%s</code>', esc_html( $key ) );
				} else {
					echo '<span style="color: grey;">---</span>';
				}
				break;
			default:
				// Do nothing.
				break;
		}
	}

	/**
	 * Get client secret
	 *
	 * @param string $client_key Client key.
	 *
	 * @return string
	 */
	public static function secret( $client_key ) {
		$secret = wp_cache_get( $client_key, 'bypath' );
		if ( false === $secret ) {
			$client = self::get_client_object( $client_key );
			if ( $client ) {
				$secret = get_post_meta( $client->ID, '_client_secret', true );
				wp_cache_set( $client_key, $secret, 'bypath', 3600 );
			} else {
				$secret = '';
			}
		}
		return $secret;
	}

	/**
	 * Get client object form client key.
	 *
	 * @param string $client_key Client key.
	 * @param string $status     Default 'publish'.
	 *
	 * @return null|\WP_Post
	 */
	public static function get_client_object( $client_key, $status = 'publish' ) {
		foreach ( get_posts( [
			'post_type'        => self::POST_TYPE,
			'post_status'      => $status,
			'posts_per_page'   => 1,
			'suppress_filters' => false,
			'meta_query'       => [
				[
					'key'   => '_client_key',
					'value' => $client_key,
				],
			],
		] ) as $post ) {
			return $post;
		}
		return null;
	}



}
