<?php
// usuarios.php
// Funciones comunes sobre la cuenta del usuario.
// La usan borrarUsuario.php y modificarUsuario.php.

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
?>
