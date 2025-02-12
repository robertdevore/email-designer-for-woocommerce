<?php
/**
 * Plugin Name: Email Designer for WooCommerce®
 * Plugin URI: https://example.com
 * Description: Design custom email templates for WooCommerce using Gutenberg.
 * Version: 1.3
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPLv2 or later
 * Text Domain: email-designer-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

// Include Composer autoloader if it exists.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

class EmailDesignerWooCommerce {

	public function __construct() {
		add_action( 'init', [ $this, 'register_email_template_post_type' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );

		// Hook to override WooCommerce email templates.
		add_filter( 'woocommerce_locate_template', [ $this, 'override_email_templates' ], 100, 3 );

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
		add_shortcode( 'customer_name', [ $this, 'shortcode_customer_name' ] );
		add_shortcode( 'order_total', [ $this, 'shortcode_order_total' ] );
		add_shortcode( 'order_details', [ $this, 'shortcode_order_details' ] );

		// Initialize filters for custom email content.
		add_action( 'woocommerce_before_send_email', [ $this, 'init_custom_email_content_filters' ], 10, 2 );
	}

	/**
	 * Register a custom post type for email templates.
	 */
	public function register_email_template_post_type() {
		$labels = [
			'name'          => __( 'Email Templates', 'email-designer-woocommerce' ),
			'singular_name' => __( 'Email Template', 'email-designer-woocommerce' ),
			'add_new'       => __( 'Add New Template', 'email-designer-woocommerce' ),
			'add_new_item'  => __( 'Add New Email Template', 'email-designer-woocommerce' ),
			'edit_item'     => __( 'Edit Email Template', 'email-designer-woocommerce' ),
			'new_item'      => __( 'New Email Template', 'email-designer-woocommerce' ),
			'view_item'     => __( 'View Email Template', 'email-designer-woocommerce' ),
			'all_items'     => __( 'All Email Templates', 'email-designer-woocommerce' ),
			'menu_name'     => __( 'Email Templates', 'email-designer-woocommerce' ),
		];

		register_post_type( 'email_template', [
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'supports'     => [ 'title', 'editor' ],
			'show_in_rest' => true,
			'capability_type' => 'post',
		] );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		// General section.
		add_settings_section(
			'email_designer_general_section',
			__( 'General Settings', 'email-designer-woocommerce' ),
			function () {
				echo '<p>' . esc_html__( 'General configuration options for Email Designer.', 'email-designer-woocommerce' ) . '</p>';
			},
			'email_designer_general_settings'
		);

		// Custom CSS field.
		add_settings_field(
			'email_designer_custom_css',
			__( 'Custom Email CSS', 'email-designer-woocommerce' ),
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
			__( 'Template Assignments', 'email-designer-woocommerce' ),
			function () {
				echo '<p>' . esc_html__( 'Assign custom templates to WooCommerce emails. Each email type can be designed differently.', 'email-designer-woocommerce' ) . '</p>';
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
	 * Add a settings page with tabs.
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Email Designer', 'email-designer-woocommerce' ),
			__( 'Email Designer', 'email-designer-woocommerce' ),
			'manage_options',
			'email-designer-settings',
			[ $this, 'render_settings_page' ],
			'dashicons-email',
			30
		);
	}

	/**
	 * Render the settings page with tabs.
	 */
	public function render_settings_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email Designer Settings', 'email-designer-woocommerce' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=email-designer-settings&tab=general" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'email-designer-woocommerce' ); ?></a>
				<a href="?page=email-designer-settings&tab=templates" class="nav-tab <?php echo $tab === 'templates' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Templates', 'email-designer-woocommerce' ); ?></a>
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
	 */
	public function render_template_settings() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce is not active.', 'email-designer-woocommerce' ) . '</p>';
			return;
		}

		$email_types = WC()->mailer()->get_emails();

		// Ensure that the admin new order email is included.
		if ( ! isset( $email_types['admin_new_order'] ) ) {
			$email_types['admin_new_order'] = new class {
				public $id = 'admin_new_order';
				public function get_title() {
					return __( 'Admin New Order', 'email-designer-woocommerce' );
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
						<th><?php esc_html_e( 'Email Type', 'email-designer-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Assigned Template', 'email-designer-woocommerce' ); ?></th>
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
									<option value=""><?php esc_html_e( 'Default WooCommerce Template', 'email-designer-woocommerce' ); ?></option>
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
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'email-designer-blocks',
			plugin_dir_url( __FILE__ ) . 'blocks.js',
			[ 'wp-blocks', 'wp-editor', 'wp-components', 'wp-data', 'wp-element' ],
			filemtime( plugin_dir_path( __FILE__ ) . 'blocks.js' )
		);
	}

	/**
	 * Shortcode to display customer name in the email.
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
	 */
	public function shortcode_order_details() {
		$order = $this->get_current_email_order();
		if ( $order ) {
			ob_start();
			?>
			<ul>
				<?php foreach ( $order->get_items() as $item ) : ?>
					<li><?php echo esc_html( $item->get_name() ); ?> - <?php echo wc_price( $item->get_total() ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php
			return ob_get_clean();
		}
		return '';
	}

	/**
	 * Utility function to get the current email's order object.
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
