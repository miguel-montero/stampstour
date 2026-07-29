<?php
header('Content-Type: application/json');

// Conexión a base de datos usando configuración central
require_once __DIR__ . '/../db_config.php';  // carga $host, $user, $password y $dbname
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    // En caso de error, devolvemos un array vacío
    echo json_encode([]);
    exit;
}

$term = $_GET['term'] ?? '';
$term = trim($term);

if (strlen($term) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT nombre_hotel, direccion, comuna 
    FROM hoteles 
    WHERE LOWER(nombre_hotel) LIKE LOWER(?) 
       OR LOWER(nombre_hotel) LIKE LOWER(?) 
       OR LOWER(nombre_hotel) LIKE LOWER(?) 
    ORDER BY nombre_hotel ASC 
    LIMIT 15
");
$param = strtolower($term);
$stmt->execute([
    $param . '%',
    '% ' . $param . '%',
    '%-' . $param . '%'
]);

$hotels = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hotels[] = [
        'label' => $row['nombre_hotel'],
        'value' => $row['nombre_hotel'],
        'desc'  => trim($row['direccion'] . ', ' . $row['comuna'])
    ];
}

echo json_encode($hotels);
