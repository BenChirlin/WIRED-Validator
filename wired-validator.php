<?php
/**
 * Plugin Name: Wired Validator
 * Description: Adds custom post title, excerpt, and image validation. Adjustable options in the writing settings.
 * Version: 1.0
 * Author: WIRED Tech Team
 * Author URI: http://www.wired.com/
 */

if ( ! class_exists( 'Wired_Validator' ) ) {
	class Wired_Validator {

		/**
		 * The one instance of Wired_Validator.
		 *
		 * @var Wired_Validator
		 */
		private static $instance;

		/**
		 * Instantiate or return the one Wired_Validator instance.
		 *
		 * @return Wired_Validator
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Initialize plugin: add admin panel, styles, and scripts
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function __construct() {
			// Enqueue custom CSS and JS
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_styles' ) );
			// Add settings link to plugin screen
			$plugin = plugin_basename( __FILE__ );
			add_filter( 'plugin_action_links_' . $plugin, array( $this, 'plugin_add_settings_link' ) );
			// Add helper text to featured image box
			add_filter( 'admin_post_thumbnail_html', array( $this, 'add_featured_helper' ) );
			// Add custom excerpt settings
			add_action( 'admin_init', array( $this, 'writing_limit_init') );
			// Add nonce to post edit form
			add_action( 'post_submitbox_misc_actions', array( $this, 'add_post_nonce' ), 9 );
			// Back end title/excerpt limit and image validation
			add_action( 'save_post', array( $this, 'validate_post' ) );
			// Add nonce to user edit form
			add_action( 'show_user_profile', array( $this, 'add_user_nonce' ) );
			add_action( 'edit_user_profile', array( $this, 'add_user_nonce' ) );
			// Back end author bio and image validation
			add_action( 'personal_options_update', array( $this, 'validate_profile' ) );
			add_action( 'edit_user_profile_update', array( $this, 'validate_profile' ) );
			add_action( 'admin_notices', array( $this, 'validate_notice' ) );
		}

		/**
		 * Extend the admin_scripts_styles() method. Hooked by admin_enqueue_scripts().
		 *
		 * @access public
		 * @author $Author$
		 * @param string $screen screen you're currently on, passed by admin_enqueue_scripts() hook.
		 */
		public function admin_scripts_styles( $screen ) {
			global $pagenow;
			if ( is_admin() && get_post_type() === 'post' ) {
				// Declare default enqueue vars
				$ver = '0001';
				if ( defined( 'CN_CACHE_VERSION' ) ) {
					$ver = CN_CACHE_VERSION;
				}

				$validator_options = $this->get_validator_options();
				$on_date = $validator_options && array_key_exists( 'valid_date', $validator_options ) ? intval( $validator_options[ 'valid_date' ] ) : -1;
				$post_date = get_the_date( 'U' );

				if ( $post_date >= $on_date ) {
					wp_register_script( 'wired-post-validator', plugins_url( 'assets/js/post-validator.js', __FILE__ ), array( 'jquery' ), $ver, true );
					wp_localize_script( 'wired-post-validator', 'limitopts', $validator_options );
					wp_enqueue_script( 'wired-post-validator' );
					wp_enqueue_style( 'validator-admin-style', plugins_url( 'assets/css/validator.css', __FILE__ ), false, $ver );
				}
			}
		}

		/**
		 * Add settings link to plugin page
		 *
		 * @access public
		 * @param array $links Links in this plugins description on the plugins page
		 * @return array Modified list of links
		 * @author Ben Chirlin
		 */
		public function plugin_add_settings_link( $links ) {
			$settings_link = '<a href="/wp-admin/options-writing.php">' . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
		}

		/**
		 * Function description
		 *
		 * @access public
		 * @param string $content Content to render
		 * @return string Altered content
		 * @author Ben Chirlin
		 */
		public function add_featured_helper( $content ) {
			$current_post_type = '';

			// Check AJAX
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				//extract the querystring from the referer
				$query = parse_url( wp_get_referer(), PHP_URL_QUERY );

				//extract the querystring into an array
				parse_str( $query, $query_array );

				//get the post_type querystring value
				if ( array_key_exists( 'post_type', $query_array ) ) {
					$current_post_type = $query_array['post_type'];
				} else {
					$current_post_type = 'post';
				}
			}

			// Check screen properties
			$screen = get_current_screen();
			if ( $screen ) {
				$current_post_type = $screen->post_type;
			}

			if ( $current_post_type === 'post' ) {
				$validator_options = $this->get_validator_options();
				$content .= '<em>Minimum recommended width ' . $validator_options['featured_min'] . 'px</em><br/><em>Full resolution jpegs are preferred</em>';
			}
			return $content;
		}

		/**
		 * Add custom title/excerpt settings to writing admin settings panel
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function writing_limit_init() {
			register_setting(
				'writing',
				'validator_options',
				array( $this, 'limit_validate_options' )
			);

			add_settings_section(
				'validator',
				'Validator Settings',
				array( $this, 'print_section_info' ),
				'writing'
			);
			add_settings_field(
				'valid_date',
				'Valdator Active Date',
				array( $this, 'valid_date_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'title_validate',
				'Validate Title?',
				array( $this, 'title_validate_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'title_min_options',
				'Title Character Minimum',
				array( $this, 'title_min_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'title_limit_options',
				'Title Character Limit',
				array( $this, 'title_limit_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'excerpt_validate',
				'Validate Excerpt?',
				array( $this, 'excerpt_validate_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'excerpt_min_options',
				'Excerpt Character Minimum',
				array( $this, 'excerpt_min_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'excerpt_limit_options',
				'Excerpt Character Limit',
				array( $this, 'excerpt_limit_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'featured_validate',
				'Validate Featured Image?',
				array( $this, 'featured_validate_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'featured_min_options',
				'Featured Image Minimum Width',
				array( $this, 'featured_min_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'bio_validate',
				'Validate Bios?',
				array( $this, 'bio_validate_callback' ),
				'writing',
				'validator'
			);
			add_settings_field(
				'bio_min_options',
				'User Biography Character Minimum',
				array( $this, 'bio_min_callback' ),
				'writing',
				'validator'
			);
		}

		/**
		 * Add nonce to form if post
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function add_post_nonce() {
			global $post;
			if ( $post->post_type === 'post' ) {
				wp_nonce_field( 'save', '_validate_nonce' );
			}
		}

		/**
		 * Section formatting
		 *
		 * @access public
		 * @param array $arg Array of section properties: id, title, callback
		 * @return null
		 * @author Ben Chirlin
		 */
		public function print_section_info( $arg ) {
			return;
		}

		/**
		 * Output title minimum field
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function valid_date_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="text" id="valid_date" placeholder="i.e. 2014/01/31" name="validator_options[valid_date]" value="%s" />',
			$validator_options && array_key_exists( 'valid_date', $validator_options ) ? date( 'Y/m/d', esc_attr( intval( $validator_options[ 'valid_date' ] ) ) ) : '' );
			print( '<p class="description">Post publish date after which to validate posts (prevents retroactive validation).</p>' );
		}

		/**
		 * Output title validate checkbox
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function title_validate_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="checkbox" id="title_validate" name="validator_options[title_validate]" value="checked" %s/>',
			$validator_options && array_key_exists( 'title_validate', $validator_options ) ? esc_attr( $validator_options[ 'title_validate' ] ) : '' );
			print( '<p class="description">Validate title lengths</p>' );
		}

		/**
		 * Output title minimum field
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function title_min_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="text" id="title_min" placeholder="i.e. 20" name="validator_options[title_min]" value="%s" />',
			$validator_options && array_key_exists( 'title_min', $validator_options ) ? esc_attr( $validator_options[ 'title_min' ] ) : '' );
			print( '<p class="description">The minimum number of characters required in a post title.</p>' );
		}

		/**
		 * Output title limit field
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function title_limit_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="text" id="title_limit" placeholder="i.e. 80" name="validator_options[title_limit]" value="%s" />',
			$validator_options && array_key_exists( 'title_limit', $validator_options ) ? esc_attr( $validator_options[ 'title_limit' ] ) : '' );
			print( '<p class="description">The maximum number of characters allowed in a post title.</p>' );
		}

		/**
		 * Output excerpt validate checkbox
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function excerpt_validate_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="checkbox" id="excerpt_validate" name="validator_options[excerpt_validate]" value="checked" %s />',
			$validator_options && array_key_exists( 'excerpt_validate', $validator_options ) ? esc_attr( $validator_options[ 'excerpt_validate' ] ) : '' );
			print( '<p class="description">Validate exceprt lengths</p>' );
		}

		/**
		 * Output excerpt minimum field
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function excerpt_min_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="text" id="excerpt_min" placeholder="i.e. 140" name="validator_options[excerpt_min]" value="%s" />',
			$validator_options && array_key_exists( 'excerpt_min', $validator_options ) ? esc_attr( $validator_options[ 'excerpt_min' ] ) : '' );
			print( '<p class="description">The minimum number of characters required in a post excerpt.</p>' );
		}

		/**
		 * Output excerpt limit field
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function excerpt_limit_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="text" id="excerpt_limit" placeholder="i.e. 140" name="validator_options[excerpt_limit]" value="%s" />',
			$validator_options && array_key_exists( 'excerpt_limit', $validator_options ) ? esc_attr( $validator_options[ 'excerpt_limit' ] ) : '' );
			print( '<p class="description">The maximum number of characters allowed in a post excerpt.</p>' );
		}

		/**
		 * Output featured validate checkbox
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function featured_validate_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="checkbox" id="featured_validate" name="validator_options[featured_validate]" value="checked" %s/>',
			$validator_options && array_key_exists( 'featured_validate', $validator_options ) ? esc_attr( $validator_options[ 'featured_validate' ] ) : '' );
			print( '<p class="description">Validate featured image widths</p>' );
		}

		/**
		 * Output featrued minimum field
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function featured_min_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="text" id="featured_min" placeholder="i.e. 1000" name="validator_options[featured_min]" value="%s" />',
			$validator_options && array_key_exists( 'featured_min', $validator_options ) ? esc_attr( $validator_options[ 'featured_min' ] ) : '' );
			print( '<p class="description">The minimum width of all posts\' full sized featured image.</p>' );
		}

		/**
		 * Output bio validate checkbox
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function bio_validate_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="checkbox" id="bio_validate" name="validator_options[bio_validate]" value="checked" %s/>',
			$validator_options && array_key_exists( 'bio_validate', $validator_options ) ? esc_attr( $validator_options[ 'bio_validate' ] ) : '' );
			print( '<p class="description">Validate bio lengths and profile image presense</p>' );
		}

		/**
		 * Output bio minimum field
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function bio_min_callback() {
			$validator_options = $this->get_validator_options();
			printf( '<input type="text" id="bio_min" placeholder="i.e. 140" name="validator_options[bio_min]" value="%s" />',
			$validator_options && array_key_exists( 'bio_min', $validator_options ) ? esc_attr( $validator_options[ 'bio_min' ] ) : '' );
			print( '<p class="description">The minimum number of characters required in a user biography.</p>' );
		}

		/**
		 * Validate limits are ints and throw notices if not
		 *
		 * @access public
		 * @param array $input Input values stored in array
		 * @return mixed Validated input array
		 * @author Ben Chirlin
		 */
		public function limit_validate_options( $validator_options ) {
			// Store date
			if ( isset( $validator_options['valid_date'] ) ) {
				$validator_options[ 'valid_date' ] = intval( date( 'U', strtotime( $validator_options[ 'valid_date' ] ) ) );
			}

			// Make all validation options either checked or empty
			if ( isset( $validator_options['title_validate'] ) ) {
				$validator_options[ 'title_validate' ] = 'checked' === $validator_options[ 'title_validate' ] ? 'checked' : '';
			}
			if ( isset( $validator_options['excerpt_validate'] ) ) {
				$validator_options[ 'excerpt_validate' ] = 'checked' === $validator_options[ 'excerpt_validate' ] ? 'checked' : '';
			}
			if ( isset( $validator_options['featured_validate'] ) ) {
				$validator_options[ 'featured_validate' ] = 'checked' === $validator_options[ 'featured_validate' ] ? 'checked' : '';
			}
			if ( isset( $validator_options['bio_validate'] ) ) {
				$validator_options[ 'bio_validate' ] = 'checked' === $validator_options[ 'bio_validate' ] ? 'checked' : '';
			}

			// Get intval of all other inputs
			if ( isset( $validator_options['title_min'] ) ) {
				$validator_options[ 'title_min' ] = intval( $validator_options[ 'title_min' ] );
			}
			if ( isset( $validator_options['title_limit'] ) ) {
				$validator_options[ 'title_limit' ] = intval( $validator_options[ 'title_limit' ] );
			}
			if ( isset( $validator_options['excerpt_min'] ) ) {
				$validator_options[ 'excerpt_min' ] = intval( $validator_options[ 'excerpt_min' ] );
			}
			if ( isset( $validator_options['excerpt_limit'] ) ) {
				$validator_options[ 'excerpt_limit' ] = intval( $validator_options[ 'excerpt_limit' ] );
			}
			if ( isset( $validator_options['featured_min'] ) ) {
				$validator_options[ 'featured_min' ] = intval( $validator_options[ 'featured_min' ] );
			}
			if ( isset( $validator_options['bio_min'] ) ) {
				$validator_options[ 'bio_min' ] = intval( $validator_options[ 'bio_min' ] );
			}

			return $validator_options;
		}

		/**
		 * Check saved posts fields to ensure everything's valid on the back end
		 * - If the title or excerpt is under or over limit, warn user
		 * - If the title or excerpt is over the limit and the post is published, trim it and warn user
		 * - If featured image is missing, warn user
		 *
		 * @access public
		 * @param int $post_id Saved post's ID
		 * @return null
		 * @author Ben Chirlin
		 */
		public function validate_post( $post_id ) {
			// Check this is a post
			if ( get_post_type( $post_id ) != 'post' ) {
				return;
			}

			// Don't save on autosave
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Check user permissions
			if ( false === current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// Check nonce
			if ( false === isset( $_POST['_validate_nonce'] ) || false === wp_verify_nonce( $_POST['_validate_nonce'], 'save' ) ) {
				return;
			}

			// Check date is after validation date
			if ( !$this->valid_date( $post_id ) ) {
				// Delete errors if invalid
				delete_post_meta( $post_id, 'validator' );
				// Exit validation
				return;
			}

			// Get validator options
			$validator_options = $this->get_validator_options();

			if ( $validator_options[ 'featured_validate' ] ) {
				// Array to store our errors in
				$errors = array();

				// Check featured image exists
				if ( !has_post_thumbnail( $post_id ) ) {
					$errors['no-thumbnail'] = 'There is no featured image attached to this post. Please add one that\'s at least ' . $validator_options['featured_min'] . 'px wide, full resolution jpegs are preferred.';
				}

				// Check featured image is the right width
				$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
				if ( $image_data && intval( $image_data[1] ) < $validator_options['featured_min'] ) {
					$errors['bad-thumbnail'] = 'The featured image attached to this post is too small. Please add one that\'s at least ' . $validator_options['featured_min'] . 'px wide, full resolution jpegs are preferred.';
				}
			}

			// Get fields requiring validation
			$the_post = get_post( $post_id );
			$the_title = $the_post->post_title;
			$the_excerpt = $the_post->post_excerpt;

			// Validate title and excerpt lengths
			if ( $validator_options[ 'title_validate' ] ) {
				if ( strlen( strip_tags( $the_title ) ) > $validator_options[ 'title_limit' ] ) {
					$msg = 'Your title has exceeded the character limit.';
					// If published, trim title excluding tags, else suggest the user do so before updating
					if ( 'publish' === $the_post->post_status ) {
						$msg .= ' Since this post is published, it has been trimmed from "<em>' . $the_title . '</em>".';
						$modified_title = force_balance_tags( substr( $the_title, 0, $validator_options['title_limit'] ) );
						remove_action( 'save_post', array( $this, 'validate_post' ) );
						wp_update_post( array( 'ID' => $post_id, 'post_title' => $modified_title ) );
						add_action( 'save_post', array( $this, 'validate_post') );
						$errors['over-title-back'] = $msg;
					} else {
						$msg .= ' Please shorten it before you save or publish.';
						$errors['over-title'] = $msg;
					}
				} else if ( strlen( strip_tags( $the_title ) ) < $validator_options[ 'title_min' ] ) {
					// Warn user title is under min
					$errors['under-title'] = 'Your title is below the character minimum. Please lengthen it before updating this post.';
				}
			}

			if ( $validator_options[ 'excerpt_validate' ] ) {
				if ( strlen( strip_tags( $the_excerpt ) ) > $validator_options[ 'excerpt_limit' ] ) {
					$msg = 'Your excerpt has exceeded the character limit.';
					// If published, trim excerpt excluding tags, else suggest the user do so before updating
					if ( 'publish' === $the_post->post_status ) {
						$msg .= ' Since this post is published, it has been trimmed from "<em>' . $the_excerpt . '</em>".';
						$modified_excerpt = force_balance_tags( substr( $the_excerpt, 0, $validator_options['excerpt_limit'] ) );
						remove_action( 'save_post', array( $this, 'validate_post' ) );
						wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => $modified_excerpt ) );
						add_action( 'save_post', array( $this, 'validate_post') );
						$errors['over-excerpt-back'] = $msg;
					} else {
						$msg .= ' Please shorten it before you save or publish.';
						$errors['over-excerpt'] = $msg;
					}
				} else if ( strlen( strip_tags( $the_excerpt ) ) < $validator_options[ 'excerpt_min' ] ) {
					// Warn user excerpt is under min
					$errors['under-excerpt'] = 'Your excerpt is below the character minimum. Please lengthen it before updating this post.';
				}
			}

			// Set post meta with errors if any exist, else remove meta
			if ( !empty ( $errors ) ) {
				update_post_meta( $post_id, 'validator', $errors );
			} else {
				delete_post_meta( $post_id, 'validator' );
			}
		}

		/**
		 * Add nonce to user form
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function add_user_nonce() {
			wp_nonce_field( 'save', '_validate_nonce' );
		}

		/**
		 * Check saved profile fields to ensure everything's valid on the back end
		 * - If their bio is under or over limit, warn user
		 * - If no profile image is present, warn user
		 *
		 * @access public
		 * @param int $user_id Saved user's ID
		 * @return null
		 * @author Ben Chirlin
		 */
		public function validate_profile( $user_id ) {
			// Check permissions
			if ( false === current_user_can( 'edit_user', $user_id ) ) {
				return false;
			}

			if ( false === check_admin_referer( 'update-user_' . $user_id ) ) {
				return false;
			}

			// Check nonce
			if ( false === isset( $_POST['_validate_nonce'] ) || false === wp_verify_nonce( $_POST['_validate_nonce'], 'save' ) ) {
				return;
			}

			// Ghost, admins, and producers don't get validated
			if (
				user_can( $user_id, 'Ghost' ) ||
				user_can( $user_id, 'Administrator' ) ||
				user_can( $user_id, 'Producer' )
			) {
				return;
			}
			// Get validator options
			$validator_options = $this->get_validator_options();

			if ( 'checked' === $validator_options[ 'bio_validate' ] ) {
				// Array to store our errors in
				$errors = array();

				// Get fields requiring validation
				$user_bio = get_user_meta( $user_id, 'shortbio', true );
				$user_img = get_user_meta( $user_id, 'image', true );

				if ( $user_bio && strlen( strip_tags( $user_bio ) ) < $validator_options[ 'bio_min' ] ) {
					$errors['under-bio'] = 'This profile\'s short biography is below the character minimum. Please lengthen it and update the profile again.';
				}

				if ( !$user_bio || empty( $user_img )  ) {
					$errors['user-img'] = "Please add a profile image to complete this profile.";
				}

				// Set post meta with errors if any exist, else remove meta
				if ( !empty ( $errors ) ) {
					update_user_meta( $user_id, 'validator', $errors );
				} else {
					delete_user_meta( $user_id, 'validator' );
				}
			}
		}

		/**
		 * Check for validation errors in meta and output messages
		 *
		 * @access public
		 * @return null
		 * @author Ben Chirlin
		 */
		public function validate_notice() {
			global $pagenow;
			global $post;

			$errors = array();

			if ( is_admin() && ( $pagenow === 'post.php' || get_post_type() === 'post' ) ) {
				// Check post has valid date
				$validator_options = $this->get_validator_options();
				$on_date = $validator_options && array_key_exists( 'valid_date', $validator_options ) ? intval( $validator_options[ 'valid_date' ] ) : -1;
				$post_date = get_the_date( 'U' );

				if ( $post_date >= $on_date ) {
					// Get all errors associated with this post if any and output them as notices
					$errors = get_post_meta( $post->ID, 'validator', true );
				}
			} elseif ( is_admin() && ( $pagenow === 'profile.php' || $pagenow === 'user-edit.php' ) ) {
				// Get all errors associated with this profile if any and output them as notices
				// If is current user's profile (profile.php)
				if ( defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE ) {
					$current_user = wp_get_current_user();
					$user_id = $current_user->ID;
				// If is another user's profile page
				} elseif (! empty($_GET['user_id']) && is_numeric($_GET['user_id']) ) {
					$user_id = $_GET['user_id'];
				// Otherwise something is wrong.
				} else {
					echo '<div class="error no-id" id="notice"><p>Error: User\'s ID couldn\'t be found</p></div>';
					return;
				}

				$errors = get_user_meta( $user_id, 'validator', true );
			}

			if ( !empty( $errors ) ) {
				foreach ( $errors as $label => $error ) {
					echo '<div class="error ' . $label . '" id="notice"><p>' . $error . '</p></div>';
				}
			}

			return;
		}

		/**
		 * Helper to get limit options with global defaults
		 *
		 * @access private
		 * @return array Current options for field limits or global defaults if not set
		 * @author Ben Chirlin
		 */
		private function get_validator_options() {
			return get_option( 'validator_options', array(
				'title_validate' => '',
				'title_min' => 20,
				'title_limit' => 80,
				'excerpt_validate' => '',
				'excerpt_min' => 40,
				'excerpt_limit' => 140,
				'featured_validate' => '',
				'featured_min' => 1000,
				'bio_validate' => '',
				'bio_min' => 140,
			) );
		}

		/**
		 * Check if given post's publish date is after validator active date option
		 *
		 * @access private
		 * @param int $post_id ID of post to check
		 * @return bool Whether to validate this post not
		 * @author Ben Chirlin
		 */
		private function valid_date( $post_id ) {
			$validator_options = $this->get_validator_options();
			$on_date = $validator_options && array_key_exists( 'valid_date', $validator_options ) ? intval( $validator_options[ 'valid_date' ] ) : -1;
			$post_date = get_the_date( 'U', $post_id );

			return $post_date >= $on_date;

		}
	}
}

if ( ! function_exists( 'wired_validator_init' ) ) {
	/**
	 * Instantiate or return the one Wired_Validator instance.
	 *
	 * @return Wired_Validator
	 */
	function wired_validator_init() {
		return Wired_Validator::instance();
	}
}

// Instatiate our plugin singleton
if ( class_exists( 'Wired_Validator' ) ) {
	// 'after_setup_theme' fires immediately after functions loads
	add_action( 'after_setup_theme', 'wired_validator_init', 11 );
}