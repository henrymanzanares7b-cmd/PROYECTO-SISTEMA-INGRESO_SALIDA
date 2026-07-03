<?php
include("conexion.php");

// Si ya inició sesión, lo manda directo al panel
if(isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = mysqli_real_escape_string($conexion, trim($_POST['usuario']));
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM usuarios WHERE usuario = '$usuario'";
    $resultado = mysqli_query($conexion, $sql);

    if(mysqli_num_rows($resultado) == 1) {
        $row = mysqli_fetch_assoc($resultado);
        // Verifica la contraseña encriptada
        if($password == $row['password']) {
            $_SESSION['usuario'] = $row['usuario'];
            $_SESSION['nombre_admin'] = $row['nombre'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "El usuario no existe.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Control de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center py-4 bg-light h-100">
    
    <div class="container" style="max-width: 400px;">
        <div class="card shadow-sm p-4 border-0 rounded-3">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">AsistenciaQR</h2>
                <p class="text-muted">Panel de Administración</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger py-2 text-center" role="alert">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="usuario" name="usuario" placeholder="admin" required autocomplete="off">
                    <label for="usuario">Usuario</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Contraseña</label>
                </div>

                <button class="w-100 btn btn-lg btn-primary fw-semibold" type="submit">Ingresar al Sistema</button>
            </form>
            
            <div class="text-center mt-4">
                <span class="text-muted text-xs">&copy; 2026 Sistema de Control</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>