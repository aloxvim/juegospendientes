// Comprobar al abrir la portada si la aplicación está configurada e instalada
// (los textos de los avisos vienen de php/instalacion.php)

const avisoInstalacion = document.getElementById('avisoInstalacion');

fetch('./php/comprobarInstalacion.php')
    .then(response => response.json())
    .then(({ estado, mensaje }) => {
        if (estado === 'sin_bd') {
            // falta la base de datos: ir a la pantalla de instalación
            location.replace('./php/autoinstall.php');
        } else if (mensaje) {
            // falta la configuración o el servidor no responde
            avisoInstalacion.innerHTML = mensaje;
            avisoInstalacion.hidden = false;
        }
    })
    .catch(error => {
        // sin PHP (por ejemplo abriendo el HTML directamente) no se puede comprobar
        console.error('No se pudo comprobar la instalación:', error);
    });
