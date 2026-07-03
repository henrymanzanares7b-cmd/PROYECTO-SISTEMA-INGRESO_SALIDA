<?php
include("conexion.php");

// Protegemos la página: Si no hay sesión, lo pateamos al login
if(!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('America/El_Salvador');
$fecha_hoy = date("Y-m-d");

// --- CONSULTAS PARA LAS ESTADÍSTICAS DEL DASHBOARD ---

// 1. Total de Empleados Activos
$query_empleados = "SELECT COUNT(*) as total FROM empleados WHERE estado = 'Activo'";
$res_empleados = mysqli_query($conexion, $query_empleados);
$total_empleados = mysqli_fetch_assoc($res_empleados)['total'] ?? 0;

// 2. Total de Entradas de Hoy
$query_entradas = "SELECT COUNT(*) as total FROM registros_asistencia WHERE fecha = '$fecha_hoy'";
$res_entradas = mysqli_query($conexion, $query_entradas);
$entradas_hoy = mysqli_fetch_assoc($res_entradas)['total'] ?? 0;

// 3. Salidas Registradas Hoy (Para saber quién ya se fue)
$query_salidas = "SELECT COUNT(*) as total FROM registros_asistencia WHERE fecha = '$fecha_hoy' AND hora_salida IS NOT NULL";
$res_salidas = mysqli_query($conexion, $query_salidas);
$salidas_hoy = mysqli_fetch_assoc($res_salidas)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-upc-scan"></i> AsistenciaQR</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="empleados.php">Empleados</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="scan.php">Escáner QR</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Hola, <b><?php echo $_SESSION['nombre_admin']; ?></b></span>
                    <a href="logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mb-4">Resumen de Hoy (<?php echo date("d/m/Y"); ?>)</h2>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-primary shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-people-fill"></i> Empleados Activos</h5>
                        <h1 class="display-4 fw-bold"><?php echo $total_empleados; ?></h1>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-success shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-box-arrow-in-right"></i> Entradas Hoy</h5>
                        <h1 class="display-4 fw-bold"><?php echo $entradas_hoy; ?></h1>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card text-white bg-warning shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title text-dark"><i class="bi bi-box-arrow-left"></i> Salidas Hoy</h5>
                        <h1 class="display-4 fw-bold text-dark"><?php echo $salidas_hoy; ?></h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="scan.php" class="btn btn-lg btn-success me-2 shadow-sm">
                    <i class="bi bi-camera"></i> Abrir Lector QR
                </a>
                <a href="empleados.php" class="btn btn-lg btn-secondary shadow-sm">
                    <i class="bi bi-person-lines-fill"></i> Gestionar Personal
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>