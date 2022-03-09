(function($) {
	$( document ).ready( function() {
		
		// WooCommerce Order Admin Page
		// ----------------------------------------
		
		// From HTML string
		$('#preview-email-button').click(function(event) {
			
			var val_email_type;
			if ( $("select#cxsemls_order_action" ).val() != "") val_email_type = $( "select#cxsemls_order_action" ).val().replace("send_email_","");
			else val_email_type = "new_order";
			
			var val_email_order = $( "#post_ID" ).val();
			
			var new_src = "";
			new_src += woocommerce_send_emails.admin_url;
			new_src += "admin.php?";
			new_src += "page=woocommerce_send_emails";
			new_src += "&";
			new_src += "cxsemls_render_email=yes";
			new_src += "&";
			new_src += "cxsemls_email_type=" + val_email_type;
			new_src += "&";
			new_src += "cxsemls_email_order=" + val_email_order;
			new_src += "&";
			new_src += "cxsemls_in_popup=true";
			
			email_control_popup(new_src);

			return false;
		});
		
		function email_control_popup(src) {
			
			cxsemls_loading({ backgroundColor: "rgba(0,0,0,0)" });
			
			jQuery.magnificPopup.open({
				items: {
					src:	src,
					type:	"iframe"
				},
				//closeBtnInside: true,
				overflowY: false,
				closeOnBgClick:	true,
				closeMarkup: '<button title="%title%" class="mfp-close button-primary"><i class="mfp-close-icn">&times;</i></button>',
				mainClass: 'cxsemls-mfp',
			});
			
		}
		
		$('select#cxsemls_order_action').change(function() {
			
			if ( $(this).val().indexOf("send_email") != -1 ) {
				$('#actions').after( $('#preview-email-row') );
				//$('#preview-email-row').css({display:"block"});
				$('#actions .button.wc-reload').fadeOut(150);
				$('#preview-email-row').slideDown(150);
			}
			else {
				//$('#preview-email-row').css({display:"none"});
				$('#actions .button.wc-reload').fadeIn(150);
				$('#preview-email-row').slideUp(150);
			}
			
		});
		
		// Open 'Send Email' Modal.
		$('#send-email').click(function(event) {

			// Remove all form errors (to avoid duplicates).
			$('.cxsemls-create-customer-form-error').remove();
			
			// prefill the email input with the billing email address.
			var val_billing_email = $("#_billing_email").val();
			if ( ! $("#cxsemls_send_email_address").val() ) {
				$("#cxsemls_send_email_address").val( val_billing_email );
			}
			
			// Open Modal.
			open_modal(
				'.cxsemls-create-modal',
				{ position: 'center', close_button: true, close_click_outside: false }
			);

			return false;
		});
		
		// Cancel 'Send Email' Modal.
		$(".button.cxsemls-send-email-form-cancel").click(function() {
			close_modal();
			return false;
		});
		
		// 'Send Email' button.
		$('.cxsemls-send-email-form-submit.button').click(function(event) {
			
			// Remove all form errors (to avoid duplicates).
			$('.cxsemls-create-customer-form-error').remove();
			
			// Get email type.
			var val_email_type;
			if ( $("select#cxsemls_order_action" ).val() != "" )
				val_email_type = $( "select#cxsemls_order_action" ).val().replace("send_email_","");
			else
				val_email_type = "new_order";
			
			var val_email_type_name;
			if ( $("select#cxsemls_order_action" ).val() != "" )
				val_email_type_name = $( "select#cxsemls_order_action :selected" ).text().trim();
			else
				val_email_type_name = 'New Order';
			
			var val_email_order = $( "#post_ID" ).val();
			
			// Old Method.
			/*var email_prompt = prompt( "Send a '" + val_email_type_name + "' Email to:", val_billing_email );
			if (email_prompt != null)
				val_billing_email = email_prompt;
			else
				return; // Bail if no email address.*/
			
			// New Method.
			val_billing_email = $("#cxsemls_send_email_address").val();
			if ( ! val_billing_email ) {
				$el = $( '<div class="inline error cxsemls-create-customer-form-error"><p><strong>'+ woocommerce_send_emails.msg_error +'</strong>: '+ woocommerce_send_emails.msg_email_empty +'.</p></div>' );
				$el.insertBefore( $("#cxsemls_send_email_address") );
				return false;
			}
			
			// Close Modal.
			close_modal();
			
			// Display loading text.
			cxsemls_loading({ text: woocommerce_send_emails.msg_email_sending_busy });
			
			jQuery.ajax({
				type:     "post",
				dataType: "json",
				url:      woocommerce_send_emails.ajaxurl,
				data: {
					action                   : "cxsemls_send_email",
					cxsemls_email_type       : val_email_type,
					cxsemls_email_order      : val_email_order,
					cxsemls_email_addresses  : val_billing_email,
					cxsemls_send_email_nonce : woocommerce_send_emails.send_email_nonce,
				},
				success: function( data ) {
					
					if ( 'incorrect-email-format' == data.status ) {
						cxsemls_loading_end();
						cxsemls_notify( woocommerce_send_emails.msg_invalid_email, { id: "send-email" } );
						return false;
					}
					
					cxsemls_loading_end();
					cxsemls_notify( woocommerce_send_emails.msg_email_sent, { id: "send-email" } );
				},
				error: function(xhr, status, error) {
					cxsemls_loading_end();
					cxsemls_notify( woocommerce_send_emails.msg_email_sending_failed, { id: "send-email" } );
				}
			});

			return false;
			
		});


		function cxsemls_notify( content, options ) {
			
			// set up default options
			var defaults = {
				id:				false,
				display_time:	5000,
			};
			options = jQuery.extend({}, defaults, options);
			
			// Check the holder element is on the page.
			if ( ! $("#cxsemls-notification-holder").length ) {
				$("body").append( '<div id="cxsemls-notification-holder"></div>' );
			}
			
			// Check if  a notification with same id on the page already.
			var $existing_element = $(".cxsemls-notification-" + options.id );
			if ( $existing_element.length ) {
				
				// Fade out existing notification.
				$existing_element.addClass('cxsemls-notification-fade-out');
				
				// Remove existing notification.
				setTimeout( function() {
					$existing_element.remove();
				}, 1000 );
			}
			
			// Create the new notification element.
			var $new_element = $( '<div/>', {
				class : "cxsemls-notification cxsemls-notification-hidden cxsemls-notification-" + options.id,
				text  : content,
			});
			
			// Add new notification to page.
			$("#cxsemls-notification-holder").append( $new_element );
			
			// Reveal the new notification.
			$new_element.removeClass( 'cxsemls-notification-hidden' );
			
			// Fade-out the new notification.
			var element_timeout = setTimeout(function() {
				$new_element.addClass( 'cxsemls-notification-hidden' );
			}, options.display_time );
			
			// Remove new notification.
			setTimeout(function() {
				$new_element.remove();
			}, options.display_time + 1000 );
		}
		
		
		// Loading Testing
		if (false) {
			time_interval = 3000;
			setTimeout(function() { /* cxsemls_loading(); */ }, 0 * time_interval);
			setTimeout(function() { /* cxsemls_loading( { text: "Loadski!..." } ); */ }, 1 * time_interval);
			setTimeout(function() { /* cxsemls_loading_end(); */ }, 2 * time_interval);
			
			time_interval = 300;
			setTimeout(function() { /* cxsemls_notify("First thing done!", {id: "first-thing"}); */ }, 0 * time_interval);
			setTimeout(function() { /* cxsemls_notify("Second thing done!", {id: "second-thing", size: "large"}); */ }, 1 * time_interval);
			setTimeout(function() { /* cxsemls_notify("First thing done again!", {id: "first-thing"}); */ }, 2 * time_interval);
			setTimeout(function() { /* cxsemls_notify("Third thing done!", {id: "third-thing"}); */ }, 3 * time_interval);
			setTimeout(function() { /* cxsemls_notify("Fourth thing done!", {id: "fourth-thing", display_time:10000}); */ }, 4 * time_interval);
			setTimeout(function() { /* cxsemls_notify("Fifth thing done!", {id: "fifth-thing", size: "medium"} ); */ }, 5 * time_interval);
			setTimeout(function() { /* cxsemls_notify("Third thing done again!", {id: "third-thing"} ); */ }, 6 * time_interval);
			setTimeout(function() { /* cxsemls_notify("Sixth thing done!", {id: "sixth-thing"} ); */ }, 7 * time_interval);
		}
	});

	$( window ).load( function() {
		
		if ( $("#cxsemls-template").length ) {
			parent.cxsemls_resize_frames();
		}
		
	});
	
	
	
	/**
	 * RE-USABLE COMPONENTS.
	 */
		
	// Helper function to check if we are in responsive/mobile.
	function is_mobile() {
		return ( $( window ).width() < 610 );
	}
	
	/**
	 * Modal Popups.
	 */
	
	function init_modal( $close_button ) {
		
		// Add the required elements if they not in the page yet.
		if ( ! $('.cxsemls-component-modal-popup').length ) {
			
			// Add the required elements to the dom.
			$('body').append( '<div class="cxsemls-component-modal-temp cxsemls-component-modal-hard-hide"></div>' );
			$('body').append( '<div class="cxsemls-component-modal-cover cxsemls-component-modal-hard-hide"></div>' );
			
			$popup_html = '';
			$popup_html += '<div class="cxsemls-component-modal-wrap cxsemls-component-modal-popup cxsemls-component-modal-hard-hide">';
			$popup_html += '	<div class="cxsemls-component-modal-container">';
			$popup_html += '		<div class="cxsemls-component-modal-content">';
			$popup_html += '		</div>';
			$popup_html += '	</div>';
			$popup_html += '</div>';
			$('body').append( $popup_html );
			
			// Enable the close_click_outside
			$('html').click(function(event) {
				if (
						0 === $('.cxsemls-component-modal-popup.cxsemls-component-modal-hard-hide').length &&
						0 !== $('.cxsemls-close-click-outside').length &&
						0 === $(event.target).parents('.cxsemls-component-modal-content').length
					) {
					close_modal();
					return false;
				}
			});
		}
	}
	
	function open_modal( $selector, $settings ) {
		
		// Set defaults
		$defaults = {
			position            : 'center',
			cover               : true,
			close_button        : true,
			close_click_outside : true,
		};
		$settings = $.extend( true, $defaults, $settings );
		
		// Init modal - incase this is first run.
		init_modal( $settings.close_button );
		
		// Move any elements that may already be in the modal out, to the temp holder, as well as the close cross.
		$('.cxsemls-component-modal-content').find('.cxsemls-cross').remove();
		$('.cxsemls-component-modal-temp').append( $('.cxsemls-component-modal-content').children() );
		
		// Get content to load in modal.
		$content = $( $selector );
		
		// If content to load doesn't exist then rather close the whole modal and bail.
		if ( ! $content.length ) {
			close_modal();
			console.log( 'Content to load into modal does not exists.' );
			return;
		}
		
		// Enable whether to close when clicked outside the modal.
		if ( $settings.close_click_outside )
			$('.cxsemls-component-modal-popup' ).addClass('cxsemls-close-click-outside');
		else
			$('.cxsemls-component-modal-popup' ).removeClass('cxsemls-close-click-outside');
		
		// Show the close button, or remove it if not.
		if ( $settings.close_button )
			$('.cxsemls-component-modal-content').append('<span class="cxsemls-cross cxsemls-top-bar-cross cxsemls-icon-cancel"></span>');
		
		// Add the intended content into the modal.
		$('.cxsemls-component-modal-content').prepend( $content );
		
		// Remove the class that's hiding the modal.
		$content.removeClass( 'cxsemls-component-modal-content-hard-hide' );
		
		// Apply positioning.
		// $( '.cxsemls-component-modal-popup' )
		// 	.removeClass( 'cxsemls-modal-position-center cxsemls-modal-position-top-right cxsemls-modal-position-top-center' )
		// 	.addClass( 'cxsemls-modal-position-' + $settings.position );
		
		// Move to top of page if Mobile.
		// if ( is_mobile() ) {
		// 	$('.cxsemls-component-modal-popup').css({ top: $(document).scrollTop() + 80 });
		// 	console.log( $(document).scrollTop() );
		// }
		
		// Control the overflow of long page content.
		$('html').css({ 'margin-right': '17px', 'overflow': 'hidden' });
		
		// Set a tiny defer timeout so that CSS fade-ins happen correctly.
		setTimeout(function() {
			
			// Move elements into the viewport by removing hard-hide, then fade in the elements.
			$('.cxsemls-component-modal-popup').removeClass( 'cxsemls-component-modal-hard-hide' );
			$('.cxsemls-component-modal-popup').addClass( 'cxsemls-modal-play-in' );
		}, 1 );
		
		// Optionally show the back cover. (not when in mobile)
		if ( $settings.cover ) {
			$('.cxsemls-component-modal-cover').removeClass( 'cxsemls-component-modal-hard-hide' );
			$('.cxsemls-component-modal-cover').addClass( 'cxsemls-modal-play-in' );
		}
		else {
			// If not showing then make sure to fade it out.
			$('.cxsemls-component-modal-cover').removeClass( 'cxsemls-modal-play-in' );
			setTimeout(function() {
				$('.cxsemls-component-modal-cover').addClass( 'cxsemls-component-modal-hard-hide' );
			}, 200 );
		}
	}
	function close_modal() {
		
		// Close the select 2 lip when clicking outside the modal to close.
		$('#cxsemls-select2-user-search').select2('close');
		
		// Fade out the elements.
		$('.cxsemls-component-modal-cover, .cxsemls-component-modal-popup').removeClass( 'cxsemls-modal-play-in' );
		
		// Move elements out the viewport by adding hard-hide.
		setTimeout(function() {
			$('.cxsemls-component-modal-cover, .cxsemls-component-modal-popup').addClass( 'cxsemls-component-modal-hard-hide' );
			
			// Remove specific positioning.
			$('.cxsemls-component-modal-popup')
				.removeClass( 'cxsemls-modal-position-center cxsemls-modal-position-top-right cxsemls-modal-position-top-center' )
				.css({ top : '' });
			
			// Control the overflow of long page content.
			$('html').css({ 'margin-right': '', 'overflow': '' });
			
		}, 200 );
	}
	function resize_modal( $to_height ) {
		
		// Init modal - incase this is first run.
		init_modal();
		
		// Cache elements.
		$modal_popup = $('.cxsemls-component-modal-popup');
		
		// Get the intended heights.
		var $to_height = ( $to_height ) ? $to_height : $modal_popup.outerHeight();
		var $margin_top = ( $to_height / 2 );
		
		// Temporarily enable margin-top transition, do the height-ing/margin-ing, then remove the transtion.
		$modal_popup.css({ height: $to_height, marginTop: -$margin_top, transitionDelay: '0s', transition: 'margin .3s' });
		setTimeout( function(){
			$modal_popup.css({ height: '', transitionDelay: '', transition: '' });
		}, 1000 );
	}

	// Handle close button.
	$( document ).on( 'click', '.cxsemls-cross', function() {
		close_modal();
		return false;
	});
	
	
})( jQuery );


function cxsemls_resize_frames() {
	
	// End the Loading Spinner
	parent.cxsemls_loading_end();

	// Show the Popup
	jQuery( parent.document ).find('.mfp-content').addClass('mfp-show');
}


function cxsemls_loading(options) {
			
	// set up default options
	var defaults = {
		id:      false,
		text: "Loading...",
		backgroundColor: "rgba(0,0,0,.3)"
		
	};
	options = jQuery.extend({}, defaults, options);
	
	if ( !jQuery(".cx-loading-holder").length ) {
		jQuery("body").append('<div class="cx-loading-holder" style="display: none; background-color:' + options.backgroundColor + '; "><div class="cx-loading-inner-holder"><div class="cx-loading-graphic"></div><div class="cx-loading-text"></div></div></div>' );
	}
	
	jQuery(".cx-loading-text").append( options.content );
	jQuery(".cx-loading-holder").fadeIn(300);
}


function cxsemls_loading_end() {
	
	jQuery(".cx-loading-holder").fadeOut(300, function() {
		jQuery(this).remove();
	});
}
