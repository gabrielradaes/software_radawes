# Instalar RyE / RadaWes en el hosting (cPanel, sin SSH)

Tiempo estimado: 30 a 45 minutos. Solo necesitas el navegador.

**Archivos que vas a usar** (carpeta `dist/` del proyecto):

| Archivo | Para qué |
|---|---|
| `radawes-hosting-AAAAMMDD.zip` | El sistema completo (se sube a cPanel) |
| `radawes-base-datos.sql` | La base de datos inicial (se importa en phpMyAdmin) |
| `CREDENCIALES.txt` | Correo y contraseña del primer administrador. **No lo subas al hosting.** |

El resultado final será:

- `https://radawes.com` → portada con las 3 tiendas
- `https://calacoto.radawes.com` → RadaWes Calacoto
- `https://comercio.radawes.com` → RyE Made in Brazil (Comercio)
- `https://elalto.radawes.com` → RyC El Alto
- `https://radawes.com/admin/login` → panel de administración

---

## Paso 1 · Entrar a cPanel y mostrar archivos ocultos

1. Entra a cPanel con los datos que te mandó el hosting por correo.
2. Abre **Administrador de archivos** (*File Manager*).
3. Arriba a la derecha: **Configuración** (*Settings*) → marca **Mostrar archivos ocultos** (*Show Hidden Files*) → **Guardar**.
   Esto es importante: el sistema usa los archivos `.htaccess` y `.env`, que empiezan con punto.

## Paso 2 · Subir y descomprimir el sistema

1. En el Administrador de archivos, ve a tu **carpeta de inicio** (el ícono de la casita, `/home/tuusuario`). **No** entres a `public_html` todavía.
2. Entra a `public_html` y **borra** los archivos de ejemplo si existen (`index.html`, `default.html`, `index.php` de bienvenida).
   **No borres** las carpetas `cgi-bin` ni `.well-known`. Vuelve a la carpeta de inicio.
3. Botón **Cargar** (*Upload*) → sube `radawes-hosting-AAAAMMDD.zip`. Espera a que llegue al 100 %.
4. Vuelve al Administrador de archivos, clic derecho sobre el zip → **Extraer** (*Extract*) → deja la ruta `/home/tuusuario` → **Extract Files**.
5. Verás dos carpetas:
   - `rye/` → el sistema (queda **fuera** de internet, protegido)
   - `public_html/` → lo que ve la gente (estilos, imágenes, fotos que subas)
6. Borra el zip para no ocupar espacio.

## Paso 3 · Crear la base de datos

1. En cPanel: **Bases de datos MySQL** (*MySQL Databases*).
2. **Crear nueva base de datos**: escribe `rye` → Crear. Quedará con un prefijo, por ejemplo `tuusuario_rye`. **Anota el nombre completo.**
3. **Agregar nuevo usuario**: nombre `rye`, genera una contraseña fuerte con el botón **Generador de contraseñas** → Crear. **Anota el usuario completo (`tuusuario_rye`) y la contraseña.**
4. **Agregar usuario a la base de datos**: elige el usuario y la base → Agregar → marca **TODOS LOS PRIVILEGIOS** (*ALL PRIVILEGES*) → Hacer cambios.

## Paso 4 · Importar la base de datos inicial

1. En cPanel: **phpMyAdmin**.
2. A la izquierda haz clic en tu base `tuusuario_rye`.
3. Pestaña **Importar** → **Seleccionar archivo** → elige `radawes-base-datos.sql` → botón **Importar** (abajo).
4. Debe decir "La importación se ejecutó exitosamente" y aparecer unas 35 tablas a la izquierda.

## Paso 5 · Conectar el sistema con la base de datos

1. Administrador de archivos → entra a `rye/` → clic derecho sobre `.env` → **Editar**.
2. Cambia **solo estas tres líneas** con lo que anotaste en el paso 3:

   ```
   DB_DATABASE=tuusuario_rye
   DB_USERNAME=tuusuario_rye
   DB_PASSWORD=la_contraseña_que_generaste
   ```

3. **Guardar cambios**. No toques las demás líneas (la `APP_KEY` ya viene generada).

## Paso 6 · Elegir PHP 8.3

