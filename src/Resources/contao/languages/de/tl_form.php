<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/languages/de/tl_form.php

declare(strict_types=1);

// ===== Legenden =====
$GLOBALS['TL_LANG']['tl_form']['antispam_legend'] = 'Anti-SPAM Schutz';
$GLOBALS['TL_LANG']['tl_form']['content_analysis_legend'] = 'Content-Analyse';

// ===== Anti-SPAM Felder =====

$GLOBALS['TL_LANG']['tl_form']['c2n_enable_antispam'] = [
    'Anti-SPAM Schutz aktivieren',
    'Aktiviert einen erweiterten SPAM-Schutz mit Honeypot-Feldern, Zeit-basierter Prüfung und optionalem ALTCHA Captcha.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_min_submit_time'] = [
    'Minimale Absende-Zeit (Sekunden)',
    'Formulare, die schneller als diese Zeit abgeschickt werden, gelten als SPAM. Empfohlen: 5-10 Sekunden für kurze Formulare, 15-40 Sekunden für längere Formulare.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_max_submit_time'] = [
    'Maximale Absende-Zeit (Sekunden)',
    'Formulare, die länger als diese Zeit brauchen, gelten als SPAM (geduldige Bots). Empfohlen: 300 Sekunden (5 Min). Wert 0 = keine Begrenzung.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_spam_prefix'] = [
    'SPAM-Markierung',
    'Text der bei SPAM in die Variable ##form_spam_marker## eingefügt wird (für E-Mail-Betreff). Tipp: Für Leerzeichen am Ende verwenden Sie &amp;nbsp;'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_block_spam'] = [
    'SPAM-Nachrichten nicht senden',
    'Wenn aktiviert, werden als SPAM erkannte Formulare NICHT per E-Mail versendet.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_enable_altcha'] = [
    'ALTCHA Captcha aktivieren',
    'Aktiviert ein modernes, barrierefreies Captcha-System. WICHTIG: Fügen Sie dem Formular ein "ALTCHA" Formularfeld hinzu! Die Konfiguration (Schwierigkeit, Algorithmus, etc.) erfolgt in der config.yml.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_enable_ip_blacklist'] = [
    'IP-Blacklist Check aktivieren',
    'Prüft die IP-Adresse und E-Mail des Absenders gegen StopForumSpam.com. Bekannte SPAM-IPs und E-Mail-Adressen werden automatisch blockiert. Die Prüfung erfolgt VOR allen anderen Checks und nutzt ein 24h-Caching. Whitelist-IPs können in der config.yml definiert werden.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_debug'] = [
    'Debug-Modus aktivieren',
    'Aktiviert ausführliche Logs im System-Log für Entwickler. WICHTIG: In Produktion deaktivieren!'
];

// ===== Content-Analyse Felder =====

// Hauptschalter
$GLOBALS['TL_LANG']['tl_form']['c2n_enable_content_analysis'] = [
    'Content-Analyse aktivieren',
    '<strong>⚠️ Für Experten:</strong> Analysiert Formulardaten auf SPAM-Muster. Nutzt intelligente Pattern-Erkennung ohne externe APIs. Alle Tests sind standardmäßig DEAKTIVIERT - aktivieren Sie nur die Tests, die Sie benötigen. Empfohlen: Erst im Debug-Modus testen, dann live schalten.'
];

// Allgemein
$GLOBALS['TL_LANG']['tl_form']['c2n_content_spam_threshold'] = [
    'SPAM-Schwellwert (Punkte)',
    'Ab dieser Punktzahl gilt eine Nachricht als SPAM. Standard: 50 Punkte. Die einzelnen Tests addieren ihre Scores auf. Höherer Wert = strenger (weniger SPAM durchgelassen), niedrigerer Wert = lockerer (mehr echte Anfragen durchgelassen).'
];

// ===== Test 1: URLs im Text =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_urls'] = [
    'URLs im Text prüfen',
    'Erkennt http://, https://, www. und Domain-Namen im Text. Fast alle SPAM-Nachrichten enthalten URLs. Sehr effektiv! Standard: +50 Punkte.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_urls'] = [
    'Score für URLs',
    'Punkte die hinzugefügt werden wenn URLs gefunden wurden. Standard: 50 (sehr hoch, da URLs ein starker SPAM-Indikator sind).'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_urls'] = [
    'Prüfen in Feldern',
    'Wählen Sie die Formularfelder aus, in denen nach URLs gesucht werden soll. WICHTIG: Wählen Sie NICHT das "Webseite"-Feld aus (falls vorhanden), da dort URLs erlaubt sind. Typisch: Nachricht, Text, Kommentar.'
];

// ===== Test 2: Nur Sonderzeichen =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_special_chars'] = [
    'Nur Sonderzeichen prüfen',
    'Erkennt Nachrichten die nur aus Sonderzeichen bestehen (z.B. "!!!###$$$"). Typisch für Bot-generierte Nachrichten. Standard: +40 Punkte.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_special_chars'] = [
    'Score für Sonderzeichen',
    'Punkte die hinzugefügt werden bei reinen Sonderzeichen-Nachrichten. Standard: 40.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_special_chars'] = [
    'Prüfen in Feldern',
    'Wählen Sie die Formularfelder aus, die auf Sonderzeichen geprüft werden sollen. Typisch: Name, Nachricht, Betreff.'
];

