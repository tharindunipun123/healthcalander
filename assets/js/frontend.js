jQuery(document).ready(function($) {
    class HealthCalendar {
        constructor(container) {
            this.container = $(container);
            this.view = this.container.data('view');
            this.calendarId = this.container.data('calendar-id');
            
            console.log('Calendar initialized with:', {
                view: this.view,
                calendarId: this.calendarId,
                container: this.container
            });
        
            if (!this.calendarId) {
                console.error('No calendar ID provided!');
                return;
            }
        
            this.currentDate = new Date();
            this.selectedDate = null;
            this.scheduleData = {};
        
            this.initializeCalendar();
            this.bindEvents();
            this.loadMonthSchedule();
        }

        initializeCalendar() {
            this.updateCalendarHeader();
            this.renderCalendarGrid();
        }

        loadMonthSchedule() {
            const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
            const lastDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
        
            console.log('Loading schedule for:', {
                calendarId: this.calendarId,
                startDate: firstDay.toISOString().split('T')[0],
                endDate: lastDay.toISOString().split('T')[0]
            });
        
            $.ajax({
                url: healthCalendar.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_schedule_details',
                    calendar_id: this.calendarId,
                    start_date: firstDay.toISOString().split('T')[0],
                    end_date: lastDay.toISOString().split('T')[0],
                    nonce: healthCalendar.nonce
                },
                success: (response) => {
                    console.log('Server response:', response);
                    if (response.success) {
                        this.scheduleData = this.groupScheduleByDate(response.data);
                        console.log('Grouped schedule data:', this.scheduleData);
                        this.renderCalendarGrid();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    console.log('Server response:', xhr.responseText);
                }
            });
        }

        groupScheduleByDate(entries) {
            const grouped = {};
            if (Array.isArray(entries)) {
                entries.forEach(entry => {
                    if (!grouped[entry.schedule_date]) {
                        grouped[entry.schedule_date] = [];
                    }
                    grouped[entry.schedule_date].push(entry);
                });
            }
            return grouped;
        }

        updateCalendarHeader() {
            const periodText = this.getDisplayPeriod();
            this.container.find('.current-period').text(periodText);
        }

        getDisplayPeriod() {
            const options = { year: 'numeric', month: 'long' };
            switch(this.view) {
                case 'week':
                    const weekStart = this.getWeekStart(this.currentDate);
                    const weekEnd = new Date(weekStart);
                    weekEnd.setDate(weekEnd.getDate() + 6);
                    return `${weekStart.toLocaleDateString()} - ${weekEnd.toLocaleDateString()}`;
                case 'day':
                    return this.currentDate.toLocaleDateString(undefined, { ...options, day: 'numeric' });
                default: // month
                    return this.currentDate.toLocaleDateString(undefined, options);
            }
        }

        renderCalendarGrid() {
            const gridContainer = this.container.find('.calendar-body');
            gridContainer.empty();

            switch(this.view) {
                case 'month':
                    this.renderMonthGrid(gridContainer);
                    break;
                case 'week':
                    this.renderWeekGrid(gridContainer);
                    break;
                case 'day':
                    this.renderDayGrid(gridContainer);
                    break;
            }
        }

        renderMonthGrid(container) {
            const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
            const lastDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);

            let currentDate = this.getWeekStart(firstDay);

            while (currentDate <= lastDay) {
                const week = $('<div class="calendar-week">');

                for (let i = 0; i < 7; i++) {
                    const isCurrentMonth = currentDate.getMonth() === this.currentDate.getMonth();
                    const dateCell = this.createDateCell(currentDate, isCurrentMonth);
                    week.append(dateCell);

                    currentDate = new Date(currentDate);
                    currentDate.setDate(currentDate.getDate() + 1);
                }

                container.append(week);
            }
        }

        createDateCell(date, isCurrentMonth) {
            const dateStr = date.toISOString().split('T')[0];
            const hasEntries = this.hasScheduleEntries(date);

            const cell = $('<div>', {
                class: `calendar-date${isCurrentMonth ? '' : ' other-month'}${hasEntries ? ' has-entries' : ''}`,
                'data-date': dateStr
            });

            cell.append($('<span>', {
                class: 'date-number',
                text: date.getDate()
            }));

            if (hasEntries) {
                const tooltip = this.createTooltip(this.scheduleData[dateStr]);
                cell.append(tooltip);

                cell.append($('<span>', {
                    class: 'schedule-indicator'
                }));
            }

            return cell;
        }

        createTooltip(entries) {
            const tooltip = $('<div class="schedule-tooltip">');
            const list = $('<div class="tooltip-entries">');

            entries.forEach(entry => {
                const time = entry.schedule_time ? 
                    new Date('1970-01-01T' + entry.schedule_time).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : 'All Day';

                list.append(`
                    <div class="tooltip-entry">
                        <div class="entry-time">${time}</div>
                        <div class="entry-instructions">${entry.instructions}</div>
                    </div>
                `);
            });

            tooltip.append(list);
            return tooltip;
        }

        hasScheduleEntries(date) {
            const dateStr = date.toISOString().split('T')[0];
            return !!this.scheduleData[dateStr];
        }

        bindEvents() {
            this.container.on('click', '.prev-period', () => this.navigatePeriod(-1));
            this.container.on('click', '.next-period', () => this.navigatePeriod(1));
            this.container.on('click', '.calendar-date', (e) => this.handleDateClick(e));
        }

        navigatePeriod(direction) {
            switch(this.view) {
                case 'month':
                    this.currentDate.setMonth(this.currentDate.getMonth() + direction);
                    break;
                case 'week':
                    this.currentDate.setDate(this.currentDate.getDate() + (direction * 7));
                    break;
                case 'day':
                    this.currentDate.setDate(this.currentDate.getDate() + direction);
                    break;
            }

            this.updateCalendarHeader();
            this.loadMonthSchedule();
        }

        handleDateClick(e) {
            const dateCell = $(e.currentTarget);
            const date = dateCell.data('date');

            this.container.find('.calendar-date').removeClass('selected');
            dateCell.addClass('selected');

            this.displayScheduleDetails(date);
        }

        displayScheduleDetails(date) {
            const entries = this.scheduleData[date] || [];
            const detailsContainer = this.container.find('.schedule-details');
            detailsContainer.empty();

            if (entries.length === 0) {
                detailsContainer.html('<p class="no-entries">No schedule entries for this date.</p>');
                return;
            }

            const list = $('<div class="schedule-entries">');
            entries.forEach(entry => {
                const time = entry.schedule_time ? 
                    new Date('1970-01-01T' + entry.schedule_time).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : 'All Day';

                list.append(`
                    <div class="schedule-entry">
                        <div class="entry-time">${time}</div>
                        <div class="entry-instructions">${entry.instructions}</div>
                    </div>
                `);
            });

            detailsContainer.append(list);
        }

        getWeekStart(date) {
            const d = new Date(date);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            return new Date(d.setDate(diff));
        }
    }

    // Initialize calendars
    $('.health-calendar-view').each(function() {
        new HealthCalendar(this);
    });
});