# Mail-System – Kurzübersicht

## Was das System macht

Der Reiseveranstalter klickt in Angular auf „Einladung senden".
Der Reisegast bekommt eine Mail mit einem Link.
Er klickt den Link, Angular öffnet das Formular.

---

## Die zwei Hauptvorgänge

### 1. Mail versenden

**Wer ruft an:** Angular (mit Bearer-Token)
**Endpoint:** `POST /php/einladung/senden`

**Angular schickt:**
```json
{
    "email":          "gast@example.com",
    "name":           "Familie Müller",
    "subject":        "Bitte Teilnehmer eintragen",
    "body_html":      "<p>Hallo, klicken Sie hier: {LINK}</p>",
    "frontend_url":   "https://app.reisebuero.de/teilnehmer",
    "reference_type": "GEBUCHT",
    "reference_id":   12345
}
```

**Was PHP macht:**
1. Token erzeugen → Hash in ACCESS_TOKENS speichern
2. Link bauen: {frontend_url}?t={token}
3. {LINK} im body_html ersetzen (falls Platzhalter vorhanden, sonst body_html unverändert)
4. Mail versenden oder verarbeiten (je nach driver in config.ini)
5. Eintrag in MAIL_LOG schreiben

**Angular bekommt zurück:**
```json
{ "log_id": 42, "link": "https://app.../teilnehmer?t=a3f7b9..." }
```

Bei `driver=mailto`: Angular baut aus den bereits bekannten Daten (email, subject,
body_html, link) die `mailto:`-URL selbst und öffnet den Standard-Mail-Client.

---

### 2. Token einlösen (Gast klickt Link)

**Wer ruft an:** Angular (KEIN Bearer-Token nötig)
**Endpoint:** `POST /php/einladung/oeffnen`

**Angular schickt:**
```json
{ "token": "a3f7b9c1d4e8f2a6..." }
```

**Was PHP macht:**
1. SHA-256-Hash berechnen
2. Delphi /public/checkmailtoken aufrufen (ohne Auth)
3. Delphi prüft: gefunden? widerrufen? abgelaufen? bereits verwendet?

**Angular bekommt zurück:**
```json
{
    "reference_type":  "GEBUCHT",
    "reference_id":    12345,
    "recipient_email": "gast@example.com"
}
```

Angular weiß jetzt: „Es geht um GEBUCHT Nr. 12345"
und holt die fachlichen Daten selbst über die Delphi-API.

---

## Konfiguration (config.ini)

```ini
[MAIL]
driver=mailto         ; STANDARD:    Mail-Client des Benutzers (kein SMTP nötig)
; driver=file         ; ENTWICKLUNG: Mails als .eml-Datei speichern
; driver=smtp         ; PRODUKTION:  Mails direkt versenden (eigener SMTP-Server)

; Absender (immer nötig)
from_address=info@konzeptdata.de
from_name=Konzept Data

; Nur bei driver=file:
file_output_dir=C:\...\maildump

; Nur bei driver=smtp:
smtp_host=mail.agenturserver.de
smtp_port=587
smtp_secure=tls
smtp_user=info@konzeptdata.de
smtp_pass=PASSWORT
```

---

## Die drei Mail-Driver

| driver   | Was passiert                                          | Wann verwenden                              |
|----------|-------------------------------------------------------|---------------------------------------------|
| `mailto` | Kein Versand, kein File. Token + MAIL_LOG werden      | Normalfall: Kunde nutzt Microsoft/Google.   |
|          | geschrieben. Angular öffnet den Standard-Mail-Client. | Kein SMTP-Setup nötig.                      |
| `file`   | Mail wird als .eml-Datei gespeichert.                 | Entwicklung / lokales Testen.               |
| `smtp`   | Mail wird direkt über SMTP versendet.                 | Eigener SMTP-Server ohne externe Restrikt.  |

**Hintergrund `mailto`:** Kunden mit Microsoft 365 oder Google Workspace blockieren
externen SMTP-Zugriff. Der Standard-Mail-Client des Reiseveranstalters ist bereits
authentifiziert — der Versand läuft über seine eigene, vertrauenswürdige Infrastruktur.

---

## Wo was liegt

```
src/
  Controller/
    EinladungController.php   ← die zwei Endpoints (senden + oeffnen)
    TokenController.php       ← Token erzeugen, widerrufen, aufräumen
    MailController.php        ← Mail versenden + MAIL_LOG schreiben
  Mail/
    MailSenderInterface.php   ← gemeinsame Schnittstelle
    MailToSender.php          ← kein Versand, kein File (Standard-Mail-Client via Angular)
    FileMailSender.php        ← schreibt .eml-Dateien (Entwicklung)
    SmtpMailSender.php        ← echter SMTP-Versand
core/
  Config.php                  ← liest config.ini
```

---

## Datenbank

ACCESS_TOKENS – ein Eintrag pro versendeter Einladung

- TOKEN_HASH      – SHA-256 des Links (nie der Klartext-Token!)
- REFERENCE_TYPE  – z.B. "GEBUCHT"
- REFERENCE_ID    – z.B. 12345
- EXPIRES_AT      – wann der Link ungültig wird
- USED_AT         – wann der Gast geklickt hat (NULL = noch nicht)
- SINGLE_USE      – 1 = einmalig, 0 = mehrfach klickbar
- REVOKED         – 1 = manuell ungültig gemacht (z.B. bei Stornierung)

MAIL_LOG – ein Eintrag pro Versandversuch

- SUCCESS         – 1 = erfolgreich, 0 = fehlgeschlagen
- ERROR_MESSAGE   – Fehlermeldung falls SUCCESS = 0
- DRIVER_USED     – 'mailto', 'file' oder 'smtp'
- SENT_BY_USER    – wer den Knopf gedrückt hat

---

## Delphi-Endpoints

```
/ibapi/insert                   AUTH   Token in DB schreiben
/ibapi/update                   AUTH   Token widerrufen
/ibapi/execute                  AUTH   Abgelaufene Tokens löschen
/ibapi/public/checkmailtoken    KEINE  Token prüfen + einlösen
```

Alle /public/...-Endpoints brauchen keinen Bearer-Token.

---

## Token-Sicherheit kurz erklärt

Der Link enthält den Klartext-Token.
Die DB speichert nur den SHA-256-Hash davon.

Wer die DB stiehlt, kann damit nichts anfangen.
Wer den Link hat, kann genau das tun, wofür er ausgestellt wurde – nicht mehr.

---

## Beim Kunden einrichten (Checkliste)

```
[ ] config.ini: driver=mailto (ist der Standard, kein weiteres Setup nötig)
[ ] from_address + from_name in config.ini eintragen
[ ] Test: "Einladung senden" klicken → Mail-Client öffnet sich vorausgefüllt
[ ] Gast-Link testen: Token einlösen, reference_type + reference_id korrekt?
```
