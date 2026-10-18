<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/errores.css" />
    <title>Reportar un error</title>
</head>

<body>

    <?php
    //depuración
    // ini_set('display_errors', 1);
    // ini_set('display_startup_errors', 1);
    // error_reporting(E_ALL);

    //PHPMailer configurado con los datos de sql/config.php
    include_once "./correo.php";

    //excepción que lanza PHPMailer si falla el envío
    use PHPMailer\PHPMailer\Exception;

    //comprobar que se ha pulsado el botón de envío del formulario de reportar un error
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        //asignar los valores introducidos a variables
        $tipoError = $_POST['errorType'];
        $correoUsuario = $_POST['userEmail'];
        $errorDetallado = trim($_POST['errorDetails']);

        /* Comprobaciones*/

        //comprobar que el envío de correos está configurado
        if (!correoConfigurado()) {
            echo "<a href='../index.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>" . MENSAJE_SIN_CORREO . "</p>");
        }

        //comprobar que no hay campos vacíos
        if (empty($tipoError) || empty($correoUsuario) || empty($errorDetallado)) {
            echo "<a href='../index.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>No puede haber campos vacíos</p>");
        }//comprobar que el correo cumple con la norma
        elseif (!filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) {
            echo "<a href='../index.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>El correo introducido no es válido</p>");
        }//comprobar que la opción seleccionada es válida
        elseif ($tipoError != "other" && $tipoError != "website" && $tipoError != "account") {
            echo "<a href='../index.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>La opción introducida no es correcta.</p>");
        }

        /* Fin de comprobaciones (por ahora) */

        try {
            //configurar el correo del reporte
            $mail = crearCorreo();
            $mail->addAddress(correoReportes()); // A: el correo que recibe los reportes
            $mail->addReplyTo($correoUsuario); // Responder: al correo del usuario que reporta

            // Contenido del correo (escapado para que el usuario no pueda meter HTML)
            $mail->isHTML(true);
            $mail->Subject = "Nuevo reporte: $tipoError";
            $mail->Body = "<p><strong>Correo del usuario:</strong> " . htmlspecialchars($correoUsuario) . "</p>
                      <p><strong>Detalles del error:</strong> " . nl2br(htmlspecialchars($errorDetallado)) . "</p>";

            // Enviar el correo
            $mail->send();

            //avisar de que se ha enviado
            echo "<a href='../index.html'>Volver al inicio</a>";
            echo "<p class='exito'><strong>¡Reporte enviado!</strong><br/>Gracias por avisar. Si hace falta más información te responderemos a "
                . "<strong>" . htmlspecialchars($correoUsuario) . "</strong>.</p>";
        } catch (Exception $e) {
            echo "<a href='../index.html'>Volver atrás</a><br/><br/>";
            echo "<p class='error'>Error al enviar el correo: " . htmlspecialchars($e->getMessage()) . "</p>";
        }


    }
    ?>

</body>

</html>
