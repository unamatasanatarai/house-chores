<?php

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/db.php';

function generateUuid() {
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
}

function logActivity($choreId, $userId, $action, $metadata = null) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO activity_logs (id, chore_id, user_id, action, metadata) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            generateUuid(),
            $choreId,
            $userId,
            $action,
            $metadata ? json_encode($metadata) : null
        ]);
    } catch (PDOException $e) {
        // Silently fail logging for now or handle as needed
    }
}

function handleChoreList($userId) {
    $status = $_GET['status'] ?? null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    try {
        $pdo = Database::getConnection();
        
        $sql = "SELECT c.*, 
                u1.name as creator_name, 
                u2.name as claimer_name, 
                u3.name as completer_name,
                (c.due_date IS NOT NULL AND DATE(c.due_date) < CURRENT_DATE() AND c.status != 'completed') as is_overdue
                FROM chores c
                LEFT JOIN users u1 ON c.created_by = u1.id
                LEFT JOIN users u2 ON c.claimed_by = u2.id
                LEFT JOIN users u3 ON c.completed_by = u3.id
                WHERE 1=1";
        
        $params = [];
        if ($status) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        } else {
            $sql .= " AND c.status != 'archived'"; // Default: don't show archived
        }

        // Sorting logic: Overdue first, then Claimed, then Available, all by due_date ASC
        $sql .= " ORDER BY 
                    is_overdue DESC, 
                    CASE c.status 
                        WHEN 'claimed' THEN 1 
                        WHEN 'available' THEN 2 
                        WHEN 'completed' THEN 3 
                        ELSE 4 
                    END, 
                    c.due_date ASC, 
                    c.created_at DESC";
        
        $sql .= " LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $chores = $stmt->fetchAll();

        // Get total for meta
        $countSql = "SELECT COUNT(*) FROM chores WHERE status != 'archived'";
        if ($status) $countSql = "SELECT COUNT(*) FROM chores WHERE status = '$status'";
        $total = $pdo->query($countSql)->fetchColumn();

        Response::success($chores, [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total
        ]);
    } catch (PDOException $e) {
        Response::error('500_INTERNAL_ERROR', 'Failed to fetch chores', ['details' => $e->getMessage()], 500);
    }
}

function handleChoreAdd($userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['title']) || empty(trim($input['title']))) {
        Response::error('422_VALIDATION_ERROR', 'Title is required', [], 422);
    }

    $id = generateUuid();
    $title = trim($input['title']);
    $description = $input['description'] ?? null;
    $dueDate = $input['due_date'] ?? null;

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO chores (id, title, description, due_date, created_by, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $title, $description, $dueDate, $userId, 'available']);

        logActivity($id, $userId, 'created');

        Response::success(['id' => $id, 'title' => $title, 'status' => 'available'], [], 201);
    } catch (PDOException $e) {
        Response::error('500_INTERNAL_ERROR', 'Failed to create chore', ['details' => $e->getMessage()], 500);
    }
}

