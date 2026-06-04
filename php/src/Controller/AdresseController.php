<?php

namespace Src\Controller;

use Exception;

class AdresseController
{
    /**
     * GET /adressen2
     * Liefert die HTML-Shell. Kein Token nötig — Daten werden per JS nachgeladen.
     */
    public function index(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        include __DIR__ . '/../Views/adressen2.php';
    }

    /**
     * GET /adressen2/load
     * Lädt alle Adressen über die Delphi-API und gibt sie als JSON zurück.
     * Erfordert Bearer-Token (auth=true in routes.php).
     */
    public function load(): array
    {
        $delphi = new DelphiApiController();

        $result = $delphi->select(json_encode([
            'sql'    => 'Select kennziffer,gruppe,name1,name2,strasse,land,plz,ort,matchcode,email,lvorgang
                         from adressen where code= :code',
            'params' => ['code' => 'demo']
        ]));

        return $result['data'] ?? [];
    }

    /**
     * GET /adressen2/nextkennziffer
     * Ermittelt die nächste freie Kennziffer aus der DB-View ADRESSEN_NEXTKENNZIFFER.
     * Erfordert Bearer-Token (auth=true in routes.php).
     */
    public function nextkennziffer(): array
    {
        $delphi = new DelphiApiController();
        $result = $delphi->select(json_encode([
            'sql'    => 'select * from ADRESSEN_NEXTKENNZIFFER',
            'params' => new \stdClass()
        ]));
        $data = $result['data'] ?? [];
        if (empty($data)) {
            throw new Exception('Nächste Kennziffer konnte nicht ermittelt werden');
        }
        return $data[0];
    }

    /**
     * POST /adressen2/insert
     * Fügt einen neuen Adress-Datensatz ein.
     * Erfordert Bearer-Token (auth=true in routes.php).
     * Body: {"kennziffer":10724,"gruppe":"K","name1":"...","name2":"...",...}
     */
    public function insert(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (empty($data['kennziffer'])) {
            throw new Exception('Parameter "kennziffer" fehlt');
        }
        if (empty($data['gruppe'])) {
            throw new Exception('Parameter "gruppe" fehlt (Pflichtfeld)');
        }

        $delphi = new DelphiApiController();
        return $delphi->insert('adressen', json_encode($data));
    }

    /**
     * GET /adressen2/kategorien
     * Liefert alle Einträge aus ADRKATS (gruppe, bezeichnung) für das Dropdown.
     * Erfordert Bearer-Token (auth=true in routes.php).
     */
    public function kategorien(): array
    {
        $delphi = new DelphiApiController();
        $result = $delphi->select(json_encode([
            'sql'    => 'Select gruppe, bezeichnung from adrkats order by gruppe',
            'params' => new \stdClass()
        ]));
        return $result['data'] ?? [];
    }

    /**
     * GET /adressen2/get?kennziffer=12345
     * Lädt einen einzelnen Adress-Datensatz zum Bearbeiten.
     * Erfordert Bearer-Token (auth=true in routes.php).
     */
    public function get(): array
    {
        $kennziffer = $_GET['kennziffer'] ?? throw new Exception('Parameter "kennziffer" fehlt');

        $delphi = new DelphiApiController();
        $result = $delphi->select(json_encode([
            'sql'    => 'Select kennziffer,gruppe,anrede,titel,name1,name2,strasse,plz,ort,telefon1,email
                         from adressen where kennziffer= :kennziffer',
            'params' => ['kennziffer' => (int)$kennziffer]
        ]));

        $data = $result['data'] ?? [];
        if (empty($data)) {
            throw new Exception('Datensatz nicht gefunden');
        }
        return $data[0];
    }

    /**
     * POST /adressen2/update
     * Aktualisiert einen Adress-Datensatz.
     * Erfordert Bearer-Token (auth=true in routes.php).
     * Body: {"kennziffer":12345,"name1":"...","name2":"...",...}
     */
    public function update(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (empty($data['kennziffer'])) {
            throw new Exception('Parameter "kennziffer" fehlt');
        }

        $kennziffer = $data['kennziffer'];
        unset($data['kennziffer']);

        $delphi = new DelphiApiController();
        return $delphi->update(
            'adressen',
            'kennziffer',
            json_encode(array_merge(['kennziffer' => $kennziffer], $data))
        );
    }

    /**
     * POST /adressen2/delete
     * Löscht einen Adress-Datensatz anhand der Kennziffer.
     * Erfordert Bearer-Token (auth=true in routes.php).
     * Body: {"kennziffer": 12345}
     */
    public function delete(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (empty($data['kennziffer'])) {
            throw new Exception('Parameter "kennziffer" fehlt');
        }

        $delphi = new DelphiApiController();
        return $delphi->delete('adressen', 'kennziffer', json_encode(['kennziffer' => $data['kennziffer']]));
    }
}
