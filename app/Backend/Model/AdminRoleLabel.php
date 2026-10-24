<?php

declare(strict_types=1);

namespace App\Backend\Model;

/** Libellés d'affichage uniquement : ne modifie ni les rôles ni les permissions. */
final class AdminRoleLabel
{
    public static function name(string $name): string
    {
        return match ($name) {
            'administrator' => 'Administrateur',
            'editor' => 'Éditeur',
            default => $name,
        };
    }

    public static function account(array $account): string
    {
        return (int)($account['id_admin'] ?? 0) === 1
            ? 'Créateur'
            : self::name((string)($account['role_name'] ?? ''));
    }

    public static function roles(array $roles): array
    {
        foreach ($roles as &$role) {
            $role['role_label'] = self::name((string)$role['role_name']);
        }
        unset($role);
        return $roles;
    }
}
