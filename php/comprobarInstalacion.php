<?php
// comprobarInstalacion.php
// Devuelve a js/comprobarInstalacion.js el estado de la instalación
// (index.html es estático y no puede comprobarlo por sí mismo)

header('Content-Type: application/json');

include_once "./instalacion.php";

$estado = estadoInstalacion();

$mensajes = [
    'sin_config' => MENSAJE_SIN_CONFIG,
    'sin_servidor' => MENSAJE_SIN_SERVIDOR,
];

echo json_encode(['estado' => $estado, 'mensaje' => $mensajes[$estado] ?? null]);
?>
