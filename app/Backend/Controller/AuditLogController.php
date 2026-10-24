<?php

declare(strict_types=1);

namespace App\Backend\Controller;

use App\Backend\Db\AuditLogDb;
use App\Backend\Model\DatabaseTransaction;

/**
 * Journal d'audit : consultation (droit "view") et purge (droit "del").
 */
class AuditLogController extends BaseController
{
    public function run(): void
    {
        // Dispatch explicite (allow-list) : pas d'invocation dynamique de méthode.
        if (($_GET['action'] ?? '') === 'purge') {
            $this->purge();
            return;
        }
        $this->index();
    }

    private function index(): void
    {
        $db = new AuditLogDb();

        $before = isset($_GET['before']) ? (int)$_GET['before'] : null;
        $search = is_array($_GET['search'] ?? null) ? $_GET['search'] : [];
        $search = ['action_log' => is_string($search['action_log'] ?? null) ? $search['action_log'] : ''];

        $result = $db->fetchKeyset(25, $before, $search);

        $rows = $result['rows'];
        foreach ($rows as &$row) {
            $a = (string)($row['action_log'] ?? '');
            if (preg_match('/(failure|failed|csrf|delete|uninstall|purge)/', $a)) {
                $row['badge'] = 'bg-danger-subtle text-danger';
            } elseif (preg_match('/(create|install|success)/', $a)) {
                $row['badge'] = 'bg-success-subtle text-success';
            } else {
                $row['badge'] = 'bg-secondary-subtle text-secondary';
            }

            $m = self::actionMeta($a);
            $row['label']        = $m[0]; // libellé humain de l'action
            $row['target_label'] = $m[1]; // ce que représente la colonne "Cible"
            $row['desc']         = $m[2]; // description d'une ligne
        }
        unset($row);

        $this->view->assign([
            'audit_rows'  => $rows,
            'has_more'    => $result['hasMore'],
            'next_cursor' => $result['nextCursor'],
            'is_paged'    => ($before !== null && $before > 0),
            'get_search'  => $search,
            'hashtoken'   => $this->session->getToken(),
        ]);

        $this->view->display('auditlog/index.tpl');
    }

    /**
     * Métadonnées d'affichage d'une action : [libellé humain, libellé de la
     * colonne « Élément concerné », description courte].
     */
    private static function actionMeta(string $action): array
    {
        static $map = [
            'login.success'    => ['Connexion réussie', 'Compte', 'Connexion réussie d\'un administrateur.'],
            'login.failure'    => ['Échec de connexion', 'E-mail saisi', 'Tentative de connexion avec des identifiants invalides.'],
            'login.unblock'    => ['IP débloquée', 'Adresse IP', 'Réinitialisation manuelle des échecs de connexion récents.'],
            'login.account_unblock' => ['Compte débloqué', 'Compte', 'Réinitialisation de la temporisation du compte, sans suppression du journal.'],
            'alert.bruteforce' => ['Alerte de blocage', 'Adresse IP', 'Seuil de blocage des connexions atteint.'],
            'alert.new_superadmin' => ['Alerte de création', 'Compte créé', 'Création d\'un compte avec le rôle Administrateur (tous les droits).'],
            'alert.superadmin_promote' => ['Alerte de promotion', 'Compte promu', 'Attribution du rôle Administrateur (tous les droits).'],
            'alert.delivery_failed' => ['Échec d’envoi d’alerte', 'Diagnostic', 'L’alerte a été enregistrée mais son e-mail n’a pas pu être envoyé.'],
            'alert.test_sent' => ['Test d’alerte envoyé', 'Destinataire', 'Le serveur de messagerie a accepté une alerte de test.'],
            'alert.test_failed' => ['Échec du test d’alerte', 'Diagnostic', 'Le test d’envoi d’une alerte de sécurité a échoué.'],
            'employee.create'  => ['Compte créé', 'Compte créé', 'Création d\'un compte d’administration.'],
            'employee.update'  => ['Compte modifié', 'Compte modifié', 'Modification d\'un compte d’administration.'],
            'employee.delete'  => ['Compte supprimé', 'Compte(s) supprimé(s)', 'Suppression d\'un ou plusieurs comptes d’administration.'],
            'role.create'      => ['Rôle créé', 'Rôle créé', 'Création d\'un rôle.'],
            'role.permissions' => ['Permissions modifiées', 'Rôle concerné', 'Mise à jour des permissions d\'un rôle.'],
            'setting.update'   => ['Réglages modifiés', 'Réglages modifiés', 'Modification d\'un ou plusieurs réglages du site.'],
            'plugin.install'   => ['Extension installée', 'Extension', 'Installation d\'une extension.'],
            'plugin.uninstall' => ['Extension désinstallée', 'Extension', 'Désinstallation d\'une extension.'],
            'audit.purge'      => ['Journal vidé', 'Opération', 'Purge complète du journal d\'audit.'],
        ];
        return $map[$action] ?? [$action, 'Élément concerné', ''];
    }

    /**
     * Vide le journal. L'action "purge" exige le bit "del" (deny-by-default
     * de checkPermissions). Ré-insère une entrée "audit.purge" pour garder la
     * trace de qui a vidé le journal et quand.
     */
    public function purge(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonResponse(false, 'Méthode non autorisée.');
        }
        $token = $_POST['hashtoken'] ?? '';
        if (!is_string($token) || !$this->session->validateToken($token)) {
            $this->jsonResponse(false, 'Session expirée.');
        }

        $db = new AuditLogDb();
        try {
            DatabaseTransaction::run(function () use ($db): void {
                if (!$db->purge() || $db->insert($this->auditActor() + [
                    'action_log' => 'audit.purge',
                    'target_log' => 'journal vidé',
                ]) === false) {
                    throw new \RuntimeException('Purge ou écriture de sa trace impossible.');
                }
            });
        } catch (\Throwable $error) {
            $this->jsonResponse(false, 'La purge a échoué. Le journal précédent est conservé.');
        }
        $this->jsonResponse(true, 'Le journal d\'audit a été vidé.', ['type' => 'purge']);
    }
}
