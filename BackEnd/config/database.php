<?php

declare(strict_types=1);

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

chargerVariablesEnvironnement(dirname(__DIR__, 2) . '/.env');

try {
    $pdo = obtenirConnexionBdd();
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        throw $e;
    }

    http_response_code(500);
    exit('Erreur de connexion à la base de données.');
}