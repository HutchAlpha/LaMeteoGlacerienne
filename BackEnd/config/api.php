<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ---------------------------------------------------------
//! Configuration API météo
// ---------------------------------------------------------

function obtenirConfigurationApiMeteo(): array
{
    $url = valeurEnvironnement('METEO_API_URL');
    $latitude = valeurEnvironnement('LATITUDE', valeurEnvironnement('Latitude'));
    $longitude = valeurEnvironnement('LONGITUDE', valeurEnvironnement('Longitude'));
    $cle = valeurEnvironnement('METEO_API_KEY');

    if ($url === '' || $latitude === '' || $longitude === '' || $cle === '') {
        throw new RuntimeException(
            'Configuration API météo incomplète : vérifie METEO_API_URL, LATITUDE, LONGITUDE et METEO_API_KEY dans .env.'
        );
    }

    return [
        'url' => $url,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'cle' => $cle,
    ];
}
// --------------------------------------------------------
//! Actualisation des données météo toutes les 3 heures
// ---------------------------------------------------------

function calculerProchaineActualisation(): string
{
    $fuseau = new DateTimeZone('Europe/Paris');
    $maintenant = new DateTimeImmutable('now', $fuseau);
    $heures = [1, 4, 7, 10, 13, 16, 19, 22];

    foreach ($heures as $heure) {
        $candidate = $maintenant->setTime($heure, 0, 0);

        if ($candidate > $maintenant) {
            return $candidate->format(DATE_ATOM);
        }
    }

    return $maintenant
        ->modify('+1 day')
        ->setTime($heures[0], 0, 0)
        ->format(DATE_ATOM);
}
// ---------------------------------------------------------
//! Récupération et filtrage des données météo
// ---------------------------------------------------------

function recupererDonneesMeteo(string $ville): array
{
    $config = obtenirConfigurationApiMeteo();
    $endpoint = rtrim($config['url'], '/');

    if (!str_ends_with($endpoint, '/forecast')) {
        $endpoint .= '/forecast';
    }

    $parametres = [
        'lat' => $config['latitude'],
        'lon' => $config['longitude'],
        'appid' => $config['cle'],
        'units' => 'metric',
        'lang' => 'fr',
    ];

    $options = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
            'timeout' => 12,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $reponse = @file_get_contents(
        $endpoint . '?' . http_build_query($parametres),
        false,
        $options
    );
    $statutHttp = 0;

    foreach ($http_response_header ?? [] as $entete) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $entete, $correspondance) === 1) {
            $statutHttp = (int) $correspondance[1];
        }
    }

    if ($reponse === false || $statutHttp === 0) {
        error_log('Échec de la requête HTTPS vers le fournisseur météo.');
        http_response_code(502);

        return [
            'erreur' => 'Impossible de récupérer les données météo.',
        ];
    }

    if ($statutHttp < 200 || $statutHttp >= 300) {
        error_log('Le fournisseur météo a répondu avec le statut HTTP ' . $statutHttp . '.');
        http_response_code(502);

        return [
            'erreur' => 'Le fournisseur météo a refusé la requête.',
        ];
    }

    try {
        $donnees = json_decode($reponse, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('Le fournisseur météo a renvoyé un JSON invalide.');
        http_response_code(502);

        return [
            'erreur' => 'La réponse du fournisseur météo est invalide.',
        ];
    }

    if (!isset($donnees['list']) || !is_array($donnees['list'])) {
        error_log('La réponse météo ne contient pas les prévisions attendues.');
        http_response_code(502);

        return [
            'erreur' => 'La réponse du fournisseur météo est incomplète.',
        ];
    }

    $resultat = [];

    foreach ($donnees['list'] as $prevision) {
        if (!is_array($prevision)) {
            continue;
        }

        $main = $prevision['main'] ?? [];
        if (!is_array($main)) {
            continue;
        }

        $temperature = $main['temp'] ?? null;
        $humidite = $main['humidity'] ?? null;
        $horodatage = $prevision['dt'] ?? null;

        if (
            !is_numeric($temperature)
            || !is_numeric($humidite)
            || !is_numeric($horodatage)
        ) {
            continue;
        }

        $meteo = $prevision['weather'][0] ?? [];
        $pluieApi = $prevision['rain'] ?? [];
        $neigeApi = $prevision['snow'] ?? [];
        $ventApi = $prevision['wind'] ?? [];

        $meteo = is_array($meteo) ? $meteo : [];
        $pluieApi = is_array($pluieApi) ? $pluieApi : [];
        $neigeApi = is_array($neigeApi) ? $neigeApi : [];
        $ventApi = is_array($ventApi) ? $ventApi : [];

        $pluie = is_numeric($pluieApi['3h'] ?? null)
            ? (float) $pluieApi['3h']
            : 0.0;
        $neigeEnMm = is_numeric($neigeApi['3h'] ?? null)
            ? (float) $neigeApi['3h']
            : 0.0;
        $vent = is_numeric($ventApi['speed'] ?? null)
            ? (float) $ventApi['speed'] * 3.6
            : null;
        $rafales = is_numeric($ventApi['gust'] ?? null)
            ? (float) $ventApi['gust'] * 3.6
            : null;
        $dateTime = gmdate('Y-m-d H:i:s', (int) $horodatage);

        $resultat[] = [
            'date' => gmdate(DATE_ATOM, (int) $horodatage),
            'date_time' => $dateTime,
            'temperature' => (float) $temperature,
            'temp_min' => is_numeric($main['temp_min'] ?? null) ? (float) $main['temp_min'] : null,
            'temp_max' => is_numeric($main['temp_max'] ?? null) ? (float) $main['temp_max'] : null,
            'feels_like' => is_numeric($main['feels_like'] ?? null) ? (float) $main['feels_like'] : null,
            'humidite' => (int) $humidite,
            'pressure' => is_numeric($main['pressure'] ?? null) ? (int) $main['pressure'] : null,
            'precipitation' => $pluie,
            'precipitations' => $pluie + $neigeEnMm,
            'snow' => $neigeEnMm * 0.7,
            'wind_speed' => $vent,
            'vent' => $vent,
            'wind_gust' => $rafales,
            'weather_icon' => is_string($meteo['icon'] ?? null) ? $meteo['icon'] : null,
            'description' => is_string($meteo['description'] ?? null) ? $meteo['description'] : null,
            'weather_description' => is_string($meteo['description'] ?? null) ? $meteo['description'] : null,
        ];
    }

    enregistrerPrevisionsMeteo($resultat);

    return [
        'ville' => $ville,
        'source' => 'openweathermap',
        'donnees' => $resultat,
        'prochaineActualisation' => calculerProchaineActualisation(),
    ];
}

