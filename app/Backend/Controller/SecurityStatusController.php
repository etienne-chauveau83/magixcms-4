<?php

declare(strict_types=1);

namespace App\Backend\Controller;

use App\Backend\Model\SecurityAudit;

/**
 * Tableau de bord « État de sécurité » : audite en live la configuration de
 * l'instance (PHP, serveur, application, comptes) et la présente par sévérité
 * et par catégorie. Lecture seule.
 */
class SecurityStatusController extends BaseController
{
    public function run(): void
    {
        $this->index();
    }

    private function index(): void
    {
        $checks  = SecurityAudit::run($this->siteSettings);
        $summary = SecurityAudit::summary($checks);

        // Regroupement par catégorie, ordre du plus actionnable au plus technique.
        $order = ['Comptes', 'Application', 'Serveur', 'PHP'];
        $grouped = [];
        foreach ($order as $cat) {
            $grouped[$cat] = [];
        }
        foreach ($checks as $c) {
            $grouped[$c['cat']][] = $c;
        }
        $grouped = array_filter($grouped);

        $this->view->assign([
            'grouped' => $grouped,
            'counts'  => $summary['counts'],
            'score'   => $summary['score'],
            'total'   => $summary['total'],
        ]);

        $this->view->display('securitystatus/index.tpl');
    }
}
