ALTER TABLE comments
    ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER member_id,
    ADD CONSTRAINT fk_comments_parent
        FOREIGN KEY (parent_id)
        REFERENCES comments(id)
        ON DELETE CASCADE,
    ADD INDEX idx_comments_parent (parent_id);
