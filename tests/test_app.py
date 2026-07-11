import io

import pytest
from PIL import Image

import app as blog


@pytest.fixture()
def client(tmp_path, monkeypatch):
    database = tmp_path / "blog.db"
    uploads = tmp_path / "uploads"
    uploads.mkdir()

    monkeypatch.setattr(blog, "DB_PATH", database)
    monkeypatch.setattr(blog, "UPLOAD_FOLDER", uploads)
    monkeypatch.setattr(blog, "ADMIN_USER", "admin")
    monkeypatch.setattr(blog, "ADMIN_PASS", "correct-horse-battery-staple")
    monkeypatch.setattr(blog, "ADMIN_PASSWORD_HASH", None)
    blog.app.config.update(TESTING=True, WTF_CSRF_ENABLED=False)
    blog.init_db()

    yield blog.app.test_client()


def log_in(client):
    return client.post(
        "/login",
        data={"username": "admin", "password": "correct-horse-battery-staple"},
    )


def test_csrf_rejects_post_without_token(client):
    blog.app.config["WTF_CSRF_ENABLED"] = True
    response = client.post(
        "/login", data={"username": "admin", "password": "correct-horse-battery-staple"}
    )
    assert response.status_code == 400


def test_login_and_crud_flow(client):
    assert log_in(client).status_code == 302

    response = client.post(
        "/dashboard",
        data={"titulo": "Primera entrada", "contenido": "Contenido seguro"},
    )
    assert response.status_code == 302

    page = client.get("/post/1")
    assert page.status_code == 200
    assert b"Primera entrada" in page.data

    response = client.post(
        "/editar/1", data={"titulo": "Actualizada", "contenido": "Texto nuevo"}
    )
    assert response.status_code == 302
    assert b"Actualizada" in client.get("/post/1").data

    assert client.get("/borrar/1").status_code == 405
    assert client.post("/borrar/1").status_code == 302
    assert client.get("/post/1").status_code == 404


def test_post_content_is_escaped(client):
    with blog.get_db() as conn:
        conn.execute(
            "INSERT INTO posts (titulo, contenido) VALUES (?, ?)",
            ("XSS", '<script>alert("xss")</script>'),
        )

    page = client.get("/post/1")
    assert b"<script>" not in page.data
    assert b"&lt;script&gt;" in page.data


def test_missing_edit_returns_404(client):
    log_in(client)
    assert client.get("/editar/999").status_code == 404
    assert client.post("/editar/999", data={"titulo": "x", "contenido": "y"}).status_code == 404


def test_valid_image_gets_unique_name(client):
    log_in(client)
    image_data = io.BytesIO()
    Image.new("RGB", (2, 2), color="red").save(image_data, format="PNG")
    image_data.seek(0)

    response = client.post(
        "/dashboard",
        data={
            "titulo": "Con imagen",
            "contenido": "Texto",
            "imagen": (image_data, "portada.png"),
        },
        content_type="multipart/form-data",
    )
    assert response.status_code == 302

    with blog.get_db() as conn:
        post = conn.execute("SELECT imagen FROM posts").fetchone()
    assert post["imagen"].startswith("portada-")
    assert (blog.UPLOAD_FOLDER / post["imagen"]).is_file()


def test_fake_image_is_rejected(client):
    log_in(client)
    response = client.post(
        "/dashboard",
        data={
            "titulo": "Archivo falso",
            "contenido": "Texto",
            "imagen": (io.BytesIO(b"not an image"), "malware.jpg"),
        },
        content_type="multipart/form-data",
    )
    assert response.status_code == 400
