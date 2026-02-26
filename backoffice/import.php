<?php
require_once __DIR__ . '/db.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Validation upload ----
    if (empty($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Aucun fichier reçu ou erreur lors de l\'upload.';
    } else {
        $file     = $_FILES['json_file'];
        $filename = basename($file['name']);

        // Extension
        if (!preg_match('/\.json$/i', $filename)) {
            $errors[] = 'Le fichier doit avoir l\'extension .json';
        }

        // Taille max 50 Mo (documents base64)
        if ($file['size'] > 50 * 1024 * 1024) {
            $errors[] = 'Fichier trop volumineux (max 50 Mo).';
        }
    }

    if (!$errors) {
        $raw  = file_get_contents($file['tmp_name']);
        // Supprimer BOM éventuel
        $raw  = ltrim($raw, "\xEF\xBB\xBF");
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'JSON invalide : ' . json_last_error_msg();
        } elseif (!isset($data['metadata']) || !isset($data['data'])) {
            $errors[] = 'Format JSON non reconnu : les clés "metadata" et "data" sont requises.';
        } else {
            // ---- Extraction des champs clés ----
            $meta    = $data['metadata'] ?? [];
            $sec0    = $data['data']['sec0_identification'] ?? [];
            $sec1    = $data['data']['sec1_pap']           ?? [];

            $enqueteur     = $meta['enqueteur']           ?? null;
            $submittedAt   = $meta['submissionDate']      ?? null;  // ISO 8601
            $laharana_fisy = $sec0['laharana_fisy']       ?? null;
            $distrika      = $sec0['distrika']            ?? null;
            $fokontany     = $sec0['fokontany']           ?? null;
            $papAnarana    = trim(($sec1['anarana'] ?? '') . ' ' . ($sec1['fanampiny'] ?? ''));

            // Convertir date ISO en datetime MySQL
            $submittedAtMysql = null;
            if ($submittedAt) {
                try {
                    $dt = new DateTime($submittedAt);
                    $submittedAtMysql = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $submittedAtMysql = null;
                }
            }
            if (!$submittedAtMysql) {
                $submittedAtMysql = date('Y-m-d H:i:s');
            }

            // ---- Vérifier doublon (même laharana_fisy + même date) ----
            if ($laharana_fisy) {
                $dupStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM submissions
                     WHERE JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.laharana_fisy')) = ?
                       AND DATE(submitted_at) = ?"
                );
                $dupStmt->execute([$laharana_fisy, date('Y-m-d', strtotime($submittedAtMysql))]);
                // On avertit mais on n'empêche pas l'import (même PAP peut être ré-enquêté)
            }

            // ---- Séparer metadata/labels/data pour les colonnes dédiées ----
            $metaJson   = json_encode($meta,          JSON_UNESCAPED_UNICODE);
            $labelsJson = json_encode($data['labels'] ?? [], JSON_UNESCAPED_UNICODE);
            $dataJson   = json_encode($data['data'],  JSON_UNESCAPED_UNICODE);

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO submissions
                        (metadata, data, full_json, original_filename, menu, submitted_at)
                     VALUES
                        (:metadata, :data, :full_json, :filename, :menu, :submitted_at)"
                );
                $stmt->execute([
                    ':metadata'     => $metaJson,
                    ':data'         => $dataJson,
                    ':full_json'    => $raw,          // stocker le JSON complet brut
                    ':filename'     => $filename,
                    ':menu'         => $laharana_fisy ?? $papAnarana, // rétrocompat colonne menu
                    ':submitted_at' => $submittedAtMysql,
                ]);

                // Redirection avec succès
                header('Location: index.php?msg=imported');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Erreur base de données : ' . $e->getMessage();
            }
        }
    }
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
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <i class="bi bi-arrow-left me-2"></i>PRODUIR 2 – Backoffice
    </a>
  </div>
</nav>

<div class="container" style="max-width:600px">
  <div class="card shadow-sm">
    <div class="card-header bg-white">
      <h5 class="mb-0"><i class="bi bi-upload me-2 text-primary"></i>Importer un fichier JSON</h5>
    </div>
    <div class="card-body">

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <strong>Erreur(s) :</strong>
          <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="json_file" class="form-label fw-semibold">Fichier JSON (format PRODUIR 2 v2.1)</label>
          <input type="file" id="json_file" name="json_file"
                 class="form-control" accept=".json" required>
          <div class="form-text">
            Format attendu : JSON exporté depuis l'application PRODUIR 2 (structure <code>metadata</code> / <code>labels</code> / <code>data</code>).
            Taille maximale : 50 Mo.
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-upload me-1"></i>Importer
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
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
