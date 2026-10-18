<?php
//plantilla de configuración: copiar como config.php y rellenar los valores
$host = '';
$usuario = '';
$password = '';
$nombreBaseDatos = '';
$puerto = 3306;

//clave de la API de RAWG (obtener en: https://rawg.io/apidocs)
$claveApiRawg = '';

//correo saliente (contraseña olvidada y reportes de errores)
//con Gmail: $smtpClave es una "contraseña de aplicación", no la contraseña de la cuenta
$smtpHost = 'smtp.gmail.com';
$smtpPuerto = 587;
$smtpUsuario = '';           //correo desde el que se envía
$smtpClave = '';
$smtpNombreRemitente = 'Juegos Pendientes';
$correoReportes = '';        //correo que recibe los reportes de errores
?>
