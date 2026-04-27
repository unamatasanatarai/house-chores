<?php

require_once __DIR__ . '/../public/api/db.php';

function generateUuid() {
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
}

try {
    $pdo = Database::getConnection();

    echo "🧹 Clearing existing data...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE activity_logs");
    $pdo->exec("TRUNCATE chores");
    $pdo->exec("TRUNCATE users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "👤 Creating users...\n";
    $users = [
        ['id' => generateUuid(), 'name' => 'Mom'],
        ['id' => generateUuid(), 'name' => 'Dad'],
        ['id' => generateUuid(), 'name' => 'Sarah'],
        ['id' => generateUuid(), 'name' => 'Leo']
    ];

    $userStmt = $pdo->prepare("INSERT INTO users (id, name) VALUES (?, ?)");
    foreach ($users as $user) {
        $userStmt->execute([$user['id'], $user['name']]);
    }

    echo "📋 Seeding 40 chores...\n";
    $choreTemplates = [
        ['title' => 'Vacuum the living room', 'desc' => 'Make sure to get under the sofa.'],
        ['title' => 'Empty the dishwasher', 'desc' => 'Stack carefully, please.'],
        ['title' => 'Mow the lawn', 'desc' => 'Edges too!'],
        ['title' => 'Clean the bathroom', 'desc' => 'Don\'t forget the mirror.'],
        ['title' => 'Feed the cat', 'desc' => 'Half a can of wet food, full bowl of water.'],
        ['title' => 'Water the plants', 'desc' => 'The ones on the balcony need extra love.'],
        ['title' => 'Take out the recycling', 'desc' => 'Blue bin is full.'],
        ['title' => 'Dust the bookshelves', 'desc' => 'Use the microfiber cloth.'],
        ['title' => 'Wipe down the kitchen counters', 'desc' => 'Use the anti-bacterial spray.'],
        ['title' => 'Organize the shoe rack', 'desc' => 'It\'s a mess again.'],
        ['title' => 'Fold the laundry', 'desc' => 'The basket is on the bed.'],
        ['title' => 'Sweep the porch', 'desc' => 'Lots of leaves after the wind.'],
        ['title' => 'Clean the windows', 'desc' => 'Just the ones in the living room.'],
        ['title' => 'Wash the car', 'desc' => 'Inside and out.'],
        ['title' => 'Cook dinner', 'desc' => 'Pasta night?'],
        ['title' => 'Scrub the kitchen floor', 'desc' => 'Use the new mop.'],
        ['title' => 'Empty all small trash cans', 'desc' => 'Office, bathroom, and bedroom.'],
        ['title' => 'Organize the pantry', 'desc' => 'Check for expired items.'],
        ['title' => 'Clean out the fridge', 'desc' => 'Throw away anything fuzzy.'],
        ['title' => 'Iron the shirts', 'desc' => 'For the week ahead.'],
    ];

    $choreStmt = $pdo->prepare("INSERT INTO chores (id, title, description, status, created_by, claimed_by, completed_by, due_date, created_at, claimed_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (id, chore_id, user_id, action, created_at) VALUES (?, ?, ?, ?, ?)");

    for ($i = 0; $i < 40; $i++) {
        $template = $choreTemplates[$i % count($choreTemplates)];
        $id = generateUuid();
        $creator = $users[array_rand($users)];
        
        $status = 'available';
        $claimedBy = null;
        $completedBy = null;
        $claimedAt = null;
        $completedAt = null;
        
        $roll = rand(1, 100);
        
        if ($roll > 70) {
            $status = 'completed';
            $claimedBy = $users[array_rand($users)]['id'];
            $completedBy = $claimedBy;
            $claimedAt = date('Y-m-d H:i:s', strtotime('-1 day'));
            $completedAt = date('Y-m-d H:i:s', strtotime('-12 hours'));
        } elseif ($roll > 40) {
            $status = 'claimed';
            $claimedBy = $users[array_rand($users)]['id'];
            $claimedAt = date('Y-m-d H:i:s', strtotime('-6 hours'));
        } elseif ($roll > 30) {
            $status = 'archived';
            $completedBy = $users[array_rand($users)]['id'];
            $completedAt = date('Y-m-d H:i:s', strtotime('-2 days'));
        }

        // Determine Due Date
        $dueType = rand(1, 3);
        if ($dueType === 1) { // Overdue
            $dueDate = date('Y-m-d', strtotime('-' . rand(1, 5) . ' days'));
        } elseif ($dueType === 2) { // Today
            $dueDate = date('Y-m-d');
        } else { // Future
            $dueDate = date('Y-m-d', strtotime('+' . rand(1, 7) . ' days'));
        }

        $createdAt = date('Y-m-d H:i:s', strtotime('-' . rand(3, 10) . ' days'));

        $choreStmt->execute([
            $id,
            $template['title'] . ($i > 19 ? " (Round " . ceil(($i+1)/20) . ")" : ""),
            $template['desc'],
            $status,
            $creator['id'],
            $claimedBy,
            $completedBy,
            $dueDate,
            $createdAt,
            $claimedAt,
            $completedAt
        ]);

        // Basic Log for creation
        $logStmt->execute([generateUuid(), $id, $creator['id'], 'created', $createdAt]);
        if ($claimedAt) {
            $logStmt->execute([generateUuid(), $id, $claimedBy, 'claimed', $claimedAt]);
        }
        if ($completedAt) {
            $logStmt->execute([generateUuid(), $id, $completedBy, 'completed', $completedAt]);
        }
    }

    echo "✨ Seeding complete! 4 users and 40 chores created.\n";

} catch (Exception $e) {
    echo "❌ Error during seeding: " . $e->getMessage() . "\n";
}
