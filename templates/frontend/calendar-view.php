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

<div class="health-calendar-view"  data-view="<?php echo esc_attr($view); ?>"
data-calendar-id="<?php echo esc_attr($calendar->ID); ?>">

    <div class="calendar-header">
        <h2><?php echo esc_html($calendar->post_title); ?></h2>
        <div class="view-controls">
            <button class="prev-period">←</button>
            <span class="current-period"></span>
            <button class="next-period">→</button>
        </div>
    </div>
    
    <div class="calendar-grid">
        <?php if ($view === 'month'): ?>
            <div class="weekday-headers">
                <?php
                $weekdays = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
                foreach ($weekdays as $weekday) {
                    echo "<div class='weekday'>$weekday</div>";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <div class="calendar-body">
            <?php
            // Calendar grid will be populated by JavaScript
            ?>
        </div>
    </div>
    
    <div class="schedule-details">
        <!-- Schedule details will be displayed here when a date is selected -->
    </div>
</div>