/**
 * Ruta: /public/assets/js/booking.js
 * Interacción del flujo de reserva público. La validación real vive en el
 * backend: esto sólo hace la experiencia más rápida y clara.
 */

(function (window, document) {
    'use strict';

    /** Íconos SVG en línea: el JS también construye interfaz, sin emoji. */
    const ICONS = {
        calendar: '<path d="M16 2v4M8 2v4M3 10h18"/><rect x="3" y="4" width="18" height="17" rx="2"/>',
        alert: '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01"/>'
    };

    function svg(paths, size = 22) {
        return '<svg class="ico" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" '
            + 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
    }

    function emptyState(iconPaths, title, hint) {
        const wrap = document.createElement('div');
        wrap.className = 'empty';
        wrap.innerHTML = '<span class="ico-box ico-box-lg">' + svg(iconPaths) + '</span>'
            + '<p class="bold mb-1"></p><p class="small muted"></p>';
        wrap.querySelectorAll('p')[0].textContent = title;
        wrap.querySelectorAll('p')[1].textContent = hint;
        return wrap.outerHTML;
    }

    document.addEventListener('DOMContentLoaded', () => {
        initPicker('[data-pick]', '[data-continue]');
        initDateTime();
        initCheckout();
    });

    /** Paso 1 y 2: selección de servicio / barbero. */
    function initPicker(pickSelector, continueSelector) {
        const picks = document.querySelectorAll(pickSelector);
        const next = document.querySelector(continueSelector);

        if (!picks.length) return;

        picks.forEach((pick) => {
            pick.addEventListener('click', () => {
                picks.forEach((p) => p.classList.remove('is-selected'));
                pick.classList.add('is-selected');

                const input = document.querySelector('[data-pick-value]');
                if (input) input.value = pick.dataset.value;

                if (next) {
                    next.classList.remove('is-disabled');
                    next.disabled = false;
                }

                // Con un toque basta: avanzamos solos (spec §67, alta conversión).
                if (pick.dataset.autoAdvance === '1' && next) {
                    setTimeout(() => next.click(), 170);
                }
            });
        });
    }

    /** Pasos 3 y 4: fecha y hora. */
    function initDateTime() {
        const strip = document.querySelector('[data-date-strip]');
        const slotZone = document.querySelector('[data-slots]');

        if (!strip || !slotZone) return;

        const serviceId = strip.dataset.service;
        const barberId = strip.dataset.barber;
        const dateInput = document.querySelector('[data-selected-date]');
        const timeInput = document.querySelector('[data-selected-time]');
        const submit = document.querySelector('[data-continue]');
        const summary = document.querySelector('[data-summary]');

        strip.querySelectorAll('.date-chip').forEach((chip) => {
            chip.addEventListener('click', async () => {
                strip.querySelectorAll('.date-chip').forEach((c) => c.classList.remove('is-selected'));
                chip.classList.add('is-selected');

                if (dateInput) dateInput.value = chip.dataset.date;
                if (timeInput) timeInput.value = '';

                disableContinue();
                await loadSlots(chip.dataset.date);
            });
        });

        async function loadSlots(date) {
            slotZone.innerHTML = '<div class="center" style="padding:32px"><span class="spinner spinner-lg"></span></div>';

            const query = new URLSearchParams({ service_id: serviceId, date });
            if (barberId && barberId !== 'any') query.set('barber_id', barberId);

            const result = await window.Flava.get('/api/v1/availability/slots?' + query.toString());

            if (!result.success) {
                slotZone.innerHTML = emptyState(ICONS.alert, 'No pudimos cargar los horarios', result.message);
                return;
            }

            renderSlots(result.data.slots || []);
        }

        function renderSlots(slots) {
            if (!slots.length) {
                slotZone.innerHTML = emptyState(ICONS.calendar, 'No quedan horarios ese día', 'Prueba con otra fecha del selector.');
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'slot-grid';

            slots.forEach((slot) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'slot';
                button.textContent = slot.time;
                button.dataset.time = slot.time;
                if (slot.barber_id) button.dataset.barber = slot.barber_id;

                button.addEventListener('click', () => {
                    grid.querySelectorAll('.slot').forEach((s) => s.classList.remove('is-selected'));
                    button.classList.add('is-selected');

                    if (timeInput) timeInput.value = slot.time;

                    const resolved = document.querySelector('[data-resolved-barber]');
                    if (resolved && slot.barber_id) resolved.value = slot.barber_id;

                    enableContinue(slot);
                });

                grid.appendChild(button);
            });

            slotZone.innerHTML = '';
            slotZone.appendChild(grid);
        }

        function enableContinue(slot) {
            if (!submit) return;

            submit.disabled = false;
            submit.classList.remove('is-disabled');

            if (summary && dateInput) {
                summary.querySelector('strong').textContent = slot.time + ' hrs';
                summary.querySelector('span').textContent = formatDate(dateInput.value);
            }
        }

        function disableContinue() {
            if (!submit) return;
            submit.disabled = true;
            submit.classList.add('is-disabled');
        }

        function formatDate(value) {
            if (!value) return '';
            const [y, m, d] = value.split('-').map(Number);
            const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            return d + ' ' + months[m - 1];
        }

        // Carga los horarios de la fecha preseleccionada
        const preselected = strip.querySelector('.date-chip.is-selected');
        if (preselected && !slotZone.dataset.prerendered) loadSlots(preselected.dataset.date);
    }

    /** Checkout: métodos de pago y validación previa amable. */
    function initCheckout() {
        const form = document.querySelector('[data-checkout-form]');
        if (!form) return;

        form.querySelectorAll('.pay-option').forEach((option) => {
            option.addEventListener('click', () => {
                form.querySelectorAll('.pay-option').forEach((o) => o.classList.remove('is-selected'));
                option.classList.add('is-selected');
                option.querySelector('input').checked = true;
            });
        });

        form.addEventListener('submit', (event) => {
            const rutInput = form.querySelector('[data-rut]');

            if (rutInput && rutInput.required && !window.Flava.rut.isValid(rutInput.value)) {
                event.preventDefault();
                rutInput.classList.add('is-invalid');
                rutInput.focus();
                window.Flava.toast('Revisa el RUT ingresado', 'error');
                return;
            }

            const button = form.querySelector('[type="submit"]');
            if (button) setTimeout(() => window.Flava.loading(button, true), 10);
        });
    }
})(window, document);
