<?php
/*
Plugin Name: WPC Smart Wishlist for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Smart Wishlist is a simple but powerful tool that can help your customer save products for buy later.
Version: 2.7.1
Author: WPClever
Author URI: https://wpclever.net
Text Domain: woosw
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 5.7.2
WC requires at least: 3.0
WC tested up to: 5.4.1
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOSW_VERSION' ) && define( 'WOOSW_VERSION', '2.7.1' );
! defined( 'WOOSW_URI' ) && define( 'WOOSW_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOSW_PATH' ) && define( 'WOOSW_PATH', plugin_dir_path( __FILE__ ) );
! defined( 'WOOSW_SUPPORT' ) && define( 'WOOSW_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woosw&utm_campaign=wporg' );
! defined( 'WOOSW_REVIEWS' ) && define( 'WOOSW_REVIEWS', 'https://wordpress.org/support/plugin/woo-smart-wishlist/reviews/?filter=5' );
! defined( 'WOOSW_CHANGELOG' ) && define( 'WOOSW_CHANGELOG', 'https://wordpress.org/plugins/woo-smart-wishlist/#developers' );
! defined( 'WOOSW_DISCUSSION' ) && define( 'WOOSW_DISCUSSION', 'https://wordpress.org/support/plugin/woo-smart-wishlist' );
! defined( 'WPC_NOTICE' ) && define( 'WPC_NOTICE', plugin_dir_url( __FILE__ ) );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOSW_URI );

include 'includes/wpc-dashboard.php';
include 'includes/wpc-menu.php';
include 'includes/wpc-kit.php';
include 'notice/notice.php';

// plugin activate
register_activation_hook( __FILE__, 'woosw_plugin_activate' );

// plugin init
if ( ! function_exists( 'woosw_init' ) ) {
	add_action( 'plugins_loaded', 'woosw_init', 11 );

	function woosw_init() {
		// load text-domain
		load_plugin_textdomain( 'woosw', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'woosw_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WPCleverWoosw' ) ) {
			class WPCleverWoosw {
				protected static $added_products = array();

				function __construct() {
					// add query var
					add_filter( 'query_vars', array( $this, 'query_vars' ), 1 );

					add_action( 'init', array( $this, 'init' ) );

					// menu
					add_action( 'admin_menu', array( $this, 'admin_menu' ) );

					// frontend scripts
					add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

					// backend scripts
					add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 99 );

					// quickview
					add_action( 'wp_ajax_wishlist_quickview', array( $this, 'wishlist_quickview' ) );

					// add
					add_action( 'wp_ajax_wishlist_add', array( $this, 'wishlist_add' ) );
					add_action( 'wp_ajax_nopriv_wishlist_add', array( $this, 'wishlist_add' ) );

					// added to cart
					if ( get_option( 'woosw_auto_remove', 'no' ) === 'yes' ) {
						add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), 10, 2 );
					}

					// remove
					add_action( 'wp_ajax_wishlist_remove', array( $this, 'wishlist_remove' ) );
					add_action( 'wp_ajax_nopriv_wishlist_remove', array( $this, 'wishlist_remove' ) );

					// load
					add_action( 'wp_ajax_wishlist_load', array( $this, 'wishlist_load' ) );
					add_action( 'wp_ajax_nopriv_wishlist_load', array( $this, 'wishlist_load' ) );

					// link
					add_filter( 'plugin_action_links', array( $this, 'action_links' ), 10, 2 );
					add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );

					// menu items
					add_filter( 'wp_nav_menu_items', array( $this, 'woosw_nav_menu_items' ), 99, 2 );

					// footer
					add_action( 'wp_footer', array( $this, 'wp_footer' ) );

					// product columns
					add_filter( 'manage_edit-product_columns', array( $this, 'woosw_product_columns' ), 10 );
					add_action( 'manage_product_posts_custom_column', array(
						$this,
						'woosw_product_posts_custom_column'
					), 10, 2 );
					add_filter( 'manage_edit-product_sortable_columns', array(
						$this,
						'woosw_product_sortable_columns'
					) );
					add_filter( 'request', array( $this, 'woosw_product_request' ) );

					// user login & logout
					add_action( 'wp_login', array( $this, 'woosw_wp_login' ), 10, 2 );
					add_action( 'wp_logout', array( $this, 'woosw_wp_logout' ), 10, 1 );

					// user columns
					add_filter( 'manage_users_columns', array( $this, 'woosw_user_table' ) );
					add_filter( 'manage_users_custom_column', array( $this, 'woosw_user_table_row' ), 10, 3 );

					// dropdown multiple
					add_filter( 'wp_dropdown_cats', array( $this, 'dropdown_cats_multiple' ), 10, 2 );
				}

				function query_vars( $vars ) {
					$vars[] = 'woosw_id';

					return $vars;
				}

				function init() {
					// added products
					$key = isset( $_COOKIE['woosw_key'] ) ? $_COOKIE['woosw_key'] : '#';

					if ( get_option( 'woosw_list_' . $key ) ) {
						self::$added_products = get_option( 'woosw_list_' . $key );
					}

					// rewrite
					if ( $page_id = self::get_page_id() ) {
						$page_slug = get_post_field( 'post_name', $page_id );

						if ( $page_slug !== '' ) {
							add_rewrite_rule( '^' . $page_slug . '/([\w]+)/?', 'index.php?page_id=' . $page_id . '&woosw_id=$matches[1]', 'top' );
						}
					}

					// shortcode
					add_shortcode( 'woosw', array( $this, 'shortcode' ) );
					add_shortcode( 'woosw_list', array( $this, 'list_shortcode' ) );

					// add button for archive
					$button_position_archive = apply_filters( 'woosw_button_position_archive', get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) );

					switch ( $button_position_archive ) {
						case 'after_title':
							add_action( 'woocommerce_shop_loop_item_title', array( $this, 'add_button' ), 11 );
							break;
						case 'after_rating':
							add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'add_button' ), 6 );
							break;
						case 'after_price':
							add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'add_button' ), 11 );
							break;
						case 'before_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_button' ), 9 );
							break;
						case 'after_add_to_cart':
							add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_button' ), 11 );
							break;
					}

					// add button for single
					$button_position_single = apply_filters( 'woosw_button_position_single', get_option( 'woosw_button_position_single', '31' ) );

					if ( ! empty( $button_position_single ) ) {
						add_action( 'woocommerce_single_product_summary', array(
							$this,
							'add_button'
						), (int) $button_position_single );
					}
				}

				function add_to_cart( $cart_item_key, $product_id ) {
					$key = self::get_key();

					if ( $key !== '#' ) {
						$products = array();

						if ( get_option( 'woosw_list_' . $key ) ) {
							$products = get_option( 'woosw_list_' . $key );
						}

						if ( array_key_exists( $product_id, $products ) ) {
							unset( $products[ $product_id ] );
							update_option( 'woosw_list_' . $key, $products );
							$this->update_meta( $product_id, 'remove' );
						}
					}
				}

				function wishlist_add() {
					$return = array( 'status' => 0 );

					if ( ( $product_id = absint( $_POST['product_id'] ) ) > 0 ) {
						$key = self::get_key();

						if ( $key === '#' ) {
							$return['status'] = 0;
							$return['notice'] = esc_html__( 'Please log in to use the wishlist!', 'woosw' );
							$return['image']  = WOOSW_URI . 'assets/images/heart_error.svg';
						} else {
							$products = array();

							if ( get_option( 'woosw_list_' . $key ) ) {
								$products = get_option( 'woosw_list_' . $key );
							}

							if ( ! array_key_exists( $product_id, $products ) ) {
								// insert if not exists
								$products = array(
									            $product_id => array(
										            'time' => time(),
										            'note' => ''
									            )
								            ) + $products;
								update_option( 'woosw_list_' . $key, $products );
								$this->update_meta( $product_id, 'add' );
								$return['notice'] = esc_html__( 'Added to the wishlist!', 'woosw' );
								$return['image']  = WOOSW_URI . 'assets/images/heart_add.svg';
							} else {
								$return['notice'] = esc_html__( 'Already in the wishlist!', 'woosw' );
								$return['image']  = WOOSW_URI . 'assets/images/heart_duplicate.svg';
							}

							$return['status'] = 1;
							$return['count']  = count( $products );

							if ( get_option( 'woosw_button_action', 'list' ) === 'list' ) {
								$return['value'] = $this->get_items( $key );
							}
						}
					} else {
						$product_id       = 0;
						$return['status'] = 0;
						$return['notice'] = esc_html__( 'Have an error, please try again!', 'woosw' );
						$return['image']  = WOOSW_URI . 'assets/images/heart_error.svg';
					}

					do_action( 'woosw_add', $product_id );

					echo json_encode( $return );
					die();
				}

				function wishlist_remove() {
					$return = array( 'status' => 0 );

					if ( ( $product_id = absint( $_POST['product_id'] ) ) > 0 ) {
						$key = self::get_key();

						if ( $key === '#' ) {
							$return['notice'] = esc_html__( 'Please log in to use the wishlist!', 'woosw' );
						} else {
							$products = array();

							if ( get_option( 'woosw_list_' . $key ) ) {
								$products = get_option( 'woosw_list_' . $key );
							}

							if ( array_key_exists( $product_id, $products ) ) {
								unset( $products[ $product_id ] );
								update_option( 'woosw_list_' . $key, $products );
								$this->update_meta( $product_id, 'remove' );
								$return['count']  = count( $products );
								$return['status'] = 1;

								if ( count( $products ) > 0 ) {
									$return['notice'] = esc_html__( 'Removed from wishlist!', 'woosw' );
								} else {
									$return['notice'] = esc_html__( 'There are no products on the wishlist!', 'woosw' );
								}
							} else {
								$return['notice'] = esc_html__( 'The product does not exist on the wishlist!', 'woosw' );
							}
						}
					} else {
						$product_id       = 0;
						$return['notice'] = esc_html__( 'Have an error, please try again!', 'woosw' );
					}

					do_action( 'woosw_remove', $product_id );

					echo json_encode( $return );
					die();
				}

				function wishlist_load() {
					$return = array( 'status' => 0 );
					$key    = self::get_key();

					if ( $key === '#' ) {
						$return['notice'] = esc_html__( 'Please log in to use wishlist!', 'woosw' );
					} else {
						$products = array();

						if ( get_option( 'woosw_list_' . $key ) ) {
							$products = get_option( 'woosw_list_' . $key );
						}

						$return['status'] = 1;
						$return['count']  = count( $products );
						$return['value']  = $this->get_items( $key );
					}

					do_action( 'woosw_load' );

					echo json_encode( $return );
					die();
				}

				function add_button() {
					echo do_shortcode( '[woosw]' );
				}

				function shortcode( $atts ) {
					$output = '';

					$atts = shortcode_atts( array(
						'id'   => null,
						'type' => get_option( 'woosw_button_type', 'button' )
					), $atts, 'woosw' );

					if ( ! $atts['id'] ) {
						global $product;
						$atts['id'] = $product->get_id();
					}

					if ( $atts['id'] ) {
						// check cats
						$selected_cats = get_option( 'woosw_cats', array() );

						if ( ! empty( $selected_cats ) && ( $selected_cats[0] !== '0' ) ) {
							if ( ! has_term( $selected_cats, 'product_cat', $atts['id'] ) ) {
								return '';
							}
						}

						$class = 'woosw-btn woosw-btn-' . esc_attr( $atts['id'] );

						if ( array_key_exists( $atts['id'], self::$added_products ) ) {
							$class .= ' woosw-added';
							$text  = get_option( 'woosw_button_text_added' );

							if ( empty( $text ) ) {
								$text = esc_html__( 'Browse wishlist', 'woosw' );
							}

							$text = apply_filters( 'woosw_button_text_added', $text );
						} else {
							$text = get_option( 'woosw_button_text' );

							if ( empty( $text ) ) {
								$text = esc_html__( 'Add to wishlist', 'woosw' );
							}

							$text = apply_filters( 'woosw_button_text', $text );
						}

						if ( get_option( 'woosw_button_class', '' ) !== '' ) {
							$class .= ' ' . esc_attr( get_option( 'woosw_button_class' ) );
						}

						if ( $atts['type'] === 'link' ) {
							$output = '<a href="#" class="' . esc_attr( $class ) . '" data-id="' . esc_attr( $atts['id'] ) . '">' . esc_html( $text ) . '</a>';
						} else {
							$output = '<button class="' . esc_attr( $class ) . '" data-id="' . esc_attr( $atts['id'] ) . '">' . esc_html( $text ) . '</button>';
						}
					}

					return apply_filters( 'woosw_button_html', $output, $atts['id'] );
				}

				function list_shortcode() {
					if ( get_query_var( 'woosw_id' ) ) {
						$key = get_query_var( 'woosw_id' );
					} else {
						$key = self::get_key();
					}

					$share_url_raw = self::get_url( $key, true );
					$share_url     = urlencode( $share_url_raw );
					$return_html   = '<div class="woosw-list">';
					$return_html   .= $this->get_items( $key );
					$return_html   .= '<div class="woosw-actions">';

					if ( get_option( 'woosw_page_share', 'yes' ) === 'yes' ) {
						$facebook  = esc_html__( 'Facebook', 'woosw' );
						$twitter   = esc_html__( 'Twitter', 'woosw' );
						$pinterest = esc_html__( 'Pinterest', 'woosw' );
						$mail      = esc_html__( 'Mail', 'woosw' );

						if ( get_option( 'woosw_page_icon', 'yes' ) === 'yes' ) {
							$facebook = $twitter = $pinterest = $mail = "<i class='woosw-icon'></i>";
						}

						$woosw_page_items = get_option( 'woosw_page_items' );

						if ( ! empty( $woosw_page_items ) ) {
							$return_html .= '<div class="woosw-share">';
							$return_html .= '<span class="woosw-share-label">' . esc_html__( 'Share on:', 'woosw' ) . '</span>';
							$return_html .= ( in_array( "facebook", $woosw_page_items ) ) ? '<a class="woosw-share-facebook" href="https://www.facebook.com/sharer.php?u=' . $share_url . '" target="_blank">' . $facebook . '</a>' : '';
							$return_html .= ( in_array( "twitter", $woosw_page_items ) ) ? '<a class="woosw-share-twitter" href="https://twitter.com/share?url=' . $share_url . '" target="_blank">' . $twitter . '</a>' : '';
							$return_html .= ( in_array( "pinterest", $woosw_page_items ) ) ? '<a class="woosw-share-pinterest" href="https://pinterest.com/pin/create/button/?url=' . $share_url . '" target="_blank">' . $pinterest . '</a>' : '';
							$return_html .= ( in_array( "mail", $woosw_page_items ) ) ? '<a class="woosw-share-mail" href="mailto:?body=' . $share_url . '" target="_blank">' . $mail . '</a>' : '';
							$return_html .= '</div><!-- /woosw-share -->';
						}
					}

					if ( get_option( 'woosw_page_copy', 'yes' ) === 'yes' ) {
						$return_html .= '<div class="woosw-copy">';
						$return_html .= '<span class="woosw-copy-label">' . esc_html__( 'Wishlist link:', 'woosw' ) . '</span>';
						$return_html .= '<span class="woosw-copy-url"><input id="woosw_copy_url" type="url" value="' . $share_url_raw . '" readonly/></span>';
						$return_html .= '<span class="woosw-copy-btn"><input id="woosw_copy_btn" type="button" value="' . esc_html__( 'Copy', 'woosw' ) . '"/></span>';
						$return_html .= '</div><!-- /woosw-copy -->';
					}

					$return_html .= '</div><!-- /woosw-actions -->';
					$return_html .= '</div><!-- /woosw-list -->';

					return $return_html;
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', 'WPC Smart Wishlist', 'Smart Wishlist', 'manage_options', 'wpclever-woosw', array(
						&$this,
						'admin_menu_content'
					) );
				}

				function admin_menu_content() {
					add_thickbox();
					$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo 'WPC Smart Wishlist ' . WOOSW_VERSION; ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woosw' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOSW_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'woosw' ); ?></a> | <a
                                        href="<?php echo esc_url( WOOSW_CHANGELOG ); ?>"
                                        target="_blank"><?php esc_html_e( 'Changelog', 'woosw' ); ?></a>
                                | <a href="<?php echo esc_url( WOOSW_DISCUSSION ); ?>"
                                     target="_blank"><?php esc_html_e( 'Discussion', 'woosw' ); ?></a>
                            </p>
                        </div>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosw&tab=settings' ); ?>"
                                   class="<?php echo $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Settings', 'woosw' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosw&tab=premium' ); ?>"
                                   class="<?php echo $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>"
                                   style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'woosw' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                                   class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'woosw' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) { ?>
                                <form method="post" action="options.php">
									<?php wp_nonce_field( 'update-options' ) ?>
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'General', 'woosw' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Disable the wishlist for unauthenticated users', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_disable_unauthenticated">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_disable_unauthenticated', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_disable_unauthenticated', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Auto remove', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_auto_remove">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_auto_remove', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_auto_remove', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Auto remove product from the wishlist after adding to the cart.', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Link to individual product', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_link">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_link', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the same tab', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_blank" <?php echo( get_option( 'woosw_link', 'yes' ) === 'yes_blank' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the new tab', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_popup" <?php echo( get_option( 'woosw_link', 'yes' ) === 'yes_popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open quick view popup', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_link', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woosw' ); ?>
                                                    </option>
                                                </select> <span class="description">If you choose "Open quick view popup", please install <a
                                                            href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                            class="thickbox" title="Install WPC Smart Quick View">WPC Smart Quick View</a> to make it work.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show note', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_show_note">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_show_note', 'no' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_show_note', 'no' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Show note on each product for all visitors. Only wishlist owner can add/edit these notes.', 'woosw' ); ?>
										</span>
                                                <p style="color: #c9356e">
                                                    This feature is only available on the Premium Version. Click <a
                                                            href="https://wpclever.net/downloads/woocommerce-smart-wishlist?utm_source=pro&utm_medium=woosw&utm_campaign=wporg"
                                                            target="_blank">here</a> to buy, just $29.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Page', 'woosw' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for wishlist page.', 'woosw' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Wishlist page', 'woosw' ); ?></th>
                                            <td>
												<?php wp_dropdown_pages( array(
													'selected'          => get_option( 'woosw_page_id', '' ),
													'name'              => 'woosw_page_id',
													'show_option_none'  => esc_html__( 'Choose a page', 'woosw' ),
													'option_none_value' => '',
												) ); ?>
                                                <span class="description">
											<?php printf( esc_html__( 'Add shortcode %s to display the wishlist on a page.', 'woosw' ), '<code>[woosw_list]</code>' ); ?>
                                                    <br/>
													<?php esc_html_e( 'After choosing a page, please go to Setting >> Permalinks and press Save Changes.', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Share buttons', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_page_share">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_page_share', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_page_share', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
                                                    <?php esc_html_e( 'Enable share buttons on the wishlist page?', 'woosw' ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Use font icon', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_page_icon">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_page_icon', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_page_icon', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Social links', 'woosw' ); ?></th>
                                            <td>
												<?php
												$woosw_page_items = get_option( 'woosw_page_items' );

												if ( empty( $woosw_page_items ) ) {
													$woosw_page_items = array();
												}
												?>
                                                <select multiple name="woosw_page_items[]" id='woosw_page_items'>
                                                    <option <?php echo ( in_array( "facebook", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="facebook"><?php esc_html_e( 'Facebook', 'woosw' ); ?>
                                                    </option>
                                                    <option <?php echo ( in_array( "twitter", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="twitter"><?php esc_html_e( 'Twitter', 'woosw' ); ?>
                                                    </option>
                                                    <option <?php echo ( in_array( "pinterest", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="pinterest"><?php esc_html_e( 'Pinterest', 'woosw' ); ?>
                                                    </option>
                                                    <option <?php echo ( in_array( "mail", $woosw_page_items ) ) ? "selected" : ""; ?>
                                                            value="mail"><?php esc_html_e( 'Mail', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Copy link', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_page_copy">
                                                    <option
                                                            value="yes" <?php echo( get_option( 'woosw_page_copy', 'yes' ) === 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_page_copy', 'yes' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
                                                    <?php esc_html_e( 'Enable copy wishlist link to share?', 'woosw' ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Button', 'woosw' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for "Add to wishlist" button.', 'woosw' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Type', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_button_type">
                                                    <option
                                                            value="button" <?php echo( get_option( 'woosw_button_type', 'button' ) === 'button' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Button', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="link" <?php echo( get_option( 'woosw_button_type', 'button' ) === 'link' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Link', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Text', 'woosw' ); ?></th>
                                            <td>
                                                <input type="text" name="woosw_button_text"
                                                       value="<?php echo get_option( 'woosw_button_text', '' ); ?>"
                                                       placeholder="<?php esc_html_e( 'Add to wishlist', 'woosw' ); ?>"/>
                                                <span class="description">
													<?php esc_html_e( 'Leave blank to use the default text or its equivalent translation in multiple languages.', 'woosw' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_button_action">
                                                    <option
                                                            value="message" <?php echo( get_option( 'woosw_button_action', 'list' ) === 'message' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Show message', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="list" <?php echo( get_option( 'woosw_button_action', 'list' ) === 'list' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Show product list', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( 'woosw_button_action', 'list' ) === 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Add to wishlist solely', 'woosw' ); ?>
                                                    </option>
                                                </select> <span class="description">
											<?php esc_html_e( 'Action triggered by clicking on the wishlist button.', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Text (added)', 'woosw' ); ?></th>
                                            <td>
                                                <input type="text" name="woosw_button_text_added"
                                                       value="<?php echo get_option( 'woosw_button_text_added', '' ); ?>"
                                                       placeholder="<?php esc_html_e( 'Browse wishlist', 'woosw' ); ?>"/>
                                                <span class="description">
													<?php esc_html_e( 'Text shown after adding an item to the wishlist. Leave blank to use the default text or its equivalent translation in multiple languages.', 'woosw' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action (added)', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_button_action_added">
                                                    <option
                                                            value="popup" <?php echo( get_option( 'woosw_button_action_added', 'popup' ) === 'popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open wishlist popup', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="page" <?php echo( get_option( 'woosw_button_action_added', 'popup' ) === 'page' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open wishlist page', 'woosw' ); ?>
                                                    </option>
                                                </select> <span class="description">
											<?php esc_html_e( 'Action triggered by clicking on the wishlist button after adding an item to the wishlist.', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Extra class (optional)', 'woosw' ); ?></th>
                                            <td>
                                                <input type="text" name="woosw_button_class"
                                                       value="<?php echo get_option( 'woosw_button_class', '' ); ?>"/>
                                                <span class="description">
											<?php esc_html_e( 'Add extra class for action button/link, split by one space.', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on archive page', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_button_position_archive">
                                                    <option
                                                            value="after_title" <?php echo( get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) === 'after_title' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under title', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="after_rating" <?php echo( get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) === 'after_rating' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under rating', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="after_price" <?php echo( get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) === 'after_price' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under price', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="before_add_to_cart" <?php echo( get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) === 'before_add_to_cart' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Above add to cart button', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="after_add_to_cart" <?php echo( get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) === 'after_add_to_cart' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under add to cart button', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="0" <?php echo( get_option( 'woosw_button_position_archive', 'after_add_to_cart' ) === '0' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'None (hide it)', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position on single page', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_button_position_single">
                                                    <option
                                                            value="6" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '6' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under title', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="11" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '11' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under price & rating', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="21" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '21' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under excerpt', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="29" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '29' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Above add to cart button', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="31" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '31' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under add to cart button', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="41" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '41' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under meta', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="51" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '51' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Under sharing', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="0" <?php echo( get_option( 'woosw_button_position_single', '31' ) === '0' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'None (hide it)', 'woosw' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Shortcode', 'woosw' ); ?></th>
                                            <td>
                                                <span class="description">
                                                    <?php printf( esc_html__( 'You can add a button manually by using the shortcode %s, eg. %s for the product whose ID is 99.', 'woosw' ), '<code>[woosw id="{product id}"]</code>', '<code>[woosw id="99"]</code>' ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Categories', 'woosw' ); ?></th>
                                            <td>
												<?php
												$selected_cats = get_option( 'woosw_cats' );

												if ( empty( $selected_cats ) ) {
													$selected_cats = array( 0 );
												}

												wc_product_dropdown_categories(
													array(
														'name'             => 'woosw_cats',
														'hide_empty'       => 0,
														'value_field'      => 'id',
														'multiple'         => true,
														'show_option_all'  => esc_html__( 'All categories', 'woosw' ),
														'show_option_none' => '',
														'selected'         => implode( ',', $selected_cats )
													) );
												?>
                                                <span class="description">
													<?php esc_html_e( 'Only show the wishlist button for products in selected categories.', 'woosw' ); ?>
												</span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Popup', 'woosw' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for the wishlist popup.', 'woosw' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Color', 'woosw' ); ?></th>
                                            <td>
												<?php $color_default = apply_filters( 'woosw_color_default', '#5fbd74' ); ?>
                                                <input type="text" name="woosw_color"
                                                       value="<?php echo get_option( 'woosw_color', $color_default ); ?>"
                                                       class="woosw_color_picker"/>
                                                <span class="description">
											<?php printf( esc_html__( 'Choose the color, default %s', 'woosw' ), '<code>' . $color_default . '</code>' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Continue shopping link', 'woosw' ); ?></th>
                                            <td>
                                                <input type="url" name="woosw_continue_url"
                                                       value="<?php echo get_option( 'woosw_continue_url' ); ?>"
                                                       class="regular-text code"/> <span
                                                        class="description">
											<?php esc_html_e( 'By default, the wishlist popup will only be closed when customers click on the "Continue Shopping" button.', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( 'Menu', 'woosw' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Settings for the wishlist menu item.', 'woosw' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Menu(s)', 'woosw' ); ?></th>
                                            <td>
												<?php
												$nav_args    = array(
													'hide_empty' => false,
													'fields'     => 'id=>name',
												);
												$nav_menus   = get_terms( 'nav_menu', $nav_args );
												$saved_menus = get_option( 'woosw_menus', array() );

												foreach ( $nav_menus as $nav_id => $nav_name ) {
													echo '<input type="checkbox" name="woosw_menus[]" value="' . $nav_id . '" ' . ( is_array( $saved_menus ) && in_array( $nav_id, $saved_menus, false ) ? 'checked' : '' ) . '/><label>' . $nav_name . '</label><br/>';
												}
												?>
                                                <span class="description">
											<?php esc_html_e( 'Choose the menu(s) you want to add the "wishlist menu" at the end.', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Action', 'woosw' ); ?></th>
                                            <td>
                                                <select name="woosw_menu_action">
                                                    <option
                                                            value="open_page" <?php echo( get_option( 'woosw_menu_action', 'open_page' ) === 'open_page' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open page', 'woosw' ); ?>
                                                    </option>
                                                    <option
                                                            value="open_popup" <?php echo( get_option( 'woosw_menu_action', 'open_page' ) === 'open_popup' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Open popup', 'woosw' ); ?>
                                                    </option>
                                                </select> <span class="description">
											<?php esc_html_e( 'Action when clicking on the "wishlist menu".', 'woosw' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <input type="submit" name="submit" class="button button-primary"
                                                       value="<?php esc_html_e( 'Update Options', 'woosw' ); ?>"/>
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="page_options"
                                                       value="woosw_disable_unauthenticated,woosw_auto_remove,woosw_link,woosw_show_note,woosw_page_id,woosw_page_share,woosw_page_icon,woosw_page_items,woosw_page_copy,woosw_button_type,woosw_button_text,woosw_button_action,woosw_button_text_added,woosw_button_action_added,woosw_button_class,woosw_button_position_archive,woosw_button_position_single,woosw_cats,woosw_color,woosw_continue_url,woosw_menus,woosw_menu_action"/>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>Get the Premium Version just $29! <a
                                                href="https://wpclever.net/downloads/woocommerce-smart-wishlist?utm_source=pro&utm_medium=woosw&utm_campaign=wporg"
                                                target="_blank">https://wpclever.net/downloads/woocommerce-smart-wishlist</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version:</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Enable note for each product.</li>
                                        <li>- Get lifetime update & premium support.</li>
                                    </ul>
                                </div>
							<?php } ?>
                        </div>
                    </div>
					<?php
				}

				function wp_enqueue_scripts() {
					// perfect srollbar
					wp_enqueue_style( 'perfect-scrollbar', WOOSW_URI . 'assets/libs/perfect-scrollbar/css/perfect-scrollbar.min.css' );
					wp_enqueue_style( 'perfect-scrollbar-wpc', WOOSW_URI . 'assets/libs/perfect-scrollbar/css/custom-theme.css' );
					wp_enqueue_script( 'perfect-scrollbar', WOOSW_URI . 'assets/libs/perfect-scrollbar/js/perfect-scrollbar.jquery.min.js', array( 'jquery' ), WOOSW_VERSION, true );

					// feather icons
					wp_enqueue_style( 'woosw-feather', WOOSW_URI . 'assets/libs/feather/feather.css' );

					// main style
					wp_enqueue_style( 'woosw-frontend', WOOSW_URI . 'assets/css/frontend.css' );
					$color_default = apply_filters( 'woosw_color_default', '#5fbd74' );
					$color         = apply_filters( 'woosw_color', get_option( 'woosw_color', $color_default ) );
					$custom_css    = ".woosw-area .woosw-inner .woosw-content .woosw-content-bot .woosw-notice { background-color: {$color}; } ";
					$custom_css    .= ".woosw-area .woosw-inner .woosw-content .woosw-content-bot .woosw-content-bot-inner .woosw-page a:hover, .woosw-area .woosw-inner .woosw-content .woosw-content-bot .woosw-content-bot-inner .woosw-continue:hover { color: {$color}; } ";
					wp_add_inline_style( 'woosw-frontend', $custom_css );

					// main js
					wp_enqueue_script( 'woosw-frontend', WOOSW_URI . 'assets/js/frontend.js', array( 'jquery' ), WOOSW_VERSION, true );

					// text
					$text = get_option( 'woosw_button_text' );

					if ( empty( $text ) ) {
						$text = esc_html__( 'Add to wishlist', 'woosw' );
					}

					$text = apply_filters( 'woosw_button_text', $text );

					// text added
					$text_added = get_option( 'woosw_button_text_added' );

					if ( empty( $text_added ) ) {
						$text_added = esc_html__( 'Browse wishlist', 'woosw' );
					}

					$text_added = apply_filters( 'woosw_button_text_added', $text_added );

					// localize
					wp_localize_script( 'woosw-frontend', 'woosw_vars', array(
							'ajax_url'            => admin_url( 'admin-ajax.php' ),
							'menu_action'         => get_option( 'woosw_menu_action', 'open_page' ),
							'copied_text'         => esc_html__( 'Copied the wishlist link:', 'woosw' ),
							'menu_text'           => esc_html__( 'Wishlist', 'woosw' ),
							'wishlist_url'        => self::get_url(),
							'button_text'         => esc_html( $text ),
							'button_action'       => get_option( 'woosw_button_action', 'list' ),
							'button_text_added'   => esc_html( $text_added ),
							'button_action_added' => get_option( 'woosw_button_action_added', 'popup' )
						)
					);
				}

				function admin_enqueue_scripts( $hook ) {
					if ( strpos( $hook, 'woosw' ) ) {
						wp_enqueue_style( 'wp-color-picker' );
						wp_enqueue_script( 'woosw-backend', WOOSW_URI . 'assets/js/backend.js', array(
							'jquery',
							'wp-color-picker'
						) );
					} else {
						wp_dequeue_style( 'jquery-ui-style' );
						wp_enqueue_style( 'woosw-backend', WOOSW_URI . 'assets/css/backend.css' );
						wp_enqueue_script( 'woosw-backend', WOOSW_URI . 'assets/js/backend.js', array(
							'jquery',
							'jquery-ui-dialog'
						), WOOSW_VERSION, true );
						wp_localize_script( 'woosw-backend', 'woosw_vars', array(
							'nonce' => wp_create_nonce( 'woosw_nonce' )
						) );
					}
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings         = '<a href="' . admin_url( 'admin.php?page=wpclever-woosw&tab=settings' ) . '">' . esc_html__( 'Settings', 'woosw' ) . '</a>';
						$links['premium'] = '<a href="' . admin_url( 'admin.php?page=wpclever-woosw&tab=premium' ) . '" style="color: #c9356e">' . esc_html__( 'Premium Version', 'woosw' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = array(
							'support' => '<a href="' . esc_url( WOOSW_SUPPORT ) . '" target="_blank">' . esc_html__( 'Support', 'woosw' ) . '</a>',
						);

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function get_items( $key ) {
					$products = get_option( 'woosw_list_' . $key );
					$link     = get_option( 'woosw_link', 'yes' );
					ob_start();

					if ( is_array( $products ) && ( count( $products ) > 0 ) ) {
						echo '<table class="woosw-content-items">';

						do_action( 'woosw_wishlist_items_before', $key );

						foreach ( $products as $product_id => $product_data ) {
							$product = wc_get_product( $product_id );

							if ( ! $product ) {
								continue;
							}

							if ( is_array( $product_data ) && isset( $product_data['time'] ) ) {
								$product_time = date_i18n( get_option( 'date_format' ), $product_data['time'] );
							} else {
								// for old version
								$product_time = date_i18n( get_option( 'date_format' ), $product_data );
							}

							if ( is_array( $product_data ) && ! empty( $product_data['note'] ) ) {
								$product_note = $product_data['note'];
							} else {
								$product_note = '';
							} ?>

                            <tr class="woosw-content-item woosw-content-item-<?php echo esc_attr( $product_id ); ?>"
                                data-id="<?php echo esc_attr( $product_id ); ?>"
                                data-key="<?php echo esc_attr( $key ); ?>">

								<?php do_action( 'woosw_wishlist_item_before', $product, $product_id, $key ); ?>

								<?php if ( self::can_edit( $key ) ) { ?>
                                    <td class="woosw-content-item--remove"><span></span></td>
								<?php } ?>

                                <td class="woosw-content-item--image">
									<?php if ( $link !== 'no' ) { ?>
                                        <a <?php echo ( $link === 'yes_popup' ? 'class="woosq-btn" data-id="' . $product_id . '"' : '' ) . ' href="' . $product->get_permalink() . '" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ); ?>>
											<?php echo $product->get_image(); ?>
                                        </a>
									<?php } else {
										echo $product->get_image();
									}

									do_action( 'woosw_wishlist_item_image', $product, $product_id, $key ); ?>
                                </td>

                                <td class="woosw-content-item--info">
                                    <div class="woosw-content-item--title">
										<?php if ( $link !== 'no' ) { ?>
                                            <a <?php echo ( $link === 'yes_popup' ? 'class="woosq-btn" data-id="' . $product_id . '"' : '' ) . ' href="' . $product->get_permalink() . '" ' . ( $link === 'yes_blank' ? 'target="_blank"' : '' ); ?>>
												<?php echo $product->get_name(); ?>
                                            </a>
										<?php } else {
											echo $product->get_name();
										} ?>
                                    </div>

                                    <div class="woosw-content-item--price">
										<?php echo $product->get_price_html(); ?>
                                    </div>

                                    <div class="woosw-content-item--time">
										<?php echo $product_time; ?>
                                    </div>

									<?php do_action( 'woosw_wishlist_item_info', $product, $product_id, $key ); ?>
                                </td>

                                <td class="woosw-content-item--actions">
                                    <div class="woosw-content-item--stock">
										<?php echo( $product->is_in_stock() ? esc_html__( 'In stock', 'woosw' ) : esc_html__( 'Out of stock', 'woosw' ) ); ?>
                                    </div>

                                    <div class="woosw-content-item--add">
										<?php echo do_shortcode( '[add_to_cart id="' . $product_id . '"]' ); ?>
                                    </div>

									<?php do_action( 'woosw_wishlist_item_actions', $product, $product_id, $key ); ?>
                                </td>

								<?php do_action( 'woosw_wishlist_item_after', $product, $product_id, $key ); ?>
                            </tr>
						<?php }

						do_action( 'woosw_wishlist_items_after', $key );

						echo '</table>';
					} else { ?>
                        <div class="woosw-content-mid-notice">
							<?php esc_html_e( 'There are no products on the wishlist!', 'woosw' ); ?>
                        </div>
					<?php }

					$items_html = ob_get_clean();

					return apply_filters( 'woosw_wishlist_items', $items_html, $key );
				}

				function woosw_nav_menu_items( $items, $args ) {
					$selected    = false;
					$saved_menus = get_option( 'woosw_menus', array() );

					if ( ! is_array( $saved_menus ) || empty( $saved_menus ) || ! property_exists( $args, 'menu' ) ) {
						return $items;
					}

					if ( $args->menu instanceof WP_Term ) {
						// menu object
						if ( in_array( $args->menu->term_id, $saved_menus, false ) ) {
							$selected = true;
						}
					} elseif ( is_numeric( $args->menu ) ) {
						// menu id
						if ( in_array( $args->menu, $saved_menus, false ) ) {
							$selected = true;
						}
					} elseif ( is_string( $args->menu ) ) {
						// menu slug or name
						$menu = get_term_by( 'name', $args->menu, 'nav_menu' );

						if ( ! $menu ) {
							$menu = get_term_by( 'slug', $args->menu, 'nav_menu' );
						}

						if ( $menu && in_array( $menu->term_id, $saved_menus, false ) ) {
							$selected = true;
						}
					}

					if ( $selected ) {
						$items .= '<li class="menu-item woosw-menu-item menu-item-type-woosw"><a href="' . self::get_url() . '"><span class="woosw-menu-item-inner" data-count="' . self::get_count() . '">' . esc_html__( 'Wishlist', 'woosw' ) . '</span></a></li>';
					}

					return $items;
				}

				function wp_footer() {
					?>
                    <div id="woosw-area" class="woosw-area">
                        <div class="woosw-inner">
                            <div class="woosw-content">
                                <div class="woosw-content-top">
									<?php esc_html_e( 'Wishlist', 'woosw' ); ?> <span
                                            class="woosw-count"><?php echo count( self::$added_products ); ?></span>
                                    <span class="woosw-close"></span>
                                </div>
                                <div class="woosw-content-mid"></div>
                                <div class="woosw-content-bot">
                                    <div class="woosw-content-bot-inner">
								<span class="woosw-page">
									<a href="<?php echo self::get_url( self::get_key() ); ?>"><?php esc_html_e( 'Open wishlist page', 'woosw' ); ?></a>
								</span>
                                        <span class="woosw-continue"
                                              data-url="<?php echo get_option( 'woosw_continue_url' ); ?>">
									<?php esc_html_e( 'Continue shopping', 'woosw' ); ?>
								</span>
                                    </div>
                                    <div class="woosw-notice"></div>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function update_meta( $product_id, $action = 'add' ) {
					$meta_count = 'woosw_count';
					$meta_time  = ( $action === 'add' ? 'woosw_add' : 'woosw_remove' );
					$count      = get_post_meta( $product_id, $meta_count, true );
					$new_count  = 0;

					if ( $action === 'add' ) {
						if ( $count ) {
							$new_count = absint( $count ) + 1;
						} else {
							$new_count = 1;
						}
					} elseif ( $action === 'remove' ) {
						if ( $count && ( absint( $count ) > 1 ) ) {
							$new_count = absint( $count ) - 1;
						} else {
							$new_count = 0;
						}
					}

					update_post_meta( $product_id, $meta_count, $new_count );
					update_post_meta( $product_id, $meta_time, time() );
				}

				public static function generate_key() {
					$key         = '';
					$key_str     = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
					$key_str_len = strlen( $key_str );

					for ( $i = 0; $i < 6; $i ++ ) {
						$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
					}

					return $key;
				}

				public static function exists_key( $key ) {
					if ( get_option( 'woosw_list_' . $key ) ) {
						return true;
					}

					return false;
				}

				public static function can_edit( $key ) {
					if ( is_user_logged_in() ) {
						if ( get_user_meta( get_current_user_id(), 'woosw_key', true ) === $key ) {
							return true;
						}
					} else {
						if ( isset( $_COOKIE['woosw_key'] ) && ( $_COOKIE['woosw_key'] === $key ) ) {
							return true;
						}
					}

					return false;
				}

				public static function get_page_id() {
					if ( get_option( 'woosw_page_id' ) ) {
						return absint( get_option( 'woosw_page_id' ) );
					}

					return false;
				}

				public static function get_key() {
					if ( ! is_user_logged_in() && ( get_option( 'woosw_disable_unauthenticated', 'no' ) === 'yes' ) ) {
						return '#';
					}

					if ( is_user_logged_in() && ( ( $user_id = get_current_user_id() ) > 0 ) ) {
						return get_user_meta( $user_id, 'woosw_key', true ) ?: '#';
					}

					if ( isset( $_COOKIE['woosw_key'] ) ) {
						return esc_attr( $_COOKIE['woosw_key'] );
					}

					return 'WOOSW';
				}

				public static function get_url( $key = null, $full = false ) {
					$url = home_url( '/' );

					if ( $page_id = self::get_page_id() ) {
						if ( $full ) {
							if ( ! $key ) {
								$key = self::get_key();
							}

							if ( get_option( 'permalink_structure' ) !== '' ) {
								$url = trailingslashit( get_permalink( $page_id ) ) . $key;
							} else {
								$url = get_permalink( $page_id ) . '&woosw_id=' . $key;
							}
						} else {
							$url = get_permalink( $page_id );
						}
					}

					return apply_filters( 'woosw_wishlist_url', $url, $key );
				}

				public static function get_count( $key = null ) {
					if ( ! $key ) {
						$key = self::get_key();
					}

					if ( ( $key != '' ) && ( $products = get_option( 'woosw_list_' . $key ) ) && is_array( $products ) ) {
						return apply_filters( 'woosw_wishlist_count', count( $products ), $key );
					} else {
						return apply_filters( 'woosw_wishlist_count', 0, $key );
					}
				}

				function woosw_product_columns( $columns ) {
					$columns['woosw'] = esc_html__( 'Wishlist', 'woosw' );

					return $columns;
				}

				function woosw_product_posts_custom_column( $column, $postid ) {
					if ( $column == 'woosw' ) {
						if ( ( $count = (int) get_post_meta( $postid, 'woosw_count', true ) ) > 0 ) {
							echo '<a href="#" class="woosw_action" data-pid="' . $postid . '">' . $count . '</a>';
						}
					}
				}

				function woosw_product_sortable_columns( $columns ) {
					$columns['woosw'] = 'woosw';

					return $columns;
				}

				function woosw_product_request( $vars ) {
					if ( isset( $vars['orderby'] ) && 'woosw' == $vars['orderby'] ) {
						$vars = array_merge( $vars, array(
							'meta_key' => 'woosw_count',
							'orderby'  => 'meta_value_num'
						) );
					}

					return $vars;
				}

				function woosw_wp_login( $user_login, $user ) {
					if ( isset( $user->data->ID ) ) {
						$user_key = get_user_meta( $user->data->ID, 'woosw_key', true );

						if ( ! $user_key || empty( $user_key ) ) {
							$user_key = self::generate_key();

							while ( self::exists_key( $user_key ) ) {
								$user_key = self::generate_key();
							}

							// set a new key
							update_user_meta( $user->data->ID, 'woosw_key', $user_key );
						}

						$secure   = apply_filters( 'woosw_cookie_secure', wc_site_is_https() && is_ssl() );
						$httponly = apply_filters( 'woosw_cookie_httponly', true );

						if ( isset( $_COOKIE['woosw_key'] ) && ! empty( $_COOKIE['woosw_key'] ) ) {
							wc_setcookie( 'woosw_key_ori', $_COOKIE['woosw_key'], time() + 604800, $secure, $httponly );
						}

						wc_setcookie( 'woosw_key', $user_key, time() + 604800, $secure, $httponly );
					}
				}

				function woosw_wp_logout( $user_id ) {
					if ( isset( $_COOKIE['woosw_key_ori'] ) && ! empty( $_COOKIE['woosw_key_ori'] ) ) {
						$secure   = apply_filters( 'woosw_cookie_secure', wc_site_is_https() && is_ssl() );
						$httponly = apply_filters( 'woosw_cookie_httponly', true );

						wc_setcookie( 'woosw_key', $_COOKIE['woosw_key_ori'], time() + 604800, $secure, $httponly );
					} else {
						unset( $_COOKIE['woosw_key_ori'] );
						unset( $_COOKIE['woosw_key'] );
					}
				}

				function dropdown_cats_multiple( $output, $r ) {
					if ( isset( $r['multiple'] ) && $r['multiple'] ) {
						$output = preg_replace( '/^<select/i', '<select multiple', $output );
						$output = str_replace( "name='{$r['name']}'", "name='{$r['name']}[]'", $output );

						foreach ( array_map( 'trim', explode( ",", $r['selected'] ) ) as $value ) {
							$output = str_replace( "value=\"{$value}\"", "value=\"{$value}\" selected", $output );
						}
					}

					return $output;
				}

				function woosw_user_table( $column ) {
					$column['woosw'] = esc_html__( 'Wishlist', 'woosw' );

					return $column;
				}

				function woosw_user_table_row( $val, $column_name, $user_id ) {
					if ( $column_name === 'woosw' ) {
						$key = get_user_meta( $user_id, 'woosw_key', true );

						if ( ( $key != '' ) && ( $products = get_option( 'woosw_list_' . $key, true ) ) ) {
							if ( is_array( $products ) && ( $count = count( $products ) ) ) {
								$val = '<a href="#" class="woosw_action" data-key="' . $key . '">' . $count . '</a>';
							}
						}
					}

					return $val;
				}

				function wishlist_quickview() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woosw_nonce' ) ) {
						die( esc_html__( 'Permissions check failed', 'woosw' ) );
					}

					global $wpdb;
					$wishlist_html = '';

					if ( isset( $_POST['key'] ) && $_POST['key'] != '' ) {
						$key      = $_POST['key'];
						$products = get_option( 'woosw_list_' . $_POST['key'], true );
						$count    = count( $products );
						ob_start();

						if ( count( $products ) > 0 ) {
							echo '<div class="woosw-quickview-items">';

							$user = $wpdb->get_results( 'SELECT user_id FROM `' . $wpdb->prefix . 'usermeta` WHERE `meta_key` = "woosw_key" AND `meta_value` = "' . $key . '" LIMIT 1', OBJECT );

							echo '<div class="woosw-quickview-item">';
							echo '<div class="woosw-quickview-item-image"><a href="' . self::get_url( $key, true ) . '" target="_blank">#' . $key . '</a></div>';
							echo '<div class="woosw-quickview-item-info">';

							if ( ! empty( $user ) ) {
								$user_id   = $user[0]->user_id;
								$user_data = get_userdata( $user_id );

								echo '<div class="woosw-quickview-item-title"><a href="' . get_edit_user_link( $user_id ) . '" target="_blank">' . $user_data->user_login . '</a></div>';
								echo '<div class="woosw-quickview-item-data">' . $user_data->user_email . ' | ' . sprintf( _n( '%s product', '%s products', $count, 'woosw' ), number_format_i18n( $count ) ) . '</div>';
							} else {
								echo '<div class="woosw-quickview-item-title">' . esc_html__( 'Guest', 'woosw' ) . '</div>';
								echo '<div class="woosw-quickview-item-data">' . sprintf( _n( '%s product', '%s products', $count, 'woosw' ), number_format_i18n( $count ) ) . '</div>';
							}

							echo '</div><!-- /woosw-quickview-item-info -->';
							echo '</div><!-- /woosw-quickview-item -->';

							foreach ( $products as $pid => $data ) {
								$_product = wc_get_product( $pid );

								if ( $_product ) {
									echo '<div class="woosw-quickview-item">';
									echo '<div class="woosw-quickview-item-image">' . $_product->get_image() . '</div>';
									echo '<div class="woosw-quickview-item-info">';
									echo '<div class="woosw-quickview-item-title"><a href="' . $_product->get_permalink() . '" target="_blank">' . $_product->get_name() . '</a></div>';
									echo '<div class="woosw-quickview-item-data">' . date_i18n( get_option( 'date_format' ), $data['time'] ) . ' <span class="woosw-quickview-item-links">| ID: ' . $pid . ' | <a href="' . get_edit_post_link( $pid ) . '" target="_blank">' . esc_html__( 'Edit', 'woosw' ) . '</a> | <a href="#" class="woosw_action" data-pid="' . $pid . '">' . esc_html__( 'See in wishlist', 'woosw' ) . '</a></span></div>';
									echo '</div><!-- /woosw-quickview-item-info -->';
									echo '</div><!-- /woosw-quickview-item -->';
								}
							}

							echo '</div>';
						} else {
							echo '<div style="text-align: center">' . esc_html__( 'Empty Wishlist', 'woosw' ) . '<div>';
						}

						$wishlist_html = ob_get_clean();
					} elseif ( isset( $_POST['pid'] ) ) {
						$pid = $_POST['pid'];
						ob_start();

						$keys  = $wpdb->get_results( 'SELECT option_name FROM `' . $wpdb->prefix . 'options` WHERE `option_name` LIKE "%woosw_list_%" AND `option_value` LIKE "%i:' . $pid . ';%"', OBJECT );
						$count = count( $keys );

						if ( $count > 0 ) {
							echo '<div class="woosw-quickview-items">';

							$_product = wc_get_product( $pid );

							if ( $_product ) {
								echo '<div class="woosw-quickview-item">';
								echo '<div class="woosw-quickview-item-image">' . $_product->get_image() . '</div>';
								echo '<div class="woosw-quickview-item-info">';
								echo '<div class="woosw-quickview-item-title"><a href="' . $_product->get_permalink() . '" target="_blank">' . $_product->get_name() . '</a></div>';
								echo '<div class="woosw-quickview-item-data">ID: ' . $pid . ' | ' . sprintf( _n( '%s wishlist', '%s wishlists', $count, 'woosw' ), number_format_i18n( $count ) ) . ' <span class="woosw-quickview-item-links">| <a href="' . get_edit_post_link( $pid ) . '" target="_blank">' . esc_html__( 'Edit', 'woosw' ) . '</a></span></div>';
								echo '</div><!-- /woosw-quickview-item-info -->';
								echo '</div><!-- /woosw-quickview-item -->';
							}

							foreach ( $keys as $item ) {
								$products = get_option( $item->option_name );
								$count    = count( $products );
								$key      = str_replace( 'woosw_list_', '', $item->option_name );
								$user     = $wpdb->get_results( 'SELECT user_id FROM `' . $wpdb->prefix . 'usermeta` WHERE `meta_key` = "woosw_key" AND `meta_value` = "' . $key . '" LIMIT 1', OBJECT );

								echo '<div class="woosw-quickview-item">';
								echo '<div class="woosw-quickview-item-image"><a href="' . self::get_url( $key, true ) . '" target="_blank">#' . $key . '</a></div>';
								echo '<div class="woosw-quickview-item-info">';

								if ( ! empty( $user ) ) {
									$user_id   = $user[0]->user_id;
									$user_data = get_userdata( $user_id );


									echo '<div class="woosw-quickview-item-title"><a href="' . get_edit_user_link( $user_id ) . '" target="_blank">' . $user_data->user_login . '</a></div>';
									echo '<div class="woosw-quickview-item-data">' . $user_data->user_email . '  | <a href="#" class="woosw_action" data-key="' . $key . '">' . sprintf( _n( '%s product', '%s products', $count, 'woosw' ), number_format_i18n( $count ) ) . '</a></div>';
								} else {
									echo '<div class="woosw-quickview-item-title">' . esc_html__( 'Guest', 'woosw' ) . '</div>';
									echo '<div class="woosw-quickview-item-data"><a href="#" class="woosw_action" data-key="' . $key . '">' . sprintf( _n( '%s product', '%s products', $count, 'woosw' ), number_format_i18n( $count ) ) . '</a></div>';
								}

								echo '</div><!-- /woosw-quickview-item-info -->';
								echo '</div><!-- /woosw-quickview-item -->';
							}

							echo '</div>';
						}

						$wishlist_html = ob_get_clean();
					}

					echo $wishlist_html;
					die();
				}
			}

			new WPCleverWoosw();
		}
	}
} else {
	add_action( 'admin_notices', 'woosw_notice_premium' );
}

if ( ! function_exists( 'woosw_plugin_activate' ) ) {
	function woosw_plugin_activate() {
		// create wishlist page
		$wishlist_page = get_page_by_path( 'wishlist', OBJECT );

		if ( empty( $wishlist_page ) ) {
			$wishlist_page_data = array(
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_name'      => 'wishlist',
				'post_title'     => esc_html__( 'Wishlist', 'woosw' ),
				'post_content'   => '[woosw_list]',
				'post_parent'    => 0,
				'comment_status' => 'closed'
			);
			$wishlist_page_id   = wp_insert_post( $wishlist_page_data );

			update_option( 'woosw_page_id', $wishlist_page_id );
		}
	}
}

if ( ! function_exists( 'woosw_notice_wc' ) ) {
	function woosw_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Smart Wishlist</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}

if ( ! function_exists( 'woosw_notice_premium' ) ) {
	function woosw_notice_premium() {
		?>
        <div class="error">
            <p>Seems you're using both free and premium version of <strong>WPC Smart Wishlist</strong>. Please
                deactivate the free version when using the premium version.</p>
        </div>
		<?php
	}
}