ALTER TABLE activity_logs MODIFY COLUMN action ENUM('created', 'claimed', 'unclaimed', 'completed', 'archived', 'unarchived', 'taken_over') NOT NULL;
