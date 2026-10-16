<?php

declare(strict_types=1);

namespace App\Component\File;

/*
 * This file is part of MAGIX CMS.
 * Copyright (C) 2008 - 2026 Gerits Aurelien (Magix CMS).
 * License: GPLv3
 */

use Magepattern\Component\Debug\Logger;
use App\Component\Routing\UrlTool;

class FileUploadTool
{
    protected UrlTool $urlTool;
    protected Logger $logger;

    // Liste blanche stricte des extensions autorisées
    private array $allowedExtensions = [
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx', 'csv',
        'ppt', 'pptx',
        'odt', 'ods',
        'zip',
        'mp3', 'wav', 'ogg'
    ];

    // Liste blanche stricte des types MIME correspondants
    private array $allowedMimes = [
        // PDF
        'application/pdf',
        // Word
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // Excel & CSV
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        // PowerPoint
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // OpenOffice / LibreOffice
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        // Archives (Aussi utilisé en interne par certains serveurs pour docx/xlsx)
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
        // Audio
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg'
    ];

    public function __construct()
    {
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        $this->urlTool = new UrlTool();
        $this->logger = Logger::getInstance();
    }

    /**
     * Validation de sécurité du fichier (Extension + MIME + Double extension)
     */
    private function validateFileSecurity(array $file): bool|string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExtensions, true)) {
            return "Extension non autorisée ({$ext}). Formats acceptés : PDF, Word, Excel, PowerPoint, ZIP, MP3, WAV.";
        }

        if (!file_exists($file['tmp_name'])) {
            return "Fichier temporaire introuvable.";
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $this->allowedMimes, true)) {
            return "Le format MIME du fichier ({$mime}) n'est pas autorisé pour des raisons de sécurité.";
        }

        if (preg_match('/\.(php|phtml|phar|sh|exe|bat|cmd|js|html|htm)/i', $file['name'])) {
            return "Nom de fichier suspect détecté.";
        }

        return true;
    }

    /**
     * Upload d'un document ou fichier audio
     */
    public function singleFileUpload(string $root, array $directories = [], array $options = []): array
    {
        $postKey = $options['postKey'] ?? 'file';
        $file = $_FILES[$postKey] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['status' => false, 'msg' => 'Aucun fichier valide reçu ou erreur lors du transfert.'];
        }

        $securityCheck = $this->validateFileSecurity($file);
        if ($securityCheck !== true) {
            return ['status' => false, 'msg' => $securityCheck];
        }

        // 1. Création de l'arborescence
        $pathParts = [];
        if (!empty($root)) $pathParts[] = rtrim($root, '/');
        if (!empty($directories)) $pathParts[] = implode('/', $directories);

        $relativePath = implode('/', $pathParts);
        $targetDir = $this->urlTool->dirUpload($relativePath, true);

        // Protection optionnelle du dossier par .htaccess (idéal pour les fichiers privés)
        if (!empty($options['protect_dir'])) {
            $this->protectDirectory($targetDir);
        }

        // 2. Formatage sécurisé du nom de fichier
        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $rawBaseName = $options['name'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
        $cleanBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $rawBaseName);
        $cleanBaseName = trim(preg_replace('/_+/', '_', $cleanBaseName), '_');

        if (empty($cleanBaseName)) {
            $cleanBaseName = 'file';
        }

        // Ajout d'un jeton unique pour éviter l'écrasement et le devinage d'URL
        $uniqueHash = substr(bin2hex(random_bytes(6)), 0, 8);
        $finalFilename = $cleanBaseName . '_' . $uniqueHash . '.' . $originalExt;
        $targetFilePath = $targetDir . $finalFilename;
        $fileSize = (int)$file['size'];

        try {
            if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                throw new \Exception("Erreur de déplacement physique du fichier.");
            }

            return [
                'status'    => true,
                'file'      => $finalFilename,
                'ext'       => $originalExt,
                'size'      => $fileSize,
                'full_path' => $targetFilePath,
                'msg'       => 'Fichier transféré avec succès.'
            ];

        } catch (\Throwable $e) {
            if (isset($targetFilePath) && file_exists($targetFilePath)) {
                unlink($targetFilePath);
            }

            $this->logger->log($e, 'php', 'error');
            return ['status' => false, 'msg' => "Erreur d'écriture du fichier sur le serveur."];
        }
    }

    /**
     * Génère un fichier .htaccess empêchant l'accès HTTP direct aux fichiers privés
     */
    private function protectDirectory(string $dirPath): void
    {
        $htaccess = rtrim($dirPath, DS) . DS . '.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
            @file_put_contents($htaccess, $rules);
        }
    }

    /**
     * Supprime physiquement un fichier
     */
    public function removeLocalFile(string $filePath): bool
    {
        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}
?>