ALTER TABLE users
    ADD COLUMN bio VARCHAR(500) NULL AFTER role,
    ADD COLUMN avatar VARCHAR(255) NULL AFTER bio,
    ADD COLUMN profile_slug VARCHAR(190) NULL AFTER avatar,
    ADD COLUMN profile_public BOOLEAN NOT NULL DEFAULT FALSE AFTER profile_slug;

UPDATE users SET profile_slug=CONCAT('autor-',id) WHERE profile_slug IS NULL OR profile_slug='';

ALTER TABLE users
    MODIFY profile_slug VARCHAR(190) NOT NULL,
    ADD UNIQUE INDEX idx_users_profile_slug (profile_slug);

ALTER TABLE comments
    ADD COLUMN staff_author_id BIGINT UNSIGNED NULL AFTER member_id,
    ADD CONSTRAINT fk_comments_staff_author FOREIGN KEY (staff_author_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD INDEX idx_comments_staff_author (staff_author_id);

CREATE TABLE IF NOT EXISTS user_follows (
    follower_member_id BIGINT UNSIGNED NOT NULL,
    followed_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_member_id, followed_user_id),
    CONSTRAINT fk_user_follows_member FOREIGN KEY (follower_member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_follows_user FOREIGN KEY (followed_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_follows_followed (followed_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
