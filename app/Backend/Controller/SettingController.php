<?php

declare(strict_types=1);

namespace App\Backend\Controller;

use App\Backend\Db\SettingDb;
use App\Backend\Model\SecurityConfig;
use App\Backend\Model\ClientIpResolver;
use App\Backend\Model\AlertService;
use Magepattern\Component\HTTP\Request;
use Magepattern\Component\Tool\FormTool;
// Si vous avez un composant Url pour récupérer l'URL de base, incluez-le
use Magepattern\Component\HTTP\Url;
use App\Backend\Db\DomainDb;
use App\Component\Cache\CacheManager;

class SettingController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $action = $_GET['action'] ?? 'index';

        if ($action === 'testAlert') {
            $this->testAlert();
            return;
        }

        if ($action === 'save' && Request::isMethod('POST')) {
            $this->processSave();
            return;
        }

        $this->index();
    }

    /**
     * @return void
     * @throws \Smarty\Exception
     */
    private function index(): void
    {
        $db = new SettingDb();
        $settings = $db->fetchAllSettings();

        $this->view->assign([
            'settings'  => $settings,
            'alert_mail_status' => AlertService::mailStatus(),
            'hashtoken' => $this->session->getToken()
        ]);

        $this->view->display('setting/index.tpl');
    }

    private function testAlert(): void
    {
        if (!Request::isMethod('POST')) {
            $this->jsonResponse(false, 'Méthode non autorisée.');
        }
        $token = $_POST['hashtoken'] ?? '';
        if (!is_string($token) || !$this->session->validateToken($token)) {
            $this->jsonResponse(false, 'Session expirée.');
        }
        $email = $_POST['email'] ?? null;
        if ($email !== null && !is_string($email)) {
            $this->jsonResponse(false, 'Destinataire invalide.');
        }
        $result = AlertService::test($email);
        $this->logAudit($result['success'] ? 'alert.test_sent' : 'alert.test_failed',
            $result['recipient'] ?? $result['message']);
        $this->jsonResponse($result['success'], $result['message']);
    }

    /**
     * @return void
     */
    private function processSave(): void
    {
        $token = $_POST['hashtoken'] ?? '';
        if (!$this->session->validateToken($token)) {
            $this->jsonResponse(false, 'Session expirée.');
        }

        $db = new SettingDb();
        $success = true;

        // Liste blanche des réglages réellement modifiables via ce formulaire.
        // Tout autre nom (ex: "theme", qui relocalise les répertoires Smarty et
        // possède son propre contrôleur) est ignoré : empêche l'écriture de
        // réglages système arbitraires via cet endpoint générique.
        $editableSettings = [
            'analytics', 'cache', 'calendar_enabled', 'content_css', 'geminiai',
            'holder_bgcolor', 'logo_percent', 'maintenance', 'mode', 'news_per_page',
            'price_display', 'product_catalog', 'product_per_page', 'robots',
            'ssl', 'vat_rate'
        ];

        $booleanKeys = [
            'ssl', 'maintenance', 'geminiai', 'product_catalog', 'calendar_enabled'
        ];

        $postedSettings = $_POST['settings'] ?? [];
        if (!is_array($postedSettings)) {
            $this->jsonResponse(false, 'Paramètres invalides.');
        }
        foreach ($postedSettings as $value) {
            if (!is_string($value)) {
                $this->jsonResponse(false, 'Paramètres invalides.');
            }
        }

        // Validation préalable de l'e-mail d'alerte de sécurité (avant toute écriture).
        $secAlertEmail = trim((string)($postedSettings['sec_alert_email'] ?? ''));
        if ($secAlertEmail !== '' && !filter_var($secAlertEmail, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(false, "Adresse e-mail d'alerte de sécurité invalide.");
        }

        // Proxies de confiance : chaque entrée doit être une IP ou un CIDR valide.
        $proxiesRaw  = trim((string)($postedSettings['sec_trusted_proxies'] ?? ''));
        $proxyTokens = $proxiesRaw === '' ? [] : (preg_split('/[\s,]+/', $proxiesRaw, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        foreach ($proxyTokens as $tok) {
            if (!ClientIpResolver::isValidCidrOrIp($tok)) {
                $this->jsonResponse(false, 'Proxy de confiance invalide : ' . $tok);
            }
        }

        foreach ($booleanKeys as $key) {
            if (!isset($postedSettings[$key])) {
                $postedSettings[$key] = '0';
            }
        }

        foreach ($postedSettings as $name => $value) {
            if (!in_array($name, $editableSettings, true)) {
                continue; // réglage non éditable via ce formulaire : ignoré
            }

            $cleanValue = FormTool::simpleClean((string)$value);

            if (!$db->updateSetting($name, $cleanValue)) {
                $success = false;
            }
        }

        // --- Réglages de sécurité (onglet "Sécurité") ---
        // Traités à part des réglages génériques : chaque entier est borné et
        // chaque interrupteur normalisé en '0'/'1' selon sa présence dans le POST.
        $clampInt = static function (string $key, int $min, int $max, int $def) use ($postedSettings): int {
            $v = isset($postedSettings[$key]) && is_numeric($postedSettings[$key]) ? (int)$postedSettings[$key] : $def;
            return max($min, min($max, $v));
        };

        $securityValues = [
            'sec_audit_retention_days' => (string)$clampInt('sec_audit_retention_days', 1, 3650, 90),
            'sec_audit_max_rows'       => (string)$clampInt('sec_audit_max_rows', 100, 10000000, 50000),
            'sec_bruteforce_max'       => (string)$clampInt('sec_bruteforce_max', 1, 1000, 5),
            'sec_bruteforce_window'    => (string)$clampInt('sec_bruteforce_window', 1, 1440, 15),
            'sec_superadmin_threshold' => (string)$clampInt('sec_superadmin_threshold', 1, 100, 3),
            'sec_bruteforce_enabled'   => isset($postedSettings['sec_bruteforce_enabled']) ? '1' : '0',
            'sec_alert_bruteforce'     => isset($postedSettings['sec_alert_bruteforce']) ? '1' : '0',
            'sec_alert_superadmin'     => isset($postedSettings['sec_alert_superadmin']) ? '1' : '0',
            'sec_dashboard_widget'     => isset($postedSettings['sec_dashboard_widget']) ? '1' : '0',
            'sec_alert_email'          => $secAlertEmail,
            'sec_trusted_proxies'      => implode(',', $proxyTokens),
        ];

        foreach ($securityValues as $name => $value) {
            if (!$db->updateSetting($name, $value)) {
                $success = false;
            }
        }

        // Ré-amorce le cache sécurité avec les valeurs fraîches (aucune requête).
        $primed = $this->siteSettings;
        foreach ($securityValues as $name => $value) {
            $primed[$name]['value'] = $value;
        }
        SecurityConfig::prime($primed);

        if ($success && isset($postedSettings['robots'])) {
            $this->generateRobotsTxt($postedSettings);
        }

        if ($success) {
            CacheManager::clearFrontend('settings');

            $changedKeys = array_keys(array_intersect_key($postedSettings, array_flip($editableSettings)) + $securityValues);
            $this->logAudit('setting.update', implode(',', $changedKeys));
            $this->jsonResponse(true, 'La configuration a été mise à jour avec succès.', [
                'type' => 'update'
            ]);
        } else {
            $this->jsonResponse(false, 'Erreur lors de la mise à jour des paramètres.');
        }
    }

    /**
     * Génère le fichier robots.txt à la racine du projet en incluant tous les sitemaps mères
     */
    /**
     * @param array $postedSettings
     * @return void
     */
    private function generateRobotsTxt(array $postedSettings): void
    {
        $robotsMode = $postedSettings['robots'];
        $robotsFile = ROOT_DIR . 'robots.txt';
        $content = "User-Agent: *\n";

        if ($robotsMode === 'noindex,nofollow') {
            $content .= "Disallow: /\n";
        } else {
            $content .= "Allow: /\n\n";

            // 1. Détermination du protocole avec la donnée fraîchement sauvegardée
            $isSsl = (int)($postedSettings['ssl'] ?? 0);
            $protocol = ($isSsl === 1) ? 'https://' : 'http://';

            // 2. Récupération de tous les domaines configurés
            $domainDb = new DomainDb();
            // On force une limite très haute (ex: 1000) pour récupérer tous les domaines
            // en une seule fois, au cas où votre méthode fetchAllDomains utiliserait la pagination.
            $domainsResult = $domainDb->fetchAllDomains(1, 1000, []);
            $domains = $domainsResult['data'] ?? [];

            // 3. Boucle sur chaque domaine pour inscrire son Sitemap mère
            if (!empty($domains)) {
                foreach ($domains as $domain) {
                    $rawUrl = rtrim($domain['url_domain'], '/');
                    $cleanDomainName = str_replace(['http://', 'https://'], '', $rawUrl);

                    $baseUrl = $protocol . $cleanDomainName;

                    // Exactement le même format que dans votre DomainController
                    $content .= "Sitemap: {$baseUrl}/sitemap-{$cleanDomainName}.xml\n";
                }
            } else {
                // Fallback de sécurité (si aucun domaine n'est défini en BDD)
                $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $baseUrl = $protocol . $domainName;
                $content .= "Sitemap: {$baseUrl}/sitemap.xml\n";
            }
        }

        // Écriture du fichier avec les droits standards (écrase le contenu précédent)
        file_put_contents($robotsFile, $content);
    }
}
