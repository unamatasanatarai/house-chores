<?php

class ChoreService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function generateUuid() {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    }

    public function createChore($userId, $title, $description = null, $dueDate = null) {
        if (empty(trim($title))) {
            throw new InvalidArgumentException('Title is required');
        }

        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare('INSERT INTO chores (id, title, description, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, trim($title), $description, $dueDate, $userId, 'available']);

        $this->logActivity($id, $userId, 'created');
        return ['id' => $id, 'title' => $title, 'status' => 'available'];
    }

    public function claimChore($choreId, $userId) {
        $stmt = $this->pdo->prepare("UPDATE chores SET status = 'claimed', claimed_by = ?, claimed_at = NOW() WHERE id = ? AND claimed_by IS NULL");
        $stmt->execute([$userId, $choreId]);
        
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Chore already claimed or not found');
        }

        $this->logActivity($choreId, $userId, 'claimed');
        return true;
    }

    public function logActivity($choreId, $userId, $action, $metadata = null) {
        $stmt = $this->pdo->prepare('INSERT INTO activity_logs (id, chore_id, user_id, action, metadata) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $this->generateUuid(),
            $choreId,
            $userId,
            $action,
            $metadata ? json_encode($metadata) : null
        ]);
    }
}
