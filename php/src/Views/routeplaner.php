<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RATIO Server - Routenplaner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        #map { height: 600px; margin-top: 20px; }
        #routeinfo { display: none; }
    </style>
</head>
<body style="background-color:#fff">
    <div class="container">
        <h2 class="mt-4 mb-4">RATIO Server - Routenplaner</h2>
        <form class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Startadresse</label>
                <input class="form-control" name="start" placeholder="z.B. Spitalstraße 4, Ehingen"
                    value="<?= htmlspecialchars($startadresse ?? '') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Zieladresse</label>
                <input class="form-control" name="ziel" placeholder="z.B. Stuttgart Flughafen, Terminal 4"
                    value="<?= htmlspecialchars($zieladresse ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">Route anzeigen</button>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
        <?php endif ?>

        <?php if ($startCoords && $zielCoords): ?>
        <div id="routeinfo" class="row mt-3 p-3 bg-white rounded shadow-sm">
            <div class="col-md-4">
                <small class="text-muted">Start</small>
                <div><?= htmlspecialchars($startadresse) ?></div>
            </div>
            <div class="col-md-4">
                <small class="text-muted">Ziel</small>
                <div><?= htmlspecialchars($zieladresse) ?></div>
            </div>
            <div class="col-md-4">
                <small class="text-muted">Entfernung</small>
                <div id="routedistance">wird berechnet …</div>
            </div>
        </div>
        <?php endif ?>

        <div id="map" style="box-shadow: 10px 20px 30px grey;"></div>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([48.7758, 9.1829], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        <?php if ($startCoords && $zielCoords): ?>
        L.marker([<?= $startCoords['lat'] ?>, <?= $startCoords['lon'] ?>]).addTo(map).bindPopup("Start").openPopup();
        L.marker([<?= $zielCoords['lat']  ?>, <?= $zielCoords['lon']  ?>]).addTo(map).bindPopup("Ziel");

        fetch(`https://router.project-osrm.org/route/v1/driving/<?= $startCoords['lon'] ?>,<?= $startCoords['lat'] ?>;<?= $zielCoords['lon'] ?>,<?= $zielCoords['lat'] ?>?overview=full&geometries=geojson`)
            .then(res => res.json())
            .then(data => {
                var route = L.geoJSON(data.routes[0].geometry).addTo(map);
                map.fitBounds(route.getBounds());

                var km  = (data.routes[0].distance / 1000).toFixed(1);
                var min = Math.round(data.routes[0].duration / 60);
                document.getElementById('routedistance').textContent = km + ' km / ca. ' + min + ' min';
                document.getElementById('routeinfo').style.display = 'flex';
            });
        <?php endif ?>
    </script>
</body>
</html>
