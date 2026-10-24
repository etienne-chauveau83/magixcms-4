<?php

declare(strict_types=1);

namespace App\Backend\Db;

use Magepattern\Component\Database\QueryBuilder;
use App\Backend\Model\SecurityConfig;

/**
 * Journal d'audit des actions sensibles du back-office.
 *
 * Rétention : le journal est borné pour éviter une croissance illimitée
 * (notamment un flood de "login.failure" sous attaque). Deux limites,
 * appliquées de façon amortie à l'insertion (et réutilisables par un cron) :
 *  - RETENTION_DAYS : ancienneté maximale des entrées ;
 *  - MAX_ROWS       : nombre maximal d'entrées conservées (les plus récentes).
 */
class AuditLogDb extends BaseDb
{
    public const RETENTION_DAYS = 90;
    public const MAX_ROWS       = 50000;

    private const SELECT_COLS = [
        'id_log', 'id_admin', 'email_admin', 'action_log', 'target_log', 'details_log', 'ip_log', 'date_register'
    ];

    /**
     * Enregistre une entrée d'audit, puis purge le journal de façon amortie
     * (~5 % des insertions) pour le maintenir borné sans coût à chaque écriture.
     */
    public function insert(array $data): int|false
    {
        // Les données de connexion sont non fiables. Une valeur trop longue
        // ne doit pas faire perdre l'événement ni son compteur de protection.
        foreach (['email_admin' => 255, 'action_log' => 100, 'target_log' => 255, 'ip_log' => 45] as $key => $length) {
            if (isset($data[$key])) {
                $data[$key] = mb_substr((string)$data[$key], 0, $length, 'UTF-8');
            }
        }
        if (isset($data['details_log']) && is_array($data['details_log'])) {
            $data['details_log'] = json_encode($data['details_log'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        $qb = new QueryBuilder();
        $qb->insert('mc_admin_audit_log', $data);

        if ($this->executeInsert($qb)) {
            $id = $this->getLastInsertId();
            if (mt_rand(1, 20) === 1) {
                $this->prune();
            }
            return $id;
        }
        return false;
    }

    /**
     * Pagination par curseur (keyset) : pas de COUNT(*) ni d'OFFSET, donc
     * performant quelle que soit la taille du journal. Renvoie les entrées plus
     * anciennes que $before (ou les plus récentes si null), limite + 1 pour
     * savoir s'il reste des entrées.
     *
     * @return array{rows:array,hasMore:bool,nextCursor:?int}
     */
    public function fetchKeyset(int $limit = 25, ?int $before = null, array $search = []): array
    {
        $limit = max(1, min(200, $limit));
        $qb = new QueryBuilder();
        $qb->select(self::SELECT_COLS)->from('mc_admin_audit_log');

        if ($before !== null && $before > 0) {
            $qb->where('id_log < :before', ['before' => $before]);
        }
        if (!empty($search['action_log'])) {
            $qb->where('action_log LIKE :action', ['action' => '%' . $search['action_log'] . '%']);
        }

        $qb->orderBy('id_log', 'DESC')->limit($limit + 1);

        $rows = $this->executeAll($qb) ?: [];
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $nextCursor = ($hasMore && !empty($rows)) ? (int)end($rows)['id_log'] : null;

        return ['rows' => $rows, 'hasMore' => $hasMore, 'nextCursor' => $nextCursor];
    }

    /**
     * Applique la rétention : supprime les entrées trop anciennes, puis plafonne
     * le nombre de lignes (conserve les MAX_ROWS plus récentes par id).
     */
    public function prune(): void
    {
        $days    = SecurityConfig::int('sec_audit_retention_days', self::RETENTION_DAYS);
        $maxRows = SecurityConfig::int('sec_audit_max_rows', self::MAX_ROWS);

        // 1. Rétention par ancienneté (intervalle = entier validé par SecurityConfig)
        $ageQb = new QueryBuilder();
        $ageQb->delete('mc_admin_audit_log')
            ->where('date_register < (NOW() - INTERVAL ' . $days . ' DAY)');
        $this->executeDelete($ageQb);

        // 2. Le curseur porte sur le nombre de lignes, sans supposer des ids
        // contigus (purge et déblocages peuvent laisser des trous).
        $maxQb = new QueryBuilder();
        $maxQb->select(['id_log'])->from('mc_admin_audit_log')
            ->orderBy('id_log', 'DESC')->limit(1, $maxRows);
        $row = $this->executeRow($maxQb);

        if ($row !== false) {
            $capQb = new QueryBuilder();
            $capQb->delete('mc_admin_audit_log')->where('id_log <= :cut', ['cut' => (int)$row['id_log']]);
            $this->executeDelete($capQb);
        }
    }

    /**
     * Vide entièrement le journal (action réservée au droit "del").
     */
    public function purge(): bool
    {
        $qb = new QueryBuilder();
        $qb->delete('mc_admin_audit_log');
        return $this->executeDelete($qb);
    }
}
