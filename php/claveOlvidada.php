<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/errores.css">
    <title>Nueva contraseña</title>
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

    //traer el archivo de conexión
    include_once "./conectar.php";

    //establecer la conexión
    $conectar = getConexion();

    //comprobar que se puede establecer la conexión
    if (!$conectar) {
        die("<p class='error'>Error en la conexión a la base de datos</p>");
    }

    //comprobar que se ha pulsado el botón de envío del formulario de crear cuenta
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        //asignar los valores introducidos a variables
        $correo = $_POST['email'];

        /* Comprobaciones */

        //comprobar que el envío de correos está configurado (antes de tocar la contraseña)
        if (!correoConfigurado()) {
            echo "<a href='../html/login.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>" . MENSAJE_SIN_CORREO . "</p>");
        }

        //comprobar que el campo no está vacío
        if (empty($correo)) {
            echo "<a href='../html/login.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>No puedes dejar el campo vacío.</p>");
        }

        //comprobar que el correo electrónico es válido
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo "<a href='../html/login.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>El correo electrónico no es válido.</p>");
        }

        //comprobar que el correo del usuario existe en la base de datos
        $sqlExisteCorreo = "SELECT email FROM Usuarios WHERE email = ?;";
        $prepararConsulta = $conectar->prepare($sqlExisteCorreo); //preparar la consulta
        $prepararConsulta->bind_param("s", $correo); //blindar el parámetro
        $prepararConsulta->execute(); //ejecutar la consulta
        $resultado = $prepararConsulta->get_result(); //obtener el resultado
        if ($resultado->num_rows === 0) {
            echo "<a href='../html/login.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>No se encuentra una cuenta con ese correo en la web.</p>");
        }

        /* Fin de las comprobaciones (por ahora) */

        /*Suponiendo que el correo introducido existe en la base de datos */

        //generar una nueva contraseña
        $nuevaClave = bin2hex(random_bytes(8));

        //cambiar la contraseña y mandarla en una transacción:
        //si el correo no se puede enviar, la contraseña antigua se mantiene
        $conectar->begin_transaction();
        try {
            //actualizar la contraseña en la base de datos
            $salt = rand(-1000000, 1000000);
            $nuevoHashedPassword = hash('sha256', $nuevaClave . $salt);
            $sqlActualizarClave = "UPDATE Usuarios SET contrasena = ?, salt = ? WHERE email = ?;";
            $prepararConsulta = $conectar->prepare($sqlActualizarClave); // preparar la consulta
            $prepararConsulta->bind_param("sis", $nuevoHashedPassword, $salt, $correo); // blindar los parámetros
            $prepararConsulta->execute(); // ejecutar la consulta

            //configurar el correo con la nueva contraseña
            $mail = crearCorreo();
            $mail->addAddress($correo); // A: el correo del usuario

            // Contenido del correo
            $correoHtml = htmlspecialchars($correo);
            $mail->isHTML(true);
            $mail->Subject = "Clave nueva generada.";
            $mail->Body = "<p>¡Hola $correoHtml! Se ha recibido una solicitud para cambiar tu clave. ¡No te preocupes!</p>
            <p>Tu nueva clave es: <strong>$nuevaClave</strong></p>
            <p>Puedes cambiarla siempre que quieras desde el apartado de modificar tu cuenta</p>";

            // Enviar el correo y, si ha ido bien, guardar la nueva contraseña
            $mail->send();
            $conectar->commit();
            $conectar->close();

            redirigir('../index.html');
        } catch (mysqli_sql_exception $e) {
            $conectar->rollback();
            $conectar->close();
            echo "<a href='../html/login.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>Error cambiando la contraseña: " . $e->getMessage() . "</p>");
        } catch (Exception $e) {
            $conectar->rollback();
            $conectar->close();
            echo "<a href='../html/login.html'>Volver atrás</a><br/><br/>";
            die("<p class='error'>Error al enviar la nueva contraseña (tu contraseña no ha cambiado): " . htmlspecialchars($e->getMessage()) . "</p>");
        }
    }
    ?>
</body>

</html>
