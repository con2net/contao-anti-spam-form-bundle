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
- **Kompatibel** - Funktioniert mit Notification Center, Standard-Mails, etc.

---

## Installation

### Via Composer (empfohlen)
```bash
composer require con2net/contao-anti-spam-form-bundle
```

### Via Contao Manager

1. Contao Manager öffnen
2. Nach "con2net/contao-anti-spam-form-bundle" suchen
3. Bundle installieren
4. Datenbank aktualisieren

---

## Konfiguration

### 1. ALTCHA HMAC-Key generieren

Der HMAC-Key ist essentiell für die ALTCHA Challenge-Erstellung und **muss** geheim bleiben!

**Key generieren (Linux/Mac):**
```bash
openssl rand -base64 32
```

**Key generieren (Windows PowerShell):**
```powershell
$bytes = New-Object byte[] 32
[System.Security.Cryptography.RNGCryptoServiceProvider]::Create().GetBytes($bytes)
[Convert]::ToBase64String($bytes)
```

**Ausgabe z.B.:** `K8vJ9mNpQ2xRzT4yH6wL3eFgD1sA5bC7oU0iM9nV2kX=`

### 2. Key in .env.local Datei anlegen
 Ergänze in der`.env.local` Datei im Root-Verzeichnis deiner Contao-Installation:
```bash
###> con2net/contao-anti-spam-form-bundle ###
ALTCHA_HMAC_KEY="DEIN-GENERIERTER-KEY-HIER"
###< con2net/contao-anti-spam-form-bundle ###
```
Hinweis: Solltest du noch keine .env.local Datei haben, lege diese bitte an. Lege in diesem Fall auch eine (leere) .env Datei direkt daneben ins Root-Verzeichnis Deiner Contao-Installation. 

### 3. ALTCHA Konfiguration (Optional)

### Standard-Werte (ohne config.yml)

Das Bundle funktioniert **ohne zusätzliche Konfiguration** mit folgenden Default-Werten:

```yaml
# Diese Werte werden automatisch verwendet, wenn keine config.yml vorhanden ist:
ALTCHA:
  max_number: 100000     # Medium Difficulty (gut für die meisten Websites)
  salt_length: 16        # 128 Bit Entropie (empfohlener Sicherheitsstandard)
  algorithm: 'SHA-256'   # Schnell und sicher
```

## Eigene Werte konfigurieren (optional)

Falls du die ALTCHA-Schwierigkeit anpassen möchtest, kannst du optional diese Konfiguration in deine `config/config.yml` einfügen:

```yaml
# config/config.yml
contao_anti_spam_form:
  altcha:
    # Challenge difficulty (höher = schwerer für Bots, langsamer für User)
    # Easy: 10000, Normal: 50000, Medium: 100000, Hard: 250000, Very Hard: 500000
    max_number: 100000
    
    # Salt length (8-32)
    # 16 = 128 Bit Entropie (empfohlen für CAPTCHA)
    salt_length: 16
    
    # Hash algorithm: SHA-256 (fast), SHA-384 (medium), SHA-512 (secure)
    algorithm: 'SHA-256'
```

## Schwierigkeitsgrade in etwa

| max_number | Schwierigkeit | Durchschnittliche Lösungszeit | Empfohlen für |
|------------|---------------|-------------------------------|---------------|
| 10.000     | Very Easy     | < 1 Sekunde                   | Testing |
| 50.000     | Easy          | 1-2 Sekunden                  | Hoher Traffic / Mobile |
| 100.000    | Medium        | 2-4 Sekunden                  | Standard-Websites ⭐ |
| 250.000    | Hard          | 5-10 Sekunden                 | Hochsichere Formulare |
| 500.000    | Very Hard     | 10-20 Sekunden                | Maximale Sicherheit |

## IP-Blacklist Konfiguration

Die IP-Blacklist (StopForumSpam.com) funktioniert ebenfalls mit Defaults. Optional kannst du konfigurieren:

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

## HMAC Key (erforderlich für Production)

**Der HMAC Key MUSS manuell gesetzt werden:**

```bash
# .env.local
ALTCHA_HMAC_KEY=dein-geheimer-hmac-key-hier
```

Den Key generierst du mit:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

**Wichtig:** Der HMAC Key ist das einzige, was du zwingend konfigurieren musst. Alles andere hat Defaults!

