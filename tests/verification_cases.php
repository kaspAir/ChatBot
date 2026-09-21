<?php
declare(strict_types=1);
// Kleiner fachlich geprüfter Kontrollsatz. Kein vollständiger HERMES-Abnahmekatalog.
$statement = 'Ein Phasenbericht wird am Ende der Phasen Konzept, Realisierung, Einführung und Umsetzung erstellt.';
// Überschrift und Absatz bilden zusammen den zusammenhängenden Originalbeleg.
$phases = 'Phasenbericht Am Ende der Phasen Konzept, Realisierung, Einführung und Umsetzung werden die Ergebnisse der Phase und die Planung des weiteren Projektverlaufs für den Auftraggeber so aufbereitet, dass er den Entscheid zum weiteren Projektvorgehen (in der Regel zur Phasenfreigabe) treffen kann.';
$initialization = '[ERGÄNZUNG KASPAR/BKI: In der Phase Initialisierung wird kein Phasenbericht erstellt.]';
$release = 'Der Releasebericht bildet die Grundlage für das Reporting und ist für den Auftraggeber die Entscheidungsgrundlage für die eventuell vorgesehene Freigabe des nächsten Release.';
return [
    ['name' => 'Allgemeiner Reporting-Satz trägt keine Phasenliste', 'expected' => false,
        'claim' => ['statement' => $statement, 'chapter' => '7.4.1.6', 'evidence_quote' => '7.4.1.6 Reporting Die Forderung der Projekt-Governance nach einer transparenten Kommunikation bedingt ein Reporting.']],
    ['name' => 'Vollständige Phasenliste ist belegt', 'expected' => true,
        'claim' => ['statement' => $statement, 'chapter' => '7.4.1.6', 'evidence_quote' => $phases]],
    ['name' => 'Verkehrte Negation wird abgelehnt', 'expected' => false,
        'claim' => ['statement' => 'In der Phase Initialisierung wird ein Phasenbericht erstellt.', 'chapter' => '4.4.1.30', 'evidence_quote' => $initialization]],
    ['name' => 'Ergänzung zur Initialisierung ist belegt', 'expected' => true,
        'claim' => ['statement' => 'In der Phase Initialisierung wird kein Phasenbericht erstellt.', 'chapter' => '4.4.1.30', 'evidence_quote' => $initialization]],
    ['name' => 'Beteiligungstabelle begründet kein gemeinsames Entscheidungsrecht', 'expected' => false,
        'claim' => ['statement' => 'Der Auftraggeber entscheidet über Phasenfreigaben gemeinsam mit anderen Rollen.', 'chapter' => '6.4.1.1', 'evidence_quote' => 'Beteiligt an der Ergebniserstellung Auftraggeber, Projektleiter Meilenstein Phasenfreigabe Auftraggeber, Projektleiter, Projektausschuss, Anwendervertreter Liste Projektentscheide Steuerung']],
    ['name' => 'Explizite Entscheidungskompetenz ist belegt', 'expected' => true,
        'claim' => ['statement' => 'Der Auftraggeber entscheidet über die Freigabe der Phase Abschluss.', 'chapter' => '5.4.1.4', 'evidence_quote' => 'Der Auftraggeber entscheidet über das Ende der Lösungsentstehung sowie über die Freigabe der Phase Abschluss.']],
    ['name' => 'Bedingte Freigabe wird nicht zur allgemeinen Pflicht', 'expected' => false,
        'claim' => ['statement' => 'Der Auftraggeber muss nach jedem Release die Freigabe des nächsten Release erteilen.', 'chapter' => '4.4.1.45', 'evidence_quote' => $release]],
    ['name' => 'Bedingung der Releasefreigabe bleibt erhalten', 'expected' => true,
        'claim' => ['statement' => 'Wenn eine Freigabe des nächsten Release vorgesehen ist, dient der Releasebericht dem Auftraggeber als Entscheidungsgrundlage.', 'chapter' => '4.4.1.45', 'evidence_quote' => $release]],
];
