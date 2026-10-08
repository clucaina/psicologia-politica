CREATE TABLE IF NOT EXISTS hp_npl_batches (
 id VARCHAR(64) PRIMARY KEY,
 sha256 CHAR(64) NOT NULL,
 status VARCHAR(20) NOT NULL,
 created_at VARCHAR(40) NOT NULL,
 created_by VARCHAR(254) NOT NULL,
 total_rows INT NOT NULL,
 inserted_rows INT NOT NULL DEFAULT 0,
 staged_at VARCHAR(40) NULL,
 rolled_back_at VARCHAR(40) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hp_npl_batch_items (
 batch_id VARCHAR(64) NOT NULL,
 natural_key CHAR(64) NOT NULL,
 observation_id BIGINT NOT NULL,
 PRIMARY KEY (batch_id,natural_key),
 UNIQUE KEY uq_npl_batch_natural_key (natural_key),
 UNIQUE KEY uq_npl_batch_observation (observation_id),
 KEY idx_npl_batch_items_observation (observation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
