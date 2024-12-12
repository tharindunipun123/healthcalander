<div class="wrap">
    <h1>Schedule Manager</h1>
    
    <div class="health-calendar-container">
        <!-- Calendar Selection -->
        <div class="calendar-selector">
            <div class="selector-group">
                <label for="calendar-select">Select Calendar:</label>
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

            <!-- Yearly Theme Section -->
            <div class="theme-management">
                <h3>Yearly Theme Management</h3>
                <form id="yearly-theme-form" class="theme-form">
                    <?php wp_nonce_field('health_calendar_nonce', 'theme_nonce'); ?>
                    <input type="hidden" name="calendar_id" value="">
                    
                    <div class="form-group">
                        <label for="theme-year">Year:</label>
                        <select id="theme-year" name="theme_year">
                            <?php
                            $current_year = date('Y');
                            for ($y = $current_year - 1; $y <= $current_year + 2; $y++) {
                                echo "<option value='$y'" . ($y == $current_year ? ' selected' : '') . ">$y</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="theme-title">Theme Title:</label>
                        <input type="text" id="theme-title" name="theme_title" required>
                    </div>

                    <div class="form-group">
                        <label for="theme-image">Theme Image:</label>
                        <input type="hidden" id="theme-image-id" name="theme_image_id">
                        <button type="button" class="button upload-theme-image">Upload Image</button>
                        <div class="theme-image-preview"></div>
                    </div>

                    <button type="submit" class="button button-primary">Save Theme</button>
                </form>
            </div>
        </div>
        
        <!-- Schedule Entry Form -->
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
                    <label for="entry-type">Entry Type:</label>
                    <select id="entry-type" name="entry_type">
                        <option value="regular">Regular Entry</option>
                        <option value="holiday">Holiday</option>
                        <option value="special_day">Special Day</option>
                        <option value="biweekly">Bi-weekly Message</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="schedule-instructions">Instructions/Message:</label>
                    <textarea id="schedule-instructions" name="instructions" rows="4" required></textarea>
                </div>

                <div class="form-group holiday-options" style="display: none;">
                    <label>
                        <input type="checkbox" name="recurring_holiday" id="recurring-holiday">
                        Recurring yearly holiday
                    </label>
                </div>

                <div class="form-group message-options" style="display: none;">
                    <label for="message-type">Message Display Type:</label>
                    <select id="message-type" name="message_type">
                        <option value="popup">Popup Message</option>
                        <option value="inline">Inline Display</option>
                    </select>
                </div>
                
                <button type="submit" class="button button-primary">Save Schedule Entry</button>
            </form>
        </div>
        
        <!-- Schedule Entries List -->
        <div class="schedule-list">
            <h3>Current Schedule Entries</h3>
            <div class="entry-filters">
                <select id="entry-type-filter">
                    <option value="">All Entries</option>
                    <option value="regular">Regular Entries</option>
                    <option value="holiday">Holidays</option>
                    <option value="special_day">Special Days</option>
                    <option value="biweekly">Bi-weekly Messages</option>
                </select>
                <input type="month" id="date-filter">
            </div>
            <div id="schedule-entries"></div>
        </div>
    </div>
</div>

<script type="text/template" id="entry-template">
    <tr data-id="<%= id %>">
        <td><%= schedule_date %></td>
        <td><%= schedule_time || 'N/A' %></td>
        <td><%= entry_type %></td>
        <td><%= instructions %></td>
        <td>
            <button class="button edit-entry">Edit</button>
            <button class="button delete-entry">Delete</button>
        </td>
    </tr>
</script>