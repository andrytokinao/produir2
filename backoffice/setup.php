<?php
/**
 * setup.php – Création / mise à jour de la base de données PRODUIR 2
 *
 * Accès : http://localhost/Produir2/backoffice/setup.php
 * À exécuter une seule fois après installation, ou après une migration.
 * Le script est idempotent : il peut être relancé sans risque (CREATE IF NOT EXISTS).
 */

// ── Paramètres de connexion ──────────────────────────────────────────────────
// (mêmes que db.php — modifiez ici si nécessaire)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'produir2_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Protection basique (commenter en production) ─────────────────────────────
// define('SETUP_TOKEN', 'mon_token_secret');
// if (($_GET['token'] ?? '') !== SETUP_TOKEN) { http_response_code(403); die('Accès refusé.'); }

// ── Exécution ────────────────────────────────────────────────────────────────
$steps  = [];   // ['label'=>…, 'ok'=>bool, 'detail'=>…]
$ran    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    $ran = true;

    // 1. Connexion SANS sélectionner la base (pour pouvoir la créer)
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $steps[] = step('Connexion MySQL', true, DB_HOST . ':' . DB_PORT . ' — utilisateur : ' . DB_USER);
    } catch (PDOException $e) {
        $steps[] = step('Connexion MySQL', false, $e->getMessage());
        render($steps, $ran);
        exit;
    }

    // 2. Créer la base de données si elle n'existe pas
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        $steps[] = step('Base de données « ' . DB_NAME . ' »', true, 'Créée ou déjà existante.');
    } catch (PDOException $e) {
        $steps[] = step('Base de données « ' . DB_NAME . ' »', false, $e->getMessage());
        render($steps, $ran); exit;
    }

    // 3. Table submissions
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `submissions` (
                `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,

                -- Données JSON complètes
                `full_json`         LONGTEXT                  DEFAULT NULL
                                    COMMENT 'Objet JSON brut complet exporté par l\'app',
                `metadata`          LONGTEXT                  DEFAULT NULL
                                    COMMENT 'Sous-objet metadata du JSON',
                `data`              LONGTEXT                  DEFAULT NULL
                                    COMMENT 'Sous-objet data du JSON',

                -- Colonnes indexables extraites
                `original_filename` VARCHAR(255)              DEFAULT NULL
                                    COMMENT 'Nom du fichier JSON importé',
                `menu`              VARCHAR(255)              DEFAULT NULL
                                    COMMENT 'N° FISy ou nom PAP (rétrocompatibilité)',
                `submitted_at`      DATETIME                  NOT NULL
                                    DEFAULT CURRENT_TIMESTAMP
                                    COMMENT 'Date de soumission (ISO depuis metadata)',

                PRIMARY KEY (`id`),
                KEY `idx_submitted_at`  (`submitted_at`),
                KEY `idx_menu`          (`menu`(100)),

                -- Contrainte JSON (MySQL 5.7.8+)
                CONSTRAINT `chk_full_json`  CHECK (JSON_VALID(`full_json`)  OR `full_json`  IS NULL),
                CONSTRAINT `chk_metadata`   CHECK (JSON_VALID(`metadata`)   OR `metadata`   IS NULL),
                CONSTRAINT `chk_data`       CHECK (JSON_VALID(`data`)       OR `data`       IS NULL)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
            COMMENT='Soumissions PRODUIR 2'
        ");
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `submissions`")->fetchColumn();
        $steps[] = step('Table `submissions`', true,
            'Créée ou déjà existante. Lignes actuelles : ' . number_format($count, 0, ',', ' '));
    } catch (PDOException $e) {
        $steps[] = step('Table `submissions`', false, $e->getMessage());
    }

    // 4. Vérifier / ajouter les colonnes manquantes (migrations incrémentales)
    $columns = $pdo->query("SHOW COLUMNS FROM `submissions`")->fetchAll();
    $colNames = array_column($columns, 'Field');

    $migrations = [
        // [ nom_colonne, instruction ALTER ]
        ['full_json',         "ADD COLUMN `full_json`         LONGTEXT    DEFAULT NULL AFTER `id`"],
        ['metadata',          "ADD COLUMN `metadata`          LONGTEXT    DEFAULT NULL AFTER `full_json`"],
        ['data',              "ADD COLUMN `data`              LONGTEXT    DEFAULT NULL AFTER `metadata`"],
        ['original_filename', "ADD COLUMN `original_filename` VARCHAR(255) DEFAULT NULL AFTER `data`"],
        ['menu',              "ADD COLUMN `menu`              VARCHAR(255) DEFAULT NULL AFTER `original_filename`"],
        ['submitted_at',      "ADD COLUMN `submitted_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `menu`"],
    ];

    foreach ($migrations as [$col, $alter]) {
        if (!in_array($col, $colNames, true)) {
            try {
                $pdo->exec("ALTER TABLE `submissions` $alter");
                $steps[] = step("Migration : colonne `$col`", true, 'Colonne ajoutée.');
            } catch (PDOException $e) {
                $steps[] = step("Migration : colonne `$col`", false, $e->getMessage());
            }
        }
    }

    // 5. Paramètres MySQL recommandés (session seulement)
    try {
        $pdo->exec("SET GLOBAL max_allowed_packet = 67108864");   // 64 Mo
        $steps[] = step('Paramètre max_allowed_packet', true, '64 Mo (session globale).');
    } catch (PDOException $e) {
        $steps[] = step('Paramètre max_allowed_packet', false,
            'Non modifié (privilèges insuffisants). Ajoutez manuellement dans my.ini : max_allowed_packet=64M — ' . $e->getMessage());
    }

    // 6. Récapitulatif version
    try {
        $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
        $steps[] = step('Version MySQL', true, $ver);
    } catch (PDOException $e) {
        $steps[] = step('Version MySQL', false, $e->getMessage());
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function step(string $label, bool $ok, string $detail = ''): array {
    return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

function render(array $steps, bool $ran): void { /* appelé uniquement en cas d'arrêt précoce */ }

// ── Affichage ────────────────────────────────────────────────────────────────
$okCount  = $ran ? count(array_filter($steps, fn($s) => $s['ok']))  : 0;
$errCount = $ran ? count(array_filter($steps, fn($s) => !$s['ok'])) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup – PRODUIR 2 Backoffice</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f0f4f8; }
  .step-ok   { color: #198754; }
  .step-err  { color: #dc3545; }
  .card-setup { max-width: 720px; margin: 0 auto; }
  pre { background:#f8f9fa; border:1px solid #dee2e6; border-radius:.4rem;
        padding:.6rem .9rem; font-size:.82rem; white-space:pre-wrap; word-break:break-all; }
  .schema-box { font-size:.8rem; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <i class="bi bi-arrow-left me-2"></i>PRODUIR 2 – Backoffice
    </a>
    <span class="navbar-text small text-white-50">Installation &amp; Migration</span>
  </div>
</nav>

<div class="container pb-5">
<div class="card-setup">

  <!-- ── Alerte résultats ── -->
  <?php if ($ran): ?>
    <div class="alert <?= $errCount === 0 ? 'alert-success' : ($okCount > 0 ? 'alert-warning' : 'alert-danger') ?> d-flex align-items-center gap-3 mb-4">
      <i class="bi <?= $errCount === 0 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-3 flex-shrink-0"></i>
      <div>
        <?php if ($errCount === 0): ?>
          <strong>Installation terminée avec succès !</strong> Toutes les étapes ont réussi.
        <?php else: ?>
          <strong><?= $okCount ?> étape(s) réussie(s)</strong>, <strong><?= $errCount ?> erreur(s)</strong>.
          Vérifiez les détails ci-dessous.
        <?php endif; ?>
      </div>
    </div>

    <!-- Détail des étapes -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Détail des étapes</h6>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($steps as $s): ?>
          <li class="list-group-item py-2 d-flex align-items-start gap-2">
            <i class="bi <?= $s['ok'] ? 'bi-check-circle-fill step-ok' : 'bi-x-circle-fill step-err' ?> mt-1 flex-shrink-0"></i>
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($s['label']) ?></div>
              <?php if ($s['detail'] !== ''): ?>
                <small class="text-muted"><?= htmlspecialchars($s['detail']) ?></small>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if ($errCount === 0): ?>
      <div class="d-flex gap-2 mb-4">
        <a href="index.php" class="btn btn-primary">
          <i class="bi bi-table me-1"></i>Aller au backoffice
        </a>
        <a href="import.php" class="btn btn-outline-primary">
          <i class="bi bi-upload me-1"></i>Importer des fichiers JSON
        </a>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- ── Formulaire ── -->
  <?php if (!$ran || $errCount > 0): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center gap-2">
      <i class="bi bi-database-gear text-primary fs-5"></i>
      <h5 class="mb-0">Créer / mettre à jour la base de données</h5>
    </div>
    <div class="card-body">

      <table class="table table-sm table-bordered mb-3 small">
        <tbody>
          <tr><th class="w-25 table-light">Hôte</th><td><code><?= DB_HOST ?>:<?= DB_PORT ?></code></td></tr>
          <tr><th class="table-light">Base</th><td><code><?= DB_NAME ?></code></td></tr>
          <tr><th class="table-light">Utilisateur</th><td><code><?= DB_USER ?></code></td></tr>
          <tr><th class="table-light">Charset</th><td><code><?= DB_CHARSET ?> / utf8mb4_unicode_ci</code></td></tr>
        </tbody>
      </table>

      <div class="alert alert-info small py-2 mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Le script est <strong>idempotent</strong> : vous pouvez le relancer sans risque —
        il utilise <code>CREATE IF NOT EXISTS</code> et des migrations incrémentales.
      </div>

      <form method="post">
        <button type="submit" name="run_setup" value="1" class="btn btn-primary px-4">
          <i class="bi bi-play-circle me-1"></i>Lancer l'installation
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Schéma de référence ── -->
  <div class="card shadow-sm">
    <div class="card-header bg-white">
      <h6 class="mb-0"><i class="bi bi-diagram-3 me-2 text-secondary"></i>Schéma de référence — table <code>submissions</code></h6>
    </div>
    <div class="card-body schema-box">
      <pre>
CREATE TABLE `submissions` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,

  -- JSON complet brut exporté par l'application
  `full_json`         LONGTEXT               DEFAULT NULL,
  -- Sous-objet metadata (enquêteur, dates, version…)
  `metadata`          LONGTEXT               DEFAULT NULL,
  -- Sous-objet data (toutes les sections du questionnaire)
  `data`              LONGTEXT               DEFAULT NULL,

  -- Colonnes indexées pour recherche rapide
  `original_filename` VARCHAR(255)           DEFAULT NULL,
  `menu`              VARCHAR(255)           DEFAULT NULL,   -- N° FISy ou nom PAP
  `submitted_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_submitted_at` (`submitted_at`),
  KEY `idx_menu`         (`menu`(100)),

  CONSTRAINT `chk_full_json` CHECK (JSON_VALID(`full_json`) OR `full_json` IS NULL),
  CONSTRAINT `chk_metadata`  CHECK (JSON_VALID(`metadata`)  OR `metadata`  IS NULL),
  CONSTRAINT `chk_data`      CHECK (JSON_VALID(`data`)      OR `data`      IS NULL)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;</pre>

      <h6 class="mt-3"><i class="bi bi-info-circle me-1 text-info"></i>my.ini recommandé (XAMPP)</h6>
      <pre>[mysqld]
character-set-server  = utf8mb4
collation-server      = utf8mb4_unicode_ci
max_allowed_packet    = 64M

[client]
default-character-set = utf8mb4</pre>
    </div>
  </div>

</div><!-- /card-setup -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
