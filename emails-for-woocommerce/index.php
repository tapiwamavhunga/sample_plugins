<?php
/**
 * Plugin Name: WooCommerce Send Emails
 * Plugin URI: https://methys.com
 * Description: WooCommerce Send Emails allows you to preview and send any of the WooCommerce transactional emails.
 * Version: 1.0
 * Contributors: tapiwamavhunga, methysdigital
 * Author: Tapiwa Mavhunga, Methys Digital
 * Author URI: https://methysdigital.com
 * License: GPLv2 or later
 * Text Domain: woocommerce-send-emails
 * Domain Path: /localization/
 */

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Define Constants
 */
define( 'CX_WC_SEND_EMAILS_VERSION', '1.3' );
define( 'CX_WC_SEND_EMAILS_REQUIRED_WOOCOMMERCE_VERSION', 2.3 );
define( 'CX_WC_SEND_EMAILS_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'CX_WC_SEND_EMAILS_URI', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'CX_WC_SEND_EMAILS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // woocommerce-send-emails/cxsemls-send-emails.php

/**
 * Check if WooCommerce is active, and is required WooCommerce version.
 */
if ( ! CX_WC_Send_Emails::is_woocommerce_active() || version_compare( get_option( 'woocommerce_version' ), CX_WC_SEND_EMAILS_REQUIRED_WOOCOMMERCE_VERSION, '<' ) ){
	add_action( 'admin_notices', array( 'CX_WC_Send_Emails', 'woocommerce_inactive_notice' ) );
	return;
}

/**
 * Check if any conflicting plugins are active, then deactivate ours.
 */
if ( CX_WC_Send_Emails::is_conflicting_plugins_active() ) {
	add_action( 'admin_notices', array( 'CX_WC_Send_Emails', 'is_conflicting_plugins_active_notice' ) );
	return;
}
	
/**
 * Includes
 */
include_once( 'includes/functions.php' );

/**
 * Instantiate plugin.
 */
$cx_wc_send_emails = CX_WC_Send_Emails::get_instance();

/**
 * Main Class.
 */
class CX_WC_Send_Emails {
	
	private $id = 'woocommerce_send_emails';
	
	private static $instance;
	
	/**
	* Get Instance creates a singleton class that's cached to stop duplicate instances
	*/
	public static function get_instance() {
		if ( !self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}
	
	/**
	* Construct empty on purpose
	*/
	private function __construct() {}
	
	/**
	* Init behaves like, and replaces, construct
	*/
	public function init() {
		
		// Translations
		add_action( 'init', array( $this, 'load_translation' ) );
		
		// Enqueue Scripts/Styles - in head of admin page
		add_action( 'admin_enqueue_scripts', array( $this, 'head_scripts' ) );
		
		// Enqueue Scripts/Styles - in head of email template page
		add_action( 'render_template_head_scripts', array( $this, 'head_scripts' ), 102 );
		
		// Add menu item
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
		// Ajax send email
		add_action( 'wp_ajax_cxsemls_send_email', array( $this, 'send_email' ) );
		add_action( 'wp_ajax_nopriv_cxsemls_send_email', array( $this, 'nopriv_send_email' ) );
		
		// WooCommerce order page meta boxe
		add_action( 'add_meta_boxes', array( $this, 'order_page_meta_box' ), 35 );
		
		//Send Emails - Admin and Template pages only
		if ( isset( $_REQUEST["page"] ) && $_REQUEST["page"] == $this->id ) {
			
			// Remove all notifications
			remove_all_actions( 'admin_notices' );
						
			if ( ! isset( $_REQUEST["cxsemls_render_email"] ) ) {
				
			}
			else {
				
				//Send Emails - Template page only
				add_filter( 'wp_print_scripts', array( $this, 'deregister_all_scripts' ), 101 );
				add_action( 'wp_print_scripts', array( $this, 'head_scripts' ), 102 );
				add_action( 'admin_init', array( $this, 'render_template_page' ) );
			}
		}
		
		// Modify email headers.
		add_action( 'woocommerce_email_headers', array( $this, 'email_headers' ) );
		
		// Other simpler WooCommerce emails - Content.
		// add_filter( 'woocommerce_email_content_low_stock', array( $this, 'woocommerce_simple_email_content' ), 10, 2 );
		// add_filter( 'woocommerce_email_content_no_stock', array( $this, 'woocommerce_simple_email_content' ), 10, 2 );
		// add_filter( 'woocommerce_email_content_backorder', array( $this, 'woocommerce_simple_email_content' ), 10, 2 );
		// Other simpler WooCommerce emails - Headers.
		// add_filter( 'woocommerce_email_headers', array( $this, 'woocommerce_simple_email_headers' ), 10, 2 );
	}
	
	/**
	 * Localization
	 */
	
	public function load_translation() {
		load_plugin_textdomain( 'woocommerce-send-emails', false, dirname( plugin_basename( __FILE__ ) ) . '/localization/' );
	}
	
	/**
	 * Dergister all scripts & styles
	 *
	 * Deregister all scripts so the email template preview is
	 * css clean and free of other plugins js bugs
	 *
	 * @date	20-08-2014
	 * @since	1.0
	 */
	function deregister_all_scripts() {
		
		global $wp_scripts,  $wp_styles;
		
		// Dequeue All Scripts
		if (false != $wp_scripts->queue) {
			foreach($wp_scripts->queue as $script) {
				$wp_scripts->dequeue( $script );
				
				// if (isset($wp_scripts->registered[$script])) {
				// 	$wp_scripts->registered[$script]->deps = array();
				// }
			}
		}
		
		// Dequeue All Styles
		if (false != $wp_styles->queue) {
			foreach($wp_styles->queue as $script) {
				$wp_styles->dequeue( $script );
				
				// if (isset($wp_styles->registered[$script])) {
				// 	$wp_styles->registered[$script]->deps = array();
				// }
			}
		}
	}
	
	/**
	 * Enqueue CSS and Scripts
	 *
	 * @date	20-08-2014
	 * @since	1.0
	 */
	public function head_scripts() {
		
		global $woocommerce, $wp_scripts, $current_screen, $pagenow;
		
		// All Pages
		wp_register_style( 'woocommerce_admin', $woocommerce->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'woocommerce_admin' );
		
		wp_enqueue_script( 'woocommerce_admin' );
		
		// Send Emails - Admin page only
		if 	(
				( isset( $_REQUEST["page"]) && $_REQUEST["page"] == $this->id )
				||
				( isset( $_REQUEST["page"] ) && $_REQUEST["page"] == "wc-settings" )
				||
				( isset( $_REQUEST["cxsemls_render_email"] ) )
				||
				( isset( $current_screen->id ) && $current_screen->id == "shop_order" )
				||
				( 'plugins.php' == $pagenow )
			) {
			
			// For image uplaoder on settings page_link
			wp_enqueue_media();
			
			// Magnificent Popup
			wp_register_script( 'magnificent-popup', CX_WC_SEND_EMAILS_URI . '/assets/js/magnificent-popup/magnificent.js', array('jquery'), CX_WC_SEND_EMAILS_VERSION );
			wp_enqueue_script( 'magnificent-popup' );
			wp_register_style( 'magnificent-popup', CX_WC_SEND_EMAILS_URI . '/assets/js/magnificent-popup/magnificent.css', array(), CX_WC_SEND_EMAILS_VERSION, 'screen' );
			wp_enqueue_style( 'magnificent-popup' );
			
			// Send Emails Custom Scripts
			wp_register_style( 'cxsemls-admin', CX_WC_SEND_EMAILS_URI . '/assets/css/send-emails-back-end.css', array(), CX_WC_SEND_EMAILS_VERSION, 'screen' );
			wp_enqueue_style( 'cxsemls-admin' );
			wp_register_script( 'cxsemls-admin', CX_WC_SEND_EMAILS_URI . '/assets/js/send-emails-back-end.js', array( 'jquery', 'iris' ), CX_WC_SEND_EMAILS_VERSION );
			wp_enqueue_script( 'cxsemls-admin' );
			wp_localize_script( 'cxsemls-admin', 'woocommerce_send_emails', array(
				'home_url'                 => get_home_url(),
				'admin_url'                => admin_url(),
				'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
				'send_email_nonce'         => wp_create_nonce( 'send_email_nonce' ),
				'msg_error'                => __( 'Error', 'woocommerce-send-emails'),
				'msg_email_empty'          => __( 'Please enter an email address', 'woocommerce-send-emails'),
				'msg_invalid_email'        => __( 'The email address you provided is not vaild', 'woocommerce-send-emails'),
				'msg_email_sent'           => __( 'Email Sent!', 'woocommerce-send-emails'),
				'msg_email_sending_failed' => __( 'Email sending failed!', 'woocommerce-send-emails'),
				'msg_email_sending_busy'   => __( 'Sending Email', 'woocommerce-send-emails'),
			));
			
			add_action( 'in_admin_footer', array( $this, 'render_send_email_ui' ) );
			
			// Fontello.
			wp_enqueue_style(
				'cxsemls-icon-font',
				CX_WC_SEND_EMAILS_URI . '/assets/fontello/css/cxsemls-icon-font.css',
				array(),
				CX_WC_SEND_EMAILS_VERSION
			);
		}
		
		// Send Emails - Template page only
		if ( ( isset($_REQUEST["page"]) && $_REQUEST["page"] == $this->id ) && isset( $_REQUEST["cxsemls_render_email"] ) ) {
			
			// Load jQuery
			wp_enqueue_script( 'jquery' );
			
			// Load Dashicons
			wp_enqueue_style( 'dashicons' );
			
			// Send Emails Custom Scripts
			wp_register_style( 'cxsemls-admin', CX_WC_SEND_EMAILS_URI . '/assets/css/send-emails-back-end.css', array(), CX_WC_SEND_EMAILS_VERSION, 'screen' );
			wp_enqueue_style( 'cxsemls-admin' );
			
		}
	}
	
	/**
	 * Add a submenu item to the WooCommerce menu
	 *
	 * @date	20-08-2014
	 * @since	1.0
	 */
	public function admin_menu() {
		
		add_submenu_page(
			NULL,
			__( 'Send Emails', 'woocommerce-send-emails' ),
			__( 'Send Emails', 'woocommerce-send-emails' ),
			'manage_woocommerce',
			$this->id,
			array( $this, 'render_admin_page' )
		);
	}
	
	/*
	*  Ajax send email
	*
	*  @date	20-08-2014
	*  @since	1.0
	*/
	public function send_email() {
		global $order, $woocommerce;
		
		// Nonce check.
		check_ajax_referer( 'send_email_nonce', 'cxsemls_send_email_nonce' );
		
		$email_type    = $_REQUEST['cxsemls_email_type'];
		$email_order   = $_REQUEST['cxsemls_email_order'];
		$email_address = ( isset( $_REQUEST['cxsemls_email_addresses'] ) ? $_REQUEST['cxsemls_email_addresses'] : '' );
		
		if ( ! is_email( $email_address ) ) {
			
			// Return status.
			wp_send_json( array(
				'status' => 'incorrect-email-format',
			) );
		}
		
		// Handle button actions
		if ( ! empty( $email_type ) ) {

			// Load mailer
			$mailer = $woocommerce->mailer();
			$mails = $mailer->get_emails();
			
			// Ensure gateways are loaded in case they need to insert data into the emails
			$woocommerce->payment_gateways();
			$woocommerce->shipping();
			
			$email_type = wc_clean( $email_type );

			if ( ! empty( $mails ) ) {
				foreach ( $mails as $mail ) {
					if ( $mail->id == $email_type ) {
						
						// New method - filters the recipient address and used the respective mails own sending function to send.
						$mail->recipient = $email_address;
						$mail->trigger( $email_order );
					}
				}
			}
		}
		
		// Return status.
		wp_send_json( array(
			'status' => 'sent',
		) );
	}
	
	function nopriv_send_email() {
		_e('You must be logged in', 'woocommerce-send-emails');
		die();
	}
	
	/**
	 * WC order page meta box
	 */
	public function order_page_meta_box() {
		
		add_meta_box(
			'woocommerce-order-actions-new',
			__( 'Send Emails', 'woocommerce-send-emails' ),
			array($this, 'order_meta_box'),
			'shop_order',
			'side',
			'high'
		);
	}
	
	/**
	 * WC order page meta box
	 *
	 * @date	20-08-2014
	 * @since	1.0
	 *
	 * @param object $post The order post
	 */
	public function order_meta_box( $post ) {
		global $woocommerce, $theorder, $wpdb;

		if ( !is_object( $theorder ) )
			$theorder = new WC_Order( $post->ID );

		$order = $theorder;
		?>
		
		<div class="cxsemls_order_page_ui">
			
			<div class="cxsemls_actions_dropdown" title="<?php _e( 'Choose which email to preview or send.', 'woocommerce-send-emails' ); ?>" >
				
				<?php do_action( 'woocommerce_order_actions_start', $post->ID ); ?>
				
				<select name="cxsemls_order_action" id="cxsemls_order_action">
					<option value=""><?php _e( 'Emails', 'woocommerce-send-emails' ); ?></option>
					
					<?php
					// Load mailer
					if ( class_exists('WC') ) {
						$mailer = WC()->mailer();
						$mails = $mailer->get_emails();
						
						// Ensure gateways are loaded in case they need to insert data into the emails
						WC()->payment_gateways();
						WC()->shipping();
						
					}
					else{
						$mailer = $woocommerce->mailer();
						$mails = $mailer->get_emails();
						
						// Ensure gateways are loaded in case they need to insert data into the emails
						$woocommerce->payment_gateways();
						$woocommerce->shipping();
					}
										
					if ( ! empty( $mails ) ) {
						foreach ( $mails as $mail ) {
							
							// Skip - mails known to not work with this sending action.
							if ( 'customer_note' == $mail->id ) continue;
							?>
							<option value="send_email_<?php echo esc_attr( $mail->id ) ?>">
								<?php echo esc_html( $mail->title ) ?>
							</option>
							<?php
						}
					}
					?>
				</select>
				
			</div>
			<div class="cxsemls_actions_buttons">
				
				<!-- Buttons Row -->
				<a class="button" id="preview-email-button" title="<?php _e( "Preview the email selected above.", 'woocommerce-send-emails' ); ?>" target="_blank" ><?php _e( 'Preview Email', 'woocommerce-send-emails' ); ?></a>
				<a class="button" id="send-email" title="<?php _e( "Send the email selected above to this customer's billing address email. Will default to 'New Order' email if nothing is selected.", 'woocommerce-send-emails' ); ?>" target="_blank" ><?php _e( 'Send Email', 'woocommerce-send-emails' ); ?></a>
				<!-- /Buttons Row -->
				
			</div>
			
		</div>
		
		<?php
	}
	
	/**
	 * Render admin page.
	 *
	 * @date	20-08-2014
	 * @since	1.0
	 */
	public function render_admin_page() {
		
		require_once( 'pages/template-page.php');
	}
	
	/**
	 * Render template page.
	 *
	 * @date	20-08-2014
	 * @since	1.0
	 */
	public function render_template_page() {
		
		require_once( 'pages/template-page.php');
	}
	
	/**
	 * Force UTF-8 to email headers
	 *
	 * @date	09-02-2015
	 * @since	2.17
	 */
	function email_headers( $headers ) {
		$headers = str_replace( "\r\n", '; charset=UTF-8' . "\r\n" , $headers );
		return $headers;
	}
	
	/**
	 * Format the other simpler WooCommerce emails - Content.
	 */
	function woocommerce_simple_email_content( $message ) {
		
		ob_start();
		wc_get_template('emails/email-header.php' );
		echo $message;
		wc_get_template('emails/email-footer.php' );
		return ob_get_clean();
	}
	/**
	 * Format the other simpler WooCommerce emails - Headers.
	 */
	function woocommerce_simple_email_headers() {
		
		return "Content-Type: text/html; charset=UTF-8\r\n";
	}
	
	/**
	 *
	 */
	public function populate_mail_object( $order, &$mail ) {
		global $cxec_cache_email_message;
		
		// New method of gathering email HTML by pushing the data up into a global.
		add_action( 'woocommerce_mail_content', array( $this, 'cancel_email_send' ), 90 );
		
		// Force the email to seem enabled in-case it has been tuned off programmatically.
		$mail->enabled = 'yes';
		
		/**
		 * Get a User ID for the preview.
		 */
		
		// Get the Customer user from the order, or the current user ID if guest.
		if ( 0 === ( $user_id = (int) get_post_meta( cxsemls_order_get_id( $order ), '_customer_user', TRUE ) ) ) {
			$user_id = get_current_user_id();
		}
		$user = get_user_by( 'id', $user_id );
		
		/**
		 * Get a Product ID for the preview.
		 */
		
		// Get a product from the order. If it doesnt exist anymore then get the latest product.
		$items = $order->get_items();
		foreach ( $items as $item ) {
			$product_id = $item['product_id'];
			if ( NULL !== get_post( $product_id ) ) break;
			//$product_variation_id = $item['variation_id'];
		}
		
		if ( NULL === get_post( $product_id ) ){
			
			$products_array = get_posts( array(
				'posts_per_page'   => 1,
				'orderby'          => 'date',
				'post_type'        => 'product',
				'post_status'      => 'publish',
			) );
			
			if ( isset( $products_array[0]->ID ) ){
				$product_id = $products_array[0]->ID;
			}
		}
		
		/**
		 * Generate the required email for use with Sending or Previewing.
		 *
		 * All the email types in all the varying plugins require specific
		 * properties to be set before they generate the email for our
		 * preview, or send a test email.
		 */
		
		$compatabiltiy_warning = FALSE; // Default.
		
		switch ( $mail->id ) {
			
			/**
			 * WooCommerce (default transactional mails).
			 */
			
			case 'new_order':
			case 'cancelled_order':
			case 'customer_processing_order':
			case 'customer_completed_order':
			case 'customer_refunded_order':
			case 'customer_on_hold_order':
			case 'customer_invoice':
			case 'failed_order':
				
				$mail->object                  = $order;
				$mail->find['order-date']      = '{order_date}';
				$mail->find['order-number']    = '{order_number}';
				$mail->replace['order-date']   = date_i18n( wc_date_format(), strtotime( cxsemls_order_get_date_created( $mail->object ) ) );
				$mail->replace['order-number'] = $mail->object->get_order_number();
				break;
			
			case 'customer_new_account':
				
				$mail->object             = $user;
				$mail->user_pass          = '{user_pass}';
				$mail->user_login         = stripslashes( $mail->object->user_login );
				$mail->user_email         = stripslashes( $mail->object->user_email );
				$mail->recipient          = $mail->user_email;
				$mail->password_generated = TRUE;
				break;
			
			case 'customer_note':
				
				$mail->object                  = $order;
				$mail->customer_note           = 'Hello';
				$mail->find['order-date']      = '{order_date}';
				$mail->find['order-number']    = '{order_number}';
				$mail->replace['order-date']   = date_i18n( wc_date_format(), strtotime( cxsemls_order_get_date_created( $mail->object ) ) );
				$mail->replace['order-number'] = $mail->object->get_order_number();
				break;
			
			case 'customer_reset_password':
				
				$mail->object     = $user;
				$mail->user_login = $user->user_login;
				$mail->reset_key  = '{{reset-key}}';
				break;
			
			/**
			 * WooCommerce Wait-list Plugin (from WooCommerce).
			 */
			
			case 'woocommerce_waitlist_mailout':
				
				$mail->object    = get_product( $product_id );
				$mail->find[]    = '{product_title}';
				$mail->replace[] = $mail->object->get_title();
				break;
				
			/**
			 * WooCommerce Subscriptions Plugin (from WooCommerce).
			 */
			
			case 'new_renewal_order':
			case 'new_switch_order':
			case 'customer_processing_renewal_order':
			case 'customer_completed_renewal_order':
			case 'customer_completed_switch_order':
			case 'customer_renewal_invoice':
				
				$mail->object = $order;
				break;
				
			case 'cancelled_subscription':
				
				$mail->object = FALSE;
				$compatabiltiy_warning = TRUE;
				break;
			
			/**
			 * Everything else, including all default WC emails.
			 */
			
			default:
				
				$mail->object = $order;
				$compatabiltiy_warning = TRUE;
				break;
		}
		
		return $compatabiltiy_warning;
	}
	
	/**
	 * New method of previewing emails.
	 * Stores the email message up in a global, then return an
	 * empty string message which prevents the email sending.
	 */
	function cancel_email_send( $message ) {
		global $cxsemls_cache_email_message;
		$cxsemls_cache_email_message = $message;
		return $message;
	}
	
	
	
	/**
	 * Add Send Email form to Order Page.
	 */
	public function render_send_email_ui() {
		?>
		<div class="cxsemls-create-modal cxsemls-component-modal-content-hard-hide cxsemls-component-modal-content-hard-hide">
		
			<div class="cxsemls-create-modal-title">
				<?php _e( 'Send Email', 'woocommerce-send-emails' ); ?>
			</div>
			
			<div class="cxsemls-create-modal-content-inner">
	            
	            <div class="cxsemls-create-modal-row">
	                <label for="cxsemls_send_email_address">
	                    <?php _e( 'Send To Email Address', 'woocommerce-send-emails' ); ?>
	                </label>
	                <input type="text" name="cxsemls_send_email_address" id="cxsemls_send_email_address" value="" />
	                <p class="cxsemls-modal-description">
	                	<?php echo sprintf( __( 'If you\'d like to cutomize these emails then have a look at our plugin <a href="%s" target="_blank">Email Customizer for WooCommerce</a>', 'woocommerce-send-emails' ), esc_url( 'https://codecanyon.net/item/email-customizer-for-woocommerce/8654473?ref=cxThemes&utm_medium=plugin%20modal&utm_campaign=free%20plugin%20upsell&utm_source=send%20emails' ) ); ?>
	                </p>
	            </div>
	            
	            <div class="cxsemls-create-modal-row cxsemls-create-modal-buttons-row">
	                <button class="button cxsemls-send-email-form-cancel">
	                    <?php _e( 'Cancel', 'woocommerce-send-emails' ); ?>
	                </button>
	                <button class="button button-primary cxsemls-send-email-form-submit">
	                    <?php _e( 'Send Email', 'woocommerce-send-emails' ); ?>
	                </button>
	            </div>
	        
	        </div>
            
		</div>
		<?php
	}
	
	
	/**
	 * Check if any conflicting plugins are active, then deactivate ours.
	 *
	 * @since	2.36
	 */
	public static function is_conflicting_plugins_active() {
		
		global $cxsemls_plugins_found;
		
		$active_plugins = (array) get_option( 'active_plugins', array() );
		
		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		
		// Define the plugins to check for.
		$plugins_to_check = array(
			'woocommerce-email-customizer.php' => 'WooCoomerce Send Emails by WooThemes',
			'yith-woocommerce-email-templates' => 'YITH WooCommerce Email Templates',
		);
		
		$cxsemls_plugins_found = array();
		foreach ( $active_plugins as $active_plugin_key => $active_plugin_value ) {
			foreach ( $plugins_to_check as $plugins_to_check_key => $plugins_to_check_value ) {
				if ( FALSE !== strpos( $active_plugin_value, $plugins_to_check_key ) || FALSE !== strpos( $active_plugin_key, $plugins_to_check_key ) ) {
					// Collect the found plugin.
					$cxsemls_plugins_found[] = $plugins_to_check[$plugins_to_check_key];
				}
			}
		}
		
		return ! empty( $cxsemls_plugins_found );
	}
	
	/**
	 * Display Notifications on conflicting plugins active.
	 *
	 * @since	2.36
	 */
	public static function is_conflicting_plugins_active_notice() {
		
		global $cxsemls_plugins_found;
		
		if ( ! empty( $cxsemls_plugins_found ) ) :
			?>
			<div id="message" class="error">
				<p>
					<?php
					printf(
						__( '%sWooCommerce Send Emails is inactive due to conflicts%sOur plugin will conflict with the following plugins and cannot be used while they are active: %s', 'woocommerce-send-emails' ),
						'<strong>',
						'</strong><br>',
						'<em>' . implode( ', ', $cxsemls_plugins_found ) . '</em>'
					);
					?>
				</p>
			</div>
			<?php
		endif;
	}
	
	/**
	 * Is WooCommerce active.
	 */
	public static function is_woocommerce_active() {
		
		$active_plugins = (array) get_option( 'active_plugins', array() );
		
		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		
		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}
	
	/**
	 * Display Notifications on specific criteria.
	 *
	 * @since	2.14
	 */
	public static function woocommerce_inactive_notice() {
		if ( current_user_can( 'activate_plugins' ) ) :
			if ( !class_exists( 'WooCommerce' ) ) :
				?>
				<div id="message" class="error">
					<p>
						<?php
						printf(
							__( '%sWooCommerce Send Emails needs WooCommerce%s %sWooCommerce%s must be active for Send Emails to work. Please install & activate WooCommerce.', 'woocommerce-send-emails' ),
							'<strong>',
							'</strong><br>',
							'<a href="http://wordpress.org/extend/plugins/woocommerce/" target="_blank" >',
							'</a>'
						);
						?>
					</p>
				</div>
				<?php
			elseif ( version_compare( get_option( 'woocommerce_db_version' ), CX_WC_SEND_EMAILS_REQUIRED_WOOCOMMERCE_VERSION, '<' ) ) :
				?>
				<div id="message" class="error">
					<!--<p style="float: right; color: #9A9A9A; font-size: 13px; font-style: italic;">For more information <a href="http://cxthemes.com/plugins/update-notice.html" target="_blank" style="color: inheret;">click here</a></p>-->
					<p>
						<?php
						printf(
							__( '%sWooCommerce Send Emails is inactive%s This version of Send Emails requires WooCommerce %s or newer. For more information about our WooCommerce version support %sclick here%s.', 'woocommerce-send-emails' ),
							'<strong>',
							'</strong><br>',
							CX_WC_SEND_EMAILS_REQUIRED_WOOCOMMERCE_VERSION,
							'<a href="https://helpcx.zendesk.com/hc/en-us/articles/202241041/" target="_blank" style="color: inheret;" >',
							'</a>'
						);
						?>
					</p>
					<div style="clear:both;"></div>
				</div>
				<?php
			endif;
		endif;
	}

}
