<?php

declare(strict_types=1);

namespace App\Backend\Model;

use App\Backend\Db\AuditLogDb;
use App\Backend\Db\EmployeeDb;
use App\Backend\Db\RoleDb;
use Magepattern\Component\Database\Layer;

/** Écritures sensibles et leur audit validés dans une même transaction. */
final class AdminSecurityService
{
    private const EMPLOYEE_FIELDS = [
        'id_admin' => 'Identifiant', 'email_admin' => 'E-mail',
        'firstname_admin' => 'Prénom', 'lastname_admin' => 'Nom',
        'active_admin' => 'Compte actif', 'id_role' => 'Rôle',
    ];
    private const RIGHTS = [
        'view' => 'Consulter', 'append' => 'Créer', 'edit' => 'Modifier',
        'del' => 'Supprimer', 'action' => 'Action',
    ];

    public static function createEmployee(array $data, int $role, array $actor): int
    {
        return DatabaseTransaction::run(function () use ($data, $role, $actor): int {
            $db = new EmployeeDb();
            self::protectRoleAssignment($role, $actor);
            $id = $db->insertEmployee($data);
            if ($id === false || !$db->syncEmployeeRole($id, $role)) {
                throw new \RuntimeException('Création du compte ou attribution du rôle impossible.');
            }
            $after = self::employeeForUpdate($id);
            self::audit($actor, 'employee.create', 'id=' . $id, self::employeeChanges([], $after));
            return $id;
        });
    }

    /** Retourne les états réellement enregistrés, pour décider des alertes après commit. */
    public static function updateEmployee(int $id, array $data, int $role, array $actor): array
    {
        return DatabaseTransaction::run(function () use ($id, $data, $role, $actor): array {
            $before = self::employeeForUpdate($id);
            self::protectEmployee($before, $actor);
            // Le créateur reste actif et conserve son rôle, comme l'impose le formulaire.
            if ($id === 1) {
                $role = 1;
                $data['active_admin'] = 1;
            }
            self::protectRoleAssignment($role, $actor);
            $db = new EmployeeDb();
            if (!$db->updateEmployee($id, $data) || !$db->syncEmployeeRole($id, $role)) {
                throw new \RuntimeException('Mise à jour du compte ou attribution du rôle impossible.');
            }
            $after = self::employeeForUpdate($id);
            $changes = self::employeeChanges($before, $after);
            if (isset($data['passwd_admin'])) {
                $changes[] = ['field' => 'password_changed', 'label' => 'Mot de passe', 'before' => null, 'after' => 'Modifié'];
            }
            if ($changes !== []) {
                self::audit($actor, 'employee.update', 'id=' . $id, $changes);
            }
            return ['before' => self::publicEmployee($before), 'after' => self::publicEmployee($after)];
        });
    }

