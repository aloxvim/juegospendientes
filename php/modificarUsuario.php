<?php
// modificarUsuario.php
// Página "Mi cuenta": a la izquierda los datos del usuario y a la derecha
// tres tarjetas independientes (nombre de usuario, correo y contraseña).
// Cada tarjeta envía su propio formulario y pide la contraseña actual.
// Tras procesarlo se redirige a esta misma página y el resultado se muestra
// en la tarjeta correspondiente (guardado en $_SESSION['mensajeCuenta']).

//depuración
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

//traer el archivo de conexión
include_once "./conectar.php";

//funciones comunes de la cuenta (claveCorrecta)
include_once "./usuarios.php";

//comprobar que existe una sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nombre_usuario'])) {
    header("Location: ../html/login.html");
    exit();
}

//establecer la conexión
$conectar = getConexion();

//obtener los datos del usuario de la sesión
function obtenerUsuario($conectar, $nombreUsuario) {
    $consulta = "SELECT id_usuario, nombre_usuario, email FROM Usuarios WHERE nombre_usuario = ?;";
    $prepararConsulta = $conectar->prepare($consulta);
    $prepararConsulta->bind_param("s", $nombreUsuario);
    $prepararConsulta->execute();
    return $prepararConsulta->get_result()->fetch_assoc();
}

//comprobar si un valor ya lo usa otro usuario (columna: 'nombre_usuario' o 'email')
function valorEnUso($conectar, $columna, $valor) {
    $consulta = $columna === 'email'
        ? "SELECT 1 FROM Usuarios WHERE email = ?;"
        : "SELECT 1 FROM Usuarios WHERE nombre_usuario = ?;";
    $prepararConsulta = $conectar->prepare($consulta);
    $prepararConsulta->bind_param("s", $valor);
    $prepararConsulta->execute();
    return $prepararConsulta->get_result()->num_rows > 0;
}

//procesar el formulario de una tarjeta; devuelve el mensaje a mostrar
function procesarTarjeta($conectar, $usuario, $tarjeta) {
    //las tres tarjetas piden la contraseña actual
    if (!claveCorrecta($conectar, $usuario['nombre_usuario'], $_POST['claveActual'] ?? '')) {
        return ['error', 'La contraseña actual no es correcta.'];
    }

    if ($tarjeta === 'nombre') {
        //caso del nombre de usuario
        $nuevoNombre = trim($_POST['nuevoNombre'] ?? '');

        if ($nuevoNombre === '') {
            return ['error', 'Escribe el nuevo nombre de usuario.'];
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $nuevoNombre)) {
            return ['error', 'El nombre de usuario solo puede contener letras y números.'];
        }
        if ($nuevoNombre === $usuario['nombre_usuario']) {
            return ['error', 'Ese ya es tu nombre de usuario.'];
        }
        if (valorEnUso($conectar, 'nombre_usuario', $nuevoNombre)) {
            return ['error', 'El nombre de usuario ya existe.'];
        }

        $sqlActualizarNombre = "UPDATE Usuarios SET nombre_usuario = ? WHERE id_usuario = ?;";
        $prepararConsulta = $conectar->prepare($sqlActualizarNombre);
        $prepararConsulta->bind_param("si", $nuevoNombre, $usuario['id_usuario']);
        $prepararConsulta->execute();

        //la sesión se identifica por el nombre: hay que actualizarla también
        $_SESSION['nombre_usuario'] = $nuevoNombre;

        return ['ok', 'Nombre de usuario cambiado.'];
    }

    if ($tarjeta === 'correo') {
        //caso del correo electrónico
        $nuevoCorreo = trim($_POST['nuevoCorreo'] ?? '');

        if (!filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) {
            return ['error', 'El correo electrónico no es válido.'];
        }
        if ($nuevoCorreo === $usuario['email']) {
            return ['error', 'Ese ya es tu correo.'];
        }
        if (valorEnUso($conectar, 'email', $nuevoCorreo)) {
            return ['error', 'El correo introducido ya existe.'];
        }

        $sqlActualizarCorreo = "UPDATE Usuarios SET email = ? WHERE id_usuario = ?;";
        $prepararConsulta = $conectar->prepare($sqlActualizarCorreo);
        $prepararConsulta->bind_param("si", $nuevoCorreo, $usuario['id_usuario']);
        $prepararConsulta->execute();

        return ['ok', 'Correo cambiado.'];
    }

    if ($tarjeta === 'clave') {
        //caso de la contraseña
        $nuevaClave = $_POST['nuevaClave'] ?? '';
        $nuevaClaveComprobar = $_POST['nuevaClaveComprobar'] ?? '';

        if ($nuevaClave === '') {
            return ['error', 'Escribe la nueva contraseña.'];
        }
        if ($nuevaClave !== $nuevaClaveComprobar) {
            return ['error', 'Las nuevas contraseñas no coinciden.'];
        }

        $salt = rand(-1000000, 1000000);
        $nuevoHashedPassword = hash('sha256', $nuevaClave . $salt);
        $sqlActualizarClave = "UPDATE Usuarios SET contrasena = ?, salt = ? WHERE id_usuario = ?;";
        $prepararConsulta = $conectar->prepare($sqlActualizarClave);
        $prepararConsulta->bind_param("sii", $nuevoHashedPassword, $salt, $usuario['id_usuario']);
        $prepararConsulta->execute();

        return ['ok', 'Contraseña cambiada.'];
    }

    return ['error', 'Formulario no válido.'];
}

