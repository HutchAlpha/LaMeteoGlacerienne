<?php

declare(strict_types=1);

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
    $latitude = valeurEnvironnement('LATITUDE');
    $longitude = valeurEnvironnement('LONGITUDE');
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

// ---------------------------------------------------------
//! Récupération et filtrage des données météo
// ---------------------------------------------------------

function recupererDonneesMeteo(string $ville): array
{
    $config = obtenirConfigurationApiMeteo();

    $parametres = http_build_query([
        'latitude' => $config['latitude'],
        'longitude' => $config['longitude'],
        'hourly' => 'temperature_2m,relative_humidity_2m',
        'timezone' => 'auto',
    ]);

    $url = $config['url'] . '?' . $parametres;

    // La clé API reste uniquement côté PHP
    if ($config['cle'] !== '') {
        $url .= '&apikey=' . urlencode($config['cle']);
    }

    $reponse = file_get_contents($url);

    if ($reponse === false) {
        http_response_code(502);

        return [
            'erreur' => 'Impossible de récupérer les données météo.',
        ];
    }

    $donnees = json_decode($reponse, true);

    if (!is_array($donnees)) {
        http_response_code(502);

        return [
            'erreur' => 'Réponse météo invalide.',
        ];
    }

    // -----------------------------------------------------
    //! FILTRE AVANT ENVOI AU FRONT / REDUX
    // -----------------------------------------------------

    $resultat = [];

    $dates = $donnees['hourly']['time'] ?? [];
    $temperatures = $donnees['hourly']['temperature_2m'] ?? [];
    $humidites = $donnees['hourly']['relative_humidity_2m'] ?? [];

    foreach ($dates as $index => $date) {
        $temperature = $temperatures[$index] ?? null;
        $humidite = $humidites[$index] ?? null;

        if ($temperature === null || $humidite === null) {
            continue;
        }

        $resultat[] = [
            'date' => $date,
            'temperature' => (float) $temperature,
            'humidite' => (float) $humidite,
        ];
    }

    return [
        'ville' => $ville,
        'source' => 'api-meteo',
        'donnees' => $resultat,
    ];
}

// ---------------------------------------------------------
//! Point d’entrée de l’API
// ---------------------------------------------------------

try {
    // Si api.php est à la racine du dossier BackEnd,
    // le .env doit être au dossier parent.
    chargerVariablesEnvironnement(dirname(__DIR__) . '/.env');

    $ville = $_GET['ville'] ?? 'Cherbourg';

    echo json_encode(recupererDonneesMeteo($ville));
} catch (Throwable $e) {
    error_log('Erreur API : ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'erreur' => 'Erreur interne du serveur.',
    ]);
}