function handleChoreAction($uri, $method, $userId) {
    if (!preg_match('/^\/chores\/([a-f\d\-]{36})\/(claim|unclaim|done|archive|unarchive|take-over)$/', $uri, $matches)) {
        Response::error('404_NOT_FOUND', 'Endpoint not found', [], 404);
    }

    $choreId = $matches[1];
    $action = $matches[2];

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM chores WHERE id = ?');
        $stmt->execute([$choreId]);
        $chore = $stmt->fetch();

        if (!$chore) {
            Response::error('404_NOT_FOUND', 'Chore not found', [], 404);
        }

        switch ($action) {
            case 'claim':
                if ($chore['claimed_by'] !== null) {
                    Response::error('409_CONFLICT_ALREADY_CLAIMED', 'Chore already claimed', [], 409);
                }
                $stmt = $pdo->prepare("UPDATE chores SET status = 'claimed', claimed_by = ?, claimed_at = NOW() WHERE id = ? AND claimed_by IS NULL");
                $stmt->execute([$userId, $choreId]);
                if ($stmt->rowCount() === 0) {
                     Response::error('409_CONFLICT_ALREADY_CLAIMED', 'Chore already claimed (race condition)', [], 409);
                }
                break;
            
            case 'take-over':
                // Take over doesn't care if it's already claimed
                $stmt = $pdo->prepare("UPDATE chores SET status = 'claimed', claimed_by = ?, claimed_at = NOW() WHERE id = ?");
                $stmt->execute([$userId, $choreId]);
                break;

            case 'unclaim':
                if ($chore['claimed_by'] !== $userId) {
                    Response::error('403_FORBIDDEN', 'Only the owner can unclaim', [], 403);
                }
                $pdo->prepare("UPDATE chores SET status = 'available', claimed_by = NULL, claimed_at = NULL WHERE id = ?")
                    ->execute([$choreId]);
                break;

            case 'done':
                if ($chore['claimed_by'] !== $userId) {
                    Response::error('403_FORBIDDEN', 'Only the owner can mark as done', [], 403);
                }
                $pdo->prepare("UPDATE chores SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ?")
                    ->execute([$userId, $choreId]);
                break;

            case 'archive':
                $pdo->prepare("UPDATE chores SET status = 'archived', archived_by = ?, archived_at = NOW() WHERE id = ?")
                    ->execute([$userId, $choreId]);
                break;

            case 'unarchive':
                $pdo->prepare("
                    UPDATE chores 
                    SET status = CASE 
                        WHEN completed_at IS NOT NULL THEN 'completed'
                        WHEN claimed_at IS NOT NULL THEN 'claimed'
                        ELSE 'available'
                    END, 
                    archived_by = NULL, 
                    archived_at = NULL 
                    WHERE id = ?
                ")->execute([$choreId]);
                break;
        }

        $newStatus = $action;
        switch ($action) {
            case 'claim': $newStatus = 'claimed'; break;
            case 'done': $newStatus = 'completed'; break;
            case 'unclaim': $newStatus = 'available'; break;
            case 'unarchive': $newStatus = 'available'; break;
            case 'archive': $newStatus = 'archived'; break;
            case 'take-over': $newStatus = 'claimed'; break;
        }

        $logAction = $action;
        if ($action === 'claim') $logAction = 'claimed';
        if ($action === 'done') $logAction = 'completed';
        if ($action === 'unclaim') $logAction = 'unclaimed';
        if ($action === 'archive') $logAction = 'archived';
        if ($action === 'unarchive') $logAction = 'unarchived';
        if ($action === 'take-over') $logAction = 'taken_over';

        logActivity($choreId, $userId, $logAction);
        Response::success(['id' => $choreId, 'new_status' => $newStatus]);

    } catch (PDOException $e) {
        Response::error('500_INTERNAL_ERROR', 'Database error', ['details' => $e->getMessage()], 500);
    }
}

function handleUserAdd() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['name']) || empty(trim($input['name']))) {
        Response::error('422_VALIDATION_ERROR', 'Name is required', [], 422);
    }

    $name = trim($input['name']);
    $uuid = generateUuid();

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO users (id, name) VALUES (?, ?)');
        $stmt->execute([$uuid, $name]);

        Response::success(['id' => $uuid, 'name' => $name], [], 201);
    } catch (PDOException $e) {
        Response::error('500_INTERNAL_ERROR', 'Failed to create user', ['details' => $e->getMessage()], 500);
    }
}

function handleUserList() {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT id, name FROM users');
        $users = $stmt->fetchAll();

        Response::success($users);
    } catch (PDOException $e) {
        Response::error('500_INTERNAL_ERROR', 'Failed to fetch users', ['details' => $e->getMessage()], 500);
    }
}

function handleLogList($userId) {
    $choreId = $_GET['chore_id'] ?? null;
    try {
        $pdo = Database::getConnection();
        if ($choreId) {
            $stmt = $pdo->prepare('SELECT l.*, u.name as user_name FROM activity_logs l JOIN users u ON l.user_id = u.id WHERE l.chore_id = ? ORDER BY l.created_at DESC LIMIT 10');
            $stmt->execute([$choreId]);
        } else {
            $stmt = $pdo->query('SELECT l.*, u.name as user_name FROM activity_logs l JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 20');
        }
        Response::success($stmt->fetchAll());
    } catch (PDOException $e) {
        Response::error('500_INTERNAL_ERROR', 'Failed to fetch logs', ['details' => $e->getMessage()], 500);
    }
}
