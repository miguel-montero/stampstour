<?php
include __DIR__ . '/../db_config.php';

$nombre = $_GET['nombre'] ?? '';
$stmt = $conn->prepare("SELECT nombre, precio_adulto, precio_nino, precio_infante FROM experiencias WHERE nombre = ?");
$stmt->bind_param("s", $nombre);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode($result);
?>
