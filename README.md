# Contao Anti-SPAM Form Bundle

Ein umfassendes Anti-SPAM Bundle für Contao Formulare mit Multi-Layer-Schutz.

---

## Wichtiger Hinweis / Disclaimer

Dieses Bundle bietet umfangreiche SPAM-Schutz-Mechanismen, kann aber **keine 100%ige SPAM-Erkennung garantieren**.

**Bitte beachte:**

- **False-Positives sind möglich:** Legitime Anfragen können fälschlicherweise als SPAM erkannt werden
- **SPAM-Bots entwickeln sich weiter:** Die Wirksamkeit einzelner Checks kann mit der Zeit abnehmen
- **Regelmäßige Anpassung nötig:** Schwellwerte und Filter sollten überwacht und ggf. nachjustiert werden
- **Kein Anspruch auf Fehlerfreiheit:** Das Bundle wird ohne Gewährleistung bereitgestellt
- **Keine Haftung:** Für entgangene Anfragen oder Datenverluste wird keine Haftung übernommen

**Empfehlung:** Teste das Bundle ausführlich in einer Staging-Umgebung, bevor du es produktiv einsetzt. Überwache die Logs regelmäßig und passe die Einstellungen an deine Bedürfnisse an.

---

## Features

### Multi-Layer SPAM-Schutz

Das Bundle kombiniert **7 verschiedene Schutzebenen**, die einzeln aktiviert und konfiguriert werden können:

1. **ALTCHA Captcha** - Modernes, barrierefreies Captcha ohne Tracking
2. **IP-Blacklist** - Prüfung gegen StopForumSpam.com Datenbank
3. **E-Mail-Blacklist** - Erkennung bekannter SPAM-E-Mail-Adressen
4. **Content-Analyse** - 7 intelligente Pattern-Checks (URLs, Sonderzeichen, etc.)
5. **Honeypot-Felder** - Unsichtbare Fallen für Bots (3 Varianten)
6. **Zeit-basierte Validierung** - Erkennung zu schneller/langsamer Submits
7. **JavaScript-Token** - Überprüfung ob JavaScript aktiv ist

### Highlights

- **Feldbasierte Content-Analyse** - Wähle für jeden Test die zu prüfenden Felder aus
- **Score-basiertes System** - Konfigurierbare Schwellwerte für flexible SPAM-Erkennung
- **DSGVO-konform** - Keine externen Tracking-Skripte, Daten bleiben auf dem Server
- **Fehler-tolerant** - API-Ausfälle brechen Formular-Submits nicht ab
- **Debug-Modus** - Ausführliche Logs für Entwickler
- **Kompatibel** - Funktioniert mit Notification Center, Standard-Mails, mehrstufigen Formularen (mp_forms) u.v.m.

---

## Installation

### Via Contao Manager

1. Contao Manager öffnen
2. Nach "con2net/contao-anti-spam-form-bundle" suchen
3. Bundle installieren
4. Datenbank aktualisieren

### Alternativ auch via Composer
```bash
composer require con2net/contao-anti-spam-form-bundle
```
Datenbank aktualisieren:
```bash
php vendor/bin/contao-console contao:migrate
```

Das war's. Das Bundle funktioniert direkt nach der Installation **ohne weitere Konfiguration**.

---

## Konfiguration

### Zero-Config (empfohlen für die meisten Installationen)

Nach der Installation und Datenbankaktualisierung ist das Bundle sofort einsatzbereit:

- **HMAC-Key wird automatisch generiert** und in der Datenbank gespeichert (Tabelle `tl_c2n_settings`). Kein manuelles Setzen erforderlich.
- **PBKDF2/SHA-256** wird als Standard-Algorithmus verwendet – sicher und ohne zusätzliche Abhängigkeiten.
- Alle anderen Einstellungen haben sinnvolle Standardwerte.

### Optionaler eigener HMAC-Key (.env.local)

Für Produktionsumgebungen, in denen du den HMAC-Key selbst verwalten möchtest (z.B. für Secrets Management oder manuelle Key-Rotation), kannst du ihn in der `.env.local` setzen:

```bash
###> con2net/contao-anti-spam-form-bundle ###
ALTCHA_HMAC_KEY="DEIN-GENERIERTER-KEY-HIER"
###< con2net/contao-anti-spam-form-bundle ###
```

**Key generieren (Linux/Mac):**
```bash
openssl rand -base64 32
```

**Key generieren (PHP, plattformunabhängig):**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

**Key generieren (Windows PowerShell):**
```powershell
$bytes = New-Object byte[] 32
[System.Security.Cryptography.RNGCryptoServiceProvider]::Create().GetBytes($bytes)
[Convert]::ToBase64String($bytes)
```

