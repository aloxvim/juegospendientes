<?php
// obtenerClaveApi.php
// Devuelve a js/addGame.js la clave de la API de RAWG guardada en sql/config.php
// (el JS no puede leer el archivo PHP directamente)

header('Content-Type: application/json');

//comprobar que existe una sesión: solo los usuarios logueados pueden añadir juegos
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['nombre_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

//traer las funciones para leer sql/config.php
include_once "./instalacion.php";

//comprobar que existe el archivo de configuración
$config = cargarConfig();
if ($config === null) {
    http_response_code(500);
    echo json_encode(['error' => 'No se encuentra el archivo de configuración']);
    exit();
}

echo json_encode(['claveApiRawg' => $config['claveApiRawg']]);
?>
