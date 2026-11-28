@extends('layouts.app')

@section('title', 'Calendar View')

@section('content')
<div class="container-fluid py-0 px-2" style="margin-top: -15px;">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-12">
            <!-- Calendar Card - Raised Higher -->
            <div class="card border-0 shadow-sm school-calendar-card" style="margin-top: 0; border-radius: 8px;">
                <div class="card-header bg-white border-0 pt-1 pb-0 px-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h5 class="fw-bold mb-0" style="font-size: 1rem;">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>School Calendar
                            </h5>
                            <p class="text-muted mb-0" style="font-size: 0.65rem;">View all scheduled activities and holidays</p>
                        </div>
                        <div class="d-flex gap-2 mt-1 mt-sm-0">
                            <a href="{{ route('calendar.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.65rem;">
                                <i class="fas fa-list me-1"></i>List View
                            </a>
                            @if(Auth::user()->role === 'admin')
                                <a href="{{ route('admin.calendar.create') }}" class="btn btn-sm btn-primary py-0 px-2" style="font-size: 0.65rem;">
                                    <i class="fas fa-plus me-1"></i>Add Event
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <!-- Calendar Legend -->
                <div class="bg-light border-top px-3 py-2" style="font-size: 0.75rem;">
                    <div class="d-flex flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge calendar-legend-own" style="width: 20px; height: 20px;"></span>
                            <span>Your Leave</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge calendar-legend-approved" style="width: 20px; height: 20px;"></span>
                            <span>Approved Leave</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge calendar-legend-pending" style="width: 20px; height: 20px;"></span>
                            <span>Pending Leave</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge calendar-legend-activity" style="width: 20px; height: 20px;"></span>
                            <span>Activity</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge calendar-legend-holiday" style="width: 20px; height: 20px;"></span>
                            <span>Holiday</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge calendar-legend-suspension" style="width: 20px; height: 20px;"></span>
                            <span>Suspension</span>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0 pb-1 px-2">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold" id="eventModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="eventModalBody" style="min-height: 200px; max-height: 500px; overflow-y: auto;">
                <!-- Content will be inserted here -->
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                @if(Auth::user()->role === 'admin')
                    <a href="#" id="editEventBtn" class="btn btn-primary d-none">Edit Event</a>
                    <button type="button" id="approveLeaveBtn" class="btn btn-success d-none">Approve Leave</button>
                    <button type="button" id="rejectLeaveBtn" class="btn btn-danger d-none">Reject Leave</button>
                @endif
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    body {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    body.dark-mode .school-calendar-card {
        background: #182233 !important;
        border: 1px solid rgba(167, 139, 250, 0.25) !important;
    }

    body.dark-mode .school-calendar-card .card-header,
    body.dark-mode .school-calendar-card .bg-light {
        background: #182233 !important;
        color: #f8fafc;
    }

    body.dark-mode .school-calendar-card .text-muted {
        color: #cbd5e1 !important;
    }

    body.dark-mode .fc .fc-toolbar-title,
    body.dark-mode .fc .fc-col-header-cell-cushion {
        color: #ede9fe !important;
    }

    body.dark-mode .fc .fc-daygrid-day-frame {
        background: #182233 !important;
    }

    body.dark-mode .fc .fc-daygrid-day,
    body.dark-mode .fc .fc-scrollgrid,
    body.dark-mode .fc .fc-scrollgrid td,
    body.dark-mode .fc .fc-scrollgrid th {
        border-color: #334155 !important;
    }

    body.dark-mode .fc .fc-daygrid-day-number {
        color: #e2e8f0 !important;
    }

    body.dark-mode .fc .fc-day-today {
        background: rgba(112, 72, 165, 0.22) !important;
        box-shadow: inset 0 0 0 2px #a78bfa;
    }

    body.dark-mode .fc .fc-day-today .fc-daygrid-day-number {
        color: #ddd6fe !important;
    }

    body.dark-mode #eventModal .modal-content,
    body.dark-mode #eventModal .modal-header,
    body.dark-mode #eventModal .modal-footer {
        background-color: #4647AE;
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.25);
    }

    body.dark-mode #eventModal .modal-body {
        background-color: #A290B7;
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.25);
    }

    body.dark-mode #eventModal .modal-header.bg-light {
        background-color: #4647AE !important;
    }

    body.dark-mode #eventModal .modal-body .text-muted,
    body.dark-mode #eventModal .modal-body strong {
        color: #ffffff !important;
    }

    body.dark-mode #eventModal .modal-body .text-dark {
        color: #ffffff !important;
    }

    body.dark-mode #eventModal .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }
    
    .container-fluid {
        max-width: 100%;
        margin: 0 auto;
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
    
    /* Calendar Styling */
    .school-calendar-card {
        background: #ffffff;
    }

    .calendar-legend-own { background: #0891b2 !important; }
    .calendar-legend-approved { background: #0891b2 !important; }
    .calendar-legend-pending { background: #f97316 !important; }
    .calendar-legend-activity { background: #7048a5 !important; }
    .calendar-legend-holiday { background: #eab308 !important; }
    .calendar-legend-suspension { background: #dc3545 !important; }

    .fc .school-activity-event,
    .fc .fc-event.school-activity-event,
    .fc .school-activity-event .fc-event-main {
        background-color: #7048a5 !important;
        border-color: #7048a5 !important;
        color: #ffffff !important;
    }

    .fc .school-holiday-event,
    .fc .fc-event.school-holiday-event,
    .fc .school-holiday-event .fc-event-main {
        background-color: #eab308 !important;
        border-color: #eab308 !important;
        color: #4a3800 !important;
    }

    .fc {
        width: 100%;
    }
    
    .fc .fc-toolbar-title {
        font-size: 1.15rem !important;
        font-weight: 800 !important;
        color: #493267;
        letter-spacing: 0;
    }
    
    .fc .fc-button {
        padding: 0.38rem 0.65rem !important;
        font-size: 0.78rem !important;
        border-radius: 6px !important;
        border: 1px solid #7048a5 !important;
        background: #7048a5 !important;
        color: #fff !important;
    }
    .fc .fc-button:hover,
    .fc .fc-button:focus {
        background: #5d398f !important;
        border-color: #5d398f !important;
    }
    .fc .fc-button-active {
        background: #493267 !important;
        border-color: #493267 !important;
        box-shadow: none !important;
    }
    
    .fc .fc-toolbar.fc-header-toolbar {
        margin-bottom: 0.3rem !important;
    }
    
    .fc .fc-col-header-cell-cushion {
        font-size: 0.76rem !important;
        font-weight: 800 !important;
        padding: 0.55rem 0 !important;
        text-decoration: none !important;
        color: #493267;
    }
    
    .fc .fc-daygrid-day-number {
        font-size: 0.78rem !important;
        padding: 0.42rem 0.48rem !important;
        font-weight: 700 !important;
        color: #493267 !important;
    }

    .fc .fc-daygrid-day {
        border-color: #cbd5e1 !important;
    }
    
    .fc .fc-daygrid-day-frame {
        min-height: 78px !important;
        height: auto !important;
        background: linear-gradient(180deg, #ffffff 0%, #fcfbfe 100%);
    }
    
    .fc .fc-daygrid-day-events {
        min-height: 18px !important;
    }
    
    .fc .fc-event-title {
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        line-height: 1.25;
    }
    
    .fc .fc-event {
        min-height: 24px;
        padding: 0.28rem 0.38rem !important;
        margin: 2px 3px !important;
        border-radius: 5px !important;
        cursor: pointer !important;
        pointer-events: auto !important;
        position: relative;
        z-index: 2;
    }
    
    .fc .fc-day-today {
        background: #f3edf9 !important;
        box-shadow: inset 0 0 0 2px #7048a5;
    }

    .fc .fc-day-today .fc-daygrid-day-number {
        color: #7048a5 !important;
        font-size: 0.85rem !important;
        font-weight: 800 !important;
    }
    
    .card {
        border-radius: 8px !important;
        overflow: hidden;
    }
    
    @media (max-width: 768px) {
        .fc .fc-toolbar {
            flex-direction: column;
            gap: 0.3rem;
        }
        .fc .fc-daygrid-day-frame {
            min-height: 50px !important;
        }
        .fc .fc-col-header-cell-cushion {
            font-size: 0.55rem !important;
            padding: 0.2rem 0 !important;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('turbo:load', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            list: 'List'
        },
        displayEventTime: false,
        eventContent: function(info) {
            if (info.view.type === 'dayGridMonth' && info.event.extendedProps.type === 'activity') {
                const title = info.event.title
                    .replace(/^\s*(?:\d{1,2}:\d{2}\s*(?:am|pm)?\s*(?:[-–]\s*\d{1,2}:\d{2}\s*(?:am|pm)?\s*)?)/i, '')
                    .trim();
                const container = document.createElement('div');
                container.className = 'fc-event-title';
                container.textContent = title || info.event.title;
                return { domNodes: [container] };
            }
            return true;
        },
        events: [
            @foreach($events as $event)
            {
                id: @json((string) $event->id),
                title: @json($event->title),
                start: @json($event->start_time ? $event->event_date->format('Y-m-d') . 'T' . $event->start_time : $event->event_date->format('Y-m-d')),
                end: @json($event->end_time ? $event->event_date->format('Y-m-d') . 'T' . $event->end_time : null),
                allDay: @json(!$event->start_time),
                backgroundColor: @json($event->event_type === 'activity' ? '#7048a5' : ($event->event_type === 'suspension' ? '#dc3545' : '#eab308')),
                borderColor: @json($event->event_type === 'activity' ? '#7048a5' : ($event->event_type === 'suspension' ? '#dc3545' : '#eab308')),
                textColor: @json($event->event_type === 'holiday' ? '#4a3800' : '#ffffff'),
                classNames: @json($event->event_type === 'activity' ? ['school-activity-event'] : ($event->event_type === 'holiday' ? ['school-holiday-event'] : ($event->event_type === 'suspension' ? ['school-suspension-event'] : []))),
                extendedProps: {
                    type: @json($event->event_type),
                    description: @json($event->description),
                    branch: @json($event->branch ? $event->branch->branch_name : 'All Branches'),
                    creator: @json(optional($event->creator)->name ?? 'Philippine Holiday Calendar')
                }
            },
            @endforeach
            @if(isset($leaveRequests))
                @foreach($leaveRequests as $leave)
                {
                    id: @json('leave-' . $leave->id),
                    title: @json('Leave: ' . (optional($leave->employeeProfile)->full_name ?? optional($leave->employee)->name ?? 'Employee')),
                    start: @json($leave->start_date->format('Y-m-d')),
                    end: @json($leave->end_date->copy()->addDay()->format('Y-m-d')),
                    backgroundColor: @json($leave->status === 'pending' ? '#f97316' : '#0891b2'),
                    borderColor: @json($leave->status === 'pending' ? '#f97316' : '#0891b2'),
                    textColor: '#ffffff',
                    extendedProps: {
                        type: 'leave',
                        leaveId: @json((string) $leave->id),
                        status: @json($leave->status),
                        employee: @json(optional($leave->employeeProfile)->full_name ?? optional($leave->employee)->name ?? 'Employee'),
                        leaveType: @json(ucfirst($leave->leave_type)),
                        reason: @json($leave->reason),
                        branch: @json(optional($leave->employeeProfile->branch)->branch_name ?? 'Unknown Branch'),
                        isOwnLeave: {{ ($currentUserProfileId && $leave->employee_profile_id === $currentUserProfileId) ? 'true' : 'false' }}
                    }
                },
                @endforeach
            @endif
        ],
        eventDidMount: function(info) {
            if (info.event.extendedProps.type === 'activity') {
                info.el.style.setProperty('background-color', '#7048a5', 'important');
                info.el.style.setProperty('border-color', '#7048a5', 'important');
                info.el.style.setProperty('color', '#ffffff', 'important');
                info.el.classList.add('school-activity-event');
            }
            if (info.event.extendedProps.type === 'holiday') {
                info.el.style.setProperty('background-color', '#eab308', 'important');
                info.el.style.setProperty('border-color', '#eab308', 'important');
                info.el.style.setProperty('color', '#4a3800', 'important');
            }
            if (info.event.extendedProps.type === 'suspension') {
                info.el.style.setProperty('background-color', '#dc3545', 'important');
                info.el.style.setProperty('border-color', '#dc3545', 'important');
                info.el.style.setProperty('color', '#ffffff', 'important');
            }
        },
        eventClick: function(info) {
            const event = info.event;
            const props = event.extendedProps;
            
            // Set modal title
            const modalTitle = document.getElementById('eventModalLabel');
            if (modalTitle) {
                modalTitle.textContent = event.title;
            }

            let detailHtml = '';

            if (props.type === 'leave') {
                const endDate = event.end ? new Date(event.end) : null;
                const displayEndDate = endDate ? new Date(endDate.getTime() - 86400000).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) : 'N/A';
                
                detailHtml = `
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Employee</strong>
                                <span class="badge p-2" style="background:#0891b2 !important;color:#ffffff !important">
                                    ${props.employee}${props.isOwnLeave ? ' (Your Leave)' : ''}
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Leave Type</strong>
                                <span class="badge bg-secondary p-2">${props.leaveType}</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Status</strong>
                                <span class="badge p-2" style="background:${props.status === 'pending' ? '#f97316' : '#0891b2'} !important;color:#ffffff !important">${props.status.charAt(0).toUpperCase() + props.status.slice(1)}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Branch</strong>
                                <span class="text-dark">${props.branch}</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Start Date</strong>
                                <span class="text-dark">${new Date(event.start).toLocaleDateString('en-US', {
                                    weekday: 'short',
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric'
                                })}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">End Date</strong>
                                <span class="text-dark">${displayEndDate}</span>
                            </div>
                        </div>
                    </div>

                    ${props.reason ? `
                        <div class="mb-3">
                            <strong class="d-block text-muted mb-2" style="font-size: 0.85rem;">Reason</strong>
                            <p class="text-dark border-start ps-3 mb-0">${props.reason}</p>
                        </div>
                    ` : `
                        <div class="mb-3">
                            <strong class="d-block text-muted mb-2" style="font-size: 0.85rem;">Reason</strong>
                            <p class="text-muted mb-0">No reason provided</p>
                        </div>
                    `}
                `;
            } else {
                const timeRange = !event.allDay && event.start
                    ? `${event.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}${event.end ? ` - ${event.end.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}` : ''}`
                    : null;
                detailHtml = `
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Type</strong>
                                <span class="badge bg-${props.type === 'activity' ? 'primary' : (props.type === 'suspension' ? 'danger' : 'warning')} p-2">${props.type.charAt(0).toUpperCase() + props.type.slice(1)}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Branch</strong>
                                <span class="text-dark">${props.branch}</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="mb-3">
                                <strong class="d-block text-muted" style="font-size: 0.85rem;">Date</strong>
                                <span class="text-dark">${new Date(event.start).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}</span>
                            </div>
                        </div>
                    </div>

                    ${timeRange ? `
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="mb-3">
                                    <strong class="d-block text-muted" style="font-size: 0.85rem;">Time</strong>
                                    <span class="text-dark"><i class="far fa-clock me-1"></i>${timeRange}</span>
                                </div>
                            </div>
                        </div>
                    ` : ''}

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="mb-3">
                                <strong class="d-block text-muted mb-2" style="font-size: 0.85rem;">Created by</strong>
                                <span class="text-dark">${props.creator}</span>
                            </div>
                        </div>
                    </div>

                    ${props.description ? `
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="mb-3">
                                    <strong class="d-block text-muted mb-2" style="font-size: 0.85rem;">Description</strong>
                                    <p class="text-dark border-start ps-3 mb-0">${props.description}</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                `;
            }

            const bodyElement = document.getElementById('eventModalBody');
            if (bodyElement) {
                bodyElement.innerHTML = detailHtml;
            }

            const editBtn = document.getElementById('editEventBtn');
            const approveBtn = document.getElementById('approveLeaveBtn');
            const rejectBtn = document.getElementById('rejectLeaveBtn');

            if (props.type === 'leave') {
                if (editBtn) editBtn.classList.add('d-none');
                if (approveBtn) approveBtn.classList.remove('d-none');
                if (rejectBtn) rejectBtn.classList.remove('d-none');

                if (props.status === 'approved') {
                    if (approveBtn) approveBtn.classList.add('d-none');
                    if (rejectBtn) rejectBtn.classList.add('d-none');
                }

                if (approveBtn) {
                    approveBtn.onclick = function() {
                        processLeaveAction('approve', props.leaveId, event);
                    };
                }

                if (rejectBtn) {
                    rejectBtn.onclick = function() {
                        processLeaveAction('reject', props.leaveId, event);
                    };
                }
            } else {
                if (approveBtn) approveBtn.classList.add('d-none');
                if (rejectBtn) rejectBtn.classList.add('d-none');
                if (editBtn) editBtn.classList.remove('d-none');

                if (editBtn) {
                    @if(Auth::user()->role === 'admin')
                    editBtn.href = '{{ url("/admin/calendar") }}/' + event.id + '/edit';
                    @endif
                }
            }

            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        },
        height: 'auto',
        contentHeight: 'auto',
        dayMaxEventRows: true,
        weekends: true
    });

    function processLeaveAction(action, leaveId, event) {
        const url = '{{ url('leave') }}' + '/' + action + '/' + leaveId;
        fetch(url, { method: 'GET' })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Action failed');
                }
                if (action === 'reject') {
                    event.remove();
                } else {
                    event.setProp('backgroundColor', '#198754');
                    event.setProp('borderColor', '#198754');
                }
                const modalEl = document.getElementById('eventModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                modalInstance.hide();
                window.location.reload();
            })
            .catch(() => {
                window.showAppToast('Unable to process the leave action. Please refresh and try again.', 'error');
            });
    }

    calendar.render();
});
</script>
@endpush
@endsection