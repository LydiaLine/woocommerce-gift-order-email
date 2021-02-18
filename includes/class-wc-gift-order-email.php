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
		add_action( 'woocommerce_order_status_on-hold_to_processing_notification',  array( $this, 'trigger' ) );

		// Call parent constructor to load any other defaults not explicity defined here
		parent::__construct();
	}
	
	/**
	 * Adds the contact to Hubspot for marketing purposes
	 */
	function create_contact( $order_id, $key ) {
		/* https://packagist.org/packages/hubspot/api-client */
		
		// Gets fields from order form
		$email = get_post_meta( $order_id, $field, true );
		$first = get_post_meta( $order_id, '_shipping_first_name', true );
		$last = get_post_meta( $order_id, '_shipping_last_name', true );
		$address = get_post_meta( $order_id, '_shipping_address_1', true );
		$city = get_post_meta( $order_id, '_shipping_city', true );
		$state = get_post_meta( $order_id, '_shipping_state', true );
		$zip = get_post_meta( $order_id, '_shipping_postcode', true );
		
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api.hubapi.com/crm/v3/objects/contacts?hapikey=$key",
  			CURLOPT_RETURNTRANSFER => true,
  			CURLOPT_ENCODING => "",
  			CURLOPT_MAXREDIRS => 10,
  			CURLOPT_TIMEOUT => 30,
  			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  			CURLOPT_CUSTOMREQUEST => "POST",
 			CURLOPT_POSTFIELDS => "{\"properties\":{\"email\":\"$email\",\"firstname\":\"$first\",\"lastname\":\"$last\",\"address\":\"$address\",\"city\":\"$city\",\"state\":\"$state\",\"zip\":\"$zip\"}}",
  			CURLOPT_HTTPHEADER => array(
	    			"accept: application/json",
	      			"content-type: application/json"
	      		),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			//echo "cURL Error #:" . $err;
			return false;
		} else {
			//echo $response;
			return true;
		}		
	}


	/**
	 * Determine if the email should actually be sent and setup email merge variables
	
	 * @since 0.1
	 * @param int $order_id
	 */
	public function trigger( $order_id ) {

		// bail if no order ID is present
		if ( ! $order_id )
			return;

		// setup order object
		$this->object = new WC_Order( $order_id );

		// get custom email fields
		$field = $this->object->get_option('custom_field');
		if (! $field) {
			$field = 'shippingemail_';
		}
		$email = get_post_meta( $order_id, $field, true );
		
		// bail if recipient email is not found or valid
		if ( empty($email) ) {
			return;
		} else {
			$email = filter_var($email, FILTER_SANITIZE_EMAIL);
			if (!(filter_var($email, FILTER_VALIDATE_EMAIL))) {
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

		// Gets API from option field
		$key = $this->object->get_option('key');
		// Add contact to HubSpot
		create_contact( $order_id, $key );
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
			'custom_field'  => array(
				'title'       => 'Custom Field Name',
				'type'        => 'text',
				'description' => sprintf( 'Enter the custom field attribute name for the email. Defaults to <code>%s</code>.', 'shippingemail_'),
				'placeholder' => '',
				'default'     => ''
			),
			'key'  => array(
				'title'       => 'HubSpot API Key',
				'type'        => 'text',
				'description' => sprintf( 'Enter the HubSpot API key for the account you want the contact to be added to.'),
				'placeholder' => '',
				'default'     => ''
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
