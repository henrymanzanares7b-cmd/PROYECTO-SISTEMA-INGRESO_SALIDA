<?php
session_start();
include("conexion.php");

// Si ya inició sesión, lo manda directo al panel
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Consulta con sentencia preparada (evita inyección SQL)
    $stmt = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE usuario = ?");
    mysqli_stmt_bind_param($stmt, "s", $usuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    if ($resultado && mysqli_num_rows($resultado) == 1) {
        $row = mysqli_fetch_assoc($resultado);

        // Verificación de contraseña (texto plano, igual que en el código original)
        if ($password == $row['password']) {
            $_SESSION['usuario']       = $row['usuario'];
            $_SESSION['nombre_admin']  = $row['nombre'];
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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MARCAJE CMD | Acceso Administrativo</title>
    <!-- Usamos Bootstrap para mantener la línea corporativa del resto de tu sistema -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
    /* Fondo azul profesional con un degradado de esquina a esquina */
    background: linear-gradient(135deg, #eef2f7 0%, #dbe4ef 100%);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: system-ui, -apple-system, sans-serif;
}

/* Tip extra para que el Login se vea más "profesional" */
.login-card {
    border: 1px solid rgba(0,0,0,0.05); /* Borde casi imperceptible para definir mejor el borde */
}

        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            background: #ffffff;
            padding: 2.5rem 2rem;
        }

        .brand-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 1.2rem auto;
            box-shadow: 0 0.25rem 0.5rem rgba(13, 110, 253, 0.2);
        }

        .form-floating > label {
            color: #6c757d;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }

        .btn-corporate {
            background: #0d6efd;
            color: white;
            font-weight: 600;
            padding: 0.8rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-corporate:hover {
            background: #0a58ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
        }

        /* Alerta Flotante (Toast) */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1055;
        }

        .custom-toast {
            background: #fff;
            border-left: 5px solid #dc3545;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .custom-toast.show {
            transform: translateX(0);
        }

        .custom-toast i {
            font-size: 1.5rem;
            color: #dc3545;
        }

        .custom-toast .fw-bold {
            color: #212529;
            margin-bottom: 0.2rem;
            font-size: 0.95rem;
        }

        .custom-toast .text-muted {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <div class="brand-icon">
                <i class="bi bi-buildings-fill"></i>
            </div>
            <h2 class="fw-bold text-dark mb-1">MARCAJE CMD</h2>
            <p class="text-muted small">Portal Administrativo</p>
        </div>

        <form method="POST" action="">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required autocomplete="off">
                <label for="usuario"><i class="bi bi-person-fill me-2"></i>Usuario asignado</label>
            </div>
            
            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                <label for="password"><i class="bi bi-lock-fill me-2"></i>Contraseña</label>
            </div>

            <button class="btn btn-corporate w-100" type="submit">Ingresar al Sistema</button>
        </form>
        
        <div class="text-center mt-4">
            <small class="text-muted">© <?php echo date('Y'); ?> MARCAJE CMD. Todos los derechos reservados.</small>
        </div>
    </div>

    <!-- Contenedor del Toast para Errores -->
    <div class="toast-container">
        <div class="custom-toast" id="errorToast">
            <i class="bi bi-x-circle-fill"></i>
            <div>
                <div class="fw-bold">Error de Autenticación</div>
                <div class="text-muted" id="toastMessage"></div>
            </div>
        </div>
    </div>

    <script>
        // Mostrar Toast si hay error desde PHP
        const errorPhp = "<?php echo addslashes($error); ?>";
        
        if (errorPhp !== "") {
            const toast = document.getElementById('errorToast');
            document.getElementById('toastMessage').textContent = errorPhp;
            
            // Mostrar con animación
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            // Ocultar automáticamente después de 4 segundos
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>