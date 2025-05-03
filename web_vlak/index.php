<?php
// Read and aggregate CSV data by location
$heatmapData = [];
$groups = [];

$csvFile = "2025-05-03-ic510_vlak_gps_speed_data.csv";

if (!file_exists($csvFile)) {
    echo "<p style='color:red;'>CSV file not found.</p>";
    exit;
}

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",");
    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row = array_combine($header, $row);
        $latKey = round($row['Lattitude'], 3);
        $lonKey = round($row['Longitude'], 3);
        $key = $latKey . ',' . $lonKey;

        $speed = floatval($row['Download Speed']);
        $ping = floatval($row['Ping']); // Assuming you want to consider Ping

        if (!isset($groups[$key])) {
            $groups[$key] = ['lat' => $latKey, 'lon' => $lonKey, 'speeds' => [], 'pings' => []];
        }
        $groups[$key]['speeds'][] = $speed;
        $groups[$key]['pings'][] = $ping;
    }
    fclose($handle);

    // Prepare data for the map with color based on speed and ping
    foreach ($groups as $group) {
        $avgSpeed = array_sum($group['speeds']) / count($group['speeds']);
        $avgPing = array_sum($group['pings']) / count($group['pings']);
        
        // Set color based on speed and ping
        $color = getColorForSpeedAndPing($avgSpeed, $avgPing);
        $heatmapData[] = [$group['lat'], $group['lon'], $color];
    }
}

// Function to get color based on speed and ping
function getColorForSpeedAndPing($speed, $ping) {
    if ($speed > 4 && $ping <300) {
        $color = 'green'; // Fast speed
    } elseif ($speed > 1 && $ping <600) {
        $color = 'yellow'; // Moderate speed
    } elseif ($speed >0.7 && $ping <800) {
        $color = 'orange'; // Slow speed
    } else {
        $color = 'red'; // Very slow speed
    }
    return $color;
}

$jsonData = json_encode($heatmapData);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Train Internet Speed & Ping Map</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <style>
        #map { height: 100vh; }
        #center-btn {
            position: absolute;
            top: 100px;
            left: 10px;
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 1000;
        }
        #center-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<div id="map"></div>
<button id="center-btn">Center on Train</button>

<script>
    var map = L.map('map').setView([49.8, 15.5], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    // Data from PHP, contains coordinates and associated color
    var heatPoints = <?php echo $jsonData; ?>;

    // Plot each point with the correct color
    heatPoints.forEach(function(point) {
        var lat = point[0];
        var lon = point[1];
        var color = point[2];

        var marker = L.circleMarker([lat, lon], {
            radius: 4,
            fillColor: color,
            color: color,
            weight: 1,
            opacity: 0.7,
            fillOpacity: 0.7
        }).addTo(map);
    });

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
    setInterval(updateTrainLocation, 15000); // Refresh every 15 seconds

    // Center map on current train location when button is clicked
    document.getElementById('center-btn').onclick = function() {
        if (trainMarker) {
            map.setView(trainMarker.getLatLng(), 13); // 13 is the zoom level
        }
    };
</script>
</body>
</html>

