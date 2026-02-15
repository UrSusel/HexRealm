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

    // Quest system tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS quests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        shop_type VARCHAR(50) DEFAULT NULL,
        required_items JSON DEFAULT NULL,
        reward_gold INT DEFAULT 0,
        reward_reputation INT DEFAULT 1,
        min_level INT DEFAULT 1,
        max_level INT DEFAULT NULL,
        guild_required BOOLEAN DEFAULT FALSE,
        repeatable BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add guild_required column if it doesn't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE quests ADD COLUMN guild_required BOOLEAN DEFAULT FALSE AFTER max_level");
    } catch (PDOException $e) {
        // Column already exists, ignore error
        if ($e->getCode() != '42S21') {
            throw $e;
        }
    }
    
    // Ensure existing quests have correct guild_required values
    $pdo->exec("UPDATE quests SET guild_required = FALSE WHERE id BETWEEN 1 AND 7");
    $pdo->exec("UPDATE quests SET guild_required = TRUE WHERE id BETWEEN 8 AND 12");

    $pdo->exec("CREATE TABLE IF NOT EXISTS character_quests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        quest_id INT NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        progress JSON DEFAULT NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
        FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE,
        UNIQUE KEY idx_char_quest_active (character_id, quest_id, status)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reputation (
        character_id INT NOT NULL PRIMARY KEY,
        points INT DEFAULT 0,
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS guilds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        required_reputation INT DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS guild_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guild_id INT NOT NULL,
        character_id INT NOT NULL,
        rank VARCHAR(50) DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE,
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
        UNIQUE KEY idx_char_guild (character_id, guild_id)
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

    // Insert initial quests
    $pdo->exec("INSERT INTO quests (id, title, description, shop_type, required_items, reward_gold, reward_reputation, min_level, max_level, guild_required, repeatable) VALUES
        (1, 'Rat Problem', 'The village sewers are infested with rats. Bring me 5 rat tails.', 'blacksmith', '[{\"id\": 20, \"quantity\": 5}]', 30, 1, 1, 10, FALSE, TRUE),
        (2, 'Goblin Hunt', 'Goblins are raiding travelers. Collect 3 goblin ears as proof.', 'blacksmith', '[{\"id\": 21, \"quantity\": 3}]', 80, 2, 3, 10, FALSE, TRUE),
        (3, 'Desert Bandits', 'Clear the desert road of bandits. Bring 2 insignias.', 'armorer', '[{\"id\": 22, \"quantity\": 2}]', 150, 3, 8, 10, FALSE, TRUE),
        (4, 'Lava Cores Needed', 'I need lava cores for my research. Bring me 1.', 'clergy', '[{\"id\": 23, \"quantity\": 1}]', 300, 4, 12, NULL, FALSE, FALSE),
        (5, 'Demon Threat', 'A demon has been sighted. Bring its horn as proof of death.', 'clergy', '[{\"id\": 24, \"quantity\": 1}]', 500, 5, 15, NULL, FALSE, FALSE),
        (6, 'Leather Supplies', 'I need materials. Bring me 10 rat tails.', 'leathersmith', '[{\"id\": 20, \"quantity\": 10}]', 40, 1, 1, 8, FALSE, FALSE),
        (7, 'Scavenger Hunt', 'Collect various monster parts: 2 rat tails, 2 goblin ears.', 'leathersmith', '[{\"id\": 20, \"quantity\": 2}, {\"id\": 21, \"quantity\": 2}]', 100, 2, 5, 12, FALSE, FALSE),
        (8, 'Guild Contract: Goblin Extermination', 'The guild needs goblin ears for a bounty. Collect 10 ears.', 'blacksmith', '[{\"id\": 21, \"quantity\": 10}]', 800, 5, 10, NULL, TRUE, TRUE),
        (9, 'Guild Contract: Bandit Cleanup', 'Clear the roads of bandits. Bring 5 insignias.', 'armorer', '[{\"id\": 22, \"quantity\": 5}]', 1500, 8, 12, NULL, TRUE, TRUE),
        (10, 'Guild Contract: Lava Core Collection', 'The guild needs lava cores for enchanting. Bring 3.', 'clergy', '[{\"id\": 23, \"quantity\": 3}]', 2500, 10, 15, NULL, TRUE, TRUE),
        (11, 'Guild Contract: Demon Slayer', 'Hunt demons for the guild. Bring 2 demon horns.', 'clergy', '[{\"id\": 24, \"quantity\": 2}]', 5000, 15, 18, NULL, TRUE, TRUE),
        (12, 'Guild Contract: Supply Run', 'Gather materials for guild crafters: 15 rat tails, 8 goblin ears.', 'leathersmith', '[{\"id\": 20, \"quantity\": 15}, {\"id\": 21, \"quantity\": 8}]', 1200, 7, 10, NULL, TRUE, TRUE),
        (13, 'Guild Contract: Rat Invasion', 'The rats have returned stronger. Bring 20 rat tails.', 'blacksmith', '[{\"id\": 20, \"quantity\": 20}]', 500, 6, 5, NULL, TRUE, TRUE),
        (14, 'Guild Contract: Goblin Uprising', 'Goblins are multiplying. Collect 15 goblin ears.', 'blacksmith', '[{\"id\": 21, \"quantity\": 15}]', 900, 7, 6, NULL, TRUE, TRUE),
        (15, 'Guild Contract: Bandit Kings', 'The bandit kings must fall. Bring 8 insignias.', 'armorer', '[{\"id\": 22, \"quantity\": 8}]', 1800, 9, 10, NULL, TRUE, TRUE),
        (16, 'Guild Contract: Lava Harvesting', 'We need more lava cores for weapons. Bring 5.', 'clergy', '[{\"id\": 23, \"quantity\": 5}]', 3000, 8, 12, NULL, TRUE, TRUE),
        (17, 'Guild Contract: Demon Hunt', 'Demons are spreading chaos. Bring 4 demon horns.', 'clergy', '[{\"id\": 24, \"quantity\": 4}]', 4500, 10, 14, NULL, TRUE, TRUE),
        (18, 'Guild Contract: Mixed Materials', 'Gather 8 rat tails, 6 goblin ears, and 3 insignias.', 'leathersmith', '[{\"id\": 20, \"quantity\": 8}, {\"id\": 21, \"quantity\": 6}, {\"id\": 22, \"quantity\": 3}]', 2000, 8, 8, NULL, TRUE, TRUE),
        (19, 'Guild Contract: Monster Mastery', 'Prove your worth: 10 rat tails, 10 goblin ears, 5 insignias, 2 lava cores.', 'leathersmith', '[{\"id\": 20, \"quantity\": 10}, {\"id\": 21, \"quantity\": 10}, {\"id\": 22, \"quantity\": 5}, {\"id\": 23, \"quantity\": 2}]', 3500, 12, 12, NULL, TRUE, TRUE),
        (20, 'Guild Contract: Legendary Hunt', 'Hunt the most dangerous creatures: 5 lava cores and 3 demon horns.', 'clergy', '[{\"id\": 23, \"quantity\": 5}, {\"id\": 24, \"quantity\": 3}]', 6000, 14, 16, NULL, TRUE, TRUE),
        (21, 'Guild Contract: Final Trial', 'The ultimate challenge: 25 rat tails, 15 goblin ears, 10 insignias, 3 lava cores, 2 demon horns.', 'leathersmith', '[{\"id\": 20, \"quantity\": 25}, {\"id\": 21, \"quantity\": 15}, {\"id\": 22, \"quantity\": 10}, {\"id\": 23, \"quantity\": 3}, {\"id\": 24, \"quantity\": 2}]', 5000, 15, 18, NULL, TRUE, TRUE),
        (22, 'Guild Contract: Elite Extermination', 'Eliminate elite monsters: 12 goblin ears, 6 lava cores, and 4 demon horns.', 'clergy', '[{\"id\": 21, \"quantity\": 12}, {\"id\": 23, \"quantity\": 6}, {\"id\": 24, \"quantity\": 4}]', 7000, 16, 17, NULL, TRUE, TRUE)
        ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), required_items=VALUES(required_items), reward_gold=VALUES(reward_gold), reward_reputation=VALUES(reward_reputation), guild_required=VALUES(guild_required), repeatable=VALUES(repeatable), max_level=VALUES(max_level)
    ");

    // Insert guilds
    $pdo->exec("INSERT INTO guilds (id, name, description, required_reputation) VALUES
        (1, 'Warriors Guild', 'A guild for brave warriors seeking glory in battle.', 10),
        (2, 'Mages Collegium', 'An academy for arcane practitioners and scholars.', 10),
        (3, 'Thieves Brotherhood', 'A secretive organization of rogues and assassins.', 10),
        (4, 'Merchants Union', 'A trading guild for those who prefer gold to glory.', 15)
        ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), required_reputation=VALUES(required_reputation)
    ");

    // One-time price scaling
    $pdo->exec("CREATE TABLE IF NOT EXISTS game_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    )");

    $priceScaleKey = 'prices_scaled_v1';
    $stmt = $pdo->prepare("SELECT setting_value FROM game_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$priceScaleKey]);
    $alreadyScaled = $stmt->fetchColumn();

    if (!$alreadyScaled) {
        // Scale prices: 10x for most, 4x for clergy items (7,8), 3x for drops.
        $pdo->exec("UPDATE items SET price = price * 10 WHERE id NOT IN (7, 8) AND type <> 'drop'");
        $pdo->exec("UPDATE items SET price = price * 4 WHERE id IN (7, 8)");
        $pdo->exec("UPDATE items SET price = price * 3 WHERE type = 'drop'");

        $pdo->prepare("INSERT INTO game_settings (setting_key, setting_value) VALUES (?, ?)")
            ->execute([$priceScaleKey, '1']);
    }

    $priceCurveKey = 'price_curve_v2';
    $stmt = $pdo->prepare("SELECT setting_value FROM game_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$priceCurveKey]);
    $curveApplied = $stmt->fetchColumn();

    if (!$curveApplied) {
        // Steeper price curve based on item power (excluding drops).
        $pdo->exec("UPDATE items SET price = ROUND(price * CASE
            WHEN power >= 30 THEN 4.5
            WHEN power >= 25 THEN 3.6
            WHEN power >= 20 THEN 3.0
            WHEN power >= 15 THEN 2.3
            WHEN power >= 10 THEN 1.8
            WHEN power >= 5 THEN 1.35
            ELSE 1.15
        END) WHERE type <> 'drop'");

        $pdo->prepare("INSERT INTO game_settings (setting_key, setting_value) VALUES (?, ?)")
            ->execute([$priceCurveKey, '1']);
    }

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
            if ($r > 45) $type = 'grass2';
            if ($r > 72) $type = 'forest';
            if ($r > 90) $type = 'mountain';
            if ($r > 97) $type = 'water';

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