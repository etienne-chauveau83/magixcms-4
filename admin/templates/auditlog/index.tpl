{extends file="layout.tpl"}
{block name='head:title'}Journal d'audit{/block}

{block name="article"}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-clipboard-data me-2"></i> Journal d'audit
            <i class="bi bi-question-circle text-info ms-1 fs-6" tabindex="0" role="button" aria-label="Aide sur le journal d’audit" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Trace les actions sensibles : connexions, comptes administrateurs, rôles, permissions, réglages, extensions et purge du journal. Chaque entrée conserve l’auteur, son IP et la date. Les consultations et le contenu éditorial ne sont pas tracés."></i>
        </h1>
        {if isset($user_permissions) && $user_permissions.del == 1}
            <button type="button" class="btn btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#auditPurgeModal">
                <i class="bi bi-trash me-1"></i> Vider le journal
            </button>
        {/if}
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
            <h6 class="m-0 fw-bold text-primary">Actions enregistrées</h6>

            <form action="index.php" method="get" class="d-flex mb-0">
                <input type="hidden" name="controller" value="AuditLog">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" name="search[action_log]" value="{$get_search.action_log|default:''}" placeholder="Rechercher une action...">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Date</th>
                            <th>Administrateur</th>
                            <th>Action</th>
                            <th>Élément concerné</th>
                            <th>IP</th>
                            <th class="text-end px-4">Détail</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $audit_rows as $r}
                            <tr class="audit-row" style="cursor:pointer"
                                data-id="{$r.id_log}"
                                data-date="{$r.date_register}"
                                data-admin="{if $r.email_admin}{$r.email_admin}{else}—{/if}"
                                data-idadmin="{$r.id_admin|default:'—'}"
                                data-action="{$r.action_log}"
                                data-label="{$r.label}"
                                data-desc="{$r.desc}"
                                data-badge="{$r.badge}"
                                data-target="{if $r.target_log}{$r.target_log}{else}—{/if}"
                                data-targetlabel="{$r.target_label}"
                                data-details="{$r.details_log|default:''}"
                                data-ip="{$r.ip_log|default:'—'}">
                                <td class="px-4 text-muted small font-monospace">{$r.date_register}</td>
                                <td>{if $r.email_admin}{$r.email_admin}{else}<span class="text-muted">&mdash;</span>{/if}</td>
                                <td>
                                    <span class="badge {$r.badge}">{$r.label}</span>
                                    <div class="text-muted font-monospace" style="font-size:.72rem">{$r.action_log}</div>
                                </td>
                                <td class="text-truncate" style="max-width:240px">{if $r.target_log}{$r.target_log}{else}<span class="text-muted">&mdash;</span>{/if}</td>
                                <td class="text-muted small font-monospace">{$r.ip_log|default:'&mdash;'}</td>
                                <td class="text-end px-4">
                                    <button type="button" class="btn btn-sm btn-link text-body-emphasis" aria-label="Voir le détail de l’événement">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        {foreachelse}
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i> Aucune action enregistrée.
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>

        {if $is_paged || $has_more}
            {assign var="q" value=""}
            {if !empty($get_search.action_log)}{capture assign="q"}&search[action_log]={$get_search.action_log|escape:'url'}{/capture}{/if}
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                {if $is_paged}
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?controller=AuditLog{$q}"><i class="bi bi-arrow-up me-1"></i>Plus récentes</a>
                {else}
                    <span class="text-muted small"><i class="bi bi-arrow-up me-1"></i>Entrées les plus récentes</span>
                {/if}
                {if $has_more}
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?controller=AuditLog&before={$next_cursor}{$q}">Plus anciennes<i class="bi bi-arrow-down ms-1"></i></a>
                {else}
                    <span class="text-muted small">Fin du journal</span>
                {/if}
            </div>
        {/if}
    </div>

    <div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clipboard-data me-2"></i>Détail de l'entrée <span id="ad-id" class="text-muted small ms-1"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p id="ad-desc" class="text-muted mb-3"></p>
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-muted">Date</dt><dd class="col-sm-9 font-monospace" id="ad-date"></dd>
                        <dt class="col-sm-3 text-muted">Action</dt><dd class="col-sm-9"><span id="ad-action" class="badge"></span> <span id="ad-code" class="text-muted small font-monospace ms-1"></span></dd>
                        <dt class="col-sm-3 text-muted">Administrateur</dt><dd class="col-sm-9" id="ad-admin"></dd>
                        <dt class="col-sm-3 text-muted">ID admin</dt><dd class="col-sm-9 font-monospace" id="ad-idadmin"></dd>
                        <dt class="col-sm-3 text-muted">Adresse IP</dt><dd class="col-sm-9 font-monospace" id="ad-ip"></dd>
                        <dt class="col-sm-3 text-muted" id="ad-target-label">Élément concerné</dt><dd class="col-sm-9"><code id="ad-target" class="text-break"></code></dd>
                    </dl>
                    <div id="ad-changes" class="table-responsive mt-3" hidden>
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Modification</th><th>Avant</th><th>Après</th></tr></thead>
                            <tbody id="ad-changes-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {if isset($user_permissions) && $user_permissions.del == 1}
        <div class="modal fade" id="auditPurgeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Vider le journal d'audit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Toutes les entrées du journal seront supprimées définitivement. Une entrée <code>audit.purge</code> sera conservée pour tracer cette opération.</p>
                        <p class="mt-2 mb-0 fw-bold">Continuer&nbsp;?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-danger" id="auditPurgeConfirm"><i class="bi bi-trash me-1"></i> Vider</button>
                    </div>
                </div>
            </div>
        </div>
    {/if}
{/block}

