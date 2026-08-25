/**
 * Ruta: /public/assets/js/flava.js
 * Utilidades compartidas de Flava Studio. Sin dependencias externas.
 */

(function (window, document) {
    'use strict';

    const Flava = {
        /** Token CSRF inyectado en el <head> por el layout. */
        csrf() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        },

        baseUrl() {
            const meta = document.querySelector('meta[name="app-url"]');
            return (meta ? meta.content : '').replace(/\/$/, '');
        },

        /**
         * Petición JSON con el formato uniforme del backend.
         * Siempre devuelve {success, message, data|errors}.
         */
        async request(url, options = {}) {
            const config = Object.assign({
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrf()
                },
                credentials: 'same-origin'
            }, options);

            if (config.body && !(config.body instanceof FormData)) {
                config.headers['Content-Type'] = 'application/json';
                config.body = JSON.stringify(config.body);
            }

            try {
                const response = await fetch(url.startsWith('http') ? url : this.baseUrl() + url, config);
                const text = await response.text();
                let payload;

                try {
                    payload = JSON.parse(text);
                } catch (e) {
                    return { success: false, message: 'Respuesta inesperada del servidor.', errors: {} };
                }

                if (response.status === 419) {
                    this.toast('La sesión expiró. Recarga la página.', 'error');
                }

                return payload;
            } catch (error) {
                return { success: false, message: 'Sin conexión. Revisa tu red e inténtalo de nuevo.', errors: {} };
            }
        },

        get(url)         { return this.request(url); },
        post(url, body)  { return this.request(url, { method: 'POST', body }); },

        /** Notificación breve. */
        toast(message, type = 'info', duration = 3800) {
            let zone = document.querySelector('.toast-zone');

            if (!zone) {
                zone = document.createElement('div');
                zone.className = 'toast-zone';
                document.body.appendChild(zone);
            }

            const el = document.createElement('div');
            el.className = 'toast toast-' + type;
            el.setAttribute('role', 'status');
            el.textContent = message;
            zone.appendChild(el);

            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transition = 'opacity .25s ease';
                setTimeout(() => el.remove(), 260);
            }, duration);
        },

        /** Estado de carga de un botón. */
        loading(button, on = true) {
            if (!button) return;

            if (on) {
                button.dataset.label = button.innerHTML;
                button.classList.add('is-loading');
                button.disabled = true;
                button.innerHTML = '<span class="spinner"></span>';
            } else {
                button.classList.remove('is-loading');
                button.disabled = false;
                if (button.dataset.label) button.innerHTML = button.dataset.label;
            }
        },

        /** Diálogo de confirmación para acciones destructivas. */
        confirm(message, title = '¿Confirmas la acción?') {
            return new Promise((resolve) => {
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop';
                backdrop.innerHTML = `
                    <div class="modal" style="max-width:420px" role="dialog" aria-modal="true">
                        <div class="modal-head"><h3></h3></div>
                        <div class="modal-body"><p class="mb-0"></p></div>
                        <div class="modal-foot">
                            <button class="btn btn-ghost btn-sm" data-act="no">Cancelar</button>
                            <button class="btn btn-danger btn-sm" data-act="yes">Confirmar</button>
                        </div>
                    </div>`;

                backdrop.querySelector('h3').textContent = title;
                backdrop.querySelector('p').textContent = message;

                const close = (value) => { backdrop.remove(); resolve(value); };

                backdrop.addEventListener('click', (event) => {
                    if (event.target === backdrop || event.target.dataset.act === 'no') close(false);
                    if (event.target.dataset.act === 'yes') close(true);
                });

                document.addEventListener('keydown', function esc(event) {
                    if (event.key === 'Escape') { document.removeEventListener('keydown', esc); close(false); }
                });

                document.body.appendChild(backdrop);
                backdrop.querySelector('[data-act="yes"]').focus();
            });
        },

        /** Formatea a pesos chilenos: 15000 -> $15.000 */
        money(amount) {
            return '$' + Number(amount || 0).toLocaleString('es-CL', { maximumFractionDigits: 0 });
        },

        /** Formatea y valida RUT chileno mientras se escribe. */
        rut: {
            clean(value) {
                return String(value || '').replace(/[^0-9kK]/g, '').toUpperCase();
            },

            format(value) {
                const clean = this.clean(value);
                if (clean.length < 2) return clean;

                const body = clean.slice(0, -1);
                const dv = clean.slice(-1);

                return body.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
            },

            isValid(value) {
                const clean = this.clean(value);
                if (clean.length < 7 || clean.length > 9) return false;

                const body = clean.slice(0, -1);
                const dv = clean.slice(-1);
                if (!/^\d+$/.test(body)) return false;

                let sum = 0, multiplier = 2;

                for (let i = body.length - 1; i >= 0; i--) {
                    sum += parseInt(body[i], 10) * multiplier;
                    multiplier = multiplier === 7 ? 2 : multiplier + 1;
                }

                const rest = 11 - (sum % 11);
                const expected = rest === 11 ? '0' : rest === 10 ? 'K' : String(rest);

                return expected === dv;
            },

            /** Enlaza un input para formatear y validar en vivo. */
            bind(input) {
                if (!input) return;
                const self = this;

                input.addEventListener('input', function () {
                    const position = this.selectionEnd === this.value.length;
                    this.value = self.format(this.value);
                    if (position) this.setSelectionRange(this.value.length, this.value.length);
                });

                input.addEventListener('blur', function () {
                    if (!this.value) { this.classList.remove('is-invalid'); return; }
                    this.classList.toggle('is-invalid', !self.isValid(this.value));
                });
            }
        },

        /** Copia texto al portapapeles con fallback. */
        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.toast('Copiado al portapapeles', 'success', 1800);
                return true;
            } catch (e) {
                const area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                const ok = document.execCommand('copy');
                area.remove();
                if (ok) this.toast('Copiado al portapapeles', 'success', 1800);
                return ok;
            }
        },

        /** Abre un modal declarado con <div class="modal-backdrop" data-modal="id">. */
        openModal(id) {
            const modal = document.querySelector('[data-modal="' + id + '"]');
            if (!modal) return;

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            modal.querySelector('[data-modal-close]')?.focus();
        },

        closeModal(modal) {
            const target = modal || document.querySelector('.modal-backdrop:not(.hidden)');
            if (!target) return;

            target.classList.add('hidden');
            document.body.style.overflow = '';
        },

        debounce(fn, wait = 300) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), wait);
            };
        }
    };

    // ---- Comportamientos globales por atributo ----
    document.addEventListener('DOMContentLoaded', () => {
        // data-confirm="mensaje" en formularios y enlaces
        document.querySelectorAll('[data-confirm]').forEach((el) => {
            el.addEventListener('click', async (event) => {
                if (el.dataset.confirmed === '1') return;

                event.preventDefault();
                const ok = await Flava.confirm(el.dataset.confirm, el.dataset.confirmTitle || '¿Confirmas la acción?');

                if (ok) {
                    el.dataset.confirmed = '1';
                    if (el.tagName === 'FORM') el.submit();
                    else el.click();
                }
            });
        });

        // data-rut en inputs de RUT
        document.querySelectorAll('[data-rut]').forEach((input) => Flava.rut.bind(input));

        // data-copy="texto"
        document.querySelectorAll('[data-copy]').forEach((el) => {
            el.addEventListener('click', () => Flava.copy(el.dataset.copy));
        });

        // Menú lateral en móvil
        const burger = document.querySelector('[data-sidebar-toggle]');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        if (burger && sidebar) {
            const toggle = () => {
                sidebar.classList.toggle('is-open');
                if (overlay) overlay.classList.toggle('is-open');
            };

            burger.addEventListener('click', toggle);
            if (overlay) overlay.addEventListener('click', toggle);
        }

        // Menú de usuario
        const userToggle = document.querySelector('[data-user-menu]');
        const userDrop = document.querySelector('.user-drop');

        if (userToggle && userDrop) {
            userToggle.addEventListener('click', (event) => {
                event.stopPropagation();
                userDrop.classList.toggle('is-open');
            });

            document.addEventListener('click', () => userDrop.classList.remove('is-open'));
        }

        // Menú público en móvil
        const navToggle = document.querySelector('[data-nav-toggle]');
        const mobileMenu = document.querySelector('.mobile-menu');

        if (navToggle && mobileMenu) {
            navToggle.addEventListener('click', () => mobileMenu.classList.toggle('is-open'));
        }

        // Modales declarativos: data-modal-open="id" / data-modal-close
        document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                Flava.openModal(trigger.dataset.modalOpen);
            });
        });

        document.querySelectorAll('.modal-backdrop[data-modal]').forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.closest('[data-modal-close]')) {
                    Flava.closeModal(modal);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') Flava.closeModal();
        });

        // Autoenvío de filtros al cambiar un select
        document.querySelectorAll('[data-auto-submit]').forEach((el) => {
            el.addEventListener('change', () => el.closest('form')?.submit());
        });

        // Evita el doble envío de formularios
        document.querySelectorAll('form[data-once]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                if (button) setTimeout(() => Flava.loading(button, true), 10);
            });
        });
    });

    window.Flava = Flava;
})(window, document);
