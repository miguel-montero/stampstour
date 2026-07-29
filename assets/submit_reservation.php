<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stamptour";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Escapar y recibir variables POST
$nombre = $conn->real_escape_string($_POST['name_booking']);
$apellido = $conn->real_escape_string($_POST['last_name_booking']);
$email = $conn->real_escape_string($_POST['email_booking']);
$telefono = $conn->real_escape_string($_POST['phone_booking']);
$adultos = (int)$_POST['adults'];
$ninos = (int)$_POST['children'];
$infantes = (int)$_POST['infants'];
$pickup = $conn->real_escape_string($_POST['airport_pick_up']);
$id_experiencia = (int)$_POST['id_experiencia'];
$fecha_reserva = $conn->real_escape_string($_POST['fecha_reserva']);
$fecha_tour = $conn->real_escape_string($_POST['date_booking']);
$subtotal = (float)$_POST['subtotal'];
$total = (float)$_POST['total_price'];
$cupon = !empty($_POST['couponApplied']) ? "'" . $conn->real_escape_string($_POST['couponApplied']) . "'" : "NULL";

// Insertar datos
$sql = "INSERT INTO reservas (nombre, apellido, email, telefono, adultos, ninos, infantes, airport_pick_up, id_experiencia, fecha_reserva, fecha_tour, subtotal, total, cupon_aplicado, estado)
VALUES ('$nombre', '$apellido', '$email', '$telefono', $adultos, $ninos, $infantes, '$pickup', $id_experiencia, '$fecha_reserva', '$fecha_tour', $subtotal, $total, $cupon, 'pendiente')";

if ($conn->query($sql) === TRUE) {
    // Redirigir a shopping.html
    echo "<script>window.location.href = 'shopping.html';</script>";
    exit;
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>
