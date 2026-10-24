{extends file="layout.tpl"}

{block name='head:title'}{#setting#}{/block}
{block name='body:id'}setting{/block}

{block name='article'}
    {* --- DÉFINITION DES VARIABLES --- *}
    {assign var="collectionformCache" value=[
    "none"      => "Désactivé (None)",
    "files"     => "Fichiers (Recommandé)",
    "apcu"      => "APCu",
    "memcached" => "Memcached",
    "redis"     => "Redis"
    ]}
    {assign var="collectionformMode" value=[
    "dev"  => "Développement",
    "prod" => "Production"
    ]}
    {assign var="collectionformRobots" value=[
    "noindex,nofollow" => "No index",
    "index,follow,all" => "Index"
    ]}
    {assign var="collectionformPrice" value=[
    "tinc" => "Taxe incluse",
    "texc" => "Taxe non comprise"
    ]}

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-gear me-2"></i> {#setting#}
        </h1>
    </div>

    <div class="card shadow-sm border-0">
        <header class="card-header bg-white p-0 border-bottom-0">
            <ul class="nav nav-tabs nav-fill" role="tablist" id="settingTab">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-3 fw-bold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                        {#general_setting#}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 fw-bold" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo" type="button" role="tab" aria-controls="seo" aria-selected="false">
                        {#seo_setting#}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 fw-bold" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced" type="button" role="tab" aria-controls="advanced" aria-selected="false">
                        {#advanced_setting#}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 fw-bold" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                        {#security_setting#|default:'Sécurité'}
                    </button>
                </li>
            </ul>
        </header>

        <div class="card-body p-4">
            <form id="edit_setting_form" action="index.php?controller=Setting&action=save" method="post" class="validate_form edit_form">
                <input type="hidden" name="hashtoken" value="{$hashtoken}">

                <div class="tab-content">

                    {* ==========================================================
                       ONGLET 1 : GÉNÉRAL (Catalogue & News)
                       ========================================================== *}
                    <div role="tabpanel" class="tab-pane fade show active" id="general" aria-labelledby="general-tab">

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2">{#catalog_setting#}</legend>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="product_per_page">{#product_per_page#}</label>
                                    <input type="number" min="0" id="product_per_page" name="settings[product_per_page]" class="form-control" value="{$settings.product_per_page.value|default:''}" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="vat_rate">{#vat_rate#}</label>
                                    <div class="input-group">
                                        <input type="text" id="vat_rate" name="settings[vat_rate]" class="form-control" value="{$settings.vat_rate.value|default:''}" />
                                        <span class="input-group-text"><i class="bi bi-percent"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="price_display">{#price_display#}</label>
                                    <select name="settings[price_display]" id="price_display" class="form-select" required>
                                        {foreach $collectionformPrice as $key => $val}
                                            <option value="{$key}" {if ($settings.price_display.value|default:'') == $key} selected{/if}>{$val}</option>
                                        {/foreach}
                                    </select>
                                </div>

                                <div class="col-12 mt-4">
                                    <div class="form-check form-switch fs-5">
                                        <input class="form-check-input" type="checkbox" role="switch" id="product_catalog" name="settings[product_catalog]" value="1" {if ($settings.product_catalog.value|default:'0') eq '1'} checked{/if} />
                                        <label class="form-check-label fs-6 text-dark" for="product_catalog">
                                            {#product_catalog_setting#|default:'Afficher tous les produits à la racine du catalogue'}
                                            <i class="bi bi-question-circle text-info ms-1" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-content="{#product_catalog_warning#|default:'Au lieu d\'afficher uniquement les catégories mères, l\'accueil du catalogue affichera la liste de tous les produits.'}"></i>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="h5 text-primary border-bottom pb-2">{#news_setting#}</legend>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="news_per_page">{#news_per_page#}</label>
                                    <input type="number" min="0" id="news_per_page" name="settings[news_per_page]" class="form-control" value="{$settings.news_per_page.value|default:''}" />
                                </div>

                                {*  AJOUT : Switch pour activer/désactiver le calendrier *}
                                <div class="col-12 mt-3">
                                    <div class="form-check form-switch fs-5">
                                        <input class="form-check-input" type="checkbox" role="switch" id="calendar_enabled" name="settings[calendar_enabled]" value="1" {if ($settings.calendar_enabled.value|default:'0') eq '1'} checked{/if} />
                                        <label class="form-check-label fs-6 text-dark" for="calendar_enabled">
                                            {#calendar_enabled_setting#|default:'Activer l\'agenda des actualités'}
                                            <i class="bi bi-question-circle text-info ms-1" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-content="{#calendar_enabled_warning#|default:'Active la vue calendrier et affiche le bouton de bascule dans la liste des actualités publiques.'}"></i>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                        <fieldset class="mt-4">
                            <legend class="h5 text-primary border-bottom pb-2">{#placeholder_setting#|default:'Images de substitution (Holder)'}</legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="holder_bgcolor">{#holder_bgcolor#|default:'Couleur de fond'}
                                        <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur la couleur de fond" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Code couleur hexadécimal, par exemple #FFFFFF."></i>
                                    </label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" id="holder_bgcolor_picker" class="form-control form-control-color border-1 p-0" style="width: 38px; height: 38px;" value="{$settings.holder_bgcolor.value|default:'#ffffff'}" title="Choisir une couleur" onchange="document.getElementById('holder_bgcolor').value = this.value">
                                        <input type="text" id="holder_bgcolor" name="settings[holder_bgcolor]" class="form-control text-uppercase" placeholder="#FFFFFF" value="{$settings.holder_bgcolor.value|default:'#ffffff'}" oninput="document.getElementById('holder_bgcolor_picker').value = this.value">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="logo_percent">{#logo_percent#|default:'Taille du logo intégré'}
                                        <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur la taille du logo" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Espace occupé par le logo sur l’image, de 1 à 100 %."></i>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" min="1" max="100" id="logo_percent" name="settings[logo_percent]" class="form-control" value="{$settings.logo_percent.value|default:'50'}" />
                                        <span class="input-group-text"><i class="bi bi-percent"></i></span>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    {* ==========================================================
                       ONGLET 2 : SEO
                       ========================================================== *}
                    <div role="tabpanel" class="tab-pane fade" id="seo" aria-labelledby="seo-tab">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="robots">{#robots_setting#}</label>
                                <select name="settings[robots]" id="robots" class="form-select" required>
                                    {foreach $collectionformRobots as $key => $val}
                                        <option value="{$key}" {if ($settings.robots.value|default:'') == $key} selected{/if}>{$val}</option>
                                    {/foreach}
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="analytics">{#analytics_setting#}</label>
                                <input type="text" id="analytics" name="settings[analytics]" class="form-control" placeholder="{#ph_analytics_setting#|default:'UA-XXXXX'}" value="{$settings.analytics.value|default:''}" />
                            </div>
                        </div>
                    </div>

                    {* ==========================================================
                       ONGLET 3 : AVANCÉ (Nettoyé)
                       ========================================================== *}
                    <div role="tabpanel" class="tab-pane fade" id="advanced" aria-labelledby="advanced-tab">

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2">{#tinymce_setting#}</legend>
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="form-label" for="content_css">{#tinymce_css#}</label>
                                    <input type="text" id="content_css" name="settings[content_css]" class="form-control" placeholder="{#ph_tinymce_css#|default:''}" value="{$settings.content_css.value|default:''}" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2">{#performance_setting#}</legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="mode">
                                        {#mode_setting#}
                                        <i class="bi bi-question-circle text-info ms-1" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-content="{#mode_warning#|default:'Mode dev affiche les erreurs détaillées'|escape:'html'}" data-bs-html="true"></i>
                                    </label>
                                    <select name="settings[mode]" id="mode" class="form-select" required>
                                        {foreach $collectionformMode as $key => $val}
                                            <option value="{$key}" {if ($settings.mode.value|default:'') == $key} selected{/if}>{$val}</option>
                                        {/foreach}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cache">
                                        {#cache_setting#}
                                        <i class="bi bi-question-circle text-info ms-1" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-content="{#cache_warning#|default:'Mise en cache des données et requêtes'|escape:'html'}" data-bs-html="true"></i>
                                    </label>
                                    <select name="settings[cache]" id="cache" class="form-select" required>
                                        {foreach $collectionformCache as $key => $val}
                                            <option value="{$key}" {if ($settings.cache.value|default:'') == $key} selected{/if}>{$val}</option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2">{#maintenance_setting#}</legend>
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" id="maintenance" name="settings[maintenance]" value="1" {if ($settings.maintenance.value|default:'0') eq '1'} checked{/if} />
                                <label class="form-check-label fs-6 text-danger" for="maintenance">
                                    {#enable_maintenance#}
                                    <i class="bi bi-question-circle text-warning ms-1" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="right" data-bs-content="{#maintenance_warning#|default:'Bloque l\'accès public au site'}"></i>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="h5 text-primary border-bottom pb-2">{#geminiai_setting#|default:'Assistant IA'}</legend>
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" id="geminiai" name="settings[geminiai]" value="1" {if ($settings.geminiai.value|default:'0') eq '1'} checked{/if} />
                                <label class="form-check-label fs-6 text-primary" for="geminiai">
                                    {#enable_geminiai#|default:'Activer Gemini AI'}
                                    <i class="bi bi-question-circle text-warning ms-1" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="right" data-bs-content="{#geminiai_warning#|default:'Active les aides IA dans l\'éditeur'}"></i>
                                </label>
                            </div>
                        </fieldset>

                    </div>

                    {* ==========================================================
                       ONGLET 4 : SÉCURITÉ
                       ========================================================== *}
                    <div role="tabpanel" class="tab-pane fade" id="security" aria-labelledby="security-tab">

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2"><i class="bi bi-clipboard-data me-2"></i>Journal d'audit</legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="sec_audit_retention_days">Rétention (jours)
                                        <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur la rétention" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Les entrées plus anciennes que cette durée sont purgées automatiquement."></i>
                                    </label>
                                    <input type="number" min="1" max="3650" class="form-control" id="sec_audit_retention_days" name="settings[sec_audit_retention_days]" value="{$settings.sec_audit_retention_days.value|default:'90'}" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="sec_audit_max_rows">Plafond de lignes
                                    <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur le plafond du journal" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Les entrées les plus anciennes sont supprimées au-delà du plafond. Les compteurs de protection des connexions sont stockés séparément et restent actifs après une purge du journal."></i>
                                    </label>
                                    <input type="number" min="100" max="10000000" class="form-control" id="sec_audit_max_rows" name="settings[sec_audit_max_rows]" value="{$settings.sec_audit_max_rows.value|default:'50000'}" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2"><i class="bi bi-shield-lock me-2"></i>Protection brute-force</legend>
                            <div class="form-check form-switch fs-5 mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="sec_bruteforce_enabled" name="settings[sec_bruteforce_enabled]" value="1" {if ($settings.sec_bruteforce_enabled.value|default:'1') eq '1'} checked{/if} />
                                <label class="form-check-label fs-6" for="sec_bruteforce_enabled">Protéger les connexions par IP et par compte
                                    <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur le blocage des connexions" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Le seuil s’applique à l’IP et au compte, même si les tentatives proviennent de plusieurs IP. L’IP est bloquée jusqu’à expiration de ses échecs ; le compte reçoit un délai progressif de 1 à 60 secondes. Une tentative refusée pendant ce délai ne le prolonge pas."></i>
                                </label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="sec_bruteforce_max">Seuil d'échecs avant blocage</label>
                                    <input type="number" min="1" max="1000" class="form-control" id="sec_bruteforce_max" name="settings[sec_bruteforce_max]" value="{$settings.sec_bruteforce_max.value|default:'5'}" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="sec_bruteforce_window">Fenêtre de comptage (minutes)</label>
                                    <input type="number" min="1" max="1440" class="form-control" id="sec_bruteforce_window" name="settings[sec_bruteforce_window]" value="{$settings.sec_bruteforce_window.value|default:'15'}" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2"><i class="bi bi-bell me-2"></i>Alertes</legend>
                            <div class="{if !$alert_mail_status.smtp_enabled || !$alert_mail_status.can_send}alert alert-warning py-2{else}d-flex flex-wrap align-items-center gap-2 small text-muted mb-3{/if}" role="status" id="securityAlertMailStatus">
                                {if !$alert_mail_status.smtp_enabled}
                                    <strong>SMTP non configuré.</strong> Les alertes utilisent la messagerie du serveur.
                                {elseif !$alert_mail_status.can_send}
                                    <strong>Configuration e-mail incomplète.</strong>
                                {else}
                                    <span><i class="bi bi-check-circle text-success me-1" aria-hidden="true"></i>Configuration SMTP renseignée.</span>
                                {/if}
                                {if $alert_mail_status.issues}
                                    <ul class="mb-0 mt-1">{foreach $alert_mail_status.issues as $issue}<li>{$issue}</li>{/foreach}</ul>
                                {/if}
                                <a href="index.php?controller=MailSetting" class="{if !$alert_mail_status.smtp_enabled || !$alert_mail_status.can_send}alert-link d-inline-block mt-1{else}text-muted{/if}">Configurer les e-mails<i class="bi bi-arrow-right ms-1"></i></a>
                            </div>
                            <div class="form-check form-switch fs-5 mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="sec_alert_bruteforce" name="settings[sec_alert_bruteforce]" value="1" {if ($settings.sec_alert_bruteforce.value|default:'1') eq '1'} checked{/if} />
                                <label class="form-check-label fs-6" for="sec_alert_bruteforce">Alerte en cas de <strong>blocage brute-force</strong></label>
                            </div>
                            <div class="form-check form-switch fs-5 mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="sec_alert_superadmin" name="settings[sec_alert_superadmin]" value="1" {if ($settings.sec_alert_superadmin.value|default:'1') eq '1'} checked{/if} />
                                <label class="form-check-label fs-6" for="sec_alert_superadmin">Alerte en cas de <strong>création d'un administrateur ou attribution du rôle Administrateur</strong></label>
                            </div>
                            <label class="form-label" for="sec_alert_email">E-mail destinataire
                                <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur le destinataire des alertes" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Laissez vide pour utiliser l’e-mail de contact de l’entreprise. Les alertes utilisent la configuration e-mail enregistrée dans Configuration des e-mails."></i>
                            </label>
                            <input type="email" class="form-control" id="sec_alert_email" name="settings[sec_alert_email]" value="{$settings.sec_alert_email.value|default:''}" placeholder="securite@example.com" />
                            {if isset($user_permissions) && $user_permissions.edit == 1}
                                <div class="d-flex align-items-center gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-primary" id="securityAlertTest"><i class="bi bi-envelope-check me-2"></i><span>Envoyer une alerte de test</span></button>
                                    <i class="bi bi-question-circle text-info" tabindex="0" role="button" aria-label="Aide sur le test des alertes" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Envoie uniquement un e-mail de test au destinataire saisi, avec la configuration e-mail déjà enregistrée. Ne modifie pas les réglages et ne déclenche ni blocage ni création de compte. L’acceptation par le serveur ne garantit pas la réception en boîte de réception."></i>
                                </div>
                                <div class="alert mt-3 mb-0" id="securityAlertTestResult" role="status" aria-live="polite" hidden></div>
                            {/if}
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2"><i class="bi bi-shield-check me-2"></i>Tableau de bord</legend>
                            <div class="form-check form-switch fs-5 mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="sec_dashboard_widget" name="settings[sec_dashboard_widget]" value="1" {if ($settings.sec_dashboard_widget.value|default:'1') eq '1'} checked{/if} />
                                <label class="form-check-label fs-6" for="sec_dashboard_widget">Afficher la tuile « Sécurité » sur le tableau de bord</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="sec_superadmin_threshold">Seuil d'alerte des comptes avec tous les droits
                                        <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur le nombre de comptes avec tous les droits" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Compte le créateur et les administrateurs disposant de tous les droits. Les éditeurs ne sont pas comptés. Au-delà de ce seuil, l’état de sécurité signale un point d’attention."></i>
                                    </label>
                                    <input type="number" min="1" max="100" class="form-control" id="sec_superadmin_threshold" name="settings[sec_superadmin_threshold]" value="{$settings.sec_superadmin_threshold.value|default:'3'}" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4">
                            <legend class="h5 text-primary border-bottom pb-2"><i class="bi bi-hdd-network me-2"></i>Réseau (proxy / CDN)</legend>
                            <label class="form-label" for="sec_trusted_proxies">Proxies de confiance (IP ou CIDR)
                                <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur les proxies de confiance" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Laissez vide si le site n’est pas derrière un proxy ou CDN. Sinon, listez les IP ou plages de vos proxies, séparées par des virgules. Sans cette liste, X-Forwarded-For est ignoré pour empêcher l’usurpation ; derrière un CDN non déclaré, les visiteurs partageraient une même IP."></i>
                            </label>
                            <input type="text" class="form-control" id="sec_trusted_proxies" name="settings[sec_trusted_proxies]" value="{$settings.sec_trusted_proxies.value|default:''}" placeholder="ex : 173.245.48.0/20, 2400:cb00::/32" />
                        </fieldset>

                        <fieldset>
                            <legend class="h5 text-primary border-bottom pb-2"><i class="bi bi-lock me-2"></i>Protocole &amp; chiffrement</legend>
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" id="ssl" name="settings[ssl]" value="1" {if ($settings.ssl.value|default:'0') eq '1'} checked{/if} />
                                <label class="form-check-label fs-6" for="ssl">
                                    {#ssl_setting#}
                                    <i class="bi bi-question-circle text-warning ms-1" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="right" data-bs-content="{#ssl_warning#|default:'Force la redirection vers HTTPS'}"></i>
                                </label>
                            </div>
                        </fieldset>

                    </div>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-end">
                    <button class="btn btn-primary px-5" type="submit" name="action" value="save">
                        <i class="bi bi-save me-2"></i> {#save#}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/block}

{block name="javascripts" append}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash === '#security') bootstrap.Tab.getOrCreateInstance(document.getElementById('security-tab')).show();
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            });
            const testButton = document.getElementById('securityAlertTest');
            if (testButton) {
                testButton.addEventListener('click', async function () {
                    const result = document.getElementById('securityAlertTestResult');
                    const label = this.querySelector('span');
                    this.disabled = true;
                    label.textContent = 'Envoi en cours…';
                    result.hidden = true;
                    try {
                        const form = document.getElementById('edit_setting_form');
                        const data = new FormData();
                        data.append('hashtoken', form.querySelector('[name="hashtoken"]').value);
                        data.append('email', document.getElementById('sec_alert_email').value);
                        const response = await fetch('index.php?controller=Setting&action=testAlert', {
                            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data
                        });
                        if (!response.ok || response.redirected) throw new Error('request');
                        const payload = await response.json();
                        result.className = 'alert mt-3 mb-0 ' + (payload.status ? 'alert-success' : 'alert-danger');
                        result.textContent = payload.message;
                    } catch (error) {
                        result.className = 'alert alert-danger mt-3 mb-0';
                        result.textContent = 'Le test a échoué. Vérifiez votre connexion et votre session, puis réessayez.';
                    } finally {
                        result.hidden = false;
                        this.disabled = false;
                        label.textContent = 'Envoyer une alerte de test';
                    }
                });
            }
        });
    </script>
{/block}
