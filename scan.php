<?php
include("conexion.php");
if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sist.Control - Escáner QR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background-color: #f4f6f9; }
        .navbar-custom { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        #lector-qr { border: none !important; border-radius: 1rem; overflow: hidden; }
        #lector-qr video { border-radius: 1rem; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-5 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-upc-scan me-2"></i>Sist.Control</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="empleados.php">Empleados</a></li>
                    <li class="nav-item"><a class="nav-link active fw-semibold" href="scan.php">Escáner QR</a></li>
                </ul>
                <a href="logout.php" class="btn btn-light btn-sm rounded-pill fw-bold text-primary shadow-sm px-3">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container text-center">
        <div class="mb-4">
            <h2 class="fw-bold text-dark"><i class="bi bi-qr-code-scan text-primary"></i> Control de Tiempos</h2>
            <p class="text-muted">Acerca el código QR a la cámara para registrar tus marcajes</p>
        </div>
        
        <div class="card shadow rounded-4 border-0 mx-auto bg-body-tertiary p-3" style="max-width: 550px;">
            <div class="card-body">
                <div id="lector-qr" class="mb-4 shadow-sm bg-white"></div>
                <hr class="text-muted">
                <form method="POST" action="procesar_asistencia.php" id="formulario-asistencia">
                    <label class="form-label text-muted fw-semibold small text-start w-100 mb-2">Ingreso Manual (Respaldo)</label>
                    <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-keyboard text-muted"></i></span>
                        <input type="text" class="form-control border-0 bg-white" name="qr_data" id="input-codigo" placeholder="Ej. EMP-001" required>
                        <button class="btn btn-primary fw-bold px-4" type="submit">Marcar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            html5QrcodeScanner.clear();
            document.getElementById('input-codigo').value = decodedText;
            document.getElementById('formulario-asistencia').submit();
        }
        let html5QrcodeScanner = new Html5QrcodeScanner("lector-qr", { fps: 15, qrbox: {width: 250, height: 250} }, false);
        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>