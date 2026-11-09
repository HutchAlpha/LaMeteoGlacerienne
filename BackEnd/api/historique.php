<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';

    $requete = $pdo->prepare('
        SELECT id, date_time AS date, temperature, humidity AS humidite,
                precipitation, temp_min, temp_max, wind_speed AS vitesse_vent,
                snow AS neige, weather_icon AS icone,
                weather_description AS description, wind_gust AS rafales,
                pressure AS pression, feels_like AS ressenti
        FROM weather_data
        ORDER BY date_time DESC
        '
    );
    $requete->execute();

    $nombre = (int) $pdo->query('SELECT COUNT(*) FROM weather_data')->fetchColumn();

    echo json_encode([
        'success' => true,
        'total' => $nombre,
        'donnees' => $requete->fetchAll(),
    ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Erreur de lecture de l’historique météo : ' . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'erreur' => 'Impossible de lire la base historique. Vérifiez la connexion MySQL et la table weather_data !',
    ]);
}
