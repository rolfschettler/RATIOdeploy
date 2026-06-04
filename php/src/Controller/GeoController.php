<?php

namespace Src\Controller;

use Exception;

class GeoController
{

    public function showroute()
    {
        header('Content-Type: text/html');

        // Dual-Call: Dieser Endpunkt kann auf zwei Wegen aufgerufen werden:
        //   1. Über den Delphi-Adapter PHP_Call() → Parameter kommen als JSON-Body (POST)
        //      Beispiel: PHP_Call('showroute', Params, Token)
        //   2. Direkt im Browser → Parameter kommen als GET-Parameter
        //      Beispiel: http://localhost/php/showroute?start=Ulm&ziel=Stuttgart
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);

        $startadresse = $data['start'] ?? $_GET['start'] ?? null;
        $zieladresse  = $data['ziel']  ?? $_GET['ziel']  ?? null;

        $startCoords = null;
        $zielCoords  = null;
        $error       = null;

        if (($startadresse || $zieladresse) && (!$startadresse || !$zieladresse)) {
            $error = "Bitte beide Adressen eingeben.";
        } elseif ($startadresse && $zieladresse) {
            $startCoords = $this->geocode($startadresse);
            $zielCoords  = $this->geocode($zieladresse);

            if (!$startCoords || !$zielCoords) {
                $error = "Eine Adresse konnte nicht gefunden werden.";
            }
        }

