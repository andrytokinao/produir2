<?php
require_once __DIR__ . '/db.php';

// ---- Vue active ----
$view     = $_GET['view']    ?? 'distrika';      // distrika | kaomina | fokontany | kaody | enqueteur
$filterD  = trim($_GET['d']  ?? '');             // filtre distrika
$filterK  = trim($_GET['k']  ?? '');             // filtre kaomina
$filterF  = trim($_GET['f']  ?? '');             // filtre fokontany
$filterE  = trim($_GET['e']  ?? '');             // filtre enquêteur
$search   = trim($_GET['q']  ?? '');

// ---- Chiffres globaux ----
$totals = $pdo->query("
    SELECT
        COUNT(*)                                                                                         AS total,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')))      AS nb_distrika,
        COUNT(DISTINCT CONCAT(
            JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')), '|',
            JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina'))
        ))                                                                                               AS nb_kaomina,
        COUNT(DISTINCT CONCAT(
            JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')), '|',
            JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina')), '|',
            JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany'))
        ))                                                                                               AS nb_fokontany,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaody_renirano'))) AS nb_kaody
    FROM submissions WHERE full_json IS NOT NULL
")->fetch(PDO::FETCH_ASSOC);

$grandTotal = (int)$totals['total'];
$nbEnqueteurs = (int)$pdo->query("
    SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.metadata.enqueteur')))
    FROM submissions WHERE full_json IS NOT NULL
")->fetchColumn();

// ====================================================================
//  REQUÊTES RÉCAPITULATIVES PAR NIVEAU
// ====================================================================

// ---- Niveau 1 : Distrika ----
$distrikaRows = $pdo->query("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')),'–')   AS distrika,
        COUNT(*)                                                                                     AS nb,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina')))  AS nb_kaomina,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany'))) AS nb_fokontany,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaody_renirano'))) AS nb_kaody
    FROM submissions WHERE full_json IS NOT NULL
    GROUP BY distrika
    ORDER BY nb DESC, distrika
")->fetchAll(PDO::FETCH_ASSOC);

// ---- Niveau 2 : Kaomina (commune) ----
$filterParams2 = [];
$w2 = ['full_json IS NOT NULL'];
if ($filterD !== '') {
    $w2[] = "JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')) = ?";
    $filterParams2[] = $filterD;
}
$w2sql = implode(' AND ', $w2);
$kaominaRows = $pdo->prepare("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')),'–')   AS distrika,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina')),'–')    AS kaomina,
        COUNT(*)                                                                                     AS nb,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany'))) AS nb_fokontany,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaody_renirano'))) AS nb_kaody
    FROM submissions WHERE $w2sql
    GROUP BY distrika, kaomina
    ORDER BY distrika, nb DESC, kaomina
");
$kaominaRows->execute($filterParams2);
$kaominaRows = $kaominaRows->fetchAll(PDO::FETCH_ASSOC);

// ---- Niveau 3 : Fokontany ----
$filterParams3 = [];
$w3 = ['full_json IS NOT NULL'];
if ($filterD !== '') {
    $w3[] = "JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')) = ?";
    $filterParams3[] = $filterD;
}
if ($filterK !== '') {
    $w3[] = "JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina')) = ?";
    $filterParams3[] = $filterK;
}
$w3sql = implode(' AND ', $w3);
$fokontanyRows = $pdo->prepare("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')),'–')   AS distrika,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina')),'–')    AS kaomina,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany')),'–')  AS fokontany,
        COUNT(*)                                                                                     AS nb,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaody_renirano'))) AS nb_kaody
    FROM submissions WHERE $w3sql
    GROUP BY distrika, kaomina, fokontany
    ORDER BY distrika, kaomina, nb DESC, fokontany
");
$fokontanyRows->execute($filterParams3);
$fokontanyRows = $fokontanyRows->fetchAll(PDO::FETCH_ASSOC);

// ---- Niveau 5 : Enquêteurs ----
$filterParamsE = [];
$wE = ['full_json IS NOT NULL'];
if ($filterD !== '') {
    $wE[] = "JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')) = ?";
    $filterParamsE[] = $filterD;
}
$wEsql = implode(' AND ', $wE);
$enqueteurRows = $pdo->prepare("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.metadata.enqueteur')),'–')         AS enqueteur,
        COUNT(*)                                                                             AS nb,
        MIN(DATE(submitted_at))                                                              AS premiere_date,
        MAX(DATE(submitted_at))                                                              AS derniere_date,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')))   AS nb_distrika,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany')))  AS nb_fokontany,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaody_renirano'))) AS nb_kaody
    FROM submissions WHERE $wEsql
    GROUP BY enqueteur
    ORDER BY nb DESC, enqueteur
