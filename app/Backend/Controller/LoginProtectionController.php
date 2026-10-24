<?php

declare(strict_types=1);

namespace App\Backend\Controller;

use App\Backend\Model\LoginGuard;

/**
 * Protection des connexions : visualisation des échecs récents par IP,
 * statut de blocage brute-force, et déblocage manuel.
 */
class LoginProtectionController extends BaseController
{
    public function run(): void
    {
        // Dispatch explicite (allow-list) : pas d'invocation dynamique de méthode.
        if (($_GET['action'] ?? '') === 'unblock') {
            $this->unblock();
            return;
        }
        $this->index();
    }

    private function index(): void
    {
        $maxAttempts = LoginGuard::maxAttempts();
        $windowMin   = LoginGuard::windowMin();

        $this->view->assign([
            'groups' => ['ip' => LoginGuard::recent('ip'), 'account' => LoginGuard::recent('account')],
            'max_attempts' => $maxAttempts,
            'window_min'   => $windowMin,
            'bf_enabled'   => LoginGuard::enabled(),
            'hashtoken'    => $this->session->getToken(),
        ]);

        $this->view->display('loginprotection/index.tpl');
    }

    /**
     * Déblocage manuel d'un compteur, sans effacer le journal. L'action "unblock"
     * exige un droit d'écriture (deny-by-default → bit "edit").
     */
    public function unblock(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonResponse(false, 'Méthode non autorisée.');
        }
        $token = $_POST['hashtoken'] ?? '';
        if (!is_string($token) || !$this->session->validateToken($token)) {
            $this->jsonResponse(false, 'Session expirée.');
        }

        $scope = $_POST['scope'] ?? 'ip';
        $hash = $_POST['subject_hash'] ?? '';
        if ($hash === '' && is_string($_POST['ip'] ?? null) && filter_var($_POST['ip'], FILTER_VALIDATE_IP)) {
            $hash = hash('sha256', inet_ntop(inet_pton($_POST['ip'])));
        }
        if (!in_array($scope, ['ip', 'account'], true) || !is_string($hash) || strlen($hash) !== 64 || !ctype_xdigit($hash)) {
            $this->jsonResponse(false, 'Compteur invalide.');
        }
        try {
            $label = LoginGuard::reset($scope, strtolower($hash), $this->auditActor());
            if ($label === null) {
                $this->jsonResponse(false, 'Ce compteur n’existe plus.');
            }
            $this->jsonResponse(true, 'Compteur réinitialisé : ' . $label . '. Le journal d’audit est conservé.', ['type' => 'unblock']);
        } catch (\Throwable $error) {
            $this->jsonResponse(false, 'Réinitialisation impossible. Réessayez dans quelques instants.');
        }
    }
}
