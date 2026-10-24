<?php

declare(strict_types=1);

namespace App\Backend\Model;

use App\Backend\Db\AuditLogDb;
use App\Backend\Db\CompanyDb;
use Magepattern\Component\Tool\MailTool;

/**
 * Alertes de sécurité. Chaque alerte est systématiquement tracée dans le
 * journal d'audit (action "alert.<event>") — ce qui en constitue l'historique
 * consultable — et un e-mail best-effort est envoyé au contact de l'entreprise.
 * L'envoi ne doit jamais interrompre l'action métier.
 */
class AlertService
{
    /** Chaque type d'événement est rattaché à son propre réglage d'activation. */
    private const EVENT_KEYS = [
        'bruteforce'         => 'sec_alert_bruteforce',
        'new_superadmin'     => 'sec_alert_superadmin',
        'superadmin_promote' => 'sec_alert_superadmin',
    ];

    public static function notify(string $event, string $subject, string $body = ''): void
    {
        // Alerte activable/désactivable indépendamment selon son type.
        $key = self::EVENT_KEYS[$event] ?? null;
        if ($key !== null && !SecurityConfig::enabled($key, true)) {
            return;
        }

        self::audit('alert.' . $event, $subject);
        $result = self::deliver($subject, $body);
        if (!$result['success']) {
            self::audit('alert.delivery_failed', $event . ' : ' . $result['message']);
        }
    }

    /** État de la configuration, sans connexion SMTP ni envoi de message. */
    public static function mailStatus(?string $recipient = null): array
    {
        $smtp = SecurityConfig::enabled('smtp_enabled', false);
        $issues = [];
        if ($smtp) {
            if (trim(SecurityConfig::str('set_host')) === '') {
                $issues[] = 'Le serveur SMTP est manquant.';
            }
            $port = SecurityConfig::str('set_port', '25');
            if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
                $issues[] = 'Le port SMTP doit être compris entre 1 et 65535.';
            }
        }
        if (filter_var(SecurityConfig::str('mail_sender'), FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = 'L’adresse e-mail de l’expéditeur est manquante ou invalide.';
        }
        $to = self::recipient($recipient);
        if ($to === '') {
            $issues[] = 'Renseignez un destinataire valide ou l’e-mail de contact de l’entreprise.';
        }
        return [
            'smtp_enabled' => $smtp,
            'can_send' => $issues === [],
            'issues' => $issues,
            'recipient' => $to,
        ];
    }

    /** Test autonome : aucun blocage de connexion ni changement de compte. */
    public static function test(?string $recipient = null): array
    {
        return self::deliver('Test d’alerte de sécurité',
            'Ceci est un test de l’envoi des alertes de sécurité. Aucun événement de sécurité n’a été déclenché.', $recipient);
    }

    private static function audit(string $action, string $target): void
    {
        try {
            (new AuditLogDb())->insert([
                'id_admin'      => null,
                'email_admin'   => 'système',
                'action_log'    => $action,
                'target_log'    => $target,
                'ip_log'        => ClientIpResolver::resolve(),
            ]);
        } catch (\Throwable $e) {
            // on ne bloque jamais sur l'audit
        }
    }

    /** Retourne le résultat réel du transport, sans exposer ses erreurs internes. */
    private static function deliver(string $subject, string $body, ?string $recipient = null): array
    {
        $status = self::mailStatus($recipient);
        if (!$status['can_send']) {
            return ['success' => false, 'message' => implode(' ', $status['issues'])];
        }
        try {
            $to = $status['recipient'];
            $mailer = new MailTool(SecurityConfig::enabled('smtp_enabled', false) ? 'smtp' : 'mail', [
                'setHost'       => SecurityConfig::str('set_host'),
                'setPort'       => SecurityConfig::int('set_port', 25),
                'setEncryption' => SecurityConfig::str('set_encryption'),
                'setUsername'   => SecurityConfig::str('set_username'),
                'setPassword'   => SecurityConfig::str('set_password'),
            ]);
            $from = SecurityConfig::str('mail_sender');
            $html = '<p>' . htmlspecialchars($subject) . '</p>'
                . ($body !== '' ? '<p>' . htmlspecialchars($body) . '</p>' : '')
                . '<p style="color:#888;font-size:12px">Alerte de sécurité automatique — MagixCMS.</p>';
            $msg = $mailer->createMessage('[Sécurité] ' . $subject, $from, $from, [$to => 'Administrateur'], $html);
            if ($mailer->send($msg)) {
                return ['success' => true, 'message' => 'Le serveur de messagerie a accepté l’alerte destinée à ' . $to . '.', 'recipient' => $to];
            }
        } catch (\Throwable $e) {
            // Le détail du transport peut contenir des informations sensibles.
        }
        return ['success' => false, 'message' => 'Échec de l’envoi. Vérifiez la configuration e-mail et la disponibilité du serveur de messagerie.'];
    }

    private static function recipient(?string $override = null): string
    {
        // Priorité au destinataire configuré, sinon le contact de l'entreprise.
        $configured = trim($override ?? SecurityConfig::str('sec_alert_email', ''));
        if ($configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_EMAIL) !== false ? $configured : '';
        }
        try {
            $info = (new CompanyDb())->getCompanyInfo();
            $email = (string)($info['mail'] ?? '');
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
