// Configuración de la API de RAWG
// La clave se guarda en sql/config.php ($claveApiRawg) y se pide al servidor
const RAWG_API_URL = 'https://api.rawg.io/api/games';

let timeoutBusqueda = null;
let juegoSeleccionado = null;
let promesaClaveApi = null;

// Función para obtener la clave de la API (solo se pide al servidor una vez)
function obtenerClaveApi() {
    if (!promesaClaveApi) {
        promesaClaveApi = fetch('../php/obtenerClaveApi.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('No se pudo obtener la clave de la API');
                }
                return response.json();
            })
            .then(data => data.claveApiRawg || '')
            .catch(error => {
                // permitir reintentar en la siguiente búsqueda
                promesaClaveApi = null;
                throw error;
            });
    }
    return promesaClaveApi;
}

// Elementos del DOM
const inputBusqueda = document.getElementById('busquedaJuego');
const resultadosDiv = document.getElementById('resultadosBusqueda');
const inputNombre = document.getElementById('nombreJuego');
const inputPuntaje = document.getElementById('puntajeMetacritic');
const inputDuracion = document.getElementById('duracionHoras');
const inputPoster = document.getElementById('poster');
const posterInfo = document.getElementById('posterInfo');

// Escuchar cambios en el campo de búsqueda
inputBusqueda.addEventListener('input', function () {
    const query = this.value.trim();

    // Limpiar el timeout anterior
    clearTimeout(timeoutBusqueda);

    if (query.length < 2) {
        resultadosDiv.style.display = 'none';
        return;
    }

    // Esperar 500ms antes de hacer la búsqueda
    timeoutBusqueda = setTimeout(() => {
        buscarJuegos(query);
    }, 500);
});

// Función para buscar juegos en RAWG
async function buscarJuegos(query) {
    resultadosDiv.innerHTML = '<div class="loading">Buscando...</div>';
    resultadosDiv.style.display = 'block';

    try {
        const claveApi = await obtenerClaveApi();

        if (!claveApi || claveApi === 'TU_API_KEY_AQUI') {
            resultadosDiv.innerHTML = '<div class="loading">Búsqueda no disponible: falta configurar la clave de la API de RAWG. Usa el modo manual.</div>';
            return;
        }

        const url = `${RAWG_API_URL}?key=${encodeURIComponent(claveApi)}&search=${encodeURIComponent(query)}&page_size=5`;
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error('Error en la búsqueda');
        }

        const data = await response.json();
        mostrarResultados(data.results);

    } catch (error) {
        console.error('Error:', error);
        resultadosDiv.innerHTML = '<div class="loading">Error al buscar. Intenta de nuevo.</div>';
    }
}

// Función para mostrar los resultados
function mostrarResultados(juegos) {
    if (!juegos || juegos.length === 0) {
        resultadosDiv.innerHTML = '<div class="loading">No se encontraron juegos.</div>';
        return;
    }

    resultadosDiv.innerHTML = '';

    juegos.forEach(juego => {
        const item = document.createElement('div');
        item.className = 'resultado-item';
        item.onclick = () => seleccionarJuego(juego);

        const img = juego.background_image ?
            `<img src="${juego.background_image}" alt="${juego.name}" />` :
            '<div class="sin-imagen"></div>';

        const rating = juego.rating ? (juego.rating * 20).toFixed(0) : 'N/A';
        const playtime = juego.playtime || 'N/A';
        const released = juego.released || 'N/A';

        item.innerHTML = `
                    ${img}
                    <div class="resultado-info">
                        <div class="resultado-nombre">${juego.name}</div>
                        <div class="resultado-detalles">
                            Rating: ${rating}/100 | Duración: ${playtime}h | Lanzamiento: ${released}
                        </div>
                    </div>
                `;

        resultadosDiv.appendChild(item);
    });
}

// Función para seleccionar un juego
async function seleccionarJuego(juego) {
    juegoSeleccionado = juego;

    // Llenar los campos del formulario
    inputNombre.value = juego.name;
    inputPuntaje.value = juego.rating ? Math.round(juego.rating * 20) : '';
    inputDuracion.value = juego.playtime || 1;

    // Descargar la imagen del juego
    if (juego.background_image) {
        posterInfo.textContent = 'Descargando imagen...';
        posterInfo.classList.remove('ok');
        try {
            await descargarImagen(juego.background_image, juego.name);
        } catch (error) {
            console.error('Error al descargar imagen:', error);
            posterInfo.textContent = 'No se pudo descargar la imagen automáticamente. Por favor, sube una manualmente.';
        }
    }

    // Ocultar resultados
    resultadosDiv.style.display = 'none';
    inputBusqueda.value = juego.name;
}

// Función para descargar y convertir la imagen usando PHP como proxy
async function descargarImagen(url, nombreJuego) {
    try {
        // Llamar al PHP que descarga la imagen
        const response = await fetch('../php/descargarImagenJuego.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                url: url,
                nombre: nombreJuego
            })
        });

        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Convertir base64 a Blob
        const byteCharacters = atob(data.base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: data.mimeType });

        // Crear un archivo File a partir del Blob
        const archivo = new File([blob], data.nombreArchivo, { type: data.mimeType });

        // Crear un DataTransfer para asignar el archivo al input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(archivo);
        inputPoster.files = dataTransfer.files;

        posterInfo.textContent = `✓ Imagen cargada: ${data.nombreArchivo} (${Math.round(data.size / 1024)}KB)`;
        posterInfo.classList.add('ok');

    } catch (error) {
        throw error;
    }
}

// Función para alternar modo manual
function toggleModoManual() {
    const busquedaContainer = document.getElementById('busquedaContainer');
    if (busquedaContainer.style.display === 'none') {
        busquedaContainer.style.display = 'block';
    } else {
        busquedaContainer.style.display = 'none';
        // Limpiar campos si se activa modo manual
        inputBusqueda.value = '';
        resultadosDiv.style.display = 'none';
    }
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function (event) {
    if (!event.target.closest('.busqueda-container')) {
        resultadosDiv.style.display = 'none';
    }
});