// ===== Test 3: Tempmail-Adressen =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_tempmail'] = [
    'Tempmail-Adressen prüfen',
    'Erkennt Wegwerf-E-Mail-Adressen (z.B. 10minutemail.com, guerrillamail.com). Spammer nutzen oft solche Adressen. Standard: +30 Punkte.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_tempmail'] = [
    'Score für Tempmail',
    'Punkte die hinzugefügt werden bei Tempmail-Adressen. Standard: 30.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_tempmail_domains'] = [
    'Tempmail-Domains',
    'Liste der Wegwerf-E-Mail Domains (eine pro Zeile). Sie können hier eigene Domains hinzufügen oder entfernen. Die E-Mail-Adresse wird automatisch gegen diese Liste geprüft.'
];

// ===== Test 4: Nachricht zu kurz =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_short_message'] = [
    'Zu kurze Nachrichten prüfen',
    'Erkennt sehr kurze Nachrichten wie "test", "hi", "asd". Echte Anfragen sind meist länger. Standard: +25 Punkte.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_short_message'] = [
    'Score für kurze Nachricht',
    'Punkte die hinzugefügt werden bei zu kurzen Nachrichten. Standard: 25.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_min_message_length'] = [
    'Minimale Nachrichtenlänge (Zeichen)',
    'Nachrichten kürzer als dieser Wert gelten als verdächtig. Standard: 10 Zeichen. Passen Sie diesen Wert an Ihr Formular an: Kurzes Kontaktformular = niedriger (5-10), Anfrage-Formular = höher (15-30).'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_short_message'] = [
    'Prüfen in Feldern',
    'Wählen Sie die Nachrichtenfelder aus, die auf Länge geprüft werden sollen. WICHTIG: Wählen Sie hier NUR große Textfelder (Textarea) aus, NICHT kurze Felder wie Name oder Betreff. Typisch: Nachricht, Text, Ihre Anfrage, Kommentar.'
];

// ===== Test 5: Repetitive Zeichen =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_repetitive'] = [
    'Repetitive Zeichen prüfen',
    'Erkennt wiederholte Zeichen wie "aaaaaaa", "111111", "!!!!!!". Typisch für Bot-Nachrichten. Standard: +20 Punkte.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_repetitive'] = [
    'Score für Wiederholungen',
    'Punkte die hinzugefügt werden bei repetitiven Mustern (6+ gleiche Zeichen hintereinander). Standard: 20.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_repetitive'] = [
    'Prüfen in Feldern',
    'Wählen Sie die Formularfelder aus, die auf Wiederholungen geprüft werden sollen. Typisch: Name, Nachricht, Betreff.'
];

// ===== Test 6: Großbuchstaben =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_uppercase'] = [
    'Großbuchstaben prüfen',
    'Erkennt übermäßig viele Großbuchstaben (z.B. "HELLO THIS IS IMPORTANT!!!"). Typisch für SPAM. Standard: +15 Punkte.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_uppercase'] = [
    'Score für Großbuchstaben',
    'Punkte die hinzugefügt werden bei zu vielen Großbuchstaben. Standard: 15.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_max_uppercase_ratio'] = [
    'Maximaler Großbuchstaben-Anteil (%)',
    'Ab diesem Prozentsatz gilt die Nachricht als verdächtig. Standard: 60% (mehr als die Hälfte in Großbuchstaben). ACHTUNG: Wählen Sie KEINE Felder aus, die Codes enthalten (wie Länderkürzel "DE", "ES")! Typisch zu prüfen: Nachricht, Text, Kommentar.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_uppercase'] = [
    'Prüfen in Feldern',
    'Wählen Sie die Formularfelder aus, die auf Großbuchstaben geprüft werden sollen. WICHTIG: Wählen Sie KEINE Felder mit Codes/Kürzeln aus (wie Land: "DE", PLZ, etc.)! Typisch: Nachricht, Name, Betreff.'
];

// ===== Test 7: SPAM-Keywords =====

$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_keywords'] = [
    'SPAM-Keywords prüfen',
    '<strong>⚠️ Für Experten:</strong> Sucht nach typischen SPAM-Wörtern. ACHTUNG: Kann zu vielen False-Positives führen! Nur aktivieren wenn Sie die Keyword-Liste sorgfältig an Ihre Branche angepasst haben. Beispiel: Tech-Firma sollte "seo, backlink" entfernen, Apotheke sollte "viagra" behalten. Standard: DEAKTIVIERT. Score: +10 pro Keyword (max. 30).'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_keywords'] = [
    'Score pro Keyword',
    'Punkte die PRO gefundenem Keyword hinzugefügt werden (max. 30 Punkte insgesamt). Standard: 10 Punkte pro Keyword.'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_spam_keywords'] = [
    'SPAM-Keywords',
    'Komma-getrennte Liste von SPAM-Wörtern. WICHTIG: Passen Sie diese Liste unbedingt an Ihre Branche an! Standard-Keywords wie "viagra, casino, crypto" passen nicht für jede Branche. Seien Sie vorsichtig mit allgemeinen Wörtern!'
];

$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_keywords'] = [
    'Prüfen in Feldern',
    'Wählen Sie die Formularfelder aus, die nach Keywords durchsucht werden sollen. Typisch: Nachricht, Betreff, Kommentar. NICHT: Name (könnte zufällig ein Keyword sein).'
];