<?php
require __DIR__ . '/../db_config.php';

$id_experiencia = intval($_REQUEST['id_experiencia'] ?? 0);
$adultos = intval($_REQUEST['adultos'] ?? 0);
$ninos = intval($_REQUEST['ninos'] ?? 0);
$infantes = intval($_REQUEST['infantes'] ?? 0);
$coupon = trim($_REQUEST['coupon_code'] ?? '');

$stmt = $conn->prepare("SELECT precio_concierge, precio_nino, precio_infante FROM experiencias WHERE id_experiencia=?");
$stmt->bind_param("i", $id_experiencia);
$stmt->execute();
$stmt->bind_result($pa, $pc, $pi);
$stmt->fetch();
$stmt->close();

$subtotal = $adultos * $pa + $ninos * $pc + $infantes * $pi;
$discount = 0;

if ($coupon !== '') {
  $stmt = $conn->prepare("SELECT porcentaje FROM cupones WHERE nombre=?");
  $stmt->bind_param("s", $coupon);
  $stmt->execute();
  $stmt->bind_result($pct);
  if ($stmt->fetch()) {
    $discount = $subtotal * ($pct/100);
  }
  $stmt->close();
}

$total = $subtotal - $discount;

header('Content-Type: application/json');
echo json_encode([
  'subtotal' => number_format($subtotal, 2, '.', ''),
  'discount' => number_format($discount, 2, '.', ''),
  'total' => number_format($total, 2, '.', '')
]);
?>
