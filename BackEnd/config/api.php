<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ---------------------------------------------------------
//! Chargement du fichier .env
// ---------------------------------------------------------

function chargerVariablesEnvironnement(string $chemin): void
{
    if (!is_file($chemin)) {
        throw new RuntimeException(
            'Fichier .env introuvable. Copie .env.example vers .env puis renseigne les variables.'
        );
    }

    $variables = parse_ini_file($chemin, false, INI_SCANNER_RAW);

    if ($variables === false) {
        throw new RuntimeException('Impossible de lire le fichier .env.');
    }

    foreach ($variables as $cle => $valeur) {
        if ($valeur === null || $valeur === '') {
            continue;
        }

        if (getenv($cle) === false) {
            putenv("{$cle}={$valeur}");
            $_ENV[$cle] = $valeur;
            $_SERVER[$cle] = $valeur;
        }
    }
}

function valeurEnvironnement(string $cle, string $defaut = ''): string
{
    $valeur = getenv($cle);

    return ($valeur === false || $valeur === '') ? $defaut : (string) $valeur;
}

// ---------------------------------------------------------
//! Configuration base de données
// ---------------------------------------------------------

function configurationDepuisEnvironnement(string $prefixe, string $env): array
{
    $host = valeurEnvironnement($prefixe . 'HOST');
    $dbname = valeurEnvironnement($prefixe . 'NAME');
    $user = valeurEnvironnement($prefixe . 'USER');

    if ($host === '' || $dbname === '' || $user === '') {
        throw new RuntimeException(
            "Configuration BDD incomplète pour {$env} : vérifie {$prefixe}HOST, {$prefixe}NAME et {$prefixe}USER."
        );
    }

    return [
        'host' => $host,
        'port' => valeurEnvironnement($prefixe . 'PORT', '3306'),
        'dbname' => $dbname,
        'user' => $user,
        'pass' => valeurEnvironnement($prefixe . 'PASS'),
        'env' => $env,
    ];
}

function connexionBdd(array $config): PDO
{
    $port = $config['port'] !== '' ? ';port=' . $config['port'] : '';

    return new PDO(
        "mysql:host={$config['host']}{$port};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function obtenirConnexionBdd(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configProduction = configurationDepuisEnvironnement('DB_', 'PRODUCTION');
    $configLocale = configurationDepuisEnvironnement('DB_LOCAL_', 'LOCAL-FALLBACK');

    try {
        $pdo = connexionBdd($configProduction);

        error_log('Connexion BDD réussie : PRODUCTION');

        return $pdo;
    } catch (PDOException $e) {
        error_log('Connexion BDD PRODUCTION impossible : ' . $e->getMessage());
    }

    try {
        $pdo = connexionBdd($configLocale);

        error_log('Connexion BDD réussie : LOCAL-FALLBACK');

        return $pdo;
    } catch (PDOException $e) {
        error_log('Connexion BDD LOCAL impossible : ' . $e->getMessage());

        throw new RuntimeException(
            'Impossible de se connecter à la base de données, en ligne comme en local.'
        );
    }
}

// ---------------------------------------------------------
//! Configuration API météo
// ---------------------------------------------------------

function obtenirConfigurationApiMeteo(): array
{
    $url = valeurEnvironnement('METEO_API_URL');
    $latitude = valeurEnvironnement('LATITUDE', valeurEnvironnement('Latitude'));
    $longitude = valeurEnvironnement('LONGITUDE', valeurEnvironnement('Longitude'));
    $cle = valeurEnvironnement('METEO_API_KEY');

    if ($url === '' || $latitude === '' || $longitude === '') {
        throw new RuntimeException(
            'Configuration API météo incomplète : vérifie METEO_API_URL, LATITUDE et LONGITUDE dans .env.'
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
    $heuresRafraichissement = [1, 4, 7, 10, 13, 16, 19, 22];

    $heureActuelle = (int) date('G');
    $prochaineHeure = null;

    foreach ($heuresRafraichissement as $heure) {
        if ($heure > $heureActuelle) {
            $prochaineHeure = $heure;
            break;
        }
    }

    if ($prochaineHeure === null) {
        $prochaineHeure = $heuresRafraichissement[0];

        return date(
            'Y-m-d\TH:i:s',
            strtotime('tomorrow ' . $prochaineHeure . ':00')
        );
    }

    return date(
        'Y-m-d\TH:i:s',
        strtotime('today ' . $prochaineHeure . ':00')
    );
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
        'units' => 'metric',
        'lang' => 'fr',
    ];

    if ($config['cle'] !== '') {
        $parametres['appid'] = $config['cle'];
    }

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
        $temperature = $prevision['main']['temp'] ?? null;
        $humidite = $prevision['main']['humidity'] ?? null;
        $horodatage = $prevision['dt'] ?? null;

        if (!is_numeric($temperature) || !is_numeric($humidite) || !is_numeric($horodatage)) {
            continue;
        }

        $resultat[] = [
            'date' => gmdate(DATE_ATOM, (int) $horodatage),
            'temperature' => (float) $temperature,
            'humidite' => (int) $humidite,
            'precipitations' => (float) ($prevision['rain']['3h'] ?? 0)
                + (float) ($prevision['snow']['3h'] ?? 0),
            'vent' => isset($prevision['wind']['speed'])
                ? (float) $prevision['wind']['speed'] * 3.6
                : null,
            'description' => $prevision['weather'][0]['description'] ?? null,
        ];
    }

    return [
        'ville' => $ville,
        'source' => 'openweathermap',
        'donnees' => $resultat,
        'prochaineActualisation' => calculerProchaineActualisation(),
    ];
}

// ---------------------------------------------------------
//! Point d’entrée de l’API
// ---------------------------------------------------------

try {
    chargerVariablesEnvironnement(dirname(__DIR__) . '/.env');

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