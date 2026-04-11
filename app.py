import os
import sqlite3
from flask import Flask, render_template, request, redirect, url_for, session, flash
from werkzeug.utils import secure_filename
from functools import wraps

app = Flask(__name__)
# RECOMENDACIÓN: En Coolify, usa una variable de entorno para la clave secreta
app.secret_key = os.getenv("SECRET_KEY", "clave_super_secreta_cambiala")

# --- RUTAS DE ARCHIVOS ---
BASE_DIR = os.path.abspath(os.path.dirname(__file__))
# Aseguramos que las subidas y la DB estén en una carpeta persistente si la configuras
UPLOAD_FOLDER = os.path.join(BASE_DIR, 'static/uploads')
DB_NAME = "blog.db"

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['ALLOWED_EXTENSIONS'] = {'png', 'jpg', 'jpeg', 'gif', 'webp'}

if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

# --- CONFIGURACIÓN DE USUARIO ---
ADMIN_USER = os.getenv("ADMIN_USER", "admin")
ADMIN_PASS = os.getenv("ADMIN_PASS", "K3y1907$$")

# --- Ayudantes ---
def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in app.config['ALLOWED_EXTENSIONS']

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'logged_in' not in session:
            flash("Por favor inicia sesión.", "error")
            return redirect(url_for('login'))
        return f(*args, **kwargs)
    return decorated_function

def init_db():
    db_path = os.path.join(BASE_DIR, DB_NAME)
    with sqlite3.connect(db_path) as conn:
        cursor = conn.cursor()
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo TEXT NOT NULL,
                contenido TEXT NOT NULL,
                imagen TEXT,
                fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ''')
        conn.commit()

# Inicializar DB al arrancar
init_db()

# --- Rutas Públicas ---

@app.route('/')
def index():
    db_path = os.path.join(BASE_DIR, DB_NAME)
    with sqlite3.connect(db_path) as conn:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM posts ORDER BY fecha DESC")
        posts = cursor.fetchall()
    return render_template('index.html', posts=posts)

@app.route('/post/<int:id>')
def post(id):
    db_path = os.path.join(BASE_DIR, DB_NAME)
    with sqlite3.connect(db_path) as conn:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM posts WHERE id = ?", (id,))
        post = cursor.fetchone()
    if post is None:
        return "Post no encontrado", 404
    return render_template('post.html', post=post)

# --- Rutas de Administración ---

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        user = request.form.get('username')
        password = request.form.get('password')
        if user == ADMIN_USER and password == ADMIN_PASS:
            session['logged_in'] = True
            return redirect(url_for('dashboard'))
        else:
            flash("Usuario o contraseña incorrectos", "error")
    return render_template('login.html')

@app.route('/logout')
def logout():
    session.pop('logged_in', None)
    return redirect(url_for('index'))

@app.route('/dashboard', methods=['GET', 'POST'])
@login_required
def dashboard():
    if request.method == 'POST':
        titulo = request.form.get('titulo')
        contenido = request.form.get('contenido')

        imagen_nombre = None
        if 'imagen' in request.files:
            file = request.files['imagen']
            if file and allowed_file(file.filename):
                filename = secure_filename(file.filename)
                file.save(os.path.join(app.config['UPLOAD_FOLDER'], filename))
                imagen_nombre = filename

        if titulo and contenido:
            db_path = os.path.join(BASE_DIR, DB_NAME)
            with sqlite3.connect(db_path) as conn:
                cursor = conn.cursor()
                cursor.execute("INSERT INTO posts (titulo, contenido, imagen) VALUES (?, ?, ?)",
                               (titulo, contenido, imagen_nombre))
                conn.commit()
            return redirect(url_for('index'))

    return render_template('dashboard.html')

@app.route('/editar/<int:id>', methods=['GET', 'POST'])
@login_required
def editar(id):
    db_path = os.path.join(BASE_DIR, DB_NAME)

    with sqlite3.connect(db_path) as conn:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM posts WHERE id = ?", (id,))
        post = cursor.fetchone()

    if request.method == 'POST':
        titulo = request.form.get('titulo')
        contenido = request.form.get('contenido')
        imagen_nombre = post['imagen']

        if 'imagen' in request.files:
            file = request.files['imagen']
            if file and allowed_file(file.filename):
                filename = secure_filename(file.filename)
                file.save(os.path.join(app.config['UPLOAD_FOLDER'], filename))
                imagen_nombre = filename

        with sqlite3.connect(db_path) as conn:
            cursor = conn.cursor()
            cursor.execute("""
                UPDATE posts
                SET titulo = ?, contenido = ?, imagen = ?
                WHERE id = ?
            """, (titulo, contenido, imagen_nombre, id))
            conn.commit()

        return redirect(url_for('post', id=id))

    return render_template('editar.html', post=post)

@app.route('/borrar/<int:id>')
@login_required
def borrar(id):
    db_path = os.path.join(BASE_DIR, DB_NAME)
    with sqlite3.connect(db_path) as conn:
        cursor = conn.cursor()
        cursor.execute("DELETE FROM posts WHERE id = ?", (id,))
        conn.commit()
    return redirect(url_for('index'))

if __name__ == "__main__":
    # Importante para Coolify: host 0.0.0.0
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port)
