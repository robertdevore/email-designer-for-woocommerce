<?php
/**
 * Custom Email Template Loader
 *
 * This template loads the content from the selected Email Template CPT.
 *
 * Available Variables:
 * - $email_heading: The heading of the email.
 * - $email: The WC_Email object.
 */

// Debugging: Confirm template loading
echo '<!-- Custom Email Template Loaded -->';

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Get the selected custom template for this email.
 */
$saved_templates = get_option( 'email_designer_templates', [] );
$email_id = isset( $email->id ) ? $email->id : '';
$template_id = isset( $saved_templates[ $email_id ] ) ? absint( $saved_templates[ $email_id ] ) : 0;

if ( $template_id ) {
    $template_post = get_post( $template_id );
    if ( $template_post && 'email_template' === $template_post->post_type ) {
        // Apply 'the_content' filters to allow shortcodes and other content modifications.
        $content = apply_filters( 'the_content', $template_post->post_content );

        // Output the header
        do_action( 'woocommerce_email_header', $email_heading, $email );

        // Output the custom content
        echo $content;

        // Output the footer
        do_action( 'woocommerce_email_footer', $email );

        // Prevent further execution to avoid default content being appended
        return;
    }
}

// Fallback: If no custom template is found, load the default content
do_action( 'woocommerce_email_header', $email_heading, $email );

// Optionally, include default email content or leave it minimal
echo wp_kses_post( $email_heading );

// Output the footer
do_action( 'woocommerce_email_footer', $email );
?>
