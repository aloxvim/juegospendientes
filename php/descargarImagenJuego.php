<?php
// descargarImagenJuego.php
// Este archivo actúa como proxy para descargar imágenes desde RAWG sin problemas de CORS

header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener los datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['url']) || !isset($input['nombre'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros requeridos']);
    exit();
}

$urlImagen = $input['url'];
$nombreJuego = $input['nombre'];

// Validar que la URL sea de RAWG
if (strpos($urlImagen, 'rawg.io') === false) {
    http_response_code(400);
    echo json_encode(['error' => 'URL no válida']);
    exit();
}

try {
    // Descargar la imagen usando cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlImagen);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Solo si tienes problemas con SSL
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $imagenData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || $imagenData === false) {
        throw new Exception('Error al descargar la imagen: ' . $error);
    }
    
    // Verificar que sea una imagen válida
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imagenData);
    
    $mimePermitidos = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($mimeType, $mimePermitidos)) {
        throw new Exception('El archivo descargado no es una imagen válida');
    }
    
    // Verificar el tamaño (máximo 2MB)
    if (strlen($imagenData) > 2 * 1024 * 1024) {
        throw new Exception('La imagen es demasiado grande (máximo 2MB)');
    }
    
    // Determinar la extensión
    $extension = '';
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $extension = 'jpg';
            break;
        case 'image/png':
            $extension = 'png';
            break;
        case 'image/webp':
            $extension = 'webp';
            break;
    }
    
    // Crear un nombre de archivo limpio (solo es el nombre que verá el <input type="file">)
    $nombreLimpio = preg_replace('/[^a-z0-9]/i', '_', $nombreJuego);
    $nombreLimpio = substr($nombreLimpio, 0, 50); // Limitar longitud
    $nombreArchivo = 'poster_' . $nombreLimpio . '.' . $extension;

    // La imagen no se guarda en el servidor: se manda al navegador en base64,
    // el JS la mete en el formulario y insertarJuego.php la guarda al enviarlo

    // Convertir la imagen a base64 para enviarla al cliente
    $imagenBase64 = base64_encode($imagenData);
    
    // Responder con éxito
    echo json_encode([
        'success' => true,
        'nombreArchivo' => $nombreArchivo,
        'mimeType' => $mimeType,
        'base64' => $imagenBase64,
        'size' => strlen($imagenData)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>