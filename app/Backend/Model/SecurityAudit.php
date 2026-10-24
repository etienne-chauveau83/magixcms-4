<?php

declare(strict_types=1);

namespace App\Backend\Model;

use App\Backend\Db\DomainDb;
use App\Backend\Db\EmployeeDb;

/**
 * Audit de configuration de l'instance. Réutilisable par le dashboard
 * « État de sécurité » (liste complète) et par le widget du Dashboard
 * principal (score). Chaque vérification renvoie :
 *   key, label, status (critical|warning|info|ok), detail, reco, cat, link, link_label
 */
class SecurityAudit
{
    /**
     * @return array<int,array<string,string>>
     */
    public static function run(array $siteSettings = []): array
    {
        $checks = [];

        // ---------------------------------------------------------------- PHP
        $checks[] = self::iniCheck('PHP', 'allow_url_include', false, 'critical',
            'Permet l\'inclusion de fichiers distants — vecteur RCE direct.',
            'Mettre allow_url_include = Off dans php.ini.');
        $checks[] = self::iniCheck('PHP', 'register_argc_argv', false, 'warning',
            'Précondition d\'une chaîne LFI→RCE via pearcmd.',
            'Mettre register_argc_argv = Off.');
        $checks[] = self::iniCheck('PHP', 'display_errors', false, 'warning',
            'Expose des messages d\'erreur (divulgation d\'information) en production.',
            'Mettre display_errors = Off en production.');
        $checks[] = self::iniCheck('PHP', 'expose_php', false, 'info',
            'Révèle la version de PHP dans l\'en-tête X-Powered-By.',
            'Mettre expose_php = Off.');

        $uploadProgress = (bool)ini_get('session.upload_progress.enabled');
        $checks[] = self::make('session.upload_progress', 'PHP',
            'session.upload_progress.enabled = ' . ($uploadProgress ? 'On' : 'Off'),
            $uploadProgress ? 'info' : 'ok',
            $uploadProgress ? 'Activé — peut servir au « session poisoning ».' : 'Désactivé.',
            $uploadProgress ? 'Désactiver si inutilisé.' : '');

        $checks[] = self::phpVersionCheck();

        // ------------------------------------------------------------ Serveur
        $pear = self::pearcmdPresent();
        $checks[] = self::make('pearcmd', 'Serveur', 'pearcmd.php',
            $pear ? 'warning' : 'ok',
            $pear ? 'Présent — exploitable dans une chaîne LFI→RCE.' : 'Absent.',
            $pear ? 'Retirer pearcmd.php du serveur si PEAR est inutilisé.' : '');

        $installPresent = is_dir(ROOT_DIR . 'install');
        $checks[] = self::make('install_dir', 'Serveur', 'Dossier install/',
            $installPresent ? 'warning' : 'ok',
            $installPresent ? 'Toujours présent après installation.' : 'Supprimé.',
            $installPresent ? 'Supprimer le dossier install/ après installation.' : '');

        $ww = [];
        foreach (['media', 'var', 'upload'] as $d) {
            $path = ROOT_DIR . $d;
            if (is_dir($path) && (fileperms($path) & 0002)) {
                $ww[] = $d . ' (' . substr(sprintf('%o', fileperms($path)), -4) . ')';
            }
        }
        $checks[] = self::make('dir_perms', 'Serveur', 'Permissions des dossiers inscriptibles',
            empty($ww) ? 'ok' : 'warning',
            empty($ww) ? 'Aucun dossier accessible en écriture par « autres ».' : 'Écriture par « autres » : ' . implode(', ', $ww) . '.',
            empty($ww) ? '' : 'Retirer le bit d\'écriture « autres » sur ces dossiers.');

        // La configuration locale ne prouve pas les en-têtes de la réponse
        // finale (proxy, AllowOverride, règles conditionnelles...).
        $modHeaders = function_exists('apache_get_modules') ? in_array('mod_headers', apache_get_modules(), true) : null;
        $ht = @file_get_contents(ROOT_DIR . '.htaccess');
        $declared = ($ht !== false && stripos($ht, 'X-Content-Type-Options') !== false);
        if ($modHeaders === false) {
            $checks[] = self::make('sec_headers', 'Serveur', 'En-têtes de sécurité (nosniff / CSP / HSTS)',
                'warning', 'mod_headers absent : les en-têtes de sécurité ne sont pas appliqués.',
                'Activer mod_headers (a2enmod headers).');
        } elseif ($modHeaders === null) {
            $checks[] = self::make('sec_headers', 'Serveur', 'En-têtes de sécurité (nosniff / CSP / HSTS)',
                'info', 'Serveur non-Apache : vérifier manuellement l\'envoi de X-Content-Type-Options / CSP / HSTS.',
                'Configurer les en-têtes de sécurité au niveau du serveur.');
        } else {
            $checks[] = self::make('sec_headers', 'Serveur', 'En-têtes de sécurité (nosniff / CSP / HSTS)',
                $declared ? 'info' : 'warning',
                $declared ? 'mod_headers chargé et déclaration trouvée dans .htaccess ; vérifier les en-têtes des réponses publiques.' : 'mod_headers chargé mais aucun en-tête de sécurité déclaré.',
                $declared ? 'Vérifier X-Content-Type-Options / CSP / HSTS dans une réponse HTTPS.' : 'Déclarer X-Content-Type-Options / CSP / HSTS dans le .htaccess.');
        }

        // Résolution d'IP derrière un proxy / CDN (exactitude + anti-spoofing)
        $trustedProxies = ClientIpResolver::trustedProxies();
        $xffPresent = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_CLIENT_IP']);
        if (!empty($trustedProxies)) {
            $checks[] = self::make('proxy_resolution', 'Serveur', 'Résolution d\'IP derrière proxy',
                'ok', count($trustedProxies) . ' proxy(ies) de confiance déclaré(s) : l\'IP cliente réelle est résolue derrière le proxy.',
                '', 'index.php?controller=Setting', 'Réglages');
        } elseif ($xffPresent) {
            $checks[] = self::make('proxy_resolution', 'Serveur', 'Résolution d\'IP derrière proxy',
                'warning', 'Un en-tête X-Forwarded-For est présent mais aucun proxy de confiance n\'est déclaré : les en-têtes sont ignorés (anti-usurpation), mais derrière un CDN non déclaré tous les visiteurs partageraient une seule IP.',
                'Déclarer vos proxies/CDN dans les réglages de sécurité (ou laisser vide si le site n\'est pas derrière un proxy).',
                'index.php?controller=Setting', 'Réglages');
        } else {
            $checks[] = self::make('proxy_resolution', 'Serveur', 'Résolution d\'IP derrière proxy',
                'ok', 'Aucun proxy détecté : l\'IP cliente est lue directement (REMOTE_ADDR).', '');
        }

        // -------------------------------------------------------- Application
        $mode  = (string)($siteSettings['mode']['value'] ?? '');
        $isDev = ($mode === 'dev' || $mode === '1');
        $checks[] = self::make('mode', 'Application', 'Mode de l\'application',
            $isDev ? 'warning' : 'ok',
            $isDev ? 'Mode « dev » actif : le bouclier de validation du host-header est désactivé.' : 'Mode production.',
            $isDev ? 'Passer en mode production une fois en ligne.' : '',
            'index.php?controller=Setting', 'Réglages');

        $ssl = (int)($siteSettings['ssl']['value'] ?? 0);
        $checks[] = self::make('ssl', 'Application', 'HTTPS (réglage ssl)',
            $ssl === 1 ? 'ok' : 'warning',
            $ssl === 1 ? 'Activé dans la configuration.' : 'Désactivé dans la configuration ; vérifier HTTPS au niveau du serveur ou du proxy.',
            $ssl === 1 ? '' : 'Activer SSL et forcer HTTPS.',
            'index.php?controller=Setting', 'Réglages');

        $allowed = [];
        try { $allowed = (new DomainDb())->getAllowedHosts(); } catch (\Throwable $e) {}
        $domainEmpty = empty($allowed);
        $checks[] = self::make('mc_domain', 'Application', 'Domaines autorisés (mc_domain)',
            $domainEmpty ? 'warning' : 'ok',
            $domainEmpty ? 'Aucun domaine déclaré : la validation du host-header est inopérante.' : count($allowed) . ' domaine(s) déclaré(s).',
            $domainEmpty ? 'Déclarer le(s) domaine(s) de production.' : '',
            'index.php?controller=Domain', 'Gérer les domaines');

        $alertsEnabled = SecurityConfig::enabled('sec_alert_bruteforce') || SecurityConfig::enabled('sec_alert_superadmin');
        $mail = AlertService::mailStatus();
        $mailReady = $mail['smtp_enabled'] && $mail['can_send'];
        $checks[] = self::make('security_alerts', 'Application', 'Envoi des alertes de sécurité',
            !$alertsEnabled ? 'info' : ($mailReady ? 'ok' : 'warning'),
            !$alertsEnabled ? 'Les alertes e-mail sont désactivées.' : ($mailReady
                ? 'Configuration SMTP renseignée ; l’envoi peut être vérifié avec une alerte de test.'
                : implode(' ', array_merge($mail['smtp_enabled'] ? [] : ['SMTP non configuré : envoi via la messagerie du serveur.'], $mail['issues']))),
            $alertsEnabled ? 'Vérifier la configuration e-mail et envoyer une alerte de test.' : '',
            'index.php?controller=Setting#security', 'Réglages des alertes');

        // ----------------------------------------------------------- Comptes
        $accounts = false;
        try { $accounts = (new EmployeeDb())->fetchAccountsForAudit(); } catch (\Throwable $e) {}
        if ($accounts === false) {
            $checks[] = self::make('accounts_unavailable', 'Comptes', 'Audit des comptes indisponible',
                'warning', 'Impossible de lire les comptes administrateurs.',
                'Vérifier la connexion à la base et les journaux du serveur.');
            return $checks;
        }

        $emptyPw = [];
        $creators = 0;
        $administrators = 0;
        $inactive = 0;
        foreach ($accounts as $a) {
            $hash = (string)($a['passwd_admin'] ?? '');
            if ($hash === '' || password_verify('', $hash)) {
                $emptyPw[] = $a['email_admin'] ?? ('#' . ($a['id_admin'] ?? '?'));
            }
            if ((int)($a['id_role'] ?? 0) === 1) {
                if ((int)$a['id_admin'] === 1) {
                    $creators++;
                } else {
                    $administrators++;
                }
            }
            if ((int)($a['active_admin'] ?? 1) === 0) {
                $inactive++;
            }
        }

        $checks[] = self::make('empty_password', 'Comptes',
            empty($emptyPw) ? 'Mots de passe administrateurs' : count($emptyPw) . ' compte(s) à mot de passe vide',
            empty($emptyPw) ? 'ok' : 'critical',
            empty($emptyPw) ? 'Aucun compte administrateur avec un mot de passe vide.' : 'Connexion possible sans mot de passe — comptes : ' . implode(', ', $emptyPw) . '.',
            empty($emptyPw) ? '' : 'Définir immédiatement un mot de passe fort pour ces comptes.',
            empty($emptyPw) ? '' : 'index.php?controller=Employee',
            empty($emptyPw) ? '' : 'Gérer les administrateurs');

        $privilegedCount = $creators + $administrators;
        $privilegedThreshold = SecurityConfig::int('sec_superadmin_threshold', 3);
        $checks[] = self::make('super_admins', 'Comptes', 'Créateur et administrateurs',
            $privilegedCount > $privilegedThreshold ? 'warning' : 'info',
            'Créateur : ' . $creators . ' ; administrateurs : ' . $administrators . '. Ces comptes disposent de tous les droits.',
            $privilegedCount > $privilegedThreshold ? 'Limiter le nombre de comptes disposant de tous les droits (surface d\'attaque).' : 'Vérifier que chacun est légitime.',
            'index.php?controller=Employee', 'Gérer les administrateurs');

        $checks[] = self::make('inactive_accounts', 'Comptes', 'Comptes administrateurs inactifs',
            $inactive === 0 ? 'ok' : 'info',
            $inactive === 0 ? 'Aucun compte inactif.' : $inactive . ' compte(s) inactif(s) encore présent(s).',
            $inactive === 0 ? '' : 'Supprimer les comptes inactifs devenus inutiles.',
            $inactive === 0 ? '' : 'index.php?controller=Employee',
            $inactive === 0 ? '' : 'Gérer les administrateurs');

        return $checks;
    }

