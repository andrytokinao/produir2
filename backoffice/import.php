<?php
// Augmenter les limites pour les gros fichiers JSON avec images base64
@ini_set('memory_limit',    '512M');
@ini_set('max_execution_time', '300');

require_once __DIR__ . '/db.php';

$results   = [];   // ['filename'=>…, 'ok'=>bool, 'msg'=>…]
$processed = false;

/**
 * Extrait récursivement tous les blobs base64 vers un tableau plat $docs[chemin.dot => blob].
 * Remplace chaque blob par '[omitted]' dans le nœud nettoyé retourné.
 * Cela réduit la taille des colonnes DB de 80-95% et évite "MySQL server has gone away".
 */
function extractAndStrip(mixed $node, string $prefix = '', array &$docs = []): mixed {
    if (is_string($node) && str_starts_with($node, 'data:')) {
        $docs[$prefix] = $node;
        return '[omitted]';
    }
    if (is_array($node)) {
        foreach ($node as $k => $v) {
            $path = $prefix !== '' ? "$prefix.$k" : (string)$k;
            $node[$k] = extractAndStrip($v, $path, $docs);
        }
    }
    return $node;
}

/**
 * Restaure les blobs base64 dans un nœud JSON (inverse de extractAndStrip).
 * Utilisé par view.php et download.php.
 */
function restoreDocsInto(mixed $node, array $docs, string $prefix = ''): mixed {
    if ($node === '[omitted]' && isset($docs[$prefix])) {
        return $docs[$prefix];
    }
    if (is_array($node)) {
        foreach ($node as $k => $v) {
            $path = $prefix !== '' ? "$prefix.$k" : (string)$k;
            $node[$k] = restoreDocsInto($v, $docs, $path);
        }
    }
    return $node;
}

