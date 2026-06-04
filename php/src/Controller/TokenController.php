<?php

namespace Src\Controller;

use Exception;
use DateTimeImmutable;

/**
 * Verwaltet kryptografisch sichere Zugriffs-Tokens für Mail-Links.
 *
 * Tokens werden als Klartext (Hex-String) im Mail-Link versendet.
 * In der Datenbank wird nur der SHA-256-Hash gespeichert, sodass
 * ein Datenbank-Leak die Tokens nicht direkt nutzbar macht.
 *
 * Verwendung:
 *   $tc = new TokenController();
 *
 *   // Token erzeugen — Klartext-Wert kommt in den Mail-Link
 *   $rawToken = $tc->create('form_access', 'auftrag', 1234, 'kunde@example.com');
 *   $link = "https://app.kundenfirma.de/formular?t=" . $rawToken;
 *
 *   // Token einlösen — z.B. wenn der Endkunde auf den Link klickt
 *   $info = $tc->redeem($_GET['t'], $_SERVER['REMOTE_ADDR']);
 */
class TokenController
{
    /** Anzahl Zufallsbytes für das Token (256 Bit Entropie) */
    private const TOKEN_BYTES = 32;

    /** Hash-Algorithmus für die Speicherung */
    private const HASH_ALGO = 'sha256';

    /** Standard-Gültigkeitsdauer in Stunden (7 Tage) */
    private const DEFAULT_VALIDITY_HOURS = 168;


    //---------------------------------------Token erzeugen-----------------------------------------------------------

    /**
     * Erzeugt ein neues Token, speichert den Hash in der DB und
     * gibt den KLARTEXT zurück.
     *
     * Der Klartext wird nur einmal zurückgeliefert — er muss vom
     * Aufrufer in den Mail-Link eingebaut werden.
     *
     * @param string $purpose         Verwendungszweck, z.B. 'form_access'
     * @param string $referenceType   z.B. 'auftrag', 'anfrage'
     * @param int    $referenceId     ID des fachlichen Bezugs
     * @param string $recipientEmail  Empfänger-Adresse (zur Nachvollziehbarkeit)
     * @param int    $validForHours   Gültigkeit in Stunden (Default: 168 = 7 Tage)
     * @param bool   $singleUse       true = einmal einlösbar (Default), false = mehrfach
     *
     * @return string Klartext-Token (64 Zeichen Hex), gehört in den Mail-Link
     */
    public function create(
        string $purpose,
        string $referenceType,
        int    $referenceId,
        string $recipientEmail,
        int    $validForHours = self::DEFAULT_VALIDITY_HOURS,
        bool   $singleUse = true
    ): string {
        if ($validForHours < 1 || $validForHours > 8760) {
            throw new Exception('validForHours muss zwischen 1 und 8760 (1 Jahr) liegen');
        }
        if ($purpose === '' || strlen($purpose) > 50) {
            throw new Exception('purpose ist leer oder länger als 50 Zeichen');
        }

        // Klartext-Token generieren (kryptografisch sicher)
        $rawToken = bin2hex(random_bytes(self::TOKEN_BYTES));
        $hash     = hash(self::HASH_ALGO, $rawToken);

        // Ablaufzeit in PHP berechnen — versionsunabhängig von Interbase
        $expiresAt = (new DateTimeImmutable())
            ->modify("+{$validForHours} hours")
            ->format('Y-m-d H:i:s');

        // ID und CREATED_AT setzt der Trigger automatisch
        $payload = [
            'token_hash'      => $hash,
            'purpose'         => $purpose,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'recipient_email' => $recipientEmail,
            'expires_at'      => $expiresAt,
            'single_use'      => $singleUse ? 1 : 0,
        ];

        $delphi = new DelphiApiController();
        $delphi->insert('access_tokens', json_encode($payload));

        return $rawToken;
    }


    //---------------------------------------Token validieren---------------------------------------------------------

