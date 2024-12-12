<?php
/*
Plugin Name: Health Calendar Manager
Description: A comprehensive health calendar system for managing patient schedules and instructions
Version: 1.0
Author: Health Calander
*/

if (!defined('ABSPATH')) exit;
// Register activation hook outside the class
register_activation_hook(__FILE__, 'health_calendar_activate');

function health_calendar_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table for storing schedule entries
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}health_schedule (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        calendar_id bigint(20) NOT NULL,
        schedule_date date NOT NULL,
        schedule_time time,
        instructions text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY calendar_id (calendar_id),
        KEY schedule_date (schedule_date)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

class HealthCalendarManager {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // Register shortcode
        add_shortcode('health_calendar', array($this, 'calendar_shortcode'));
    }
    public function init() {
        // Register Custom Post Type for Calendars
        register_post_type('health_calendar', array(
            'labels' => array(
                'name' => 'Health Calendars',
                'singular_name' => 'Health Calendar',
                'add_new' => 'Add New Calendar',
                'add_new_item' => 'Add New Health Calendar',
                'edit_item' => 'Edit Health Calendar',
                'new_item' => 'New Health Calendar',
                'view_item' => 'View Health Calendar',
                'search_items' => 'Search Health Calendars',
                'not_found' => 'No health calendars found',
                'not_found_in_trash' => 'No health calendars found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'thumbnail'),
            'menu_icon' => 'dashicons-calendar-alt'
        ));
        
        // Register Calendar Category Taxonomy
        register_taxonomy('calendar_category', 'health_calendar', array(
            'labels' => array(
                'name' => 'Calendar Categories',
                'singular_name' => 'Calendar Category',
                'search_items' => 'Search Categories',
                'all_items' => 'All Categories',
                'edit_item' => 'Edit Category',
                'update_item' => 'Update Category',
                'add_new_item' => 'Add New Category',
                'new_item_name' => 'New Category Name',
                'menu_name' => 'Categories'
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'calendar-category')
        ));
    }
    
    
    
    
    public function admin_scripts($hook) {
        if ('health_calendar_page_health-calendar-schedule' !== $hook) {
            return;
        }
        
        wp_enqueue_style('health-calendar-admin', plugins_url('assets/css/admin.css', __FILE__));
        wp_enqueue_script('health-calendar-admin', plugins_url('assets/js/admin.js', __FILE__), array('jquery', 'jquery-ui-datepicker'));
        
        // Localize the script with new data
        wp_localize_script('health-calendar-admin', 'healthCalendar', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('health_calendar_nonce')
        ));
    }
    public function frontend_scripts() {
        wp_enqueue_style('health-calendar-frontend', plugins_url('assets/css/frontend.css', __FILE__));
        wp_enqueue_script('health-calendar-frontend', plugins_url('assets/js/frontend.js', __FILE__), array('jquery'));

        wp_localize_script('health-calendar-frontend', 'healthCalendar', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('health_calendar_nonce')
        ));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=health_calendar',
            'Schedule Manager',
            'Schedule Manager',
            'manage_options',
            'health-calendar-schedule',
            array($this, 'render_schedule_manager')
        );
    }
    
    public function render_schedule_manager() {
        // Admin interface for managing schedules
        include(plugin_dir_path(__FILE__) . 'templates/admin/schedule-manager.php');
    }
    
    public function calendar_shortcode($atts) {
        $attributes = shortcode_atts(array(
            'id' => 0,
            'category' => '',
            'view' => 'month' // month, week, day
        ), $atts);
        
        ob_start();
        include(plugin_dir_path(__FILE__) . 'templates/frontend/calendar-view.php');
        return ob_get_clean();
    }
    
    public function get_schedule_entries($calendar_id, $start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}health_schedule 
            WHERE calendar_id = %d 
            AND schedule_date BETWEEN %s AND %s 
            ORDER BY schedule_date, schedule_time",
            $calendar_id, $start_date, $end_date
        ));
    }
}



