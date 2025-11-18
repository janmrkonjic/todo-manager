<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Preveri, če je uporabnik prijavljen
if (!isset($_SESSION['uporabnik_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Niste prijavljeni']);
    exit;
}

// Naloži .env datoteko
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignoriraj komentarje
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsiraj KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Odstrani narekovaje, če obstajajo
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    return true;
}

// Naloži .env datoteko
$env_path = __DIR__ . '/../.env';
loadEnv($env_path);

// OpenWeather API konfiguracija
$api_key = getenv('OPENWEATHER_API_KEY') ?: $_ENV['OPENWEATHER_API_KEY'] ?? null;

if (!$api_key || $api_key === 'your_api_key_here') {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'API ključ ni nastavljen. Prosimo nastavite OPENWEATHER_API_KEY v .env datoteki.'
    ]);
    exit;
}

$base_url = 'https://api.openweathermap.org/data/2.5/weather';

// Pridobi parametre
$city = $_GET['city'] ?? null;
$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

// Preveri, če imamo mesto ali koordinate
if (!$city && (!$lat || !$lon)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Manjka parameter: city ali lat/lon']);
    exit;
}

// Ustvari edinstveni ključ za cache
$cache_key = $city ? "weather_city_{$city}" : "weather_coords_{$lat}_{$lon}";
$cache_key = md5($cache_key); // Hash za varno ime datoteke

// Nastavi mapo za cache (ustvari, če ne obstaja)
$cache_dir = __DIR__ . '/../cache/weather/';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

$cache_file = $cache_dir . $cache_key . '.json';

// Preveri, če imamo podatke v cache-u (veljajo 30 minut)
if (file_exists($cache_file)) {
    $cache_time = filemtime($cache_file);
    $cache_age = time() - $cache_time;
    
    // Če je cache mladši od 30 minut (1800 sekund)
    if ($cache_age < 1800) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            $cached_data['from_cache'] = true;
            $cached_data['cache_age'] = $cache_age;
            echo json_encode($cached_data);
            exit;
        }
    }
}

// Če ni v cache-u, kliči OpenWeather API
$url = $base_url . '?appid=' . $api_key . '&units=metric&lang=sl';

if ($city) {
    $url .= '&q=' . urlencode($city);
} else {
    $url .= '&lat=' . $lat . '&lon=' . $lon;
}

// Uporabi cURL za klic API-ja
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Napaka pri klicu API-ja: ' . $error]);
    exit;
}

curl_close($ch);

// Preveri HTTP status kodo
if ($http_code !== 200) {
    $error_data = json_decode($response, true);
    $error_message = $error_data['message'] ?? 'Neznana napaka';
    // Logiraj surovi odgovor za diagnostiko
    file_put_contents(__DIR__ . '/../weather_error.log', date('Y-m-d H:i:s') . "\nURL: $url\nResponse: $response\n\n", FILE_APPEND);
    http_response_code($http_code);
    echo json_encode(['success' => false, 'error' => 'OpenWeather API napaka: ' . $error_message]);
    exit;
}

// Parsiraj odgovor
$weather_data = json_decode($response, true);

if (!$weather_data) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Napaka pri parsiranju odgovora']);
    exit;
}

// Pripravi poenostavljen odgovor
$simplified_data = [
    'success' => true,
    'city' => $weather_data['name'] ?? 'Neznano',
    'country' => $weather_data['sys']['country'] ?? '',
    'temperature' => round($weather_data['main']['temp'] ?? 0, 1),
    'feels_like' => round($weather_data['main']['feels_like'] ?? 0, 1),
    'temp_min' => round($weather_data['main']['temp_min'] ?? 0, 1),
    'temp_max' => round($weather_data['main']['temp_max'] ?? 0, 1),
    'pressure' => $weather_data['main']['pressure'] ?? 0,
    'humidity' => $weather_data['main']['humidity'] ?? 0,
    'description' => $weather_data['weather'][0]['description'] ?? '',
    'icon' => $weather_data['weather'][0]['icon'] ?? '01d',
    'wind_speed' => $weather_data['wind']['speed'] ?? 0,
    'wind_deg' => $weather_data['wind']['deg'] ?? 0,
    'clouds' => $weather_data['clouds']['all'] ?? 0,
    'sunrise' => $weather_data['sys']['sunrise'] ?? null,
    'sunset' => $weather_data['sys']['sunset'] ?? null,
    'from_cache' => false
];

// Shrani v cache datoteko
file_put_contents($cache_file, json_encode($simplified_data), LOCK_EX);

// Vrni podatke
echo json_encode($simplified_data);
