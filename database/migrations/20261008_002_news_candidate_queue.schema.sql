CREATE TABLE IF NOT EXISTS hp_news_candidates (
 url_sha256 CHAR(64) PRIMARY KEY,
 feed_id VARCHAR(100) NOT NULL,
 title VARCHAR(400) NOT NULL,
 source_url VARCHAR(2000) NOT NULL,
 short_summary TEXT NULL,
 published_at VARCHAR(40) NULL,
 matched_terms TEXT NULL,
 image_brief TEXT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'review',
 created_at VARCHAR(40) NOT NULL,
 KEY idx_news_candidates_status (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