        include __DIR__ . '/../Views/routeplaner.php';
    }


    public function calculateDistance()
    {
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);

        if (!$data) {
            throw new Exception("Ungültiges JSON");
        }

        $start = $data["start"] ?? null;
        $ziel  = $data["ziel"]  ?? null;
        $stops = $data["zwischenstopps"] ?? [];

        if (!$start || !$ziel) {
            throw new Exception("Start und Ziel müssen angegeben werden");
        }

        $points = [];
        $points[] = $this->geocode($start);
        foreach ($stops as $stop) {
            $points[] = $this->geocode($stop);
        }
        $points[] = $this->geocode($ziel);

        foreach ($points as $p) {
            if (!$p) {
                throw new Exception("Eine Adresse konnte nicht gefunden werden");
            }
        }

        $coords = [];
        foreach ($points as $p) {
            $coords[] = $p["lon"] . "," . $p["lat"];
        }

        $coordString = implode(";", $coords);
        $url         = "https://router.project-osrm.org/route/v1/driving/$coordString?overview=false";
        $routeData   = json_decode(file_get_contents($url), true);

        if (!isset($routeData["routes"][0])) {
            throw new Exception("Route konnte nicht berechnet werden");
        }

        $route = $routeData["routes"][0];
        return [
            "start"          => $start,
            "ziel"           => $ziel,
            "zwischenstopps" => $stops,
            "distance_km"    => round($route["distance"] / 1000, 2),
            "duration_min"   => round($route["duration"] / 60, 1)
        ];
    }


    public function travelroute()
    {
        header('Content-Type: text/html');

        $body = file_get_contents("php://input");
        $data = json_decode($body, true);
        if (!$data) throw new Exception("Ungültiges JSON");

        [$allPoints, $startzeit, $haltezeit] = $this->parseRouteInput($data);

        $inputJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        include __DIR__ . '/../Views/travelroute.php';
    }

    public function calculateroute(): array
    {
        $body = file_get_contents("php://input");
        $data = json_decode($body, true);
        if (!$data) throw new Exception("Ungültiges JSON");

        [$allPoints, $startzeit, $haltezeit] = $this->parseRouteInput($data);

        // OSRM Trip API serverseitig aufrufen
        $coordStr = implode(';', array_map(fn($p) => $p['lon'] . ',' . $p['lat'], $allPoints));
        $osrmUrl  = "https://router.project-osrm.org/trip/v1/driving/{$coordStr}?roundtrip=false&source=first&destination=last";
        $osrmData = json_decode(file_get_contents($osrmUrl), true);

        if (!isset($osrmData['trips'][0])) {
            throw new Exception("OSRM konnte keine Route berechnen");
        }

        $trip      = $osrmData['trips'][0];
        $waypoints = $osrmData['waypoints'];

        // Optimierte Reihenfolge rekonstruieren
        $ordered = array_fill(0, count($waypoints), null);
        foreach ($waypoints as $i => $wp) {
            $ordered[$wp['waypoint_index']] = $allPoints[$i];
        }

        // Zeitberechnung
        [$startstunde, $startminute] = array_map('intval', explode(':', $startzeit));
        $currentMinutes = $startstunde * 60 + $startminute;

        $stops = [];
        foreach ($ordered as $i => $point) {
            $isFirst = $i === 0;
            $isLast  = $i === count($ordered) - 1;
            $leg     = $i > 0 ? $trip['legs'][$i - 1] : null;

            $stop = [
                'reihenfolge' => $i + 1,
                'ort'         => $point['label'],
                'typ'         => $isFirst ? 'start' : ($isLast ? 'ziel' : 'zwischenstopp'),
                'lat'         => (float)$point['lat'],
                'lon'         => (float)$point['lon'],
            ];

            if ($leg) {
                $legKm  = round($leg['distance'] / 1000, 1);
                $legMin = (int)round($leg['duration'] / 60);
                $currentMinutes += $legMin;

                $stop['strecke_km']   = $legKm;
                $stop['fahrzeit_min'] = $legMin;
                $stop['ankunft']      = sprintf('%02d:%02d', intdiv($currentMinutes, 60) % 24, $currentMinutes % 60);

                if (!$isLast && $haltezeit > 0) {
                    $currentMinutes   += $haltezeit;
                    $stop['abfahrt']   = sprintf('%02d:%02d', intdiv($currentMinutes, 60) % 24, $currentMinutes % 60);
                }
            } else {
                // Startpunkt
                $stop['abfahrt'] = $startzeit;
            }

            $stops[] = $stop;
        }

        return [
            'gesamtstrecke_km' => round($trip['distance'] / 1000, 1),
            'gesamtzeit_min'   => (int)round($trip['duration'] / 60),
            'startzeit'        => $startzeit,
            'haltezeit_min'    => $haltezeit,
            'stops'            => $stops,
        ];
    }


    //---------------------------------------Hilfsmethoden-------------------------------------------------------------------------------

    private function parseRouteInput(array $data): array
    {
        // Pflichtfelder
        $startadresse = trim($data['start'] ?? '');
        $zieladresse  = trim($data['ziel']  ?? '');
        if (!$startadresse) throw new Exception("Parameter 'start' fehlt oder leer");
        if (!$zieladresse)  throw new Exception("Parameter 'ziel' fehlt oder leer");

        // Optionale Felder mit Defaults
        $zwischenstopps = is_array($data['zwischenstopps'] ?? null) ? $data['zwischenstopps'] : [];
        $zwischenstopps = array_filter(array_map('trim', $zwischenstopps));

        if (count($zwischenstopps) > 8) {
            throw new Exception("Maximal 8 Zwischenstopps erlaubt (OSRM-Limit: 10 Koordinaten gesamt)");
        }

        $haltezeit = max(0, (int)($data['haltezeit'] ?? 0));

        $startzeit = trim($data['startzeit'] ?? '');
        if (!preg_match('/^\d{1,2}:\d{2}$/', $startzeit)) {
            $startzeit = '00:00';
        }

        // Alle Adressen geocoden
        $allPoints = [];

        $coords = $this->geocode($startadresse);
        if (!$coords) throw new Exception("Startadresse nicht gefunden: {$startadresse}");
        $allPoints[] = ['label' => $startadresse, 'lat' => $coords['lat'], 'lon' => $coords['lon']];

        foreach ($zwischenstopps as $stop) {
            $coords = $this->geocode($stop);
            if (!$coords) throw new Exception("Adresse nicht gefunden: {$stop}");
            $allPoints[] = ['label' => $stop, 'lat' => $coords['lat'], 'lon' => $coords['lon']];
        }

        $coords = $this->geocode($zieladresse);
        if (!$coords) throw new Exception("Zieladresse nicht gefunden: {$zieladresse}");
        $allPoints[] = ['label' => $zieladresse, 'lat' => $coords['lat'], 'lon' => $coords['lon']];

        return [$allPoints, $startzeit, $haltezeit];
    }

    private function geocode(string $adresse): ?array
    {
        $url     = "https://nominatim.openstreetmap.org/search?q=" . urlencode($adresse) . "&format=json&limit=1";
        $context = stream_context_create(["http" => ["header" => "User-Agent: MeinRouterDemo/1.0\r\n"]]);
        $result  = json_decode(file_get_contents($url, false, $context), true);

        if (!empty($result)) {
            return ["lat" => $result[0]["lat"], "lon" => $result[0]["lon"]];
        }

        return null;
    }
}
