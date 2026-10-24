/**
 * Gère le groupe et la validation d'une nouvelle variable de traduction.
 * Composant partagé par les modes classique et visuel, en JavaScript natif.
 */
class MagixTranslationGroups {
    constructor(form, canEdit) {
        this.form = form;
        this.canEdit = canEdit;
        this.select = form.querySelector('#translationGroupSelect');
        this.custom = form.querySelector('#translationNewGroupName');
        this.container = form.querySelector('#translationNewGroupFields');
        this.group = form.querySelector('[name="new_group"]');
        this.key = form.querySelector('[name="new_key"]');
        this.values = [...form.querySelectorAll('[name^="new_value"]')];
        this.initialGroup = this.select.value;
        this.select.addEventListener('change', () => this.sync());
        form.addEventListener('input', () => this.sync());
        this.sync();
    }

    sync() {
        const create = this.select.value === '';
        const hasValue = this.values.some((input) => input.value.trim());
        const hasName = create && Boolean(this.custom.value.trim());

        this.container.hidden = !create;
        this.custom.disabled = !this.canEdit || !create;
        this.key.required = hasValue || hasName;
        this.custom.required = create && Boolean(this.key.value.trim());
        this.custom.setCustomValidity(
            create &&
                (/[\r\n\[\]]/.test(this.custom.value) ||
                    this.custom.value.trim() === 'Fichier généré par Magix CMS')
                ? 'Choisissez un nom de groupe sans crochets et différent de l’en-tête du catalogue.'
                : '',
        );
        this.group.value = this.select.value || this.custom.value.trim() || 'Général';

        this.values.forEach((input, index) => {
            input.setCustomValidity(
                index === 0 && this.key.value.trim() && !hasValue
                    ? 'Renseignez au moins une traduction pour créer cette variable.'
                    : '',
            );
        });
    }

    hasDraft() {
        return this.select.value === '' && Boolean(this.custom.value.trim());
    }

    clearDraft() {
        this.custom.value = '';
        this.select.value = this.initialGroup;
        this.sync();
    }
}
