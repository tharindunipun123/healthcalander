jQuery(document).ready(function($) {
    const scheduleForm = $('#schedule-entry-form');
    const yearlyThemeForm = $('#yearly-theme-form');
    const calendarSelect = $('#calendar-select');
    const entriesList = $('#schedule-entries');
    let mediaUploader = null;

    // Calendar selection handling
    calendarSelect.on('change', function() {
        const calendarId = $(this).val();
        $('.calendar-id-input').val(calendarId);
        loadScheduleEntries();
        loadYearlyTheme();
    });

    // Initialize with first calendar
    if (calendarSelect.val()) {
        $('.calendar-id-input').val(calendarSelect.val());
        loadScheduleEntries();
        loadYearlyTheme();
    }

    // Entry type dependent fields toggling
    $('#entry-type').on('change', function() {
        const type = $(this).val();
        $('.holiday-options').toggle(type === 'holiday');
        $('.message-options').toggle(['special_day', 'biweekly'].includes(type));
    });

    // Theme image upload handling
    $('.upload-theme-image').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Choose Theme Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#theme-image-id').val(attachment.id);
            $('.theme-image-preview').html(`<img src="${attachment.url}" style="max-width: 200px;">`);
        });

        mediaUploader.open();
    });

    // Schedule Entry Form Submission
    scheduleForm.on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_schedule_entry');
        formData.append('nonce', healthCalendar.nonce);

        $.ajax({
            url: healthCalendar.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    scheduleForm[0].reset();
                    loadScheduleEntries();
                    showNotice('Schedule entry saved successfully!', 'success');
                } else {
                    showNotice(response.data.message || 'Unknown error', 'error');
                }
            },
            error: handleAjaxError
        });
    });

    // Yearly Theme Form Submission
    yearlyThemeForm.on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_yearly_theme');
        formData.append('nonce', healthCalendar.nonce);

        $.ajax({
            url: healthCalendar.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice('Yearly theme saved successfully!', 'success');
                } else {
                    showNotice(response.data.message || 'Unknown error', 'error');
                }
            },
            error: handleAjaxError
        });
    });

    // Entry filtering
    $('#entry-type-filter, #date-filter').on('change', loadScheduleEntries);

    function loadScheduleEntries() {
        const calendarId = $('#calendar-id').val();
        const entryType = $('#entry-type-filter').val();
        const dateFilter = $('#date-filter').val();

        if (!calendarId) return;

        $.ajax({
            url: healthCalendar.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_schedule_entries',
                calendar_id: calendarId,
                entry_type: entryType,
                date_filter: dateFilter,
                nonce: healthCalendar.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayScheduleEntries(response.data);
                } else {
                    showNotice(response.data.message || 'Error loading entries', 'error');
                }
            },
            error: handleAjaxError
        });
    }

    function loadYearlyTheme() {
        const calendarId = $('#calendar-id').val();
        const year = $('#theme-year').val();

        $.ajax({
            url: healthCalendar.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_yearly_theme',
                calendar_id: calendarId,
                year: year,
                nonce: healthCalendar.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    $('#theme-title').val(response.data.title);
                    $('#theme-image-id').val(response.data.image_id);
                    if (response.data.image_url) {
                        $('.theme-image-preview').html(`<img src="${response.data.image_url}" style="max-width: 200px;">`);
                    }
                }
            },
            error: handleAjaxError
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
                    <th>Type</th>
                    <th>Instructions/Message</th>
                    <th>Actions</th>
                </tr>
            </thead>
        `);
        
        const tbody = $('<tbody>');
        entries.forEach(entry => {
            tbody.append(`
                <tr data-id="${entry.id}" class="entry-type-${entry.entry_type}">
                    <td>${entry.schedule_date}</td>
                    <td>${entry.schedule_time || 'N/A'}</td>
                    <td>${formatEntryType(entry.entry_type)}</td>
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

    function formatEntryType(type) {
        const types = {
            regular: 'Regular Entry',
            holiday: 'Holiday',
            special_day: 'Special Day',
            biweekly: 'Bi-weekly Message'
        };
        return types[type] || type;
    }

    function showNotice(message, type) {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
            </div>
        `);
        $('.wrap > h1').after(notice);
        setTimeout(() => notice.fadeOut(), 3000);
    }

    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error:', error);
        showNotice('An error occurred. Please try again.', 'error');
    }
});