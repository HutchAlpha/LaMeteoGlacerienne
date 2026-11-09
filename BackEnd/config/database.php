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

chargerVariablesEnvironnement(dirname(__DIR__) . '/.env');

try {
    $configuration = configurationDepuisEnvironnement('DB_', valeurEnvironnement('APP_ENV', 'NON DÉFINI'));
    $pdo = connexionBdd($configuration);
} catch (Throwable $e) {
    error_log('Connexion à la base de données impossible : ' . $e->getMessage());
    throw new RuntimeException('Connexion à la base de données impossible.', 0, $e);
}