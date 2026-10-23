import { Controller } from '@hotwired/stimulus';

// Substitui os <script> inline bloqueados pela CSP (script-src 'self').
export default class extends Controller {
    static values = {
        url: String,
        label: String,
    };

    // botão por lote
    lot(event) {
        event.preventDefault();
        const btn = this.element;
        const label = this.labelValue;
        const idle = `<i class="fa-solid fa-lightbulb fa-fw text-warning"></i> ${label}`;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin fa-fw"></i>';

        this.post(this.urlValue)
            .then((data) => {
                btn.innerHTML = data.dispatched > 0
                    ? `<i class="fa-solid fa-check fa-fw text-success"></i> ${label}`
                    : `<i class="fa-solid fa-ban fa-fw"></i> ${label}`;
            })
            .catch(() => {
                btn.innerHTML = `<i class="fa-solid fa-xmark fa-fw text-danger"></i> ${label}`;
            })
            .finally(() => {
                setTimeout(() => {
                    btn.innerHTML = idle;
                    btn.disabled = false;
                }, 2000);
            });
    }

    // botão da peça inteira (form)
    form(event) {
        event.preventDefault();
        event.stopPropagation();
        const form = this.element;
        const btn = form.querySelector('button');
        const label = btn.dataset.label || '';
        const idle = `<i class="fa-solid fa-lightbulb fa-fw"></i> ${label}`;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin fa-fw"></i>';

        const reset = (from) => {
            setTimeout(() => {
                btn.classList.remove(from);
                btn.classList.add('btn-warning');
                btn.innerHTML = idle;
                btn.disabled = false;
            }, 2000);
        };

        this.post(form.action, new FormData(form))
            .then((data) => {
                const cls = data.dispatched > 0 ? 'btn-success' : 'btn-secondary';
                btn.classList.replace('btn-warning', cls);
                btn.innerHTML = `<i class="fa-solid fa-${data.dispatched > 0 ? 'check' : 'ban'} fa-fw"></i>`;
                reset(cls);
            })
            .catch(() => {
                btn.classList.replace('btn-warning', 'btn-danger');
                btn.innerHTML = '<i class="fa-solid fa-xmark fa-fw"></i>';
                reset('btn-danger');
            });
    }

    post(url, body) {
        return fetch(url, { method: 'POST', body })
            .then((r) => (r.ok ? r.json() : Promise.reject(r.status)));
    }
}
