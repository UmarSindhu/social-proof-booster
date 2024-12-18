<?php

class Social_Proof_Booster {

    public function __construct() {
        // Ensure the session is started before using it
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        // Hook to initialize the plugin
        add_action( 'init', array( $this, 'spb_initialize' ) );

        // Hook to enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'spb_enqueue_scripts' ) );
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_script('heartbeat');
        });

        //Initialize settings
        add_action( 'admin_init', array( $this, 'spb_initialize_settings' ) );

        //Initialize settings menu
        add_action( 'admin_menu', array( $this, 'spb_register_settings_menu' ) );

        // Register Hearbeat hooks
        add_filter('heartbeat_settings', [$this, 'spb_adjust_heartbeat_interval']);
        add_filter('heartbeat_received', [$this, 'spb_send_heartbeat_data'], 10, 2);
        add_filter('heartbeat_nopriv_received', [$this, 'spb_send_heartbeat_data'], 10, 2);
        //Register ajax handlers
        add_action( 'wp_ajax_spb_track_impression', array( $this, 'spb_track_impression' ) );
        add_action( 'wp_ajax_nopriv_spb_track_impression', array( $this, 'spb_track_impression' ) );
        add_action( 'wp_ajax_spb_track_click', array( $this, 'spb_track_click' ) );
        add_action( 'wp_ajax_nopriv_spb_track_click', array( $this, 'spb_track_click' ) );
    }

    public function spb_initialize() {
        // Plugin initialization code
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>WooCommerce is required for Social Proof Booster to work. Please install and activate WooCommerce.</p></div>';
            });
            return;
        }
    }

    public function spb_enqueue_scripts() {
        wp_enqueue_style( 
            'spb-style', 
            SPB_PLUGIN_URL . 'assets/css/style.css', 
            array(), 
            '1.0' 
        );
        wp_enqueue_script( 
            'spb-script', 
            SPB_PLUGIN_URL . 'assets/js/script.js', 
            array( 'jquery' ), 
            '1.0', 
            true 
        );

        $enabled = get_option( 'spb_enabled', 1 );
        $popup_delay = get_option( 'spb_popup_delay', 8 );
        $popup_bg_color = get_option( 'spb_popup_bg_color', '#000000' );
        $popup_position = get_option( 'spb_popup_position', 'bottom-left' );
    
        wp_localize_script( 'spb-script', 'spb_data', array(
            'enabled'     => $enabled,
            'ajax_url'    => admin_url('admin-ajax.php'),
            'popup_delay' => $popup_delay,
            'bg_color'    => $popup_bg_color,
            'position'    => $popup_position,
        ) );
    }

    public function spb_initialize_settings() {
        // Register settings
        register_setting( 'spb_settings_group', 'spb_enabled' );
        register_setting( 'spb_settings_group', 'spb_popup_delay' );
        register_setting( 'spb_settings_group', 'spb_popup_bg_color' );
        register_setting( 'spb_settings_group', 'spb_popup_position' );


        // Add settings sections and fields
        add_settings_section(
            'spb_general_settings', 
            'General Settings', 
            null, 
            'spb-settings'
        );
    
        add_settings_field(
            'spb_enabled',
            'Enable Popups',
            array( $this, 'spb_render_checkbox' ),
            'spb-settings',
            'spb_general_settings',
            array( 'label_for' => 'spb_enabled' )
        );
    
        add_settings_field(
            'spb_popup_delay',
            'Popup Delay (in seconds)',
            array( $this, 'spb_render_text_field' ),
            'spb-settings',
            'spb_general_settings',
            array( 'label_for' => 'spb_popup_delay' )
        );

        // Add settings fields
        add_settings_field(
            'spb_popup_bg_color',
            'Popup Background Color',
            array( $this, 'spb_render_color_picker' ),
            'spb-settings',
            'spb_general_settings',
            array( 'label_for' => 'spb_popup_bg_color' )
        );

        add_settings_field(
            'spb_popup_position',
            'Popup Position',
            array( $this, 'spb_render_dropdown' ),
            'spb-settings',
            'spb_general_settings',
            array( 'label_for' => 'spb_popup_position' )
        );

         // Add settings fields for the new section
         add_settings_section(
            'spb_tracking_section', 
            'Tracking Information', 
            null, 
            'spb-settings'
        );

        // Display impressions and clicks data in a table
        add_settings_field(
            'spb_tracking_data',
            'Tracked Impressions and Clicks',
            array( $this, 'spb_render_tracking_data' ),
            'spb-settings',
            'spb_tracking_section'
        );

    }
    
    // Render tracking data (impressions and clicks) in a table
    public function spb_render_tracking_data() {
        $impressions = get_option( 'spb_impressions', array() );
        $clicks = get_option( 'spb_clicks', array() );

        ?>
        <h3>Impressions</h3>
        <table class="form-table">
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
                            <td><?php echo esc_html(date('Y-m-d H:i:s', time())); ?></td>
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
                            <td><?php echo esc_html(date('Y-m-d H:i:s', time())); ?></td>
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
    public function spb_render_checkbox( $args ) {
        $option = get_option( $args['label_for'] );
        ?>
        <input type="checkbox" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="1" <?php checked( 1, $option, true ); ?> />
        <?php
    }
    
    public function spb_render_text_field( $args ) {
        $option = get_option( $args['label_for'], '6' ); // Default delay: 8 seconds
        ?>
        <input type="number" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" min="1" />
        <?php
    }
    
    public function spb_register_settings_menu() {
        add_menu_page(
            'Social Proof Booster',    // Page title
            'Social Proof Booster',    // Menu title
            'manage_options',          // Capability
            'spb-settings',            // Menu slug
            array( $this, 'spb_settings_page' ), // Callback
            'dashicons-megaphone',     // Icon
            90                         // Position
        );
    }

    public function spb_settings_page() {
        ?>
        <div class="wrap">
            <h1>Social Proof Booster Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'spb_settings_group' );
                do_settings_sections( 'spb-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Render a color picker field
    public function spb_render_color_picker( $args ) {
        $option = get_option( $args['label_for'], '#000000' ); // Default: black
        ?>
        <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $args['label_for'] ); ?>" value="<?php echo esc_attr( $option ); ?>" class="spb-color-picker" />
        <script>
            jQuery(document).ready(function($) {
                $('.spb-color-picker').wpColorPicker();
            });
        </script>
        <?php
    }

    // Render a dropdown for popup position
    public function spb_render_dropdown( $args ) {
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
    public function spb_send_heartbeat_data($response, $data) {
        if (isset($data['spb_check_new_orders'])) {
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
            if (!isset($_SESSION['spb_shown_orders'])) {
                $_SESSION['spb_shown_orders'] = array(); // Track orders shown in the current session
            }
    
            foreach ($orders as $order) {
                // Skip this order if it has already been shown
                if (in_array($order->get_id(), $_SESSION['spb_shown_orders'])) {
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
                $_SESSION['spb_shown_orders'][] = $order->get_id();
            }
    
            if (!empty($new_orders)) {
                $response['new_orders'] = $new_orders;
            }
        }
    
        return $response;
    }
    
    

    // Adjust the heartbeat interval (every 10 seconds in this case)
    public function spb_adjust_heartbeat_interval($settings) {
        $settings['interval'] = 15; // Set to 10 seconds or adjust as needed
        return $settings;
    }

    public function spb_track_impression() {
        if ( isset( $_POST['popup_data'] ) ) {
            $popup_data = sanitize_text_field( wp_unslash( $_POST['popup_data'] ) );
            $impressions = get_option( 'spb_impressions', array() );
            $impressions[] = $popup_data;
            update_option( 'spb_impressions', $impressions );
        }
        wp_send_json_success();
    }

    public function spb_track_click() {
        if ( isset( $_POST['popup_data'] ) ) {
            $popup_data = sanitize_text_field( wp_unslash( $_POST['popup_data'] ) );
            $clicks = get_option( 'spb_clicks', array() );
            $clicks[] = $popup_data;
            update_option( 'spb_clicks', $clicks );
        }
        wp_send_json_success();
    }
    

}

// Initialize the plugin
new Social_Proof_Booster();
