<?php
//depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//traer el archivo de conexión
include_once "./conectar.php";

//establecer la conexión
$conectar = getConexion();

//comprobar que se puede establecer la conexión
if (!$conectar) {
    die("<p class='error'>Error en la conexión a la base de datos</p>");
}

//comprobar que existe una sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nombre_usuario'])) {
    header("Location: ../html/login.html");
    exit();
}

?>

<!--Código HTML -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/modificar.css" />

    <link rel="icon" href="../img/iconos/icons8-modify-16.png" sizes="any" />
    <link rel="icon" href="../img/iconos/icons8-modify-16.png" type="image/svg+xml" />
    <link rel="apple-touch-icon" href="../img/iconos/icons8-modify-16.png" />

    <title>Modificar mis datos</title>
</head>

<body>
    <h2>Cambiar mis datos</h2>

    <!-- Formulario -->
    <main>
        <form action="" method="post">
            <!--Campo para modificar el nombre de usuario -->
            <label for="newUsername">Nuevo nombre de usuario <strong>(opcional)</strong>: </label>
            <input type="text" name="newUsername" placeholder="Escribe tu nuevo nombre de usuario" /> <br /> <br />

            <!--Campo para modificar el correo del usuario -->
            <label for="newEmail">Nuevo correo <strong>(opcional)</strong>: </label>
            <input type="email" name="newEmail" placeholder="Escribe otra dirección de correo" /> <br /> <br />

            <!--Campo para modificar la contraseña -->
            <label for="oldPassword">Tu actual contraseña: </label>
            <input type="password" name="oldPassword" placeholder="Obligatorio SOLO si vas a cambiar algo" /> <br />
            <label for="newPassword">Nueva contraseña <strong>(opcional)</strong>: </label>
            <input type="password" name="newPassword" placeholder="Escribe tu nueva contraseña" /> <br />
            <label for="newPasswordCheck">Repite la nueva contraseña: </label>
            <input type="password" name="newPasswordCheck" /> <br />

            <!--Botón para enviar el formulario-->
            <button type="submit">Modificar datos</button>

            <!--Enlace al inico de sesión-->
            <section>
                <a href="./principal.php">Volver atrás</a>
            </section>
        </form>
    </main>

    <footer>
        &copy; 2025 Creada por <a href="https://github.com/aloxvim/juegospendientes" target="_blank">@aloxvim</a>.
    </footer>
</body>

</html>

<?php

//obtener el nombre de usuario desde la URL
$nombre_usuario = $_GET['nombre_usuario'] ?? null;

