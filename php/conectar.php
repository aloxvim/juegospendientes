<?php

    //depuración para mostrar errores
    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

    //funciones para comprobar la configuración e instalación
    include_once __DIR__ . '/instalacion.php';

    function getConexion() {

        //comprobar que sql/config.php existe y está relleno
        $config = cargarConfig();
        if (!configRellena($config)) {
            mostrarErrorInstalacion(MENSAJE_SIN_CONFIG);
        }

        //intentar la conexión
        try {
            return new mysqli($config['host'], $config['usuario'], $config['password'], $config['nombreBaseDatos'], (int) $config['puerto']);
        } catch (mysqli_sql_exception $e) {
            //1049 = la base de datos no existe -> pantalla de instalación
            if ($e->getCode() == 1049) {
                redirigir('./autoinstall.php');
            }

            //cualquier otro error: servidor caído, credenciales incorrectas...
            mostrarErrorInstalacion(MENSAJE_SIN_SERVIDOR);
        }
    }

?>