function enregistrerPrevisionsMeteo(array $previsions): void
{
    global $pdo;

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Connexion à la base de données indisponible.');
    }

    $requete = $pdo->prepare('
        INSERT INTO weather_data (
            date_time, temperature, humidity, wind_speed, wind_gust,
            pressure, feels_like, precipitation, snow, temp_min, temp_max,
            weather_icon, weather_description
        )
        VALUES (
            :date_time, :temperature, :humidity, :wind_speed, :wind_gust,
            :pressure, :feels_like, :precipitation, :snow, :temp_min, :temp_max,
            :weather_icon, :weather_description
        )
        ON DUPLICATE KEY UPDATE
            temperature = VALUES(temperature),
            humidity = VALUES(humidity),
            wind_speed = VALUES(wind_speed),
            wind_gust = VALUES(wind_gust),
            pressure = VALUES(pressure),
            feels_like = VALUES(feels_like),
            precipitation = VALUES(precipitation),
            snow = VALUES(snow),
            temp_min = VALUES(temp_min),
            temp_max = VALUES(temp_max),
            weather_icon = VALUES(weather_icon),
            weather_description = VALUES(weather_description)
    ');

    $pdo->beginTransaction();
    try {
        foreach ($previsions as $prevision) {
            $requete->execute([
                ':date_time' => $prevision['date_time'],
                ':temperature' => $prevision['temperature'],
                ':humidity' => $prevision['humidite'],
                ':wind_speed' => $prevision['wind_speed'],
                ':wind_gust' => $prevision['wind_gust'],
                ':pressure' => $prevision['pressure'],
                ':feels_like' => $prevision['feels_like'],
                ':precipitation' => $prevision['precipitation'],
                ':snow' => $prevision['snow'],
                ':temp_min' => $prevision['temp_min'],
                ':temp_max' => $prevision['temp_max'],
                ':weather_icon' => $prevision['weather_icon'],
                ':weather_description' => $prevision['weather_description'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ---------------------------------------------------------
//! Point d’entrée de l’API
// ---------------------------------------------------------

try {
    require_once __DIR__ . '/database.php';

    $ville = $_GET['ville'] ?? 'La Glacerie';

    if (!is_string($ville) || trim($ville) === '' || strlen($ville) > 100) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Le nom de la ville est invalide.']);
        exit;
    }

    echo json_encode(recupererDonneesMeteo(trim($ville)), JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Erreur interne de l’API météo : ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'erreur' => 'Erreur interne du serveur.',
    ], JSON_INVALID_UTF8_SUBSTITUTE);
}