Ein manuell gesetzter Key hat immer Vorrang vor dem automatisch generierten.

> **Hinweis für Shared Hosting:** Die `.env.local` gehört ins Root-Verzeichnis deiner Contao-Installation – dort wo auch die `composer.json` liegt, **nicht** in den Webroot (`public/` oder `web/`). Beispiel: Webroot ist `./client_1234/public_html/public/`, dann gehört die `.env.local` nach `./client_1234/public_html/`. Falls noch keine .env vorhanden ist, lege zusätzlich eine leere .env Datei direkt daneben ins gleiche Verzeichnis.

> **Updater von v1.0.x:** Wenn du bisher `ALTCHA_HMAC_KEY` in der `.env.local` hattest und auf den automatisch generierten Key wechseln möchtest, kannst du die Zeile einfach auskommentieren oder entfernen. Das Bundle generiert dann beim nächsten Seitenaufruf automatisch einen neuen Key.

### Optionale ALTCHA-Konfiguration (config.yml)

Das Bundle funktioniert vollständig **ohne** Einträge in der `config.yml`. Falls du die Schwierigkeit oder den Algorithmus anpassen möchtest:

```yaml
# config/config.yml
contao_anti_spam_form:
  altcha:
    # Challenge-Schwierigkeit (höher = schwerer für Bots, langsamer für User)
    # Easy: 10000, Normal: 50000, Medium: 100000, Hard: 250000, Very Hard: 500000
    max_number: 100000

    # Salt-Länge (8-32 Zeichen)
    # Hinweis: Wird nur bei Legacy-Algorithmen (SHA-256/384/512) verwendet.
    # Bei PBKDF2, Argon2id und Scrypt wird dieser Wert ignoriert.
    salt_length: 16

    # Algorithmus (siehe Tabelle unten)
    algorithm: 'pbkdf2'
```

> **Updater von v1.0.x:** Wenn du bisher `algorithm: 'SHA-256'` in deiner `config.yml` hattest, funktioniert das weiterhin ohne Änderungen. Du kannst den Eintrag aber entfernen, um den neuen PBKDF2-Standard zu nutzen – oder auf `pbkdf2-sha384` / `pbkdf2-sha512` upgraden für mehr Sicherheit.

#### Verfügbare Algorithmen

| Algorithmus | Internes Format | Sicherheit | Empfehlung |
|-------------|-----------------|------------|------------|
| `pbkdf2` | PBKDF2/SHA-256 | ★★★★☆ | **Standard für neue Installationen** |
| `pbkdf2-sha384` | PBKDF2/SHA-384 | ★★★★★ | Erhöhte Sicherheit |
| `pbkdf2-sha512` | PBKDF2/SHA-512 | ★★★★★ | Maximale PBKDF2-Sicherheit |
| `SHA-256` | SHA-256 (Legacy) | ★★☆☆☆ | Bestehende Installationen (Backwards-Compat) |
| `SHA-384` | SHA-384 (Legacy) | ★★★☆☆ | Bestehende Installationen |
| `SHA-512` | SHA-512 (Legacy) | ★★★☆☆ | Bestehende Installationen |
| `argon2id` | Argon2id | ★★★★★ | Vorbereitet – kommt in einer der nächsten Versionen |
| `scrypt` | Scrypt | ★★★★★ | Vorbereitet – kommt in einer der nächsten Versionen |

> **Hinweis zu Legacy-Algorithmen (SHA-256/384/512):** Diese nutzen das ältere V1 ALTCHA-Format und sind ausschließlich für Backwards-Kompatibilität mit bestehenden Installationen vorgesehen. Für neue Installationen sind PBKDF2-Varianten empfohlen.

#### Schwierigkeitsgrade (so in etwa ;-))

| max_number | Schwierigkeit | Durchschnittliche Lösungszeit | Empfohlen für |
|------------|---------------|-------------------------------|---------------|
| 10.000     | Very Easy     | < 1 Sekunde                   | Testing |
| 50.000     | Easy          | 1-2 Sekunden                  | Hoher Traffic / Mobile |
| 100.000    | Medium        | 2-4 Sekunden                  | **Standard-Websites** |
| 250.000    | Hard          | 5-10 Sekunden                 | Hochsichere Formulare |
| 500.000    | Very Hard     | 10-20 Sekunden                | Maximale Sicherheit |

> **Hinweis zu PBKDF2-SHA-384/512:** Bei stärkeren Algorithmen kann die Lösungszeit etwas länger sein. Ggf. `max_number` entsprechend anpassen.

