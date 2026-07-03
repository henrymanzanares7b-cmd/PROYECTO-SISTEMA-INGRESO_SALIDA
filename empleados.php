<?php
include("conexion.php");
if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

// --- LÓGICA PARA AGREGAR ---
if(isset($_POST['agregar'])) {
    $id = $_POST['id_empleado'];
    $nombre = $_POST['nombre_completo'];
    $puesto = $_POST['puesto'];

    $sql_insert = "INSERT INTO empleados (id_empleado, nombre_completo, puesto) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql_insert);
    mysqli_stmt_bind_param($stmt, "sss", $id, $nombre, $puesto);
    mysqli_stmt_execute($stmt);
}

// --- LÓGICA PARA ELIMINAR ---
if(isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    $sql_delete = "DELETE FROM empleados WHERE id_empleado = ?";
    $stmt = mysqli_prepare($conexion, $sql_delete);
    mysqli_stmt_bind_param($stmt, "s", $id_eliminar);
    mysqli_stmt_execute($stmt);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Empleados</title>
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="empleados.php">Empleados</a></li>
                    <li class="nav-item"><a class="nav-link" href="scan.php">Escáner QR</a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php">Reportes</a></li>
                    <li class="nav-item"><a class="nav-link" href="permisos.php">Permisos</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Hola, <b><?php echo htmlspecialchars($_SESSION['nombre_admin'] ?? $_SESSION['usuario']); ?></b></span>
                    <a href="logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mb-4">Gestión de Personal</h2>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold">Nuevo Empleado</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label>Código (Ej. EMP-004)</label>
                                <input type="text" name="id_empleado" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Nombre Completo</label>
                                <input type="text" name="nombre_completo" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Puesto</label>
                                <input type="text" name="puesto" class="form-control" required>
                            </div>
                            <button type="submit" name="agregar" class="btn btn-success w-100">Guardar Empleado</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Puesto</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql_select = "SELECT * FROM empleados";
                                $resultado = mysqli_query($conexion, $sql_select);
                                while($fila = mysqli_fetch_assoc($resultado)) {
                                    echo "<tr>";
                                    echo "<td><b>" . htmlspecialchars($fila['id_empleado']) . "</b></td>";
                                    echo "<td>" . htmlspecialchars($fila['nombre_completo']) . "</td>";
                                    echo "<td>" . htmlspecialchars($fila['puesto']) . "</td>";
                                    echo "<td><span class='badge bg-success'>" . htmlspecialchars($fila['estado']) . "</span></td>";
                                    echo "<td><a href='empleados.php?eliminar=" . urlencode($fila['id_empleado']) . "' class='btn btn-sm btn-danger' onclick='return confirm(\"¿Borrar empleado?\")'><i class='bi bi-trash'></i></a></td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>