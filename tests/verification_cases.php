<?php
declare(strict_types=1);
// Kleiner fachlich geprüfter Kontrollsatz. Kein vollständiger HERMES-Abnahmekatalog.
$statement = 'Ein Phasenbericht wird am Ende der Phasen Konzept, Realisierung, Einführung und Umsetzung erstellt.';
$phases = 'Am Ende der Phasen Konzept, Realisierung, Einführung und Umsetzung werden die Ergebnisse der Phase und die Planung des weiteren Projektverlaufs für den Auftraggeber so aufbereitet, dass er den Entscheid zum weiteren Projektvorgehen (in der Regel zur Phasenfreigabe) treffen kann.';
$initialization = '[ERGÄNZUNG KASPAR/BKI: In der Phase Initialisierung wird kein Phasenbericht erstellt.]';
return [
    ['name' => 'Allgemeiner Reporting-Satz trägt keine Phasenliste', 'expected' => false,
        'claim' => ['statement' => $statement, 'chapter' => '7.4.1.6', 'evidence_quote' => '7.4.1.6 Reporting Die Forderung der Projekt-Governance nach einer transparenten Kommunikation bedingt ein Reporting.']],
    ['name' => 'Vollständige Phasenliste ist belegt', 'expected' => true,
        'claim' => ['statement' => $statement, 'chapter' => '7.4.1.6', 'evidence_quote' => $phases]],
    ['name' => 'Verkehrte Negation wird abgelehnt', 'expected' => false,
        'claim' => ['statement' => 'In der Phase Initialisierung wird ein Phasenbericht erstellt.', 'chapter' => '4.4.1.30', 'evidence_quote' => $initialization]],
    ['name' => 'Ergänzung zur Initialisierung ist belegt', 'expected' => true,
        'claim' => ['statement' => 'In der Phase Initialisierung wird kein Phasenbericht erstellt.', 'chapter' => '4.4.1.30', 'evidence_quote' => $initialization]],
];
