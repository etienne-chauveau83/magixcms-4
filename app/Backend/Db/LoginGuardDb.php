<?php

declare(strict_types=1);

namespace App\Backend\Db;

use App\Backend\Model\DatabaseTransaction;
use Magepattern\Component\Database\Layer;
use Magepattern\Component\Database\QueryBuilder;

/** État temporaire des limitations ; indépendant du journal d’audit. */
class LoginGuardDb extends BaseDb
{
    public function state(string $scope, string $hash, int $minutes): array
    {
        $qb = new QueryBuilder();
        $qb->select([
            'COUNT(*) AS fails',
            'COALESCE(TIMESTAMPDIFF(MICROSECOND, MAX(date_attempt), NOW(6)) / 1000000, 0) AS elapsed',
        ])->from('mc_admin_login_attempt')
            ->where('scope = :scope', ['scope' => $scope])
            ->where('subject_hash = :hash', ['hash' => $hash])
            ->where('date_attempt > NOW(6) - INTERVAL ' . (int)$minutes . ' MINUTE');
        $row = $this->executeRow($qb);
        if ($row === false) {
            throw new \RuntimeException('Protection des connexions indisponible. Vérifier la migration SQL.');
        }
        return $row;
    }

    public function record(string $ip, string $email, string $accountHash, int $minutes): void
    {
        DatabaseTransaction::run(function () use ($ip, $email, $accountHash): void {
            foreach (['ip' => hash('sha256', $ip), 'account' => $accountHash] as $scope => $hash) {
                $qb = new QueryBuilder();
                $qb->insert('mc_admin_login_attempt', [
                    'scope' => $scope,
                    'subject_hash' => $hash,
                    'subject_label' => $scope === 'ip' ? $ip : mb_substr($email, 0, 255, 'UTF-8'),
                    'ip_attempt' => $ip,
                    'email_attempt' => mb_substr($email, 0, 255, 'UTF-8'),
                ]);
                if (!$this->executeInsert($qb)) {
                    throw new \RuntimeException('Échec de l’enregistrement des compteurs de connexion.');
                }
            }
        });
        if (mt_rand(1, 20) === 1) {
            $this->prune($minutes);
        }
    }

    public function prune(int $minutes): void
    {
        $qb = new QueryBuilder();
        $qb->delete('mc_admin_login_attempt')
            ->where('date_attempt <= NOW(6) - INTERVAL ' . (int)$minutes . ' MINUTE');
        if (!$this->executeDelete($qb)) {
            throw new \RuntimeException('Échec de la rétention des compteurs de connexion.');
        }
    }

    public function clear(string $scope, string $hash): bool
    {
        $qb = new QueryBuilder();
        $qb->delete('mc_admin_login_attempt')
            ->where('scope = :scope', ['scope' => $scope])
            ->where('subject_hash = :hash', ['hash' => $hash]);
        return $this->executeDelete($qb);
    }

    /** Au plus 200 sujets par écran ; métadonnées issues du dernier essai. */
    public function recent(string $scope, int $minutes): array
    {
        $qb = new QueryBuilder();
        $qb->select([
            'subject_hash', 'COUNT(*) AS fails', 'MAX(id_attempt) AS last_id',
            'MAX(date_attempt) AS last_at',
            'TIMESTAMPDIFF(MICROSECOND, MAX(date_attempt), NOW(6)) / 1000000 AS elapsed',
        ])->from('mc_admin_login_attempt')
            ->where('scope = :scope', ['scope' => $scope])
            ->where('date_attempt > NOW(6) - INTERVAL ' . (int)$minutes . ' MINUTE')
            ->groupBy('subject_hash')->orderBy('fails', 'DESC')->orderBy('last_id', 'DESC')->limit(200);
        $rows = $this->executeAll($qb);
        if ($rows === false) {
            throw new \RuntimeException('Protection des connexions indisponible. Vérifier la migration SQL.');
        }
        if ($rows !== []) {
            $last = new QueryBuilder();
            $last->select(['id_attempt', 'subject_label', 'ip_attempt', 'email_attempt'])
                ->from('mc_admin_login_attempt')->whereIn('id_attempt', array_column($rows, 'last_id'));
            $details = $this->executeAll($last);
            if ($details === false) {
                throw new \RuntimeException('Lecture des compteurs de connexion impossible.');
            }
            $byId = array_column($details, null, 'id_attempt');
            foreach ($rows as &$row) {
                $row += $byId[$row['last_id']] ?? [];
                $row['scope'] = $scope;
            }
            unset($row);
        }
        return $rows;
    }

    public function subject(string $scope, string $hash): ?string
    {
        $row = Layer::getInstance()->fetch(
            'SELECT subject_label FROM mc_admin_login_attempt WHERE scope = :scope AND subject_hash = :hash ORDER BY id_attempt DESC LIMIT 1',
            ['scope' => $scope, 'hash' => $hash]
        );
        return $row !== false ? (string)$row['subject_label'] : null;
    }
}
