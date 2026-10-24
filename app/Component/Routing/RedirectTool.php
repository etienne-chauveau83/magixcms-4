<?php

declare(strict_types=1);

namespace App\Component\Routing;

use Magepattern\Component\Database\QueryBuilder;
use App\Frontend\Db\BaseDb;
use Magepattern\Component\HTTP\Request;

class RedirectTool extends BaseDb
{
    /**
     * Vérifie si l'URL courante possède une redirection active
     * et l'exécute immédiatement le cas échéant.
     */
    public function checkAndRedirect(): void
    {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($currentUri, PHP_URL_PATH);

        if (empty($path) || $path === '/') {
            return;
        }

        $qb = new QueryBuilder();
        $qb->select(['new_url', 'type_redirect'])
            ->from('mc_seo_redirect')
            ->where('old_url = :url', ['url' => $path])
            ->where('active = 1');

        $redirect = $this->executeRow($qb);

        if ($redirect) {
            $type = (int)$redirect['type_redirect'];
            $newUrl = (string)$redirect['new_url'];

            // -----------------------------------------------------------
            // CORRECTION SÉCURITÉ : Prévention "Open Redirect"
            // -----------------------------------------------------------
            $parsedHost   = parse_url($newUrl, PHP_URL_HOST);
            $parsedScheme = parse_url($newUrl, PHP_URL_SCHEME);

            // On récupère le domaine actuel en retirant le port éventuel (ex: :8000)
            $currentHost = isset($_SERVER['HTTP_HOST']) ? explode(':', strtolower($_SERVER['HTTP_HOST']))[0] : '';

            // A. On bloque les protocoles dangereux (ex: javascript:, data:)
            if (!empty($parsedScheme) && !in_array(strtolower($parsedScheme), ['http', 'https'])) {
                return;
            }

            // B. Si un domaine est précisé dans l'URL de destination,
            // on vérifie qu'il correspond exactement au domaine actuel.
            if (!empty($parsedHost) && strtolower($parsedHost) !== $currentHost) {
                return; // Annulation silencieuse de la redirection externe
            }

            // C. Blocage des URL relatives de protocole (ex: //evil.example.com)
            // parse_url attrape généralement ce cas, mais sécurité additionnelle :
            if (empty($parsedHost) && str_starts_with($newUrl, '//')) {
                return;
            }
            // -----------------------------------------------------------

            // Sécurité anti-boucle infinie (si old_url == new_url)
            if ($path === parse_url($newUrl, PHP_URL_PATH)) {
                return;
            }

            http_response_code($type);
            header("Location: " . $newUrl, true, $type);
            exit;
        }
    }
}