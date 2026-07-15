<?php
include("conexion.php");
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>