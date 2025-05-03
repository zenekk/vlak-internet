<?php
$heatDataJS = "";
$errorJS = "";
$csv_path = __DIR__ . "/2025-05-03-ic510_vlak_gps_speed_data.csv";

if (!file_exists($csv_path)) {
    $errorJS = "document.getElementById('error').innerText = 'CSV file not found.';\n";
    $errorJS .= "document.getElementById('error').style.display = 'block';\n";
} else {
    $file = fopen($csv_path, "r");
    if ($file !== false) {
        $header = fgetcsv($file); // skip header
        $groups = [];
        while (($row = fgetcsv($file)) !== false) {
            $lat = round(floatval($row[3]), 3);
            $lon = round(floatval($row[4]), 3);
            $speed = floatval($row[10]); // Download Speed

            if ($lat && $lon && $speed > 0) {
                $key = "$lat,$lon";
                if (!isset($groups[$key])) {
                    $groups[$key] = ['lat' => $lat, 'lon' => $lon, 'speeds' => []];
                }
                $groups[$key]['speeds'][] = $speed;
            }
        }
        fclose($file);

        foreach ($groups as $group) {
            $avgSpeed = array_sum($group['speeds']) / count($group['speeds']);
            $heatDataJS .= "[{$group['lat']}, {$group['lon']}, $avgSpeed],\n";
        }
    } else {
        $errorJS = "document.getElementById('error').innerText = 'Unable to open the CSV file.';\n";
        $errorJS .= "document.getElementById('error').style.display = 'block';\n";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Internet Speed Heatmap</title>
    <meta charset="utf-8" />
    <style>
        #map { height: 100vh; width: 100%; margin: 0; padding: 0; }
        #error {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            padding: 10px;
            border-radius: 8px;
            z-index: 1000;
            font-family: sans-serif;
            display: none;
        }
        body { margin: 0; }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
</head>
<body>
    <div id="map"></div>
    <div id="error"></div>

    <script>
        var map = L.map('map').setView([49.9, 18.35], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var heatData = [
            <?= $heatDataJS ?>
        ];

        if (heatData.length > 0) {
            L.heatLayer(heatData, {
                radius: 20,
                blur: 15,
                maxZoom: 17
            }).addTo(map);
        }

        <?= $errorJS ?>

        var trainMarker = null;

        function updateTrainLocation() {
            fetch('http://cdwifi.cz/portal/api/vehicle/realtime')
                .then(response => response.json())
                .then(data => {
                    var lat = data.gpsLat;
                    var lon = data.gpsLng;
                    var speed = data.speed;

                    if (lat && lon) {
                        if (trainMarker) {
                            trainMarker.setLatLng([lat, lon]);
                            trainMarker.setPopupContent(`🚄 Train Speed: ${speed} km/h`);
                        } else {
                            trainMarker = L.marker([lat, lon])
                                .addTo(map)
                                .bindPopup(`🚄 Train Speed: ${speed} km/h`)
                                .openPopup();
                        }
                    }
                })
                .catch(error => {
                    console.error("Failed to fetch train location:", error);
                });
        }

        updateTrainLocation(); // Initial call
        //setInterval(updateTrainLocation, 15000); // Refresh every 15 seconds
    </script>
</body>
</html>

