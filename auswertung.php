<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

// ---- Abgeschlossenes Quiz speichern ----
// Die Antworten wurden in quiz.php bereits Flagge für Flagge geprüft.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q = $_SESSION['quiz'] ?? null;
    if (!$q || !hash_equals($q['token'], (string)($_POST['token'] ?? ''))
        || count($q['antworten']) !== count($q['codes'])) {
        header('Location: ' . ($q ? 'quiz.php' : 'index.php'));
        exit;
    }
    unset($_SESSION['quiz']); // verhindert doppeltes Speichern

    $spielart = $q['spielart'] ?? 'flaggen';
    $umrisse = $spielart === 'umrisse';
    $details = array_values($q['antworten']);
    $lr = $hr = 0;
    foreach ($details as $d) {
        $lr += $d['pl'] !== 'falsch' ? 1 : 0;
        $hr += (!$umrisse && $d['ph'] !== 'falsch') ? 1 : 0;
    }
    $anzahl = count($q['codes']);
    // Flaggen: Land + Hauptstadt = 2 Punkte pro Frage, Umrisse: 1 Punkt
    $max = $anzahl * ($umrisse ? 1 : 2);
    $punkte = $lr + $hr;

    $st = db()->prepare('INSERT INTO resultate
        (zeit, name, spielart, stufe, modus, anzahl, land_richtig, haupt_richtig, punkte, max_punkte, prozent, dauer_sek, details)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        date('Y-m-d H:i:s'), $q['name'], $spielart, $umrisse ? ($q['stufe'] ?? '') : '', $q['modus'], $anzahl, $lr, $hr, $punkte, $max,
        round($punkte / $max * 100, 1), time() - $q['start'],
        json_encode($details, JSON_UNESCAPED_UNICODE),
    ]);
    $id = (int)db()->lastInsertId();
    $_SESSION['meine'][] = $id;
    header('Location: auswertung.php?id=' . $id);
    exit;
}

// ---- Resultat anzeigen ----
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM resultate WHERE id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    header('Location: index.php');
    exit;
}
// Einordnung in die Rangliste der gleichen Kombination (Spielart, Stufe, Umfang)
$umrisse = $r['spielart'] === 'umrisse';
$kombi = kombi_name($r['spielart'], $r['stufe'], $r['modus']);
$liste = rangliste($r['spielart'], $r['stufe'], $r['modus']);
$platz = 0;
foreach ($liste as $z) {
    if ((int)$z['id'] === $id) {
        $platz = (int)$z['rang'];
        break;
    }
}
$eigenes = in_array($id, $_SESSION['meine'] ?? [], true);
$details = json_decode($r['details'], true);
$p = (float)$r['prozent'];
$lob = $p >= 95 ? 'Hervorragend!' : ($p >= 80 ? 'Sehr gut!' : ($p >= 60 ? 'Gut gemacht, weiter üben!' : 'Da geht noch mehr, übe die roten Felder nochmals.'));

function zelle(string $eingabe, string $status, string $loesung): string
{
    $cls = $status === 'richtig' ? 'ok' : ($status === 'tippfehler' ? 'tipp' : 'falsch');
    $eingabe = $eingabe === '' ? '<em>leer</em>' : h($eingabe);
    $html = '<td class="' . $cls . '"><span class="ein">' . $eingabe . '</span>';
    if ($status !== 'richtig') {
        $html .= '<span class="soll">' . ($status === 'tippfehler' ? 'korrekt: ' : '') . h($loesung) . '</span>';
    }
    return $html . '</td>';
}

kopf('Auswertung · Flaggenquiz Europa');
?>
<header class="leiste">
  <h1>Auswertung <span class="kombi-chip"><?= h($kombi) ?></span></h1>
  <span><?= h($r['name']) ?> · <?= h(date('d.m.Y H:i', strtotime($r['zeit']))) ?></span>
</header>

