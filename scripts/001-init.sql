-- Initial Schema Setup
CREATE TABLE families (
    id VARCHAR(36) PRIMARY KEY,
    family_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY,
    family_id VARCHAR(36),
    name VARCHAR(50) NOT NULL,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id)
) ENGINE=InnoDB;

CREATE TABLE chores (
    id VARCHAR(36) PRIMARY KEY,
    family_id VARCHAR(36),
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL,
    assigned_to VARCHAR(36) NULL,
    due_date DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;