1. En cPanel: **Administrador MultiPHP** (*MultiPHP Manager*) → marca `radawes.com` → en la lista elige **PHP 8.3 (ea-php83)** → **Aplicar**.
   ⚠️ Elige la que dice **(ea-php83)**, NO la "PHP 8.3 [Secured] (alt-php83)". En este hosting los dominios usan PHP-FPM y la versión *alt-php83* viene **sin la extensión `mbstring`**, lo que provoca "500 Server Error" (`Call to undefined function mb_split()`). La pantalla "Seleccionar versión de PHP" (PHP Selector) **no se aplica** a dominios con PHP-FPM, así que activar extensiones ahí no sirve.
2. **Editor INI de MultiPHP**: elige el dominio y pon `upload_max_filesize` = **16M** y `post_max_size` = **64M** (para subir varias fotos a la vez).

## Paso 7 · Crear los subdominios de cada tienda

1. En cPanel: **Dominios** (*Domains*) → **Crear un nuevo dominio** (*Create A New Domain*).
2. Escribe `calacoto.radawes.com`.
3. **Deja marcada** la casilla **"Compartir la raíz del documento con radawes.com"** (*Share document root*). Las 3 tiendas usan la misma carpeta `public_html`; el sistema sabe qué tienda mostrar según la dirección.
4. **Enviar**. Repite con `comercio.radawes.com` y `elalto.radawes.com`.

> Si tu cPanel muestra "Subdominios" en vez de "Dominios": crea `calacoto`, `comercio` y `elalto`, y en **Raíz del documento** escribe `public_html` (borra lo que proponga automáticamente).

## Paso 8 · Activar HTTPS (el candado)

1. En cPanel: **Estado SSL/TLS** (*SSL/TLS Status*) → **Ejecutar AutoSSL** (*Run AutoSSL*).
2. Espera unos minutos y recarga: `radawes.com`, `www.radawes.com` y los 3 subdominios deben salir con candado verde.
   Si recién compraste el dominio, puede tardar hasta unas horas en activarse.

## Paso 9 · Probar

Abre en el navegador:

- `https://radawes.com` → portada "Elige tu tienda"
- `https://calacoto.radawes.com` → entra directo a RadaWes Calacoto
- `https://radawes.com/admin/login` → entra con el correo y la contraseña de **CREDENCIALES.txt**

## Paso 10 · Primeros ajustes en el panel

1. **Usuarios** → edita **Administrador** → pon tu correo real y una contraseña nueva que solo tú sepas.
2. **Usuarios → + Nuevo usuario** → crea los supervisores y vendedores de cada tienda (cada uno con su sucursal).
3. **Sucursales** → completa la **dirección, WhatsApp y horario reales** de Calacoto y El Alto (hoy dicen "por confirmar").
4. **Colores de la tienda** → elige la paleta de cada tienda.
5. **Productos** → empieza a cargar los productos reales con sus fotos, tallas y stock.

---

## Si algo falla

| Síntoma | Causa probable | Solución |
|---|---|---|
| Página en blanco o "500 Server Error" | Datos de la base mal escritos | Revisa las 3 líneas `DB_` del paso 5 (con el prefijo `tuusuario_`) |
| "500" y la base está bien | PHP sin mbstring | Paso 6: el dominio debe usar **PHP 8.3 (ea-php83)** |
| Todas las páginas dicen "404" menos la portada | Falta el `.htaccess` | Paso 1 (archivos ocultos) y verifica que `public_html/.htaccess` exista |
| Se ve sin diseño (todo blanco y desordenado) | No se copiaron `css/` y `js/` | Verifica `public_html/css/app.css` y `public_html/js/alpine.min.js` |
| No puedo subir fotos grandes | Límite de PHP | Paso 6: `upload_max_filesize` y `post_max_size` |
| El subdominio muestra otra cosa | Raíz del documento distinta | Paso 7: debe ser `public_html` |

El detalle de cualquier error queda en `rye/storage/logs/` (archivo `laravel-FECHA.log`). Si algo falla, copia las últimas líneas de ese archivo y envíalas para revisarlas. **No envíes el archivo `.env`.**

## Copias de seguridad

- El hosting hace copias diarias automáticas (JetBackup, en cPanel).
- Una vez al mes, descarga tú también: **phpMyAdmin → tu base → Exportar** (la base de datos) y la carpeta `public_html/uploads` comprimida (las fotos).

## Actualizaciones futuras

Cuando haya cambios en el sistema se arma un zip nuevo. Para actualizar se reemplaza `rye/` **conservando** `rye/.env` y `rye/storage/`, y se reemplazan `public_html/css` y `public_html/js` **sin tocar** `public_html/uploads` (ahí están las fotos).
