<div class="wrap">
    <h1>Schedule Manager</h1>
    
    <div class="health-calendar-container">
        <div class="calendar-selector">
            <select id="calendar-select">
                <?php
                $calendars = get_posts(array(
                    'post_type' => 'health_calendar',
                    'posts_per_page' => -1
                ));
                foreach ($calendars as $calendar) {
                    echo sprintf(
                        '<option value="%d">%s</option>',
                        $calendar->ID,
                        esc_html($calendar->post_title)
                    );
                }
                ?>
            </select>
        </div>
        
        <div class="schedule-form">
            <h3>Add New Schedule Entry</h3>
            <form id="schedule-entry-form">
                <?php wp_nonce_field('health_calendar_nonce', 'health_calendar_nonce'); ?>
                <input type="hidden" id="calendar-id" name="calendar_id" value="">
                
                <div class="form-group">
                    <label for="schedule-date">Date:</label>
                    <input type="date" id="schedule-date" name="date" required>
                </div>
                
                <div class="form-group">
                    <label for="schedule-time">Time (optional):</label>
                    <input type="time" id="schedule-time" name="time">
                </div>
                
                <div class="form-group">
                    <label for="schedule-instructions">Instructions:</label>
                    <textarea id="schedule-instructions" name="instructions" rows="4" required></textarea>
                </div>
                
                <button type="submit" class="button button-primary">Save Schedule Entry</button>
            </form>
        </div>
        
        <div class="schedule-list">
            <h3>Current Schedule Entries</h3>
            <div id="schedule-entries"></div>
        </div>
    </div>
</div>