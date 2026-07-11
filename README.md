# Blog Moderno

Blog personal construido con Flask y SQLite.

## Configuración

Instala las dependencias y define las variables de entorno antes de iniciar:

```bash
pip install -r requirements.txt
export SECRET_KEY="una-clave-aleatoria-larga"
export ADMIN_USER="admin"
export ADMIN_PASSWORD_HASH="$(python -c 'from werkzeug.security import generate_password_hash; print(generate_password_hash("cambia-esta-clave"))')"
```

Variables opcionales:

- `DATA_DIR`: directorio persistente para la base de datos y las imágenes.
- `DATABASE_PATH`: ruta completa de la base SQLite.
- `UPLOAD_FOLDER`: directorio de imágenes.
- `MAX_UPLOAD_MB`: máximo por petición; el valor predeterminado es 5 MB.
- `SESSION_COOKIE_SECURE=true`: actívalo cuando el sitio utilice HTTPS.
- `ADMIN_PASS`: contraseña en texto plano, disponible solo por compatibilidad. Se recomienda `ADMIN_PASSWORD_HASH`.

Para desarrollo:

```bash
python app.py
```

Para producción, el `Procfile` inicia Gunicorn. Monta `DATA_DIR` como volumen persistente.

## Pruebas

```bash
pip install pytest
pytest
```