    /**
     * Validiert ein Token ohne es einzuloesen.
     * Nutzt den Delphi-Endpoint /checkmailtoken (ohne Auth).
     */
    public function validate(string $rawToken): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            throw new Exception('Token-Format ungueltig');
        }

        $hash   = hash('sha256', $rawToken);
        $delphi = new DelphiApiController();
        $result = $delphi->checkMailToken($hash, null);

        if (($result['status'] ?? '') !== 'OK') {
            throw new Exception($result['message'] ?? 'Token-Pruefung fehlgeschlagen');
        }

        return $result['data'];
    }

    //---------------------------------------Token einlösen-----------------------------------------------------------

    /**
     * Validiert ein Token UND loest es bei single_use=true ein.
     * Nutzt den Delphi-Endpoint /checkmailtoken (ohne Auth).
     */
    public function redeem(string $rawToken, ?string $clientIp = null): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            throw new Exception('Token-Format ungueltig');
        }

        $hash   = hash('sha256', $rawToken);
        $delphi = new DelphiApiController();
        $result = $delphi->checkMailToken($hash, $clientIp);

        if (($result['status'] ?? '') !== 'OK') {
            throw new Exception($result['message'] ?? 'Token-Pruefung fehlgeschlagen');
        }

        return $result['data'];
    }

    //---------------------------------------Token widerrufen---------------------------------------------------------

    /**
     * Macht ein Token vorzeitig ungültig (z.B. weil der zugehörige
     * Vorgang storniert wurde). Der Datensatz bleibt in der DB für
     * Nachvollziehbarkeit erhalten, das Token kann aber nicht mehr
     * eingelöst werden.
     */
    public function revoke(int $tokenId): void
    {
        $delphi = new DelphiApiController();
        $delphi->update('access_tokens', 'id', json_encode([
            'id'      => $tokenId,
            'revoked' => 1,
        ]));
    }


    //---------------------------------------Tokens zu fachlichem Bezug suchen----------------------------------------

    /**
     * Findet alle Tokens zu einem fachlichen Bezug.
     *
     * Nützlich für Admin-Oberflächen — Beispiel: "Welche Links wurden
     * für Auftrag X verschickt? Wurden sie eingelöst?"
     *
     * @return array  Liste der gefundenen Tokens (ohne Hash, mit Status-Infos)
     */
    public function findByReference(string $referenceType, int $referenceId): array
    {
        $delphi = new DelphiApiController();
        $result = $delphi->select(json_encode([
            'sql'    => 'select id, purpose, recipient_email, created_at,
                                expires_at, used_at, used_from_ip,
                                single_use, revoked
                         from access_tokens
                         where reference_type = :type and reference_id = :id
                         order by created_at desc',
            'params' => ['type' => $referenceType, 'id' => $referenceId]
        ]));

        $rows = $result['data'] ?? [];

        // Spaltennamen normalisieren (Großbuchstaben → Kleinbuchstaben)
        return array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $rows);
    }


    //---------------------------------------Cleanup-----------------------------------------------------------------

    /**
     * Räumt abgelaufene Tokens aus der Datenbank.
     *
     * Sollte regelmäßig per Cronjob aufgerufen werden, z.B. einmal täglich.
     * Tokens werden erst gelöscht, wenn sie seit mindestens
     * $keepDaysAfterExpiry Tagen abgelaufen sind. So bleibt eine
     * gewisse Nachvollziehbarkeit für kürzlich abgelaufene Vorgänge.
     *
     * @param int $keepDaysAfterExpiry  Wartezeit nach Ablauf in Tagen (Default: 30)
     *
     * @return array  Antwort des Delphi-Moduls (für Logging)
     */
    public function cleanup(int $keepDaysAfterExpiry = 30): array
    {
        if ($keepDaysAfterExpiry < 0) {
            throw new Exception('keepDaysAfterExpiry muss >= 0 sein');
        }

        // Cutoff in PHP berechnen — wegen alter Interbase-Version
        // (DATEADD und ähnliche Funktionen sind nicht zuverlässig verfügbar)
        $cutoff = (new DateTimeImmutable())
            ->modify("-{$keepDaysAfterExpiry} days")
            ->format('Y-m-d H:i:s');

        $delphi = new DelphiApiController();
        return $delphi->execute(json_encode([
            'sql'    => 'delete from access_tokens where expires_at < :cutoff',
            'params' => ['cutoff' => $cutoff]
        ]));
    }
}










