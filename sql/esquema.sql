/*
 * Esquema de la base de datos que ejecuta php/autoinstall.php.
 * Solo usa CREATE ... IF NOT EXISTS: si una tabla ya existe no se toca,
 * así que nunca borra ni sobrescribe datos.
 * La base de datos la crea el PHP con el nombre de sql/config.php.
 */

/* Tabla Usuarios */
CREATE TABLE IF NOT EXISTS Usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    salt VARCHAR(255) NOT NULL,
    contrasena VARCHAR(100) NOT NULL
);

/* Tabla Juegos */
CREATE TABLE IF NOT EXISTS Juegos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poster VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    puntuacion_metacritic INT NOT NULL,
    duracion_horas INT NOT NULL,
    indicador DECIMAL(5, 2) GENERATED ALWAYS AS (puntuacion_metacritic / duracion_horas) STORED /* puntuacion_metacritic / duracion_horas */
);

/* Tabla intermedia Añade */
CREATE TABLE IF NOT EXISTS Anade (
    id_juego INT,
    id_usuario INT,
    PRIMARY KEY (id_juego, id_usuario),
    FOREIGN KEY (id_juego) REFERENCES Juegos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) ON DELETE CASCADE
);
