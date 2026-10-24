/**
 * Système de notifications (Toasts) global pour MagixCMS 4
 * Requiert : Bootstrap 5 JS
 */
class MagixToast {

    // Raccourcis pratiques
    static success(message, options = {}) { this.show(message, 'success', options); }
    static error(message, options = {})   { this.show(message, 'error', options); }
    static warning(message, options = {}) { this.show(message, 'warning', options); }
    static info(message, options = {})    { this.show(message, 'info', options); }

    /**
     * Conserve la mise en forme courante sans insérer le HTML reçu dans la page.
     * Les nœuds autorisés sont recréés ; les attributs actifs sont ignorés.
     */
    static formatMessage(message) {
        const template = document.createElement('template');
        template.innerHTML = String(message ?? '');

        const allowedTags = new Set([
            'a', 'b', 'br', 'code', 'del', 'div', 'em', 'i', 'ins', 'li',
            'mark', 'ol', 'p', 'pre', 's', 'small', 'span', 'strong', 'sub',
            'sup', 'u', 'ul'
        ]);
        const blockedTags = new Set([
            'script', 'style', 'template', 'iframe', 'object', 'embed',
            'svg', 'math', 'noscript'
        ]);
        const allowedClasses = /^(?:bi(?:-[a-z0-9-]+)?|fw-(?:bold|bolder|semibold|normal|light|lighter)|fst-(?:italic|normal)|text-(?:primary|secondary|success|danger|warning|info|light|dark|body|muted|white|decoration-(?:underline|line-through|none))|m[se]-[0-3])$/;

        const appendNodes = (source, target) => {
            for (const node of source.childNodes) {
                if (node.nodeType === Node.TEXT_NODE) {
                    target.appendChild(document.createTextNode(node.textContent));
                    continue;
                }
                if (node.nodeType !== Node.ELEMENT_NODE) continue;

                const tag = node.localName;
                if (node.namespaceURI !== 'http://www.w3.org/1999/xhtml' || blockedTags.has(tag)) continue;
                if (!allowedTags.has(tag)) {
                    appendNodes(node, target);
                    continue;
                }

                const element = document.createElement(tag);
                const classes = Array.from(node.classList).filter(name => allowedClasses.test(name));
                if (classes.length) element.className = classes.join(' ');

                if (tag === 'a') {
                    const href = node.getAttribute('href');
                    if (href) {
                        try {
                            const url = new URL(href, document.baseURI);
                            if (['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol)) {
                                element.setAttribute('href', url.href);
                            }
                        } catch (error) {
                            // Un lien invalide reste affiché comme du texte.
                        }
                    }
                    if (node.hasAttribute('title')) element.setAttribute('title', node.getAttribute('title'));
                    if (node.getAttribute('target') === '_blank') {
                        element.setAttribute('target', '_blank');
                        element.setAttribute('rel', 'noopener noreferrer');
                    }
                }

                appendNodes(node, element);
                target.appendChild(element);
            }
        };

        const fragment = document.createDocumentFragment();
        appendNodes(template.content, fragment);
        return fragment;
    }

    // Méthode principale
    static show(message, type = 'info', options = {}) {
        const container = document.getElementById('magix-toast-container');
        if (!container) {
            console.warn("MagixToast: Le conteneur #magix-toast-container est introuvable.");
            return;
        }

        // Configuration du design selon le type
        const config = {
            'success': { bg: 'text-bg-success', icon: 'bi-check-circle' },
            'error':   { bg: 'text-bg-danger',  icon: 'bi-exclamation-octagon' },
            'warning': { bg: 'text-bg-warning', icon: 'bi-exclamation-triangle' },
            'info':    { bg: 'text-bg-info',    icon: 'bi-info-circle' }
        };

        const style = Object.prototype.hasOwnProperty.call(config, type) ? config[type] : config['info'];

        // 1. Création de l'élément DOM du Toast
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center border-0 mb-3 shadow-lg ${style.bg}`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        // 2. Structure interne du Toast (Bootstrap 5)
        // Note: btn-close-white est ajouté sauf pour warning/info qui ont un texte noir par défaut dans BS5
        const closeBtnClass = (type === 'success' || type === 'error') ? 'btn-close-white' : '';

        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i class="bi ${style.icon} fs-5 me-3"></i>
                    <span class="fw-medium"></span>
                </div>
                <button type="button" class="btn-close ${closeBtnClass} me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
            </div>
        `;

        // html: false affiche les données saisies telles quelles, y compris les chevrons.
        const content = toastEl.querySelector('.fw-medium');
        if (options?.html === false) {
            content.textContent = String(message ?? '');
        } else {
            content.appendChild(this.formatMessage(message));
        }

        // 3. Injection dans la page
        container.appendChild(toastEl);

        // 4. Initialisation avec le moteur JS de Bootstrap
        // delay: 4000 = disparaît après 4 secondes
        const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
        bsToast.show();

        // 5. Autodestruction (Nettoyage du HTML quand l'animation de fermeture est finie)
        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    }
}