    /**
     * Compteurs par sévérité + score.
     *
     * @return array{counts:array<string,int>,total:int,score:int}
     */
    public static function summary(array $checks): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0];
        foreach ($checks as $c) {
            $counts[$c['status']]++;
        }
        $total = count($checks);
        // Les constats purement informatifs ne sont pas des non-conformités.
        $assessed = $total - $counts['info'];
        $score = $assessed > 0 ? (int)round(($counts['ok'] / $assessed) * 100) : 100;
        return ['counts' => $counts, 'total' => $total, 'score' => $score];
    }

    /**
     * Vérifie la version de PHP contre le minimum requis par MagixCMS 4 (8.2)
     * et les dates de fin de support SÉCURITÉ de php.net. Reste "ok" tant que la
     * branche est supportée (la date de fin est affichée, sans fausse alarme) ;
     * "critical" sous le minimum requis ou en fin de vie réelle.
     *
     * @return array<string,string>
     */
    private static function phpVersionCheck(): array
    {
        // https://www.php.net/supported-versions.php — à tenir à jour.
        $eol = [
            '8.2' => '2026-12-31',
            '8.3' => '2027-12-31',
            '8.4' => '2028-12-31',
            '8.5' => '2029-12-31',
        ];
        $label = 'Version de PHP (' . PHP_VERSION . ')';

        // Sous le minimum requis par MagixCMS 4 (et de toute façon en fin de vie).
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            return self::make('php_version', 'PHP', $label, 'critical',
                'Sous le minimum requis par MagixCMS 4 (PHP 8.2) et en fin de vie — plus de correctifs de sécurité.',
                'Mettre à jour vers PHP 8.3 ou 8.4.');
        }

        $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        if (!isset($eol[$branch])) {
            return self::make('php_version', 'PHP', $label, 'info',
                'Branche absente du calendrier local de support.',
                'Vérifier le calendrier de support sur php.net.');
        }

        if (date('Y-m-d') > $eol[$branch]) {
            return self::make('php_version', 'PHP', $label, 'critical',
                'Version en fin de vie — plus de correctifs de sécurité.',
                'Mettre à jour vers une branche PHP encore supportée (8.3 / 8.4).');
        }

        return self::make('php_version', 'PHP', $label, 'ok', 'Version supportée.', '');
    }

    private static function make(string $key, string $cat, string $label, string $status, string $detail, string $reco, string $link = '', string $linkLabel = ''): array
    {
        return [
            'key'        => $key,
            'cat'        => $cat,
            'label'      => $label,
            'status'     => $status,
            'detail'     => $detail,
            'reco'       => $reco,
            'link'       => $link,
            'link_label' => $linkLabel,
        ];
    }

    private static function iniCheck(string $cat, string $directive, bool $wantOn, string $failStatus, string $riskDetail, string $reco): array
    {
        $on  = (bool)ini_get($directive);
        $bad = ($on !== $wantOn);
        return self::make($directive, $cat, $directive . ' = ' . ($on ? 'On' : 'Off'),
            $bad ? $failStatus : 'ok',
            $bad ? $riskDetail : 'Conforme.',
            $bad ? $reco : '');
    }

    private static function pearcmdPresent(): bool
    {
        foreach (explode(PATH_SEPARATOR, (string)ini_get('include_path')) as $dir) {
            $dir = trim($dir);
            if ($dir === '' || $dir === '.') {
                continue;
            }
            if (@file_exists(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'pearcmd.php')) {
                return true;
            }
        }
        foreach (['/usr/share/php/pearcmd.php', '/usr/local/lib/php/pearcmd.php'] as $p) {
            if (@file_exists($p)) {
                return true;
            }
        }
        return false;
    }
}