### 4. Datenbank aktualisieren
```bash
# Via Console
php vendor/bin/contao-console contao:migrate

# ODER via Contao Manager
https://deine-domain.de/contao-manager.phar.php
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
- **Empfehlung:** Beginne mit 50, passe nach Bedarf an (höher = strenger)

### Honeypot-Felder hinzufügen

**Backend → Formulare → [Dein Formular] → Neues Formularfeld**

Wähle einen der 3 Honeypot-Typen:

1. **Honeypot (Text)** - Getarntes Textfeld
2. **Honeypot (Textarea)** - Getarntes Nachrichtenfeld
3. **Honeypot (Checkbox)** - Getarnte Checkbox

**Feldname:** Beliebig (z.B. `local_office_address`, `business_role`)

Empfehlenswert sind Feldnamen, die eine gewisse Verlockung auf Bots ausüben. Nicht zu "üblich" da ggf. AutoComplete-Mechanismen bei echten Clients dazu führen, dass das Feld dann trotzdem ausgefüllt wird.

**Empfohlene Labels (unauffällig):**
- "Position" / "Business Role"
- "Zusätzliche Informationen"
- "Newsletter abonnieren"

### ALTCHA-Feld hinzufügen

**Backend → Formulare → [Dein Formular] → Neues Formularfeld**

1. Feldtyp: **ALTCHA Anti-SPAM Widget**
2. Feldname: `captcha` (empfohlen)
3. Fertig! (Konfiguration erfolgt in `config.yml`)

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

---

## Logs & Debugging

### System-Log

**Backend → System → System-Log**

Hier findest du alle Anti-SPAM Ereignisse:

- **Rot:** SPAM erkannt, Formular blockiert
- **Normal:** Prüfung erfolgreich, kein SPAM / Informationen

**Debug-Modus aktivieren** für detaillierte Logs (eher für Troubleshooting/Analyse):
- Zeit-Berechnungen
- Feldmappings
- Content-Analyse Scores
- API-Requests

---

## Troubleshooting

### ALTCHA wird nicht angezeigt

**Prüfe:**
1. HMAC-Key in `.env.local` gesetzt?
2. ALTCHA-Formularfeld hinzugefügt?
3. Cache geleert? (`rm -rf var/cache/*`)
4. Browser-Console: JavaScript-Fehler?

### Content-Analyse funktioniert nicht

**Prüfe:**
1. Content-Analyse aktiviert?
2. Mindestens ein Test aktiviert?
3. Felder ausgewählt?
4. Debug-Modus: Logs prüfen

### Legitime Anfragen werden als SPAM erkannt

**Lösungen:**
1. **Schwellwert erhöhen** (z.B. von 50 auf 70)
2. **Tests deaktivieren** die zu streng sind
3. **Felder anpassen** (z.B. "Website"-Feld vom URL-Check ausschließen)
4. **Debug-Modus** aktivieren und Scores analysieren

### SPAM kommt trotzdem durch

**Lösungen:**
1. **Schwellwert senken** (z.B. von 50 auf 30)
2. **Mehr Tests aktivieren**
3. **IP-Blacklist aktivieren**
4. **ALTCHA aktivieren**
5. **Honeypot-Felder hinzufügen**

---

## Technische Details

### Systemanforderungen

- PHP 8.2 oder höher
- Contao 4.13 oder höher / Contao 5.3 LTS
- Symfony 5.4 / 6.0 / 7.0
- ALTCHA Library 1.2+

### Hook-Prioritäten
```
compileFormFields (Priority 100):
  -> Timestamp in Session speichern

prepareFormData (Priority 100):
  -> Multi-Layer SPAM-Checks
  -> Bei SPAM: Flag setzen
```

### Architektur
```
Services:
  - AltchaService (Challenge-Erstellung + Validierung)
  - IpBlacklistService (StopForumSpam.com API)
  - ContentAnalysisService (Pattern-Matching)
  - LoggingHelper (Unified Logging)

Listener:
  - FormLoadListener (Timestamp)
  - AntiSpamFormListener (SPAM-Checks)
  - PageListener (CSS/JS)

Widgets:
  - AltchaFormField (Captcha)
  - HoneypotField (Text)
  - HoneypotTextareaField (Textarea)
  - HoneypotCheckboxField (Checkbox)
```

---

## Lizenz

MIT License - siehe [LICENSE](LICENSE) Datei

---

## Credits

- **ALTCHA Library:** https://github.com/altcha-org/altcha-lib-php
- **StopForumSpam:** https://www.stopforumspam.com/
- **Contao CMS:** https://contao.org/

---

## Support

**Projekt:** https://github.com/con2net/contao-anti-spam-form-bundle  
**Website:** https://www.connect2net.de

---

**Hinweis:** Dieses Bundle wird ohne Gewährleistung bereitgestellt. Teste es gründlich vor dem produktiven Einsatz und passe die Einstellungen an deine Bedürfnisse an.

Entwickelt mit ❤️ in Norddeutschland von **connect2Net webServices** / Stefan Meise