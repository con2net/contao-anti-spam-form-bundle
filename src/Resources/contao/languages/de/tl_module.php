<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/languages/de/tl_module.php

declare(strict_types=1);

// ===== Legenden =====
$GLOBALS['TL_LANG']['tl_module']['antispam_legend'] = 'Anti-SPAM Schutz';
$GLOBALS['TL_LANG']['tl_module']['content_analysis_legend'] = 'Content-Analyse';

// ===== Anti-SPAM Felder (Registrierung) =====

$GLOBALS['TL_LANG']['tl_module']['c2n_enable_antispam'] = [
    'Anti-SPAM Schutz aktivieren',
    'Aktiviert den erweiterten SPAM-Schutz der Anti-SPAM Erweiterung für dieses Registrierungsformular (JavaScript-Token, Honeypot, Zeit-basierte Prüfung und optional ALTCHA).'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_min_submit_time'] = [
    'Minimale Absende-Zeit (Sekunden)',
    'Registrierungen, die schneller als diese Zeit abgeschickt werden, gelten als SPAM. Empfohlen: 10-20 Sekunden.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_max_submit_time'] = [
    'Maximale Absende-Zeit (Sekunden)',
    'Registrierungen, die länger als diese Zeit brauchen, gelten als SPAM (geduldige Bots). Empfohlen: 600 Sekunden (10 Min). Wert 0 = keine Begrenzung.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_spam_reaction'] = [
    'Reaktion bei SPAM',
    'Legt fest, was bei einer als SPAM erkannten Registrierung passiert. In allen Fällen wird KEIN Mitglied angelegt.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_spam_reaction_options'] = [
    'discard' => 'Still verwerfen (Weiterleitung wie bei Erfolg)',
    'error' => 'Fehlermeldung am Formular anzeigen',
    'report' => 'Bericht per E-Mail senden, dann still verwerfen'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_report_email'] = [
    'Empfänger des Berichts',
    'Eine oder mehrere E-Mail-Adressen (durch Komma getrennt). Absender ist die E-Mail-Adresse des Systemadministrators. Höchstens 10 Berichte pro Stunde.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_enable_altcha'] = [
    'ALTCHA aus Anti-SPAM Erweiterung verwenden',
    'Zeigt das ALTCHA Captcha der Anti-SPAM Erweiterung an und blendet dafür die Contao-Sicherheitsfrage aus. Unter Contao 4.13 bitte die Hinweise in der Hilfe beachten.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_enable_ip_blacklist'] = [
    'IP-Blacklist Check aktivieren',
    'Prüft die IP-Adresse und E-Mail des Absenders gegen StopForumSpam.com. Dabei werden beide an diesen externen Dienst übermittelt (Datenschutzerklärung beachten). Die Prüfung läuft erst nach allen lokalen Prüfungen und nutzt ein 24h-Caching. Ist der Dienst nicht erreichbar, wird die Registrierung nicht blockiert. Whitelist-IPs können in der config.yml definiert werden.'
];

$GLOBALS['TL_LANG']['tl_module']['c2n_debug'] = [
    'Debug-Modus',
    'Schreibt ausführliche Informationen zur SPAM-Prüfung dieses Registrierungsformulars ins Contao-Log.'
];
