<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SPBP_Social_Proof_Booster {

    public function __construct() {
        // Ensure the session is started before using it
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        // Hook to initialize the plugin
        add_action( 'init', array( $this, 'spbp_initialize' ) );

        // Hook to enqueue scripts and styles
        add_action( 'admin_enqueue_scripts', [$this, 'spbp_admin_custom_scripts'] );
        add_action( 'wp_enqueue_scripts', array( $this, 'spbp_enqueue_scripts' ) );

        //Initialize settings
        add_action( 'admin_init', array( $this, 'spbp_initialize_settings' ) );

        //Initialize settings menu
        add_action( 'admin_menu', array( $this, 'spbp_register_settings_menu' ) );

        // Register Hearbeat hooks
        add_filter('heartbeat_settings', [$this, 'spbp_adjust_heartbeat_interval']);
        add_filter('heartbeat_received', [$this, 'spbp_send_heartbeat_data'], 10, 2);
        add_filter('heartbeat_nopriv_received', [$this, 'spbp_send_heartbeat_data'], 10, 2);
        //Register ajax handlers
        add_action( 'wp_ajax_spbp_track_impression', array( $this, 'spbp_track_impression' ) );
        add_action( 'wp_ajax_nopriv_spbp_track_impression', array( $this, 'spbp_track_impression' ) );
        add_action( 'wp_ajax_spbp_track_click', array( $this, 'spbp_track_click' ) );
        add_action( 'wp_ajax_nopriv_spbp_track_click', array( $this, 'spbp_track_click' ) );
    }

    public function spbp_initialize() {
        // Plugin initialization code
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>WooCommerce is required for Social Proof Booster to work. Please install and activate WooCommerce.</p></div>';
            });
            return;
        }
    }

    function spbp_admin_custom_scripts() {
        wp_enqueue_style( 'spbp-admin-styles', SPBP_PLUGIN_URL . 'assets/css/admin-style.css', array(), '1.0' );
        wp_enqueue_script( 'spbp-admin-script', SPBP_PLUGIN_URL . 'assets/js/admin-script.js', array( 'jquery' ), '1.0', true );
    }

    public function spbp_enqueue_scripts() {
        wp_enqueue_style( 'spbp-style', SPBP_PLUGIN_URL . 'assets/css/style.css', array(), '1.0' );
        wp_enqueue_script('heartbeat');
        wp_enqueue_script( 'spbp-script', SPBP_PLUGIN_URL . 'assets/js/script.js', array( 'jquery' ), '1.0', true );

        $enabled = get_option( 'spbp_enabled', 1 );
        $popup_delay = get_option( 'spbp_popup_delay', 8 );
        $popup_bg_color = get_option( 'spbp_popup_bg_color', '#000000' );
        $popup_position = get_option( 'spbp_popup_position', 'bottom-left' );
        $nonce = wp_create_nonce( 'spbp_nonce_action' );
        wp_localize_script( 'spbp-script', 'spbp_data', array(
            'enabled'     => $enabled,
            'nonce'       => $nonce,
            'ajax_url'    => admin_url('admin-ajax.php'),
            'popup_delay' => $popup_delay,
            'bg_color'    => $popup_bg_color,
            'position'    => $popup_position,
        ) );
    }

    public function spbp_initialize_settings() {
        // Register settings
        register_setting(
            'spbp_settings_group',
            'spbp_enabled',
            array(
                'sanitize_callback' => [$this, 'spbp_sanitize_checkbox'],
            )
        );
        
        register_setting(
            'spbp_settings_group',
            'spbp_popup_delay',
            array(
                'sanitize_callback' => [$this, 'spbp_sanitize_integer'],
            )
        );
        
        register_setting(
            'spbp_settings_group',
            'spbp_popup_bg_color',
            array(
                'sanitize_callback' => 'sanitize_hex_color',
            )
        );
        
        register_setting(
            'spbp_settings_group',
            'spbp_popup_position',
            array(
                'sanitize_callback' => [$this ,'spbp_sanitize_popup_position'],
            )
        );


        // Add settings sections and fields
        add_settings_section(
            'spbp_general_settings', 
            'General Settings', 
            null, 
            'spbp-settings'
        );
    
        add_settings_field(
            'spbp_enabled',
            'Enable Popups',
            array( $this, 'spbp_render_checkbox' ),
            'spbp-settings',
            'spbp_general_settings',
            array( 'label_for' => 'spbp_enabled' )
        );
    
        add_settings_field(
            'spbp_popup_delay',
            'Popup Delay (in seconds)',
            array( $this, 'spbp_render_text_field' ),
            'spbp-settings',
            'spbp_general_settings',
            array( 'label_for' => 'spbp_popup_delay' )
        );

        // Add settings fields
        add_settings_field(
            'spbp_popup_bg_color',
            'Popup Background Color',
            array( $this, 'spbp_render_color_picker' ),
            'spbp-settings',
            'spbp_general_settings',
            array( 'label_for' => 'spbp_popup_bg_color' )
        );

        add_settings_field(
            'spbp_popup_position',
            'Popup Position',
            array( $this, 'spbp_render_dropdown' ),
            'spbp-settings',
            'spbp_general_settings',
            array( 'label_for' => 'spbp_popup_position' )
        );

         // Add settings fields for the new section
         add_settings_section(
            'spbp_tracking_section', 
            'Tracking Information', 
            null, 
            'spbp-settings'
        );

        // Display impressions and clicks data in a table
        add_settings_field(
            'spbp_tracking_data',
            'Tracked Impressions and Clicks',
            array( $this, 'spbp_render_tracking_data' ),
            'spbp-settings',
            'spbp_tracking_section'
        );

    }
    
    // Render tracking data (impressions and clicks) in a table
    public function spbp_render_tracking_data() {
        $impressions = get_option( 'spbp_impressions', array() );
        $clicks = get_option( 'spbp_clicks', array() );

        ?>
        <h3>Impressions</h3>
        <table class="form-table sbp-table">
            <thead>
                <tr>
                    <th>Popup Data</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($impressions)): ?>
                    <?php foreach ($impressions as $impression): ?>
                        <tr>
                            <td><?php echo esc_html($impression); ?></td>
                            <td><?php echo esc_html(gmdate('Y-m-d H:i:s', time())); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">No impressions tracked yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>Clicks</h3>
        <table class="form-table">
            <thead>
                <tr>
                    <th>Popup Data</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($clicks)): ?>
                    <?php foreach ($clicks as $click): ?>
                        <tr>
                            <td><?php echo esc_html($click); ?></td>
                            <td><?php echo esc_html(gmdate('Y-m-d H:i:s', time())); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">No clicks tracked yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    // Render settings fields
    public function spbp_render_checkbox( $args ) {
        $option = get_option( $args['label_for'] );
        ?>
        <input type="checkbox" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="1" <?php checked( 1, $option, true ); ?> />
        <?php
    }
    
    public function spbp_render_text_field( $args ) {
        $option = get_option( $args['label_for'], '6' ); // Default delay: 8 seconds
        ?>
        <input type="number" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" min="1" />
        <?php
    }
    
    public function spbp_register_settings_menu() {
        add_menu_page(
            'Social Proof Booster',    // Page title
            'Social Proof Booster',    // Menu title
            'manage_options',          // Capability
            'spbp-settings',            // Menu slug
            array( $this, 'spbp_settings_page' ), // Callback
            'dashicons-megaphone',     // Icon
            90                         // Position
        );
    }

    public function spbp_settings_page() {
        ?>
        <div class="wrap">
            <h1>Social Proof Booster Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'spbp_settings_group' );
                do_settings_sections( 'spbp-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Render a color picker field
    public function spbp_render_color_picker( $args ) {
        $option = get_option( $args['label_for'], '#000000' ); // Default: black
        ?>
        <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" class="spbp-color-picker" />
        <?php
    }

    // Render a dropdown for popup position
    public function spbp_render_dropdown( $args ) {
        $option = get_option( $args['label_for'], 'bottom-right' ); // Default: bottom-right
        ?>
        <select id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>">
            <option value="bottom-right" <?php selected( $option, 'bottom-right' ); ?>>Bottom Right</option>
            <option value="bottom-left" <?php selected( $option, 'bottom-left' ); ?>>Bottom Left</option>
            <option value="top-right" <?php selected( $option, 'top-right' ); ?>>Top Right</option>
            <option value="top-left" <?php selected( $option, 'top-left' ); ?>>Top Left</option>
        </select>
        <?php
    }
    
    /* Heartbeat API for real-time orders update */
    public function spbp_send_heartbeat_data($response, $data) {
        if (isset($data['spbp_check_new_orders'])) {
            // Fetch the most recent orders (status: any, as you want any orders)
            $orders = wc_get_orders(array(
                'limit' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => 'any', // Accepting all statuses
            ));
    
            // Initialize new orders array
            $new_orders = array();
    
            // Initialize session variable to track shown orders if not set
            if (!isset($_SESSION['spbp_shown_orders'])) {
                $_SESSION['spbp_shown_orders'] = array(); // Track orders shown in the current session
            }
    
            foreach ($orders as $order) {
                // Skip this order if it has already been shown
                if (in_array($order->get_id(), $_SESSION['spbp_shown_orders'])) {
                    continue;
                }
    
                $items = $order->get_items();  // Get order items
                if (empty($items)) {
                    continue;  // Skip if no items
                }
    
                $products_with_links = array();
    
                foreach ( $order->get_items() as $item ) {
                    // Get the product object
                    $product = $item->get_product();
                    if ( $product ) {
                        // Get the product name
                        $product_name = $product->get_name();
                        // Get the product URL
                        $product_url = get_permalink( $product->get_id() );
                        // Create a clickable link
                        $product_link = '<a href="' . esc_url( $product_url ) . '">' . esc_html( $product_name ) . '</a>';
                        $products_with_links[] = $product_link;
                    }
                }
        
                $new_orders[] = array(
                    'name'       => $order->get_billing_first_name(),
                    'products'   => implode( ', ', $products_with_links ), // Concatenate products with links
                    'timestamp'  => $order->get_date_created()->getTimestamp(),
                );
    
                // Add this order ID to the list of shown orders
                $_SESSION['spbp_shown_orders'][] = $order->get_id();
            }
    
            if (!empty($new_orders)) {
                $response['new_orders'] = $new_orders;
            }
        }
    
        return $response;
    }
    
    

    // Adjust the heartbeat interval (every 10 seconds in this case)
    public function spbp_adjust_heartbeat_interval($settings) {
        $settings['interval'] = 15; // Set to 10 seconds or adjust as needed
        return $settings;
    }

    public function spbp_track_impression() {
        // Sanitize and unslash the nonce before verifying it
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( sanitize_key( $_POST['nonce'] ) ), 'spbp_nonce_action' ) ) {
            wp_send_json_error( array( 'message' => 'Nonce verification failed.' ) );
            return;
        }
    
        // Proceed with original functionality
        if ( isset( $_POST['popup_data'] ) ) {
            $popup_data = sanitize_text_field( wp_unslash( $_POST['popup_data'] ) );
            $impressions = get_option( 'spbp_impressions', array() );
            $impressions[] = $popup_data;
            update_option( 'spbp_impressions', $impressions );
        }
    
        wp_send_json_success();
    }
    
    

    public function spbp_track_click() {
        // Sanitize and unslash the nonce before verifying it
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( sanitize_key( $_POST['nonce'] ) ), 'spbp_nonce_action' ) ) {
            wp_send_json_error( array( 'message' => 'Nonce verification failed.' ) );
            return;
        }
    
        // Proceed with original functionality
        if ( isset( $_POST['popup_data'] ) ) {
            $popup_data = sanitize_text_field( wp_unslash( $_POST['popup_data'] ) );
            $clicks = get_option( 'spbp_clicks', array() );
            $clicks[] = $popup_data;
            update_option( 'spbp_clicks', $clicks );
        }
    
        wp_send_json_success();
    }
    
    //Sanitize function
    public function spbp_sanitize_checkbox($input) {
        return $input === '1' ? '1' : '0';
    }
    public function spbp_sanitize_integer($input) {
        return absint($input); // Ensures the input is a positive integer.
    }
    public function spbp_sanitize_popup_position($input) {
        $allowed_positions = array('top-left', 'top-right', 'bottom-left', 'bottom-right');
        return in_array($input, $allowed_positions, true) ? $input : 'bottom-right';
    }
}

// Initialize the plugin
new SPBP_Social_Proof_Booster();
