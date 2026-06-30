import './bootstrap';

import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

window.Alpine = Alpine;
Alpine.start();

const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/*
|--------------------------------------------------------------------------
| Modal form system (AJAX) — tạo/sửa mọi module dùng chung 1 modal
|--------------------------------------------------------------------------
| - Trigger: phần tử có [data-modal-form="<url>"] (+ optional [data-modal-title]).
| - GET url → trả HTML form (partial) → inject vào modal → Alpine.initTree.
| - Submit → POST (Accept: application/json):
|     422 → vẽ lỗi cạnh field; 2xx → điều hướng theo {redirect}.
*/
const Modal = {
    el: null,
    body: null,
    titleEl: null,
    panel: null,

    init() {
        this.el = document.getElementById('app-modal');
        if (!this.el) return;
        this.body = this.el.querySelector('[data-modal-body]');
        this.titleEl = this.el.querySelector('[data-modal-title]');
        this.panel = this.el.querySelector('[data-modal-panel]');

        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal-form]');
            if (trigger) {
                e.preventDefault();
                this.open(trigger.getAttribute('data-modal-form'), trigger.getAttribute('data-modal-title') || '');
                return;
            }
            if (e.target.closest('[data-modal-close]')) {
                e.preventDefault();
                this.close();
            }
        });

        this.el.querySelector('[data-modal-backdrop]')?.addEventListener('click', () => this.close());
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.el.classList.contains('hidden')) this.close();
        });

        this.body.addEventListener('submit', (e) => {
            const form = e.target.closest('form');
            if (!form) return;
            e.preventDefault();
            this.submit(form);
        });
    },

    show() {
        this.el.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        this.panel?.classList.remove('animate-fade-in-up');
        void this.panel?.offsetWidth;
        this.panel?.classList.add('animate-fade-in-up');
    },

    close() {
        this.el.classList.add('hidden');
        this.body.innerHTML = '';
        document.body.classList.remove('overflow-hidden');
    },

    async open(url, title) {
        this.show();
        this.titleEl.textContent = title;
        this.body.innerHTML = '<div class="py-12 text-center text-sm text-gray-400">Đang tải…</div>';
        try {
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            });
            if (!res.ok) throw new Error('load failed');
            this.body.innerHTML = await res.text();
            window.Alpine.initTree(this.body);
            this.body.querySelector('input, select, textarea')?.focus();
        } catch (err) {
            this.body.innerHTML =
                '<div class="py-12 text-center text-sm text-red-600">Không tải được biểu mẫu. Vui lòng thử lại.</div>';
        }
    },

    async submit(form) {
        const btn = form.querySelector('[type="submit"]');
        if (btn) btn.disabled = true;
        this.clearErrors(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: new FormData(form),
            });

            if (res.status === 422) {
                const data = await res.json();
                this.paintErrors(form, data.errors || {});
                if (btn) btn.disabled = false;
                return;
            }
            // Phiên hết hạn (401) hoặc CSRF token đã cũ (419) — xảy ra khi server
            // khởi động lại, DB/bảng sessions bị làm mới, hoặc để form mở quá lâu.
            // Token trong trang đã cũ nên bấm Lưu lại cũng vô ích → báo rõ và tải
            // lại trang để lấy token mới (đăng nhập lại nếu cần).
            if (res.status === 419 || res.status === 401) {
                alert('Phiên làm việc đã hết hạn. Trang sẽ được tải lại, vui lòng đăng nhập lại nếu cần rồi thử lại.');
                window.location.reload();
                return;
            }
            if (res.ok) {
                const data = await res.json().catch(() => ({}));
                window.location = data.redirect || window.location.href;
                return;
            }
            throw new Error(`submit failed (HTTP ${res.status})`);
        } catch (err) {
            if (btn) btn.disabled = false;
            console.error('[modal submit]', err);
            alert('Có lỗi xảy ra. Vui lòng thử lại.');
        }
    },

    clearErrors(form) {
        form.querySelectorAll('.js-field-error').forEach((el) => el.remove());
        form.querySelectorAll('[data-error-input]').forEach((el) => {
            el.classList.remove('border-red-300', 'text-red-900');
            el.removeAttribute('data-error-input');
        });
    },

    paintErrors(form, errors) {
        let first = null;
        Object.entries(errors).forEach(([name, messages]) => {
            const field =
                form.querySelector(`[name="${name}"]`) || form.querySelector(`[name="${name}[]"]`);
            if (!field) return;
            field.classList.add('border-red-300');
            field.setAttribute('data-error-input', '1');
            const p = document.createElement('p');
            p.className = 'mt-1 text-xs text-red-600 js-field-error';
            p.textContent = Array.isArray(messages) ? messages[0] : messages;
            (field.closest('.mb-4') || field.parentNode).appendChild(p);
            if (!first) first = field;
        });
        first?.focus();
        first?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    },
};

/*
|--------------------------------------------------------------------------
| Kanban drag & drop (SortableJS) — kéo thẻ task giữa các cột để đổi status
|--------------------------------------------------------------------------
*/
function initKanban() {
    const columns = document.querySelectorAll('[data-kanban-column]');
    if (!columns.length) return;

    const refreshCounts = () => {
        columns.forEach((col) => {
            const status = col.getAttribute('data-kanban-column');
            const count = col.querySelectorAll('[data-task-id]').length;
            const badge = document.querySelector(`[data-kanban-count="${status}"]`);
            if (badge) badge.textContent = count;
            const empty = col.querySelector('[data-kanban-empty]');
            if (empty) empty.classList.toggle('hidden', count > 0);
        });
    };

    columns.forEach((col) => {
        new Sortable(col, {
            group: 'kanban',
            animation: 150,
            draggable: '[data-task-id]',
            filter: '[data-kanban-empty]',
            ghostClass: 'kanban-ghost',
            chosenClass: 'kanban-chosen',
            dragClass: 'kanban-drag',
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 3,
            swapThreshold: 0.65,
            onEnd: async (evt) => {
                const card = evt.item;
                const toCol = evt.to.closest('[data-kanban-column]');
                const fromCol = evt.from.closest('[data-kanban-column]');
                refreshCounts();
                if (!toCol || toCol === fromCol) return;

                const url = card.getAttribute('data-status-url');
                const status = toCol.getAttribute('data-kanban-column');

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf(),
                        },
                        body: JSON.stringify({ status }),
                    });
                    if (!res.ok) throw new Error('status update failed');
                } catch (err) {
                    // Revert về cột cũ nếu lưu thất bại (vd thiếu quyền).
                    const ref = evt.from.children[evt.oldIndex] || null;
                    evt.from.insertBefore(card, ref);
                    refreshCounts();
                    alert('Không cập nhật được trạng thái (có thể bạn không có quyền).');
                }
            },
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    Modal.init();
    initKanban();
});
