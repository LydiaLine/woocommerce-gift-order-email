<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * A custom Gift Order WooCommerce Email class
 *
 * @since 0.1
 * @extends \WC_Email
 */
class WC_Gift_Order_Email extends WC_Email {


	/**
	 * Set email defaults
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// set ID, this simply needs to be a unique name
		$this->id = 'wc_gift_order';

		// this is the title in WooCommerce Email settings
		$this->title = 'Gift Order';

		// this is the description in WooCommerce email settings
		$this->description = 'Gift Order Notification emails are sent when a customer places an order that has a gift recipient.';

		// these are the default heading and subject lines that can be overridden using the settings
		$this->heading = 'Gift Order';
		$this->subject = 'Gift Order';

		// these define the locations of the templates that this email should use, we'll just use the new order template since this email is similar
		$this->template_html  = 'emails/admin-new-order.php';
		$this->template_plain = 'emails/plain/admin-new-order.php';

		// Trigger on new paid orders
		add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_failed_to_processing_notification',  array( $this, 'trigger' ) );

		// Call parent constructor to load any other defaults not explicity defined here
		parent::__construct();
	}
	
	/**
	Validate an email address.
	Provide email address (raw input)
	Returns true if the email address has the email 
	address format and the domain exists.
	Function taken from the following website:
	https://www.linuxjournal.com/article/9585
	*/
	public function validEmail($email) {
	   $isValid = true;
	   $atIndex = strrpos($email, "@");
	   if (is_bool($atIndex) && !$atIndex) {
	      $isValid = false;
	   }
	   else {
	      $domain = substr($email, $atIndex+1);
	      $local = substr($email, 0, $atIndex);
	      $localLen = strlen($local);
	      $domainLen = strlen($domain);
	      if ($localLen < 1 || $localLen > 64) {
		 // local part length exceeded
		 $isValid = false;
	      } else if ($domainLen < 1 || $domainLen > 255) {
		 // domain part length exceeded
		 $isValid = false;
	      } else if ($local[0] == '.' || $local[$localLen-1] == '.') {
		 // local part starts or ends with '.'
		 $isValid = false;
	      } else if (preg_match('/\\.\\./', $local)) {
		 // local part has two consecutive dots
		 $isValid = false;
	      } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
		 // character not valid in domain part
		 $isValid = false;
	      } else if (preg_match('/\\.\\./', $domain)) {
		 // domain part has two consecutive dots
		 $isValid = false;
	      } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
			 str_replace("\\\\","",$local))) {
		 // character not valid in local part unless 
		 // local part is quoted
		 if (!preg_match('/^"(\\\\"|[^"])+"$/',
		     str_replace("\\\\","",$local))) {
		    $isValid = false;
		 }
	      }
// Not including DNS check currently
// 	      if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) {
// 		 // domain not found in DNS
// 		 $isValid = false;
// 	      }
	   }
	   return $isValid;
	}

	/**
	 * Determine if the email should actually be sent and setup email merge variables
	 *
	 * @since 0.1
	 * @param int $order_id
	 */
	public function trigger( $order_id ) {

		// bail if no order ID is present
		if ( ! $order_id )
			return;

		// setup order object
		$this->object = new WC_Order( $order_id );

		// bail if shipping method is not expedited
		//if ( ! in_array( $this->object->get_shipping_method(), array( 'Three Day Shipping', 'Next Day Shipping' ) ) )
		//	return;

		// get custom email fields
		$email = get_post_meta( $order_id, 'shippingemail_', true );
		
		// bail if recipient email is not found or valid
		if ( empty($email) ) {
			return;
		} else {
			if ( !(validEmail($email)) ) {
				return;
			}
		}

		// replace variables in the subject/headings
		$this->find[] = '{order_date}';
		$this->replace[] = date_i18n( woocommerce_date_format(), strtotime( $this->object->order_date ) );

		$this->find[] = '{order_number}';
		$this->replace[] = $this->object->get_order_number();

		if ( ! $this->is_enabled() )
			return;

		// woohoo, send the email!
		$this->send( $email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}


	/**
	 * get_content_html function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		woocommerce_get_template( $this->template_html, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading()
		) );
		return ob_get_clean();
	}


	/**
	 * get_content_plain function.
	 *
	 * @since 0.1
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		woocommerce_get_template( $this->template_plain, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading()
		) );
		return ob_get_clean();
	}


	/**
	 * Initialize Settings Form Fields
	 *
	 * @since 2.0
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable this email notification',
				'default' => 'yes'
			),
			'subject'    => array(
				'title'       => 'Subject',
				'type'        => 'text',
				'description' => sprintf( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', $this->subject ),
				'placeholder' => '',
				'default'     => ''
			),
			'heading'    => array(
				'title'       => 'Email Heading',
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.' ), $this->heading ),
				'placeholder' => '',
				'default'     => ''
			),
			'email_type' => array(
				'title'       => 'Email type',
				'type'        => 'select',
				'description' => 'Choose which format of email to send.',
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'	    => __( 'Plain text', 'woocommerce' ),
					'html' 	    => __( 'HTML', 'woocommerce' ),
					'multipart' => __( 'Multipart', 'woocommerce' ),
				)
			)
		);
	}


} // end \WC_Gift_Order_Email class
