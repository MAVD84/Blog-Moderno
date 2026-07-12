ALTER TABLE members
    ADD COLUMN profile_slug VARCHAR(190) NULL AFTER avatar,
    ADD COLUMN profile_public BOOLEAN NOT NULL DEFAULT FALSE AFTER profile_slug;

UPDATE members SET profile_slug=CONCAT('usuario-',id) WHERE profile_slug IS NULL OR profile_slug='';

ALTER TABLE members
    MODIFY profile_slug VARCHAR(190) NOT NULL,
    ADD UNIQUE INDEX idx_members_profile_slug (profile_slug);

CREATE TABLE IF NOT EXISTS member_follows (
    follower_id BIGINT UNSIGNED NOT NULL,
    followed_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    CONSTRAINT fk_follows_follower FOREIGN KEY (follower_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT fk_follows_followed FOREIGN KEY (followed_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT chk_not_self_follow CHECK (follower_id <> followed_id),
    INDEX idx_follows_followed (followed_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
