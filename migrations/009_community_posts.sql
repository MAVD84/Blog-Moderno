ALTER TABLE posts
    ADD COLUMN member_author_id BIGINT UNSIGNED NULL AFTER author_id,
    ADD COLUMN post_type ENUM('blog', 'community') NOT NULL DEFAULT 'blog' AFTER author_name,
    ADD COLUMN status ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'published' AFTER post_type,
    ADD CONSTRAINT fk_posts_member_author
        FOREIGN KEY (member_author_id) REFERENCES members(id) ON DELETE SET NULL,
    ADD INDEX idx_posts_community (post_type, status, fecha);
