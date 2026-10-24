<?php

declare(strict_types=1);

namespace App\Backend\Controller;

use App\Backend\Db\RoleDb;
use App\Backend\Model\AdminRoleLabel;
use App\Backend\Model\AdminSecurityService;
use Magepattern\Component\HTTP\Request;
use Magepattern\Component\Tool\FormTool;

class RoleController extends BaseController
{
    public function run(): void
    {
        $action = $_GET['action'] ?? null;
        if (in_array($action, ['add', 'edit'], true)) {
            $this->$action();
            return;
        }
        $this->index();
    }

    private function index(): void
    {
        $db = new RoleDb();
        $this->view->assign([
            'role_list' => AdminRoleLabel::roles($db->fetchAllRoles() ?: []),
            'hashtoken' => $this->session->getToken()
        ]);
        $this->view->display('role/index.tpl');
    }

    public function edit(): void
    {
        $idRole = (int)($_REQUEST['edit'] ?? 0);
        $db = new RoleDb();

        if (Request::isMethod('POST')) {
            $this->processSave($db, $idRole);
            return;
        }

        $this->view->assign([
            'id_role'     => $idRole,
            'modules'     => $db->fetchAllModules(),
            'permissions' => $db->fetchPermissionsByRole($idRole),
            'hashtoken'   => $this->session->getToken()
        ]);
        $this->view->display('role/edit.tpl');
    }

    private function processSave(RoleDb $db, int $idRole): void
    {
        $token = $_POST['hashtoken'] ?? '';
        if (!is_string($token) || !$this->session->validateToken($token)) {
            $this->jsonResponse(false, 'Session expirée.');
        }

        // On récupère la matrice envoyée (format: permissions[id_module][view])
        $matrix = $_POST['permissions'] ?? [];

        if (!is_array($matrix)) {
            $this->jsonResponse(false, 'Matrice de permissions invalide.');
        }
        try {
            AdminSecurityService::savePermissions($idRole, $matrix, $this->auditActor());
            $this->jsonResponse(true, 'Permissions mises à jour.');
        } catch (\Throwable $error) {
            $this->jsonResponse(false, 'L’enregistrement a échoué. Les permissions précédentes sont conservées.');
        }
    }
    public function add(): void
    {
        if (Request::isMethod('POST')) {
            $this->processAdd();
            return;
        }

        $db = new RoleDb();

        $this->view->assign([
            'modules'   => $db->fetchAllModules(),
            'hashtoken' => $this->session->getToken()
        ]);

        $this->view->display('role/add.tpl');
    }

    private function processAdd(): void
    {
        $token = $_POST['hashtoken'] ?? '';
        if (!is_string($token) || !$this->session->validateToken($token)) {
            $this->jsonResponse(false, 'Session expirée.');
        }

        $roleName = FormTool::simpleClean($_POST['role_name'] ?? '');

        if (empty($roleName)) {
            $this->jsonResponse(false, 'Veuillez indiquer un nom pour ce rôle.');
        }

        $db = new RoleDb();
        $matrix = $_POST['permissions'] ?? [];
        if (!is_array($matrix)) {
            $this->jsonResponse(false, 'Matrice de permissions invalide.');
        }
        try {
            $newId = AdminSecurityService::createRole($roleName, $matrix, $this->auditActor());
            $this->jsonResponse(true, 'Le rôle a été créé avec succès.', ['type' => 'add', 'id' => $newId]);
        } catch (\Throwable $error) {
            $this->jsonResponse(false, 'La création du rôle a échoué. Aucune modification n’a été enregistrée.');
        }
    }
}
