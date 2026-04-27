-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- Chores Table
CREATE TABLE IF NOT EXISTS chores (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('available', 'claimed', 'completed', 'archived') DEFAULT 'available',
    due_date TIMESTAMP NULL,
    created_by CHAR(36) NOT NULL,
    claimed_by CHAR(36) NULL,
    completed_by CHAR(36) NULL,
    archived_by CHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    claimed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    archived_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (claimed_by) REFERENCES users(id),
    FOREIGN KEY (completed_by) REFERENCES users(id),
    FOREIGN KEY (archived_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id CHAR(36) PRIMARY KEY,
    chore_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    action ENUM('created', 'claimed', 'unclaimed', 'completed', 'archived', 'unarchived') NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chore_id) REFERENCES chores(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