");
$enqueteurRows->execute($filterParamsE);
$enqueteurRows = $enqueteurRows->fetchAll(PDO::FETCH_ASSOC);

// ---- Niveau 4 : Kaody Renirano ----
$filterParams4 = [];
$w4 = ['full_json IS NOT NULL'];
if ($filterD !== '') {
    $w4[] = "JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')) = ?";
    $filterParams4[] = $filterD;
}
if ($filterK !== '') {
    $w4[] = "JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina')) = ?";
    $filterParams4[] = $filterK;
}
if ($filterF !== '') {
    $w4[] = "JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany')) = ?";
    $filterParams4[] = $filterF;
}
$w4sql = implode(' AND ', $w4);
$kaodyRows = $pdo->prepare("
    SELECT
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.distrika')),'–')      AS distrika,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaomina')),'–')       AS kaomina,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.fokontany')),'–')     AS fokontany,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(full_json,'$.data.sec0_identification.kaody_renirano')),'–') AS kaody,
        COUNT(*)                                                                                        AS nb
    FROM submissions WHERE $w4sql
    GROUP BY distrika, kaomina, fokontany, kaody
    ORDER BY distrika, kaomina, fokontany, nb DESC, kaody
");
$kaodyRows->execute($filterParams4);
$kaodyRows = $kaodyRows->fetchAll(PDO::FETCH_ASSOC);

// ---- Filtre texte côté PHP (rapide car peu de lignes agrégées) ----
function filterRows(array $rows, string $q, array $cols): array {
    if ($q === '') return $rows;
    $q = mb_strtolower($q);
    return array_filter($rows, function($r) use ($q, $cols) {
        foreach ($cols as $c) {
            if (mb_strpos(mb_strtolower($r[$c] ?? ''), $q) !== false) return true;
        }
        return false;
    });
}

if ($search !== '') {
    $distrikaRows  = filterRows($distrikaRows,  $search, ['distrika']);
    $kaominaRows   = filterRows($kaominaRows,   $search, ['distrika', 'kaomina']);
    $fokontanyRows = filterRows($fokontanyRows, $search, ['distrika', 'kaomina', 'fokontany']);
    $kaodyRows     = filterRows($kaodyRows,     $search, ['distrika', 'kaomina', 'fokontany', 'kaody']);
    $enqueteurRows = filterRows($enqueteurRows, $search, ['enqueteur']);
}

// ---- Helper barre de progression ----
function progressBar(int $nb, int $total, string $color = 'primary'): string {
    $pct = $total > 0 ? round($nb / $total * 100, 1) : 0;
    return "<div class='d-flex align-items-center gap-2'>
              <div class='progress flex-grow-1' style='height:8px'>
                <div class='progress-bar bg-$color' style='width:{$pct}%'></div>
              </div>
              <span class='small text-muted text-nowrap'>{$pct} %</span>
            </div>";
}

