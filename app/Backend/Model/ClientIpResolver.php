<?php

declare(strict_types=1);

namespace App\Backend\Model;

use Magepattern\Component\HTTP\IPMatcher;

/**
 * Résolution de l'IP cliente résistante au spoofing d'en-têtes.
 *
 * Par défaut : REMOTE_ADDR uniquement — aucun en-tête de proxy (X-Forwarded-For,
 * Client-IP…) n'est honoré, donc une IP ne peut pas être usurpée pour contourner
 * un blocage ou se faire passer pour une IP de confiance.
 *
 * Si (et seulement si) REMOTE_ADDR appartient à un proxy de confiance DÉCLARÉ
 * (réglage sec_trusted_proxies : IP/CIDR, ex. plages d'un CDN), on lit alors
 * l'IP cliente réelle dans X-Forwarded-For (le hop le plus à droite qui n'est pas
 * lui-même un proxy de confiance).
 *
 * Remplace l'usage de ClientTool::getIp() (qui faisait confiance à XFF sans
 * vérifier le proxy) pour LoginGuard, le journal d'audit et les alertes.
 */
class ClientIpResolver
{
    public static function resolve(): string
    {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (filter_var($remote, FILTER_VALIDATE_IP) === false) {
            return '0.0.0.0';
        }
        $remote = inet_ntop(inet_pton($remote));

        $trusted = self::trustedProxies();
        // Pas de proxy de confiance, ou la requête n'en vient pas → IP directe.
        if (empty($trusted) || !self::ipInList($remote, $trusted)) {
            return $remote;
        }

        // REMOTE_ADDR est un proxy de confiance → l'IP cliente est dans XFF.
        $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff === '') {
            return $remote;
        }

        // XFF = "client, proxy1, proxy2" : on prend le hop le plus à DROITE qui
        // n'est pas un proxy de confiance = l'IP cliente réelle non falsifiable.
        $hops = array_map('trim', explode(',', $xff));
        for ($i = count($hops) - 1; $i >= 0; $i--) {
            $ip = $hops[$i];
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                // Ne jamais traverser un hop dont la provenance est inconnue.
                return $remote;
            }
            $ip = inet_ntop(inet_pton($ip));
            if (!self::ipInList($ip, $trusted)) {
                return $ip;
            }
        }
        // Aucun client non approuvé dans la chaîne : conserver le pair direct.
        return $remote;
    }

    /**
     * Liste des proxies de confiance (IP/CIDR) depuis la config sécurité.
     *
     * @return string[]
     */
    public static function trustedProxies(): array
    {
        $raw = SecurityConfig::str('sec_trusted_proxies', '');
        if ($raw === '') {
            return [];
        }
        return preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** Vrai si $ip ∈ une des entrées (IP exacte ou CIDR) de la liste. */
    public static function ipInList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            $entry = trim((string)$entry);
            if (!self::isValidCidrOrIp($entry)) {
                continue;
            }
            try {
                if (IPMatcher::checkIp($ip, $entry)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // entrée invalide (ex. familles IPv4/IPv6 mixtes) : ignorée
            }
        }
        return false;
    }

    /** Valide une entrée de proxy de confiance : IP exacte ou notation CIDR. */
    public static function isValidCidrOrIp(string $entry): bool
    {
        $entry = trim($entry);
        if ($entry === '') {
            return false;
        }
        if (strpos($entry, '/') === false) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }
        [$subnet, $mask] = explode('/', $entry, 2);
        if (filter_var($subnet, FILTER_VALIDATE_IP) === false || !ctype_digit($mask)) {
            return false;
        }
        $max = (strpos($subnet, ':') !== false) ? 128 : 32;
        $m = (int)$mask;
        return $m >= 0 && $m <= $max;
    }
}
