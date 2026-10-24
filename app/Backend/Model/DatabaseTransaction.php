<?php

declare(strict_types=1);

namespace App\Backend\Model;

use Magepattern\Component\Database\Layer;

/** Transactions partagées par les écritures de sécurité, sans modifier Magepattern. */
final class DatabaseTransaction
{
    public static function run(callable $operation): mixed
    {
        $pdo = Layer::getInstance()->connection();
        if ($pdo === null) {
            throw new \RuntimeException('Connexion à la base indisponible.');
        }
        // Une écriture SQL refusée doit rester observable même si le driver
        // partagé a été configuré en mode silencieux.
        $errorMode = $pdo->getAttribute(\PDO::ATTR_ERRMODE);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction && !$pdo->beginTransaction()) {
                throw new \RuntimeException('Impossible de démarrer la transaction.');
            }
            $result = $operation();
            if ($result === false) {
                throw new \RuntimeException('Échec d’une écriture de sécurité.');
            }
            if ($ownsTransaction && !$pdo->commit()) {
                throw new \RuntimeException('Impossible de valider la transaction.');
            }
            return $result;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        } finally {
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, $errorMode);
        }
    }
}
