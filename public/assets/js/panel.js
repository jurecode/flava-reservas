/**
 * Ruta: /public/assets/js/panel.js
 * Interacciones de los paneles internos: buscador de clientes, slots de
 * disponibilidad, calendario y editor de horarios.
 * Toda la lógica de negocio vive en el backend; esto sólo agiliza el trabajo.
 */

(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        initCustomerSearch();
        initBookingForm();
        initRescheduleForm();
        initWeekEditor();
        initCalendar();
        initImageFields();
    });

    /** Vista previa local de la imagen elegida, antes de subirla. */
    function initImageFields() {
        document.querySelectorAll('[data-image-field]').forEach((field) => {
            const input = field.querySelector('[data-image-input]');
            const preview = field.querySelector('[data-image-preview]');
            if (!input || !preview) return;

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) return;

                if (file.size > 4 * 1024 * 1024) {
                    window.Flava.toast('La imagen pesa más de 4 MB', 'error');
                    input.value = '';
                    return;
                }

                const url = URL.createObjectURL(file);
                preview.innerHTML = '';

                const img = document.createElement('img');
                img.src = url;
                img.alt = '';
                img.onload = () => URL.revokeObjectURL(url);
                preview.appendChild(img);

                // Al elegir una imagen nueva, quitar deja de tener sentido
                const remove = field.querySelector('input[type="checkbox"][name$="_remove"]');
                if (remove) remove.checked = false;
            });
        });
    }

    /** Buscador de clientes de recepción (spec §90). */
    function initCustomerSearch() {
        const input = document.querySelector('[data-customer-search]');
        const results = document.querySelector('[data-customer-results]');
        if (!input || !results) return;

        const idField = document.querySelector('[data-customer-id]');
        const card = document.querySelector('[data-customer-card]');
        const newBlock = document.querySelector('[data-new-customer]');

        const select = (customer) => {
            if (idField) idField.value = customer.id;
            card?.classList.remove('hidden');
            newBlock?.classList.add('hidden');
            results.classList.remove('is-open');
            input.value = customer.name;

            const nameEl = card?.querySelector('[data-customer-name]');
            const detailEl = card?.querySelector('[data-customer-detail]');
            if (nameEl) nameEl.textContent = customer.name;
            if (detailEl) {
                const parts = [customer.rut, customer.phone, customer.visits + ' visita(s)'].filter(Boolean);
                detailEl.textContent = parts.join(' · ');
            }
        };

        document.querySelector('[data-customer-clear]')?.addEventListener('click', () => {
            if (idField) idField.value = '';
            card?.classList.add('hidden');
            newBlock?.classList.remove('hidden');
            input.value = '';
            input.focus();
        });

        const search = window.Flava.debounce(async () => {
            const term = input.value.trim();

            if (term.length < 2) {
                results.classList.remove('is-open');
                return;
            }

            const response = await window.Flava.get('/api/v1/admin/customers/search?q=' + encodeURIComponent(term));

            if (!response.success || !response.data.length) {
                results.innerHTML = '<div class="search-item"><div class="dt">Sin resultados. Puedes crear el cliente abajo.</div></div>';
                results.classList.add('is-open');
                return;
            }

            results.innerHTML = '';

            response.data.forEach((customer) => {
                const item = document.createElement('div');
                item.className = 'search-item';

                const detail = [customer.rut, customer.phone, customer.visits + ' visita(s)']
                    .filter(Boolean).join(' · ');

                item.innerHTML = '<div class="nm"></div><div class="dt"></div>';
                item.querySelector('.nm').textContent = customer.name;
                item.querySelector('.dt').textContent = detail
                    + (customer.no_shows > 0 ? ' · ' + customer.no_shows + ' no-show' : '');

                item.addEventListener('click', () => select(customer));
                results.appendChild(item);
            });

            results.classList.add('is-open');
        }, 280);

        input.addEventListener('input', search);
        document.addEventListener('click', (event) => {
            if (!results.contains(event.target) && event.target !== input) {
                results.classList.remove('is-open');
            }
        });
    }

    /** Formulario de creación manual: carga los slots reales del barbero. */
    function initBookingForm() {
        const form = document.querySelector('[data-booking-form]');
        if (!form) return;

        const service = form.querySelector('[data-service]');
        const barber = form.querySelector('[data-barber]');
        const date = form.querySelector('[data-date]');
        const zone = form.querySelector('[data-slots]');
        const timeField = form.querySelector('[data-time]');
        const submit = form.querySelector('[data-submit]');
        const hint = form.querySelector('[data-barber-hint]');

        const loadBarbers = async () => {
            if (!service.value) return;

            const response = await window.Flava.get('/api/v1/admin/services/' + service.value + '/barbers');
            if (!response.success) return;

            const current = barber.value;
            barber.innerHTML = '<option value="">Selecciona un barbero</option>';

            response.data.barbers.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name + ' · ' + item.duration + ' min · ' + window.Flava.money(item.price);
                barber.appendChild(option);
            });

            if (current && barber.querySelector('option[value="' + current + '"]')) barber.value = current;

            if (hint) {
                hint.textContent = response.data.barbers.length
                    ? response.data.barbers.length + ' barbero(s) realizan este servicio'
                    : 'Ningún barbero tiene habilitado este servicio.';
                hint.classList.toggle('field-error', !response.data.barbers.length);
            }
        };

        const loadSlots = async () => {
            if (!service.value || !barber.value || !date.value) {
                zone.innerHTML = '<span class="small muted">Elige servicio, barbero y fecha.</span>';
                disable();
                return;
            }

            zone.innerHTML = '<span class="spinner"></span>';

            const query = new URLSearchParams({
                service_id: service.value,
                barber_id: barber.value,
                date: date.value
            });

            const response = await window.Flava.get('/api/v1/admin/availability/slots?' + query.toString());

            if (!response.success) {
                zone.innerHTML = '<span class="field-error">' + response.message + '</span>';
                disable();
                return;
            }

            renderSlots(response.data.slots || []);
        };

        const renderSlots = (slots) => {
            if (!slots.length) {
                zone.innerHTML = '<span class="small muted">Sin horarios libres ese día. Prueba otra fecha o barbero.</span>';
                disable();
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'slot-grid';

            slots.forEach((slot) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'slot';
                button.style.padding = '10px 4px';
                button.style.fontSize = '.9rem';
                button.textContent = slot.time;

                button.addEventListener('click', () => {
                    grid.querySelectorAll('.slot').forEach((s) => s.classList.remove('is-selected'));
                    button.classList.add('is-selected');
                    timeField.value = slot.time;
                    enable();
                });

                grid.appendChild(button);
            });

            zone.innerHTML = '';
            zone.appendChild(grid);
            disable();
        };

        const enable = () => { if (submit) { submit.disabled = false; submit.classList.remove('is-disabled'); } };
        const disable = () => { if (submit) { submit.disabled = true; submit.classList.add('is-disabled'); } if (timeField) timeField.value = ''; };

        service?.addEventListener('change', async () => { await loadBarbers(); loadSlots(); });
        barber?.addEventListener('change', loadSlots);
        date?.addEventListener('change', loadSlots);
    }

    /** Reprogramación desde el detalle de la reserva. */
    function initRescheduleForm() {
        const form = document.querySelector('[data-reschedule]');
        if (!form) return;

        const serviceId = form.dataset.service;
        const bookingId = form.dataset.booking;
        const barber = form.querySelector('[data-rs-barber]');
        const date = form.querySelector('[data-rs-date]');
        const zone = form.querySelector('[data-rs-slots]');
        const timeField = form.querySelector('[data-rs-time]');
        const submit = form.querySelector('[data-rs-submit]');

        const load = async () => {
            if (!barber.value || !date.value) return;

            zone.innerHTML = '<span class="spinner"></span>';

            const query = new URLSearchParams({
                service_id: serviceId,
                barber_id: barber.value,
                date: date.value,
                exclude_booking_id: bookingId
            });

            const response = await window.Flava.get('/api/v1/admin/availability/slots?' + query.toString());

            if (!response.success || !(response.data.slots || []).length) {
                zone.innerHTML = '<span class="small muted">Sin horarios libres ese día.</span>';
                submit.disabled = true;
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'slot-grid';

            response.data.slots.forEach((slot) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'slot';
                button.style.padding = '9px 4px';
                button.style.fontSize = '.86rem';
                button.textContent = slot.time;

                button.addEventListener('click', () => {
                    grid.querySelectorAll('.slot').forEach((s) => s.classList.remove('is-selected'));
                    button.classList.add('is-selected');
                    timeField.value = slot.time;
                    submit.disabled = false;
                });

                grid.appendChild(button);
            });

            zone.innerHTML = '';
            zone.appendChild(grid);
            submit.disabled = true;
        };

        barber?.addEventListener('change', load);
        date?.addEventListener('change', load);
        load();
    }

    /** Editor de horario semanal: agrega y quita bloques. */
    function initWeekEditor() {
        const editor = document.querySelector('[data-week-editor]');
        if (!editor) return;

        const addBlock = (weekday, start = '10:00', end = '20:00') => {
            const container = editor.querySelector('[data-blocks="' + weekday + '"]');
            if (!container) return;

            const index = container.children.length;
            const row = document.createElement('div');
            row.className = 'time-block';
            row.innerHTML =
                '<input class="input" type="time" step="300" name="schedule[' + weekday + '][' + index + '][start_time]" value="' + start + '">' +
                '<span class="muted">a</span>' +
                '<input class="input" type="time" step="300" name="schedule[' + weekday + '][' + index + '][end_time]" value="' + end + '">' +
                '<button type="button" class="btn btn-xs btn-ghost" data-remove-block>Quitar</button>';

            container.appendChild(row);
            container.closest('.day-row')?.classList.remove('is-off');
        };

        editor.addEventListener('click', (event) => {
            const add = event.target.closest('[data-add-block]');
            if (add) { addBlock(add.dataset.addBlock); return; }

            const remove = event.target.closest('[data-remove-block]');
            if (remove) {
                const container = remove.closest('.day-blocks');
                remove.closest('.time-block').remove();
                if (container && !container.children.length) {
                    container.closest('.day-row')?.classList.add('is-off');
                }
            }
        });

        document.querySelectorAll('[data-preset]').forEach((button) => {
            button.addEventListener('click', async () => {
                const ok = await window.Flava.confirm('Se reemplazarán los bloques actuales por el horario sugerido.', 'Aplicar horario');
                if (!ok) return;

                const days = button.dataset.preset === 'fullweek' ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5];

                editor.querySelectorAll('.day-blocks').forEach((c) => { c.innerHTML = ''; });
                editor.querySelectorAll('.day-row').forEach((r) => r.classList.add('is-off'));
                days.forEach((day) => addBlock(day, '10:00', '20:00'));
            });
        });
    }

    /** Calendario administrativo: día y semana, alimentado por la API. */
    function initCalendar() {
        const root = document.querySelector('[data-calendar]');
        if (!root) return;

        let current = root.dataset.date || new Date().toISOString().slice(0, 10);
        let view = 'day';
        let barberId = '';

        const title = document.querySelector('[data-cal-title]');
        const barberSelect = document.querySelector('[data-cal-barber]');

        const startOfWeek = (iso) => {
            const date = new Date(iso + 'T12:00:00');
            const day = (date.getDay() + 6) % 7;
            date.setDate(date.getDate() - day);
            return date.toISOString().slice(0, 10);
        };

        const shift = (iso, days) => {
            const date = new Date(iso + 'T12:00:00');
            date.setDate(date.getDate() + days);
            return date.toISOString().slice(0, 10);
        };

        const label = (iso) => new Date(iso + 'T12:00:00')
            .toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' });

        const render = async () => {
            const from = view === 'week' ? startOfWeek(current) : current;
            const to = view === 'week' ? shift(startOfWeek(current), 6) : current;

            if (title) {
                title.textContent = view === 'week'
                    ? label(from) + ' → ' + label(to)
                    : label(current).charAt(0).toUpperCase() + label(current).slice(1);
            }

            root.innerHTML = '<div class="center" style="padding:50px"><span class="spinner spinner-lg"></span></div>';

            const query = new URLSearchParams({ start: from, end: to });
            if (barberId) query.set('barber_id', barberId);

            const response = await window.Flava.get('/api/v1/admin/calendar/events?' + query.toString());

            if (!response.success) {
                root.innerHTML = '<div class="empty"><p>' + response.message + '</p></div>';
                return;
            }

            renderEvents(response.data || [], from, to);
        };

        const renderEvents = (events, from, to) => {
            const days = {};
            let cursor = from;

            while (cursor <= to) {
                days[cursor] = [];
                cursor = shift(cursor, 1);
            }

            events.forEach((event) => {
                const day = event.start.slice(0, 10);
                if (days[day]) days[day].push(event);
            });

            const wrap = document.createElement('div');
            wrap.style.padding = '14px';
            wrap.style.display = 'grid';
            wrap.style.gap = '14px';
            wrap.style.gridTemplateColumns = Object.keys(days).length > 1
                ? 'repeat(auto-fit, minmax(210px, 1fr))'
                : '1fr';

            Object.keys(days).forEach((day) => {
                const column = document.createElement('div');
                const items = days[day].sort((a, b) => a.start.localeCompare(b.start));

                const head = document.createElement('div');
                head.className = 'label';
                head.style.marginBottom = '8px';
                head.textContent = label(day) + ' · ' + items.length;
                column.appendChild(head);

                if (!items.length) {
                    const empty = document.createElement('p');
                    empty.className = 'small muted';
                    empty.textContent = 'Sin eventos';
                    column.appendChild(empty);
                }

                items.forEach((event) => {
                    const card = document.createElement('div');
                    card.className = 'slot-row';
                    card.style.borderLeftColor = event.backgroundColor;
                    card.style.marginBottom = '7px';
                    card.style.cursor = event.extendedProps.type === 'booking' ? 'pointer' : 'default';

                    const time = event.start.slice(11, 16) + '–' + event.end.slice(11, 16);
                    card.innerHTML =
                        '<div class="slot-time" style="flex-basis:46px;font-size:.86rem"></div>' +
                        '<div class="slot-info"><div class="who" style="font-size:.9rem"></div>' +
                        '<div class="what"></div></div>';

                    card.querySelector('.slot-time').textContent = event.start.slice(11, 16);
                    card.querySelector('.who').textContent = event.title;
                    card.querySelector('.what').textContent = event.extendedProps.type === 'booking'
                        ? event.extendedProps.barber + ' · ' + event.extendedProps.statusLabel + ' · ' + event.extendedProps.total
                        : time;

                    if (event.extendedProps.type === 'booking') {
                        card.addEventListener('click', () => {
                            window.location.href = window.Flava.baseUrl() + '/admin/reservas/' + event.extendedProps.bookingId;
                        });
                    }

                    column.appendChild(card);
                });

                wrap.appendChild(column);
            });

            root.innerHTML = '';
            root.appendChild(wrap);
        };

        document.querySelector('[data-cal-prev]')?.addEventListener('click', () => {
            current = shift(current, view === 'week' ? -7 : -1);
            render();
        });

        document.querySelector('[data-cal-next]')?.addEventListener('click', () => {
            current = shift(current, view === 'week' ? 7 : 1);
            render();
        });

        document.querySelector('[data-cal-today]')?.addEventListener('click', () => {
            current = new Date().toISOString().slice(0, 10);
            render();
        });

        document.querySelectorAll('[data-cal-view]').forEach((button) => {
            button.addEventListener('click', () => {
                view = button.dataset.calView;
                document.querySelectorAll('[data-cal-view]').forEach((b) => {
                    b.classList.toggle('btn-dark', b === button);
                    b.classList.toggle('btn-ghost', b !== button);
                });
                render();
            });
        });

        barberSelect?.addEventListener('change', () => {
            barberId = barberSelect.value;
            render();
        });

        render();
    }
})(window, document);
