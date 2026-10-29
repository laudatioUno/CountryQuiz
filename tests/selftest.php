<?php
// Self test for the quiz data and the answer checking. Run: php tests/selftest.php
// Exit code 0 = everything fine. Runs in CI on every pull request and before a release.
declare(strict_types=1);

const UPDATE_SEITE = true;           // no redirect to the update page
$_SERVER['REQUEST_METHOD'] = 'GET';
$tmp = sys_get_temp_dir() . '/countryquiz-selftest-' . getmypid();
@mkdir($tmp);
session_save_path($tmp);
require __DIR__ . '/../lib.php';

$fehler = 0;
function pruefen(bool $ok, string $text): void
{
    global $fehler;
    if (!$ok) {
        $fehler++;
        echo "FEHLER: $text\n";
    }
}

$codes = array_keys(LAENDER);
pruefen(count($codes) === 46, 'Es sollten 46 Länder sein, gefunden: ' . count($codes));

// Every country has a flag, an outline and lists of similar countries/flags
foreach ($codes as $c) {
    pruefen(is_file(__DIR__ . '/../' . flagge($c)), "Flagge fehlt: $c");
    pruefen(is_file(__DIR__ . '/../' . umriss($c)), "Umriss fehlt: $c");
    foreach (['AEHNLICH_LAND' => AEHNLICH_LAND, 'AEHNLICH_FLAGGE' => AEHNLICH_FLAGGE] as $name => $liste) {
        pruefen(count($liste[$c] ?? []) >= 3, "$name: zu wenige Einträge für $c");
        foreach ($liste[$c] ?? [] as $x) {
            pruefen(isset(LAENDER[$x]) && $x !== $c, "$name: ungültiger Eintrag $x bei $c");
        }
    }
}

// Answer checking: every official name counts as correct; the name of another
// country never counts at the flag of a different country
foreach (LAENDER as $a => $daten) {
    foreach ([0, 1] as $feld) {
        foreach ($daten[$feld] as $name) {
            pruefen(pruefe($name, $a, $feld) === 'richtig', "Nicht als richtig erkannt: $a «{$name}»");
        }
        $eigen = array_map('norm', array_merge($daten[$feld], TOLERIERT[$a][$feld] ?? []));
        foreach (LAENDER as $b => $andere) {
            if ($a === $b) {
                continue;
            }
            foreach (array_merge($andere[$feld], TOLERIERT[$b][$feld] ?? []) as $name) {
                if (!in_array(norm($name), $eigen, true)) {
                    pruefen(pruefe($name, $a, $feld) === 'falsch', "Anderes Land akzeptiert: Flagge $a, Eingabe «{$name}»");
                }
            }
        }
    }
}

// Examples from the tolerant checking
foreach ([['li', 'Lichtenstein', 'tippfehler'], ['gb', 'Vereintes Königreich', 'tippfehler'], ['by', 'Weissrussland', 'richtig'],
          ['ba', 'Bosnien', 'tippfehler'], ['is', 'Irland', 'falsch'], ['sk', 'Slowenien', 'falsch'], ['gb', 'England', 'falsch']] as [$c, $e, $soll]) {
    pruefen(pruefe($e, $c, 0) === $soll, "«{$e}» bei $c sollte $soll sein, ist " . pruefe($e, $c, 0));
}

// Answer options: 4 different countries incl. the correct one, never the same set twice in a row
foreach (['leicht', 'mittel'] as $stufe) {
    foreach ($codes as $c) {
        $vorher = null;
        for ($i = 0; $i < 6; $i++) {
            $o = antwortmoeglichkeiten($c, $stufe);
            pruefen(count($o) === 4 && count(array_unique($o)) === 4 && in_array($c, $o, true), "Antwortmöglichkeiten ungültig: $stufe $c");
            $set = array_values(array_diff($o, [$c]));
            sort($set);
            pruefen($set !== $vorher, "Zweimal dieselbe Auswahl hintereinander: $stufe $c");
            $vorher = $set;
        }
    }
}

pruefen((bool)preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', APP_VERSION), 'APP_VERSION hat nicht die Form 1.2.3');

array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
echo $fehler === 0 ? "OK – alle Prüfungen bestanden.\n" : "$fehler Fehler.\n";
exit($fehler === 0 ? 0 : 1);
