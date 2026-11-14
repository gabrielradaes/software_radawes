# Actualizar la página con `git push`

Cada `git push` a la rama **main** de GitHub actualiza automáticamente
https://radawescalzadosbrasileros.com en 2 a 5 minutos (la primera vez tarda más: sube todo).

Lo hace el flujo `.github/workflows/deploy.yml`:
1. GitHub instala las dependencias y compila los estilos.
2. Sube por FTP seguro **solo los archivos que cambiaron**.
3. Llama a la página (`/_deploy`) para aplicar cambios de base de datos y limpiar la caché.

**Nunca toca:** `rye/.env`, `rye/storage/` (registros, sesiones) ni `public_html/uploads/` (las fotos).

---

## Configuración (una sola vez)

### 1 · Crear una cuenta FTP para las actualizaciones (cPanel)

1. cPanel → **Cuentas de FTP**.
2. **Iniciar sesión**: `deploy`
3. **Contraseña**: genera una fuerte con el botón **Generador de contraseñas** y cópiala (la necesitas en el paso 2).
4. **Directorio**: borra lo que propone (`public_html/deploy`) y deja **`/`**
   (la cuenta necesita ver las carpetas `rye/` y `public_html/`).
5. **Cuota**: Ilimitado → **Crear cuenta de FTP**.

Quedará como `deploy@radawescalzadosbrasileros.com`.

### 2 · Guardar los secretos en GitHub

GitHub → repositorio **software_radawes** → **Settings** → **Secrets and variables** → **Actions**.

En la pestaña **Secrets** → **New repository secret**, crea estos 4 (los valores están en
`dist/GITHUB-SECRETOS.txt` en tu computadora; ese archivo nunca se sube a GitHub):

| Name | Value |
|---|---|
| `FTP_SERVER` | `paul.cloudserver17.com` |
| `FTP_USERNAME` | `deploy@radawescalzadosbrasileros.com` |
| `FTP_PASSWORD` | la contraseña del paso 1 |
| `DEPLOY_TOKEN` | la clave larga de `GITHUB-SECRETOS.txt` (ya está puesta en el `.env` del hosting) |

En la pestaña **Variables** → **New repository variable**:

| Name | Value |
|---|---|
| `SITE_URL` | `https://radawescalzadosbrasileros.com` |

### 3 · Primera actualización

GitHub → pestaña **Actions** → **Desplegar al hosting** → **Run workflow** → **Run workflow**.
La primera vez tarda 10 a 30 minutos (sube todas las librerías). Las siguientes, 2 a 5 minutos.

Si termina con ✅ verde, listo. Si sale ❌ rojo, abre el paso que falló y copia el mensaje.

---

## Uso diario

```bash
git add -A
git commit -m "Describe el cambio"
git push
```

Y en GitHub → **Actions** ves el progreso. GitHub también te avisa por correo si algo falla.

## Si algo sale mal

| Paso que falla | Causa probable |
|---|---|
| "Subir sistema" — `530 Login incorrect` | Usuario o contraseña FTP mal copiados en los secretos |
| "Subir sistema" — no encuentra `rye/` | La cuenta FTP no tiene directorio `/` (paso 1.4) |
| "Aplicar migraciones" — `No autorizado` (403) | `DEPLOY_TOKEN` distinto al del `.env` del hosting |
| "Comprobar que la página responde" — 500 | Error en el código nuevo: revisa `rye/storage/logs/` en cPanel |