/** Convertit une valeur ini (ex: "50M") en octets */
function return_bytes(string $val): int {
    $val  = trim($val);
    $last = strtolower($val[-1] ?? '');
    $num  = (int)$val;
    return match($last) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

/** Traduit un code UPLOAD_ERR_* en message lisible */
function uploadErrorMsg(int $code): string {
    return match($code) {
        UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (dépasse upload_max_filesize = ' . ini_get('upload_max_filesize') . ' dans php.ini).',
        UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (dépasse MAX_FILE_SIZE du formulaire).',
        UPLOAD_ERR_PARTIAL    => 'Fichier reçu partiellement — réessayez.',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier envoyé.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire PHP introuvable — vérifiez php.ini (upload_tmp_dir).',
        UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque — vérifiez les permissions du dossier temporaire.',
        UPLOAD_ERR_EXTENSION  => 'Upload bloqué par une extension PHP.',
        default               => 'Erreur upload inconnue (code ' . $code . ').',
    };
}

// Auto-migration : ajouter la colonne `documents` si elle n'existe pas encore
try {
    $existingCols = array_column(
        $pdo->query("SHOW COLUMNS FROM `submissions`")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );
    if (!in_array('documents', $existingCols, true)) {
        $pdo->exec("ALTER TABLE `submissions`
            ADD COLUMN `documents` LONGTEXT DEFAULT NULL
            COMMENT 'Blobs base64 extraits (images, signatures, documents attachés)'");
    }
} catch (PDOException $e) { /* table pas encore créée – setup.php s'en chargera */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $processed = true;

    // Détecter dépassement de post_max_size (PHP vide $_FILES et $_POST silencieusement)
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_FILES) && empty($_POST)) {
        $results[] = ['filename' => '—', 'ok' => false,
            'msg' => 'Requête trop volumineuse. Augmentez post_max_size (actuellement ' . ini_get('post_max_size') . ') dans php.ini.'];
    } elseif (empty($_FILES['json_files']['name'][0])) {
        $results[] = ['filename' => '—', 'ok' => false, 'msg' => 'Aucun fichier sélectionné.'];
    } else {
        // Normaliser $_FILES['json_files'] (tableau uniforme)
        $files = $_FILES['json_files'];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $filename = basename($files['name'][$i]);
            $error    = $files['error'][$i];
            $tmpName  = $files['tmp_name'][$i];
            $size     = $files['size'][$i];

            // --- Validation upload ---
            if ($error !== UPLOAD_ERR_OK) {
                $results[] = ['filename' => $filename, 'ok' => false,
                    'msg' => uploadErrorMsg($error)];
                continue;
            }

            if (!preg_match('/\.json$/i', $filename)) {
                $results[] = ['filename' => $filename, 'ok' => false,
                    'msg' => 'Extension invalide (attendu .json).'];
                continue;
            }

            if ($size > 50 * 1024 * 1024) {
                $results[] = ['filename' => $filename, 'ok' => false,
                    'msg' => 'Fichier trop volumineux (max 50 Mo).'];
                continue;
            }

            // --- Lecture & décodage ---
            $raw  = file_get_contents($tmpName);
            $raw  = ltrim($raw, "\xEF\xBB\xBF");   // BOM
            $data = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $results[] = ['filename' => $filename, 'ok' => false,
                    'msg' => 'JSON invalide : ' . json_last_error_msg()];
                continue;
            }

            if (!isset($data['metadata']) || !isset($data['data'])) {
                $results[] = ['filename' => $filename, 'ok' => false,
                    'msg' => 'Format non reconnu : clés "metadata" et "data" requises.'];
                continue;
            }

            // --- Extraction champs clés ---
            $meta    = $data['metadata'] ?? [];
            $sec0    = $data['data']['sec0_identification'] ?? [];
            $sec1    = $data['data']['sec1_pap']           ?? [];

            $enqueteur     = $meta['enqueteur']      ?? null;
            $submittedAt   = $meta['submissionDate'] ?? null;
            $laharana_fisy = $sec0['laharana_fisy']  ?? null;
            $distrika      = $sec0['distrika']        ?? null;
            $fokontany     = $sec0['fokontany']       ?? null;
            $papAnarana    = trim(($sec1['anarana'] ?? '') . ' ' . ($sec1['fanampiny'] ?? ''));

            // Convertir date ISO → MySQL
            $submittedAtMysql = null;
            if ($submittedAt) {
                try {
                    $dt = new DateTime($submittedAt);
                    $submittedAtMysql = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {}
            }
            if (!$submittedAtMysql) {
                $submittedAtMysql = date('Y-m-d H:i:s');
            }

            // --- Colonnes JSON dédiées ---
            // Extraire TOUS les blobs base64 dans un tableau plat $docsMap[chemin => blob].
            // Les colonnes DB ne contiendront que '[omitted]' à la place des images.
            // Cela réduit chaque INSERT de ~95% et évite "MySQL server has gone away".
            $docsMap   = [];
            $cleanData = extractAndStrip($data, '', $docsMap);

            $fullJsonClean = json_encode($cleanData,                    JSON_UNESCAPED_UNICODE);
            $metaJson      = json_encode($cleanData['metadata'] ?? $meta, JSON_UNESCAPED_UNICODE);
            $dataJson      = json_encode($cleanData['data']     ?? [],    JSON_UNESCAPED_UNICODE);
            $docsJson      = $docsMap ? json_encode($docsMap,            JSON_UNESCAPED_UNICODE) : null;

            // Libérer la mémoire avant l'INSERT
            unset($data, $cleanData, $docsMap, $raw);

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO submissions
                        (metadata, data, full_json, original_filename, menu, submitted_at, documents)
                     VALUES
                        (:metadata, :data, :full_json, :filename, :menu, :submitted_at, :documents)"
                );
                $stmt->execute([
                    ':metadata'     => $metaJson,
                    ':data'         => $dataJson,
                    ':full_json'    => $fullJsonClean,
                    ':filename'     => $filename,
                    ':menu'         => $laharana_fisy ?? $papAnarana,
                    ':submitted_at' => $submittedAtMysql,
                    ':documents'    => $docsJson,
                ]);

                $results[] = ['filename' => $filename, 'ok' => true,
                    'msg' => 'Importé avec succès' .
                        ($laharana_fisy ? ' (N° FISy : ' . $laharana_fisy . ')' : '') . '.'];

            } catch (PDOException $e) {
                $results[] = ['filename' => $filename, 'ok' => false,
                    'msg' => 'Erreur base de données : ' . $e->getMessage()];
            } finally {
                unset($metaJson, $dataJson, $fullJsonClean, $docsJson);
                gc_collect_cycles();
            }
        }
    }

    // Redirection si tout est OK
    $successCount = count(array_filter($results, fn($r) => $r['ok']));
    $errorCount   = count($results) - $successCount;

    if ($errorCount === 0 && $successCount === 1) {
        header('Location: index.php?msg=imported');
        exit;
    }
    if ($errorCount === 0 && $successCount > 1) {
        header('Location: index.php?msg=imported_multi&n=' . $successCount);
        exit;
    }
    // Sinon : afficher le récapitulatif détaillé ci-dessous
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Importer JSON – PRODUIR 2</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f4f8; }
        #drop-zone {
            border: 2px dashed #0d6efd;
            border-radius: .5rem;
            padding: 2.5rem 1rem;
            text-align: center;
            color: #6c757d;
            cursor: pointer;
            transition: background .2s, border-color .2s;
        }
        #drop-zone.drag-over { background: #e7f1ff; border-color: #0a58ca; color: #0a58ca; }
        .file-badge {
            font-size: .82rem;
            background: #e9ecef;
            border-radius: .3rem;
            padding: .2rem .5rem;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-arrow-left me-2"></i>PRODUIR 2 – Backoffice
        </a>
    </div>
</nav>

<div class="container" style="max-width:680px">

    <?php if ($processed && $results): ?>
        <!-- ===== RÉCAPITULATIF ===== -->
        <?php
        $okCount  = count(array_filter($results, fn($r) => $r['ok']));
        $errCount = count($results) - $okCount;
        $alertClass = $errCount === 0 ? 'alert-success' : ($okCount > 0 ? 'alert-warning' : 'alert-danger');
        $icon       = $errCount === 0 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        ?>
        <div class="alert <?= $alertClass ?> d-flex align-items-center gap-3">
            <i class="bi <?= $icon ?> fs-4 flex-shrink-0"></i>
            <div>
                <strong><?= $okCount ?> fichier(s) importé(s)</strong>
                <?= $errCount > 0 ? ', <strong>' . $errCount . ' erreur(s)</strong>' : '' ?>.
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Détail par fichier</h6>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($results as $r): ?>
                    <li class="list-group-item d-flex align-items-start gap-2 py-2">
                        <i class="bi <?= $r['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> mt-1 flex-shrink-0"></i>
                        <div style="min-width:0">
                            <div class="fw-semibold text-truncate" title="<?= htmlspecialchars($r['filename']) ?>">
                                <?= htmlspecialchars($r['filename']) ?>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($r['msg']) ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="d-flex gap-2 mb-4">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-table me-1"></i>Retour à la liste
            </a>
            <a href="import.php" class="btn btn-outline-secondary">
                <i class="bi bi-upload me-1"></i>Importer d'autres fichiers
            </a>
        </div>

    <?php else: ?>
        <!-- ===== FORMULAIRE ===== -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-upload me-2 text-primary"></i>Importer des fichiers JSON
                </h5>
            </div>
            <div class="card-body">

                <form method="post" enctype="multipart/form-data" id="import-form">

                    <!-- Zone glisser-déposer -->
                    <div id="drop-zone" onclick="document.getElementById('json_files').click()">
                        <i class="bi bi-cloud-upload fs-1 d-block mb-2"></i>
                        <div class="fw-semibold">Cliquez ou glissez-déposez vos fichiers JSON ici</div>
                        <div class="small mt-1">
                            Plusieurs fichiers acceptés simultanément &middot;
                            Taille max par fichier : <strong><?= ini_get('upload_max_filesize') ?>o</strong>
                            &middot; Total max : <strong><?= ini_get('post_max_size') ?>o</strong>
                        </div>
                    </div>

                    <input type="file" id="json_files" name="json_files[]"
                           accept=".json" multiple class="d-none" required>

                    <!-- Liste des fichiers sélectionnés -->
                    <div id="file-list" class="mt-3 d-flex flex-wrap gap-2"></div>

                    <?php
                    $uploadMaxBytes = return_bytes(ini_get('upload_max_filesize'));
                    $postMaxBytes   = return_bytes(ini_get('post_max_size'));
                    $limitWarning   = ($uploadMaxBytes < 10 * 1024 * 1024 || $postMaxBytes < 10 * 1024 * 1024);
                    ?>
                    <?php if ($limitWarning): ?>
                        <div class="alert alert-warning small py-2 mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Les limites PHP sont faibles (<code>upload_max_filesize=<?= ini_get('upload_max_filesize') ?></code>,
                            <code>post_max_size=<?= ini_get('post_max_size') ?></code>).
                            Modifiez <strong>c:\xampp\php\php.ini</strong> :
                            <code>upload_max_filesize=50M</code> &amp; <code>post_max_size=55M</code>,
                            puis redémarrez Apache.
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary" id="btn-submit" disabled>
                            <i class="bi bi-upload me-1"></i>Importer
                            <span id="btn-count" class="badge bg-white text-primary ms-1 d-none">0</span>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>

            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h6><i class="bi bi-info-circle me-1 text-info"></i>Format attendu</h6>
                <pre class="small bg-light p-2 rounded border"><code>{
  "metadata": { "enqueteur": "…", "submissionDate": "…", … },
  "labels":   { "daty": "Daty", … },
  "data": {
    "sec0_identification": { "distrika": "…", … },
    "sec1_pap": { "anarana": "…", … },
    …
  }
}</code></pre>
                <p class="small text-muted mb-0">
                    Format JSON exporté depuis l'application PRODUIR 2
                    (structure <code>metadata</code> / <code>labels</code> / <code>data</code>).
                </p>
            </div>
        </div>
    <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const input    = document.getElementById('json_files');
        const dropZone = document.getElementById('drop-zone');
        const fileList = document.getElementById('file-list');
        const btnSub   = document.getElementById('btn-submit');
        const btnCount = document.getElementById('btn-count');

        if (!input) return;  // page récapitulatif

        function esc(str) {
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function renderList(files) {
            fileList.innerHTML = '';
            if (!files || !files.length) {
                btnSub.disabled = true;
                btnCount.classList.add('d-none');
                return;
            }
            [...files].forEach(f => {
                const span = document.createElement('span');
                span.className = 'file-badge';
                span.innerHTML = `<i class="bi bi-file-earmark-text text-primary"></i>
                        <span title="${esc(f.name)}">${esc(f.name)}</span>`;
                fileList.appendChild(span);
            });
            btnSub.disabled = false;
            btnCount.textContent = files.length;
            btnCount.classList.remove('d-none');
        }

        // Sélection via <input>
        input.addEventListener('change', () => renderList(input.files));

        // Drag & drop
        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        ['dragleave','dragend'].forEach(ev =>
            dropZone.addEventListener(ev, () => dropZone.classList.remove('drag-over'))
        );
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const jsonFiles = [...e.dataTransfer.files].filter(f => f.name.toLowerCase().endsWith('.json'));
            if (!jsonFiles.length) return;
            const dt = new DataTransfer();
            jsonFiles.forEach(f => dt.items.add(f));
            input.files = dt.files;
            renderList(input.files);
        });
    })();
</script>
</body>
</html>
