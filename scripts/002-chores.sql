CREATE TABLE chores (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL,
    assigned_to VARCHAR(36) NULL,
    due_date DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;