/* ============================================================
#   Tabelle: ACCESS_TOKENS
#   Speichert Einmal-/Zeitlimitierte Zugriffstoken für 
#   Webformular-Links in Mails


CREATE TABLE ACCESS_TOKENS (
    ID              INTEGER       NOT NULL,
    TOKEN_HASH      VARCHAR(64)  NOT NULL,
    PURPOSE         VARCHAR(50)  NOT NULL,
    REFERENCE_ID    INTEGER,
    REFERENCE_TYPE  VARCHAR(50),
    RECIPIENT_EMAIL VARCHAR(255),
    CREATED_AT      TIMESTAMP    NOT NULL,
    EXPIRES_AT      TIMESTAMP    NOT NULL,
    USED_AT         TIMESTAMP,
    USED_FROM_IP    VARCHAR(45),
    SINGLE_USE      INTEGER     DEFAULT 1 NOT NULL,
    REVOKED         INTEGER     DEFAULT 0 NOT NULL,

    CONSTRAINT PK_ACCESS_TOKENS PRIMARY KEY (ID),
    CONSTRAINT UQ_ACCESS_TOKENS_HASH UNIQUE (TOKEN_HASH)
);

# Generator für die ID 

CREATE GENERATOR GEN_ACCESS_TOKENS_ID;
SET GENERATOR GEN_ACCESS_TOKENS_ID TO 0;

# Trigger für Auto-Increment (BEFORE INSERT) 

SET TERM ^ ;
CREATE TRIGGER TRG_ACCESS_TOKENS_BI FOR ACCESS_TOKENS
ACTIVE BEFORE INSERT POSITION 0
AS
BEGIN
    IF (NEW.ID IS NULL) THEN
        NEW.ID = GEN_ID(GEN_ACCESS_TOKENS_ID, 1);
    IF (NEW.CREATED_AT IS NULL) THEN
        NEW.CREATED_AT = CURRENT_TIMESTAMP;
END^
SET TERM ; ^

# Indizes für Performance 
CREATE INDEX IDX_ACCESS_TOKENS_PURPOSE ON ACCESS_TOKENS(PURPOSE);
CREATE INDEX IDX_ACCESS_TOKENS_EXPIRES ON ACCESS_TOKENS(EXPIRES_AT);
CREATE INDEX IDX_ACCESS_TOKENS_REF ON ACCESS_TOKENS(REFERENCE_TYPE, REFERENCE_ID);




============================================================ */










/* ============================================================
   Tabelle: MAIL_LOG
   Protokolliert alle versendeten Mails (erfolgreich oder nicht).
   Bei manuell ausgelöstem Versand wird hier nach JEDEM Versand-
   versuch ein Eintrag geschrieben — egal ob erfolgreich oder
   gescheitert.
   

CREATE TABLE MAIL_LOG (
    ID                INTEGER        NOT NULL,
    RECIPIENT_EMAIL   VARCHAR(255)   NOT NULL,
    RECIPIENT_NAME    VARCHAR(255),
  
    SUBJECT           VARCHAR(500)   NOT NULL,
    BODY_HTML         BLOB SUB_TYPE TEXT,
    
    FROM_EMAIL        VARCHAR(255),
    FROM_NAME         VARCHAR(255),
    REPLY_TO          VARCHAR(255),
    
    SUCCESS           INTEGER        DEFAULT 1 NOT NULL,
    ERROR_MESSAGE     VARCHAR(2000),
    DRIVER_USED       VARCHAR(20),
    
    SENT_AT           TIMESTAMP      NOT NULL,
    SENT_BY_USER      VARCHAR(100),
    
    REFERENCE_TYPE    VARCHAR(50),
    REFERENCE_ID      INTEGER,
    
    CONSTRAINT PK_MAIL_LOG PRIMARY KEY (ID)
);

#   Generator fuer die ID 
CREATE GENERATOR GEN_MAIL_LOG_ID;
SET GENERATOR GEN_MAIL_LOG_ID TO 0;

#  Trigger: setzt ID und SENT_AT automatisch 
SET TERM ^ ;
CREATE TRIGGER TRG_MAIL_LOG_BI FOR MAIL_LOG
ACTIVE BEFORE INSERT POSITION 0
AS
BEGIN
    IF (NEW.ID IS NULL) THEN
        NEW.ID = GEN_ID(GEN_MAIL_LOG_ID, 1);
    IF (NEW.SENT_AT IS NULL) THEN
        NEW.SENT_AT = CURRENT_TIMESTAMP;
END^
SET TERM ; ^

#  Indizes fuer Performance 
CREATE INDEX IDX_MAIL_LOG_SENT ON MAIL_LOG(SENT_AT);
CREATE INDEX IDX_MAIL_LOG_RECIPIENT ON MAIL_LOG(RECIPIENT_EMAIL);
CREATE INDEX IDX_MAIL_LOG_REF ON MAIL_LOG(REFERENCE_TYPE, REFERENCE_ID);
CREATE INDEX IDX_MAIL_LOG_SUCCESS ON MAIL_LOG(SUCCESS);





============================================================ */