<?php
// File: src/Resources/contao/languages/de/explain.php

declare(strict_types=1);

/**
 * Erklärungen für den Helpwizard (?)
 */

$GLOBALS['TL_LANG']['XPL']['c2n_antispam'] = [
    [
        'Anti-SPAM Schutz - So funktioniert es',
        '<strong>Der Anti-SPAM Schutz arbeitet mit mehreren Mechanismen:</strong><br><br>
        
        <strong>1. Honeypot-Feld (Pflicht!):</strong><br>
        Fügen Sie dem Formular ein Honeypot-Feld hinzu (Text, Textarea oder Checkbox).
        Dieses Feld ist für normale Besucher unsichtbar, aber Bots füllen es aus.<br><br>
        
        <strong>2. Zeit-basierte Prüfung:</strong><br>
        - <strong>Minimale Zeit:</strong> Zu schnelles Absenden (< X Sekunden) = Bot<br>
        - <strong>Maximale Zeit:</strong> Zu langsames Absenden (> X Sekunden) = Bot mit Verzögerung<br><br>
        
        <strong>3. JavaScript-Token:</strong><br>
        Ein verstecktes Token wird per JavaScript erzeugt. Bots ohne JS-Ausführung fallen durch.<br><br>
        
        <strong>4. SPAM-Behandlung:</strong><br>
        - <strong>Marker setzen:</strong> E-Mail wird mit "*** SPAM ***" versendet (Standard)<br>
        - <strong>Blockieren:</strong> E-Mail wird NICHT versendet, Fehlermeldung für User<br><br>
        
        <strong>Variable für E-Mail-Betreff:</strong><br>
        Nutzen Sie im Notification Center: <code>##form_spam_marker##Kontaktformular: Neue Anfrage</code><br>
        Bei SPAM wird daraus: <strong>*** SPAM *** Kontaktformular: Neue Anfrage</strong><br><br>
        
        <strong>Debug-Modus:</strong><br>
        Aktivieren Sie den Debug-Modus nur während der Entwicklung! 
        Er schreibt ausführliche Logs ins System-Log (Zeit-Checks, Honeypot-Status, etc.).'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_honeypot'] = [
    [
        'Honeypot-Feld - Best Practices',
        '<strong>Was ist ein Honeypot?</strong><br>
        Ein verstecktes Formularfeld das für Menschen unsichtbar ist, aber von Bots ausgefüllt wird.<br><br>
        
        <strong>Empfohlene Feldnamen:</strong><br>
        - <code>website</code> - Sehr unauffällig<br>
        - <code>company</code> - Lockt Business-Bots<br>
        - <code>newsletter_subscribe</code> - Checkbox-Variante<br>
        - <code>local_office_address</code> - Hat sich bewährt!<br>
        - <code>additional_info</code> - Für Textarea<br><br>
        
        <strong>NICHT verwenden:</strong><br>
        - <code>honeypot</code> - Zu offensichtlich!<br>
        - <code>spam</code> - Bots kennen das<br>
        - <code>trap</code> - Verrät den Zweck<br><br>
        
        <strong>Label-Empfehlungen:</strong><br>
        - Text: "Website", "Firma", "Telefonnummer"<br>
        - Textarea: "Weitere Informationen", "Anmerkungen"<br>
        - Checkbox: "Newsletter abonnieren", "Updates erhalten"<br><br>
        
        <strong>Wie es funktioniert:</strong><br>
        1. Feld wird per CSS versteckt (<code>opacity: 0</code>)<br>
        2. Im HTML-Code aber vorhanden<br>
        3. Bots füllen ALLE Felder aus → SPAM erkannt!<br>
        4. Menschen sehen das Feld nicht → bleibt leer<br><br>
        
        <strong>Hinweis:</strong><br>
        Fügen Sie mindestens EIN Honeypot-Feld zu Ihrem Formular hinzu,
        damit der Anti-SPAM Schutz funktioniert!'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_block_spam'] = [
    [
        'SPAM-Nachrichten nicht senden vs. Abuse-E-Mail',
        '<strong>SPAM-Nachrichten nicht senden (dieser Haken):</strong><br>
        Ist diese Option aktiviert, wird eine als SPAM erkannte Formular-Einsendung
        vollständig blockiert. Es geht KEINE Benachrichtigung raus - weder an die normalen
        Empfänger noch an eine Abuse-Adresse.<br><br>

        <strong>Abuse-E-Mail (nur wirksam wenn dieser Haken NICHT gesetzt ist):</strong><br>
        SPAM wird weiterhin nur markiert (siehe SPAM-Markierung), aber alle
        Benachrichtigungen - auch über das NotificationCenter - gehen dann ausschließlich
        an diese Abuse-Adresse statt an die ursprünglich konfigurierten Empfänger.
        So sehen Ihre regulären Empfänger keine SPAM-Mails mehr, ohne dass die
        Einsendung komplett verloren geht.<br><br>

        <strong>Wichtig:</strong><br>
        Ist das Abuse-E-Mail-Feld leer, ändert sich am bisherigen Verhalten NICHTS -
        SPAM-markierte Mails gehen wie gewohnt an die normalen Empfänger. Keine
        Migration nötig, bestehende Formulare funktionieren unverändert weiter.'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_spam_reaction'] = [
    [
        'Still verwerfen',
        'Die Registrierung wird verworfen, der Besucher wird wie nach einer erfolgreichen
        Registrierung weitergeleitet. Bots erhalten so keinen Hinweis auf die Erkennung.'
    ],
    [
        'Fehlermeldung am Formular anzeigen',
        'Das Formular wird mit einer Fehlermeldung erneut angezeigt, die Eingaben bleiben
        erhalten. Empfohlen, weil ein echter Besucher bei einer Fehlerkennung sofort merkt,
        dass die Registrierung nicht geklappt hat.'
    ],
    [
        'Bericht per E-Mail senden, dann still verwerfen',
        'Wie "Still verwerfen", zusätzlich geht ein Bericht über die verworfene
        Registrierung (ohne Passwort) an die konfigurierte(n) Adresse(n).'
    ],
    [
        'Wichtig',
        'In allen Fällen wird KEIN Mitglied angelegt und es wird keine E-Mail an den
        Besucher verschickt.'
    ]
];

$GLOBALS['TL_LANG']['XPL']['c2n_registration_altcha'] = [
    [
        'ALTCHA statt Contao-Sicherheitsfrage',
        'Ist diese Option aktiv, zeigt das Registrierungsformular das ALTCHA Captcha der
        Anti-SPAM Erweiterung an. Die Contao-Sicherheitsfrage wird dafür automatisch
        ausgeblendet, an der Einstellung "Spam-Schutz deaktivieren" ändert sich dabei
        nichts.'
    ],
    [
        '"Spam-Schutz deaktivieren" (Contao)',
        'Diese Contao-Einstellung im Abschnitt "Modul-Konfiguration" schaltet NUR die
        Contao-Sicherheitsfrage ab, NICHT den Schutz der Anti-SPAM Erweiterung. Dieser
        wird ausschließlich über "Anti-SPAM Schutz aktivieren" gesteuert.'
    ],
    [
        'Contao 4.13: Einbindung über ein Inhaltselement',
        'Ist das Registrierungsmodul unter Contao 4.13 über ein Inhaltselement vom Typ
        "Modul" in einen Artikel eingebunden, kann die Contao-Sicherheitsfrage nicht
        automatisch ausgeblendet werden, es erscheinen dann beide Captchas. Setzen Sie in
        diesem Fall im Modul zusätzlich den Haken "Spam-Schutz deaktivieren". Unter
        Contao 5 ist das nicht nötig.'
    ]
];