$usuario = obtenerUsuario($conectar, $_SESSION['nombre_usuario']);

//si el usuario de la sesión ya no existe, cerrar la sesión
if (!$usuario) {
    $_SESSION = [];
    session_destroy();
    header("Location: ../html/login.html");
    exit();
}

//se ha enviado una de las tarjetas
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $tarjeta = $_POST['tarjeta'] ?? '';

    try {
        [$tipo, $texto] = procesarTarjeta($conectar, $usuario, $tarjeta);
    } catch (mysqli_sql_exception $e) {
        [$tipo, $texto] = ['error', 'Error guardando los cambios: ' . $e->getMessage()];
    }

    $_SESSION['mensajeCuenta'] = ['tarjeta' => $tarjeta, 'tipo' => $tipo, 'texto' => $texto];
    $conectar->close();

    //redirigir para que recargar la página no reenvíe el formulario
    header("Location: ./modificarUsuario.php");
    exit();
}

$conectar->close();

//mensaje pendiente de mostrar (solo se muestra una vez)
$mensajeCuenta = $_SESSION['mensajeCuenta'] ?? null;
unset($_SESSION['mensajeCuenta']);

//pintar el mensaje si corresponde a esta tarjeta
function mostrarMensaje($mensajeCuenta, $tarjeta) {
    if ($mensajeCuenta && $mensajeCuenta['tarjeta'] === $tarjeta) {
        $clase = $mensajeCuenta['tipo'] === 'ok' ? 'exito' : 'error';
        echo "<p class='$clase'>" . htmlspecialchars($mensajeCuenta['texto']) . "</p>";
    }
}

$nombreUsuario = htmlspecialchars($usuario['nombre_usuario']);
$correoUsuario = htmlspecialchars($usuario['email']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/modificar.css" />

    <link rel="icon" href="../img/iconos/icons8-modify-16.png" sizes="any" />
    <link rel="icon" href="../img/iconos/icons8-modify-16.png" type="image/svg+xml" />
    <link rel="apple-touch-icon" href="../img/iconos/icons8-modify-16.png" />

    <title>Mi cuenta</title>
</head>

<body>
    <h1>Mi cuenta</h1>

    <main>
        <!--Izquierda: datos del usuario-->
        <aside class="perfil">
            <p class="perfil-etiqueta">Sesión iniciada como</p>
            <p class="perfil-nombre"><?php echo $nombreUsuario; ?></p>
            <p class="perfil-correo"><?php echo $correoUsuario; ?></p>
        </aside>

        <!--Derecha: tarjetas para cambiar cada dato-->
        <div class="tarjetas">

            <!--Tarjeta: nombre de usuario-->
            <article class="tarjeta">
                <h2>Nombre de usuario</h2>
                <?php mostrarMensaje($mensajeCuenta, 'nombre'); ?>
                <form action="./modificarUsuario.php" method="post">
                    <input type="hidden" name="tarjeta" value="nombre" />

                    <label for="nuevoNombre">Nuevo nombre de usuario: </label>
                    <input type="text" name="nuevoNombre" id="nuevoNombre" placeholder="<?php echo $nombreUsuario; ?>" pattern="[a-zA-Z0-9]+" title="Solo letras y números" required />

                    <label for="claveNombre">Contraseña actual: </label>
                    <input type="password" name="claveActual" id="claveNombre" placeholder="Escribe tu contraseña actual" required />

                    <button type="submit">Cambiar nombre</button>
                </form>
            </article>

            <!--Tarjeta: correo electrónico-->
            <article class="tarjeta">
                <h2>Correo electrónico</h2>
                <?php mostrarMensaje($mensajeCuenta, 'correo'); ?>
                <form action="./modificarUsuario.php" method="post">
                    <input type="hidden" name="tarjeta" value="correo" />

                    <label for="nuevoCorreo">Nuevo correo: </label>
                    <input type="email" name="nuevoCorreo" id="nuevoCorreo" placeholder="<?php echo $correoUsuario; ?>" required />

                    <label for="claveCorreo">Contraseña actual: </label>
                    <input type="password" name="claveActual" id="claveCorreo" placeholder="Escribe tu contraseña actual" required />

                    <button type="submit">Cambiar correo</button>
                </form>
            </article>

            <!--Tarjeta: contraseña-->
            <article class="tarjeta">
                <h2>Contraseña</h2>
                <?php mostrarMensaje($mensajeCuenta, 'clave'); ?>
                <form action="./modificarUsuario.php" method="post">
                    <input type="hidden" name="tarjeta" value="clave" />

                    <label for="claveClave">Contraseña actual: </label>
                    <input type="password" name="claveActual" id="claveClave" placeholder="Escribe tu contraseña actual" required />

                    <label for="nuevaClave">Nueva contraseña: </label>
                    <input type="password" name="nuevaClave" id="nuevaClave" placeholder="Escribe tu nueva contraseña" required />

                    <label for="nuevaClaveComprobar">Repite la nueva contraseña: </label>
                    <input type="password" name="nuevaClaveComprobar" id="nuevaClaveComprobar" placeholder="Vuelve a escribirla" required />

                    <button type="submit">Cambiar contraseña</button>
                </form>
            </article>

        </div>

        <!--Al fondo: volver a la lista-->
        <a href="./principal.php" class="volver">Volver a mi lista</a>
    </main>

    <footer>
        &copy; 2025 Creada por <a href="https://github.com/aloxvim/juegospendientes" target="_blank">@aloxvim</a>.
    </footer>
</body>

</html>
