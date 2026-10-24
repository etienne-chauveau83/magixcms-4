{extends file="layout.tpl"}
{block name='head:title'}Protection des connexions{/block}

{block name="article"}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-shield-lock me-2"></i>Protection des connexions
            <i class="bi bi-question-circle text-info ms-1 fs-6" tabindex="0" role="button" aria-label="Aide sur la protection des connexions" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Après {$max_attempts} échecs sur {$window_min} minutes, une IP est bloquée jusqu’à expiration de ses échecs. Un même compte est aussi protégé entre plusieurs IP par un délai progressif de 1 à 60 secondes. Les requêtes refusées pendant le délai ne le prolongent pas. Réinitialiser un compteur ne supprime aucune trace d’audit et ne réinitialise pas l’autre protection."></i>
        </h1>
    </div>
    {if !$bf_enabled}
        <div class="alert alert-warning" role="status">La protection des connexions est <strong>désactivée</strong>. Aucun blocage ni nouveau compteur n’est appliqué ; les échecs restent journalisés.</div>
    {/if}
    {foreach $groups as $scope => $rows}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h2 class="h6 m-0 fw-bold text-primary">{if $scope == 'ip'}Adresses IP{else}Comptes{/if} — {$window_min} dernières minutes
                    <i class="bi bi-question-circle text-info ms-1" tabindex="0" role="button" aria-label="Aide sur la liste des compteurs" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Au plus 200 compteurs par catégorie, classés par nombre d’échecs. Le statut est recalculé à l’affichage. Les compteurs temporaires sont indépendants du journal d’audit."></i>
                </h2>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th class="px-4">{if $scope == 'ip'}Adresse IP{else}Compte{/if}</th>
                        <th>Échecs</th><th>{if $scope == 'ip'}Dernier e-mail tenté{else}Dernière IP{/if}</th>
                        <th>Dernière tentative</th><th>Statut</th>
                        {if isset($user_permissions) && $user_permissions.edit == 1}<th class="text-end px-4">Action</th>{/if}
                    </tr></thead>
                    <tbody>
                    {foreach $rows as $r}
                        <tr>
                            <td class="px-4 text-break">{$r.subject_label|default:'—'}</td>
                            <td>{$r.fails}</td>
                            <td class="small text-muted">{if $scope == 'ip'}{$r.email_attempt|default:'—'}{else}{$r.ip_attempt}{/if}</td>
                            <td class="small text-muted font-monospace">{$r.last_at}</td>
                            <td>{if $r.blocked}<span class="badge bg-danger">{if $scope == 'ip'}Bloquée{else}Temporisé{/if}</span>{else}<span class="badge bg-secondary">Surveillé</span>{/if}</td>
                            {if isset($user_permissions) && $user_permissions.edit == 1}
                                <td class="text-end px-4"><button type="button" class="btn btn-sm btn-outline-secondary btn-unblock" data-scope="{$scope}" data-hash="{$r.subject_hash}"><i class="bi bi-unlock me-1"></i>Réinitialiser</button></td>
                            {/if}
                        </tr>
                    {foreachelse}
                        <tr><td colspan="{if isset($user_permissions) && $user_permissions.edit == 1}6{else}5{/if}" class="text-center text-muted py-4">Aucun compteur récent.</td></tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    {/foreach}
{/block}

{block name="javascripts" append}
    <script>
        if (typeof bootstrap !== 'undefined') document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
        document.querySelectorAll('.btn-unblock').forEach(function (button) {
            button.addEventListener('click', async function () {
                this.disabled = true;
                try {
                    const body = new FormData();
                    body.append('hashtoken', '{$hashtoken}');
                    body.append('scope', this.dataset.scope);
                    body.append('subject_hash', this.dataset.hash);
                    const response = await fetch('index.php?controller=LoginProtection&action=unblock', {
                        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body
                    });
                    if (!response.ok || response.redirected) throw new Error('request');
                    const data = await response.json();
                    if (data.status) {
                        if (typeof MagixToast !== 'undefined') MagixToast.success(data.message, { html: false });
                        window.location.reload();
                    } else {
                        if (typeof MagixToast !== 'undefined') MagixToast.error(data.message, { html: false });
                    }
                } catch (error) {
                    if (typeof MagixToast !== 'undefined') MagixToast.error('Réinitialisation impossible. Vérifiez votre session.');
                } finally {
                    this.disabled = false;
                }
            });
        });
    </script>
{/block}
