<?php
// instalacion.php
// Funciones para comprobar si la aplicación está configurada e instalada.
// La usan conectar.php, autoinstall.php y comprobarInstalacion.php.
//
// Estados posibles de estadoInstalacion():
//   'sin_config'   -> falta sql/config.php o tiene campos obligatorios vacíos
//   'sin_servidor' -> config rellena pero no se puede conectar al servidor MySQL
//   'sin_bd'       -> el servidor responde pero falta la base de datos o alguna tabla
//   'instalada'    -> todo listo

//que mysqli lance excepciones en cualquier versión de PHP
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//tablas que tiene que tener la base de datos para considerarse instalada
const TABLAS_NECESARIAS = ['Usuarios', 'Juegos', 'Anade'];

//obtener los datos de sql/config.php (null si el archivo no existe)
function cargarConfig() {
    $configPath = __DIR__ . '/../sql/config.php';
    if (!file_exists($configPath)) {
        return null;
    }

    include $configPath;

    return [
        'host' => $host ?? '',
        'usuario' => $usuario ?? '',
        'password' => $password ?? '',
        'nombreBaseDatos' => $nombreBaseDatos ?? '',
        'puerto' => $puerto ?? 3306,
    ];
}

//comprobar que los campos obligatorios están rellenos (la contraseña puede estar vacía)
function configRellena($config) {
    return $config !== null
        && trim($config['host']) !== ''
        && trim($config['usuario']) !== ''
        && trim($config['nombreBaseDatos']) !== '';
}

//conectar al servidor MySQL sin seleccionar base de datos (lanza mysqli_sql_exception si falla)
function conectarServidor($config) {
    return new mysqli($config['host'], $config['usuario'], $config['password'], null, (int) $config['puerto']);
}

//averiguar en qué estado está la instalación
function estadoInstalacion() {
    $config = cargarConfig();
    if (!configRellena($config)) {
        return 'sin_config';
    }

    try {
        $conexion = conectarServidor($config);
    } catch (mysqli_sql_exception $e) {
        return 'sin_servidor';
    }

    try {
        //contar cuántas de las tablas necesarias existen en la base de datos
        $marcadores = implode(',', array_fill(0, count(TABLAS_NECESARIAS), '?'));
        $consulta = "SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ($marcadores);";
        $parametros = array_merge([$config['nombreBaseDatos']], TABLAS_NECESARIAS);

        $prepararConsulta = $conexion->prepare($consulta);
        $prepararConsulta->bind_param(str_repeat('s', count($parametros)), ...$parametros);
        $prepararConsulta->execute();
        $total = $prepararConsulta->get_result()->fetch_assoc()['total'];
        $conexion->close();

        return $total == count(TABLAS_NECESARIAS) ? 'instalada' : 'sin_bd';
    } catch (mysqli_sql_exception $e) {
        $conexion->close();
        return 'sin_servidor';
    }
}

//crear la base de datos y sus tablas; devuelve null si todo va bien o el mensaje de error
function instalarBaseDatos() {
    $config = cargarConfig();
    if (!configRellena($config)) {
        return 'El archivo sql/config.php no está relleno.';
    }

    //el nombre va dentro de la sentencia SQL, así que solo se permiten caracteres seguros
    $nombreBaseDatos = $config['nombreBaseDatos'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $nombreBaseDatos)) {
        return 'El nombre de la base de datos solo puede tener letras, números y guiones bajos.';
    }

    $esquemaPath = __DIR__ . '/../sql/esquema.sql';
    if (!file_exists($esquemaPath)) {
        return 'No se encuentra el archivo sql/esquema.sql.';
    }

    try {
        $conexion = conectarServidor($config);

        //crear la base de datos si no existe y usarla
        $conexion->query("CREATE DATABASE IF NOT EXISTS `$nombreBaseDatos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $conexion->select_db($nombreBaseDatos);

        //crear las tablas (multi_query ejecuta todas las sentencias del archivo)
        $conexion->multi_query(file_get_contents($esquemaPath));
        do {
            if ($resultado = $conexion->store_result()) {
                $resultado->free();
            }
        } while ($conexion->more_results() && $conexion->next_result());

        $conexion->close();
    } catch (mysqli_sql_exception $e) {
        return 'Error creando la base de datos: ' . $e->getMessage();
    }

    //comprobar que realmente ha quedado todo instalado
    if (estadoInstalacion() !== 'instalada') {
        return 'La instalación no se ha completado. Revisa los permisos del usuario de MySQL.';
    }

    return null;
}

//mostrar un mensaje de error de instalación y terminar
//(si la página ya ha empezado a pintarse solo se añade el mensaje)
function mostrarErrorInstalacion($mensaje) {
    if (!headers_sent()) {
        echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
            . "<link rel='stylesheet' type='text/css' href='../css/errores.css'>"
            . "<title>Error de configuración</title></head><body>";
    }
    echo "<a href='../index.html'>Volver al inicio</a>";
    echo "<p class='error'>$mensaje</p>";
    exit();
}

//redirigir a otra página aunque ya se haya enviado HTML
function redirigir($url) {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo "<script>location.replace(" . json_encode($url) . ");</script>";
        echo "<a href='" . htmlspecialchars($url) . "'>Continuar</a>";
    }
    exit();
}

//mensaje cuando falta rellenar sql/config.php
const MENSAJE_SIN_CONFIG = "<strong>Falta la configuración.</strong> Copia <code>sql/config.empty.php</code> como <code>sql/config.php</code> y rellena los datos de conexión a la base de datos.";

//mensaje cuando el servidor MySQL no responde
const MENSAJE_SIN_SERVIDOR = "<strong>No se puede conectar al servidor de base de datos.</strong> Comprueba que MySQL está en marcha y que el host, usuario, contraseña y puerto de <code>sql/config.php</code> son correctos.";
?>
