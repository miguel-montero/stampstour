<?php
// confirmation.php
if (empty($_GET['external_reference'])) {
    header('Location: /');
    exit;
}
$idReserva = (int)$_GET['external_reference'];
$pdo = new PDO('mysql:host=localhost;dbname=stamptour;charset=utf8', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$stmt = $pdo->prepare("
    SELECT r.*, t.nombre, t.apellido, t.telefono, t.email
    FROM reserva r
    JOIN titular t ON t.id_titular = r.id_titular
    WHERE r.id_reserva = ?
");
$stmt->execute([$idReserva]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    echo "Reserva no encontrada.";
    exit;
}
$status = $_GET['status'] ?? 'success';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Confirmación de Reserva</title>
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body>
<main class="container py-4">
  <?php if($status==='success'): ?>
    <div class="alert alert-success">
      <h2>¡Pago exitoso!</h2>
      <p>Tu pago ha sido procesado correctamente.</p>
    </div>
  <?php else: ?>
    <div class="alert alert-danger">
      <h2>Pago no completado</h2>
      <p>Estado: <?=htmlspecialchars($status)?></p>
    </div>
  <?php endif; ?>
  <h3 class="mb-3">Detalle de tu reserva</h3>
  <ul class="list-group mb-4">
    <li class="list-group-item"><strong>Actividad:</strong> <?=htmlspecialchars($order['activity_name'])?></li>
    <li class="list-group-item"><strong>Nombre:</strong> <?=htmlspecialchars($order['nombre'].' '.$order['apellido'])?></li>
    <li class="list-group-item"><strong>Email:</strong> <?=htmlspecialchars($order['email'])?></li>
    <li class="list-group-item"><strong>Teléfono:</strong> <?=htmlspecialchars($order['telefono'])?></li>
    <li class="list-group-item"><strong>Fecha de actividad:</strong> <?=htmlspecialchars($order['fecha_actividad'])?></li>
  </ul>
  <form action="save_reservation.php" method="POST">
    <input type="hidden" name="order_id" value="<?=$idReserva?>">
    <div class="mb-3">
      <label for="hotel" class="form-label">Hotel donde te hospedarás</label>
      <input type="text" class="form-control" id="hotel" name="hotel" required>
    </div>
    <button type="submit" class="btn btn-primary">Confirmar Reserva</button>
  </form>
</main>
</body>
</html>