// Initialize the plugin
HealthCalendarManager::get_instance();

// Admin AJAX handlers
add_action('wp_ajax_save_schedule_entry', 'save_schedule_entry_callback');
function save_schedule_entry_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'health_calendar_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token.'));
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access.'));
        return;
    }
    
    // Validate required fields
    if (empty($_POST['calendar_id']) || empty($_POST['date']) || empty($_POST['instructions'])) {
        wp_send_json_error(array('message' => 'Required fields are missing.'));
        return;
    }
    
    global $wpdb;
    
    $calendar_id = intval($_POST['calendar_id']);
    $date = sanitize_text_field($_POST['date']);
    $time = !empty($_POST['time']) ? sanitize_text_field($_POST['time']) : null;
    $instructions = wp_kses_post($_POST['instructions']);
    
    // Validate date format
    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
        wp_send_json_error(array('message' => 'Invalid date format.'));
        return;
    }
    
    // Validate time format if provided
    if ($time !== null) {
        $time_obj = DateTime::createFromFormat('H:i', $time);
        if (!$time_obj || $time_obj->format('H:i') !== $time) {
            wp_send_json_error(array('message' => 'Invalid time format.'));
            return;
        }
    }
    
    // Insert the schedule entry
    $result = $wpdb->insert(
        $wpdb->prefix . 'health_schedule',
        array(
            'calendar_id' => $calendar_id,
            'schedule_date' => $date,
            'schedule_time' => $time,
            'instructions' => $instructions
        ),
        array('%d', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        wp_send_json_error(array(
            'message' => 'Database error: ' . $wpdb->last_error
        ));
        return;
    }
    
    wp_send_json_success(array(
        'message' => 'Schedule entry saved successfully',
        'entry_id' => $wpdb->insert_id
    ));
}
add_action('wp_ajax_save_schedule_entry', 'save_schedule_entry_callback');
function get_schedule_entries_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'health_calendar_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token.'));
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access.'));
        return;
    }
    
    global $wpdb;
    $calendar_id = intval($_POST['calendar_id']);
    
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}health_schedule 
        WHERE calendar_id = %d 
        ORDER BY schedule_date DESC, schedule_time ASC",
        $calendar_id
    ));
    
    wp_send_json_success($entries);
}
// Add this right after the other AJAX registrations
add_action('wp_ajax_get_schedule_entries', 'get_schedule_entries_callback');
// AJAX handler for getting schedule details (frontend)
add_action('wp_ajax_nopriv_get_schedule_details', 'get_schedule_details_callback');
add_action('wp_ajax_get_schedule_details', 'get_schedule_details_callback');


function get_schedule_details_callback() {
    $calendar_id = intval($_POST['calendar_id']);
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : null;
    
    global $wpdb;
    
    // If a date range is provided (for month view)
    if ($start_date && $end_date) {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}health_schedule 
            WHERE calendar_id = %d 
            AND schedule_date BETWEEN %s AND %s 
            ORDER BY schedule_date ASC, schedule_time ASC",
            $calendar_id, $start_date, $end_date
        ));
    }
    // If a single date is provided (for day view)
    else if ($date) {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}health_schedule 
            WHERE calendar_id = %d 
            AND schedule_date = %s 
            ORDER BY schedule_time ASC",
            $calendar_id, $date
        ));
    }
    
    // Add debug information
    if (empty($entries)) {
        wp_send_json_success(array(
            'debug' => array(
                'calendar_id' => $calendar_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'date' => $date,
                'query' => $wpdb->last_query,
                'error' => $wpdb->last_error
            )
        ));
        return;
    }
    
    wp_send_json_success($entries);
}

