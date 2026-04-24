<?php
require_once __DIR__ . '/config.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

$stmt = $pdo->query("SELECT migration_name FROM migrations");
$executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $filename = basename($file);

    if (!in_array($filename, $executed)) {
        echo "Running migration: $filename... ";

        $sql = file_get_contents($file);

        try {
            $pdo->exec($sql);

            $stmt = $pdo->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $stmt->execute([$filename]);

            echo "DONE\n";
            $count++;
        } catch (PDOException $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

echo $count > 0 ? "Successfully applied $count migration(s).\n" : "Database is up to date.\n";
