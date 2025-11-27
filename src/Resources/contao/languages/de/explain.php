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
