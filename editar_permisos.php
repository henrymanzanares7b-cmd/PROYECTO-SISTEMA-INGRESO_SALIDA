<?php
session_start();
include("conexion.php");
include("permisos_helper.php");

// Solo usuarios con permisos de gestión (ej. Administrador) pueden editar
requerirPermiso('gestionar_empleados', 'No tienes permiso para gestionar permisos.');

$mensaje = '';
$nivel = null;
$id_nivel = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener datos del nivel a editar
if ($id_nivel > 0) {
    $stmt = mysqli_prepare($conexion, "SELECT * FROM niveles_permisos WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_nivel);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $nivel = mysqli_fetch_assoc($resultado);
}

if (!$nivel) {
    die('Nivel no encontrado.');
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recoger todos los permisos (checkboxes)
    $ver_reportes        = isset($_POST['ver_reportes']) ? 1 : 0;
    $exportar_excel      = isset($_POST['exportar_excel']) ? 1 : 0;
    $gestionar_empleados = isset($_POST['gestionar_empleados']) ? 1 : 0;
    $ver_todos_registros = isset($_POST['ver_todos_registros']) ? 1 : 0;
    $permiso_salud       = isset($_POST['permiso_salud']) ? 1 : 0;
    $permiso_personal    = isset($_POST['permiso_personal']) ? 1 : 0;
    $permiso_otros       = isset($_POST['permiso_otros']) ? 1 : 0;

    $update = "UPDATE niveles_permisos SET
                ver_reportes = ?,
                exportar_excel = ?,
                gestionar_empleados = ?,
                ver_todos_registros = ?,
                permiso_salud = ?",
                permiso_personal = ?,
                permiso_otros = ?
               WHERE id = ?";
    $stmt = mysqli_prepare($conexion, $update);
    mysqli_stmt_bind_param($stmt, 'iiiiiiii', 
        $ver_reportes, $exportar_excel, $gestionar_empleados, 
        $ver_todos_registros, $permiso_salud, $permiso_personal, 
        $permiso_otros, $id_nivel
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $mensaje = '<div class="alert-success">Permisos actualizados correctamente.</div>';
        // Refrescar datos del nivel
        $stmt2 = mysqli_prepare($conexion, "SELECT * FROM niveles_permisos WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, 'i', $id_nivel);
        mysqli_stmt_execute($stmt2);
        $resultado2 = mysqli_stmt_get_result($stmt2);
        $nivel = mysqli_fetch_assoc($resultado2);
    } else {
        $mensaje = '<div class="alert-error">Error al actualizar: ' . mysqli_error($conexion) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar permisos - AuraSync</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  /* Estilos básicos (puedes expandir con los mismos colores) */
  * { box-sizing: border-box; }
  body {
    font-family: 'Manrope', sans-serif;
    background: #f3f4f9;
    padding: 40px 20px;
    display: flex;
    justify-content: center;
  }
  .container {
    max-width: 800px;
    width: 100%;
    background: #fff;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.05);
  }
  h1 { font-size: 24px; color: #0c1024; margin-top: 0; }
  .field-group { margin-bottom: 30px; }
  .field-group h3 {
    font-size: 16px;
    font-weight: 600;
    color: #121735;
    margin-bottom: 12px;
    border-bottom: 1px solid #e7e9f2;
    padding-bottom: 8px;
  }
  .checkbox-item {
    display: inline-block;
    margin-right: 24px;
    margin-bottom: 10px;
    font-size: 14px;
    cursor: pointer;
  }
  .checkbox-item input[type="checkbox"] {
    margin-right: 6px;
    transform: scale(1.1);
  }
  .btn-save {
    background: #5b6ff2;
    color: #fff;
    border: none;
    padding: 12px 30px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: .2s;
  }
  .btn-save:hover { background: #4a5adf; }
  .alert-success {
    background: #e6f7e6;
    border: 1px solid #b2e0b2;
    color: #1a7a1a;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 20px;
  }
  .alert-error {
    background: #fff5f5;
    border: 1px solid #ffdbdb;
    color: #e53e3e;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 20px;
  }
  .back-link {
    display: inline-block;
    margin-top: 20px;
    color: #5b6ff2;
    text-decoration: none;
    font-weight: 600;
  }
  .back-link:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <h1>Editar permisos: <?php echo htmlspecialchars($nivel['nivel']); ?></h1>
    <?php echo $mensaje; ?>

    <form method="POST">
        <div class="field-group">
            <h3>Permisos existentes</h3>
            <label class="checkbox-item">
                <input type="checkbox" name="ver_reportes" <?= $nivel['ver_reportes'] ? 'checked' : '' ?>> Ver reportes
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="exportar_excel" <?= $nivel['exportar_excel'] ? 'checked' : '' ?>> Exportar Excel
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="gestionar_empleados" <?= $nivel['gestionar_empleados'] ? 'checked' : '' ?>> Gestionar empleados
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="ver_todos_registros" <?= $nivel['ver_todos_registros'] ? 'checked' : '' ?>> Ver todos los registros
            </label>
        </div>

        <div class="field-group">
            <h3>Nuevos permisos</h3>
            <label class="checkbox-item">
                <input type="checkbox" name="permiso_salud" <?= $nivel['permiso_salud'] ? 'checked' : '' ?>> Salud
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="permiso_personal" <?= $nivel['permiso_personal'] ? 'checked' : '' ?>> Personal
            </label>
            <label class="checkbox-item">
                <input type="checkbox" name="permiso_otros" <?= $nivel['permiso_otros'] ? 'checked' : '' ?>> Otros
            </label>
        </div>

        <button type="submit" class="btn-save">Guardar cambios</button>
        <a href="lista_permisos.php" class="back-link">← Volver a la lista</a>
    </form>
</div>
</body>
</html>