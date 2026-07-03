<?php
include("conexion.php");
if(!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escáner QR - Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-upc-scan"></i> AsistenciaQR</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="empleados.php">Empleados</a></li>
                    <li class="nav-item"><a class="nav-link active" href="scan.php">Escáner QR</a></li>
                </ul>
                <a href="logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container text-center">
        <h3 class="mb-3">Registrar Entrada / Salida</h3>
        
        <div class="card shadow-sm mx-auto" style="max-width: 500px;">
            <div class="card-body">
                <div id="lector-qr" class="mb-3"></div>

                <hr>
                
                <form method="POST" action="procesar_asistencia.php" id="formulario-asistencia">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="qr_data" id="input-codigo" placeholder="Ej. EMP-001" required>
                        <button class="btn btn-primary" type="submit">Registrar Manual</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            html5QrcodeScanner.clear(); // Detiene la cámara temporalmente
            document.getElementById('input-codigo').value = decodedText;
            document.getElementById('formulario-asistencia').submit(); // Envía el formulario solo
        }

        let html5QrcodeScanner = new Html5QrcodeScanner("lector-qr", { fps: 10, qrbox: {width: 250, height: 250} }, false);
        html5QrcodeScanner.render(onScanSuccess);
    </script>

</body>
</html>