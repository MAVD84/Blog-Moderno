ALTER TABLE posts
    ADD COLUMN image_credit VARCHAR(200) NULL AFTER imagen,
    ADD COLUMN image_credit_url VARCHAR(500) NULL AFTER image_credit;
