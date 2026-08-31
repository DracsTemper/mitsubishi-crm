(() => {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const notify = (message, type = 'success') => {
        const host = document.querySelector('[data-crm-notifications]');
        if (!host) return;
        const toast = document.createElement('div');
        toast.className = `toast crm-toast crm-toast-${type}`;
        toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        toast.setAttribute('aria-live', type === 'danger' ? 'assertive' : 'polite');
        toast.setAttribute('aria-atomic', 'true');
        const body = document.createElement('div');
        body.className = 'd-flex align-items-start gap-2';
        const icon = document.createElement('i');
        icon.className = `bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'}`;
        const copy = document.createElement('div');
        copy.className = 'flex-grow-1';
        copy.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Close');
        body.append(icon, copy, close);
        toast.append(body);
        host.append(toast);
        const instance = bootstrap.Toast.getOrCreateInstance(toast, { delay: 3600 });
        toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
        instance.show();
    };

    const confirmAction = (form) => new Promise((resolve) => {
        const element = document.querySelector('#crmConfirmModal');
        if (!element) return resolve(true);
        const title = element.querySelector('#crmConfirmTitle');
        const message = element.querySelector('[data-crm-confirm-message]');
        const accept = element.querySelector('[data-crm-confirm-accept]');
        title.textContent = form.dataset.confirmTitle || 'Confirm action';
        message.textContent = form.dataset.confirmMessage || 'This action cannot be undone.';
        accept.textContent = form.dataset.confirmButton || 'Continue';
        accept.className = `btn ${form.dataset.confirmStyle === 'danger' ? 'btn-danger' : 'btn-primary'}`;
        const modal = bootstrap.Modal.getOrCreateInstance(element);
        let decided = false;
        const finish = (choice) => {
            if (decided) return;
            decided = true;
            accept.removeEventListener('click', approve);
            resolve(choice);
        };
        const approve = () => { finish(true); modal.hide(); };
        accept.addEventListener('click', approve);
        element.addEventListener('hidden.bs.modal', () => finish(false), { once: true });
        modal.show();
    });

    const setLoading = (button, loading) => {
        if (!button) return;
        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Processing…</span>';
        } else {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    };

    const clearValidation = (form) => {
        form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
        form.querySelectorAll('[data-ajax-error]').forEach((error) => error.remove());
    };

    const showValidation = (form, errors) => {
        Object.entries(errors || {}).forEach(([name, messages]) => {
            const field = form.querySelector(`[name="${CSS.escape(name)}"]`);
            if (!field) return;
            field.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.dataset.ajaxError = 'true';
            feedback.textContent = messages[0];
            field.insertAdjacentElement('afterend', feedback);
        });
    };

    const errorMessage = (response, payload) => {
        if (response.status === 403) return 'You are not authorized to perform this action.';
        if (response.status === 404) return 'This record no longer exists.';
        if (response.status === 419) return 'Your session expired. Refresh the page and try again.';
        if (response.status >= 500) return 'Something went wrong on the server. Please try again.';
        return payload?.message || 'The action could not be completed.';
    };

    const refreshSection = async (url, selector) => {
        if (!url || !selector) return;
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('The updated section could not be loaded.');
        const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
        const current = document.querySelector(selector);
        const updated = documentCopy.querySelector(selector);
        if (!current || !updated) throw new Error('The updated section was not found.');
        current.replaceWith(updated);
    };

    const showDeletedState = (form, message) => {
        const record = form.closest('[data-crm-record]');
        if (!record) return;
        const state = document.createElement('div');
        state.className = 'panel crm-action-complete';
        const body = document.createElement('div');
        body.className = 'panel-body text-center py-5';
        const icon = document.createElement('div');
        icon.className = 'crm-action-complete-icon';
        icon.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i>';
        const heading = document.createElement('h2');
        heading.className = 'h4 mt-3';
        heading.textContent = message;
        const link = document.createElement('a');
        link.className = 'btn btn-primary mt-3';
        link.href = form.dataset.successUrl || '/dashboard';
        link.textContent = form.dataset.successLabel || 'Back to list';
        body.append(icon, heading, link);
        state.append(body);
        record.replaceWith(state);
    };

    const submit = async (form, submitter) => {
        if (form.dataset.crmSubmitting === 'true') return;
        if (form.dataset.confirmTitle && !await confirmAction(form)) return;
        if (form.dataset.crmSubmitting === 'true') return;
        form.dataset.crmSubmitting = 'true';
        clearValidation(form);
        setLoading(submitter, true);
        const data = new FormData(form);
        const override = data.get('_method');
        const method = String(override || form.method || 'POST').toUpperCase();
        if (override) data.delete('_method');
        try {
            const response = await fetch(form.action, {
                method,
                body: data,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok) {
                if (response.status === 422) showValidation(form, payload?.errors);
                throw new Error(errorMessage(response, payload));
            }
            if (!payload?.success) throw new Error(payload?.message || 'The server returned an unexpected response.');
            const refreshSelector = form.dataset.refreshSelector;
            if (refreshSelector) await refreshSection(payload.data?.refresh_url, refreshSelector);
            if (form.dataset.removeRecord === 'true') showDeletedState(form, payload.message);
            notify(payload.message || 'Action completed successfully.');
        } catch (error) {
            notify(error.message === 'Failed to fetch' ? 'Network error. Check your connection and try again.' : error.message, 'danger');
        } finally {
            if (document.body.contains(form)) delete form.dataset.crmSubmitting;
            if (document.body.contains(submitter)) setLoading(submitter, false);
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-crm-ajax]');
        if (!form) return;
        event.preventDefault();
        submit(form, event.submitter || form.querySelector('[type="submit"]'));
    });

    window.CrmActions = { notify };
})();