{block name="javascripts" append}
    <script>
        (function () {
            if (typeof bootstrap !== 'undefined') document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
            const modalEl = document.getElementById('auditDetailModal');
            if (!modalEl || typeof bootstrap === 'undefined') return;
            const modal = new bootstrap.Modal(modalEl);
            const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            document.querySelectorAll('.audit-row').forEach(function (row) {
                row.addEventListener('click', function () {
                    const d = this.dataset;
                    set('ad-id', '#' + d.id);
                    set('ad-date', d.date);
                    set('ad-admin', d.admin);
                    set('ad-idadmin', d.idadmin);
                    set('ad-ip', d.ip);
                    set('ad-target', d.target);
                    set('ad-desc', d.desc || '');
                    set('ad-code', d.action);
                    set('ad-target-label', d.targetlabel || 'Élément concerné');
                    const changesBody = document.getElementById('ad-changes-body');
                    changesBody.replaceChildren();
                    let changes = [];
                    try {
                        const details = JSON.parse(d.details || '{}');
                        if (Array.isArray(details.changes)) changes = details.changes;
                    } catch (error) { /* Les anciennes entrées n'ont pas de détails structurés. */ }
                    changes.slice(0, 1000).forEach(function (change) {
                        if (!change || typeof change !== 'object') return;
                        const tr = document.createElement('tr');
                        [change.label || change.field, change.before, change.after].forEach(function (value) {
                            const td = document.createElement('td');
                            td.className = 'text-break';
                            td.textContent = value === null || value === undefined ? '—' : String(value);
                            tr.appendChild(td);
                        });
                        changesBody.appendChild(tr);
                    });
                    document.getElementById('ad-changes').hidden = changesBody.children.length === 0;
                    const act = document.getElementById('ad-action');
                    if (act) { act.textContent = d.label || d.action; act.className = 'badge ' + (d.badge || ''); }
                    modal.show();
                });
            });
        })();
    </script>
    {if isset($user_permissions) && $user_permissions.del == 1}
    <script>
        document.getElementById('auditPurgeConfirm')?.addEventListener('click', async function () {
            this.disabled = true;
            try {
                const fd = new FormData();
                fd.append('hashtoken', '{$hashtoken}');
                const resp = await fetch('index.php?controller=AuditLog&action=purge', {
                    method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
                });
                const data = await resp.json();
                if (data.status) {
                    if (typeof MagixToast !== 'undefined') MagixToast.success(data.message);
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    if (typeof MagixToast !== 'undefined') MagixToast.error(data.message || 'Erreur.');
                    this.disabled = false;
                }
            } catch (e) {
                if (typeof MagixToast !== 'undefined') MagixToast.error('Erreur réseau.');
                this.disabled = false;
            }
        });
    </script>
    {/if}
{/block}
