<?php
declare(strict_types=1);

namespace App\Component\Db;

use Magepattern\Component\Database\QueryBuilder;
use Magepattern\Component\Database\Layer;
use Magepattern\Component\Debug\Logger;
use App\Component\File\ImageTool;

class GlobalDataDb
{
    /**
     * Exécute la requête de récupération
     */
    protected function executeAll(QueryBuilder $qb): array
    {
        try {
            $layer = Layer::getInstance();
            $result = $layer->fetchAll($qb->getSql(), $qb->getParams());
            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            Logger::getInstance()->log($e, "php", "error", Logger::LOG_MONTH, Logger::LOG_LEVEL_ERROR);
            return [];
        }
    }

    protected function executeRow(QueryBuilder $qb): array
    {
        try {
            $layer = Layer::getInstance();
            $result = $layer->fetch($qb->getSql(), $qb->getParams());
            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            Logger::getInstance()->log($e, "php", "error", Logger::LOG_MONTH, Logger::LOG_LEVEL_ERROR);
            return [];
        }
    }

    /**
     * Récupère toutes les infos de l'entreprise (mc_company_info)
     */
    public function getCompanyInfo(): array
    {
        $qb = new QueryBuilder();
        $qb->select(['name_info', 'value_info'])
            ->from('mc_company_info');

        $results = $this->executeAll($qb);
        $config = [];

        if ($results) {
            foreach ($results as $row) {
                $config[$row['name_info']] = $row['value_info'];
            }
        }
        return $config;
    }

    /**
     * Récupère tous les réseaux sociaux actifs (mc_share_network)
     */
    public function getActiveNetworks(): array
    {
        $qb = new QueryBuilder();
        $qb->select(['name', 'url_share', 'icon'])
            ->from('mc_share_network')
            ->where('is_active = 1')
            ->orderBy('order_share', 'ASC');

        $results = $this->executeAll($qb) ?: [];
        $networks = [];

        foreach ($results as $row) {
            $name = $row['name'] ?? '';
            // On indexe par le nom (en minuscule) car le layout fait souvent : $companyData.socials.facebook
            if (!empty($name)) {
                $networks[strtolower($name)] = [
                    'url'  => $row['url_share'] ?? '',
                    'icon' => $row['icon'] ?? ''
                ];
            }
        }
        return $networks;
    }

    /**
     * Récupère les logos formatés via ImageTool (mc_logo)
     */
    public function getFormattedLogos(): array
    {
        $logos = ['header' => null, 'footer' => null];
        $imageTool = new ImageTool();

        // Logo Principal (Header)
        $qb1 = new QueryBuilder();
        $qb1->select('*')->from('mc_logo')->where('active_logo = 1')->limit(1);
        $header = $this->executeRow($qb1);

        if (!empty($header)) {
            // ALIAS : ImageTool a besoin de 'name_img'. La BDD utilise 'img_logo'
            $header['name_img'] = $header['img_logo'] ?? '';
            $fmt = $imageTool->setModuleImages('logo', 'logo', [$header], 0, '/img/logo/');
            $logos['header'] = $fmt[0] ?? null;
        }

        // Logo Footer
        $qb2 = new QueryBuilder();
        $qb2->select('*')->from('mc_logo')->where('active_footer = 1')->limit(1);
        $footer = $this->executeRow($qb2);

        if (!empty($footer)) {
            $footer['name_img'] = $footer['img_logo'] ?? '';
            $fmt = $imageTool->setModuleImages('logo', 'logo', [$footer], 0, '/img/logo/');
            $logos['footer'] = $fmt[0] ?? null;
        }

        return $logos;
    }

    /**
     * Retourne le "Pack" complet de données pour l'email
     */
    public function getEmailContextData(): array
    {
        $companyData = $this->getCompanyInfo();
        $companyData['socials'] = $this->getActiveNetworks();

        return [
            'companyData' => $companyData,
            'logos'       => $this->getFormattedLogos()
        ];
    }
}