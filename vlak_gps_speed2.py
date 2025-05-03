# TBD:
# when wifi captive appears the script doesn't create an error, but generates seemingly valid data

import requests
import time
import csv
import os
import socket

filename = '2025-05-03-ic510_vlak_gps_speed_data.csv'
socket.setdefaulttimeout(1)
print("ahoj4")

class TrainLocation:
    def __init__(self, api_url='http://cdwifi.cz/portal/api/vehicle/realtime'):
        self.api_url = api_url
        self.lat = None
        self.lon = None
        self.speed = None
        self.alt = None
        self.prev_lat = None
        self.prev_lon = None
        self.delay = None

    def update(self):
        response = requests.get(self.api_url)
        data = response.json()

        self.lat = data.get('gpsLat')
        self.lon = data.get('gpsLng')
        self.speed = data.get('speed')
        self.alt = data.get('altitude')
        self.prev_lat = data.get('prevGpsLat')
        self.prev_lon = data.get('prevGpsLng')
        self.delay = data.get('delay')

    def to_dict(self):
        return {
            "lat": self.lat,
            "lon": self.lon,
            "speed": self.speed,
            "alt": self.alt,
            "prev_lat": self.prev_lat,
            "prev_lon": self.prev_lon,
            "delay": self.delay
        }

def quick_connection_test(url="http://speedtest.tds.net/speedtest/random1000x1000.jpg", timeout=10):
# https://speedtest.serverius.net/speedtest/random1000x1000.jpg
    result = {
        "success": False,
        "latency_ms": None,
        "download_speed_mbps": 0.0,
        "error": None,
    }

    try:
        # Measure latency
        start_ping = time.time()
        head = requests.head(url, timeout=timeout)
        latency = (time.time() - start_ping) * 1000  # ms
        result["latency_ms"] = round(latency, 2)

        # Measure small download with time cap
        start = time.time()
        response = requests.get(url, stream=True, timeout=timeout)
        total_bytes = 0
        for chunk in response.iter_content(1024 * 50):
            total_bytes += len(chunk)
            if time.time() - start > timeout:
                break  # stop downloading but still calculate

        duration = time.time() - start
        if duration > 0:
            mbits = (total_bytes * 8) / 1_000_000
            result["download_speed_mbps"] = round(mbits / duration, 2)
        result["success"] = True
    except Exception as e:
        result["error"] = str(e)

    return result

# ==== Run script ====
vlak_gps = TrainLocation()
start_time = time.time()
vlak_gps.update()
gps_time = time.time()
print(f"time gps: {gps_time - start_time}")
print(vlak_gps.to_dict())

# Fast connection quality test
conn_result = quick_connection_test(timeout=10)
print(f"Download Speed: {conn_result['download_speed_mbps']} Mbps")
print(f"Ping: {conn_result['latency_ms']} ms")
print(f"Error: {conn_result['error']}")
end_time = time.time()
print(f"time speedtest: {end_time - start_time}")

# Build log record
vlak_data = {
    'Start Time': start_time,
    'GPS Time': gps_time,
    'End Time': end_time,
    'Lattitude': vlak_gps.lat,
    'Longitude': vlak_gps.lon,
    'Altitude': vlak_gps.alt,
    'Train Speed': vlak_gps.speed,
    'Prev. Lat': vlak_gps.prev_lat,
    'Prev. Long': vlak_gps.prev_lon,
    'Delay': vlak_gps.delay,
    'Download Speed': conn_result['download_speed_mbps'] or -1,
    'Upload Speed': 0.0,  # Not measured in this test
    'Ping': conn_result['latency_ms'] or -1
}

# Write to CSV
file_exists = os.path.isfile(filename)
with open(filename, mode='a', newline='') as file:
    writer = csv.DictWriter(file, fieldnames=vlak_data.keys())
    if not file_exists:
        writer.writeheader()
    writer.writerow(vlak_data)

print("==========================\n")

