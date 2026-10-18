<?php
// borrarUsuario.php
// Borrado de cuenta en 3 pasos:
//   1) pedir la contraseña
//   2) primera confirmación
//   3) confirmación final avisando de que es irreversible
// El paso superado se guarda en la sesión (y caduca), así que no se puede
// saltar ningún paso enviando el formulario a mano.

//depuración
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

//traer el archivo de conexión
include_once "./conectar.php";

//comprobar que existe una sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nombre_usuario'])) {
    header("Location: ../html/login.html");
    exit();
}

//tiempo para completar los pasos, en segundos
const TIEMPO_BORRADO = 300;

//devuelve el último paso superado (0 si ninguno o si ha caducado)
function pasoSuperado() {
    $estado = $_SESSION['borrarCuenta'] ?? null;

    if (!$estado || $estado['expira'] < time()) {
        unset($_SESSION['borrarCuenta']);
        return 0;
    }

    return $estado['paso'];
}

//guardar en la sesión el paso superado
function marcarPaso($paso) {
    $_SESSION['borrarCuenta'] = ['paso' => $paso, 'expira' => time() + TIEMPO_BORRADO];
}

//comprobar la contraseña del usuario
function claveCorrecta($conectar, $nombreUsuario, $clave) {
    $consulta = "SELECT contrasena, salt FROM Usuarios WHERE nombre_usuario = ?;";
    $prepararConsulta = $conectar->prepare($consulta);
    $prepararConsulta->bind_param("s", $nombreUsuario);
    $prepararConsulta->execute();
    $usuario = $prepararConsulta->get_result()->fetch_assoc();

    if (!$usuario) {
        return false;
    }

    $hashedPassword = hash('sha256', $clave . $usuario['salt']);
    return hash_equals($usuario['contrasena'], $hashedPassword);
}

//contar los juegos del usuario (para avisar de lo que se va a perder)
function contarJuegos($conectar, $nombreUsuario) {
    $consulta = "SELECT COUNT(*) AS total FROM Anade a INNER JOIN Usuarios u ON a.id_usuario = u.id_usuario WHERE u.nombre_usuario = ?;";
    $prepararConsulta = $conectar->prepare($consulta);
    $prepararConsulta->bind_param("s", $nombreUsuario);
    $prepararConsulta->execute();
    return (int) $prepararConsulta->get_result()->fetch_assoc()['total'];
}

//borrar el usuario, sus juegos y sus pósters
function borrarCuenta($conectar, $nombreUsuario) {
    //obtener el id del usuario
    $consultaObtenerIdUsuario = "SELECT id_usuario FROM Usuarios WHERE nombre_usuario = ?;";
    $prepararConsultaUsuario = $conectar->prepare($consultaObtenerIdUsuario);
    $prepararConsultaUsuario->bind_param("s", $nombreUsuario);
    $prepararConsultaUsuario->execute();
    $idUsuario = $prepararConsultaUsuario->get_result()->fetch_assoc()['id_usuario'];

    //obtener los juegos del usuario y sus pósters
    $consultaJuegos = "SELECT j.id, j.poster FROM Juegos j INNER JOIN Anade a ON j.id = a.id_juego WHERE a.id_usuario = ?;";
    $stmtJuegos = $conectar->prepare($consultaJuegos);
    $stmtJuegos->bind_param("i", $idUsuario);
    $stmtJuegos->execute();
    $juegos = $stmtJuegos->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtJuegos->close();

    //borrar todo en una transacción: o se borra todo o no se borra nada
    $conectar->begin_transaction();
    try {
        //eliminar los registros de la tabla 'Anade' asociados con el usuario
        $stmt = $conectar->prepare("DELETE FROM Anade WHERE id_usuario = ?;");
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $stmt->close();

        //eliminar los juegos del usuario
        $stmt = $conectar->prepare("DELETE FROM Juegos WHERE id = ?;");
        foreach ($juegos as $juego) {
            $stmt->bind_param("i", $juego['id']);
            $stmt->execute();
        }
        $stmt->close();

        //eliminar el usuario
        $stmt = $conectar->prepare("DELETE FROM Usuarios WHERE id_usuario = ?;");
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $stmt->close();

        $conectar->commit();
    } catch (mysqli_sql_exception $e) {
        $conectar->rollback();
        throw $e;
    }

    //eliminar los archivos de los pósters (solo cuando la BD ya está borrada)
    foreach ($juegos as $juego) {
        $rutaPoster = "../img/avatares_juegos/" . basename($juego['poster']);
        if (is_file($rutaPoster)) {
            unlink($rutaPoster);
        }
    }
}

