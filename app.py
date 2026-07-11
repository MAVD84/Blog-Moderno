import hmac
import os
import secrets
import sqlite3
import uuid
from functools import wraps
from pathlib import Path

from flask import (
    Flask,
    abort,
    flash,
    redirect,
    render_template,
    request,
    send_from_directory,
    session,
    url_for,
)
from flask_wtf.csrf import CSRFProtect
from PIL import Image, UnidentifiedImageError
from werkzeug.security import check_password_hash
from werkzeug.utils import secure_filename


BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = Path(os.getenv("DATA_DIR", BASE_DIR)).resolve()
DB_PATH = Path(os.getenv("DATABASE_PATH", DATA_DIR / "blog.db")).resolve()
UPLOAD_FOLDER = Path(os.getenv("UPLOAD_FOLDER", DATA_DIR / "uploads")).resolve()
ALLOWED_IMAGE_FORMATS = {
    "PNG": "png",
    "JPEG": "jpg",
    "GIF": "gif",
    "WEBP": "webp",
}

DATA_DIR.mkdir(parents=True, exist_ok=True)
DB_PATH.parent.mkdir(parents=True, exist_ok=True)
UPLOAD_FOLDER.mkdir(parents=True, exist_ok=True)

app = Flask(__name__)
app.config.update(
    SECRET_KEY=os.getenv("SECRET_KEY") or secrets.token_hex(32),
    MAX_CONTENT_LENGTH=int(os.getenv("MAX_UPLOAD_MB", "5")) * 1024 * 1024,
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE="Lax",
    SESSION_COOKIE_SECURE=os.getenv("SESSION_COOKIE_SECURE", "false").lower() == "true",
    UPLOAD_FOLDER=str(UPLOAD_FOLDER),
)
csrf = CSRFProtect(app)

ADMIN_USER = os.getenv("ADMIN_USER")
ADMIN_PASSWORD_HASH = os.getenv("ADMIN_PASSWORD_HASH")
ADMIN_PASS = os.getenv("ADMIN_PASS")


def login_required(view):
    @wraps(view)
    def decorated_function(*args, **kwargs):
        if not session.get("logged_in"):
            flash("Por favor inicia sesión.", "error")
            return redirect(url_for("login"))
        return view(*args, **kwargs)

    return decorated_function


def get_db():
    connection = sqlite3.connect(DB_PATH)
    connection.row_factory = sqlite3.Row
    return connection


def init_db():
    with get_db() as conn:
        conn.execute(
            """
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo TEXT NOT NULL,
                contenido TEXT NOT NULL,
                imagen TEXT,
                fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
            """
        )


def credentials_are_configured():
    return bool(ADMIN_USER and (ADMIN_PASSWORD_HASH or ADMIN_PASS))


def password_is_valid(password):
    if ADMIN_PASSWORD_HASH:
        return check_password_hash(ADMIN_PASSWORD_HASH, password)
    return bool(ADMIN_PASS) and hmac.compare_digest(ADMIN_PASS, password)


def save_image(file):
    if not file or not file.filename:
        return None

    try:
        image = Image.open(file.stream)
        image.verify()
        image_format = image.format
    except (UnidentifiedImageError, OSError, ValueError):
        raise ValueError("El archivo seleccionado no es una imagen válida.")

    extension = ALLOWED_IMAGE_FORMATS.get(image_format)
    if not extension:
        raise ValueError("Formato no permitido. Usa PNG, JPG, GIF o WEBP.")

    original_stem = Path(secure_filename(file.filename)).stem[:60] or "imagen"
    filename = f"{original_stem}-{uuid.uuid4().hex}.{extension}"
    file.stream.seek(0)
    file.save(UPLOAD_FOLDER / filename)
    return filename


def remove_image(filename):
    if not filename:
        return
    path = UPLOAD_FOLDER / filename
    try:
        path.resolve().relative_to(UPLOAD_FOLDER)
        path.unlink(missing_ok=True)
    except (OSError, ValueError):
        app.logger.warning("No se pudo eliminar la imagen %s", filename)


