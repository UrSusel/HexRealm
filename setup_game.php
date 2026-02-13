<?php
require 'db.php';

try {
    function getNeighbors($x, $y, $width, $height) {
        $offsets = ($y % 2 != 0)
            ? [[1,0],[1,-1],[0,-1],[-1,0],[0,1],[1,1]]
            : [[1,0],[0,-1],[-1,-1],[-1,0],[-1,1],[0,1]];
        $neighbors = [];
        foreach ($offsets as $o) {
            $nx = $x + $o[0];
            $ny = $y + $o[1];
            if ($nx >= 0 && $ny >= 0 && $nx < $width && $ny < $height) {
                $neighbors[] = [$nx, $ny];
            }
        }
        return $neighbors;
    }

    function applyHills(&$tiles, $width, $height) {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($tiles[$y][$x] !== 'mountain') continue;
                foreach (getNeighbors($x, $y, $width, $height) as $n) {
                    $nx = $n[0]; $ny = $n[1];
                    $t = $tiles[$ny][$nx];
                    if (in_array($t, ['grass', 'grass2', 'forest'], true)) {
                        $tiles[$ny][$nx] = (rand(0, 1) === 0) ? 'hills' : 'hills2';
                    }
                }
            }
        }
    }

    function applyFarmlands(&$tiles, $width, $height) {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($tiles[$y][$x] !== 'city_village') continue;
                foreach (getNeighbors($x, $y, $width, $height) as $n) {
                    $nx = $n[0]; $ny = $n[1];
                    $t = $tiles[$ny][$nx];
                    if (in_array($t, ['grass', 'grass2', 'forest', 'hills', 'hills2'], true)) {
                        $tiles[$ny][$nx] = 'farmlands';
                    }
                }
            }
        }
    }
    // Create tables if they don't exist (non-destructive)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS worlds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        width INT NOT NULL,
        height INT NOT NULL,
        is_tutorial BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS characters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(50) DEFAULT 'Nameless',
        class_id INT DEFAULT NULL,
        hp INT DEFAULT 100,
        max_hp INT DEFAULT 100,
        energy INT DEFAULT 10,
        max_energy INT DEFAULT 10,
        base_attack INT DEFAULT 1,
        base_defense INT DEFAULT 0,
        stat_points INT DEFAULT 0,
        skill_points INT DEFAULT 0,
        pos_x INT DEFAULT 0,
        pos_y INT DEFAULT 0,
        world_id INT DEFAULT 1,
        tutorial_completed BOOLEAN DEFAULT FALSE,
        xp INT DEFAULT 0,
        max_xp INT DEFAULT 100,
        level INT DEFAULT 1,
        steps_buffer INT DEFAULT 0,
        in_combat BOOLEAN DEFAULT FALSE,
        enemy_hp INT DEFAULT 0,
        enemy_max_hp INT DEFAULT 0,
        combat_state TEXT DEFAULT NULL,
        gold INT DEFAULT 0,
        duel_id INT DEFAULT NULL,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (world_id) REFERENCES worlds(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        base_hp INT DEFAULT 100,
        base_energy INT DEFAULT 10,
        description TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        type VARCHAR(50) NOT NULL,
        power INT DEFAULT 0,
        optimal_class_id INT,
        icon VARCHAR(10),
        price INT DEFAULT 10,
        rarity VARCHAR(20) DEFAULT 'common',
        description TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS duel_requests (id INT AUTO_INCREMENT PRIMARY KEY, challenger_id INT, target_id INT, status VARCHAR(20) DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS active_duels (id INT AUTO_INCREMENT PRIMARY KEY, player1_id INT, player2_id INT, current_turn_id INT, combat_state TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, turn_start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT DEFAULT 1,
        is_equipped BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (character_id) REFERENCES characters(id),
        FOREIGN KEY (item_id) REFERENCES items(id),
        UNIQUE KEY idx_char_item (character_id, item_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS map_tiles (
        world_id INT NOT NULL,
        x INT NOT NULL,
        y INT NOT NULL,
        type VARCHAR(20) NOT NULL,
        PRIMARY KEY (world_id, x, y),
        FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
    )");

    // New: store last known position per character per world
    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_positions (
        character_id INT NOT NULL,
        world_id INT NOT NULL,
        pos_x INT NOT NULL DEFAULT 0,
        pos_y INT NOT NULL DEFAULT 0,
        PRIMARY KEY (character_id, world_id),
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
        FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
    )");

    // --- Remove ONLY the tutorial world (id=1) and its tiles ---
    $pdo->prepare("DELETE FROM map_tiles WHERE world_id = ?")->execute([1]);
    $pdo->prepare("DELETE FROM worlds WHERE id = ?")->execute([1]);

    // Insert (or recreate) tutorial world with id = 1
    $pdo->prepare("INSERT INTO worlds (id, name, width, height, is_tutorial) VALUES (1, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE name = VALUES(name), width = VALUES(width), height = VALUES(height), is_tutorial = VALUES(is_tutorial)")
        ->execute(['Tutorial Island', 15, 15]);

    // Ensure a default user and character exist (idempotent)
    $pdo->prepare("INSERT INTO users (id, username, password) VALUES (1, 'Tester', 'admin')
        ON DUPLICATE KEY UPDATE username = VALUES(username), password = VALUES(password)")
        ->execute();

    $pdo->prepare("INSERT INTO characters (id, user_id, name, hp, max_hp, energy, max_energy, base_attack, world_id, tutorial_completed)
        VALUES (1, 1, 'Hero', 100, 100, 10, 10, 1, 1, 0)
        ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), name=VALUES(name), hp=VALUES(hp), max_hp=VALUES(max_hp),
            energy=VALUES(energy), max_energy=VALUES(max_energy), base_attack=VALUES(base_attack), world_id=VALUES(world_id), tutorial_completed=VALUES(tutorial_completed)")
        ->execute();

    // Insert classes/items if missing (safe, will ignore duplicates)
    $pdo->exec("INSERT INTO classes (id, name, base_hp, base_energy, description) VALUES
        (1, 'Warrior', 150, 8, 'Master of the sword.'),
        (2, 'Mage', 80, 12, 'Wields magic.'),
        (3, 'Rogue', 100, 10, 'Fast and agile.') ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");

    $pdo->exec("INSERT INTO items (id, name, type, power, optimal_class_id, icon, price, rarity, description) VALUES
        (1, 'Rusty Sword', 'weapon', 10, 1, '⚔️', 35, 'common', 'A basic rusty sword.'),
        (2, 'Old Staff', 'weapon', 12, 2, '🪄', 35, 'common', 'A wooden staff.'),
        (3, 'Dagger', 'weapon', 9, 3, '🗡️', 35, 'common', 'Sharp but small.'),
        (4, 'Leather Jacket', 'armor', 5, 3, '👕', 45, 'common', 'Basic protection.'),
        (5, 'Plate Armor', 'armor', 15, 1, '🛡️', 45, 'common', 'Heavy iron armor.'),
        (6, 'Apprentice Robe', 'armor', 3, 2, '👘', 45, 'common', 'Cloth robe.'),
        (7, 'Health Potion', 'consumable', 50, NULL, '🧪', 25, 'uncommon', 'Heals 50 HP'),
        (8, 'Bandage', 'consumable', 20, NULL, '🩹', 5, 'common', 'Heals 20 HP'),
        (20, 'Rat Tail', 'drop', 0, NULL, '🐀', 4, 'common', 'A tail from a sewer rat.'),
        (21, 'Goblin Ear', 'drop', 0, NULL, '👂', 12, 'uncommon', 'A trophy from a goblin.'),
        (22, 'Bandit Insignia', 'drop', 0, NULL, '🎖️', 25, 'rare', 'Stolen from a desert bandit.'),
        (23, 'Lava Core', 'drop', 0, NULL, '🔥', 50, 'rare', 'Warm to the touch.'),
        (24, 'Demon Horn', 'drop', 0, NULL, '😈', 120, 'very_rare', 'Radiates dark energy.')
        ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), rarity=VALUES(rarity), description=VALUES(description)
    ");

    // Ensure the tutorial character has basic consumables (delete specific items then reinsert to avoid duplicates)
    $pdo->prepare("DELETE FROM inventory WHERE character_id = ? AND item_id IN (7,8)")->execute([1]);
    $pdo->prepare("INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, 7, 3), (?, 8, 3)")->execute([1,1]);

    // GENERATE MAP FOR TUTORIAL (world_id = 1)
    $tiles = [];
    for ($y = 0; $y < 15; $y++) {
        $row = [];
        for ($x = 0; $x < 15; $x++) {
            $type = 'grass';
            $r = rand(1, 100);
            if ($r > 50) $type = 'grass2';
            if ($r > 75) $type = 'forest';
            if ($r > 90) $type = 'mountain';
            if ($r > 96) $type = 'water';

            // Safe starting zone
            if ($x < 3 && $y < 3) $type = 'grass';
            if ($x == 0 && $y == 0) $type = 'city_village';

            $row[] = $type;
        }
        $tiles[] = $row;
    }

    applyHills($tiles, 15, 15);
    applyFarmlands($tiles, 15, 15);

    $stmt = $pdo->prepare("INSERT INTO map_tiles (world_id, x, y, type) VALUES (1, ?, ?, ?)");
    for ($y = 0; $y < 15; $y++) {
        for ($x = 0; $x < 15; $x++) {
            $stmt->execute([$x, $y, $tiles[$y][$x]]);
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<h1 style='color:green'>✅ Done! (Tutorial world recreated)</h1>";
    echo "<a href='index.php'>RETURN TO GAME</a>";

} catch (PDOException $e) {
    die("Błąd SQL: " . $e->getMessage());
}
?>