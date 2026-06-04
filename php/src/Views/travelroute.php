<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RATIO Server - Reiseroute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        #map { height: 600px; margin-top: 20px; }
        #routeinfo { display: none; }
        /* Fahrplan-Stil */
        .stop-list { list-style: none; padding: 0; margin: 0; }
        .stop-item { display: flex; align-items: stretch; }
        .stop-spine {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 32px;
            flex-shrink: 0;
        }
        .stop-dot {
            width: 14px; height: 14px;
            border-radius: 50%;
            background: #0d6efd;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #0d6efd;
            flex-shrink: 0;
            margin-top: 4px;
        }
        .stop-dot.first  { background: #198754; box-shadow: 0 0 0 2px #198754; }
        .stop-dot.last   { background: #dc3545; box-shadow: 0 0 0 2px #dc3545; }
        .stop-line {
            width: 2px;
            flex: 1;
            background: #adb5bd;
            min-height: 28px;
        }
        .stop-line.hidden { background: transparent; }
        .stop-body { padding: 0 0 20px 10px; }
        .stop-name { font-weight: 600; font-size: 0.95rem; }
        .stop-leg  { font-size: 0.8rem; color: #6c757d; margin-top: 2px; }
    </style>
</head>
<body style="background-color:#fff">
    <div class="container">
        <h2 class="mt-4 mb-2">RATIO Server - Reiseroute</h2>
        <details class="mb-4">
            <summary class="text-muted small" style="cursor:pointer;">Eingabeparameter (JSON)</summary>
            <pre class="bg-white border rounded p-3 mt-2 small"><?= htmlspecialchars($inputJson) ?></pre>
        </details>

        <div id="routeinfo" class="mt-3 p-3 bg-white rounded shadow-sm">
            <div class="row mb-2">
                <div class="col-md-6">
                    <small class="text-muted">Gesamtstrecke</small>
                    <div id="routedistance">wird berechnet …</div>
                </div>
                <div class="col-md-6">
                    <small class="text-muted">Fahrzeit</small>
                    <div id="routeduration"></div>
                </div>
            </div>
            <hr>
            <small class="text-muted">Optimierte Reihenfolge</small>
            <ul id="waypoint-list" class="stop-list mt-3"></ul>
        </div>

        <div id="map" style="box-shadow: 10px 20px 30px grey;"></div>

    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([48.4, 9.9], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        // Alle Punkte vom Server (Start → Zwischenstopps → Ziel)
        var points    = <?= json_encode($allPoints) ?>;
        var startzeit = <?= json_encode($startzeit) ?>;
        var haltezeit = <?= (int)$haltezeit ?>; // Minuten

        // OSRM Trip-URL bauen: source=first, destination=last → TSP mit fixem Start und Ziel
        var coordStr = points.map(p => p.lon + ',' + p.lat).join(';');
        var url = `https://router.project-osrm.org/trip/v1/driving/${coordStr}?roundtrip=false&source=first&destination=last&overview=full&geometries=geojson`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                var trip = data.trips[0];

                // Route auf Karte zeichnen
                var routeLayer = L.geoJSON(trip.geometry).addTo(map);
                map.fitBounds(routeLayer.getBounds());

                // Optimierte Reihenfolge ermitteln
                // waypoints[i].waypoint_index = Position in der optimierten Route
                var ordered = new Array(data.waypoints.length);
                data.waypoints.forEach((wp, i) => {
                    ordered[wp.waypoint_index] = points[i];
                });

                // Marker setzen
                ordered.forEach((p, i) => {
                    var isLast  = i === ordered.length - 1;
                    var color   = isLast ? '#dc3545' : '#0d6efd';
                    var icon    = L.divIcon({
                        className: '',
                        html: `<div style="background:${color};color:#fff;width:28px;height:28px;border-radius:50%;text-align:center;line-height:28px;font-size:12px;font-family:Arial,sans-serif;box-shadow:0 2px 4px rgba(0,0,0,.4)">${i + 1}</div>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    });
                    L.marker([p.lat, p.lon], { icon }).addTo(map).bindPopup(p.label);
                });

                // Zeitberechnung
                function parseMinutes(hhmm) {
                    if (!hhmm) return null;
                    var parts = hhmm.split(':');
                    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
                }
                function formatTime(minutes) {
                    var h = Math.floor(minutes / 60) % 24;
                    var m = minutes % 60;
                    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                }

                var currentMinutes = parseMinutes(startzeit);

                // Waypoint-Liste im Fahrplan-Stil
                var list = document.getElementById('waypoint-list');
                ordered.forEach((p, i) => {
                    var isFirst = i === 0;
                    var isLast  = i === ordered.length - 1;
                    var leg     = i > 0 ? trip.legs[i - 1] : null;

                    var dotClass  = isFirst ? 'first' : (isLast ? 'last' : '');
                    var lineClass = isLast  ? 'hidden' : '';

                    // Ankunftszeit berechnen
                    var legHtml = '';
                    if (leg && currentMinutes !== null) {
                        var legKm  = (leg.distance / 1000).toFixed(1);
                        var legMin = Math.round(leg.duration / 60);
                        currentMinutes += legMin;
                        var ankunft = formatTime(currentMinutes);
                        var abfahrtHtml = '';
                        if (!isLast && haltezeit > 0) {
                            currentMinutes += haltezeit;
                            abfahrtHtml = `<div class="stop-leg fw-semibold text-dark">Abfahrt: ${formatTime(currentMinutes)}</div>`;
                        }
                        legHtml = `<div class="stop-leg">↑ ${legKm} km &nbsp;·&nbsp; ${legMin} min Fahrt</div>
                                   <div class="stop-leg fw-semibold text-dark">Ankunft: ${ankunft}</div>
                                   ${abfahrtHtml}`;
                    } else if (leg) {
                        var legKm  = (leg.distance / 1000).toFixed(1);
                        var legMin = Math.round(leg.duration / 60);
                        legHtml = `<div class="stop-leg">↑ ${legKm} km &nbsp;·&nbsp; ${legMin} min</div>`;
                    }

                    // Startzeit anzeigen
                    var nameExtra = '';
                    if (isFirst && startzeit) {
                        nameExtra = `<span class="stop-leg fw-semibold text-dark">Abfahrt: ${startzeit}</span>`;
                    }

                    var li = document.createElement('li');
                    li.className = 'stop-item';
                    li.innerHTML = `
                        <div class="stop-spine">
                            <div class="stop-dot ${dotClass}"></div>
                            <div class="stop-line ${lineClass}"></div>
                        </div>
                        <div class="stop-body">
                            ${legHtml}
                            <div class="stop-name">${p.label}</div>
                            ${nameExtra}
                        </div>`;
                    list.appendChild(li);
                });

                // Strecke & Zeit
                var km  = (trip.distance / 1000).toFixed(1);
                var min = Math.round(trip.duration / 60);
                document.getElementById('routedistance').textContent = km + ' km';
                document.getElementById('routeduration').textContent = 'ca. ' + min + ' min';
                document.getElementById('routeinfo').style.display   = 'block';
            });
    </script>
</body>
</html>
