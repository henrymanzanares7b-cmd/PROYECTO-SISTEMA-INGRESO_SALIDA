<?php
// permisos_helper.php
// Debe ser incluido después de session_start() en cada página

/**
 * Verifica si el usuario actual tiene un permiso específico
 * @param string $permiso Nombre del permiso (ej. 'ver_reportes', 'permiso_salud')
 * @return bool
 */
function tienePermiso($permiso) {
    if (!isset($_SESSION['permisos']) || !is_array($_SESSION['permisos'])) {
        return false;
    }
    return isset($_SESSION['permisos'][$permiso]) && $_SESSION['permisos'][$permiso] === true;
}

/**
 * Redirige o muestra error si no tiene permiso
 * @param string $permiso
 * @param string $mensaje (opcional)
 */
function requerirPermiso($permiso, $mensaje = 'No tienes permiso para acceder a esta sección.') {
    if (!tienePermiso($permiso)) {
        // Puedes redirigir o mostrar un mensaje de error
        die('<h2>Acceso denegado</h2><p>' . htmlspecialchars($mensaje) . '</p>');
    }
}
?>