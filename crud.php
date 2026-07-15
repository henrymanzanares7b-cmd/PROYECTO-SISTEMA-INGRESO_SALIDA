<?php
// Se incluye el archivo que contiene la conexión a la base de datos
include("conexion.php"); 

// 1. CONDICIÓN PARA AGREGAR
if(isset($_POST['agregar'])) {
    $nombres = $_POST['nombres'];
    $cedula = $_POST['cedula'];
    $carrera = $_POST['carrera'];

    // Se prepara la sentencia SQL
    $sql = "INSERT INTO estudiantes (nombre, cedula, carrera) VALUES ('$nombres', '$cedula', '$carrera')";
    
    // Se asume la ejecución de la consulta (ej. mysqli_query)
    $resultado = mysqli_query($conexion, $sql); 

    if($resultado) {
        echo "Estudiante agregado con éxito";
        echo "<br><button><a href='../index.php'>Volver</a></button>";
    } else {
        echo "Error al agregar el registro: " . mysqli_error($conexion);
    }
}

// 2. CONDICIÓN PARA BUSCAR/LEER
if(isset($_POST['read'])) {
    $cedula = $_POST['cedula'];
    $sql = "SELECT * FROM estudiantes WHERE cedula = '$cedula'";
    $resultado = mysqli_query($conexion, $sql);

    // Si se encontraron registros (filas > 0)
    if(mysqli_num_rows($resultado) > 0) {
        // Se recorren y extraen los datos (fetch_assoc)
        while($fila = mysqli_fetch_assoc($resultado)) {
            // Se inyectan los datos recuperados en un formulario para poder editarlos
            echo "<form method='POST' action='crud.php'>";
            echo "Nombre: <br><input type='text' name='nombres' value='" . $fila['nombres'] . "'><br>";
            echo "Cédula: <br><input type='text' name='cedula' value='" . $fila['cedula'] . "'><br>";
            echo "Carrera: <br><input type='text' name='carrera' value='" . $fila['carrera'] . "'><br>";
            echo "<input type='submit' name='editar' value='editar'>";
            echo "</form>";

            // Botón en forma de enlace para eliminar mediante el método GET
            echo "<br><button><a href='crud.php?cedula=" . $fila['cedula'] . "&eliminar=eliminar'>Eliminar</a></button>";
        }
    }

}    

// 3. CONDICIÓN PARA ACTUALIZAR (EDITAR)
if(isset($_POST['editar'])) {
    $nombres = $_POST['nombres'];
    $cedula = $_POST['cedula'];
    $carrera = $_POST['carrera'];

    $sql = "UPDATE estudiantes SET nombres='$nombres', cedula='$cedula', carrera='$carrera' WHERE cedula='$cedula'";
    $resultado = mysqli_query($conexion, $sql);

    if($resultado) {
        echo "Estudiante actualizado con éxito";
        echo "<br><button><a href='../index.php'>Volver</a></button>";
    } else {
        echo "Error al actualizar el registro: " . mysqli_error($conexion);
    }
}

// 4. CONDICIÓN PARA ELIMINAR
if(isset($_GET['eliminar'])) {
    $cedula = $_GET['cedula'];
    
    $sql = "DELETE FROM estudiantes WHERE cedula = '$cedula'";
    $resultado = mysqli_query($conexion, $sql);

    if($resultado) {
        echo "Estudiante eliminado con éxito";
        echo "<br><button><a href='../index.php'>Volver</a></button>";
    } else {
        echo "Error al eliminar el registro: " . mysqli_error($conexion);
    }
}
?>

