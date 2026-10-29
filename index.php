<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

$fehler = '';
// Zuletzt gewählte Kombination vorauswählen
[$spielart, $stufe, $modus] = $_SESSION['letzte_kombi'] ?? ['flaggen', '', 'kurz'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $name = mb_substr(preg_replace('/\s+/u', ' ', $name) ?? '', 0, 40);
    [$spielart, $stufe, $modus] = kombi_aus($_POST);

    if ($name === '') {
        $fehler = 'Bitte gib deinen Namen ein.';
    } else {
        $codes = array_keys(LAENDER);
        shuffle($codes);
        if ($modus === 'kurz') {
            $codes = array_slice($codes, 0, ANZAHL_KURZ);
        }
        // Antwortmöglichkeiten gleich beim Start festlegen (Neuladen ändert sie nicht)
        $optionen = [];
        if ($spielart === 'umrisse' && $stufe !== 'schwer') {
            foreach ($codes as $i => $c) {
                $optionen[$i] = antwortmoeglichkeiten($c, $stufe);
            }
        }
        $_SESSION['quiz'] = [
            'token'    => bin2hex(random_bytes(16)),
            'name'     => $name,
            'spielart' => $spielart,
            'stufe'    => $stufe,
            'modus'    => $modus,
            'codes'    => $codes,
            'optionen' => $optionen,
            'start'    => time(),
            'pos'      => 0,      // aktuelle Frage
            'antworten' => [],    // geprüfte Antworten je Position
        ];
        $_SESSION['letzter_name'] = $name;
        $_SESSION['letzte_kombi'] = [$spielart, $stufe, $modus];
        header('Location: quiz.php');
        exit;
    }
}

// Bestenliste: welche Kombination wird gezeigt?
$bl = $_GET['bl'] ?? ($spielart === 'umrisse' ? $stufe : 'flaggen');
if ($bl !== 'flaggen' && !isset(STUFEN[$bl])) {
    $bl = 'flaggen';
}
$blSpielart = $bl === 'flaggen' ? 'flaggen' : 'umrisse';
$blStufe = $bl === 'flaggen' ? '' : $bl;

$alle = count(LAENDER);
$stufeGewaehlt = $stufe ?: 'mittel';
kopf('Flaggenquiz Europa');
?>
<header class="hero">
  <h1>Flaggenquiz Europa</h1>
  <p>Flaggen und Umrisse erkennen – Länder und Hauptstädte üben.</p>
</header>

<form method="post" class="karte start" id="startform">
  <?php if ($fehler): ?><p class="meldung"><?= h($fehler) ?></p><?php endif; ?>
  <label for="name">Dein Name</label>
  <input id="name" name="name" maxlength="40" required autocomplete="off"
         value="<?= h($_POST['name'] ?? $_SESSION['letzter_name'] ?? '') ?>">

  <fieldset>
    <legend>Was willst du üben?</legend>
    <div class="wahl-zwei">
      <label class="wahl spielart">
        <input type="radio" name="spielart" value="flaggen" <?= $spielart === 'flaggen' ? 'checked' : '' ?>>
        <img src="<?= h(flagge('ch')) ?>" alt="">
        <span><strong>Flaggen</strong><small>Flagge sehen, Land und Hauptstadt schreiben</small></span>
      </label>
      <label class="wahl spielart">
        <input type="radio" name="spielart" value="umrisse" <?= $spielart === 'umrisse' ? 'checked' : '' ?>>
        <img src="<?= h(umriss('it')) ?>" alt="">
        <span><strong>Umrisse <em class="neu">NEU</em></strong><small>Land an seiner Form erkennen</small></span>
      </label>
    </div>
  </fieldset>

  <fieldset id="stufen">
    <legend>Stufe</legend>
    <div class="wahl-drei">
      <?php
      $beschrieb = [
          'leicht' => 'Land aus 4 Namen auswählen',
          'mittel' => 'Passende Flagge aus 4 auswählen',
          'schwer' => 'Land selbst schreiben',
      ];
      $punkte = ['leicht' => 1, 'mittel' => 2, 'schwer' => 3];
      foreach (STUFEN as $s => $titel): ?>
        <label class="wahl stufe">
          <span class="stufe-kopf">
            <input type="radio" name="stufe" value="<?= $s ?>" <?= $stufeGewaehlt === $s ? 'checked' : '' ?>>
            <span class="punkte punkte-<?= $s ?>" aria-hidden="true"><?= str_repeat('<i class="an"></i>', $punkte[$s]) . str_repeat('<i></i>', 3 - $punkte[$s]) ?></span>
          </span>
          <strong><?= $titel ?></strong>
          <small><?= $beschrieb[$s] ?></small>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Umfang</legend>
    <div class="wahl-zwei">
      <label class="wahl">
        <input type="radio" name="modus" value="kurz" <?= $modus === 'kurz' ? 'checked' : '' ?>>
        <span><strong>Kurz</strong> · <?= ANZAHL_KURZ ?> zufällige Länder</span>
      </label>
      <label class="wahl">
        <input type="radio" name="modus" value="komplett" <?= $modus === 'komplett' ? 'checked' : '' ?>>
        <span><strong>Komplett</strong> · alle <?= $alle ?> Länder</span>
      </label>
    </div>
  </fieldset>

  <button type="submit">Los geht's</button>
