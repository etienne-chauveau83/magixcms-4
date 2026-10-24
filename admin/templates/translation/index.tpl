{extends file="layout.tpl"}

{block name='head:title'}Gestion des traductions{/block}

{block name='article'}
    {* 1. EN-TÊTE DE LA PAGE *}
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-translate me-2"></i> Traductions
        </h1>

        {* Sélecteur de domaine *}
        <div class="d-flex flex-wrap align-items-center gap-3">
            <a id="translationModeSwitch" class="btn btn-outline-primary" href="index.php?controller=Translation&amp;domain={$domain|escape:'url'}&amp;mode=visual"><i class="bi bi-window me-2" aria-hidden="true"></i>Aperçu visuel</a>
            <form method="GET" action="index.php" id="translationContext" class="d-flex align-items-center">
                <input type="hidden" name="controller" value="Translation">
                <input type="hidden" name="mode" value="classic">
                <input type="hidden" name="lang" value="">
                <label for="domainSelect" class="me-2 small fw-bold text-muted text-nowrap">Contexte :</label>
                <select name="domain" id="domainSelect" class="form-select form-select-sm border-primary-subtle shadow-sm">
                    <option value="theme" {if $domain == 'theme'}selected{/if}>Thème Actif</option>
                    {if isset($plugins) && $plugins|count > 0}
                        <optgroup label="Plugins">
                            {foreach $plugins as $plugin}
                                <option value="{$plugin.name|escape:'html'}" {if $domain == $plugin.name}selected{/if}>Plugin : {$plugin.name|escape:'html'}</option>
                            {/foreach}
                        </optgroup>
                    {/if}
                </select>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        {* 2. HEADER DE LA CARTE AVEC LE DROPDOWN DE LANGUE *}
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom-0">
            <h6 class="m-0 fw-bold text-primary">Fichiers .conf ({$domain|escape:'html'}) <button type="button" class="btn btn-link p-0 ms-2" data-translation-help data-bs-trigger="hover focus" data-bs-content="Vous éditez les traductions de {$domain_label|escape:'html'}. Dans les modèles du site, utilisez {ldelim}#ma_cle#{rdelim}." aria-label="Aide sur les traductions"><i class="bi bi-question-circle" aria-hidden="true"></i></button></h6>

            {if isset($langs)}
                {include file="components/dropdown-lang.tpl"}
            {/if}
        </div>

        <div class="card-body bg-light">

            {* 3. BARRE DE RECHERCHE RAPIDE *}
            <div class="mb-4">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="filterTranslations" class="form-control border-start-0" placeholder="Rechercher une clé ou un texte traduit (ex: footer, bienvenue)...">
                </div>
            </div>

            <form id="edit_translations" action="index.php?controller=Translation&action=save" method="post">
                <input type="hidden" name="hashtoken" value="{$hashtoken|default:''|escape:'html'}">
                <input type="hidden" name="domain" value="{$domain|escape:'html'}">
                <input type="hidden" name="catalog_context" value="{$translation_context|escape:'html'}">
                <input type="hidden" name="catalog_version" value="{$translation_version|escape:'html'}">
                {foreach $langs|default:[] as $iso}
                    <input type="hidden" name="catalog_languages[{$iso|escape:'html'}]" value="1">
                {/foreach}

                <div class="tab-content">
                    {if isset($langs)}
                        {foreach $langs as $id => $iso}
                            <fieldset role="tabpanel" class="tab-pane {if $iso@first}show active{/if}" id="lang-{$id}">

                                {if isset($structure) && $structure|count > 0}
                                    {* LES ACCORDÉONS BOOTSTRAP 5 *}
                                    <div class="accordion shadow-sm" id="accordionLang{$iso}">

                                        {foreach $structure as $groupName => $keysArr}
                                            {* Index de boucle : identifiant stable sans caractères du nom de groupe. *}
                                            {$cleanGroupId = $keysArr@iteration}

                                            {* Ajout de la classe "translation-group" pour le JS *}
                                            <div class="accordion-item border-0 mb-2 rounded overflow-hidden translation-group">
                                                <h2 class="accordion-header" id="heading-{$iso}-{$cleanGroupId}">
                                                    <button class="accordion-button {if !$keysArr@first}collapsed{/if} bg-white fw-bold text-primary border-bottom" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{$iso}-{$cleanGroupId}">
                                                        <i class="bi bi-folder2-open me-2"></i> {$groupName|escape:'html'}
                                                        <span class="badge bg-secondary ms-auto rounded-pill">{$keysArr|count} clés</span>
                                                    </button>
                                                </h2>

                                                <div id="collapse-{$iso}-{$cleanGroupId}" class="accordion-collapse collapse {if $keysArr@first}show{/if}" data-bs-parent="#accordionLang{$iso}">
                                                    <div class="accordion-body bg-light">
                                                        <div class="row g-4">
                                                            {foreach $keysArr as $key}
                                                                {* Ajout de la classe "translation-item" pour le JS *}
                                                                <div class="col-12 translation-item">
                                                                    <div class="form-group bg-white p-3 rounded border border-light">
                                                                        {* Ajout de la classe "translation-key" pour le JS *}
                                                                        <label class="form-label fw-bold text-dark mb-2 translation-key">{$key|escape:'html'}</label>

                                                                        {* Ajout de la classe "translation-val" pour le JS *}
                                                                        <textarea class="form-control bg-light translation-val"
                                                                                  name="content[{$iso|escape:'html'}][{$groupName|escape:'html'}][{$key|escape:'html'}]"
                                                                                  rows="2" {if !($user_permissions.edit|default:0)}readonly{/if}>{$translations.$iso.$groupName.$key|default:''|escape}</textarea>
                                                                    </div>
                                                                </div>
                                                            {/foreach}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        {/foreach}

                                    </div>
                                {else}
                                    <div class="col-12 text-center text-muted py-4 bg-white rounded shadow-sm">
                                        <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                                        Aucune variable définie pour le moment.
                                    </div>
                                {/if}

                            </fieldset>
                        {/foreach}

                        {* 4. BLOC GLOBAL POUR AJOUTER UNE NOUVELLE VARIABLE *}
                        <div class="mt-5 p-4 bg-primary bg-opacity-10 border border-primary-subtle rounded shadow-sm">
                            <h5 class="fw-bold text-primary mb-3"><i class="bi bi-plus-circle me-2"></i>Créer une nouvelle variable</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="translationNewKey" class="form-label fw-bold">Clé (Ex: texte_bienvenue)</label>
                                    <input type="text" name="new_key" id="translationNewKey" pattern="[A-Za-z_][A-Za-z0-9_]*" {if !($user_permissions.edit|default:0)}disabled{/if} class="form-control" placeholder="Clé unique (sans espaces)">
                                </div>
                                <div class="col-md-6">
                                    {include file="translation/components/group-select.tpl" group_label="Groupe de classement"}
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Valeurs traduites</label>
                                    <div class="row g-3">
                                        {foreach $langs as $id => $iso}
                                            <div class="col-12 d-flex align-items-start">
                                                <span class="badge bg-secondary me-3 mt-1" style="width:45px; padding: 0.5em;">{$iso|upper}</span>
                                                <textarea name="new_value[{$iso}]" {if !($user_permissions.edit|default:0)}disabled{/if} class="form-control" rows="1" placeholder="Traduction en {$iso|upper}"></textarea>
                                            </div>
                                        {/foreach}
                                    </div>
                                </div>
                            </div>
                        </div>

                    {else}
                        <div class="alert alert-warning">Aucune langue configurée ou transmise à la vue.</div>
                    {/if}
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-outline-secondary" type="reset" id="translationResetAll" disabled>Tout annuler</button>
                    <button class="btn btn-primary px-4" type="submit" {if !($user_permissions.edit|default:0)}disabled{/if}>
                        <i class="bi bi-save me-1"></i> Tout enregistrer
                    </button>
                </div>
                <input type="hidden" name="catalog_complete" value="1">
            </form>
        </div>
    </div>
{/block}

{block name='javascripts' append}
    <script src="{$site_url}/{$baseadmin}/templates/js/MagixTranslationGroups.min.js?v=18"></script>
    <script src="{$site_url}/{$baseadmin}/templates/js/MagixTranslationClassic.min.js?v=18"></script>
{/block}