    public static function deleteEmployees(array $ids, array $actor): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        if ($ids === [] || min($ids) < 1 || in_array(1, $ids, true)) {
            throw new \InvalidArgumentException('Liste de comptes à supprimer invalide.');
        }
        DatabaseTransaction::run(function () use ($ids, $actor): void {
            $snapshots = [];
            foreach ($ids as $id) {
                $snapshots[$id] = self::employeeForUpdate($id);
                self::protectEmployee($snapshots[$id], $actor);
            }
            if (!(new EmployeeDb())->deleteEmployees($ids)) {
                throw new \RuntimeException('Suppression des comptes impossible.');
            }
            foreach ($snapshots as $id => $before) {
                self::audit($actor, 'employee.delete', 'id=' . $id, self::employeeChanges($before, []));
            }
        });
    }

    public static function createRole(string $name, array $matrix, array $actor): int
    {
        return DatabaseTransaction::run(function () use ($name, $matrix, $actor): int {
            $db = new RoleDb();
            $id = $db->insertRole($name);
            if ($id === false || !$db->savePermissions($id, $matrix)) {
                throw new \RuntimeException('Création du rôle ou de ses permissions impossible.');
            }
            $changes = self::permissionChanges([], $db->fetchPermissionsByRole($id));
            array_unshift($changes, ['field' => 'role_name', 'label' => 'Nom du rôle', 'before' => null, 'after' => $name]);
            self::audit($actor, 'role.create', 'role=' . $id, $changes);
            return $id;
        });
    }

    public static function savePermissions(int $id, array $matrix, array $actor): void
    {
        DatabaseTransaction::run(function () use ($id, $matrix, $actor): void {
            $role = Layer::getInstance()->fetch('SELECT id_role FROM mc_admin_role_user WHERE id_role = :id FOR UPDATE', ['id' => $id]);
            if ($role === false) {
                throw new \RuntimeException('Rôle introuvable.');
            }
            $db = new RoleDb();
            $before = $db->fetchPermissionsByRole($id);
            if (!$db->savePermissions($id, $matrix)) {
                throw new \RuntimeException('Enregistrement des permissions impossible.');
            }
            $changes = self::permissionChanges($before, $db->fetchPermissionsByRole($id));
            if ($changes !== []) {
                self::audit($actor, 'role.permissions', 'role=' . $id, $changes);
            }
        });
    }

    private static function employeeForUpdate(int $id): array
    {
        $row = Layer::getInstance()->fetch(
            'SELECT e.*, rel.id_role FROM mc_admin_employee e LEFT JOIN mc_admin_access_rel rel ON e.id_admin = rel.id_admin WHERE e.id_admin = :id FOR UPDATE',
            ['id' => $id]
        );
        if ($row === false) {
            throw new \RuntimeException('Compte administrateur introuvable.');
        }
        return $row;
    }

    /** Recontrôle sous verrou, après la validation du formulaire. */
    private static function protectEmployee(array $target, array $actor): void
    {
        if ((int)($target['id_admin'] ?? 0) === 1 || (int)($target['id_role'] ?? 0) === 1) {
            $current = (new EmployeeDb())->fetchEmployeeById((int)($actor['id_admin'] ?? 0));
            if ((int)($current['id_role'] ?? 0) !== 1) {
                throw new \RuntimeException('Modification d’un compte disposant de tous les droits non autorisée.');
            }
        }
    }

    private static function protectRoleAssignment(int $role, array $actor): void
    {
        if ($role === 1) {
            $current = (new EmployeeDb())->fetchEmployeeById((int)($actor['id_admin'] ?? 0));
            if ((int)($current['id_role'] ?? 0) !== 1) {
                throw new \RuntimeException('Attribution du rôle Administrateur non autorisée.');
            }
        }
    }

    private static function publicEmployee(array $data): array
    {
        return array_intersect_key($data, self::EMPLOYEE_FIELDS);
    }

    private static function employeeChanges(array $before, array $after): array
    {
        $changes = [];
        foreach (self::EMPLOYEE_FIELDS as $field => $label) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if (in_array($field, ['id_admin', 'id_role', 'active_admin'], true)) {
                $old = $old !== null ? (int)$old : null;
                $new = $new !== null ? (int)$new : null;
            }
            if ($old !== $new) {
                $changes[] = ['field' => $field, 'label' => $label, 'before' => $old, 'after' => $new];
            }
        }
        return $changes;
    }

    private static function permissionChanges(array $before, array $after): array
    {
        $modules = array_column((new RoleDb())->fetchAllModules(), 'name', 'id_module');
        $ids = array_unique(array_merge(array_keys($before), array_keys($after)));
        sort($ids, SORT_NUMERIC);
        $changes = [];
        foreach ($ids as $id) {
            foreach (self::RIGHTS as $right => $label) {
                $old = (int)($before[$id][$right] ?? 0);
                $new = (int)($after[$id][$right] ?? 0);
                if ($old !== $new) {
                    $changes[] = [
                        'field' => 'permissions.' . $id . '.' . $right,
                        'label' => ($modules[$id] ?? ('#' . $id)) . ' — ' . $label,
                        'before' => $old, 'after' => $new,
                    ];
                }
            }
        }
        return $changes;
    }

    private static function audit(array $actor, string $action, string $target, array $changes): void
    {
        $id = (new AuditLogDb())->insert([
            'id_admin' => $actor['id_admin'] ?? null,
            'email_admin' => $actor['email_admin'] ?? null,
            'ip_log' => $actor['ip_log'] ?? null,
            'action_log' => $action, 'target_log' => $target,
            'details_log' => ['changes' => $changes],
        ]);
        if ($id === false) {
            throw new \RuntimeException('Écriture du journal d’audit impossible.');
        }
    }
}