<section class="karte bilanz">
  <div class="gross"><?= (int)$r['punkte'] ?> / <?= (int)$r['max_punkte'] ?> <small>Punkte</small></div>
  <div class="balken"><span style="width: <?= $p ?>%"></span></div>
  <p class="lob"><?= h($lob) ?></p>
  <dl>
    <?php if ($umrisse): ?>
    <div><dt>Länder erkannt</dt><dd><?= (int)$r['land_richtig'] ?> / <?= (int)$r['anzahl'] ?></dd></div>
    <?php else: ?>
    <div><dt>Länder</dt><dd><?= (int)$r['land_richtig'] ?> / <?= (int)$r['anzahl'] ?></dd></div>
    <div><dt>Hauptstädte</dt><dd><?= (int)$r['haupt_richtig'] ?> / <?= (int)$r['anzahl'] ?></dd></div>
    <?php endif; ?>
    <div><dt>Ergebnis</dt><dd><?= number_format($p, 1, '.', '') ?> %</dd></div>
    <div><dt>Zeit</dt><dd><?= h(dauer((int)$r['dauer_sek'])) ?></dd></div>
  </dl>
</section>

<section class="karte platzierung">
  <div class="platz">
    <span class="platz-nr"><?= $platz ?>.</span>
    <span>
      <strong><?= $eigenes ? 'Dein Platz' : 'Platz' ?> von <?= count($liste) ?></strong>
      <small>Rangliste «<?= h($kombi) ?>» · sortiert nach Prozent, bei Gleichstand zählt die schnellere Zeit</small>
    </span>
  </div>
  <div class="rang-scroll" id="rang-scroll">
    <?php rangliste_tabelle($liste, $r['spielart'], $id, $eigenes ? 'du' : 'dieses'); ?>
  </div>
</section>
<script>
// Eigene Zeile in der Rangliste sichtbar scrollen
(function () {
  var box = document.getElementById('rang-scroll'), zeile = box && box.querySelector('tr.markiert');
  if (zeile) box.scrollTop = zeile.offsetTop - box.clientHeight / 2 + zeile.offsetHeight;
})();
</script>

<h2 class="zwischentitel"><?= $eigenes ? 'Deine Antworten' : 'Antworten' ?></h2>
<div class="karte tabelle-wrap">
<table class="auswertung">
  <?php if ($umrisse): ?>
  <thead><tr><th>#</th><th>Umriss</th><th>Gesucht</th><th><?= $eigenes ? 'Deine Antwort' : 'Antwort' ?></th></tr></thead>
  <tbody>
  <?php foreach ($details as $i => $d): ?>
    <tr>
      <td class="nr"><?= $i + 1 ?></td>
      <td><img class="umriss-klein" src="<?= h(umriss($d['c'])) ?>" alt=""></td>
      <td class="gesucht"><?= h(loesung_land($d['c'])) ?></td>
      <?php if (isset($d['w'])): // Leicht/Mittel: ausgewählt ?>
        <td class="<?= $d['pl'] === 'richtig' ? 'ok' : 'falsch' ?>">
          <span class="ein"><?php if ($r['stufe'] === 'mittel'): ?><img class="flagge-klein" src="<?= h(flagge($d['w'])) ?>" alt=""> <?php endif; ?><?= h(loesung_land($d['w'])) ?></span>
          <?php if ($d['pl'] !== 'richtig'): ?>
            <span class="soll"><?php if ($r['stufe'] === 'mittel'): ?><img class="flagge-klein" src="<?= h(flagge($d['c'])) ?>" alt=""> <?php endif; ?><?= h(loesung_land($d['c'])) ?></span>
          <?php endif; ?>
        </td>
      <?php else: // Schwer: geschrieben ?>
        <?= zelle($d['l'], $d['pl'], loesung_land($d['c'])) ?>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php else: ?>
  <thead><tr><th>#</th><th>Flagge</th><th>Land</th><th>Hauptstadt</th></tr></thead>
  <tbody>
  <?php foreach ($details as $i => $d): ?>
    <tr>
      <td class="nr"><?= $i + 1 ?></td>
      <td><img src="<?= h(flagge($d['c'])) ?>" alt=""></td>
      <?= zelle($d['l'], $d['pl'], loesung_land($d['c'])) ?>
      <?= zelle($d['h'], $d['ph'], loesung_haupt($d['c'])) ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php endif; ?>
</table>
</div>

<div class="abschluss">
  <a class="sekundaer" href="ergebnisse.php?spielart=<?= h($r['spielart']) ?>&amp;stufe=<?= h($r['stufe']) ?>&amp;modus=<?= h($r['modus']) ?>">Alle Ergebnisse</a>
  <a class="knopf" href="index.php">Nochmals üben</a>
</div>
<?php fuss();