init_db()


@app.route("/")
def index():
    with get_db() as conn:
        posts = conn.execute("SELECT * FROM posts ORDER BY fecha DESC").fetchall()
    return render_template("index.html", posts=posts)


@app.route("/uploads/<path:filename>")
def uploaded_file(filename):
    return send_from_directory(UPLOAD_FOLDER, filename)


@app.route("/post/<int:id>")
def post(id):
    with get_db() as conn:
        item = conn.execute("SELECT * FROM posts WHERE id = ?", (id,)).fetchone()
    if item is None:
        abort(404)
    return render_template("post.html", post=item)


@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        if not credentials_are_configured():
            app.logger.error("Configura ADMIN_USER y ADMIN_PASSWORD_HASH (o ADMIN_PASS).")
            flash("El acceso administrativo no está configurado.", "error")
            return render_template("login.html"), 503

        user = request.form.get("username", "")
        password = request.form.get("password", "")
        valid_user = hmac.compare_digest(ADMIN_USER, user)
        if valid_user and password_is_valid(password):
            session.clear()
            session["logged_in"] = True
            return redirect(url_for("dashboard"))
        flash("Usuario o contraseña incorrectos", "error")
    return render_template("login.html")


@app.post("/logout")
@login_required
def logout():
    session.clear()
    return redirect(url_for("index"))


@app.route("/dashboard", methods=["GET", "POST"])
@login_required
def dashboard():
    if request.method == "POST":
        titulo = request.form.get("titulo", "").strip()
        contenido = request.form.get("contenido", "").strip()
        if not titulo or not contenido:
            flash("El título y el contenido son obligatorios.", "error")
            return render_template("dashboard.html"), 400

        try:
            imagen_nombre = save_image(request.files.get("imagen"))
        except ValueError as error:
            flash(str(error), "error")
            return render_template("dashboard.html"), 400

        with get_db() as conn:
            conn.execute(
                "INSERT INTO posts (titulo, contenido, imagen) VALUES (?, ?, ?)",
                (titulo, contenido, imagen_nombre),
            )
        return redirect(url_for("index"))

    return render_template("dashboard.html")


@app.route("/editar/<int:id>", methods=["GET", "POST"])
@login_required
def editar(id):
    with get_db() as conn:
        item = conn.execute("SELECT * FROM posts WHERE id = ?", (id,)).fetchone()
    if item is None:
        abort(404)

    if request.method == "POST":
        titulo = request.form.get("titulo", "").strip()
        contenido = request.form.get("contenido", "").strip()
        if not titulo or not contenido:
            flash("El título y el contenido son obligatorios.", "error")
            return render_template("editar.html", post=item), 400

        imagen_nombre = item["imagen"]
        new_image = None
        if request.files.get("imagen") and request.files["imagen"].filename:
            try:
                new_image = save_image(request.files["imagen"])
                imagen_nombre = new_image
            except ValueError as error:
                flash(str(error), "error")
                return render_template("editar.html", post=item), 400

        try:
            with get_db() as conn:
                conn.execute(
                    "UPDATE posts SET titulo = ?, contenido = ?, imagen = ? WHERE id = ?",
                    (titulo, contenido, imagen_nombre, id),
                )
        except sqlite3.Error:
            remove_image(new_image)
            raise

        if new_image:
            remove_image(item["imagen"])
        return redirect(url_for("post", id=id))

    return render_template("editar.html", post=item)


@app.post("/borrar/<int:id>")
@login_required
def borrar(id):
    with get_db() as conn:
        item = conn.execute("SELECT imagen FROM posts WHERE id = ?", (id,)).fetchone()
        if item is None:
            abort(404)
        conn.execute("DELETE FROM posts WHERE id = ?", (id,))
    remove_image(item["imagen"])
    return redirect(url_for("index"))


@app.errorhandler(413)
def upload_too_large(_error):
    return "El archivo supera el tamaño máximo permitido.", 413


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port)
