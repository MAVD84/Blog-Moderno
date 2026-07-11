ALTER TABLE posts ADD COLUMN slug VARCHAR(200) NULL AFTER titulo;
CREATE UNIQUE INDEX idx_posts_slug ON posts (slug);

-- La aplicación asignará automáticamente slugs basados en el título
-- a los artículos existentes la próxima vez que se abra la portada.