</form>
<p class="klein">Tipp: Umlaute und Gross-/Kleinschreibung sind egal (ue statt ü geht auch).</p>
<script>
// Stufe nur zeigen, wenn "Umrisse" gewählt ist (ohne JavaScript bleibt sie sichtbar)
(function () {
  var form = document.getElementById('startform'), stufen = document.getElementById('stufen');
  function zeigen() { stufen.hidden = !form.querySelector('input[name=spielart][value=umrisse]').checked; }
  form.addEventListener('change', zeigen);
  zeigen();
})();
</script>

<section class="bestenliste" id="bestenliste">
  <div class="bl-kopf">
    <h2>Bestenliste</h2>
    <a class="sekundaer" href="ergebnisse.php?spielart=<?= $blSpielart ?>&amp;stufe=<?= h($blStufe) ?>">Alle Ergebnisse →</a>
  </div>
  <nav class="chips" aria-label="Bestenliste wählen">
    <?php foreach (['flaggen' => 'Flaggen', 'leicht' => 'Umrisse · Leicht', 'mittel' => 'Umrisse · Mittel', 'schwer' => 'Umrisse · Schwer'] as $k => $t): ?>
      <a href="?bl=<?= $k ?>#bestenliste" class="<?= $bl === $k ? 'aktiv' : '' ?>"<?= $bl === $k ? ' aria-current="true"' : '' ?>><?= $t ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="bl-spalten">
  <?php foreach (['kurz', 'komplett'] as $m): $top = array_slice(rangliste($blSpielart, $blStufe, $m), 0, 5); ?>
    <div class="karte bl">
      <h3><?= modus_name($m) ?></h3>
      <?php if (!$top): ?>
        <p class="leer">Noch keine Ergebnisse.</p>
      <?php else: ?>
        <ol>
        <?php foreach ($top as $z): ?>
          <li>
            <span class="rang"><?= ['', '🥇', '🥈', '🥉'][(int)$z['rang']] ?? (int)$z['rang'] . '.' ?></span>
            <span class="bl-name"><?= h($z['name']) ?></span>
            <span class="bl-wert"><?= number_format((float)$z['prozent'], 1, '.', '') ?> % · <?= h(dauer((int)$z['dauer_sek'])) ?></span>
          </li>
        <?php endforeach; ?>
        </ol>
      <?php endif; ?>
      <a href="ergebnisse.php?spielart=<?= $blSpielart ?>&amp;stufe=<?= h($blStufe) ?>&amp;modus=<?= $m ?>">Ganze Rangliste</a>
    </div>
  <?php endforeach; ?>
  </div>
</section>
<?php fuss();