function listUrl(string $field, string $value): string {
    $map = ['distrika' => 'dist'];
    // Pour un lien vers index.php filtré
    return 'index.php?' . http_build_query([$field => $value]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Récapitulatifs géographiques – PRODUIR 2</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f0f4f8; }
  .navbar-brand { font-weight: 700; }
  .kpi-card { border-top: 4px solid; transition: box-shadow .15s; }
  .kpi-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12) !important; }
  .kpi-distrika { border-color: #0d6efd; }
  .kpi-kaomina  { border-color: #198754; }
  .kpi-fokontany{ border-color: #fd7e14; }
  .kpi-kaody    { border-color: #6f42c1; }
  .text-teal    { color: #20c997 !important; }
  .nav-tabs .nav-link.active { font-weight: 600; }
  table td, table th { vertical-align: middle; }
  .geo-badge { font-size: .7rem; }
  .row-link { cursor: pointer; }
  .row-link:hover td { background: #e8f0fe !important; }
  .progress { min-width: 80px; }
  thead th { white-space: nowrap; }
</style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4 shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">
      <i class="bi bi-arrow-left me-2"></i>PRODUIR 2 – Backoffice
    </a>
    <span class="text-white-50 small"><i class="bi bi-geo-alt me-1"></i>Récapitulatifs géographiques</span>
  </div>
</nav>

<div class="container-fluid px-4 pb-5">

  <!-- ===== KPI globaux ===== -->
  <div class="row g-3 mb-4 row-cols-2 row-cols-md-5">
    <div class="col">
      <div class="card kpi-card kpi-distrika shadow-sm h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold text-primary"><?= $totals['nb_distrika'] ?></div>
          <div class="text-muted small">Distrika</div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card kpi-card kpi-kaomina shadow-sm h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold text-success"><?= $totals['nb_kaomina'] ?></div>
          <div class="text-muted small">Kaomina (Communes)</div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card kpi-card kpi-fokontany shadow-sm h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold" style="color:#fd7e14"><?= $totals['nb_fokontany'] ?></div>
          <div class="text-muted small">Fokontany</div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card kpi-card kpi-kaody shadow-sm h-100">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold text-purple" style="color:#6f42c1"><?= $totals['nb_kaody'] ?></div>
          <div class="text-muted small">Kaody Renirano</div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card kpi-card shadow-sm h-100" style="border-top-color:#20c997">
        <div class="card-body text-center py-3">
          <div class="fs-1 fw-bold" style="color:#20c997"><?= $nbEnqueteurs ?></div>
          <div class="text-muted small">Enquêteurs</div>
        </div>
      </div>
    </div>
  </div>
  <div class="text-center text-muted small mb-4">
    <i class="bi bi-people me-1"></i>Total : <strong><?= $grandTotal ?></strong> soumission(s)
  </div>

  <!-- ===== Filtres / recherche ===== -->
  <form method="get" class="row g-2 mb-3 align-items-end">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
    <div class="col-md-4">
      <label class="form-label fw-semibold small">Recherche rapide</label>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
             class="form-control" placeholder="Nom de la zone…">
    </div>
    <?php if (in_array($view, ['kaomina','fokontany','kaody'])): ?>
    <div class="col-md-3">
      <label class="form-label fw-semibold small">Filtrer par Distrika</label>
      <select name="d" class="form-select">
        <option value="">— Tous —</option>
        <?php foreach ($distrikaRows as $dr): ?>
          <option value="<?= htmlspecialchars($dr['distrika']) ?>"
                  <?= $filterD === $dr['distrika'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($dr['distrika']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if (in_array($view, ['fokontany','kaody'])): ?>
    <div class="col-md-3">
      <label class="form-label fw-semibold small">Filtrer par Kaomina</label>
      <select name="k" class="form-select">
        <option value="">— Toutes —</option>
        <?php
        $kaominas = array_unique(array_column($kaominaRows, 'kaomina'));
        sort($kaominas);
        foreach ($kaominas as $km): ?>
          <option value="<?= htmlspecialchars($km) ?>" <?= $filterK === $km ? 'selected' : '' ?>>
            <?= htmlspecialchars($km) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if ($view === 'kaody'): ?>
    <div class="col-md-3">
      <label class="form-label fw-semibold small">Filtrer par Fokontany</label>
      <select name="f" class="form-select">
        <option value="">— Tous —</option>
        <?php
        $fokonts = array_unique(array_column($fokontanyRows, 'fokontany'));
        sort($fokonts);
        foreach ($fokonts as $fk): ?>
          <option value="<?= htmlspecialchars($fk) ?>" <?= $filterF === $fk ? 'selected' : '' ?>>
            <?= htmlspecialchars($fk) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary"><i class="bi bi-filter me-1"></i>Filtrer</button>
      <a href="stats.php?view=<?= $view ?>" class="btn btn-outline-secondary ms-1">Réinitialiser</a>
    </div>
  </form>

  <!-- ===== Onglets ===== -->
  <ul class="nav nav-tabs mb-0" id="geoTabs">
    <?php
    $tabs = [
        'distrika'   => ['label' => '<i class="bi bi-map me-1"></i>Distrika',              'color' => 'text-primary'],
        'kaomina'    => ['label' => '<i class="bi bi-pin-map me-1"></i>Kaomina',           'color' => 'text-success'],
        'fokontany'  => ['label' => '<i class="bi bi-geo-alt me-1"></i>Fokontany',         'color' => 'text-warning'],
        'kaody'      => ['label' => '<i class="bi bi-signpost-2 me-1"></i>Kaody Renirano', 'color' => 'text-purple'],
        'enqueteur'  => ['label' => '<i class="bi bi-person-badge me-1"></i>Enquêteurs',   'color' => 'text-teal'],
    ];
    foreach ($tabs as $v => $t):
        $qs = http_build_query(['view' => $v, 'q' => $search, 'd' => $filterD, 'k' => $filterK]);
    ?>
    <li class="nav-item">
      <a class="nav-link <?= $view === $v ? 'active' : '' ?> <?= $t['color'] ?>"
         href="stats.php?<?= $qs ?>">
        <?= $t['label'] ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="card shadow-sm border-top-0" style="border-radius:0 0 .5rem .5rem">
    <div class="card-body p-0">

      <!-- ========== TAB : DISTRIKA ========== -->
      <?php if ($view === 'distrika'): ?>
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-primary">
            <tr>
              <th>Distrika</th>
              <th class="text-center">Soumissions</th>
              <th>Progression</th>
              <th class="text-center">Kaomina</th>
              <th class="text-center">Fokontany</th>
              <th class="text-center">Kaody Renirano</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$distrikaRows): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Aucune donnée.</td></tr>
            <?php else: foreach ($distrikaRows as $r): ?>
            <tr class="row-link"
                onclick="window.location='index.php?dist=<?= urlencode($r['distrika']) ?>'">
              <td class="fw-semibold"><i class="bi bi-map me-1 text-primary"></i><?= htmlspecialchars($r['distrika']) ?></td>
              <td class="text-center"><span class="badge bg-primary fs-6"><?= $r['nb'] ?></span></td>
              <td style="min-width:160px"><?= progressBar((int)$r['nb'], $grandTotal) ?></td>
              <td class="text-center"><?= $r['nb_kaomina'] ?></td>
              <td class="text-center"><?= $r['nb_fokontany'] ?></td>
              <td class="text-center"><?= $r['nb_kaody'] ?></td>
              <td class="text-center" onclick="event.stopPropagation()">
                <a href="stats.php?view=kaomina&d=<?= urlencode($r['distrika']) ?>"
                   class="btn btn-sm btn-outline-primary" title="Détails communes">
                  <i class="bi bi-zoom-in"></i>
                </a>
                <a href="index.php?dist=<?= urlencode($r['distrika']) ?>"
                   class="btn btn-sm btn-outline-secondary" title="Voir soumissions">
                  <i class="bi bi-list-ul"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr class="table-light fw-bold">
              <td>TOTAL</td>
              <td class="text-center"><?= $grandTotal ?></td>
              <td></td>
              <td class="text-center"><?= $totals['nb_kaomina'] ?></td>
              <td class="text-center"><?= $totals['nb_fokontany'] ?></td>
              <td class="text-center"><?= $totals['nb_kaody'] ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ========== TAB : KAOMINA ========== -->
      <?php elseif ($view === 'kaomina'): ?>
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-success">
            <tr>
              <th>Distrika</th>
              <th>Kaomina (Commune)</th>
              <th class="text-center">Soumissions</th>
              <th>Progression</th>
              <th class="text-center">Fokontany</th>
              <th class="text-center">Kaody Renirano</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$kaominaRows): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Aucune donnée.</td></tr>
            <?php else:
              $prevD = null;
              foreach ($kaominaRows as $r):
                  $distFlag = $r['distrika'] !== $prevD;
                  $prevD    = $r['distrika'];
            ?>
            <tr class="row-link"
                onclick="window.location='stats.php?view=fokontany&d=<?= urlencode($r['distrika']) ?>&k=<?= urlencode($r['kaomina']) ?>'">
              <td>
                <?php if ($distFlag): ?>
                  <span class="badge bg-primary geo-badge"><?= htmlspecialchars($r['distrika']) ?></span>
                <?php else: ?>
                  <span class="text-muted small">↳</span>
                <?php endif; ?>
              </td>
              <td class="fw-semibold"><i class="bi bi-pin-map me-1 text-success"></i><?= htmlspecialchars($r['kaomina']) ?></td>
              <td class="text-center"><span class="badge bg-success fs-6"><?= $r['nb'] ?></span></td>
              <td style="min-width:140px"><?= progressBar((int)$r['nb'], $grandTotal, 'success') ?></td>
              <td class="text-center"><?= $r['nb_fokontany'] ?></td>
              <td class="text-center"><?= $r['nb_kaody'] ?></td>
              <td class="text-center" onclick="event.stopPropagation()">
                <a href="stats.php?view=fokontany&d=<?= urlencode($r['distrika']) ?>&k=<?= urlencode($r['kaomina']) ?>"
                   class="btn btn-sm btn-outline-success" title="Détails fokontany">
                  <i class="bi bi-zoom-in"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ========== TAB : FOKONTANY ========== -->
      <?php elseif ($view === 'fokontany'): ?>
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-warning">
            <tr>
              <th>Distrika</th>
              <th>Kaomina</th>
              <th>Fokontany</th>
              <th class="text-center">Soumissions</th>
              <th>Progression</th>
              <th class="text-center">Kaody Renirano</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$fokontanyRows): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Aucune donnée.</td></tr>
            <?php else:
              $prevD = $prevK = null;
              foreach ($fokontanyRows as $r):
                  $distFlag  = $r['distrika'] !== $prevD;
                  $kaomFlag  = $r['kaomina']  !== $prevK || $distFlag;
                  $prevD = $r['distrika']; $prevK = $r['kaomina'];
            ?>
            <tr class="row-link"
                onclick="window.location='stats.php?view=kaody&d=<?= urlencode($r['distrika']) ?>&k=<?= urlencode($r['kaomina']) ?>&f=<?= urlencode($r['fokontany']) ?>'">
              <td>
                <?php if ($distFlag): ?>
                  <span class="badge bg-primary geo-badge"><?= htmlspecialchars($r['distrika']) ?></span>
                <?php else: echo '<span class="text-muted small">·</span>'; endif; ?>
              </td>
              <td>
                <?php if ($kaomFlag): ?>
                  <span class="badge bg-success geo-badge"><?= htmlspecialchars($r['kaomina']) ?></span>
                <?php else: echo '<span class="text-muted small">·</span>'; endif; ?>
              </td>
              <td class="fw-semibold"><i class="bi bi-geo-alt me-1" style="color:#fd7e14"></i><?= htmlspecialchars($r['fokontany']) ?></td>
              <td class="text-center"><span class="badge fs-6" style="background:#fd7e14"><?= $r['nb'] ?></span></td>
              <td style="min-width:140px"><?= progressBar((int)$r['nb'], $grandTotal, 'warning') ?></td>
              <td class="text-center"><?= $r['nb_kaody'] ?></td>
              <td class="text-center" onclick="event.stopPropagation()">
                <a href="stats.php?view=kaody&d=<?= urlencode($r['distrika']) ?>&k=<?= urlencode($r['kaomina']) ?>&f=<?= urlencode($r['fokontany']) ?>"
                   class="btn btn-sm btn-outline-warning" title="Kaody Renirano">
                  <i class="bi bi-zoom-in"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ========== TAB : KAODY RENIRANO ========== -->
      <?php elseif ($view === 'kaody'): ?>
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-secondary">
            <tr>
              <th>Distrika</th>
              <th>Kaomina</th>
              <th>Fokontany</th>
              <th>Kaody Renirano</th>
              <th class="text-center">Soumissions</th>
              <th>Progression</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$kaodyRows): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Aucune donnée.</td></tr>
            <?php else:
              $prevD = $prevK = $prevF = null;
              foreach ($kaodyRows as $r):
                  $distFlag  = $r['distrika']  !== $prevD;
                  $kaomFlag  = $r['kaomina']   !== $prevK || $distFlag;
                  $fokFlag   = $r['fokontany'] !== $prevF || $kaomFlag;
                  $prevD = $r['distrika']; $prevK = $r['kaomina']; $prevF = $r['fokontany'];
            ?>
            <tr class="row-link"
                onclick="window.location='index.php?q=<?= urlencode($r['kaody']) ?>'">
              <td><?php if ($distFlag): ?><span class="badge bg-primary geo-badge"><?= htmlspecialchars($r['distrika']) ?></span><?php else: echo '<span class="text-muted">·</span>'; endif; ?></td>
              <td><?php if ($kaomFlag): ?><span class="badge bg-success geo-badge"><?= htmlspecialchars($r['kaomina']) ?></span><?php else: echo '<span class="text-muted">·</span>'; endif; ?></td>
              <td><?php if ($fokFlag):  ?><span class="badge geo-badge" style="background:#fd7e14"><?= htmlspecialchars($r['fokontany']) ?></span><?php else: echo '<span class="text-muted">·</span>'; endif; ?></td>
              <td class="fw-semibold"><i class="bi bi-signpost-2 me-1" style="color:#6f42c1"></i><?= htmlspecialchars($r['kaody']) ?></td>
              <td class="text-center"><span class="badge fs-6" style="background:#6f42c1"><?= $r['nb'] ?></span></td>
              <td style="min-width:140px"><?= progressBar((int)$r['nb'], $grandTotal, 'secondary') ?></td>
              <td class="text-center" onclick="event.stopPropagation()">
                <a href="index.php?q=<?= urlencode($r['kaody']) ?>"
                   class="btn btn-sm btn-outline-secondary" title="Voir soumissions">
                  <i class="bi bi-list-ul"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <!-- ========== TAB : ENQUÊTEURS ========== -->
      <?php if ($view === 'enqueteur'): ?>
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead style="background:#d1f2eb">
            <tr>
              <th>#</th>
              <th>Enquêteur</th>
              <th class="text-center">Soumissions</th>
              <th>Progression</th>
              <th class="text-center">Distrika</th>
              <th class="text-center">Fokontany</th>
              <th class="text-center">Kaody</th>
              <th class="text-center">Première</th>
              <th class="text-center">Dernière</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$enqueteurRows): ?>
              <tr><td colspan="10" class="text-center text-muted py-4">Aucune donnée.</td></tr>
            <?php else:
              $rank = 0;
              foreach ($enqueteurRows as $r):
                  $rank++;
            ?>
            <tr class="row-link"
                onclick="window.location='index.php?q=<?= urlencode($r['enqueteur']) ?>'">
              <td class="text-muted small"><?= $rank ?></td>
              <td class="fw-semibold">
                <i class="bi bi-person-circle me-1" style="color:#20c997"></i>
                <?= htmlspecialchars($r['enqueteur']) ?>
              </td>
              <td class="text-center">
                <span class="badge fs-6" style="background:#20c997"><?= $r['nb'] ?></span>
              </td>
              <td style="min-width:150px"><?= progressBar((int)$r['nb'], $grandTotal, 'success') ?></td>
              <td class="text-center"><?= $r['nb_distrika'] ?></td>
              <td class="text-center"><?= $r['nb_fokontany'] ?></td>
              <td class="text-center"><?= $r['nb_kaody'] ?></td>
              <td class="text-center small text-nowrap"><?= htmlspecialchars($r['premiere_date'] ?? '–') ?></td>
              <td class="text-center small text-nowrap"><?= htmlspecialchars($r['derniere_date'] ?? '–') ?></td>
              <td class="text-center" onclick="event.stopPropagation()">
                <a href="index.php?q=<?= urlencode($r['enqueteur']) ?>"
                   class="btn btn-sm btn-outline-secondary" title="Voir soumissions">
                  <i class="bi bi-list-ul"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="2">TOTAL — <?= count($enqueteurRows) ?> enquêteur(s)</td>
              <td class="text-center"><?= $grandTotal ?></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>

    </div><!-- /card-body -->
  </div><!-- /card -->

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
