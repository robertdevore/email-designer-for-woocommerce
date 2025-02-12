<?php

/**
 * The plugin bootstrap file.
 *
 * @link              https://robertdevore.com
 * @since             1.0.0
 * @package           EmailDesignerWooCommerce
 *
 * @wordpress-plugin
 *
 * Plugin Name: Email Designer for WooCommerce®
 * Description: Design custom email templates for WooCommerce® using the core WordPress® editor.
 * Plugin URI:  https://robertdevore.com/project/email-designer-for-woocommerce/
 * Version:     1.0.0
 * Author:      Robert DeVore
 * Author URI:  https://robertdevore.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: email-designer-for-woocommerce
 * Domain Path: /languages
 * Update URI:  https://github.com/robertdevore/email-designer-for-woocommerce/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/robertdevore/email-designer-for-woocommerce/',
	__FILE__,
	'email-designer-for-woocommerce'
);

// Set the branch that contains the stable release.
$myUpdateChecker->setBranch( 'main' );

// Check if Composer's autoloader is already registered globally.
if ( ! class_exists( 'RobertDevore\WPComCheck\WPComPluginHandler' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use RobertDevore\WPComCheck\WPComPluginHandler;

new WPComPluginHandler( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' );

/**
 * Load plugin text domain for localization.
 *
 * @since  1.0.0
 * @return void
 */
