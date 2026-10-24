{extends file="layout.tpl"}
{block name='head:title'}
    Textes et traductions
{/block}
{block name='stylesheets' append}
    <link rel="stylesheet" href="{$site_url}/{$baseadmin}/templates/css/translation-studio.min.css?v=18">
{/block}
{block name='article'}
    <div class="translation-studio" id="translationStudio" data-preview-base="{$site_url|escape:'html'}" data-admin-path="{$baseadmin|escape:'html'}" data-domain="{$domain|escape:'html'}" data-media-url="{$site_url|escape:'html'}/{$baseadmin|escape:'html'}/templates/js/vendor/elfinder/elfinder.html" data-can-edit="{$user_permissions.edit|default:0}">
        <header class="ts-heading">
            <div class="ts-heading-copy">
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="bi bi-translate me-2" aria-hidden="true"></i>
                    Traductions
                </h1>
            </div>
            <div class="ts-heading-actions">
                <a id="translationModeSwitch" class="btn btn-outline-secondary" href="index.php?controller=Translation&amp;domain={$domain|escape:'url'}&amp;mode=classic">
                    <i class="bi bi-list-ul me-2" aria-hidden="true"></i>
                    Mode classique
                </a>
                <span id="translationSaveStatus" class="ts-save-status" role="status" aria-live="polite">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    Tout est enregistré
                </span>
                <button type="submit" form="edit_translations" class="btn btn-primary ts-save" id="translationSave" disabled>
                    <i class="bi bi-check2 me-2" aria-hidden="true"></i>
                    <span>Enregistrer</span>
                </button>
            </div>
        </header>
        <div class="ts-toolbar">
            <div class="ts-select">
                <label for="domainSelect">Espace</label>
                <form method="get" action="index.php" id="translationContext">
                    <input type="hidden" name="controller" value="Translation">
                    <input type="hidden" name="mode" value="visual">
                    <input type="hidden" name="lang" value="">
                    <select name="domain" id="domainSelect" class="form-select form-select-sm">
                        <option value="theme" {if $domain == 'theme'}selected{/if}>Thème du site</option>
                        {foreach $plugins|default:[] as $plugin}
                            <option value="{$plugin.name|escape:'html'}" {if $domain == $plugin.name}selected{/if}>Plugin : {$plugin.name|escape:'html'}</option>
                        {/foreach}
                    </select>
                </form>
            </div>
            <div class="ts-select">
                <label for="translationLanguage">Langue</label>
                <select id="translationLanguage" class="form-select form-select-sm">
                    {foreach $langs|default:[] as $id => $iso}
                        <option value="{$iso|escape:'html'}">{$iso|upper}</option>
                    {/foreach}
                </select>
            </div>
            <div class="ts-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="filterTranslations">Rechercher un texte</label>
                <input type="search" id="filterTranslations" class="form-control" placeholder="Rechercher un texte…" autocomplete="off">
                <kbd>/</kbd>
            </div>
        </div>
        <div class="ts-workspace">
            <aside class="ts-navigation" aria-label="Rubriques de traduction">
                <div class="ts-nav-label">
                    ZONES DU SITE
                    <button type="button" class="ts-field-help" data-translation-help data-bs-trigger="hover focus" data-bs-content="Cliquez sur une rubrique, ou directement sur un texte dans l’aperçu, pour le modifier." aria-label="Aide sur les rubriques">
                        <i class="bi bi-question-circle" aria-hidden="true"></i>
                    </button>
                </div>
                <nav id="translationSections">
                    {foreach $structure|default:[] as $groupName => $keysArr}
                        <button type="button" class="ts-section" data-group="{$keysArr@iteration}" data-group-name="{$groupName|escape:'html'}" aria-pressed="false">
                            <i class="bi bi-folder2" aria-hidden="true"></i>
                            <span class="ts-section-name">{$groupName|escape:'html'}</span>
                            <span class="ts-section-count">{$keysArr|count}</span>
                            <span class="ts-section-dot" hidden></span>
                        </button>
                    {/foreach}
                </nav>
            </aside>
            <section class="ts-editor" aria-labelledby="translationSectionTitle">
                <div class="ts-editor-heading">
                    <div class="ts-section-symbol">
                        <i class="bi bi-layout-text-window-reverse" id="translationSectionIcon" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 id="translationSectionTitle">
                            Textes du site
                        </h2>
                        <p id="translationSectionMeta">
                        </p>
                    </div>
                </div>
                <div class="ts-editor-filters" role="group" aria-label="Filtrer les textes">
                    <button type="button" class="is-active" data-filter="all" aria-pressed="true">
                        Tous
                    </button>
                    <button type="button" data-filter="changed" aria-pressed="false">
                        Modifiés
                        <span id="translationChangedCount">0</span>
                    </button>
                    <button type="button" data-filter="empty" aria-pressed="false">
                        À compléter
                    </button>
                </div>
                <form id="edit_translations" action="index.php?controller=Translation&action=save" method="post">
                    <input type="hidden" name="hashtoken" value="{$hashtoken|default:''|escape:'html'}">
                    <input type="hidden" name="domain" value="{$domain|escape:'html'}">
                    <input type="hidden" name="catalog_context" value="{$translation_context|escape:'html'}">
                    <input type="hidden" name="catalog_version" value="{$translation_version|escape:'html'}">
                    {foreach $langs|default:[] as $iso}
                        <input type="hidden" name="catalog_languages[{$iso|escape:'html'}]" value="1">
                    {/foreach}
                    <div class="ts-fields" id="translationFields">
                        {foreach $langs|default:[] as $id => $iso}
                            {foreach $structure|default:[] as $groupName => $keysArr}
                                {assign var="groupIndex" value=$keysArr@iteration}
                                {foreach $keysArr as $key}
                                    <article class="ts-field" data-iso="{$iso|escape:'html'}" data-group="{$groupIndex}" data-key="{$key|escape:'html'}" hidden>
                                        <div class="ts-field-heading">
                                            <label class="ts-field-label" for="translation-{$id}-{$groupIndex}-{$key@iteration}">{$key|escape:'html'}</label>
                                            <button type="button" class="ts-field-help" aria-label="Aide sur ce texte" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Ce texte appartient à la rubrique {$groupName|escape:'html'}.">
                                                <i class="bi bi-question-circle" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" class="ts-field-reset" title="Annuler la modification de ce texte" aria-label="Annuler la modification de ce texte" disabled>
                                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <code class="ts-technical-key">{$key|escape:'html'}</code>
                                        <textarea id="translation-{$id}-{$groupIndex}-{$key@iteration}" class="form-control translation-val" name="content[{$iso|escape:'html'}][{$groupName|escape:'html'}][{$key|escape:'html'}]" rows="1" {if !($user_permissions.edit|default:0)}readonly{/if}>{$translations.$iso.$groupName.$key|default:''|escape:'html'}</textarea>
                                        <div class="ts-field-meta">
                                            <span class="ts-field-location">
                                                <i class="bi bi-type" aria-hidden="true"></i>
                                                <span>Texte du site</span>
                                            </span>
                                            <span class="ts-field-dirty" hidden>Modifié</span>
                                        </div>
                                    </article>
                                {/foreach}
                            {/foreach}
                        {/foreach}
                        <div class="ts-empty" id="translationEmpty" hidden>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <h3>
                                Aucun texte trouvé
                            </h3>
                            <p>
                                Essayez un autre mot ou affichez tous les textes.
                            </p>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="translationClearFilter">
                                Réinitialiser les filtres
                            </button>
                        </div>
                    </div>
                    <details class="ts-new-key">
                        <summary>
                            <i class="bi bi-plus-circle me-2" aria-hidden="true"></i>
                            Ajouter un texte personnalisé
                        </summary>
                        <div class="p-3">
                            <label for="translationNewKey" class="form-label">Clé du texte</label>
                            <input type="text" name="new_key" id="translationNewKey" class="form-control mb-3" placeholder="ex. texte_bienvenue" pattern="[A-Za-z_][A-Za-z0-9_]*">
                            {include file="translation/components/group-select.tpl" group_label="Rubrique"}
                            {foreach $langs|default:[] as $id => $iso}
                                <label for="translationNewValue-{$id}" class="form-label">Texte · {$iso|upper}</label>
                                <textarea name="new_value[{$iso|escape:'html'}]" id="translationNewValue-{$id}" class="form-control mb-3" rows="2"></textarea>
                            {/foreach}
                        </div>
                    </details>
                    <input type="hidden" name="catalog_complete" value="1">
                </form>
                <footer class="ts-editor-footer">
                    <button type="button" class="ts-field-help" data-translation-help data-bs-trigger="hover focus" data-bs-content="Les changements restent privés avant enregistrement. Tout annuler restaure les dernières valeurs enregistrées." aria-label="Aide sur l’enregistrement">
                        <i class="bi bi-question-circle" aria-hidden="true"></i>
                    </button>
                    <button type="button" id="translationResetAll" disabled>
                        Tout annuler
                    </button>
                </footer>
            </section>
            <section class="ts-preview" id="translationPreview" aria-labelledby="translationPreviewTitle">
                <header class="ts-preview-heading">
                    <div>
                        <span class="ts-live-dot"></span>
                        <h2 id="translationPreviewTitle">
                            Aperçu en direct
                        </h2>
                        <button type="button" class="ts-field-help" data-translation-help data-bs-trigger="hover focus" data-bs-content="Le vrai site, avec vos textes en cours. Les interactions et les formulaires sont désactivés dans cet aperçu." aria-label="Aide sur l’aperçu">
                            <i class="bi bi-question-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="ts-preview-actions">
                        <button type="button" class="is-active" data-device="mobile" aria-pressed="true" aria-label="Aperçu mobile" title="Mobile">
                            <i class="bi bi-phone" aria-hidden="true"></i>
                        </button>
                        <button type="button" data-device="desktop" aria-pressed="false" aria-label="Aperçu ordinateur" title="Ordinateur">
                            <i class="bi bi-display" aria-hidden="true"></i>
                        </button>
                        <button type="button" id="translationExpand" aria-label="Agrandir l’aperçu" title="Agrandir l’aperçu">
                            <i class="bi bi-arrows-angle-expand" aria-hidden="true"></i>
                        </button>
                    </div>
                </header>
                <div class="ts-preview-address">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <label for="translationPreviewPage" class="visually-hidden">Page d’aperçu</label>
                    <select id="translationPreviewPage">
                        <option value="">Accueil du site</option>
                    </select>
                    <button type="button" id="translationRefresh" title="Recharger l’aperçu" aria-label="Recharger l’aperçu">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="ts-preview-stage">
                    <div class="ts-preview-viewport" id="translationPreviewViewport">
                        <iframe id="translationPreviewFrame" title="Aperçu du site avec les traductions en cours" sandbox="allow-same-origin" referrerpolicy="same-origin">
                        </iframe>
                    </div>
                    <div class="ts-preview-loading" id="translationPreviewLoading">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        Chargement du site…
                    </div>
                </div>
                <div class="ts-preview-caption" id="translationPreviewCaption" role="status" aria-live="polite">
                    <i class="bi bi-cursor" aria-hidden="true"></i>
                    <span>Sélectionnez un texte pour le repérer dans la page.</span>
                </div>
                <div class="ts-preview-sample" id="translationPreviewSample" hidden>
                    <span class="ts-eyebrow">TEXTE SÉLECTIONNÉ</span>
                    <strong>
                    </strong>
                    <p>
                    </p>
                </div>
            </section>
        </div>
    </div>
    <div class="modal fade" id="translationMediaModal" tabindex="-1" aria-labelledby="translationMediaTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="translationMediaTitle">
                        <i class="bi bi-images me-2" aria-hidden="true"></i>
                        Choisir une image — MagixMedia
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer le sélecteur d’images">
                    </button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="translationMediaFrame" title="Médiathèque Magix CMS" class="ts-media-frame" referrerpolicy="same-origin">
                    </iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>
{/block}
{block name='javascripts' append}
    <script src="{$site_url}/{$baseadmin}/templates/js/MagixTranslationGroups.min.js?v=18"></script>
    <script src="{$site_url}/{$baseadmin}/templates/js/MagixTranslation.min.js?v=18"></script>
{/block}
