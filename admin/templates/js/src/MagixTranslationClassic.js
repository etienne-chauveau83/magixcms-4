/* Magix CMS — interface classique des traductions, composants natifs. */
class MagixTranslationClassic {
    constructor() {
        this.form = document.getElementById('edit_translations');
        if (!this.form) return;
        this.filter = document.getElementById('filterTranslations');
        this.context = document.getElementById('translationContext');
        this.domain = this.context.elements.domain.value;
        this.save = this.form.querySelector('button[type="submit"]');
        this.reset = document.getElementById('translationResetAll');
        this.saving = false;
        this.form.noValidate = true;
        this.groups = new MagixTranslationGroups(this.form, !this.save.disabled);
        const params = new URLSearchParams(window.location.search);
        const language = [...document.querySelectorAll('[data-lang-iso]')].find(
            (button) => button.dataset.langIso === params.get('lang'),
        );
        if (language && window.MagixTabs) MagixTabs.switch(language);
        this.filter.value = params.get('search')?.slice(0, 200) || '';
        this.filter.addEventListener('input', () => this.search());
        this.form.addEventListener('input', () => this.dirty());
        this.form.addEventListener('change', () => this.dirty());
        this.form.addEventListener('submit', (event) => this.submit(event));
        this.form.addEventListener('reset', (event) => {
            if (this.saving) event.preventDefault();
            else
                requestAnimationFrame(() => {
                    this.groups.sync();
                    this.dirty();
                    this.search();
                });
        });
        document.getElementById('translationModeSwitch').addEventListener('click', (event) => {
            if (!this.canNavigate()) {
                event.preventDefault();
                return;
            }
            const url = new URL(event.currentTarget.href);
            url.searchParams.set('lang', this.language());
            if (this.filter.value.trim()) url.searchParams.set('search', this.filter.value.trim());
            event.currentTarget.href = url.href;
        });
        this.context.addEventListener('change', () => {
            if (!this.canNavigate()) {
                this.context.elements.domain.value = this.domain;
                return;
            }
            this.context.elements.lang.value = this.language();
            this.context.submit();
        });
        window.addEventListener('beforeunload', (event) => {
            if (this.changed()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
        document.querySelectorAll('[data-translation-help]').forEach((help) => {
            if (window.bootstrap?.Popover) new bootstrap.Popover(help, { html: false });
        });
        this.search();
        this.dirty();
    }
    language() {
        const active = this.form.querySelector('.tab-pane.active');
        return (
            [...document.querySelectorAll('[data-lang-iso]')].find(
                (button) => button.dataset.langTarget === '#' + active?.id,
            )?.dataset.langIso || ''
        );
    }
    changed() {
        return (
            [
                ...this.form.querySelectorAll(
                    '.translation-val,[name="new_key"],[name^="new_value"]',
                ),
            ].some((input) => input.value !== input.defaultValue) || this.groups.hasDraft()
        );
    }
    dirty() {
        this.reset.disabled = this.saving || !this.changed();
    }
    canNavigate() {
        if (!this.saving && !this.changed()) return true;
        if (typeof MagixToast !== 'undefined')
            MagixToast.error(
                this.saving
                    ? 'L’enregistrement est en cours. Patientez avant de changer de vue.'
                    : 'Enregistrez vos modifications ou utilisez « Tout annuler » avant de changer de vue.',
            );
        return false;
    }
    search() {
        const term = this.filter.value.toLocaleLowerCase('fr').trim();
        this.form.querySelectorAll('.translation-group').forEach((group) => {
            let visible = false;
            group.querySelectorAll('.translation-item').forEach((item) => {
                const text =
                    item.querySelector('.translation-key').textContent +
                    ' ' +
                    item.querySelector('.translation-val').value;
                const match = text.toLocaleLowerCase('fr').includes(term);
                item.hidden = !match;
                visible ||= match;
            });
            group.hidden = !visible;
            const collapse = group.querySelector('.accordion-collapse');
            if (visible && term && collapse && window.bootstrap?.Collapse)
                bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
        });
    }
    async submit(event) {
        event.preventDefault();
        if (this.saving || this.save.disabled) return;
        this.groups.sync();
        if (!this.form.reportValidity()) return;
        const data = new FormData(this.form);
        const controls = [...this.form.elements].filter((input) => input.type !== 'hidden');
        const disabled = controls.map((input) => input.disabled);
        const submitted = new Map(
            [...this.form.querySelectorAll('.translation-val')].map((input) => [
                input,
                input.value,
            ]),
        );
        const label = this.save.innerHTML;
        this.saving = true;
        this.save.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Enregistrement…';
        this.save.setAttribute('aria-busy', 'true');
        controls.forEach((input) => {
            input.disabled = true;
        });
        try {
            const response = await fetch(this.form.action, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('Enregistrement impossible.');
            const result = await response.json();
            if (!result.status) throw new Error(result.message || 'Enregistrement impossible.');
            if (result.catalog_version)
                this.form.elements.catalog_version.value = result.catalog_version;
            submitted.forEach((value, input) => {
                input.defaultValue = value;
            });
            this.form.elements.new_key.value = '';
            this.groups.clearDraft();
            this.form.querySelectorAll('[name^="new_value"]').forEach((input) => {
                input.value = '';
            });
            if (typeof MagixToast !== 'undefined')
                MagixToast.success(result.message || 'Les textes ont été enregistrés.');
            if (String(data.get('new_key') || '').trim()) window.location.reload();
        } catch (error) {
            if (typeof MagixToast !== 'undefined')
                MagixToast.error(error.message || 'Une erreur de communication est survenue.');
        } finally {
            controls.forEach((input, index) => {
                input.disabled = disabled[index];
            });
            this.save.innerHTML = label;
            this.save.removeAttribute('aria-busy');
            this.saving = false;
            this.dirty();
        }
    }
}
document.addEventListener('DOMContentLoaded', () => new MagixTranslationClassic());
