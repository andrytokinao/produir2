<?php
require_once __DIR__ . '/db.php';

// ---- Messages flash ----
$msg = null;
if (isset($_GET['msg'])) {
    $msg = match($_GET['msg']) {
        'deleted'  => ['type' => 'success', 'text' => 'Enregistrement supprimé avec succès.'],
        'imported' => ['type' => 'success', 'text' => 'Fichier JSON importé avec succès.'],
        'error'    => ['type' => 'danger',  'text' => 'Une erreur est survenue.'],
        default    => null,
    };
}

// ---- Filtres ----
$search   = trim($_GET['q']    ?? '');
$distrika = trim($_GET['dist'] ?? '');
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.metadata.enqueteur')) LIKE ?
     OR JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec0_identification.laharana_fisy')) LIKE ?
     OR TRIM(CONCAT(
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec1_pap.anarana')),''),
            ' ',
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec1_pap.fanampiny')),'')
        )) LIKE ?
    )";
    $like   = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($distrika !== '') {
    $where[]  = "JSON_UNQUOTE(JSON_EXTRACT(full_json, '$.data.sec0_identification.distrika')) = ?";
    $params[] = $distrika;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- Total ----
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions $whereSql");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// ---- Distrika disponibles ----
$distStmt  = $pdo->query("SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')) AS d
                           FROM submissions WHERE full_json IS NOT NULL ORDER BY d");
$distrikas = array_filter(array_column($distStmt->fetchAll(PDO::FETCH_ASSOC), 'd'));

// ---- Données ----
$sql = "SELECT
    id,
    submitted_at,
    original_filename,
    JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.metadata.enqueteur'))                           AS enqueteur,
    JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.metadata.submissionDateLocal'))                 AS date_local,
    JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika'))            AS distrika,
    JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany'))           AS fokontany,
    JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.laharana_fisy'))       AS laharana_fisy,
    TRIM(CONCAT(
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec1_pap.anarana')),''),
        ' ',
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec1_pap.fanampiny')),'')
    ))                                                                                      AS pap_name
FROM submissions
$whereSql
ORDER BY submitted_at DESC
LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PRODUIR 2 – Backoffice</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f0f4f8; }
  .navbar-brand { font-weight: 700; letter-spacing: .5px; }
  .stat-card { border-left: 4px solid #0d6efd; }
  .pap-name { font-weight: 600; }
  .badge-fisy { font-size: .72rem; }
  tfoot td { background: #f8f9fa; font-weight: 600; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm">
  <div class="container-fluid">
    <span class="navbar-brand"><i class="bi bi-clipboard2-data me-2"></i>PRODUIR 2 – Backoffice</span>
    <div class="d-flex gap-2">
      <a href="stats.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-bar-chart-map me-1"></i>Récapitulatifs
      </a>
      <a href="stats.php?view=enqueteur" class="btn btn-outline-light btn-sm">
        <i class="bi bi-person-badge me-1"></i>Par enquêteur
      </a>
      <a href="upload.php" class="btn btn-light btn-sm">
        <i class="bi bi-upload me-1"></i>Importer JSON
      </a>
    </div>
  </div>
</nav>

<div class="container-fluid px-4">

<?php if ($msg): ?>
  <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg['text']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

  <!-- Statistique -->
  <div class="row g-3 mb-4">
    <div class="col-sm-auto">
      <div class="card stat-card shadow-sm">
        <div class="card-body d-flex align-items-center gap-3 py-3">
          <i class="bi bi-people fs-2 text-primary"></i>
          <div>
            <div class="fs-3 fw-bold"><?= $total ?></div>
            <div class="text-muted small">Soumissions</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtres -->
  <form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-md-5">
      <label class="form-label fw-semibold small">Recherche</label>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
             class="form-control" placeholder="Enquêteur, N° FISy, Nom PAP…">
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold small">Distrika</label>
      <select name="dist" class="form-select">
        <option value="">— Tous —</option>
        <?php foreach ($distrikas as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $distrika === $d ? 'selected' : '' ?>>
            <?= htmlspecialchars($d) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-search me-1"></i>Filtrer
      </button>
      <a href="index.php" class="btn btn-outline-secondary ms-1">Réinitialiser</a>
    </div>
  </form>

  <!-- Tableau -->
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-primary">
            <tr>
              <th>#</th>
              <th>N° FISy</th>
              <th>Nom PAP</th>
              <th>Enquêteur</th>
              <th>Distrika</th>
              <th>Fokontany</th>
              <th>Date soumission</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-2 d-block mb-2"></i>Aucun enregistrement trouvé.
                </td>
              </tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td class="text-muted small"><?= (int)$r['id'] ?></td>
                <td>
                  <span class="badge bg-secondary badge-fisy">
                    <?= htmlspecialchars($r['laharana_fisy'] ?: '–') ?>
                  </span>
                </td>
                <td class="pap-name"><?= htmlspecialchars($r['pap_name'] ?: '–') ?></td>
                <td><?= htmlspecialchars($r['enqueteur'] ?: '–') ?></td>
                <td><?= htmlspecialchars($r['distrika'] ?: '–') ?></td>
                <td><?= htmlspecialchars($r['fokontany'] ?: '–') ?></td>
                <td class="small text-nowrap">
                  <?= htmlspecialchars($r['date_local'] ?: substr($r['submitted_at'] ?? '', 0, 16)) ?>
                </td>
                <td class="text-center text-nowrap">
                  <a href="view.php?id=<?= $r['id'] ?>"
                     class="btn btn-sm btn-outline-primary" title="Voir le détail">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="download.php?id=<?= $r['id'] ?>"
                     class="btn btn-sm btn-outline-success" title="Télécharger JSON">
                    <i class="bi bi-download"></i>
                  </a>
                  <a href="delete.php?id=<?= $r['id'] ?>"
                     class="btn btn-sm btn-outline-danger" title="Supprimer"
                     onclick="return confirm('Supprimer cet enregistrement définitivement ?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted"><?= $total ?> enregistrement(s) – page <?= $page ?>/<?= $totalPages ?></small>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
            <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
              <a class="page-link"
                 href="?p=<?= $pg ?>&q=<?= urlencode($search) ?>&dist=<?= urlencode($distrika) ?>">
                <?= $pg ?>
              </a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
  </div><!-- /card -->

</div><!-- /container -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