### Optionale IP-Blacklist Konfiguration

```yaml
contao_anti_spam_form:
  ip_blacklist:
    cache_lifetime: 86400   # 24h Cache für API-Anfragen
    api_timeout: 3          # 3s Timeout für StopForumSpam API
    whitelist:
      - '127.0.0.1'         # Localhost immer erlauben
      - '192.168.1.0/24'    # Lokales Netzwerk
      # - '10.0.0.0/8'      # Firmen-VPN (Beispiel)
```

---

## Verwendung

### Formular erstellen

1. **Backend → Formulare → Neues Formular**
2. Füge deine Formularfelder hinzu (E-Mail, Name, Nachricht, etc.)
3. Aktiviere den Anti-SPAM Schutz

### Anti-SPAM aktivieren

**Backend → Formulare → [Dein Formular]**

Scrolle zur Sektion **"Anti-SPAM Schutz"** und aktiviere die gewünschten Features.

#### Basis-Einstellungen

- **Anti-SPAM Schutz aktivieren** - Hauptschalter für alle Schutzfunktionen
- **Minimale Absende-Zeit** - Formulare schneller als X Sek. = SPAM (z.B. 10 Sek.)
- **Maximale Absende-Zeit** - Formulare langsamer als X Sek. = SPAM (z.B. 300 Sek. oder 0 für unbegrenzt)
- **SPAM-Markierung** - Text für `##form_spam_marker##` Variable (z.B. `*** SPAM *** `)
- **SPAM-Nachrichten nicht senden** - SPAM-E-Mails komplett blockieren (statt nur markieren)
- **Abuse-E-Mail-Adresse** - Nur wirksam wenn "SPAM-Nachrichten nicht senden" NICHT aktiviert ist: leitet SPAM-markierte Benachrichtigungen an diese Adresse um, statt an die konfigurierten Empfänger (siehe [Empfehlungen / Best Practices](#empfehlungen--best-practices))

#### Erweiterte Features

- **ALTCHA Captcha aktivieren** - Barrierefreies Captcha (benötigt ALTCHA-Formularfeld!)
- **IP-Blacklist Check aktivieren** - Prüfung gegen StopForumSpam.com
- **Debug-Modus aktivieren** - Ausführliche Logs (für Troubleshooting/Analyse)

### Content-Analyse konfigurieren

**Backend → Formulare → [Dein Formular] → Content-Analyse**

Die Content-Analyse bietet **7 intelligente Tests**, die für jedes Formular-Feld einzeln aktiviert werden können:

#### 1. URLs im Text prüfen
Erkennt Links in Nachrichtenfeldern (sehr viel SPAM enthält URLs).

- **Score:** 50 Punkte (Standard)
- **Felder wählen:** Empfiehlt sich für Nachrichtenfelder o.ä.

#### 2. Nur Sonderzeichen prüfen
Erkennt Nachrichten wie `!!!###$$$`.

- **Score:** 40 Punkte
- **Felder wählen:** Name, Nachricht, Betreff

#### 3. Tempmail-Adressen prüfen
Erkennt Wegwerf-E-Mail-Adressen (10minutemail.com, etc.).

- **Score:** 30 Punkte
- **Domains:** Vordefinierte Liste (erweiterbar)

#### 4. Nachricht zu kurz prüfen
Erkennt zu kurze Nachrichten wie "test", "hi".

- **Score:** 25 Punkte
- **Mindestlänge:** 10 Zeichen (Standard)
- **Felder wählen:** NUR große Textfelder (Textarea), Eher NICHT Vorname o.ä.!

#### 5. Repetitive Zeichen prüfen
Erkennt Wiederholungen wie `aaaaaaa`, `!!!!!!`.

- **Score:** 20 Punkte
- **Felder wählen:** Alle Textfelder

#### 6. Großbuchstaben prüfen
Erkennt übermäßig viele Großbuchstaben (`HELLO THIS IS IMPORTANT!!!`).

- **Score:** 15 Punkte
- **Max. Anteil:** 60% (Standard)
- **Felder wählen:** NICHT Länder-/Code-Felder (DE, ES, etc.)!

#### 7. SPAM-Keywords prüfen
Sucht nach typischen SPAM-Wörtern.

- **Score:** 10 Punkte pro Keyword (max. 30)
- **Keywords:** Anpassbar (Standard: viagra, casino, crypto, etc.)
- **ACHTUNG:** Kann zu False-Positives führen! Nur aktivieren wenn sorgfältig konfiguriert.

#### Score-System

- **SPAM-Schwellwert:** 50 Punkte (Standard)
- Jeder Test addiert Punkte
- Ab Schwellwert = SPAM erkannt
- **Empfehlung:** Beginne mit 50, passe nach Bedarf an (höher = weniger streng)

### Honeypot-Felder hinzufügen

**Backend → Formulare → [Dein Formular] → Neues Formularfeld**

Wähle einen der 3 Honeypot-Typen:

1. **Honeypot (Text)** - Getarntes Textfeld
2. **Honeypot (Textarea)** - Getarntes Nachrichtenfeld
3. **Honeypot (Checkbox)** - Getarnte Checkbox

**Feldname:** Beliebig (z.B. `local_office_address`, `business_role`)

Empfehlenswert sind Feldnamen, die eine gewisse Verlockung auf Bots ausüben. Nicht zu "üblich" – AutoComplete-Mechanismen in Browsern könnten das Feld sonst bei echten Nutzern vorausfüllen.

**Empfohlene Labels (unauffällig):**
- "Position" / "Business Role"
- "Zusätzliche Informationen"
- "Newsletter abonnieren"

### ALTCHA-Feld hinzufügen

**Backend → Formulare → [Dein Formular] → Neues Formularfeld**

1. Feldtyp: **ALTCHA Anti-SPAM Widget**
2. Feldname: `captcha` (empfohlen)
3. Fertig! Das Widget konfiguriert sich automatisch.

### E-Mail-Benachrichtigung

In deiner E-Mail-Benachrichtigung (Notification Center oder Contao Standard) steht das Token `##form_spam_marker##` zur Verfügung.

**Beispielhafte Verwendung im Betreff:**
```
##form_spam_marker##Neue Anfrage über Kontaktformular
```

**Ergebnis:**
- Normal: `Neue Anfrage über Kontaktformular`
- SPAM: `*** SPAM *** Neue Anfrage über Kontaktformular` 
 
So kannst du SPAM-Nachrichten im Posteingang sofort erkennen und z.B. automatisch in einen Spam-Ordner verschieben lassen.

Alternativ kannst du SPAM-markierte Benachrichtigungen auch komplett von den normalen Empfängern
fernhalten, indem du eine **Abuse-E-Mail-Adresse** hinterlegst (siehe oben) — dann gehen sie nur
noch dorthin. Details und Anwendungsfälle dazu im nächsten Abschnitt.

---

## Empfehlungen / Best Practices

### Verhalten bei SPAM "Eingaben"

In bestimmten Situationen kann es passieren, dass die Formulareingaben eines echten Nutzers fälschlicherweise 
zu einer SPAM-Reaktion führen (z.B. durch eine zu streng eingestellte Content-Analyse oder
ungewöhnliches Zeitverhalten). Ob und wie sichtbar eine als SPAM erkannte Einsendung für den Absender ist, hängt von der Kombination
aus "SPAM-Nachrichten nicht senden" und "Abuse-E-Mail-Adresse" ab. Standardmäßig
zeigt das Bundle bei einer Blockierung direkt im Formular eine Fehlermeldung (Contao 4.13 und
5.3+ verhalten sich hier identisch) — das hilft echten Nutzern diese Situation zu erkennen,
ist aber für einen Bot theoretisch als Signal erkennbar, dass hier ein Filter aktiv ist.

Je nach Anwendungsfall kann ein anderes Verhalten sinnvoller sein:

| SPAM-Nachrichten nicht senden | Abuse-E-Mail-Adresse | Verhalten                                                                                           | Wann sinnvoll                                                                                                                                        |
|--|----------------------|-----------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| aktiviert | irrelevant           | Einsendung wird komplett blockiert, sichtbare Fehlermeldung für den Absender, nichts wird versendet | Maximale "Ruhe", eine beim Nutzer sichtbare "Blockierungsmeldung" wird akzeptiert                                                                    |
| deaktiviert | leer                 | SPAM wird markiert (`##form_spam_marker##`) und geht wie gewohnt an die konfigurierten Empfänger    | Standard, wenn du vermeintliche SPAM-Mails als solche kennzeichnen möchtest, und selbst überprüfen/filtern/aussortieren willst                       |
| deaktiviert | echte, überwachte E-Mail-Adresse | SPAM-markierte Benachrichtigungen gehen nur an diese Adresse statt an die normalen Empfänger        | Manuelle Prüfung von Grenzfällen, ohne die regulären Empfänger zu belasten                                                                           |
| deaktiviert | bewusst unbeobachtete E-Mail-Adresse ("Silent Drop") | Absender sieht eine normale Erfolgsmeldung, die eigentliche Benachrichtigung verpufft faktisch      | Weder ein sichtbares Block-Signal noch der Verlust echter Anfragen (False Positives) ist gewünscht, aber auch niemand soll mit SPAM behelligt werden |

**Hinweis zum "Silent Drop":** Verwende dafür eine echte, aber bewusst nicht gelesene Mailbox
statt einer nicht existierenden Adresse — Es besteht sonst die Gefahr dass dadurch unnötige Bounce-Mails, die im schlimmsten Fall auch 
Reputationsprobleme verursachen könnne, erzeugt werden. Bitte ggf. prüfen, ob Nachrichten an diese Adresse sicher gedroppt werden können.

---

## Logs & Debugging

### System-Log

**Backend → System → System-Log**

Hier findest du alle Anti-SPAM Ereignisse:

- **Rot:** SPAM erkannt, Formular blockiert
- **Normal:** Prüfung erfolgreich, kein SPAM / Informationen

**Debug-Modus aktivieren** für detaillierte Logs:
- Zeit-Berechnungen
- Feldmappings
- Content-Analyse Scores
- API-Requests

---

## Troubleshooting

### .env.local wird nicht erkannt

Die `.env.local` muss im Contao-Root-Verzeichnis liegen – dort wo die `composer.json` ist. Bei typischen Shared-Hosting-Setups liegt der Webroot in einem Unterordner (`public/` oder `web/`). Die `.env.local` gehört eine Ebene darüber, nicht in den Webroot. Nach dem Anlegen der Datei unbedingt den Cache leeren:
```bash
php vendor/bin/contao-console cache:clear
```

### ALTCHA wird nicht angezeigt

1. ALTCHA-Formularfeld hinzugefügt?
2. Cache geleert? (`php vendor/bin/contao-console cache:clear`)
3. Browser-Console: JavaScript-Fehler?
4. Contao-Manager: Datenbank aktualisiert? (bzw.`contao:migrate`)

### Content-Analyse funktioniert nicht

1. Content-Analyse aktiviert?
2. Mindestens ein Test aktiviert?
3. Felder ausgewählt?
4. Debug-Modus: Logs prüfen

### Legitime Anfragen werden als SPAM erkannt

1. **Schwellwert erhöhen** (z.B. von 50 auf 70)
2. **Tests deaktivieren** die zu streng sind
3. **Felder anpassen** (z.B. "Website"-Feld vom URL-Check ausschließen)
4. **Debug-Modus** aktivieren und Scores analysieren

### SPAM kommt trotzdem durch

1. **Schwellwert senken** (z.B. von 50 auf 30)
2. **Mehr Tests aktivieren**
3. **IP-Blacklist aktivieren**
4. **ALTCHA aktivieren**
5. **Honeypot-Felder hinzufügen**

---

## Technische Details

### Systemanforderungen

- PHP 8.2 oder höher
- Contao 4.13 LTS, Contao 5.3 LTS oder Contao 5.7
- Symfony 5.4 / 6.x / 7.x

### Architektur

```
Services:
  - AltchaService         (Challenge-Erstellung + Validierung, V1/V2)
  - IpBlacklistService    (StopForumSpam.com API)
  - ContentAnalysisService(Pattern-Matching)
  - LoggingHelper         (Unified Logging)

Listener:
  - FormLoadListener        (Session-Timestamp)
  - AntiSpamFormListener    (Multi-Layer SPAM-Checks)
  - CleanupFormDataListener (Entfernt technische Felder aus raw_data)
  - PageListener            (CSS/JS Assets)
  - Notification/AbuseEmailNcV1Listener (NotificationCenter 1.x: Empfänger-Umleitung)
  - Notification/AbuseEmailNcV2Listener (NotificationCenter 2.x: Empfänger-Umleitung)

Widgets:
  - AltchaFormField       (ALTCHA Captcha)
  - HoneypotField         (Text)
  - HoneypotTextareaField (Textarea)
  - HoneypotCheckboxField (Checkbox)

Datenbank:
  - tl_c2n_settings       (Gemeinsame Settings-Tabelle für con2net Bundles,
                            speichert den automatisch generierten HMAC-Key)
```

---

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für eine vollständige Versionshistorie.

---

## Lizenz

MIT License – siehe [LICENSE](LICENSE)

---

## Credits

- **ALTCHA:** https://altcha.org / https://github.com/altcha-org/altcha-lib-php
- **StopForumSpam:** https://www.stopforumspam.com/
- **Contao CMS:** https://contao.org/

---

## Support

**Projekt:** https://github.com/con2net/contao-anti-spam-form-bundle  
**Website:** https://www.connect2net.de

---

Entwickelt mit ❤️ in Norddeutschland von **connect2Net webServices** / Stefan Meise