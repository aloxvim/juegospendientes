<?php
// autoinstall.php
// Pantalla de instalación: crea la base de datos y sus tablas.
// Solo se puede usar mientras la base de datos no esté instalada;
// si ya lo está, siempre devuelve al inicio.

include_once "./instalacion.php";

$estado = estadoInstalacion();

//si ya está instalada, esta pantalla no hace nada
if ($estado === 'instalada') {
    redirigir('../index.html');
}

$errorInstalacion = null;

//el usuario ha aceptado: instalar
if ($estado === 'sin_bd' && $_SERVER['REQUEST_METHOD'] == "POST") {
    $errorInstalacion = instalarBaseDatos();

    //si todo ha ido bien, salir de la pantalla
    if ($errorInstalacion === null) {
        redirigir('../index.html');
    }
}

$config = cargarConfig();
$nombreBaseDatos = htmlspecialchars($config['nombreBaseDatos'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../css/autoinstall.css">

    <link rel="icon" href="../img/iconos/icons8-gamepad-16.png" sizes="any" />
    <link rel="apple-touch-icon" href="../img/iconos/icons8-gamepad-16.png" />

    <title>Instalación</title>
</head>

<body>
    <h1>Instalación</h1>

    <main>
        <p class="nombre-app">JuegosPendientes</p>

        <?php if ($estado === 'sin_config') { ?>
            <!--No se puede instalar sin configuración-->
            <p class="error"><?php echo MENSAJE_SIN_CONFIG; ?></p>
            <a href="./autoinstall.php" class="boton">Volver a comprobar</a>

        <?php } elseif ($estado === 'sin_servidor') { ?>
            <!--No se puede instalar si el servidor MySQL no responde-->
            <p class="error"><?php echo MENSAJE_SIN_SERVIDOR; ?></p>
            <a href="./autoinstall.php" class="boton">Volver a comprobar</a>

        <?php } else { ?>
            <!--Confirmación de la instalación-->
            <?php if ($errorInstalacion !== null) { ?>
                <p class="error"><?php echo htmlspecialchars($errorInstalacion); ?></p>
            <?php } ?>

            <p>
                No se ha encontrado la base de datos <strong><?php echo $nombreBaseDatos; ?></strong>
                (o le faltan tablas).
            </p>
            <p>
                Se va a crear la base de datos con sus tablas y relaciones usando los datos de
                <code>sql/config.php</code>. Si alguna tabla ya existe no se modificará.
            </p>
            <p>¿Quieres continuar?</p>

            <form action="./autoinstall.php" method="post">
                <button type="submit">Instalar</button>

                <section>
                    <a href="../index.html">Cancelar</a>
                </section>
            </form>
        <?php } ?>
    </main>

    <footer>
        &copy; 2025 Creada por <a href="https://github.com/aloxvim/juegospendientes" target="_blank">@aloxvim</a>.
    </footer>
</body>

</html>
