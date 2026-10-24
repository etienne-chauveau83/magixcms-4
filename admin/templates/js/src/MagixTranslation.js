/**
 * Éditeur visuel des traductions Magix CMS en JavaScript natif.
 */
class MagixTranslation {
    constructor() {
        this.root = document.getElementById('translationStudio');
        if (!this.root) return;
        this.form = document.getElementById('edit_translations');
        this.fields = [...this.root.querySelectorAll('.ts-field')];
        this.sections = [...this.root.querySelectorAll('.ts-section')];
        this.language = document.getElementById('translationLanguage');
        this.search = document.getElementById('filterTranslations');
        this.frame = document.getElementById('translationPreviewFrame');
        this.preview = document.getElementById('translationPreview');
        this.stage = this.preview.querySelector('.ts-preview-stage');
        this.page = document.getElementById('translationPreviewPage');
        this.status = document.getElementById('translationSaveStatus');
        this.save = document.getElementById('translationSave');
        this.canEdit = this.root.dataset.canEdit === '1';
        this.form.noValidate = true;
        this.groups = new MagixTranslationGroups(this.form, this.canEdit);
        this.mediaField = null;
        this.mode = 'all';
        this.device = 'mobile';
        this.bindings = new Map();
        this.selected = null;
        this.saving = false;
        this.expanded = false;
        this.groupNames = {
            Général: ['Général', 'bi-sliders'],
            'Header & Mobile Menu (Offcanvas)': ['En-tête et menu', 'bi-layout-text-window'],
            Footer: ['Pied de page', 'bi-layout-text-window-reverse'],
            Cookies: ['Cookies et consentement', 'bi-shield-check'],
            home: ['Accueil', 'bi-house-door'],
            'Catalog Page': ['Catalogue', 'bi-grid'],
            'Catalog Category Page': ['Catégories', 'bi-folder2'],
            'Category Loop (Grid)': ['Cartes des catégories', 'bi-grid-3x3-gap'],
            'Product Detail Page': ['Fiche produit', 'bi-box-seam'],
            'About Page': ['À propos', 'bi-info-circle'],
            'News Root Page': ['Actualités', 'bi-newspaper'],
            'News Single Page': ['Article et événement', 'bi-file-earmark-text'],
            'News Loop (Grid)': ['Cartes des actualités', 'bi-postcard'],
            'News calendar': ['Agenda', 'bi-calendar3'],
            Breadcrumbs: ['Fil d’Ariane', 'bi-signpost-split'],
            'Maintenance Page': ['Maintenance', 'bi-cone-striped'],
            pages: ['Pages de contenu', 'bi-file-earmark-richtext'],
        };
        this.labels = {
            interested_form: 'Invitation à prendre contact',
            header_nav_title: 'Titre du menu',
            header_close_aria: 'Nom du bouton pour les lecteurs d’écran',
            header_close_btn: 'Bouton de fermeture du menu',
            about_label: 'Lien « À propos »',
            legal_label: 'Lien « Mentions légales »',
            contact_label: 'Lien « Contact »',
            about_url: 'Destination du lien « À propos »',
            legal_url: 'Destination des mentions légales',
            contact_url: 'Destination du lien « Contact »',
            footer_logo_welcome: 'Introduction de la présentation',
            footer_logo_description: 'Présentation du site',
            footer_menu_title: 'Titre du menu du pied de page',
            footer_menu_empty: 'Message si le menu est vide',
            footbar_menu_label: 'Bouton du menu mobile',
            footbar_share_label: 'Bouton de partage',
            footbar_share_empty: 'Message sans réseau social',
            footbar_totop_title: 'Aide du bouton de retour en haut',
            footbar_totop_label: 'Bouton de retour en haut',
            powered_by: 'Mention de réalisation',
            website_created: 'Introduction du crédit du site',
            footer_website_title: 'Nom du créateur du site',
            footer_website_url: 'Lien du créateur du site',
            go_to_website: 'Bouton pour visiter le site',
            cookie_page_label: 'Lien vers les cookies',
            banner_text: 'Message de la bannière',
            more_info_label: 'Lien « En savoir plus »',
            cookie_page_url: 'Page d’information sur les cookies',
            btn_refuse: 'Bouton de refus',
            btn_settings: 'Bouton de personnalisation',
            btn_accept: 'Bouton d’acceptation',
            modal_title: 'Titre des préférences',
            tab_analytics: 'Onglet statistiques',
            tab_third_party: 'Onglet des services externes',
            analytics_title: 'Titre des cookies de statistiques',
            analytics_desc: 'Description des statistiques',
            analytics_toggle: 'Activation des statistiques',
            third_party_title: 'Titre des services externes',
            third_party_desc: 'Description des services externes',
            label_fonts: 'Option des polices Google',
            label_videos: 'Option des vidéos',
            label_maps: 'Option des cartes',
            btn_save: 'Bouton de confirmation des préférences',
            news_featured_title: 'Titre des actualités à la une',
            news_view_all_btn: 'Bouton pour voir les actualités',
            footer_news_title: 'Titre des dernières actualités',
            footer_news_empty: 'Message sans actualité',
            catalog_breadcrumb_label: 'Nom du catalogue',
            catalog_categories_title: 'Titre des catégories',
            catalog_products_title: 'Titre des produits',
            catalog_empty_message: 'Message du catalogue vide',
            catalog_category_breadcrumb: 'Lien vers le catalogue',
            catalog_category_refine: 'Titre des filtres',
            catalog_category_products: 'Titre des produits disponibles',
            catalog_category_empty: 'Message de catégorie vide',
            category_loop_empty_title: 'Titre sans catégorie',
            category_loop_empty_text: 'Message sans catégorie',
            product_breadcrumb_catalog: 'Lien vers le catalogue',
            product_ref_title: 'Titre de la référence produit',
            product_ref_label: 'Libellé de la référence',
            product_ean_title: 'Titre du code-barres',
            product_ean_label: 'Libellé du code-barres',
            product_stock_in: 'Disponibilité en stock',
            product_stock_out: 'Disponibilité en rupture',
            product_docs_title: 'Titre des documents',
            about_learn_more_title: 'Bouton pour en savoir plus',
            news_breadcrumb_label: 'Nom des actualités',
            news_filter_tags_all: 'Filtre des mots-clés',
            news_filter_dates_all: 'Filtre des dates',
            news_single_breadcrumb: 'Lien vers les actualités',
            news_single_event_label: 'Libellé des événements',
            news_single_date_from: 'Début de la période',
            news_single_date_to: 'Fin de la période',
            news_single_published_on: 'Libellé de publication',
            news_single_tags_title: 'Titre des mots-clés',
            news_loop_date_from: 'Début de la période',
            news_loop_empty_title: 'Titre sans résultat',
            news_loop_empty_text: 'Message sans résultat',
            news_loop_empty_link: 'Lien de retour aux actualités',
            news_calendar: 'Titre de l’agenda',
            news_view_calendar: 'Bouton vers l’agenda',
            news_back_to_list: 'Retour aux actualités',
            news_calendar_today: 'Bouton « Aujourd’hui »',
            calendar_seo_title: 'Titre de l’agenda dans les moteurs de recherche',
            calendar_seo_desc: 'Description de l’agenda dans les moteurs de recherche',
            breadcrumb_home: 'Lien vers l’accueil',
            maintenance_page_title: 'Titre de l’onglet de maintenance',
            maintenance_site_default: 'Nom du site par défaut',
            maintenance_title: 'Titre de la page de maintenance',
            maintenance_text: 'Message de maintenance',
            maintenance_thanks: 'Message de remerciement',
            pages_learn_more: 'Bouton pour en savoir plus',
        };
        const requestedLanguage = new URLSearchParams(window.location.search).get('lang');
        if ([...this.language.options].some((option) => option.value === requestedLanguage))
            this.language.value = requestedLanguage;
        this.iso = this.language.value;
        this.group = (
            this.sections.find((s) => s.dataset.groupName === 'Footer') || this.sections[0]
        )?.dataset.group;
        this.search.value =
            new URLSearchParams(window.location.search).get('search')?.slice(0, 200) || '';
        this.decorate();
        this.events();
        this.render(true);
        this.openPage();
        this.observer = new ResizeObserver(() => this.resizePreview());
        this.observer.observe(this.stage);
    }
    normalize(value) {
        return String(value).replace(/\s+/g, ' ').trim();
    }
    plain(value) {
        const parsed = new DOMParser().parseFromString(String(value), 'text/html');
        parsed.querySelectorAll('script,style').forEach((n) => n.remove());
        return parsed.body.textContent || '';
    }
    name(key) {
        return Object.hasOwn(this.labels, key)
            ? this.labels[key]
            : key.replace(/[_-]+/g, ' ').replace(/^./, (c) => c.toUpperCase());
    }
    groupMeta(name) {
        return Object.hasOwn(this.groupNames, name) ? this.groupNames[name] : [name, 'bi-folder2'];
    }
    decorate() {
        this.form
            .querySelectorAll('.ts-new-key input, .ts-new-key textarea, .ts-new-key select')
            .forEach((input) => (input.disabled = !this.canEdit));
        this.sections.forEach((section) => {
            const [label, icon] = this.groupMeta(section.dataset.groupName);
            section.querySelector('.ts-section-name').textContent = label;
            section.querySelector('i').className = 'bi ' + icon;
        });
        const priority = ['footer_logo_description', 'footer_logo_welcome', 'footer_menu_title'];
        this.fields
            .sort((a, b) => {
                if (a.dataset.iso !== b.dataset.iso || a.dataset.group !== b.dataset.group)
                    return 0;
                return (
                    (priority.includes(a.dataset.key) ? priority.indexOf(a.dataset.key) - 10 : 0) -
                    (priority.includes(b.dataset.key) ? priority.indexOf(b.dataset.key) - 10 : 0)
                );
            })
            .forEach((field) => {
                document
                    .getElementById('translationFields')
                    .insertBefore(field, document.getElementById('translationEmpty'));
                const key = field.dataset.key;
                this.adaptField(field);
                field.querySelector('label').textContent = this.name(key);
                const help = field.querySelector('.ts-field-help');
                const type = key.endsWith('_aria')
                    ? 'Accessibilité · libellé lu par les lecteurs d’écran'
                    : field.dataset.type === 'image'
                      ? 'Image du site'
                      : field.dataset.type === 'url'
                        ? 'Lien de destination'
                        : key.includes('seo_')
                          ? 'Référencement · moteurs de recherche'
                          : 'Texte visible du site';
                field.querySelector('.ts-field-location span').textContent = type;
                help.dataset.bsContent =
                    key.includes('%s') ||
                    field.querySelector('.translation-val').value.includes('%s')
                        ? 'Conservez %s : le site le remplace automatiquement par la valeur correspondante.'
                        : type +
                          '. Cliquez dans le champ pour le repérer dans l’aperçu. La clé technique reste inchangée.';
                help.setAttribute('aria-label', 'Aide : ' + this.name(key));
                if (window.bootstrap?.Popover) new bootstrap.Popover(help, { html: false });
            });
        this.root.querySelectorAll('[data-translation-help]').forEach((help) => {
            if (window.bootstrap?.Popover)
                new bootstrap.Popover(help, { html: false, trigger: 'hover focus' });
        });
        this.groups.sync();
        try {
            const names = new Intl.DisplayNames(['fr'], { type: 'language' });
            [...this.language.options].forEach((option) => {
                const label = names.of(option.value);
                option.textContent =
                    label.charAt(0).toUpperCase() +
                    label.slice(1) +
                    ' · ' +
                    option.value.toUpperCase();
            });
        } catch (_) {
            /* Les codes de langue restent utilisables. */
        }
    }
    fieldType(key, value) {
        // Une valeur multiligne ne doit jamais être aplatie par un input.
        if (/[\r\n]/.test(value)) return 'longtext';
        const descriptive =
            /(?:^|[_-])(alt|aria|title|titre|caption|description|desc)(?:[_-]|$)/i.test(key);
        const imageKey = /(?:^|[_-])(image|img|photo|picture|thumbnail|logo)(?:[_-]|$)/i.test(key);
        const sourceKey =
            /(?:^|[_-])(url|src|path|file)(?:[_-]|$)/i.test(key) ||
            /(?:^|[_-])(image|img|photo|picture|thumbnail|logo)$/i.test(key);
        const imageValue = /\.(?:avif|webp|png|jpe?g|gif|svg|bmp|ico)(?:[?#].*)?$/i.test(
            value.trim(),
        );
        if (!descriptive && ((imageKey && (sourceKey || imageValue)) || (sourceKey && imageValue)))
            return 'image';
        if (/(?:^|[_-])(url|href|link|lien|src)(?:[_-]|$)/i.test(key)) return 'url';
        if (
            value.length > 120 ||
            /(?:^|[_-])(description|desc|message|intro|body|content)(?:[_-]|$)/i.test(key)
        )
            return 'longtext';
        return 'text';
    }
    resourceUrl(value) {
        const trimmed = value.trim();
        if (!trimmed || /[\u0000-\u001f\u007f\\]/.test(trimmed) || trimmed.startsWith('//'))
            return null;
        try {
            const url = new URL(
                trimmed,
                (this.root.dataset.previewBase || location.origin).replace(/\/?$/, '/'),
            );
            // Une vignette ne doit pas déclencher une requête vers le back-office.
            if (url.origin === location.origin) this.safePage(url.origin + url.pathname);
            return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password
                ? url
                : null;
        } catch (_) {
            return null;
        }
    }
    destinationAllowed(value) {
        const trimmed = value.trim();
        if (!trimmed) return true;
        if (/[\u0000-\u001f\u007f\\]/.test(trimmed) || trimmed.startsWith('//')) return false;
        try {
            const url = new URL(
                trimmed,
                (this.root.dataset.previewBase || location.origin).replace(/\/?$/, '/'),
            );
            return (
                ['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol) &&
                !url.username &&
                !url.password
            );
        } catch (_) {
            return false;
        }
    }
    adaptField(field) {
        const original = field.querySelector('.translation-val');
        const type = this.fieldType(field.dataset.key, original.value);
        field.dataset.type = type;
        if (type !== 'longtext') {
            const input = document.createElement('input');
            input.type = 'text';
            input.id = original.id;
            input.name = original.name;
            input.className = original.className;
            input.defaultValue = original.defaultValue;
            input.value = original.value;
            input.readOnly = original.readOnly;
            if (type === 'url' || type === 'image') {
                input.inputMode = 'url';
                input.spellcheck = false;
                input.autocomplete = 'off';
                input.placeholder =
                    type === 'image' ? '/media/image.jpg' : '/fr/page/ ou https://…';
            }
            original.replaceWith(input);
        }
        if (type === 'image') {
            const box = document.createElement('div');
            box.className = 'ts-image-picker';
            const thumb = document.createElement('div');
            thumb.className = 'ts-image-thumbnail';
            const image = document.createElement('img');
            image.alt = 'Aperçu de l’image choisie';
            image.referrerPolicy = 'no-referrer';
            const placeholder = document.createElement('i');
            placeholder.className = 'bi bi-image';
            placeholder.setAttribute('aria-hidden', 'true');
            thumb.append(image, placeholder);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary btn-sm';
            button.textContent = 'Choisir une image';
            button.disabled = !this.canEdit;
            button.addEventListener('click', () => this.openMedia(field));
            image.addEventListener('error', () => {
                image.hidden = true;
                placeholder.hidden = false;
            });
            box.append(thumb, button);
            field.querySelector('.translation-val').before(box);
        }
        this.updateField(field);
    }
    updateField(field) {
        const input = field.querySelector('.translation-val');
        const type = field.dataset.type;
        input.setCustomValidity(
            (type === 'url' && !this.destinationAllowed(input.value)) ||
                (type === 'image' && input.value.trim() && !this.resourceUrl(input.value))
                ? 'Indiquez un chemin interne ou une adresse autorisée.'
                : '',
        );
        if (type !== 'image') return;
        const image = field.querySelector('.ts-image-thumbnail img');
        const placeholder = field.querySelector('.ts-image-thumbnail i');
        const url = this.resourceUrl(input.value);
        image.hidden = !url;
        placeholder.hidden = Boolean(url);
        if (url) image.src = url.href;
        else image.removeAttribute('src');
    }
    setupMedia() {
        this.mediaModal = document.getElementById('translationMediaModal');
        this.mediaFrame = document.getElementById('translationMediaFrame');
        this.mediaModal.addEventListener('hidden.bs.modal', () => {
            const field = this.mediaField;
            this.mediaField = null;
            this.mediaFrame.removeAttribute('src');
            field?.querySelector('.ts-image-picker button').focus({ preventScroll: true });
        });
        window.addEventListener('message', (event) => {
            if (
                !this.mediaField ||
                !this.canEdit ||
                this.saving ||
                event.origin !== location.origin ||
                event.source !== this.mediaFrame.contentWindow ||
                event.data?.mceAction !== 'insertFile' ||
                typeof event.data.content !== 'string'
            )
                return;
            const value = this.mediaSelection(event.data.content);
            if (!value) {
                if (typeof MagixToast !== 'undefined')
                    MagixToast.error('Sélectionnez un fichier image de la médiathèque.');
                return;
            }
            const input = this.mediaField.querySelector('.translation-val');
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            bootstrap.Modal.getInstance(this.mediaModal)?.hide();
        });
    }
    mediaSelection(value) {
        const url = this.resourceUrl(value.replace(/(\/media\/)a\//i, '$1'));
        const root = new URL(
            (this.root.dataset.previewBase || location.origin).replace(/\/?$/, '/'),
        ).pathname;
        if (
            !url ||
            url.origin !== location.origin ||
            !url.pathname.startsWith(root + 'media/') ||
            !/\.(?:avif|webp|png|jpe?g|gif|svg|bmp|ico)$/i.test(url.pathname)
        )
            return null;
        return url.pathname + url.search;
    }
    openMedia(field) {
        if (!this.canEdit || this.saving || !window.bootstrap?.Modal) return;
        this.select(field);
        this.mediaField = field;
        const url = new URL(this.root.dataset.mediaUrl, location.href);
        if (url.origin !== location.origin) return;
        this.mediaFrame.src = url.href;
        bootstrap.Modal.getOrCreateInstance(this.mediaModal).show();
    }
    events() {
        this.sections.forEach((s) =>
            s.addEventListener('click', () => {
                this.group = s.dataset.group;
                this.selected = null;
                this.render();
            }),
        );
        this.search.addEventListener('input', () => this.render(true));
        this.root.querySelectorAll('[data-filter]').forEach((button) =>
            button.addEventListener('click', () => {
                this.mode = button.dataset.filter;
                this.render(true);
            }),
        );
        this.language.addEventListener('change', () => {
            this.iso = this.language.value;
            this.selected = null;
            this.render(true);
            this.openPage();
        });
        this.fields.forEach((field) => {
            const input = field.querySelector('.translation-val');
            input.addEventListener('focus', () => this.select(field));
            input.addEventListener('input', () => {
                this.resizeInput(input);
                this.updateField(field);
                this.dirty();
                this.apply(field);
                if (this.selected === field) this.describePreview(field);
            });
            field.querySelector('.ts-field-reset').addEventListener('click', () => {
                input.value = input.defaultValue;
                this.resizeInput(input);
                this.updateField(field);
                this.dirty();
                this.apply(field);
                this.select(field);
            });
        });
        this.form.addEventListener('input', () => this.dirty());
        this.form.addEventListener('change', () => this.dirty());
        this.setupMedia();
        this.form.addEventListener('submit', (event) => this.submit(event));
        this.form
            .querySelector('#translationNewGroupName')
            .addEventListener('input', () => this.dirty());
        document.getElementById('translationResetAll').addEventListener('click', () => {
            this.form.reset();
            this.groups.sync();
            this.fields.forEach((f) => {
                this.updateField(f);
                this.apply(f);
                this.resizeInput(f.querySelector('.translation-val'));
            });
            this.dirty();
            this.render();
        });
        document.getElementById('translationClearFilter').addEventListener('click', () => {
            this.search.value = '';
            this.mode = 'all';
            this.render(true);
        });
        document.getElementById('translationModeSwitch').addEventListener('click', (event) => {
            if (!this.canNavigate()) {
                event.preventDefault();
                return;
            }
            const url = new URL(event.currentTarget.href);
            url.searchParams.set('lang', this.iso);
            if (this.search.value.trim()) url.searchParams.set('search', this.search.value.trim());
            event.currentTarget.href = url.href;
        });
        document.getElementById('translationContext').addEventListener('change', (event) => {
            if (!this.canNavigate()) {
                event.target.value = this.root.dataset.domain;
                return;
            }
            event.currentTarget.elements.lang.value = this.iso;
            event.currentTarget.submit();
        });
        this.root.querySelectorAll('[data-device]').forEach((button) =>
            button.addEventListener('click', () => {
                this.device = button.dataset.device;
                this.root.querySelectorAll('[data-device]').forEach((b) => {
                    b.classList.toggle('is-active', b === button);
                    b.setAttribute('aria-pressed', String(b === button));
                });
                this.resizePreview();
                if (this.selected) this.focusPreview(this.selected);
            }),
        );
        document
            .getElementById('translationExpand')
            .addEventListener('click', () => this.expand(!this.expanded));
        document
            .getElementById('translationRefresh')
            .addEventListener('click', () => this.openPage(this.frame.src));
        this.page.addEventListener('change', () => this.openPage(this.page.value));
        this.frame.addEventListener('load', () => this.previewLoaded());
        window.addEventListener('beforeunload', (event) => {
            if (this.changed()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.expanded) {
                event.preventDefault();
                this.expand(false);
            }
            if (
                event.key === '/' &&
                !event.ctrlKey &&
                !event.metaKey &&
                !event.target.matches('input,textarea,select,[contenteditable]')
            ) {
                event.preventDefault();
                this.search.focus();
            }
            if (this.expanded && event.key === 'Tab') {
                const elements = [...this.preview.querySelectorAll('button,select')].filter(
                    (el) => !el.disabled,
                );
                const first = elements[0],
                    last = elements[elements.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                }
                if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
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
    matches(field) {
        const value = field.querySelector('.translation-val');
        const term = this.normalize(this.search.value).toLocaleLowerCase('fr');
        return (
            field.dataset.iso === this.iso &&
            (this.mode !== 'changed' || value.value !== value.defaultValue) &&
            (this.mode !== 'empty' || !value.value.trim()) &&
            (!term ||
                (this.name(field.dataset.key) + ' ' + field.dataset.key + ' ' + value.value)
                    .toLocaleLowerCase('fr')
                    .includes(term))
        );
    }
    render(choose = false) {
        const matched = this.fields.filter((f) => this.matches(f));
        if (choose && !matched.some((f) => f.dataset.group === this.group) && matched.length)
            this.group = matched[0].dataset.group;
        this.sections.forEach((section) => {
            const count = matched.filter((f) => f.dataset.group === section.dataset.group).length;
            section.hidden = count === 0 && Boolean(this.search.value || this.mode !== 'all');
            section.classList.toggle('is-active', section.dataset.group === this.group);
            section.setAttribute('aria-pressed', String(section.dataset.group === this.group));
            section.querySelector('.ts-section-count').textContent = String(count);
        });
        this.fields.forEach((field) => {
            field.hidden = !matched.includes(field) || field.dataset.group !== this.group;
            if (!field.hidden) this.resizeInput(field.querySelector('.translation-val'));
        });
        const section = this.sections.find((s) => s.dataset.group === this.group);
        if (section) {
            document.getElementById('translationSectionTitle').textContent =
                section.querySelector('.ts-section-name').textContent;
            document.getElementById('translationSectionIcon').className =
                section.querySelector('i').className;
        }
        const visible = this.fields.filter((f) => !f.hidden);
        document.getElementById('translationSectionMeta').textContent =
            visible.length +
            ' texte' +
            (visible.length > 1 ? 's' : '') +
            ' · ' +
            this.language.options[this.language.selectedIndex].textContent;
        document.getElementById('translationEmpty').hidden = visible.length > 0;
        this.root.querySelectorAll('[data-filter]').forEach((b) => {
            b.classList.toggle('is-active', b.dataset.filter === this.mode);
            b.setAttribute('aria-pressed', String(b.dataset.filter === this.mode));
        });
        this.dirty();
        if (!this.selected || this.selected.hidden) this.select(visible[0] || null);
    }
    resizeInput(input) {
        if (input.tagName !== 'TEXTAREA') return;
        input.style.height = 'auto';
        input.style.height = Math.min(240, Math.max(96, input.scrollHeight + 2)) + 'px';
    }
    changed() {
        return (
            this.fields.some((f) => {
                const input = f.querySelector('.translation-val');
                return input.value !== input.defaultValue;
            }) ||
            this.groups.hasDraft() ||
            Boolean(
                this.form.elements.new_key.value.trim() ||
                    [...this.form.querySelectorAll('[name^="new_value"]')].some((input) =>
                        input.value.trim(),
                    ),
            )
        );
    }
    dirty() {
        let count = 0;
        this.fields.forEach((field) => {
            const input = field.querySelector('.translation-val'),
                changed = input.value !== input.defaultValue;
            field.classList.toggle('is-dirty', changed);
            field.querySelector('.ts-field-dirty').hidden = !changed;
            field.querySelector('.ts-field-reset').disabled = !changed || !this.canEdit;
            if (changed) count++;
        });
        this.sections.forEach((s) => {
            s.querySelector('.ts-section-dot').hidden = !this.fields.some(
                (f) =>
                    f.dataset.iso === this.iso &&
                    f.dataset.group === s.dataset.group &&
                    f.classList.contains('is-dirty'),
            );
        });
        document.getElementById('translationChangedCount').textContent = String(count);
        const changed = this.changed();
        this.save.disabled = !changed || !this.canEdit || this.saving;
        document.getElementById('translationResetAll').disabled = !changed || this.saving;
        if (!this.saving) {
            this.status.textContent = changed
                ? count
                    ? count +
                      ' texte' +
                      (count > 1 ? 's' : '') +
                      ' modifié' +
                      (count > 1 ? 's' : '')
                    : 'Nouveau texte à enregistrer'
                : 'Tout est enregistré';
            this.status.classList.toggle('is-dirty', changed);
        }
    }
    select(field) {
        this.selected = field;
        this.fields.forEach((f) => f.classList.toggle('is-selected', f === field));
        if (field) {
            this.focusPreview(field);
            this.describePreview(field);
        }
    }
    safePage(value) {
        const base = new URL(this.root.dataset.previewBase || '/', window.location.origin);
        const url = new URL(value || this.iso + '/', base.href.replace(/\/?$/, '/'));
        // Décoder avant comparaison : %61dmin ne doit pas contourner le filtre.
        const path = decodeURIComponent(url.pathname).replace(/\\/g, '/');
        const root = decodeURIComponent(base.pathname).replace(/\/?$/, '/');
        const admin = this.root.dataset.adminPath || 'admin';
        const segments = path.slice(root.length).split('/');
        if (
            url.origin !== window.location.origin ||
            url.username ||
            url.password ||
            url.search ||
            !path.startsWith(root) ||
            /[%\u0000-\u001f\u007f]/.test(path) ||
            segments.some((segment) =>
                ['admin', 'install', admin.toLowerCase(), '.', '..'].includes(
                    segment.toLowerCase(),
                ),
            )
        )
            throw new Error('Page d’aperçu non autorisée.');
        return url;
    }
    openPage(value) {
        try {
            const url = this.safePage(value);
            document.getElementById('translationPreviewLoading').hidden = false;
            this.bindings.clear();
            this.doc = null;
            this.frame.src = url.href;
            this.resizePreview();
        } catch (_) {
            this.caption('Cette page ne peut pas être ouverte dans l’aperçu.');
        }
    }
    resizePreview() {
        const width = this.device === 'mobile' ? 390 : 1100;
        const scale = Math.min(1, this.stage.clientWidth / width);
        this.frame.style.width = width + 'px';
        this.frame.style.height = Math.max(400, this.stage.clientHeight / scale) + 'px';
        this.frame.style.transform = 'scale(' + scale + ')';
        document.getElementById('translationPreviewViewport').style.width =
            Math.min(width, this.stage.clientWidth) + 'px';
    }
    previewLoaded() {
        document.getElementById('translationPreviewLoading').hidden = true;
        try {
            const doc = this.frame.contentDocument;
            if (!doc?.body) throw new Error('Aperçu indisponible');
            this.doc = doc;
            const style = doc.createElement('style');
            style.textContent =
                'html{scroll-behavior:auto!important}.modal[data-translation-opened]{display:block!important;opacity:1!important}.offcanvas[data-translation-opened]{visibility:visible!important;transform:none!important}[data-translation-tab]{display:block!important;opacity:1!important}[data-translation-suppressed]{display:none!important}.magix-preview-text{display:inline!important;margin:0!important;padding:0!important;border:0!important;background:none!important;color:inherit!important;font:inherit!important;letter-spacing:inherit!important}[data-translation-field]{cursor:text!important}[data-translation-preview]{outline:2px solid #00bedb!important;outline-offset:4px!important;border-radius:2px}';
            doc.head.append(style);
            doc.querySelectorAll('a,button,input,select,textarea,[tabindex]').forEach(
                (el) => (el.tabIndex = -1),
            );
            this.frame.tabIndex = -1;
            doc.addEventListener(
                'click',
                (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const target = event.target.closest('[data-translation-field]');
                    if (!target) return;
                    const field = this.fields.find(
                        (f) =>
                            f.querySelector('.translation-val').id ===
                            target.dataset.translationField,
                    );
                    if (field) {
                        if (this.expanded) this.expand(false);
                        this.group = field.dataset.group;
                        this.search.value = '';
                        this.mode = 'all';
                        this.render();
                        this.select(field);
                        field.querySelector('.translation-val').focus({ preventScroll: true });
                        const holder = this.form;
                        holder.scrollTop +=
                            field.getBoundingClientRect().top -
                            holder.getBoundingClientRect().top -
                            12;
                    }
                },
                true,
            );
            doc.addEventListener('submit', (event) => event.preventDefault(), true);
            doc.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && this.expanded) this.expand(false);
            });
            const nodes = [];
            const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, {
                acceptNode: (node) =>
                    node.parentElement?.closest('script,style,noscript,textarea,svg')
                        ? NodeFilter.FILTER_REJECT
                        : node.textContent.trim()
                          ? NodeFilter.FILTER_ACCEPT
                          : NodeFilter.FILTER_REJECT,
            });
            while (walker.nextNode()) nodes.push(walker.currentNode);
            const candidates = this.fields.filter((f) => f.dataset.iso === this.iso);
            const values = new Map();
            candidates.forEach((f) => {
                const value = this.normalize(
                    this.plain(f.querySelector('.translation-val').defaultValue),
                );
                if (value) values.set(value, (values.get(value) || 0) + 1);
            });
            candidates.forEach((field) => {
                const key = field.dataset.key,
                    input = field.querySelector('.translation-val'),
                    original = this.normalize(this.plain(input.defaultValue));
                const explicit = [...doc.querySelectorAll('[data-translation-key]')].filter(
                    (element) => element.dataset.translationKey === key,
                );
                const label = explicit.find((element) => element.dataset.translationLabel)?.dataset
                    .translationLabel;
                if (label) {
                    this.labels[key] = label.slice(0, 120);
                    field.querySelector('label').textContent = this.labels[key];
                    field
                        .querySelector('.ts-field-help')
                        .setAttribute('aria-label', 'Aide : ' + this.labels[key]);
                }
                if (field.dataset.type === 'image') {
                    const originalUrl = this.resourceUrl(input.defaultValue)?.href;
                    const uniqueSource =
                        originalUrl &&
                        candidates.filter(
                            (candidate) =>
                                candidate.dataset.type === 'image' &&
                                this.resourceUrl(
                                    candidate.querySelector('.translation-val').defaultValue,
                                )?.href === originalUrl,
                        ).length === 1;
                    const images = [...doc.querySelectorAll('img')].filter((image) => {
                        if (image.dataset.translationSrc)
                            return image.dataset.translationSrc === key;
                        if (!uniqueSource) return false;
                        try {
                            return (
                                new URL(image.getAttribute('src') || '', doc.baseURI).href ===
                                originalUrl
                            );
                        } catch (_) {
                            return false;
                        }
                    });
                    const targets = images.map((element) => ({
                        element,
                        attribute: 'src',
                        responsive: [
                            element,
                            ...(element.closest('picture')?.querySelectorAll('source') || []),
                        ].map((node) => ({ node, srcset: node.getAttribute('srcset') })),
                    }));
                    if (targets.length) {
                        targets.forEach((binding) => {
                            binding.element.dataset.translationField = input.id;
                        });
                        this.bindings.set(field, targets);
                        this.apply(field);
                    }
                    return;
                }
                if (field.dataset.type === 'url') return;
                let targets = explicit.flatMap((element) => {
                    const attribute = element.dataset.translationAttr;
                    if (attribute)
                        return ['aria-label', 'title', 'alt', 'placeholder'].includes(attribute)
                            ? [{ element, attribute }]
                            : [];
                    if (element.childElementCount) return [];
                    const node = doc.createTextNode(element.textContent);
                    element.replaceChildren(node);
                    return [{ element, node, prefix: '', suffix: '', literal: true }];
                });
                if (!targets.length && (!original || key.endsWith('_url') || key.includes('seo_')))
                    return;
                if (!targets.length) {
                    const scoped = {
                        footer_logo_description: '.widget-footerlogo p',
                        footer_logo_welcome: '.widget-footerlogo p',
                        footer_menu_title: '.widget-footermenu h5',
                    }[key];
                    const raw = this.plain(input.defaultValue);
                    const matching = nodes.filter(
                        (n) =>
                            (this.normalize(n.textContent) === original ||
                                (scoped && n.textContent.includes(raw))) &&
                            (!scoped || n.parentElement.closest(scoped)),
                    );
                    if (scoped || values.get(original) === 1) {
                        if (matching.length === 1 || scoped)
                            targets = matching.map((node) => {
                                const index = node.textContent.indexOf(raw);
                                return {
                                    node,
                                    element: node.parentElement,
                                    prefix:
                                        index >= 0
                                            ? node.textContent.slice(0, index)
                                            : node.textContent.match(/^\s*/)[0],
                                    suffix:
                                        index >= 0
                                            ? node.textContent.slice(index + raw.length)
                                            : node.textContent.match(/\s*$/)[0],
                                };
                            });
                    }
                    if (key.endsWith('_aria'))
                        targets = [...doc.querySelectorAll('[aria-label]')]
                            .filter((el) => el.getAttribute('aria-label') === input.defaultValue)
                            .map((element) => ({ element, attribute: 'aria-label' }));
                }
                if (targets.length) {
                    targets.forEach((binding) => {
                        if (binding.node) {
                            const mark = doc.createElement('span');
                            mark.className = 'magix-preview-text';
                            binding.node.replaceWith(mark);
                            mark.append(binding.node);
                            binding.element = mark;
                        }
                        binding.element.dataset.translationField = input.id;
                    });
                    this.bindings.set(field, targets);
                    this.apply(field);
                }
            });
            const current = this.frame.src;
            this.page.replaceChildren();
            this.addPage(current, 'Page actuelle');
            const seen = new Set([current]);
            [...doc.querySelectorAll('a[href]')].forEach((a) => {
                try {
                    const url = this.safePage(a.href);
                    url.hash = '';
                    if (
                        !seen.has(url.href) &&
                        !url.search &&
                        !/\.[a-z0-9]{2,5}$/i.test(url.pathname) &&
                        a.textContent.trim() &&
                        seen.size < 25
                    ) {
                        seen.add(url.href);
                        this.addPage(url.href, this.normalize(a.textContent).slice(0, 65));
                    }
                } catch (_) {}
            });
            this.page.value = current;
            if (this.selected) {
                this.focusPreview(this.selected);
                this.describePreview(this.selected);
            }
        } catch (_) {
            this.doc = null;
            this.caption(
                'Le site n’est pas disponible dans l’aperçu. Vos textes restent éditables.',
            );
        }
    }
    addPage(value, label) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        this.page.append(option);
    }
    apply(field) {
        const value = field.querySelector('.translation-val').value;
        (this.bindings.get(field) || []).forEach((binding) => {
            if (binding.attribute === 'src') {
                const url = this.resourceUrl(value);
                if (!url) {
                    if (!value.trim()) {
                        binding.element.removeAttribute('src');
                        binding.responsive.forEach(({ node }) => node.removeAttribute('srcset'));
                    }
                    return;
                }
                binding.element.setAttribute('src', url.href);
                binding.responsive.forEach(({ node, srcset }) => {
                    if (
                        value !== field.querySelector('.translation-val').defaultValue ||
                        srcset === null
                    )
                        node.removeAttribute('srcset');
                    else node.setAttribute('srcset', srcset);
                });
            } else if (binding.attribute) binding.element.setAttribute(binding.attribute, value);
            else
                binding.node.textContent =
                    binding.prefix + (binding.literal ? value : this.plain(value)) + binding.suffix;
        });
    }
    focusPreview(field) {
        if (!this.doc) return;
        this.doc
            .querySelectorAll('[data-translation-preview]')
            .forEach((el) => el.removeAttribute('data-translation-preview'));
        ['data-translation-opened', 'data-translation-tab', 'data-translation-suppressed'].forEach(
            (attribute) =>
                this.doc
                    .querySelectorAll('[' + attribute + ']')
                    .forEach((element) => element.removeAttribute(attribute)),
        );
        const bindings = this.bindings.get(field) || [];
        if (!bindings.length) {
            const group = this.sections.find((s) => s.dataset.group === field.dataset.group)
                ?.dataset.groupName;
            if (group === 'Footer') this.scrollFrame(this.doc.querySelector('footer'), false);
            else if (group?.includes('Header')) this.doc.defaultView.scrollTo(0, 0);
            return;
        }
        const element = bindings[0].element;
        const overlay = element.closest('.modal, .offcanvas');
        if (overlay) {
            overlay.setAttribute('data-translation-opened', '');
            const tab = element.closest('.tab-pane');
            if (tab) {
                tab.setAttribute('data-translation-tab', '');
                tab.parentElement.querySelectorAll('.tab-pane').forEach((p) => {
                    if (p !== tab) p.setAttribute('data-translation-suppressed', '');
                });
            }
        }
        bindings.forEach(
            (binding) =>
                (binding.element.dataset.translationPreview =
                    field.querySelector('.translation-val').id),
        );
        this.scrollFrame(element, true);
    }
    scrollFrame(element, center = true) {
        if (element)
            this.doc.defaultView.scrollTo({
                top: Math.max(
                    0,
                    element.getBoundingClientRect().top +
                        this.doc.defaultView.scrollY -
                        (center ? this.doc.defaultView.innerHeight * 0.4 : 0),
                ),
                behavior: 'auto',
            });
    }
    caption(message) {
        const el = document.getElementById('translationPreviewCaption');
        el.querySelector('span').textContent = message;
    }
    describePreview(field) {
        const sample = document.getElementById('translationPreviewSample');
        const targets = this.bindings.get(field) || [];
        const visible = targets.some(
            (b) =>
                b.element.getClientRects().length &&
                this.doc?.defaultView.getComputedStyle(b.element).visibility !== 'hidden',
        );
        sample.hidden = Boolean(visible);
        if (visible)
            this.caption('« ' + this.name(field.dataset.key) + ' » est repéré dans la page.');
        else {
            this.caption(
                'Ce texte n’est pas visible sur cette page. Choisissez une autre page ou utilisez cet aperçu de texte.',
            );
            sample.querySelector('strong').textContent = this.name(field.dataset.key);
            sample.querySelector('p').textContent =
                this.plain(field.querySelector('.translation-val').value) || 'Texte non renseigné';
        }
    }
    expand(open) {
        this.expanded = open;
        this.preview.classList.toggle('is-expanded', open);
        const button = document.getElementById('translationExpand');
        button.setAttribute('aria-label', open ? 'Réduire l’aperçu' : 'Agrandir l’aperçu');
        button.title = open ? 'Réduire l’aperçu' : 'Agrandir l’aperçu';
        button.querySelector('i').className =
            'bi ' + (open ? 'bi-arrows-angle-contract' : 'bi-arrows-angle-expand');
        if (open) {
            this.preview.setAttribute('role', 'dialog');
            this.preview.setAttribute('aria-modal', 'true');
            button.focus();
        } else {
            this.preview.removeAttribute('role');
            this.preview.removeAttribute('aria-modal');
            button.focus();
        }
        this.resizePreview();
        if (this.selected) this.focusPreview(this.selected);
    }
    async submit(event) {
        event.preventDefault();
        if (this.saving || !this.canEdit || !this.changed()) return;
        this.groups.sync();
        const invalid = this.fields.find(
            (field) => !field.querySelector('.translation-val').checkValidity(),
        );
        if (invalid) {
            this.iso = invalid.dataset.iso;
            this.language.value = this.iso;
            this.group = invalid.dataset.group;
            this.search.value = '';
            this.mode = 'all';
            this.render();
            invalid.querySelector('.translation-val').reportValidity();
            return;
        }
        if (
            [...this.form.querySelectorAll('.ts-new-key input,.ts-new-key textarea')].some(
                (input) => !input.checkValidity(),
            )
        )
            this.form.querySelector('.ts-new-key').open = true;
        if (!this.form.reportValidity()) return;
        const data = new FormData(this.form);
        const submitted = new Map(
            this.fields.map((f) => [
                f.querySelector('.translation-val'),
                f.querySelector('.translation-val').value,
            ]),
        );
        const controls = [...this.form.elements].filter((el) => el.type !== 'hidden');
        const disabled = controls.map((el) => el.disabled);
        this.saving = true;
        this.save.disabled = true;
        this.save.setAttribute('aria-busy', 'true');
        this.status.textContent = 'Enregistrement…';
        controls.forEach((el) => (el.disabled = true));
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
            submitted.forEach((value, input) => (input.defaultValue = value));
            if (typeof MagixToast !== 'undefined')
                MagixToast.success('Les textes ont été enregistrés.');
            if (String(data.get('new_key') || '').trim()) {
                this.form.elements.new_key.value = '';
                this.groups.clearDraft();
                this.form
                    .querySelectorAll('[name^="new_value"]')
                    .forEach((input) => (input.value = ''));
                window.location.reload();
                return;
            }
            this.form
                .querySelectorAll('[name^="new_value"]')
                .forEach((input) => (input.value = ''));
            this.openPage(this.frame.src);
        } catch (error) {
            if (typeof MagixToast !== 'undefined')
                MagixToast.error(error.message || 'Une erreur de communication est survenue.');
            this.caption('Les modifications n’ont pas été enregistrées. Vous pouvez réessayer.');
        } finally {
            controls.forEach((el, index) => (el.disabled = disabled[index]));
            this.saving = false;
            this.save.removeAttribute('aria-busy');
            this.dirty();
        }
    }
}
document.addEventListener('DOMContentLoaded', () => new MagixTranslation());
