jQuery(document).ready(function($) {
    const scheduleForm = $('#schedule-entry-form');
    const calendarSelect = $('#calendar-select');
    const entriesList = $('#schedule-entries');
    
    // Update hidden calendar ID when selection changes
    calendarSelect.on('change', function() {
        $('#calendar-id').val($(this).val());
        loadScheduleEntries();
    });
    
    // Initialize with first calendar
    if (calendarSelect.val()) {
        $('#calendar-id').val(calendarSelect.val());
        loadScheduleEntries();
    }
    
    // Handle form submission
    scheduleForm.on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'save_schedule_entry');
        formData.append('nonce', healthCalendar.nonce); // Add nonce to formData
        
        $.ajax({
            url: healthCalendar.ajaxurl, // Use localized ajaxurl
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    scheduleForm[0].reset();
                    loadScheduleEntries();
                    alert('Schedule entry saved successfully!');
                } else {
                    alert('Error saving schedule entry: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error saving schedule entry. Please try again.');
            }
        });
    });
    
    function loadScheduleEntries() {
        const calendarId = $('#calendar-id').val();
        if (!calendarId) return;
        
        $.ajax({
            url: healthCalendar.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_schedule_entries',
                calendar_id: calendarId,
                nonce: healthCalendar.nonce
            },
            success: function(response) {
                console.log('Response:', response); // Add this debug line
                if (response.success) {
                    displayScheduleEntries(response.data);
                } else {
                    console.error('Error:', response.data ? response.data.message : 'Unknown error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    }
    
    function displayScheduleEntries(entries) {
        entriesList.empty();
        
        if (!entries || entries.length === 0) {
            entriesList.html('<p>No schedule entries found.</p>');
            return;
        }
        
        const table = $('<table class="wp-list-table widefat fixed striped">');
        table.append(`
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Instructions</th>
                    <th>Actions</th>
                </tr>
            </thead>
        `);
        
        const tbody = $('<tbody>');
        entries.forEach(entry => {
            tbody.append(`
                <tr data-id="${entry.id}">
                    <td>${entry.schedule_date}</td>
                    <td>${entry.schedule_time || 'N/A'}</td>
                    <td>${entry.instructions}</td>
                    <td>
                        <button class="button edit-entry">Edit</button>
                        <button class="button delete-entry">Delete</button>
                    </td>
                </tr>
            `);
        });
        
        table.append(tbody);
        entriesList.append(table);
    }
});