<?php

namespace Hametuha\ByPath\PostType;


use Hametuha\Pattern\Singleton;
use Ramsey\Uuid\Uuid;

/**
 * User token class
 *
 * @package bypath
 */
class UserToken extends Singleton {

	const POST_TYPE = 'tokens';

	/**
	 * Initialize
	 */
	protected function init() {
		add_action( 'init', function() {
			register_post_type( self::POST_TYPE, [
				'label'   => __( 'User Token', 'bypath' ),
				'public'  => false,
				'show_ui' => true,
				'supports' => [ 'title' ],
				'show_in_menu' => 'edit.php?post_type=' . Client::POST_TYPE,
				'capabilities' => [
					'edit_post'		 => 'edit_users',
					'read_post'		 => 'edit_users',
					'delete_post'		 => 'edit_users',
					'edit_posts'		 => 'edit_users',
					'edit_others_posts'	 => 'edit_users',
					'publish_posts'		 => 'edit_users',
					'read_private_posts'	 => 'edit_users',
					'delete_posts'           => 'edit_users',
					'delete_private_posts'   => 'edit_users',
					'delete_published_posts' => 'edit_users',
					'delete_others_posts'    => 'edit_users',
					'edit_private_posts'     => 'edit_users',
					'edit_published_posts'   => 'edit_users',
				],
			] );
		}, 2 );

		// Render meta box.
		add_action( 'add_meta_boxes', function( $post_type, $post ) {
			if ( self::POST_TYPE !== $post_type ) {
				return;
			}
			add_meta_box( 'bypath-user-token', __( 'Token Status', 'bypath' ), [ $this, 'do_meta_box' ], $post_type, 'normal', 'high' );
		}, 10, 2 );

		// Add token list to list table.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', function( $columns ) {
			$new_column = [];
			foreach ( $columns as $key => $value ) {
				$new_column[ $key ] = $value;
				if ( 'title' === $key ) {
					$new_column['client'] = __( 'Client', 'bypath' );
					$new_column['token_owner'] = __( 'Token Owner', 'bypath' );
				}
			}
			return $new_column;
		} );
		// Render columns.
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'manage_columns' ], 10, 2 );

	}


	/**
	 * Render column content
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function manage_columns( $column, $post_id ) {
		$post = get_post( $post_id );
		switch ( $column ) {
			case 'client':
				$client = get_post( $post->post_parent );
				if ( $client ) {
					printf( '<a href="%s">%s</a>', get_edit_post_link( $client ), esc_html( get_the_title( $client ) ) );
				} else {
					echo '<span style="color: grey;">---</span>';
				}
				break;
			case 'token_owner':
				$user = get_userdata( $post->post_author );
				if ( $user ) {
					printf( '%s %s', get_avatar( $user->ID, 32 ), esc_html( $user->display_name ) );
				} else {
					printf( '<span style="color: grey;">%s</span>', esc_html__( 'No user', 'bypath' ) );
				}
				break;
			default:
				// Do nothing.
				break;
		}
	}

	/**
	 * Render meta box
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function do_meta_box( $post ) {
		?>
		<p>
			<strong><?php esc_html_e( 'Token Owner', 'bypath' ) ?></strong><br />
			<?php if ( $user = get_user_by( 'id', $post->post_author ) ) : ?>
				<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ) ?>"><?php echo esc_html( $user->display_name ) ?></a>
			<?php else : ?>
				<span style="color: grey"><?php esc_html_e( 'No user', 'bypath' ) ?></span>
			<?php endif; ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Token', 'bypath' ) ?></strong><br />
			<input class="regular-text" type="text" readonly value="<?php esc_attr_e( $post->post_excerpt ) ?>" />
		</p>
		<p>
			<strong><?php esc_html_e( 'Generated', 'bypath' ) ?></strong><br />
			<?php echo mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post->post_date ) ?>
		</p>
		<?php
	}

	/**
	 * Generate token.
	 *
	 * @param string $client_key             Client key.
	 * @param int    $user_id                User ID.
	 * @param bool   $generate_if_not_exists Defalt true.
	 *
	 * @return string|\WP_Error
	 */
	public static function get_token( $client_key, $user_id, $generate_if_not_exists = true ) {
		$client = Client::get_client_object( $client_key );
		if ( ! $client ) {
			return new \WP_Error( 'client_not_found', __( 'No client found.', 'bypath' ), [
				'status' => 404,
			] );
		}
		$token = '';
		foreach ( get_posts( [
			'post_type'        => self::POST_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'author'           => $user_id,
			'post_parent'      => $client->ID,
			'suppress_filters' => true,
			'orderby'          => [
				'date' => 'DESC',
			],
		] ) as $post ) {
			$token = $post->post_excerpt;
		}
		if ( $generate_if_not_exists && ! $token ) {
			$uuid = Uuid::uuid4()->toString();
			$post_id = wp_insert_post( [
				'post_type'    => self::POST_TYPE,
				'post_author'  => $user_id,
				'post_title'   => sprintf( __( 'Token for %1$s of #%2$d', 'bypath' ), get_the_title( $client ), $user_id ),
				'post_excerpt' => $uuid,
				'post_status'  => 'publish',
				'post_parent'  => $client->ID,
			], true );
			if ( is_wp_error( $post_id ) ) {
				return new \WP_Error( 'token_generation_failed', __( 'Failed to generate new token.', 'bypath' ), [
					'status' => 500,
				] );
			}
			return $uuid;
		} else {
			return $token;
		}
	}




}
