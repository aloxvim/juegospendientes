<?php
// correo.php
// Crea un PHPMailer ya configurado con los datos SMTP de sql/config.php.
// Lo usan claveOlvidada.php y reportar.php.

//traer el archivo autoload del phpmailer
require_once __DIR__ . '/../vendor/autoload.php';

//funciones para leer sql/config.php
include_once __DIR__ . '/instalacion.php';

use PHPMailer\PHPMailer\PHPMailer;

//mensaje cuando faltan los datos del correo
const MENSAJE_SIN_CORREO = "<strong>El envío de correos no está configurado.</strong> Rellena los campos <code>\$smtp...</code> de <code>sql/config.php</code>.";

//comprobar que los datos del correo saliente están rellenos
function correoConfigurado() {
    $config = cargarConfig();
    return $config !== null
        && trim($config['smtpHost']) !== ''
        && trim($config['smtpUsuario']) !== ''
        && trim($config['smtpClave']) !== '';
}

//crear el PHPMailer con la configuración SMTP (lanza excepciones si falla el envío)
function crearCorreo() {
    $config = cargarConfig();

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    // Configurar SMTP
    $mail->isSMTP();
    $mail->Host = $config['smtpHost'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtpUsuario'];
    $mail->Password = $config['smtpClave'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) $config['smtpPuerto'];

    // De: siempre la cuenta configurada (Gmail no deja enviar como otra dirección)
    $mail->setFrom($config['smtpUsuario'], $config['smtpNombreRemitente']);

    return $mail;
}

//correo que recibe los reportes (si está vacío, la propia cuenta de envío)
function correoReportes() {
    $config = cargarConfig();
    return trim($config['correoReportes']) !== '' ? $config['correoReportes'] : $config['smtpUsuario'];
}
?>
