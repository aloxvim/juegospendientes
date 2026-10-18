# 🎮 Juegos Pendientes

¿Te ha pasado que tienes una gran cantidad de videojuegos en tu biblioteca y no sabes por dónde empezar? ¡No te preocupes más! Con esta sencilla aplicación, podrás:

- 📋 **Añadir** los juegos que tienes pendientes (con búsqueda automática en [RAWG](https://rawg.io)).
- ⭐ **Asignarles una puntuación**.
- ⏳ **Registrar la duración** de cada juego.

La página se encargará de **ordenarlos automáticamente** desde el juego con mayor puntuación y menor duración, para que puedas organizarte mejor y disfrutar al máximo de tus videojuegos. ¡Empieza a jugar y a completar tu lista de pendientes hoy mismo!

[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/aloxvim/juegospendientes)

---

## 📦 Requisitos

Un servidor **LAMP** (Linux + Apache + MySQL + PHP), por ejemplo con Docker:

- **Apache** (u otro servidor web con PHP).
- **PHP 8** o superior con las extensiones `mysqli`, `curl` y `fileinfo` (vienen activadas en la mayoría de instalaciones).
- **MySQL 5.7+** o **MariaDB 10.2+**.

No hace falta compilar nada: las dependencias de PHP (PHPMailer) ya vienen incluidas en la carpeta `vendor/`.

## 🚀 Instalación

1. **Copia el proyecto** en la carpeta pública de tu servidor web (el *document root* de Apache):

   ```bash
   git clone https://github.com/aloxvim/juegospendientes.git
   ```

2. **Crea el archivo de configuración** a partir de la plantilla:

   ```bash
   cp sql/config.empty.php sql/config.php
   ```

   y rellena al menos los datos de la base de datos (ver la sección *Configuración* más abajo). `sql/config.php` está en el `.gitignore`, así que tus credenciales nunca se subirán al repositorio.

3. **Da permisos de escritura** a la carpeta de las portadas para el usuario de Apache (normalmente `www-data`):

   ```bash
   chown -R www-data:www-data img/avatares_juegos
   ```

4. **Abre la aplicación en el navegador.** Si la base de datos todavía no existe, se abrirá automáticamente la pantalla de **instalación**: pulsa *Instalar* y se crearán la base de datos, sus tablas y sus relaciones. Esta pantalla solo funciona mientras falte la base de datos; una vez instalada, siempre te devuelve al inicio.

5. **Crea tu cuenta** desde *Crear cuenta* y empieza a añadir juegos.

> El usuario de MySQL de `config.php` necesita permiso para crear bases de datos. Si no lo tiene, crea tú la base de datos a mano y ejecuta dentro de ella el archivo `sql/esquema.sql`.

## ⚙️ Configuración

Todos los ajustes están en `sql/config.php`:

| Campo | Para qué sirve | ¿Obligatorio? |
|---|---|---|
| `$host`, `$puerto` | Dirección y puerto del servidor MySQL (con Docker, el nombre o IP del contenedor). | Sí |
| `$usuario`, `$password` | Usuario y contraseña de MySQL. | Sí |
| `$nombreBaseDatos` | Nombre de la base de datos (por ejemplo `JuegosPendientes`). | Sí |
| `$claveApiRawg` | Clave de la [API de RAWG](https://rawg.io/apidocs) para buscar juegos y rellenar los datos solos. Sin ella se pueden añadir juegos en modo manual. | No |
| `$smtpHost`, `$smtpPuerto` | Servidor de correo saliente (por defecto, Gmail). | Para enviar correos |
| `$smtpUsuario`, `$smtpClave` | Cuenta desde la que se envían los correos. Con Gmail, `$smtpClave` es una [contraseña de aplicación](https://myaccount.google.com/apppasswords), no la contraseña normal. | Para enviar correos |
| `$smtpNombreRemitente` | Nombre que aparece como remitente. | No |
| `$correoReportes` | Dónde llegan los reportes de errores (si se deja vacío, a `$smtpUsuario`). | No |

Sin la configuración del correo la aplicación funciona igual, pero *¿Has olvidado tu contraseña?* y *Reportar problema* avisarán de que el envío de correos no está configurado.

Si al abrir la aplicación aparece un aviso de configuración, revisa que `sql/config.php` existe, que está relleno y que el servidor MySQL está en marcha.

## 🔄 Actualizar

```bash
git pull
```

- Tu `sql/config.php` no se toca al actualizar. Si una versión nueva añade campos, aparecerán en `sql/config.empty.php`: cópialos a tu `config.php`.
- Las actualizaciones nunca borran datos: la instalación solo crea lo que falta (`CREATE ... IF NOT EXISTS`).
- Aun así, antes de actualizar en un servidor con usuarios es buena idea hacer una copia de la base de datos:

  ```bash
  mysqldump -u root -p JuegosPendientes > copia_juegospendientes.sql
  ```

## 📄 Licencia

Juegos Pendientes es **software libre y de código abierto**, publicado bajo la [licencia MIT](LICENSE.txt): puedes usarlo, estudiarlo, modificarlo y compartirlo libremente, siempre que mantengas el aviso de copyright.