// AJAX handler for deleting schedule entries (admin)
add_action('wp_ajax_delete_schedule_entry', 'delete_schedule_entry_callback');
function delete_schedule_entry_callback() {
    check_ajax_referer('health_calendar_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    global $wpdb;
    $entry_id = intval($_POST['entry_id']);
    
    $result = $wpdb->delete(
        $wpdb->prefix . 'health_schedule',
        array('id' => $entry_id),
        array('%d')
    );
    
    if ($result) {
        wp_send_json_success(array('message' => 'Schedule entry deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Error deleting schedule entry'));
    }
}

// AJAX handler for updating schedule entries (admin)
add_action('wp_ajax_update_schedule_entry', 'update_schedule_entry_callback');
function update_schedule_entry_callback() {
    check_ajax_referer('health_calendar_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    global $wpdb;
    
    $entry_id = intval($_POST['entry_id']);
    $date = sanitize_text_field($_POST['date']);
    $time = sanitize_text_field($_POST['time']);
    $instructions = wp_kses_post($_POST['instructions']);
    
    $result = $wpdb->update(
        $wpdb->prefix . 'health_schedule',
        array(
            'schedule_date' => $date,
            'schedule_time' => $time,
            'instructions' => $instructions
        ),
        array('id' => $entry_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success(array('message' => 'Schedule entry updated successfully'));
    } else {
        wp_send_json_error(array('message' => 'Error updating schedule entry'));
    }
}

// Add metadata fields for calendar customization
function add_calendar_meta_boxes() {
    add_meta_box(
        'health_calendar_settings',
        'Calendar Settings',
        'render_calendar_settings',
        'health_calendar',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_calendar_meta_boxes');

function render_calendar_settings($post) {
    wp_nonce_field('health_calendar_meta', 'health_calendar_meta_nonce');
    
    $color_scheme = get_post_meta($post->ID, '_calendar_color_scheme', true);
    $default_view = get_post_meta($post->ID, '_calendar_default_view', true);
    ?>
    <div class="calendar-settings">
        <p>
            <label for="calendar_color_scheme">Color Scheme:</label>
            <select name="calendar_color_scheme" id="calendar_color_scheme">
                <option value="default" <?php selected($color_scheme, 'default'); ?>>Default Blue</option>
                <option value="green" <?php selected($color_scheme, 'green'); ?>>Green</option>
                <option value="purple" <?php selected($color_scheme, 'purple'); ?>>Purple</option>
                <option value="orange" <?php selected($color_scheme, 'orange'); ?>>Orange</option>
            </select>
        </p>
        <p>
            <label for="calendar_default_view">Default View:</label>
            <select name="calendar_default_view" id="calendar_default_view">
                <option value="month" <?php selected($default_view, 'month'); ?>>Month</option>
                <option value="week" <?php selected($default_view, 'week'); ?>>Week</option>
                <option value="day" <?php selected($default_view, 'day'); ?>>Day</option>
            </select>
        </p>
    </div>
    <?php
}

// Save calendar metadata
function save_calendar_meta($post_id) {
    if (!isset($_POST['health_calendar_meta_nonce'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['health_calendar_meta_nonce'], 'health_calendar_meta')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['calendar_color_scheme'])) {
        update_post_meta(
            $post_id,
            '_calendar_color_scheme',
            sanitize_text_field($_POST['calendar_color_scheme'])
        );
    }
    
    if (isset($_POST['calendar_default_view'])) {
        update_post_meta(
            $post_id,
            '_calendar_default_view',
            sanitize_text_field($_POST['calendar_default_view'])
        );
    }
}
add_action('save_post_health_calendar', 'save_calendar_meta');

// Add shortcode column to calendar list
function add_calendar_shortcode_column($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['shortcode'] = 'Shortcode';
        }
    }
    return $new_columns;
}
add_filter('manage_health_calendar_posts_columns', 'add_calendar_shortcode_column');

function display_calendar_shortcode_column($column, $post_id) {
    if ($column === 'shortcode') {
        echo '<code>[health_calendar id="' . $post_id . '"]</code>';
    }
}
add_action('manage_health_calendar_posts_custom_column', 'display_calendar_shortcode_column', 10, 2);