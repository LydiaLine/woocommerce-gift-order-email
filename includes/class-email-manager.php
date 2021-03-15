<?php
/**
 * Email Manager.
 *
 * @package     woocommerce-gift-order-email
 * @class       Gift-Email-Manager
 */

/**
 * Handles Emails
 */
class Gift_Email_Manager {

    /**
     * Constructor.
     */
    public function __construct() {

        add_filter( 'woocommerce_email_classes', array( &$this, 'gift_init_emails' ) );

        // Email Actions.
        $email_actions = array(
            wc_gift_order,
        );

        foreach ( $email_actions as $action ) {
            add_action( $action, array( 'WC_Emails', 'send_transactional_email' ), 10, 10 );
        }
        
        add_filter( 'woocommerce_template_directory', array( $this, 'gift_template_directory' ), 10, 2 );
    }

    /**
     * Include Email class files.
     *
     * @param array $emails - List of email class files.
     * @return array $emails - List of email class files.
     * @since 1.0
     */
    public function gift_init_emails( $emails ) {

        if ( ! isset( $emails['WC_Gift_Order_Email'] ) ) {
            $emails['WC_Gift_Order_Email'] = include_once plugin_dir_path( __FILE__ ) . 'class-wc-gift-order-email.php';
        }
        return $emails;
    }

    /**
     * Return the Location of the template.
     *
     * @param str $directory - Directory.
     * @param str $template - Template Name.
     * @return str $directory - Directory.
     * @since 1.0
     */
    public function gift_template_directory( $directory, $template ) {
        if ( false !== strpos( $template, 'gift' ) ) {
            return 'gift-order';
        }

        return $directory;
    }

} // end of class
return new Gift_Email_Manager();
