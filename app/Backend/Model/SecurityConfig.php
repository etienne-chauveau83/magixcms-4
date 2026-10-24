<?php

declare(strict_types=1);

namespace App\Backend\Model;

/**
 * Accès centralisé aux réglages de sécurité (table mc_setting, catégorie
 * "security"). Le cache est amorcé une fois par requête par BaseController
 * (prime()) avec les réglages déjà chargés en masse, et ré-amorcé par
 * SettingController après une sauvegarde — SecurityConfig n'émet donc AUCUNE
 * requête SQL propre. Chaque getter retourne une valeur par défaut si le
 * réglage est absent ou vide → aucune régression si un réglage n'est pas
 * encore seedé.
 */
class SecurityConfig
{
    private static ?array $cache = null;

    /**
     * Amorce le cache avec les réglages déjà chargés (évite toute lecture SQL
     * dédiée). Appelé par BaseController::__construct, et par SettingController
     * après sauvegarde (valeurs fraîches).
     *
     * @param array<string,array<string,mixed>> $settings
     */
    public static function prime(array $settings): void
    {
        self::$cache = $settings;
    }

    private static function all(): array
    {
        return self::$cache ?? [];
    }

    private static function raw(string $key): ?string
    {
        $v = self::all()[$key]['value'] ?? null;
        return ($v === null || $v === '') ? null : (string)$v;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::raw($key);
        if ($v === null || !is_numeric($v)) {
            return $default;
        }
        $n = (int)$v;
        return $n > 0 ? $n : $default;
    }

    public static function enabled(string $key, bool $default = true): bool
    {
        $v = self::raw($key);
        if ($v === null) {
            return $default;
        }
        return (int)$v === 1;
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = self::raw($key);
        return $v ?? $default;
    }
}
