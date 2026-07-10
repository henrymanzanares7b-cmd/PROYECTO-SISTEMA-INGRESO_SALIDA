<?php
// procesar_estado_permiso.php (VERSIÓN FINAL LIMPIA)
include("conexion.php");

if (isset($_GET['id']) && isset($_GET['accion'])) {
    $id_solicitud = (int) $_GET['id'];
    $accion = strtolower(trim($_GET['accion']));
    
    if ($accion === 'eliminar') {
        $sql = "DELETE FROM solicitudes_permisos WHERE id_solicitud = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id_solicitud);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // AQUÍ: Asegúrate de que esta palabra sea EXACTAMENTE igual a la de tu BD
        $nuevo_estado = ($accion === 'aprobar') ? 'aprobado' : 'rechazado';
        
        $sql = "UPDATE solicitudes_permisos SET estado = ? WHERE id_solicitud = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "si", $nuevo_estado, $id_solicitud);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Redirigir de vuelta a la página principal de permisos
header('Location: permisos.php');
exit();
?>