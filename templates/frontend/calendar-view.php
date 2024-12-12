<?php
$calendar_id = intval($attributes['id']);
$category = sanitize_text_field($attributes['category']);
$view = sanitize_text_field($attributes['view']);

if (!$calendar_id && !$category) {
    echo 'Please specify either a calendar ID or category.';
    return;
}

// Get calendar data
if ($calendar_id) {
    $calendar = get_post($calendar_id);
} else {
    $calendars = get_posts(array(
        'post_type' => 'health_calendar',
        'tax_query' => array(
            array(
                'taxonomy' => 'calendar_category',
                'field' => 'slug',
                'terms' => $category
            )
        ),
        'posts_per_page' => 1
    ));
    $calendar = !empty($calendars) ? $calendars[0] : null;
}

if (!$calendar) {
    echo 'Calendar not found.';
    return;
}

// Get yearly theme
$yearly_theme = get_post_meta($calendar->ID, '_yearly_theme_' . date('Y'), true);
$theme_image = get_post_meta($calendar->ID, '_yearly_theme_image_' . date('Y'), true);

// Calculate date range based on view
$today = new DateTime();
switch ($view) {
    case 'week':
        $start_date = clone $today;
        $start_date->modify('monday this week');
        $end_date = clone $start_date;
        $end_date->modify('+6 days');
        break;
    case 'day':
        $start_date = $end_date = $today;
        break;
    default: // month
        $start_date = clone $today;
        $start_date->modify('first day of this month');
        $end_date = clone $start_date;
        $end_date->modify('last day of this month');
}

$schedule_entries = HealthCalendarManager::get_instance()->get_schedule_entries(
    $calendar->ID,
    $start_date->format('Y-m-d'),
    $end_date->format('Y-m-d')
);
?>

<div class="health-calendar-view" data-view="<?php echo esc_attr($view); ?>"
     data-calendar-id="<?php echo esc_attr($calendar->ID); ?>">

    <!-- Calendar Topic and Yearly Theme Section -->
    <div class="calendar-header-extended">
        <!-- Topic Section -->
        <div class="calendar-topic">
            <h2><?php echo esc_html($calendar->post_title); ?></h2>
            <?php if ($yearly_theme): ?>
                <div class="yearly-theme-text"><?php echo esc_html($yearly_theme); ?></div>
            <?php endif; ?>
        </div>

        <!-- Yearly Theme Image -->
        <?php if ($theme_image): ?>
        <div class="yearly-theme-image">
            <img src="<?php echo esc_url($theme_image); ?>" alt="Yearly Theme">
        </div>
        <?php endif; ?>

        <!-- Month and Year Selection -->
        <div class="calendar-navigation">
            <div class="date-selectors">
                <select class="month-select" aria-label="Select Month">
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $month_name = date('F', mktime(0, 0, 0, $m, 1));
                        $selected = ($m == $today->format('n')) ? ' selected' : '';
                        echo sprintf(
                            '<option value="%d"%s>%s</option>',
                            $m,
                            $selected,
                            esc_html($month_name)
                        );
                    }
                    ?>
                </select>

                <select class="year-select" aria-label="Select Year">
                    <?php
                    $current_year = (int)$today->format('Y');
                    $start_year = $current_year - 2;
                    $end_year = $current_year + 2;
                    
                    for ($y = $start_year; $y <= $end_year; $y++) {
                        $selected = ($y == $current_year) ? ' selected' : '';
                        echo sprintf(
                            '<option value="%d"%s>%s</option>',
                            $y,
                            $selected,
                            esc_html($y)
                        );
                    }
                    ?>
                </select>
            </div>

            <!-- View Controls -->
            <div class="view-controls">
                <button class="prev-period" aria-label="Previous Period">←</button>
                <span class="current-period"></span>
                <button class="next-period" aria-label="Next Period">→</button>
            </div>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="calendar-grid">
        <?php if ($view === 'month'): ?>
            <div class="weekday-headers">
                <?php
                $weekdays = array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');
                foreach ($weekdays as $weekday) {
                    echo sprintf(
                        '<div class="weekday">%s</div>',
                        esc_html($weekday)
                    );
                }
                ?>
            </div>
        <?php endif; ?>
        
        <div class="calendar-body">
            <!-- Calendar grid will be populated by JavaScript -->
        </div>
    </div>
    
    <!-- Schedule Details and Messages -->
    <div class="calendar-details">
        <div class="schedule-details">
            <!-- Daily schedule details will be displayed here -->
        </div>
        
        <div class="message-section">
            <div class="biweekly-messages">
                <!-- Bi-weekly messages will appear here -->
            </div>
            <div class="special-day-message">
                <!-- Special day messages will appear here -->
            </div>
        </div>
    </div>

    <!-- Message Popup Container -->
    <div class="message-popup" style="display: none;">
        <div class="popup-content">
            <span class="close-popup">&times;</span>
            <div class="popup-message"></div>
        </div>
    </div>
</div>