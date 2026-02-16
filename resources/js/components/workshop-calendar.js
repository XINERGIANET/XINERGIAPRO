import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import listPlugin from "@fullcalendar/list";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";

export function initWorkshopCalendar() {
    const calendarEl = document.getElementById('workshop-calendar');

    if (calendarEl) {
        const eventsUrl = calendarEl.dataset.eventsUrl;

        const calendar = new Calendar(calendarEl, {
            plugins: [
                dayGridPlugin,
                timeGridPlugin,
                listPlugin,
                interactionPlugin
            ],
            initialView: 'dayGridMonth',
            locale: 'es',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            events: eventsUrl + '?t=' + new Date().getTime(),
            eventDisplay: 'block',
            eventClick: function (info) {
                window.dispatchEvent(new CustomEvent('open-edit-appointment-modal', {
                    detail: parseInt(info.event.id)
                }));
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Agenda'
            },
            height: 'auto',
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: false
            },
            firstDay: 1
        });

        calendar.render();
    }
}

export default initWorkshopCalendar;
