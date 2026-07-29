<?php
// save_reservation.php
if (empty($_POST['order_id']) || empty($_POST['hotel'])) {
    header('Location: index.html');
    exit;
}
$idReserva = (int)$_POST['order_id'];
$hotelName = trim($_POST['hotel']);

// Conexión a base de datos usando configuración central
require_once __DIR__ . '/../db_config.php';  // carga $host, $user, $password y $dbname :contentReference[oaicite:0]{index=0}
$pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Buscar hotel existente
$stmt = $pdo->prepare("SELECT id_hotel FROM hotel WHERE nombre_hotel = ?");
$stmt->execute([$hotelName]);
$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

if ($hotel) {
    $idHotel = $hotel['id_hotel'];
} else {
    // Insertar nuevo hotel
    $ins = $pdo->prepare("INSERT INTO hotel (nombre_hotel) VALUES (?)");
    $ins->execute([$hotelName]);
    $idHotel = $pdo->lastInsertId();
}

// Actualizar reserva
$upd = $pdo->prepare("UPDATE reserva SET id_hotel = ? WHERE id_reserva = ?");
$upd->execute([$idHotel, $idReserva]);

header('Location: detalle_compra.php?order_id=' . $idReserva);
exit;
?>