$nombreUsuario = $_SESSION['nombre_usuario'];
$conectar = getConexion();

$pasoMostrar = 1;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $paso = (int) ($_POST['paso'] ?? 0);

    try {
        if ($paso === 1) {
            //paso 1: comprobar la contraseña
            if (claveCorrecta($conectar, $nombreUsuario, $_POST['clave'] ?? '')) {
                marcarPaso(1);
                $pasoMostrar = 2;
            } else {
                unset($_SESSION['borrarCuenta']);
                $error = "La contraseña no es correcta.";
            }
        } elseif ($paso === 2 && pasoSuperado() === 1) {
            //paso 2: primera confirmación
            marcarPaso(2);
            $pasoMostrar = 3;
        } elseif ($paso === 3 && pasoSuperado() === 2) {
            //paso 3: confirmación final -> borrar
            borrarCuenta($conectar, $nombreUsuario);
            $conectar->close();

            //cerrar la sesión del usuario borrado
            $_SESSION = [];
            session_destroy();

            redirigir('../index.html');
        } else {
            //paso saltado o caducado: volver a empezar
            unset($_SESSION['borrarCuenta']);
            $error = "La confirmación ha caducado o no es válida. Vuelve a empezar.";
        }
    } catch (mysqli_sql_exception $e) {
        unset($_SESSION['borrarCuenta']);
        $pasoMostrar = 1;
        $error = "Error borrando la cuenta: " . $e->getMessage();
    }
} else {
    //al entrar en la página siempre se empieza desde el principio
    unset($_SESSION['borrarCuenta']);
}

$totalJuegos = $pasoMostrar > 1 ? contarJuegos($conectar, $nombreUsuario) : 0;
$conectar->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/borrarUsuario.css">

    <link rel="icon" href="../img/iconos/icons8-modify-16.png" sizes="any" />
    <link rel="apple-touch-icon" href="../img/iconos/icons8-modify-16.png" />

    <title>Borrar cuenta</title>
</head>

<body>
    <h1>Borrar cuenta</h1>

    <main>
        <p class="pasos">Paso <?php echo $pasoMostrar; ?> de 3</p>

        <?php if ($error !== null) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>

        <?php if ($pasoMostrar === 1) { ?>
            <!--Paso 1: contraseña-->
            <form action="./borrarUsuario.php" method="post">
                <input type="hidden" name="paso" value="1" />

                <p>Para borrar la cuenta <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong>, primero confirma que eres tú.</p>

                <label for="clave">Tu contraseña: </label>
                <input type="password" name="clave" id="clave" placeholder="Escribe tu contraseña" required autofocus />

                <button type="submit">Continuar</button>

                <section>
                    <a href="./principal.php">Cancelar</a>
                </section>
            </form>

        <?php } elseif ($pasoMostrar === 2) { ?>
            <!--Paso 2: primera confirmación-->
            <form action="./borrarUsuario.php" method="post">
                <input type="hidden" name="paso" value="2" />

                <p>¿Seguro que quieres borrar tu cuenta <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong>?</p>
                <p>Se borrarán también <strong><?php echo $totalJuegos; ?> <?php echo $totalJuegos === 1 ? 'juego' : 'juegos'; ?></strong> de tu lista y sus portadas.</p>

                <button type="submit">Sí, quiero borrar mi cuenta</button>

                <section>
                    <a href="./principal.php">Cancelar</a>
                </section>
            </form>

        <?php } else { ?>
            <!--Paso 3: confirmación final-->
            <form action="./borrarUsuario.php" method="post">
                <input type="hidden" name="paso" value="3" />

                <div class="aviso-irreversible">
                    <p><strong>Esta acción es irreversible.</strong></p>
                    <p>Tu cuenta, tus <?php echo $totalJuegos; ?> <?php echo $totalJuegos === 1 ? 'juego' : 'juegos'; ?> y sus portadas se borrarán para siempre y no se podrán recuperar.</p>
                </div>

                <button type="submit" class="peligro">Borrar mi cuenta definitivamente</button>

                <section>
                    <a href="./principal.php">Cancelar y volver a mi lista</a>
                </section>
            </form>
        <?php } ?>
    </main>

    <footer>
        &copy; 2025 Creada por <a href="https://github.com/aloxvim/juegospendientes" target="_blank">@aloxvim</a>.
    </footer>
</body>

</html>