//comprobar que el nombre del usuario es el de la sesión
if ($nombre_usuario === $_SESSION['nombre_usuario']) {

    if ($_SERVER['REQUEST_METHOD'] == "POST") {

        //valores del formulario
        $nuevoNombreUsuario = $_POST['newUsername'];
        $nuevoCorreoUsuario = $_POST['newEmail'];
        $claveActual = $_POST['oldPassword'];
        $nuevaClave = $_POST['newPassword'];
        $nuevaClaveComprobar = $_POST['newPasswordCheck'];

        //obtener el id del usuario
        $consultaObtenerIdUsuario = "SELECT id_usuario, nombre_usuario, email FROM Usuarios WHERE nombre_usuario = ?;";
        $prepararConsultaUsuario = $conectar->prepare($consultaObtenerIdUsuario);
        $prepararConsultaUsuario->bind_param("s", $_SESSION['nombre_usuario']);
        $prepararConsultaUsuario->execute();
        $resultado = $prepararConsultaUsuario->get_result();
        $usuario = $resultado->fetch_assoc();
        $idUsuario = $usuario['id_usuario'];
        $nombreUsuarioAntiguo = $usuario['nombre_usuario'];
        $correoAntiguoUsuario = $usuario['email'];

        /* Lo voy a hacer caso por caso porque vaya lío */

        //caso del correo (ahora con verificaciones)
        if (empty($claveActual)) {
            die("<p class='error'>No puedes modificar tu correo si no introduces tu contraseña actual.</p>");
        } else {
            //obtener la antigua contraseña
            $sqlObtenerClaveAntigua = "SELECT contrasena, salt FROM Usuarios WHERE id_usuario = ?;";
            $prepararConsulta = $conectar->prepare($sqlObtenerClaveAntigua); // preparar la consulta
            $prepararConsulta->bind_param("i", $idUsuario); // blindar los parámetros
            $prepararConsulta->execute(); // ejecutar la consulta

            //obtener el resultado
            $resultado = $prepararConsulta->get_result();
            $usuario = $resultado->fetch_assoc();

            // comprobar la contraseña
            $hashedPassword = hash('sha256', $claveActual . $usuario['salt']);

            if ($hashedPassword === $usuario['contrasena']) {
                //caso del correo electrónico
                if (!empty($nuevoCorreoUsuario)) {
                    try {
                        //comprobar que el correo electrónico no se repite
                        $consultaBuscaCorreo = "SELECT email FROM Usuarios WHERE email = ?;";
                        $prepararConsulta = $conectar->prepare($consultaBuscaCorreo); //preparar la consulta
                        $prepararConsulta->bind_param("s", $nuevoCorreoUsuario); //blindar el parámetro
                        $prepararConsulta->execute(); //ejecutar la consulta
                        $resultado = $prepararConsulta->get_result(); //obtener el resultado
                        if ($resultado->num_rows > 0) {
                            die("<p class='error'>El correo introducido ya existe.</p>");
                        } else {
                            //actualizar el correo
                            $sqlActualizarCorreo = "UPDATE Usuarios SET email = ? WHERE id_usuario = ?;";
                            $prepararConsulta = $conectar->prepare($sqlActualizarCorreo); //preparar la consulta
                            $prepararConsulta->bind_param("si", $nuevoCorreoUsuario, $idUsuario); //blindar los parámetros
                            $prepararConsulta->execute(); //ejecutar la consulta
                        }


                    } catch (mysqli_sql_exception $e) {
                        //cerrar la conexión
                        $conectar->close();
                        die("<p class='error'>Error actualizando el correo electrónico: " . $e->getMessage() . "</p>");
                    }
                }
            } else {
                die("<p class='error'>Has introducido una contraseña incorrecta.</p>");
            }


        }




        //caso de la contraseña, este va a ser divertido
        if (!empty($nuevaClave) && !empty($nuevaClaveComprobar)) {
            //obtener la antigua contraseña
            $sqlObtenerClaveAntigua = "SELECT contrasena, salt FROM Usuarios WHERE id_usuario = ?;";
            $prepararConsulta = $conectar->prepare($sqlObtenerClaveAntigua); // preparar la consulta
            $prepararConsulta->bind_param("i", $idUsuario); // blindar los parámetros
            $prepararConsulta->execute(); // ejecutar la consulta

            //obtener el resultado
            $resultado = $prepararConsulta->get_result();
            $usuario = $resultado->fetch_assoc();

            // comprobar si el usuario existe
            if ($usuario) {
                // comprobar la contraseña
                $hashedPassword = hash('sha256', $claveActual . $usuario['salt']);

                if ($hashedPassword === $usuario['contrasena']) {
                    if ($nuevaClave === $nuevaClaveComprobar) {
                        // Consulta para actualizar la contraseña
                        $salt = rand(-1000000, 1000000);
                        $nuevoHashedPassword = hash('sha256', $nuevaClave . $salt);
                        $sqlActualizarClave = "UPDATE Usuarios SET contrasena = ?, salt = ? WHERE id_usuario = ?;";
                        $prepararConsulta = $conectar->prepare($sqlActualizarClave); // preparar la consulta
                        $prepararConsulta->bind_param("sii", $nuevoHashedPassword, $salt, $idUsuario); // blindar los parámetros
                        $prepararConsulta->execute(); // ejecutar la consulta
                    } else {
                        die("<p class='error'>Las nuevas contraseñas no coinciden</p>");
                    }
                } else {
                    die("<p class='error'>Has introducido mal tu antigua contraseña.</p>");
                }
            } else {
                die("<p class='error'>El usuario no existe o ha ocurrido un error.</p>");
            }

        }

        //caso del nombre de usuario (ahora con verificaciones)
        if (empty($claveActual)) {
            die("<p class='error'>No puedes modificar tu correo si no introduces tu contraseña actual.</p>");
        } else {
            //obtener la antigua contraseña
            $sqlObtenerClaveAntigua = "SELECT contrasena, salt FROM Usuarios WHERE id_usuario = ?;";
            $prepararConsulta = $conectar->prepare($sqlObtenerClaveAntigua); // preparar la consulta
            $prepararConsulta->bind_param("i", $idUsuario); // blindar los parámetros
            $prepararConsulta->execute(); // ejecutar la consulta

            //obtener el resultado
            $resultado = $prepararConsulta->get_result();
            $usuario = $resultado->fetch_assoc();

            // comprobar la contraseña
            $hashedPassword = hash('sha256', $claveActual . $usuario['salt']);

            if ($hashedPassword === $usuario['contrasena']) {
                //caso del nombre de usuario
                if (!empty($nuevoNombreUsuario)) {
                    try {
                        //comprobar que el nombre de usuario no se repite
                        $consultaBuscaUsuario = "SELECT nombre_usuario FROM Usuarios WHERE nombre_usuario = ?";
                        $prepararConsulta = $conectar->prepare($consultaBuscaUsuario); //preparar la consulta
                        $prepararConsulta->bind_param("s", $nuevoNombreUsuario); //blindar el parámetro
                        $prepararConsulta->execute(); //ejecutar la consulta
                        $resultado = $prepararConsulta->get_result(); //obtener el resultado
                        if ($resultado->num_rows > 0) {
                            die("<p class='error'>El nombre de usuario ya existe.</p>");
                        } else {
                            //actualizar el nombre de usuario
                            $sqlActualizarNombre = "UPDATE Usuarios SET nombre_usuario = ? WHERE id_usuario = ?;";
                            $prepararConsulta = $conectar->prepare($sqlActualizarNombre); //preparar la consulta
                            $prepararConsulta->bind_param("si", $nuevoNombreUsuario, $idUsuario); //blindar los parámetros
                            $prepararConsulta->execute(); //ejecutar la consulta
                        }
                    } catch (mysqli_sql_exception $e) {
                        //cerrar la conexión
                        $conectar->close();
                        die("<p class='error'>Error actualizando el nombre de usuario: " . $e->getMessage() . "</p>");
                    }
                }
            } else {
                die("<p class='error'>Has introducido una contraseña incorrecta.</p>");
            }




        }





        $conectar->close();
        header("Location: ./principal.php");
    }

} else {
    //redirigir si el nombre colocado en la url no es el de la sesión
    header("Location: https://www.youtube.com/watch?v=dQw4w9WgXcQ");
    exit();
}
?>