function edwc_load_textdomain() {
    load_plugin_textdomain( 'email-designer-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'edwc_load_textdomain' );

/**
 * Class EmailDesignerWooCommerce
 *
 * Integrates custom email templates with WooCommerce emails.
 *
 * This class registers a custom post type for storing email templates and adds
 * a settings page for managing template assignments. It overrides default
 * WooCommerce email templates with user-defined custom templates and injects
 * dynamic content into emails via shortcodes and content filters.
 *
 * The main features include:
 * - Registering a custom post type for email templates.
 * - Adding and managing plugin settings and template assignments.
 * - Overriding WooCommerce email templates with custom designs.
 * - Providing shortcodes for dynamic email content (e.g., customer name, order total,
 *   and order details).
 * - Initializing custom content filters during the WooCommerce email sending process.
 *
 * @package EmailDesignerWooCommerce
 * @since   1.0.0
 */
class EmailDesignerWooCommerce {

    public function __construct() {
        add_action( 'init', [ $this, 'register_email_template_post_type' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );

        // Hook to override WooCommerce email templates.
        add_filter( 'woocommerce_locate_template', [ $this, 'override_email_templates' ], 100, 3 );

        add_shortcode( 'customer_name', [ $this, 'shortcode_customer_name' ] );
        add_shortcode( 'order_total', [ $this, 'shortcode_order_total' ] );
        add_shortcode( 'order_details', [ $this, 'shortcode_order_details' ] );

        // Initialize filters for custom email content.
        add_action( 'woocommerce_before_send_email', [ $this, 'init_custom_email_content_filters' ], 10, 2 );
    }

    /**
     * Register a custom post type for email templates.
     * 
     * @since  1.0.0
     * @return void
     */
    public function register_email_template_post_type() {
        $labels = [
            'name'                  => __( 'Email Templates', 'email-designer-for-woocommerce' ),
            'singular_name'         => __( 'Email Template', 'email-designer-for-woocommerce' ),
            'menu_name'             => __( 'Email Designer', 'email-designer-for-woocommerce' ),
            'name_admin_bar'        => __( 'Email Template', 'email-designer-for-woocommerce' ),
            'add_new'               => __( 'Add New', 'email-designer-for-woocommerce' ),
            'add_new_item'          => __( 'Add New Template', 'email-designer-for-woocommerce' ),
            'new_item'              => __( 'New Email Template', 'email-designer-for-woocommerce' ),
            'edit_item'             => __( 'Edit Email Template', 'email-designer-for-woocommerce' ),
            'view_item'             => __( 'View Email Template', 'email-designer-for-woocommerce' ),
            'all_items'             => __( 'All Email Templates', 'email-designer-for-woocommerce' ),
            'search_items'          => __( 'Search Email Templates', 'email-designer-for-woocommerce' ),
            'parent_item_colon'     => __( 'Parent Email Templates:', 'email-designer-for-woocommerce' ),
            'not_found'             => __( 'No email templates found.', 'email-designer-for-woocommerce' ),
            'not_found_in_trash'    => __( 'No email templates found in Trash.', 'email-designer-for-woocommerce' ),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_icon'             => 'dashicons-email',
            'supports'              => [ 'title', 'editor' ],
            'show_in_rest'          => true,
            'capability_type'       => 'post',
        ];

        register_post_type( 'email_template', $args );
    }    

    /**
     * Register plugin settings.
     * 
     * @since  1.0.0
     * @return void
     */
    public function register_settings() {
        // General section.
        add_settings_section(
            'email_designer_general_section',
            __( 'General Settings', 'email-designer-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'General configuration options for Email Designer.', 'email-designer-for-woocommerce' ) . '</p>';
            },
            'email_designer_general_settings'
        );

        // Custom CSS field.
        add_settings_field(
            'email_designer_custom_css',
            __( 'Custom Email CSS', 'email-designer-for-woocommerce' ),
            function () {
                $css = get_option( 'email_designer_custom_css', '' );
                echo '<textarea name="email_designer_custom_css" style="width: 100%; height: 200px;">' . esc_textarea( $css ) . '</textarea>';
            },
            'email_designer_general_settings',
            'email_designer_general_section'
        );

        // Register general settings.
        register_setting(
            'email_designer_general_settings',
            'email_designer_custom_css',
            [ 'sanitize_callback' => 'wp_strip_all_tags' ]
        );

        // Template assignment settings.
        add_settings_section(
            'email_designer_template_section',
            __( 'Template Assignments', 'email-designer-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Assign custom templates to WooCommerce emails. Each email type can be designed differently.', 'email-designer-for-woocommerce' ) . '</p>';
            },
            'email_designer_templates'
        );

        register_setting(
            'email_designer_templates',
            'email_designer_templates',
            [ 'sanitize_callback' => [ $this, 'sanitize_template_settings' ] ]
        );
    }

    /**
     * Sanitize the template settings.
     * 
     * @since  1.0.0
     * @return array
     */
    public function sanitize_template_settings( $input ) {
        $sanitized = [];
        foreach ( $input as $email_id => $template_id ) {
            $sanitized[ sanitize_text_field( $email_id ) ] = absint( $template_id );
        }
        return $sanitized;
    }

    /**
     * Override WooCommerce email templates with custom templates.
     * 
     * @since  1.0.0
     * @return mixed
     */
    public function override_email_templates( $template, $template_name, $template_path ) {
        // Only handle main WooCommerce email templates.
        if ( strpos( $template_name, 'emails/' ) !== 0 ) {
            return $template; // Skip non-email templates.
        }

        // Extract email ID.
        $email_id = str_replace( [ 'emails/', '.php' ], '', $template_name );
        $email_id = str_replace( '-', '_', $email_id );

        // Check if a custom template is assigned.
        $saved_templates = get_option( 'email_designer_templates', [] );

        if ( isset( $saved_templates[ $email_id ] ) ) {
            $template_id   = absint( $saved_templates[ $email_id ] );
            $template_post = get_post( $template_id );

            if ( $template_post && 'email_template' === $template_post->post_type ) {
                // Use the base template to output the custom content.
                return plugin_dir_path( __FILE__ ) . 'templates/base-email-template.php';
            }
        }

        // Fallback to the default WooCommerce template.
        return $template;
    }

    /**
     * Add the Email Designer settings page as a submenu under Email Templates.
     * 
     * @since  1.0.0
     * @return void
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=email_template',
            __( 'Email Designer Settings', 'email-designer-for-woocommerce' ),
            __( 'Settings', 'email-designer-for-woocommerce' ),
            'manage_options',
            'email-designer-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Render the settings page with tabs.
     * 
     * @since  1.0.0
     * @return void
     */
    public function render_settings_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Email Designer Settings', 'email-designer-for-woocommerce' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=email-designer-settings&tab=general" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'email-designer-for-woocommerce' ); ?></a>
                <a href="?page=email-designer-settings&tab=templates" class="nav-tab <?php echo $tab === 'templates' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Templates', 'email-designer-for-woocommerce' ); ?></a>
            </h2>
            <div class="tab-content">
                <?php
                if ( $tab === 'general' ) {
                    $this->render_general_settings();
                } elseif ( $tab === 'templates' ) {
                    $this->render_template_settings();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render general settings tab.
     * 
     * @since  1.0.0
     * @return void
     */
    public function render_general_settings() {
        echo '<form method="post" action="options.php">';
        settings_fields( 'email_designer_general_settings' );
        do_settings_sections( 'email_designer_general_settings' );
        submit_button();
        echo '</form>';
    }

    /**
     * Render template settings tab.
     *
     * Here we ensure that every email type has its own row. Since WooCommerce may not
     * return all email types (for example, the admin new order email is sometimes omitted),
     * we add it manually if necessary.
     * 
     * @since  1.0.0
     * @return void
     */
    public function render_template_settings() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<p>' . esc_html__( 'WooCommerce is not active.', 'email-designer-for-woocommerce' ) . '</p>';
            return;
        }

        $email_types = WC()->mailer()->get_emails();

        // Ensure that the admin new order email is included.
        if ( ! isset( $email_types['admin_new_order'] ) ) {
            $email_types['admin_new_order'] = new class {
                public $id = 'admin_new_order';
                public function get_title() {
                    return __( 'Admin New Order', 'email-designer-for-woocommerce' );
                }
            };
        }

        $saved_templates = get_option( 'email_designer_templates', [] ); // Retrieve saved settings.
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'email_designer_templates' );
            do_settings_sections( 'email_designer_templates' );
            ?>
            <table class="form-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Email Type', 'email-designer-for-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Assigned Template', 'email-designer-for-woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $email_types as $email ): ?>
                        <tr>
                            <td>
                                <?php
                                // Use the get_title() method if available.
                                echo ( is_callable( [ $email, 'get_title' ] ) ) ? esc_html( $email->get_title() ) : esc_html( $email->id );
                                ?>
                                <br><small>(ID: <?php echo esc_html( $email->id ); ?>)</small>
                            </td>
                            <td>
                                <select name="email_designer_templates[<?php echo esc_attr( $email->id ); ?>]">
                                    <option value=""><?php esc_html_e( 'Default WooCommerce Template', 'email-designer-for-woocommerce' ); ?></option>
                                    <?php
                                    $templates = get_posts( [
                                        'post_type'   => 'email_template',
                                        'numberposts' => -1,
                                        'orderby'     => 'title',
                                        'order'       => 'ASC',
                                    ] );
                                    foreach ( $templates as $template ) {
                                        $selected = selected(
                                            isset( $saved_templates[ $email->id ] ) ? $saved_templates[ $email->id ] : '',
                                            $template->ID,
                                            false
                                        );
                                        echo "<option value='" . esc_attr( $template->ID ) . "' {$selected}>" . esc_html( $template->post_title ) . "</option>";
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Shortcode to display customer name in the email.
     * 
     * @since  1.0.0
     * @return string
     */
    public function shortcode_customer_name() {
        $order = $this->get_current_email_order();
        if ( $order ) {
            return esc_html( $order->get_billing_first_name() );
        }
        return '';
    }

    /**
     * Shortcode to display order total in the email.
     * 
     * @since  1.0.0
     * @return string
     */
    public function shortcode_order_total() {
        $order = $this->get_current_email_order();
        if ( $order ) {
            return wc_price( $order->get_total() );
        }
        return '';
    }

    /**
     * Shortcode to display customer order details dynamically.
     * 
     * @since  1.0.0
     * @return bool|string
     */
    public function shortcode_order_details() {
        $order = $this->get_current_email_order();
        if ( $order ) {
            ob_start();
            ?>
            <table class="td" cellspacing="0" cellpadding="6" border="1" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th class="td" scope="col"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
                        <th class="td" scope="col"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
                        <th class="td" scope="col"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                        <tr>
                            <td class="td"><?php echo esc_html( $item->get_name() ); ?></td>
                            <td class="td"><?php echo esc_html( $item->get_quantity() ); ?></td>
                            <td class="td"><?php echo wc_price( $item->get_total() ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="td" scope="row" colspan="2"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
                        <td class="td"><?php echo $order->get_formatted_order_total(); ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php
            return ob_get_clean();
        }
        return '';
    }
    
    /**
     * Utility function to get the current email's order object.
     * 
     * @since  1.0.0
     * @return mixed
     */
    private function get_current_email_order() {
        if ( ! isset( WC()->mailer()->emails ) ) {
            return null;
        }

        // WooCommerce hook provides order object during email rendering.
        $email = current( WC()->mailer()->emails );
        if ( isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) {
            return $email->object;
        }
        return null;
    }

    /**
     * Utility function to get the current email object.
     * 
     * @since  1.0.0
     * @return mixed
     */
    private function get_current_email() {
        if ( ! isset( WC()->mailer()->emails ) ) {
            return null;
        }

        // WooCommerce hook provides email object during email rendering.
        foreach ( WC()->mailer()->emails as $email ) {
            if ( isset( $email->object ) && is_a( $email->object, 'WC_Order' ) ) {
                return $email;
            }
        }
        return null;
    }

    /**
     * Initialize custom email content filters based on assignments.
     *
     * @param WC_Email $email The email object.
     * @param WC_Order $order The order object.
     * 
     * @since  1.0.0
     * @return void
     */
    public function init_custom_email_content_filters( $email, $order ) {
        if ( ! $email || ! $order ) {
            return;
        }

        // Save the current email object to a global variable.
        global $current_email;
        $current_email = $email;

        // Retrieve assigned template for the email.
        $email_id        = $email->id;
        $saved_templates = get_option( 'email_designer_templates', [] );

        if ( isset( $saved_templates[ $email_id ] ) ) {
            $template_id   = absint( $saved_templates[ $email_id ] );
            $template_post = get_post( $template_id );

            if ( $template_post && 'email_template' === $template_post->post_type ) {
                add_filter( "woocommerce_email_content_{$email_id}", function( $content, $email_obj ) use ( $template_post ) {
                    // Process and inject the custom content
                    return do_shortcode( apply_filters( 'the_content', $template_post->post_content ) );
                }, 10, 2 );
            }
        }
    }
}

new EmailDesignerWooCommerce();
