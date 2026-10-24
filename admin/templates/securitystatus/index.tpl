{extends file="layout.tpl"}
{block name='head:title'}État de sécurité{/block}

{block name="article"}
    {if $counts.critical > 0}{assign var="scoreColor" value="bg-danger text-white"}
    {elseif $score >= 80}{assign var="scoreColor" value="bg-success text-white"}
    {elseif $score >= 50}{assign var="scoreColor" value="bg-warning text-dark"}
    {else}{assign var="scoreColor" value="bg-danger text-white"}{/if}

    <div class="card border-0 shadow-sm mb-4 bg-dark text-white">
        <div class="card-body p-4 d-flex align-items-center">
            <i class="bi bi-shield-check display-5 me-3 opacity-75"></i>
            <div>
                <h1 class="h3 mb-1">État de sécurité
                    <i class="bi bi-question-circle text-info ms-1 fs-6" tabindex="0" role="button" aria-label="Aide sur l’état de sécurité" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Audit indicatif de la configuration PHP, serveur, application et comptes. Les constats sont classés par sévérité ; les liens indiquent où les corriger. Le score exclut les informations neutres. L’audit ne modifie rien et est recalculé à chaque affichage."></i>
                </h1>
                <p class="opacity-75 mb-0">{$total} vérifications.</p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm {$scoreColor} h-100">
                <div class="card-body text-center">
                    <h6 class="text-uppercase small mb-1">Score</h6>
                    <h2 class="display-6 fw-bold mb-0">{$score}%</h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-uppercase small mb-1"><i class="bi bi-x-octagon-fill me-1"></i>Critiques</h6>
                    <h2 class="display-6 fw-bold mb-0">{$counts.critical}</h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body text-center">
                    <h6 class="text-uppercase small mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Attention</h6>
                    <h2 class="display-6 fw-bold mb-0">{$counts.warning}</h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body text-center">
                    <h6 class="text-uppercase small mb-1"><i class="bi bi-check-circle-fill me-1"></i>Conformes</h6>
                    <h2 class="display-6 fw-bold mb-0">{$counts.ok}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex flex-wrap gap-2 align-items-center justify-content-between border-bottom">
            <h6 class="m-0 fw-bold text-primary">Détail des vérifications</h6>
            <div class="btn-group btn-group-sm" role="group" id="secFilter">
                <button type="button" class="btn btn-outline-secondary active" data-filter="all">Tout</button>
                <button type="button" class="btn btn-outline-danger" data-filter="critical">Critiques ({$counts.critical})</button>
                <button type="button" class="btn btn-outline-warning" data-filter="warning">Attention ({$counts.warning})</button>
                <button type="button" class="btn btn-outline-success" data-filter="ok">Conformes ({$counts.ok})</button>
            </div>
        </div>
        <div class="card-body p-0">
            {foreach $grouped as $cat => $list}
                <div class="sec-group">
                    <div class="px-4 py-2 bg-body-secondary small fw-bold text-uppercase text-muted">{$cat}</div>
                    <ul class="list-group list-group-flush">
                        {foreach $list as $c}
                            {if $c.status == 'critical'}{assign var="bc" value="bg-danger"}{assign var="ic" value="bi-x-octagon-fill"}{assign var="bt" value="Critique"}
                            {elseif $c.status == 'warning'}{assign var="bc" value="bg-warning text-dark"}{assign var="ic" value="bi-exclamation-triangle-fill"}{assign var="bt" value="Attention"}
                            {elseif $c.status == 'info'}{assign var="bc" value="bg-info text-dark"}{assign var="ic" value="bi-info-circle-fill"}{assign var="bt" value="Info"}
                            {else}{assign var="bc" value="bg-success"}{assign var="ic" value="bi-check-circle-fill"}{assign var="bt" value="OK"}{/if}
                            <li class="list-group-item py-3 check-row" data-status="{$c.status}">
                                <div class="d-flex align-items-start">
                                    <span class="badge {$bc} me-3 mt-1" style="min-width:90px"><i class="bi {$ic} me-1"></i>{$bt}</span>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold font-monospace">{$c.label}</div>
                                        <div class="text-muted small">{$c.detail}</div>
                                        {if $c.reco}<div class="small mt-1"><i class="bi bi-arrow-right-short text-primary-emphasis"></i> <span class="text-primary-emphasis fw-medium">{$c.reco}</span></div>{/if}
                                    </div>
                                    {if $c.link}
                                        <a href="{$c.link}" class="btn btn-sm btn-primary ms-3 flex-shrink-0 align-self-center">{$c.link_label}<i class="bi bi-arrow-right ms-1"></i></a>
                                    {/if}
                                </div>
                            </li>
                        {/foreach}
                    </ul>
                </div>
            {/foreach}
        </div>
    </div>
{/block}

{block name="javascripts" append}
    <script>
        (function () {
            if (typeof bootstrap !== 'undefined') document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
            const bar = document.getElementById('secFilter');
            if (!bar) return;
            bar.querySelectorAll('button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    bar.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const f = this.dataset.filter;
                    document.querySelectorAll('.check-row').forEach(function (row) {
                        row.style.display = (f === 'all' || row.dataset.status === f) ? '' : 'none';
                    });
                    document.querySelectorAll('.sec-group').forEach(function (g) {
                        const any = Array.from(g.querySelectorAll('.check-row')).some(r => r.style.display !== 'none');
                        g.style.display = any ? '' : 'none';
                    });
                });
            });
        })();
    </script>
{/block}
