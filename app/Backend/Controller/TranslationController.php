<?php
declare(strict_types=1);

namespace App\Backend\Controller;

use App\Backend\Db\LangDb;
use App\Backend\Db\ThemeDb;
use App\Backend\Db\PluginDb;

class TranslationController extends BaseController
{
    public function run(): void
    {
        $action = $_GET['action'] ?? 'index';
        if (in_array($action, ['index', 'save'], true)) {
            $this->$action();
        } else {
            $this->index();
        }
    }

    public function index(): void
    {
        $langDb = new LangDb();
        $themeDb = new ThemeDb();
        $pluginDb = new PluginDb();

        $rawLangs = $langDb->getFrontendLanguages() ?: [];
        $activeLangs = [];
        $formattedLangs = []; // Format spécifique pour le dropdown-lang.tpl [id => iso]

        foreach ($rawLangs as $key => $val) {
            if (is_array($val)) {
                $id = $val['id_lang'] ?? $key;
                $iso = $val['iso_lang'] ?? 'fr';
                $activeLangs[] = ['id_lang' => $id, 'iso_lang' => $iso];
                $formattedLangs[$id] = $iso;
            } else {
                $activeLangs[] = ['id_lang' => $key, 'iso_lang' => $val];
                $formattedLangs[$key] = $val;
            }
        }

        $rawDefault = $langDb->getDefaultLanguage() ?: ['iso_lang' => 'fr'];
        $defaultLang = is_array($rawDefault) ? $rawDefault : ['iso_lang' => $rawDefault];

        $currentTheme = $themeDb->getCurrentTheme();

        // Extensions ayant des traductions publiques.
        $rawPlugins = $pluginDb->fetchInstalledPlugins();
        $installedPlugins = [];
        $pluginsDir = ROOT_DIR . 'plugins' . DS;

        foreach ($rawPlugins as $pluginName => $dbData) {
            $manifestPath = $pluginsDir . $pluginName . DS . 'manifest.json';

            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);

                // Rigueur absolue : si pas de type, c'est du backend par défaut
                $type = $manifest['type'] ?? 'backend';

                // Les types qui ont une présence frontend
                $translatableTypes = ['widget', 'frontend', 'hybrid', 'addon'];

                // Le catalogue public doit exister.
                $hasFrontI18n = is_dir($pluginsDir . $pluginName . DS . 'i18n' . DS . 'front');

                // On n'ajoute le plugin à la liste QUE s'il remplit les deux conditions
                if (in_array($type, $translatableTypes) && $hasFrontI18n) {
                    $installedPlugins[] = [
                        'name' => $pluginName,
                        'type' => $type
                    ];
                }
            }
        }

        $domain = $_GET['domain'] ?? 'theme';
        if (!is_string($domain) || ($domain !== 'theme' && !in_array($domain, array_column($installedPlugins, 'name'), true))) {
            $this->jsonResponse(false, 'Domaine de traduction invalide.');
        }

        if ($domain === 'theme') {
            $i18nDir = ROOT_DIR . 'skin' . DS . $currentTheme . DS . 'i18n' . DS;
            $domainLabel = "Thème : " . ucfirst($currentTheme);
        } else {
            $i18nDir = ROOT_DIR . 'plugins' . DS . $domain . DS . 'i18n' . DS . 'front' . DS;
            $domainLabel = "Plugin : " . ucfirst(str_replace('Magix', '', $domain));
        }

        $defaultConfPath = $i18nDir . $defaultLang['iso_lang'] . '.conf';

        $translations = [];
        $structure = [];
        $catalogIsos = array_values(array_unique(array_merge(
            array_column($activeLangs, 'iso_lang'), [$defaultLang['iso_lang']]
        )));
        $catalogLock = @fopen($i18nDir . '.translations.lock', is_file($i18nDir . '.translations.lock') ? 'r' : 'c');
        if (is_resource($catalogLock) && !flock($catalogLock, LOCK_SH)) {
            fclose($catalogLock);
            $catalogLock = false;
        }
        try {
            $catalogVersion = $this->catalogVersion($i18nDir, $catalogIsos);
            foreach ($activeLangs as $lang) {
                $iso = $lang['iso_lang'];
                $langPath = $i18nDir . $iso . '.conf';

                // readConf renvoie [ 'Groupe' => ['cle' => 'val'] ]
                $parsed = $this->readConf(is_file($langPath) ? $langPath : $defaultConfPath);
                $translations[$iso] = $parsed;

                foreach ($parsed as $group => $keysArr) {
                    if (!isset($structure[$group])) {
                        $structure[$group] = [];
                    }
                    foreach (array_keys($keysArr) as $k) {
                        if (!in_array($k, $structure[$group])) {
                            $structure[$group][] = $k;
                        }
                    }
                }
            }

            if (!hash_equals($catalogVersion, $this->catalogVersion($i18nDir, $catalogIsos))) {
                $this->jsonResponse(false, 'Les traductions ont changé pendant leur chargement. Rechargez la page.');
            }
        } finally {
            if (is_resource($catalogLock)) {
                flock($catalogLock, LOCK_UN);
                fclose($catalogLock);
            }
        }
        $this->view->assign([
            'domain'       => $domain,
            'domain_label' => $domainLabel,
            'langs'        => $formattedLangs,
            'translations' => $translations,
            'structure'    => $structure,
            'plugins'      => $installedPlugins,
            'hashtoken'    => $this->session->getToken(),
            'translation_context' => $domain === 'theme' ? $currentTheme : $domain,
            'translation_version' => $catalogVersion
        ]);

        $visualMode = ($_GET['mode'] ?? '') === 'visual';
        $this->view->display($visualMode ? 'translation/visual.tpl' : 'translation/index.tpl');
    }

    public function save(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonResponse(false, 'Méthode non autorisée.');
        }
        $token = $_POST['hashtoken'] ?? '';
        if (!is_string($token) || !$this->session->validateToken($token)) {
            $this->jsonResponse(false, 'Session invalide.');
        }

        $domain = $_POST['domain'] ?? 'theme';
        $contents = $_POST['content'] ?? []; // Format: ['fr' => ['Groupe' => ['key' => 'val']]]

        // Le domaine est soit "theme", soit un nom de dossier de plugin strict.
        // Empêche une traversée de chemin via le paramètre domain (branche else).
        if (!is_string($domain) || ($domain !== 'theme' && !preg_match('/^[A-Za-z0-9_]+$/D', $domain))) {
            $this->jsonResponse(false, 'Domaine de traduction invalide.');
        }

        $newKey = $_POST['new_key'] ?? '';
        $newGroup = $_POST['new_group'] ?? 'Général';
        $newValues = $_POST['new_value'] ?? [];
        $submittedLanguages = $_POST['catalog_languages'] ?? [];
        if (!is_array($contents) || !is_array($submittedLanguages) || $submittedLanguages === []) {
            $this->jsonResponse(false, 'Le formulaire est incomplet. Rechargez la page avant d’enregistrer.');
        }
        foreach ($submittedLanguages as $iso => $present) {
            if (!is_string($iso) || !preg_match('/^[a-z]{2,3}$/D', $iso) || $present !== '1') {
                $this->jsonResponse(false, 'Langue du formulaire invalide.');
            }
            // Une langue sans clés n'émet aucun champ content[] dans le formulaire HTML.
            $contents[$iso] ??= [];
        }
        if (!is_array($contents) || !is_string($newKey)
            || !is_string($newGroup) || !is_array($newValues)) {
            $this->jsonResponse(false, 'Format de traduction invalide.');
        }
        $newKey = trim($newKey);
        $newGroup = trim($newGroup);
        if (($newKey !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $newKey))
            || $newGroup === '' || preg_match('/[\r\n\[\]]/', $newGroup)
            || $newGroup === 'Fichier généré par Magix CMS') {
            $this->jsonResponse(false, 'Clé ou rubrique invalide.');
        }
        foreach ($contents as $iso => $groups) {
            if (!is_string($iso) || !preg_match('/^[a-z]{2,3}$/D', $iso) || !is_array($groups)) {
                $this->jsonResponse(false, 'Langue ou contenu invalide.');
            }
            foreach ($groups as $group => $keys) {
                if (!is_string($group) || trim($group) === '' || preg_match('/[\r\n]/', $group) || !is_array($keys)) {
                    $this->jsonResponse(false, 'Rubrique invalide.');
                }
                foreach ($keys as $key => $value) {
                    if (!is_string($key) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key) || !is_string($value)) {
                        $this->jsonResponse(false, 'Clé ou valeur invalide.');
                    }
                    if ($newKey !== '' && $key === $newKey) {
                        $this->jsonResponse(false, 'Cette clé existe déjà. Modifiez le texte existant.');
                    }
                }
            }
            if (isset($newValues[$iso]) && !is_string($newValues[$iso])) {
                $this->jsonResponse(false, 'Valeur du nouveau texte invalide.');
            }
        }

        if ($domain === 'theme') {
            $themeDb = new ThemeDb();
            $currentTheme = $themeDb->getCurrentTheme();
            $baseDir = ROOT_DIR . 'skin' . DS . $currentTheme . DS . 'i18n' . DS;
        } else {
            if (!array_key_exists($domain, (new PluginDb())->fetchInstalledPlugins())) {
                $this->jsonResponse(false, 'Extension non installée.');
            }
            $baseDir = ROOT_DIR . 'plugins' . DS . $domain . DS . 'i18n' . DS . 'front' . DS;
        }
        if (!is_dir($baseDir)) {
            $this->jsonResponse(false, 'Le dossier des traductions est introuvable.');
        }

        $context = $domain === 'theme' ? $currentTheme : $domain;
        $expectedContext = $_POST['catalog_context'] ?? '';
        $expectedVersion = $_POST['catalog_version'] ?? '';
        $languages = (new LangDb())->getFrontendLanguages() ?: [];
        $isos = [];
        foreach ($languages as $key => $language) {
            $isos[] = is_array($language) ? $language['iso_lang'] : $language;
        }
        if (!is_string($expectedContext) || $expectedContext !== $context
            || !is_string($expectedVersion) || array_diff(array_keys($contents), $isos)
            || array_diff($isos, array_keys($contents))
            || array_diff(array_keys($submittedLanguages), $isos)
            || array_diff($isos, array_keys($submittedLanguages))) {
            $this->jsonResponse(false, 'Le thème ou les langues ont changé. Rechargez la page avant d’enregistrer.');
        }
        if (($_POST['catalog_complete'] ?? '') !== '1') {
            $this->jsonResponse(false, 'Le formulaire est incomplet. Rechargez la page avant d’enregistrer.');
        }
        if (array_diff(array_keys($newValues), $isos)) {
            $this->jsonResponse(false, 'Langue du nouveau texte invalide.');
        }
        if ($newKey !== '' && !array_filter($newValues, static fn(string $value): bool => trim($value) !== '')) {
            $this->jsonResponse(false, 'Renseignez au moins une traduction pour créer cette variable.');
        }
        $rawDefault = (new LangDb())->getDefaultLanguage() ?: ['iso_lang' => 'fr'];
        $defaultIso = is_array($rawDefault) ? $rawDefault['iso_lang'] : $rawDefault;
        $catalogIsos = array_values(array_unique(array_merge($isos, [$defaultIso])));
        $lock = @fopen($baseDir . '.translations.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) fclose($lock);
            $this->jsonResponse(false, 'Les traductions sont occupées. Réessayez.');
        }
        $success = false;
        $savedVersion = '';
        try {
            if (!hash_equals($this->catalogVersion($baseDir, $catalogIsos), $expectedVersion)) {
                $this->jsonResponse(false, 'Les traductions ont changé depuis l’ouverture de cet écran. Rechargez la page avant d’enregistrer.');
            }
            $existing = [];
            foreach ($isos as $iso) {
                $path = $baseDir . $iso . '.conf';
                $existing[$iso] = $this->readConf(is_file($path) ? $path : $baseDir . $defaultIso . '.conf');
            }
            if (!$this->isCompleteCatalog($contents, $existing)) {
                $this->jsonResponse(false, 'Le formulaire est incomplet. Aucune traduction n’a été écrasée. Rechargez la page.');
            }
            $catalogs = [];
            foreach ($contents as $iso => $transData) {
                if ($newKey !== '') {
                    $transData[$newGroup][$newKey] = $newValues[$iso] ?? '';
                }
                $catalogs[$baseDir . $iso . '.conf'] = $transData;
            }
            $success = $this->writeCatalog($catalogs);
            if ($success) {
                // L'empreinte retournée correspond à cette sauvegarde, sous le même verrou.
                $savedVersion = $this->catalogVersion($baseDir, $catalogIsos);
            }
        } catch (\Throwable $error) {
            $this->logger->log('Échec de sauvegarde des traductions : ' . $error->getMessage(), 'error');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        if ($success) {
            \App\Component\Cache\CacheManager::clearFrontend();
            $this->jsonResponse(true, "Toutes les traductions ont été enregistrées avec succès !", [
                'catalog_version' => $savedVersion,
            ]);
        } else {
            $this->jsonResponse(false, "Erreur d'écriture. Vérifiez les droits du dossier (CHMOD).");
        }
    }

    /**
     * Parse le fichier et groupe les variables en fonction des commentaires #
     */
    private function readConf(string $path): array
    {
        if (!file_exists($path)) return [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        $data = [];
        $currentGroup = 'Général'; // Groupe par défaut

        for ($lineIndex = 0; $lineIndex < count($lines); $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            // Les sections Smarty ne sont pas des traductions globales. Elles
            // restent inchangées dans le fichier lors d’une sauvegarde.
            if (preg_match('/^\[[^\]\r\n]+\]$/', $line)) break;
            if (empty($line) || str_starts_with($line, ';')) continue;

            // Classement par les commentaires du catalogue.
            if (str_starts_with($line, '#')) {
                $possibleGroup = trim(substr($line, 1));
                // On ignore le commentaire système généré par le CMS
                if ($possibleGroup !== 'Fichier généré par Magix CMS' && $possibleGroup !== '') {
                    $currentGroup = $possibleGroup;
                }
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);

                if (str_starts_with($val, '"""')) {
                    $val = substr($val, 3);
                    while (strpos($val, '"""') === false && ++$lineIndex < count($lines)) {
                        $val .= "\n" . $lines[$lineIndex];
                    }
                    $end = strpos($val, '"""');
                    if ($end === false) throw new \RuntimeException('Valeur multiligne incomplète.');
                    $val = stripcslashes(substr($val, 0, $end));
                } elseif (str_starts_with($val, '"') && str_ends_with($val, '"')) {
                    $val = stripcslashes(substr($val, 1, -1));
                } elseif (str_starts_with($val, "'") && str_ends_with($val, "'")) {
                    $val = str_replace(["\\'", "\\\\"], ["'", "\\"], substr($val, 1, -1));
                }
                $data[$currentGroup][$key] = $val;
            }
        }
        return $data;
    }

    /** Vérifie l'union des clés affichées pour toutes les langues du formulaire. */
    private function isCompleteCatalog(array $contents, array $existing): bool
    {
        if (array_diff_key($contents, $existing) || array_diff_key($existing, $contents)) {
            return false;
        }
        $structure = [];
        foreach ($existing as $groups) {
            foreach ($groups as $group => $keys) {
                foreach ($keys as $key => $value) {
                    $structure[$group][$key] = true;
                }
            }
        }
        foreach ($contents as $groups) {
            if (array_diff_key($groups, $structure) || array_diff_key($structure, $groups)) {
                return false;
            }
            foreach ($structure as $group => $keys) {
                if (array_diff_key($groups[$group], $keys) || array_diff_key($keys, $groups[$group])) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Prépare toutes les langues avant de publier le premier fichier. Appel sous verrou. */
    private function writeCatalog(array $catalogs): bool
    {
        $prepared = [];
        $backups = [];
        $published = [];
        $retainBackups = false;
        try {
            foreach ($catalogs as $path => $data) {
                $temporary = $this->prepareConf($path, $data);
                if ($temporary === false) {
                    return false;
                }
                $prepared[$path] = $temporary;
                $backups[$path] = null;
                if (is_file($path)) {
                    $backup = $this->temporaryConf(dirname($path), '.translation-backup-');
                    if ($backup === false) {
                        return false;
                    }
                    $backups[$path] = $backup;
                    if (!@copy($path, $backup) || !@chmod($backup, fileperms($path) & 0666)) {
                        return false;
                    }
                }
            }
            foreach ($prepared as $path => $temporary) {
                if (!@rename($temporary, $path)) {
                    // Restaure les langues déjà publiées si un remplacement échoue.
                    foreach (array_reverse($published) as $previous) {
                        $restored = $backups[$previous] !== null
                            ? @rename($backups[$previous], $previous)
                            : @unlink($previous);
                        if (!$restored) {
                            $retainBackups = true;
                        }
                    }
                    if ($retainBackups) {
                        throw new \RuntimeException('Restauration incomplète : les sauvegardes .translation-backup sont conservées.');
                    }
                    return false;
                }
                $published[] = $path;
            }
            return true;
        } finally {
            foreach ($prepared as $temporary) {
                if (is_file($temporary)) @unlink($temporary);
            }
            if (!$retainBackups) {
                foreach ($backups as $backup) {
                    if ($backup !== null && is_file($backup)) @unlink($backup);
                }
            }
        }
    }

    /** Écrit un catalogue isolé avec les mêmes garanties que la sauvegarde des langues. */
    private function writeConf(string $path, array $data): bool
    {
        return $this->writeCatalog([$path => $data]);
    }

    /** Conserve l’extension protégée des catalogues, même pendant une écriture ou un rollback. */
    private function temporaryConf(string $directory, string $prefix): string|false
    {
        $temporary = @tempnam($directory, $prefix);
        if ($temporary === false) {
            return false;
        }
        $protected = $temporary . '.conf';
        if (!@rename($temporary, $protected)) {
            @unlink($temporary);
            return false;
        }
        return $protected;
    }

    /** Sérialise un catalogue et ses sections dans un fichier temporaire du même dossier. */
    private function prepareConf(string $path, array $data): string|false
    {
        if (!is_dir(dirname($path)) || !is_writable(dirname($path))
            || (file_exists($path) && !is_file($path))) {
            return false;
        }
        $content = "# Fichier généré par Magix CMS\n";
        foreach ($data as $group => $keys) {
            $content .= "\n# {$group}\n";
            foreach ($keys as $key => $value) {
                $cleanValue = addcslashes((string)$value, "\\\"\n\r\t");
                $content .= "{$key} = \"{$cleanValue}\"\n";
            }
        }
        // Les sections, y compris cachées, ne doivent jamais devenir globales.
        if (is_file($path)) {
            $existing = @file_get_contents($path);
            if ($existing === false) {
                return false;
            }
            $sectionOffset = $this->sectionOffset($existing);
            if ($sectionOffset !== null) {
                $content .= "\n" . substr($existing, $sectionOffset);
            }
        }
        $temporary = $this->temporaryConf(dirname($path), '.translation-');
        if ($temporary === false) {
            return false;
        }
        $mode = is_file($path) ? fileperms($path) & 0666 : 0644;
        if (@file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)
            || !@chmod($temporary, $mode)) {
            @unlink($temporary);
            return false;
        }
        return $temporary;
    }

    /** Repère une section réelle, sans confondre une ligne de texte multiligne. */
    private function sectionOffset(string $content): ?int
    {
        $offset = 0;
        $multiline = false;
        foreach (preg_split('/(?<=\n)/', $content) as $line) {
            if (!$multiline && preg_match('/^[ \t]*\[[^\]\r\n]+\][ \t]*$/', rtrim($line, "\r\n"))) {
                return $offset;
            }
            if ($multiline) {
                if (str_contains($line, '"""')) $multiline = false;
            } elseif (preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*\s*=\s*"""(.*)$/s', $line, $match)) {
                $multiline = !str_contains($match[1], '"""');
            }
            $offset += strlen($line);
        }
        return null;
    }

    /** Empêche une sauvegarde d’écraser un catalogue ou un thème changé entre-temps. */
    private function catalogVersion(string $directory, array $isos): string
    {
        sort($isos, SORT_STRING);
        $hash = hash_init('sha256');
        foreach ($isos as $iso) {
            $path = $directory . $iso . '.conf';
            hash_update($hash, $iso . "\0" . (is_file($path) ? file_get_contents($path) : '') . "\0");
        }
        return hash_final($hash);
    }
}
