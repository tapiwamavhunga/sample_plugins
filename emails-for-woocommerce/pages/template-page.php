<?php
if ( ! current_user_can( 'manage_woocommerce' ) )
	wp_die( __( 'Cheatin&#8217; uh?', 'woocommerce-send-emails' ) );

global $wp_scripts, $woocommerce, $wpdb, $current_user, $order, $cx_wc_send_emails;
?>
<html>
<head>
	<meta http-equiv="Content-Type" content="<?php echo 'Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'); ?>" />
	<title>
		Prevew Email Template
	</title>

	<?php
	do_action( 'cxsemls_render_template_head_scripts' );
	
	print_head_scripts(); //This is the main one
	print_admin_styles();
	?>
</head>
<body id="cxsemls-template" class="cxsemls-template" >
	
	<?php
	$mails = $woocommerce->mailer()->get_emails();
	
	// Ensure gateways are loaded in case they need to insert data into the emails
	$woocommerce->payment_gateways();
	$woocommerce->shipping();
	
	/* Get Email to Show */
	if ( isset( $_REQUEST['cxsemls_email_type'] ) && $_REQUEST['cxsemls_email_type'] == sanitize_text_field( $_REQUEST['cxsemls_email_type'] ) ) {
		$email_type = $_REQUEST['cxsemls_email_type'];
	}
	else {
		$email_type = current( $mails )->id;
	}

	/* Get Order to Show */
	if ( isset( $_REQUEST['cxsemls_email_order'] ) ) {
		
		$order_id_to_show = $_REQUEST['cxsemls_email_order'];
	}
	else{
		
		//Get the most recent order.
		$order_collection = new WP_Query(array(
			'post_type'			=> 'shop_order',
			'post_status'		=> array_keys( wc_get_order_statuses() ),
			'posts_per_page'	=> 1,
		));
		$order_collection = $order_collection->posts;
		$latest_order = current($order_collection)->ID;
		$order_id_to_show = $latest_order;
	}
	
	if ( ! get_post( $order_id_to_show ) ) :
		
		/**
		 * Display an error message if there isn't an order yet.
		 */
		
		?>
		<div class="email-template-preview pe-in-admin-page">
			<div class="main-content">
				
				<!-- NO ORDER WARNING -->
				
				<div class="compatability-warning-text">
					<span class="dashicons dashicons-welcome-comments"></span>
					<!-- <h6><?php _e( "You'll need at least one order to use Send Emails properly", 'woocommerce-send-emails' ) ?></h6> -->
					<h6><?php _e( "You'll need at least one order to preview all the email types correctly", 'woocommerce-send-emails' ) ?></h6>
					<p>
						<?php _e( "Simply follow your store's checkout process to create at least one order, then return here to preview all the possible email types.", 'woocommerce-send-emails' ) ?>
					</p>
				</div>
				
				<!-- / NO ORDER WARNING -->
			
			</div>
		</div>
		<?php
		
	else :
		
		/**
		 * Display the chosen email.
		 */
		
		// prep the order.
		$order = new WC_Order( $order_id_to_show );
		
		if ( ! empty( $mails ) ) {
			foreach ( $mails as $mail ) {
				
				if ( $mail->id == $email_type ) {
					
					// Important Step: populates the $mail object with the necessary properties for it to Preview (or Send a test).
					// It also returns a BOOLEAN for whether we have checked this email types preview with our plugin.
					$compat_warning = $cx_wc_send_emails->populate_mail_object( $order, $mail );
					
					// Info Meta Swicth on /off
					$header = ( get_user_meta( $current_user->ID, 'header_info_userspecifc', true) ) ? get_user_meta( $current_user->ID, 'header_info_userspecifc', true ) : 'off' ;
					?>
					
					<div class="email-template-preview pe-in-admin-page">
						<div class="main-content">
						
							<?php if ( $compat_warning && ( $mail->id !== $_REQUEST['cxsemls_approve_preview'] ) ) : ?>
								
								<!-- COMPAT WARNING -->
								
								<div class="compatability-warning">
									<div class="compatability-warning-text">
										<span class="dashicons dashicons-welcome-comments"></span>
										<h6><?php _e( "We've not seen this email type from this third party plugin before", 'woocommerce-send-emails' ) ?></h6>
										<p>
											<?php _e( "Don't worry, the email will send fine, you just can't preview it. Customizing it - here are your options. Option 1: choose to show one of the other known emails and customize it (colors, sizes, etc) - the styling will be inherited if they have included the header and footer in the normal way. Option 2: choose to dismiss this message and see if it just works. If you see a blank screen then use option 1.", 'woocommerce-send-emails' ) ?>
											<a href="#" id="cxsemls_approve_preview_button" data-approve-preview="<?php echo esc_attr( $mail->id ) ?>"><?php _e( 'Dismiss', 'woocommerce-send-emails' ); ?></a>
										</p>
									</div>
								</div>
								
								<!-- / COMPAT WARNING -->
								
							<?php else: ?>
								
								<!-- EMAIL TEMPLATE -->
								
								<?php
								// Get the email contents. using @ to block ugly error messages showing in the preview.
								// The following mimics what WooCommerce does in it's `->send` method of WC_Email.
								@ $email_message = $mail->get_content();
								$email_message   = $mail->style_inline( $email_message );
								$email_message   = apply_filters( 'woocommerce_mail_content', $email_message );
								
								// Convert line breaks to <br>'s if the mail is type 'plain'.
								if ( 'plain' === $mail->email_type )
									$email_message = '<div style="padding: 35px 40px; background-color: white;">' . str_replace( "\n", '<br/>', $email_message ) . '</div>';
								
								// Display the email.
								echo $email_message;
								?>
								
								<!-- / EMAIL TEMPLATE -->
								
							<?php endif; ?>
							
						</div>
					</div>
					
					<?php
				}
			}
		}
	
	endif;
	?>
	
</body>
</html>

<?php exit; ?>