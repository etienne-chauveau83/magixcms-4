{* Choix commun aux deux modes : une rubrique existante ou un nouveau groupe. *}
<label for="translationGroupSelect" class="form-label">
    {$group_label|default:'Rubrique'|escape:'html'}
    <button type="button" class="btn btn-link text-info p-0 ms-1" data-translation-help
            data-bs-content="Choisissez un groupe existant ou « Nouveau groupe… ». Le nouveau groupe sera créé à l’enregistrement d’une nouvelle variable."
            aria-label="Aide sur les groupes">
        <i class="bi bi-question-circle" aria-hidden="true"></i>
    </button>
</label>
<select id="translationGroupSelect" class="form-select mb-3" {if !($user_permissions.edit|default:0)}disabled{/if}>
    {foreach $structure|default:[] as $groupName => $keysArr}
        <option value="{$groupName|escape:'html'}" {if $groupName == 'Général'}selected{/if}>{$groupName|escape:'html'}</option>
    {/foreach}
    <option value="">Nouveau groupe…</option>
</select>
<div id="translationNewGroupFields" class="mb-3" hidden>
    <label for="translationNewGroupName" class="form-label">Nom du nouveau groupe</label>
    <input type="text" id="translationNewGroupName" class="form-control" maxlength="150"
           autocomplete="off" placeholder="Ex. Présentation" {if !($user_permissions.edit|default:0)}disabled{/if}>
</div>
<input type="hidden" name="new_group" id="translationNewGroup" value="Général">
