<?php
defined( 'ABSPATH' ) || exit;

$theme = wp_get_theme();

if ( 'Big Store' == $theme ) {
	return;
}

if ( ! class_exists( 'THNotice' ) ) {
	class THNotice {
		function __construct() {
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			add_action( 'admin_init', array( $this, 'notice_ignore' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'notice_scripts' ) );
		}

		function admin_notice() {
			global $current_user;
			$user_id = $current_user->ID;

			if ( ! get_user_meta( $user_id, 'th_thunk_notice_ignore' ) ) {
				?>
                <div class="notice wpc-notice">
                    <div class="wpc-notice-thumbnail">
                        <a href="https://themehunk.com/product/m-shop/?utm_source=wpc&utm_medium=banner&utm_campaign=wpc_plugins"
                           target="_blank">
                            <img src="<?php echo WPC_NOTICE . 'notice/assets/images/plugin-banner.png'; ?>"
                                 alt="Big Store - New Free WooCommerce Theme"/>
                        </a>
                    </div>
                    <div class="wpc-notice-text">
                        <h3>M Shop : ECOMMERCE WORDPRESS THEME</h3>
                        <p>
                            M Shop is an eCommerce WordPress theme specially made for shopping business websites. Theme
                            is best suited for Fashion & Clothing store, furniture, grocery, clothing, electronic,
                            gadget store and decorative gifts stores. Theme comes with Professional templates, ideal for
                            those who need to showcase their products as well as their online business, wrapped up in
                            beautiful design.
                        </p>
                        <ul class="wpc-notice-ul">
                            <li class="show-mor-message">
                                <a href="https://themehunk.com/product/m-shop/?utm_source=wpc&utm_medium=banner&utm_campaign=wpc_plugins"
                                   target="_blank">
                                    <span class="dashicons dashicons-desktop"></span>
                                    Live Demo
                                </a>
                            </li>
                            <li class="free-download-message">
                                <a href="https://themehunk.com/product/m-shop/?utm_source=wpc&utm_medium=banner&utm_campaign=wpc_plugins"
                                   target="_blank">
                                    <span class="dashicons dashicons-external"></span>
                                    Check Detail
                                </a>
                            </li>
                            <li class="hide-message">
                                <a href="?th_thunk_notice_ignore=1" class="dashicons-dismiss-icon">
                                    <span class="dashicons dashicons-welcome-comments"></span>
                                    Hide message
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
				<?php
			}
		}

		function notice_ignore() {
			global $current_user;
			$user_id = $current_user->ID;

			if ( isset( $_GET['th_thunk_notice_ignore'] ) ) {
				if ( $_GET['th_thunk_notice_ignore'] == '1' ) {
					update_user_meta( $user_id, 'th_thunk_notice_ignore', 'true', true );
				} else {
					delete_user_meta( $user_id, 'th_thunk_notice_ignore' );
				}
			}
		}

		function notice_scripts() {
			wp_enqueue_style( 'wpc-notice', WPC_NOTICE . 'notice/assets/css/notice.css' );
		}
	}

	new THNotice();
}