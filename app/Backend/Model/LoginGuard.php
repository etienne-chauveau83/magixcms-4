<?php

declare(strict_types=1);

namespace App\Backend\Model;

use App\Backend\Db\LoginGuardDb;
use Magepattern\Component\Database\Layer;

/** Limitation par IP et temporisation progressive par compte, sans verrou permanent. */
class LoginGuard
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_MIN = 15;
    public const MAX_ACCOUNT_DELAY = 60;

    public static function enabled(): bool
    {
        return SecurityConfig::enabled('sec_bruteforce_enabled', true);
    }

    public static function maxAttempts(): int
    {
        return SecurityConfig::int('sec_bruteforce_max', self::MAX_ATTEMPTS);
    }

    public static function windowMin(): int
    {
        return SecurityConfig::int('sec_bruteforce_window', self::WINDOW_MIN);
    }

    public static function currentIp(): string
    {
        return ClientIpResolver::resolve();
    }

    public static function normalizeAccount(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public static function accountHash(string $email): string
    {
        return hash('sha256', self::normalizeAccount($email));
    }

    public static function acquire(string $ip, ?string $email = null): bool
    {
        $subjects = ['ip' => hash('sha256', $ip)];
        if ($email !== null) {
            $subjects['account'] = self::accountHash($email);
        }
        ksort($subjects);
        $held = [];
        foreach ($subjects as $scope => $hash) {
            $name = self::lockName($scope, $hash);
            $row = Layer::getInstance()->fetch('SELECT GET_LOCK(:name, 5) AS acquired', ['name' => $name]);
            if ((int)($row['acquired'] ?? 0) !== 1) {
                foreach ($held as $lock) {
                    self::releaseName($lock);
                }
                return false;
            }
            $held[] = $name;
        }
        return true;
    }

    public static function release(string $ip, ?string $email = null): void
    {
        self::releaseName(self::lockName('ip', hash('sha256', $ip)));
        if ($email !== null) {
            self::releaseName(self::lockName('account', self::accountHash($email)));
        }
    }

    private static function lockName(string $scope, string $hash): string
    {
        $database = defined('MP_DBNAME') ? MP_DBNAME : '';
        return 'mc-login:' . substr(hash('sha256', $database . ':' . $scope . ':' . $hash), 0, 55);
    }

    private static function releaseName(string $name): void
    {
        Layer::getInstance()->fetch('SELECT RELEASE_LOCK(:name)', ['name' => $name]);
    }

    public static function failuresForIp(string $ip): int
    {
        return (int)(new LoginGuardDb())->state('ip', hash('sha256', $ip), self::windowMin())['fails'];
    }

    public static function accountDelay(int $failures): int
    {
        if ($failures < self::maxAttempts()) {
            return 0;
        }
        return min(self::MAX_ACCOUNT_DELAY, 2 ** min(6, $failures - self::maxAttempts()));
    }

    public static function subjectBlocked(string $scope, array $state): bool
    {
        if (!self::enabled()) {
            return false;
        }
        return $scope === 'ip'
            ? (int)$state['fails'] >= self::maxAttempts()
            : (float)$state['elapsed'] < self::accountDelay((int)$state['fails']);
    }

    public static function isBlocked(string $ip, ?string $email = null): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $db = new LoginGuardDb();
        if (self::subjectBlocked('ip', $db->state('ip', hash('sha256', $ip), self::windowMin()))) {
            return true;
        }
        return $email !== null && self::subjectBlocked('account', $db->state('account', self::accountHash($email), self::windowMin()));
    }

    /** Retourne les nouveaux seuils franchis ; les requêtes bloquées ne passent pas ici. */
    public static function recordFailure(string $ip, string $email): array
    {
        $db = new LoginGuardDb();
        $db->record($ip, self::normalizeAccount($email), self::accountHash($email), self::windowMin());
        $thresholds = [];
        foreach (['ip' => hash('sha256', $ip), 'account' => self::accountHash($email)] as $scope => $hash) {
            if ((int)$db->state($scope, $hash, self::windowMin())['fails'] === self::maxAttempts()) {
                $thresholds[] = $scope;
            }
        }
        return $thresholds;
    }

    public static function successfulLogin(string $email): void
    {
        if (!(new LoginGuardDb())->clear('account', self::accountHash($email))) {
            throw new \RuntimeException('Impossible de réinitialiser la temporisation du compte.');
        }
    }

    public static function recent(string $scope): array
    {
        $rows = (new LoginGuardDb())->recent($scope, self::windowMin());
        foreach ($rows as &$row) {
            $row['blocked'] = self::subjectBlocked($scope, $row);
        }
        unset($row);
        return $rows;
    }

    public static function reset(string $scope, string $hash, ?array $actor = null): ?string
    {
        $name = self::lockName($scope, $hash);
        $lock = Layer::getInstance()->fetch('SELECT GET_LOCK(:name, 5) AS acquired', ['name' => $name]);
        if ((int)($lock['acquired'] ?? 0) !== 1) {
            throw new \RuntimeException('Compteur occupé. Réessayez.');
        }
        try {
            return DatabaseTransaction::run(function () use ($scope, $hash, $actor): ?string {
                $db = new LoginGuardDb();
                $label = $db->subject($scope, $hash);
                if ($label === null) {
                    return null;
                }
                $before = (int)$db->state($scope, $hash, self::windowMin())['fails'];
                if (!$db->clear($scope, $hash)) {
                    throw new \RuntimeException('Impossible de réinitialiser le compteur.');
                }
                if ($actor !== null && (new \App\Backend\Db\AuditLogDb())->insert([
                    'id_admin' => $actor['id_admin'] ?? null,
                    'email_admin' => $actor['email_admin'] ?? null,
                    'ip_log' => $actor['ip_log'] ?? null,
                    'action_log' => $scope === 'ip' ? 'login.unblock' : 'login.account_unblock',
                    'target_log' => $label,
                    'details_log' => ['changes' => [['field' => 'failures', 'label' => 'Compteur d’échecs', 'before' => $before, 'after' => 0]]],
                ]) === false) {
                    throw new \RuntimeException('Écriture du déblocage dans le journal impossible.');
                }
                return $label;
            });
        } finally {
            self::releaseName($name);
        }
    }
}
