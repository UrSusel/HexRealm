<?php
require 'db.php';
header('Content-Type: application/json');
ini_set('display_errors', '0');
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';  // <-- ADD THIS LINE

// --- HELPER: Check remembered login via cookie FIRST ---
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rpg_remember'])) {
    $token = $_COOKIE['rpg_remember'];
    if (strpos($token, ':') !== false) {
        list($storedUserId, $hash) = explode(':', $token, 2);
        $expectedHash = hash_hmac('sha256', $storedUserId, 'rpg_secret_key_change_in_production');
        if (hash_equals($expectedHash, $hash)) {
            $_SESSION['user_id'] = (int)$storedUserId;
        }
    }
}

$userId = $_SESSION['user_id'] ?? null;

// --- AUTH ENDPOINTS ---

if ($action === 'register_account') {
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $password2 = $input['password2'] ?? '';
    
    if (strlen($username) < 3 || strlen($password) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password must be at least 3 characters.']); exit;
    }
    if ($password !== $password2) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']); exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Username already taken.']); exit;
    }
    
    $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hashedPwd]);
        $newUserId = $pdo->lastInsertId();
        $_SESSION['user_id'] = $newUserId;
        echo json_encode(['status' => 'success', 'user_id' => $newUserId]); exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']); exit;
    }
}

if ($action === 'login_account') {
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $rememberMe = $input['remember_me'] ?? false;
    
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']); exit;
    }
    
    $_SESSION['user_id'] = $user['id'];
    
    // Set remember-me cookie if requested (7 days = 604800 seconds)
    if ($rememberMe) {
        $hash = hash_hmac('sha256', $user['id'], 'rpg_secret_key_change_in_production');
        $token = $user['id'] . ':' . $hash;
        setcookie('rpg_remember', $token, time() + 604800, '/', '', false, true);
    }
    
    echo json_encode(['status' => 'success', 'user_id' => $user['id']]); exit;
}

if ($action === 'logout_account') {
    if (isset($_SESSION['char_id'])) {
        $pdo->prepare("UPDATE characters SET last_seen = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")->execute([(int)$_SESSION['char_id']]);
    }
    session_destroy();
    setcookie('rpg_remember', '', time() - 3600, '/');
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'check_remembered_login') {
    if ($userId) {
        echo json_encode(['status' => 'success', 'user_id' => $userId]); exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']); exit;
    }
}

// --- REQUIRE LOGIN FOR REST ---
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']); exit;
}

// Use session character_id if available, otherwise fetch first character
$charId = $_SESSION['char_id'] ?? null;
if (!$charId) {
    $stmt = $pdo->prepare("SELECT id FROM characters WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $charId = $row['id'] ?? 0;
}

// Pobranie postaci
$stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$charId, $userId]);
$char = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$char && $action !== 'select_class' && $action !== 'get_characters' && $action !== 'create_character' && $action !== 'select_character') {
    echo json_encode(['status' => 'error', 'message' => 'No character found']); exit;
}

$STEPS_PER_ENERGY = 10; 
$MAX_SPEED_NORMAL = 5;
$MAX_SPEED_EXHAUSTED = 1;
$COMBAT_DISABLED = false; // Combat enabled
$STAMINA_DISABLED = false; // Stamina drain enabled

// --- FUNKCJE POMOCNICZE ---
function offsetToCube($col, $row) {
    $col = (int)$col; $row = (int)$row;
    $q = $col - ($row - ($row & 1)) / 2;
    $r = $row;
    $s = -$q - $r;
    return ['q' => $q, 'r' => $r, 's' => $s];
}

function getGameDistance($x1, $y1, $x2, $y2) {
    $a = offsetToCube($x1, $y1);
    $b = offsetToCube($x2, $y2);
    $dist = (abs($a['q'] - $b['q']) + abs($a['r'] - $b['r']) + abs($a['s'] - $b['s'])) / 2;
    
    // Jeśli ruch jest poziomy (to samo Y), podwajamy koszt, bo wizualnie jest to daleko
    if ($y1 == $y2) return $dist * 2;
    return $dist;
}

function getWorldCapital($pdo, $worldId) {
    $stmt = $pdo->prepare("SELECT x, y FROM map_tiles WHERE world_id = ? AND type = 'city_capital' LIMIT 1");
    $stmt->execute([(int)$worldId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return [(int)$row['x'], (int)$row['y']];
    }
    return [0, 0];
}

function worldExists($pdo, $worldId) {
    if ($worldId <= 0) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM worlds WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$worldId]);
    return (bool)$stmt->fetchColumn();
}

function worldHasTiles($pdo, $worldId) {
    if ($worldId <= 0) return false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM map_tiles WHERE world_id = ?");
    $stmt->execute([(int)$worldId]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function ensureTutorialWorld($pdo) {
    $tutorialId = 1;

    $worldExists = worldExists($pdo, $tutorialId);
    if (!$worldExists) {
        $stmt = $pdo->prepare("INSERT INTO worlds (id, name, width, height, is_tutorial) VALUES (1, ?, 15, 15, 1)");
        $stmt->execute(['Tutorial World']);
    }

    if (worldHasTiles($pdo, $tutorialId)) {
        return $tutorialId;
    }

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

            if ($x < 3 && $y < 3) $type = 'grass';
            if ($x == 0 && $y == 0) $type = 'city_village';

            $row[] = $type;
        }
        $tiles[] = $row;
    }

    $tiles[7][7] = 'city_capital';

    $stmt = $pdo->prepare("INSERT INTO map_tiles (world_id, x, y, type) VALUES (1, ?, ?, ?)");
    for ($y = 0; $y < 15; $y++) {
        for ($x = 0; $x < 15; $x++) {
            $stmt->execute([$x, $y, $tiles[$y][$x]]);
        }
    }

    return $tutorialId;
}

// --- NOWE ENDPOINTY ŚWIATA ---

if ($action === 'get_characters') {
    $stmt = $pdo->prepare("SELECT id, name, class_id, level, hp, max_hp FROM characters WHERE user_id = ? ORDER BY id");
    $stmt->execute([$userId]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pad to 3 slots
    while (count($characters) < 3) {
        $characters[] = ['id' => null, 'name' => null, 'level' => 0];
    }
    
    echo json_encode(['status' => 'success', 'characters' => $characters]); exit;
}

if ($action === 'select_character') {
    $charIdToSelect = (int)$input['character_id'];
    $stmt = $pdo->prepare("SELECT id FROM characters WHERE id = ? AND user_id = ?");
    $stmt->execute([$charIdToSelect, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Character does not exist.']); exit;
    }
    if (isset($_SESSION['char_id'])) {
        $pdo->prepare("UPDATE characters SET last_seen = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")->execute([(int)$_SESSION['char_id']]);
    }
    $_SESSION['char_id'] = $charIdToSelect;
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'create_character') {
    $name = trim($input['name'] ?? '') ?: 'New character';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM characters WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() >= 3) {
        echo json_encode(['status' => 'error', 'message' => 'Maximum 3 characters.']); exit;
    }
    
    // Pick a valid world (prefer tutorial). Auto-create tutorial if needed.
    $worldStmt = $pdo->query("SELECT id FROM worlds ORDER BY is_tutorial DESC, id ASC LIMIT 1");
    $worldId = (int)$worldStmt->fetchColumn();
    if ($worldId <= 0) {
        $worldId = ensureTutorialWorld($pdo);
    }
    if ($worldId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'No worlds found. Run setup_game.php or import worlds.']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO characters (user_id, name, world_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $name, $worldId]);
        $newCharId = $pdo->lastInsertId();
        // Auto-select the new character
        if (isset($_SESSION['char_id'])) {
            $pdo->prepare("UPDATE characters SET last_seen = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")->execute([(int)$_SESSION['char_id']]);
        }
        $_SESSION['char_id'] = $newCharId;
        echo json_encode(['status' => 'success', 'character_id' => $newCharId]); exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']); exit;
    }
}

if ($action === 'delete_character') {
    $targetId = (int)$input['character_id'];
    
    $stmt = $pdo->prepare("SELECT id, in_combat FROM characters WHERE id = ? AND user_id = ?");
    $stmt->execute([$targetId, $userId]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['status' => 'error', 'message' => 'Access denied.']); exit; }
    
    if ($row['in_combat']) { echo json_encode(['status' => 'error', 'message' => 'Cannot delete character during combat!']); exit; }
    
    $pdo->prepare("DELETE FROM inventory WHERE character_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM saved_positions WHERE character_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM characters WHERE id = ?")->execute([$targetId]);
    
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'get_worlds_list') {
    $timeoutMinutes = 5;
    
    // Try to count with last_seen, fallback to counting all if column doesn't exist
    try {
        $stmt = $pdo->prepare("
            SELECT w.id, w.name, w.width, w.height,
            (SELECT COUNT(*) FROM characters c WHERE c.world_id = w.id AND c.last_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE)) as player_count
            FROM worlds w
            WHERE w.is_tutorial = 0
        ");
        $stmt->execute([$timeoutMinutes]);
    } catch (Exception $e) {
        // Fallback: count all characters if last_seen doesn't work
        $stmt = $pdo->query("
            SELECT w.id, w.name, w.width, w.height,
            (SELECT COUNT(*) FROM characters c WHERE c.world_id = w.id) as player_count
            FROM worlds w
            WHERE w.is_tutorial = 0
        ");
    }
    
    $worlds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($worlds as &$w) { 
        $w['player_limit'] = 20; 
    }
    
    echo json_encode(['status' => 'success', 'worlds' => $worlds]); exit;
}

if ($action === 'join_world') {
    $targetWorldId = (int)$input['world_id'];
    $bypassCityCheck = !empty($input['bypass_city']);
    
    $stmt = $pdo->prepare("SELECT id FROM worlds WHERE id = ?");
    $stmt->execute([$targetWorldId]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'World does not exist.']); exit;
    }

    // Check player limit (20)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM characters WHERE world_id = ?");
    $stmt->execute([$targetWorldId]);
    if ($stmt->fetchColumn() >= 20) {
        echo json_encode(['status' => 'error', 'message' => 'World is full (20/20).']); exit;
    }

    $curWorldId = (int)($char['world_id'] ?? 0);
    $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
        ->execute([$charId, $curWorldId, (int)$char['pos_x'], (int)$char['pos_y']]);

   
    $currentWorldValid = worldExists($pdo, $curWorldId) && worldHasTiles($pdo, $curWorldId);
    if ($curWorldId != 1 && !($bypassCityCheck && !$currentWorldValid)) {
        $posX = (int)($char['pos_x'] ?? 0);
        $posY = (int)($char['pos_y'] ?? 0);
        $tileStmt = $pdo->prepare("SELECT type FROM map_tiles WHERE x = ? AND y = ? AND world_id = ? LIMIT 1");
        $tileStmt->execute([$posX, $posY, $curWorldId]);
        $curTile = $tileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$curTile || strpos($curTile['type'], 'city') === false) {
            echo json_encode(['status' => 'error', 'message' => 'You must be in a city or village to change worlds.']); exit;
        }
    }

    
    $posStmt = $pdo->prepare("SELECT pos_x, pos_y FROM saved_positions WHERE character_id = ? AND world_id = ? LIMIT 1");
    $posStmt->execute([$charId, $targetWorldId]);
    $saved = $posStmt->fetch(PDO::FETCH_ASSOC);
    if ($saved) {
        $newX = (int)$saved['pos_x'];
        $newY = (int)$saved['pos_y'];
    } else {
        [$newX, $newY] = getWorldCapital($pdo, $targetWorldId);
    }

    
    $pdo->prepare("UPDATE characters SET world_id = ?, pos_x = ?, pos_y = ?, in_combat = 0, combat_state = NULL WHERE id = ?")
        ->execute([$targetWorldId, $newX, $newY, $charId]);

    echo json_encode(['status' => 'success']); exit;
}

// --- LOGIKA GRY ---

if ($action === 'select_class') {
    $classId = (int)$input['class_id'];
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $cls = $stmt->fetch();
    if (!$cls) {
        echo json_encode(['status' => 'error', 'message' => 'Class not found.']); exit;
    }

    // Prefer tutorial world if it exists; otherwise keep current world.
    $worldStmt = $pdo->query("SELECT id FROM worlds WHERE is_tutorial = 1 ORDER BY id ASC LIMIT 1");
    $tutorialWorldId = (int)$worldStmt->fetchColumn();
    $targetWorldId = $tutorialWorldId > 0 ? $tutorialWorldId : (int)($char['world_id'] ?? 1);

    $pdo->prepare("UPDATE characters SET class_id = ?, hp = ?, max_hp = ?, energy = ?, max_energy = ?, world_id = ?, tutorial_completed = 0 WHERE id = ?")
        ->execute([$classId, $cls['base_hp'], $cls['base_hp'], $cls['base_energy'], $cls['base_energy'], $targetWorldId, $charId]);
    
    $pdo->prepare("DELETE FROM inventory WHERE character_id = ?")->execute([$charId]); // Clear inventory
    $weaponId = $classId; $armorId = ($classId==1)?5:($classId==2?6:4);
    $pdo->prepare("INSERT INTO inventory (character_id, item_id, is_equipped) VALUES (?, ?, 1), (?, ?, 1)")->execute([$charId, $weaponId, $charId, $armorId]);
    $pdo->prepare("INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, 7, 3), (?, 8, 3)")->execute([$charId, $charId]);

    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'get_state') {
    // Attempt to update last_seen if column exists — ignore errors to remain backward compatible
    try {
        $pdo->prepare("UPDATE characters SET last_seen = NOW() WHERE id = ?")->execute([$charId]);
    } catch (Exception $e) {
        // ignore if column missing
    }

    $invStmt = $pdo->prepare("SELECT i.id as item_id, i.name, i.type, i.power, i.icon, i.description, COALESCE(inv.item_value, i.price) as price, i.rarity, inv.quantity, inv.is_equipped FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.character_id = ?");
    $invStmt->execute([$charId]);
    $inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAttack = 1 + ($char['base_attack'] ?? 1); 
    $totalDefense = ($char['base_defense'] ?? 0);
    foreach ($inventory as $item) {
        if ($item['is_equipped'] && $item['type'] == 'weapon') $totalAttack += $item['power'];
        if ($item['is_equipped'] && $item['type'] == 'armor') $totalDefense += $item['power'];
    }

    $char['attack'] = $totalAttack;
    $char['defense'] = $totalDefense;
    $char['inventory'] = $inventory;
    $char['speed'] = ($char['energy'] > 0) ? $MAX_SPEED_NORMAL : $MAX_SPEED_EXHAUSTED;
    
    // Pobierz nazwę świata (z fallbackiem na tutorial)
    $worldName = null;
    if (worldExists($pdo, (int)$char['world_id'])) {
        $wStmt = $pdo->prepare("SELECT name FROM worlds WHERE id = ?");
        $wStmt->execute([$char['world_id']]);
        $worldName = $wStmt->fetchColumn();
    }
    if (!$worldName || !worldHasTiles($pdo, (int)$char['world_id'])) {
        $fallbackWorldId = ensureTutorialWorld($pdo);
        if ($fallbackWorldId > 0) {
            [$spawnX, $spawnY] = getWorldCapital($pdo, $fallbackWorldId);
            $pdo->prepare("UPDATE characters SET world_id = ?, pos_x = ?, pos_y = ?, in_combat = 0, combat_state = NULL WHERE id = ?")
                ->execute([$fallbackWorldId, $spawnX, $spawnY, $charId]);
            $char['world_id'] = $fallbackWorldId;
            $char['pos_x'] = $spawnX;
            $char['pos_y'] = $spawnY;
            $wStmt = $pdo->prepare("SELECT name FROM worlds WHERE id = ?");
            $wStmt->execute([$fallbackWorldId]);
            $worldName = $wStmt->fetchColumn();
        } else {
            echo json_encode([
                'status' => 'error',
                'code' => 'world_missing',
                'message' => 'World not found or empty. Choose another world.',
                'allow_world_change' => true
            ]); exit;
        }
    }
    $char['world_name'] = $worldName;

    if ($COMBAT_DISABLED && $char['in_combat']) {
        $pdo->prepare("UPDATE characters SET in_combat = 0, combat_state = NULL, duel_id = NULL WHERE id = ?")->execute([$charId]);
        $char['in_combat'] = 0;
        $char['duel_id'] = null;
        $char['combat_state'] = null;
    } elseif ($char['in_combat'] && empty($char['combat_state'])) {
        $pdo->prepare("UPDATE characters SET in_combat = 0 WHERE id = ?")->execute([$charId]);
        $char['in_combat'] = 0;
    }

    // Check for active duel state
    if ($char['duel_id']) {
        $stmt = $pdo->prepare("SELECT * FROM active_duels WHERE id = ?");
        $stmt->execute([$char['duel_id']]);
        $duel = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($duel) {
            $char['in_combat'] = 1;
            $char['is_pvp'] = true;
            // We don't send full state here, client will poll get_duel_state
        } else {
            // Duel finished or invalid
            $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$charId]);
            $char['duel_id'] = null;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $char]);
    exit;
}

if ($action === 'get_map') {
    // Fog of War / Optimization: Fetch only area around player
    $rangeX = 10; // Covers ~20 width
    $rangeY = 14; // Covers ~28 height
    
    $minX = $char['pos_x'] - $rangeX; $maxX = $char['pos_x'] + $rangeX;
    $minY = $char['pos_y'] - $rangeY; $maxY = $char['pos_y'] + $rangeY;

    $stmt = $pdo->prepare("SELECT * FROM map_tiles WHERE world_id = ? AND x BETWEEN ? AND ? AND y BETWEEN ? AND ?");
    $stmt->execute([$char['world_id'], $minX, $maxX, $minY, $maxY]);
    $tiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$tiles) {
        echo json_encode([
            'status' => 'error',
            'code' => 'world_missing',
            'message' => 'World map missing. Choose another world.',
            'allow_world_change' => true
        ]); exit;
    }
    echo json_encode(['status' => 'success', 'tiles' => $tiles]); exit;
}

if ($action === 'move') {
    $targetX = (int)$input['x']; $targetY = (int)$input['y'];

    if ($char['hp'] <= 0) { echo json_encode(['status' => 'dead', 'message' => 'You are dead.']); exit; }
    if ($char['in_combat']) {
        if ($COMBAT_DISABLED) {
            $pdo->prepare("UPDATE characters SET in_combat = 0, combat_state = NULL, duel_id = NULL WHERE id = ?")->execute([$charId]);
            $char['in_combat'] = 0;
            $char['duel_id'] = null;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'You are in combat!']); exit;
        }
    }

    $currentSpeed = $STAMINA_DISABLED ? $MAX_SPEED_NORMAL : (($char['energy'] > 0) ? $MAX_SPEED_NORMAL : $MAX_SPEED_EXHAUSTED);
    $dist = getGameDistance($char['pos_x'], $char['pos_y'], $targetX, $targetY);
    
    if ($dist > $currentSpeed) { echo json_encode(['status' => 'error', 'message' => 'Too far!']); exit; }

    
    $tileStmt = $pdo->prepare("SELECT type FROM map_tiles WHERE x = ? AND y = ? AND world_id = ?");
    $tileStmt->execute([$targetX, $targetY, $char['world_id']]);
    $targetTile = $tileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetTile || $targetTile['type'] === 'water' || $targetTile['type'] === 'mountain') {
        echo json_encode(['status' => 'error', 'message' => 'Terrain inaccessible!']); exit;
    }

    $isSafe = (strpos($targetTile['type'], 'city') !== false);
    $encounter = false; $enemyHp = 0; $msg = "Traveling...";

    if ($isSafe) {
        // Economy Update: Cities only heal up to 50%
        $char['hp'] = max($char['hp'], floor($char['max_hp'] * 0.5)); $char['energy'] = $char['max_energy']; $char['steps_buffer'] = 0;
        $msg = "Resting in the city.";
    } else {
        if (!$STAMINA_DISABLED) {
            $char['steps_buffer'] += $dist;
            while ($char['steps_buffer'] >= $STEPS_PER_ENERGY) {
                if ($char['energy'] > 0) { $char['energy']--; $char['steps_buffer'] -= $STEPS_PER_ENERGY; } else break;
            }
        }

        
        $chance = 15;
        if ($char['world_id'] == 1 && $char['tutorial_completed'] == 0) {
            $chance = 35; // 35% szansy w tutorialu żeby szybko spotkać wroga
        }

        if (!$COMBAT_DISABLED && rand(1, 100) <= $chance) { 
            $encounter = true;
            
            // Monster Types Logic
            // Prioritize rats (standard) at low levels 1-4 (70% chance)
            if ($char['level'] <= 4) {
                $weightedTypes = ['standard' => 70];
                if ($char['level'] >= 2) $weightedTypes['green'] = 20;
                if ($char['level'] >= 3) $weightedTypes['yellow'] = 10;
            } elseif ($char['level'] <= 9) {
                // Easier progression from level 5-9
                $weightedTypes = ['standard' => 40];
                if ($char['level'] >= 5) $weightedTypes['green'] = 35;
                if ($char['level'] >= 5) $weightedTypes['yellow'] = 20;
                if ($char['level'] >= 7) $weightedTypes['orange'] = 15;
            } else {
                // Normal progression at level 10+
                $weightedTypes = ['standard' => 20];
                if ($char['level'] >= 2) $weightedTypes['green'] = 30;
                if ($char['level'] >= 3) $weightedTypes['yellow'] = 60;
                if ($char['level'] >= 5) $weightedTypes['orange'] = 70;
                if ($char['level'] >= 7) $weightedTypes['red'] = 60;
            }
            
            $rand = mt_rand(1, array_sum($weightedTypes));
            $cur = 0;
            $type = 'standard';
            foreach ($weightedTypes as $k => $w) {
                $cur += $w;
                if ($rand <= $cur) { $type = $k; break; }
            }
            $hpMult = 1.0; $dmgMult = 1.0; $heals = 0;
            $levelOffset = 0;
            $namesMap = [
                'standard' => 'Rat',
                'green' => 'Leaf Goblin',
                'yellow' => 'Desert Bandit',
                'orange' => 'Lava Golem',
                'red' => 'Blood Demon'
            ];
            $enemyName = $namesMap[$type] ?? 'Monster';
            
            if ($type === 'standard') { $levelOffset = rand(-1, 2); }
            elseif ($type === 'green') { $levelOffset = rand(-2, 0); $heals = 1; }
            elseif ($type === 'yellow') { $levelOffset = rand(-3, -1); $hpMult = 1.2; $dmgMult = 1.2; $heals = 2; }
            elseif ($type === 'orange') { $levelOffset = rand(-4, -2); $hpMult = 1.5; $dmgMult = 1.5; }
            elseif ($type === 'red') { $levelOffset = rand(-5, -3); $hpMult = 2.0; $dmgMult = 2.0; }
            
            $enemyLevel = max(1, $char['level'] + $levelOffset);
            $levelMult = 1 + ($enemyLevel * 0.10); // 10% stats per level
            
            $enemyHp = (int)(rand(30, 60) * $hpMult * $levelMult);
            
            $arenaTiles = [];
            for ($ay = 0; $ay < 5; $ay++) {
                for ($ax = 0; $ax < 7; $ax++) {
                    $r = rand(1, 100);
                    $atype = 'grass';
                    if ($r > 60) $atype = 'grass2';
                    if ($r > 90) $atype = 'water';
                    if (($ax == 1 && $ay == 2) || ($ax == 5 && $ay == 2)) $atype = 'grass';
                    $arenaTiles[] = ['x' => $ax, 'y' => $ay, 'type' => $atype];
                }
            }
            
            $combatState = [
                'player_pos' => ['x' => 1, 'y' => 2],
                'enemy_pos' => ['x' => 5, 'y' => 2],
                'tiles' => $arenaTiles,
                'turn' => 'player',
                'player_ap' => 2,
                'enemy_ap' => 2,
                'is_defending' => false,
                'enemy_type' => $type,
                'enemy_name' => $enemyName,
                'enemy_level' => $enemyLevel,
                'enemy_dmg_mult' => $dmgMult,
                'enemy_heals' => $heals
            ];
            
            $pdo->prepare("UPDATE characters SET in_combat = 1, enemy_hp = ?, enemy_max_hp = ?, pos_x = ?, pos_y = ?, energy = ?, steps_buffer = ?, combat_state = ? WHERE id = ?")
                ->execute([$enemyHp, $enemyHp, $targetX, $targetY, $char['energy'], $char['steps_buffer'], json_encode($combatState), $charId]);
            $msg = "⚔️ AMBUSH!";
        }
    }

    if (!$encounter) {
        $pdo->prepare("UPDATE characters SET pos_x = ?, pos_y = ?, hp = ?, energy = ?, steps_buffer = ? WHERE id = ?")
            ->execute([$targetX, $targetY, $char['hp'], $char['energy'], $char['steps_buffer'], $charId]);

        
        $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
            ->execute([$charId, (int)$char['world_id'], $targetX, $targetY]);
    } else {
        
        $pdo->prepare("UPDATE characters SET in_combat = 1, enemy_hp = ?, enemy_max_hp = ?, pos_x = ?, pos_y = ?, energy = ?, steps_buffer = ?, combat_state = ? WHERE id = ?")
            ->execute([$enemyHp, $enemyHp, $targetX, $targetY, $char['energy'], $char['steps_buffer'], json_encode($combatState), $charId]);

        $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
            ->execute([$charId, (int)$char['world_id'], $targetX, $targetY]);
        $msg = "⚔️ AMBUSH!";
    }

    // Fetch new local map tiles for Fog of War update
    $rangeX = 10; $rangeY = 14;
    $minX = $targetX - $rangeX; $maxX = $targetX + $rangeX;
    $minY = $targetY - $rangeY; $maxY = $targetY + $rangeY;
    
    $mapStmt = $pdo->prepare("SELECT * FROM map_tiles WHERE world_id = ? AND x BETWEEN ? AND ? AND y BETWEEN ? AND ?");
    $mapStmt->execute([$char['world_id'], $minX, $maxX, $minY, $maxY]);
    $localTiles = $mapStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success', 'new_x' => $targetX, 'new_y' => $targetY,
        'hp' => $char['hp'], 'energy' => $char['energy'], 'steps_buffer' => $char['steps_buffer'],
        'encounter' => $encounter, 'message' => $msg, 'enemy_hp' => $enemyHp,
        'local_tiles' => $localTiles
    ]);
    exit;
}

if ($action === 'respawn') {
    $stmt = $pdo->prepare("SELECT max_hp, max_energy, world_id FROM characters WHERE id = ?");
    $stmt->execute([$charId]);
    $stats = $stmt->fetch();
    $worldId = (int)$stats['world_id'];
    [$spawnX, $spawnY] = getWorldCapital($pdo, $worldId);
    $pdo->prepare("UPDATE characters SET hp = ?, energy = ?, steps_buffer = 0, pos_x = ?, pos_y = ?, in_combat = 0, combat_state = NULL WHERE id = ?")
        ->execute([$stats['max_hp'], $stats['max_energy'], $spawnX, $spawnY, $charId]);

    
    $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
        ->execute([$charId, $worldId, $spawnX, $spawnY]);

    echo json_encode(['status' => 'success']); exit;
}

// --- DUEL SYSTEM ---

if ($action === 'send_duel_request') {
    $targetId = (int)$input['target_id'];
    if ($targetId == $charId) { echo json_encode(['status' => 'error', 'message' => 'You cannot fight yourself.']); exit; }
    
    // Check distance
    $stmt = $pdo->prepare("SELECT pos_x, pos_y, in_combat, duel_id FROM characters WHERE id = ?");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    
    if (!$target) { echo json_encode(['status' => 'error', 'message' => 'Player does not exist.']); exit; }
    if ($target['in_combat'] || $target['duel_id']) { echo json_encode(['status' => 'error', 'message' => 'Player is busy fighting.']); exit; }
    
    $dist = getGameDistance($char['pos_x'], $char['pos_y'], $target['pos_x'], $target['pos_y']);
    if ($dist > 5) { echo json_encode(['status' => 'error', 'message' => 'Too far!']); exit; }
    
    // Check existing requests
    $stmt = $pdo->prepare("SELECT id FROM duel_requests WHERE challenger_id = ? AND target_id = ? AND status = 'pending'");
    $stmt->execute([$charId, $targetId]);
    if ($stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Challenge already sent.']); exit; }
    
    $pdo->prepare("INSERT INTO duel_requests (challenger_id, target_id) VALUES (?, ?)")->execute([$charId, $targetId]);
    echo json_encode(['status' => 'success', 'message' => 'Wyzwanie wysłane!']); exit;
}

if ($action === 'respond_duel_request') {
    $reqId = (int)$input['request_id'];
    $response = $input['response']; // 'accept' or 'reject'
    
    $stmt = $pdo->prepare("SELECT * FROM duel_requests WHERE id = ? AND target_id = ? AND status = 'pending'");
    $stmt->execute([$reqId, $charId]);
    $req = $stmt->fetch();
    
    if (!$req) { echo json_encode(['status' => 'error', 'message' => 'Challenge expired.']); exit; }
    
    if ($response === 'reject') {
        $pdo->prepare("UPDATE duel_requests SET status = 'rejected' WHERE id = ?")->execute([$reqId]);
        echo json_encode(['status' => 'success']); exit;
    }
    
    if ($response === 'accept') {
        // Initialize Duel
        $challengerId = $req['challenger_id'];
        
        // Check if challenger is online (fix for "offline" issue)
        $stmt = $pdo->prepare("SELECT id FROM characters WHERE id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        $stmt->execute([$challengerId]);
        if (!$stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Player is offline.']); exit; }
        
        // Create Arena
        $arenaTiles = [];
        for ($ay = 0; $ay < 5; $ay++) {
            for ($ax = 0; $ax < 7; $ax++) {
                $arenaTiles[] = ['x' => $ax, 'y' => $ay, 'type' => 'grass'];
            }
        }

        $nameStmt = $pdo->prepare("SELECT name FROM characters WHERE id = ?");
        $nameStmt->execute([$challengerId]);
        $challengerName = $nameStmt->fetchColumn() ?: 'Challenger';
        
        $starterId = (rand(0, 1) === 0) ? $challengerId : $charId;
        $p1Starts = ($starterId == $challengerId);
        $starterName = $p1Starts ? $challengerName : $char['name'];
        $combatState = [
            'p1_pos' => ['x' => 1, 'y' => 2], // Challenger
            'p2_pos' => ['x' => 5, 'y' => 2], // Target (You)
            'tiles' => $arenaTiles,
            'p1_ap' => $p1Starts ? 2 : 0,
            'p2_ap' => $p1Starts ? 0 : 2,
            'turn_id' => $starterId,
            'log' => 'Duel started! Starts: ' . $starterName
        ];
        
        $pdo->prepare("INSERT INTO active_duels (player1_id, player2_id, current_turn_id, combat_state, turn_start_time) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$challengerId, $charId, $starterId, json_encode($combatState)]);
        $duelId = $pdo->lastInsertId();
        
        // Update both players
        $pdo->prepare("UPDATE characters SET duel_id = ?, in_combat = 1 WHERE id = ?")->execute([$duelId, $challengerId]);
        $pdo->prepare("UPDATE characters SET duel_id = ?, in_combat = 1 WHERE id = ?")->execute([$duelId, $charId]);
        
        $pdo->prepare("UPDATE duel_requests SET status = 'accepted' WHERE id = ?")->execute([$reqId]);
        
        echo json_encode(['status' => 'success', 'duel_id' => $duelId]); exit;
    }
}

if ($action === 'get_duel_state') {
    if (!$char['duel_id']) { echo json_encode(['status' => 'ended', 'hp' => (int)$char['hp']]); exit; }
    
    // Update my last_seen so opponent knows I'm here
    $pdo->prepare("UPDATE characters SET last_seen = NOW() WHERE id = ?")->execute([$charId]);
    
    $stmt = $pdo->prepare("SELECT * FROM active_duels WHERE id = ?");
    $stmt->execute([$char['duel_id']]);
    $duel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$duel) { 
        // Duel deleted (ended)
        $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$charId]);
        
        // Return current HP so client knows if dead
        $stmt = $pdo->prepare("SELECT hp FROM characters WHERE id = ?");
        $stmt->execute([$charId]);
        $hp = (int)$stmt->fetchColumn();
        echo json_encode(['status' => 'ended', 'hp' => $hp]); exit; 
    }
    
    // 1. Check for Opponent Disconnect (40s timeout)
    $oppId = ($charId == $duel['player1_id']) ? $duel['player2_id'] : $duel['player1_id'];
    // Use SQL to compare times to avoid PHP/DB timezone mismatch
    $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, last_seen, NOW()) FROM characters WHERE id = ?");
    $stmt->execute([$oppId]);
    $secondsSinceLastSeen = (int)$stmt->fetchColumn();
    
    if ($secondsSinceLastSeen > 40) {
        // End duel due to disconnect
        $pdo->prepare("DELETE FROM active_duels WHERE id = ?")->execute([$duel['id']]);
        $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$duel['player1_id']]);
        $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$duel['player2_id']]);
        echo json_encode(['status' => 'ended', 'message' => 'Opponent disconnected!']); exit;
    }

    // 1b. Fix invalid turn state to avoid softlock
    $state = json_decode($duel['combat_state'], true);
    if (!is_array($state)) $state = [];
    $validTurn = in_array((int)$duel['current_turn_id'], [(int)$duel['player1_id'], (int)$duel['player2_id']], true);
    $stateChanged = false;
    if (!$validTurn) {
        $duel['current_turn_id'] = (int)$duel['player1_id'];
        $stateChanged = true;
    }
    if (!isset($state['p1_ap']) || !isset($state['p2_ap'])) {
        $p1Turn = ((int)$duel['current_turn_id'] === (int)$duel['player1_id']);
        $state['p1_ap'] = $p1Turn ? 2 : 0;
        $state['p2_ap'] = $p1Turn ? 0 : 2;
        $stateChanged = true;
    }
    if ($stateChanged) {
        $pdo->prepare("UPDATE active_duels SET current_turn_id = ?, combat_state = ?, turn_start_time = NOW() WHERE id = ?")
            ->execute([$duel['current_turn_id'], json_encode($state), $duel['id']]);
        $duel['combat_state'] = json_encode($state);
    }

    // 2. Check Turn Timer (30s limit)
    $turnStart = strtotime($duel['turn_start_time']);
    $elapsed = time() - $turnStart;
    if ($elapsed > 30) {
        // Force Switch Turn
        $state = json_decode($duel['combat_state'], true);
        $nextTurnId = ($duel['current_turn_id'] == $duel['player1_id']) ? $duel['player2_id'] : $duel['player1_id'];
        
        // Reset AP logic
        $isP1Next = ($nextTurnId == $duel['player1_id']);
        $state['p1_ap'] = $isP1Next ? 2 : 0;
        $state['p2_ap'] = $isP1Next ? 0 : 2;
        $state['log'] = "Time's up! Turn switch.";
        
        $pdo->prepare("UPDATE active_duels SET current_turn_id = ?, combat_state = ?, turn_start_time = NOW() WHERE id = ?")
            ->execute([$nextTurnId, json_encode($state), $duel['id']]);
            
        // Update local vars for response
        $duel['current_turn_id'] = $nextTurnId;
        $duel['combat_state'] = json_encode($state);
        $elapsed = 0;
    }
    
    $remaining = max(0, 30 - $elapsed);

    $state = json_decode($duel['combat_state'], true);
    $isP1 = ($charId == $duel['player1_id']);
    
    // Transform state to be relative to the viewer (like PvE)
    // "player" is ME, "enemy" is OPPONENT
    $clientState = [
        'turn' => ($duel['current_turn_id'] == $charId) ? 'player' : 'enemy',
        'player_ap' => $isP1 ? $state['p1_ap'] : $state['p2_ap'],
        'enemy_ap' => $isP1 ? $state['p2_ap'] : $state['p1_ap'],
        'player_pos' => $isP1 ? $state['p1_pos'] : $state['p2_pos'],
        'enemy_pos' => $isP1 ? $state['p2_pos'] : $state['p1_pos'],
        'tiles' => $state['tiles'],
        'log' => $state['log'] ?? ''
    ];
    
    // Get Enemy HP
    $enemyId = $isP1 ? $duel['player2_id'] : $duel['player1_id'];
    $stmt = $pdo->prepare("SELECT hp, max_hp, name FROM characters WHERE id = ?");
    $stmt->execute([$enemyId]);
    $enemy = $stmt->fetch();
    
    echo json_encode([
        'status' => 'success', 
        'combat_state' => $clientState, 
        'my_hp' => $char['hp'],
        'enemy_hp' => $enemy['hp'],
        'enemy_max_hp' => $enemy['max_hp'],
        'enemy_name' => $enemy['name'],
        'turn_remaining' => $remaining
    ]); exit;
}

if ($action === 'pvp_action') {
    $subAction = $input['sub_action']; // move, attack
    if (!$char['duel_id']) { echo json_encode(['status' => 'error', 'message' => 'No duel active']); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM active_duels WHERE id = ?");
    $stmt->execute([$char['duel_id']]);
    $duel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($duel['current_turn_id'] != $charId) { echo json_encode(['status' => 'error', 'message' => 'Not your turn!']); exit; }
    
    $state = json_decode($duel['combat_state'], true);
    $isP1 = ($charId == $duel['player1_id']);
    $myKey = $isP1 ? 'p1' : 'p2';
    $enKey = $isP1 ? 'p2' : 'p1';
    
    if ($subAction === 'move') {
        $tx = (int)$input['x']; $ty = (int)$input['y'];
        $myPos = $state[$myKey.'_pos'];
        $enPos = $state[$enKey.'_pos'];
        
        $dist = getGameDistance($myPos['x'], $myPos['y'], $tx, $ty);
        if ($dist > 2.2) { echo json_encode(['status' => 'error', 'message' => 'Too far']); exit; }
        if ($state[$myKey.'_ap'] < ceil($dist)) { echo json_encode(['status' => 'error', 'message' => 'Not enough AP']); exit; }
        if ($tx == $enPos['x'] && $ty == $enPos['y']) { echo json_encode(['status' => 'error', 'message' => 'Occupied']); exit; }
        
        $state[$myKey.'_pos'] = ['x' => $tx, 'y' => $ty];
        $state[$myKey.'_ap'] -= ceil($dist);
    }
    
    if ($subAction === 'attack') {
        if ($state[$myKey.'_ap'] < 2) { echo json_encode(['status' => 'error', 'message' => 'Not enough AP']); exit; }
        $myPos = $state[$myKey.'_pos']; $enPos = $state[$enKey.'_pos'];
        $dist = getGameDistance($myPos['x'], $myPos['y'], $enPos['x'], $enPos['y']);
        if ($dist > 2.2) { echo json_encode(['status' => 'error', 'message' => 'Too far']); exit; }
        
        // Get attacker weapon power
        $weaponStmt = $pdo->prepare("SELECT items.power FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND is_equipped = 1 AND items.type = 'weapon'");
        $weaponStmt->execute([$charId]);
        $weaponPower = $weaponStmt->fetchColumn() ?: 0;
        
        $rawDmg = rand(10, 15) + $char['base_attack'] + $weaponPower;
        $enemyId = $isP1 ? $duel['player2_id'] : $duel['player1_id'];
        
        // Check Enemy HP and armor for defense
        $stmt = $pdo->prepare("SELECT c.hp, c.name, c.base_defense, COALESCE(i.power, 0) as armor_power FROM characters c LEFT JOIN inventory inv ON inv.character_id = c.id AND inv.is_equipped = 1 LEFT JOIN items i ON inv.item_id = i.id AND i.type = 'armor' WHERE c.id = ?");
        $stmt->execute([$enemyId]);
        $enemy = $stmt->fetch();
        $enemyDef = (int)($enemy['base_defense'] ?? 0) + (int)($enemy['armor_power'] ?? 0);
        $dmg = max(1, $rawDmg - $enemyDef);
        $newHp = $enemy['hp'] - $dmg;
        
        if ($newHp <= 0) {
            // KILL - End Duel
            $pdo->prepare("UPDATE characters SET hp = 0, duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$enemyId]);
            $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$charId]);
            $pdo->prepare("DELETE FROM active_duels WHERE id = ?")->execute([$duel['id']]);
            echo json_encode(['status' => 'success', 'dmg' => $dmg, 'win' => true, 'log' => "You defeated player " . $enemy['name'] . "!"]); exit;
        } else {
            $pdo->prepare("UPDATE characters SET hp = ? WHERE id = ?")->execute([$newHp, $enemyId]);
            $state[$myKey.'_ap'] = 0;
            $state['log'] = "Player " . $char['name'] . " deals $dmg damage!";
        }
    }
    
    // End Turn Check
    $turnChanged = false;
    if ($state[$myKey.'_ap'] <= 0) {
        $duel['current_turn_id'] = $isP1 ? $duel['player2_id'] : $duel['player1_id'];
        $state[$enKey.'_ap'] = 2; // Reset AP for next player
        $turnChanged = true;
    }
    
    if ($turnChanged) {
        $pdo->prepare("UPDATE active_duels SET combat_state = ?, current_turn_id = ?, turn_start_time = NOW() WHERE id = ?")->execute([json_encode($state), $duel['current_turn_id'], $duel['id']]);
    } else {
        $pdo->prepare("UPDATE active_duels SET combat_state = ?, current_turn_id = ? WHERE id = ?")->execute([json_encode($state), $duel['current_turn_id'], $duel['id']]);
    }
    echo json_encode(['status' => 'success', 'dmg' => $dmg ?? 0]); exit;
}

// --- WALKA ---

if ($action === 'combat_move') {
    $tx = (int)$input['x']; $ty = (int)$input['y'];
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Enemy turn!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Not enough AP!']); exit; }

    $tileType = 'water';
    foreach ($cState['tiles'] as $t) { if ($t['x'] == $tx && $t['y'] == $ty) { $tileType = $t['type']; break; } }
    if ($tileType === 'water') { echo json_encode(['status' => 'error', 'message' => 'Water!']); exit; }
    if ($tx == $cState['enemy_pos']['x'] && $ty == $cState['enemy_pos']['y']) { echo json_encode(['status' => 'error', 'message' => 'Enemy is there!']); exit; }
    
    $moveDist = getGameDistance($cState['player_pos']['x'], $cState['player_pos']['y'], $tx, $ty);
    $isHorizontal = ($cState['player_pos']['y'] == $ty);
    $maxDist = $isHorizontal ? 2.2 : 1.1;
    if ($moveDist > $maxDist) { echo json_encode(['status' => 'error', 'message' => 'Too far.']); exit; }
    
    $apCost = (int)ceil($moveDist);
    if ($cState['player_ap'] < $apCost) { echo json_encode(['status' => 'error', 'message' => 'Not enough AP!']); exit; }
    
    $cState['player_ap'] -= $apCost;
    $cState['player_pos'] = ['x' => $tx, 'y' => $ty];
    if ($cState['player_ap'] <= 0) { $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2; }
    
    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState]); exit;
}

if ($action === 'combat_defend') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Enemy turn!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Not enough AP!']); exit; }
    $cState['player_ap'] -= 1;
    $cState['is_defending'] = true; 
    if ($cState['player_ap'] <= 0) { $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2; }
    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState, 'message' => '🛡️ Defensive stance! (-50% damage)']); exit;
}

if ($action === 'combat_attack') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['player_ap'] < 2) { echo json_encode(['status' => 'error', 'message' => 'Attack requires 2 AP!']); exit; }
    $dist = getGameDistance($cState['player_pos']['x'], $cState['player_pos']['y'], $cState['enemy_pos']['x'], $cState['enemy_pos']['y']);
    $isHorizontal = ($cState['player_pos']['y'] == $cState['enemy_pos']['y']);
    $maxDist = $isHorizontal ? 2.2 : 1.1;
    if ($dist > $maxDist) { echo json_encode(['status' => 'error', 'message' => 'Enemy too far!']); exit;
    }
    
    $invStmt = $pdo->prepare("SELECT items.power FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND is_equipped = 1 AND items.type = 'weapon'");
    $invStmt->execute([$charId]);
    $weaponDmg = $invStmt->fetchColumn() ?: 0;
    
    $dmg = rand(10, 15) + $char['base_attack'] + $weaponDmg;
    $char['enemy_hp'] -= $dmg;
    $cState['player_ap'] = 0; 
    
    $log = "You deal $dmg damage!";
    $win = false;
    $tutorialFinishedNow = false;
    $xp = 0; $goldReward = 0; $dropItem = null;
    
    if ($char['enemy_hp'] <= 0) {
        $win = true; $xp = rand(15, 25);
        
        // --- DROPS LOGIC ---
        $cState = json_decode($char['combat_state'], true);
        $enemyType = $cState['enemy_type'] ?? 'standard';
        $enemyLevel = $cState['enemy_level'] ?? 1;
        
        // Coin rewards (in copper), no scaling by enemy level
        $minCopper = 5;
        $maxCopper = 12;

        if ($enemyType === 'green') {
            $minCopper = 10;
            $maxCopper = 20;
        } elseif ($enemyType === 'yellow') {
            $minCopper = 20;
            $maxCopper = 35;
        } elseif ($enemyType === 'orange') {
            $minCopper = 40;
            $maxCopper = 80;
        } elseif ($enemyType === 'red') {
            $minCopper = 100;
            $maxCopper = 200;
        }

        $goldReward = rand($minCopper, $maxCopper);
        
        $dropId = 0;
        $dropChance = 0;
        
        if ($enemyType === 'standard') { $dropId = 20; $dropChance = 100; } // Rat Tail
        elseif ($enemyType === 'green') { $dropId = 21; $dropChance = 45; } // Goblin Ear
        elseif ($enemyType === 'yellow') { $dropId = 22; $dropChance = 40; } // Bandit Insignia
        elseif ($enemyType === 'orange') { $dropId = 23; $dropChance = 35; } // Lava Core
        elseif ($enemyType === 'red') { $dropId = 24; $dropChance = 30; } // Demon Horn
        
        if ($dropId > 0 && rand(1, 100) <= $dropChance) {
            // Use base drop value (no scaling by enemy level)
            $stmt = $pdo->prepare("SELECT name, price FROM items WHERE id = ?"); 
            $stmt->execute([$dropId]);
            $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
            $scaledValue = (int)$itemData['price'];
            
            $pdo->prepare("INSERT INTO inventory (character_id, item_id, quantity, item_value) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE quantity = quantity + 1, item_value = GREATEST(item_value, ?)")->execute([$charId, $dropId, $scaledValue, $scaledValue]);
            $dropItem = $itemData['name'];
            $log .= " Found loot: $dropItem!";
        }
        
        $char['xp'] += $xp; 
        $char['gold'] += $goldReward;
        $char['in_combat'] = 0; 
        $char['combat_state'] = NULL;
        
        $log .= " (+$xp XP, +$goldReward coins)";
        
        // --- SPRAWDZENIE UKOŃCZENIA TUTORIALU ---
        if ($char['world_id'] == 1 && $char['tutorial_completed'] == 0) {
            $char['gold'] += 50; // Bonus coins for tutorial
            $pdo->prepare("UPDATE characters SET tutorial_completed = 1 WHERE id = ?")->execute([$charId]);
            $char['tutorial_completed'] = 1;
            $tutorialFinishedNow = true;
            $log .= " VICTORY! Tutorial Completed!";
        } else {
            if ($char['xp'] >= $char['max_xp']) {
                $char['level']++; $char['xp'] = 0; $char['max_xp'] *= 1.2;
                $char['hp'] = $char['max_hp'];
                $char['stat_points'] += 3; // Grant 3 stat points
                $log .= " VICTORY! LEVEL UP!";
            } else {
                $log .= " VICTORY!";
            }
        }
    } else {
        $cState['turn'] = 'enemy';
        $cState['enemy_ap'] = 2;
    }
    
    $pdo->prepare("UPDATE characters SET hp=?, enemy_hp=?, xp=?, max_xp=?, level=?, max_hp=?, stat_points=?, gold=?, in_combat=?, combat_state=? WHERE id=?")
        ->execute([$char['hp'], max(0,$char['enemy_hp']), $char['xp'], $char['max_xp'], $char['level'], $char['max_hp'], $char['stat_points'], $char['gold'], $char['in_combat'], json_encode($cState), $charId]);
        
    
    echo json_encode([
        'status' => 'success', 
        'enemy_hp' => max(0,$char['enemy_hp']), 
        'dmg_dealt' => $dmg,
        'win' => $win, 
        'log' => $log, 
        'gold' => $char['gold'],
        'combat_state' => $cState,
        'tutorial_finished' => $tutorialFinishedNow,
        'xp_gain' => $xp,
        'gold_gain' => $goldReward,
        'loot' => $dropItem
    ]); exit;
}

if ($action === 'combat_use_item') {
    $itemId = (int)$input['item_id']; // This is ITEM ID from items table
    $stmt = $pdo->prepare("SELECT inventory.id, items.power, inventory.quantity FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND items.id = ?");
    $stmt->execute([$charId, $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item || $item['quantity'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Item missing!']); exit; }
    
    $heal = $item['power'];
    $char['hp'] = min($char['max_hp'], $char['hp'] + $heal);
    
    // Use inventory ID for deletion/update
    if ($item['quantity'] > 1) { 
        $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?")->execute([$item['id']]); 
    } 
    else { $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$item['id']]); }
    
    $cState = json_decode($char['combat_state'], true);
    $cState['player_ap'] = 0; $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2;

    $pdo->prepare("UPDATE characters SET hp = ?, combat_state = ? WHERE id = ?")->execute([$char['hp'], json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'hp' => $char['hp'], 'combat_state' => $cState, 'message' => "Healed for $heal HP. Enemy turn."]); exit;
}

// --- OUT OF COMBAT ITEM USAGE ---
if ($action === 'use_item') {
    $itemId = (int)$input['item_id'];
    if ($char['in_combat']) { echo json_encode(['status' => 'error', 'message' => 'Use combat actions!']); exit; }

    // Check if item is consumable (potion/bandage)
    // Assuming ID 7 = Bandage (Power 20), ID 8 = Potion (Power 50)
    // Or check item type if we had a 'consumable' type. For now, ID check or type check.
    
    $stmt = $pdo->prepare("SELECT inventory.id, items.power, items.type, inventory.quantity FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND items.id = ?");
    $stmt->execute([$charId, $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item || $item['quantity'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Item not found']); exit; }
    if ($item['id'] != 7 && $item['id'] != 8 && $item['type'] !== 'consumable') { echo json_encode(['status' => 'error', 'message' => 'Cannot use this item here.']); exit; }

    $heal = ($item['id'] == 7) ? 20 : (($item['id'] == 8) ? 50 : $item['power']);
    $char['hp'] = min($char['max_hp'], $char['hp'] + $heal);

    if ($item['quantity'] > 1) { $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?")->execute([$item['id']]); } 
    else { $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$item['id']]); }

    $pdo->prepare("UPDATE characters SET hp = ? WHERE id = ?")->execute([$char['hp'], $charId]);
    echo json_encode(['status' => 'success', 'hp' => $char['hp'], 'message' => "Healed for $heal HP."]); exit;
}

if ($action === 'enemy_turn') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'enemy') { echo json_encode(['status' => 'error']); exit; }
    
    $type = $cState['enemy_type'] ?? 'standard';
    $name = $cState['enemy_name'] ?? 'Enemy';
    $enemyLevel = $cState['enemy_level'] ?? 1;
    $dmgMult = $cState['enemy_dmg_mult'] ?? 1.0;
    $heals = $cState['enemy_heals'] ?? 0;
    $levelMult = 1 + ($enemyLevel * 0.10);
    
    $log = ""; $actions_performed = []; 
    
    $safety = 0;
    while ($cState['enemy_ap'] > 0 && $safety++ < 20) {
        $pl = $cState['player_pos']; $en = $cState['enemy_pos'];
        $dist = getGameDistance($pl['x'], $pl['y'], $en['x'], $en['y']);
        
        // HEAL LOGIC (Free action)
        if ($heals > 0 && $char['enemy_hp'] < ($char['enemy_max_hp'] * 0.5)) {
             $healAmt = (int)($char['enemy_max_hp'] * 0.3); // Heal 30%
             $char['enemy_hp'] = min($char['enemy_max_hp'], $char['enemy_hp'] + $healAmt);
             $heals--; $cState['enemy_heals'] = $heals; // No AP cost
             $actions_performed[] = ['type' => 'heal', 'amount' => $healAmt];
             $log .= "$name heals for $healAmt HP! ";
             continue;
        }

        $isHorizontal = ($pl['y'] == $en['y']);
        $maxDist = $isHorizontal ? 2.2 : 1.1;
        if ($dist <= $maxDist && $cState['enemy_ap'] >= 2) {
            $baseDmg = rand(10, 18);
            $dmg = (int)ceil($baseDmg * $dmgMult * $levelMult);
            
            // Get player armor power
            $armorStmt = $pdo->prepare("SELECT items.power FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND is_equipped = 1 AND items.type = 'armor'");
            $armorStmt->execute([$charId]);
            $armorPower = $armorStmt->fetchColumn() ?: 0;
            
            $totalDefense = (int)($char['base_defense'] ?? 0) + $armorPower;
            $dmg = max(1, $dmg - $totalDefense);
            
            if (!empty($cState['is_defending'])) {
                $dmg = ceil($dmg * 0.5);
                $log .= "$name attacks! You block ($dmg dmg).";
            } else {
                $log .= "$name attacks! You lose $dmg HP.";
            }
            $char['hp'] -= $dmg;
            $cState['enemy_ap'] = 0;
            $actions_performed[] = ['type' => 'attack', 'dmg' => $dmg];
            break;
        } else if ($cState['enemy_ap'] >= 1) {
            $offsets = ($en['y'] % 2 != 0) ? [[1,0], [1,-1], [0,-1], [-1,0], [0,1], [1,1]] : [[1,0], [0,-1], [-1,-1], [-1,0], [-1,1], [0,1]];
            $bestMove = null; $minDist = 999;
            foreach ($offsets as $o) {
                $nx = $en['x'] + $o[0]; $ny = $en['y'] + $o[1];
                $valid = false;
                foreach ($cState['tiles'] as $t) { if ($t['x'] == $nx && $t['y'] == $ny && $t['type'] !== 'water') { $valid = true; break; } }
                if ($nx == $pl['x'] && $ny == $pl['y']) $valid = false; 
                if ($valid) { $d = getGameDistance($nx, $ny, $pl['x'], $pl['y']); if ($d < $minDist) { $minDist = $d; $bestMove = ['x' => $nx, 'y' => $ny]; } }
            }
            $moveCost = $bestMove ? (int)ceil(getGameDistance($en['x'], $en['y'], $bestMove['x'], $bestMove['y'])) : 99;
            if ($bestMove && $cState['enemy_ap'] >= $moveCost) { $cState['enemy_pos'] = $bestMove; $cState['enemy_ap'] -= $moveCost; $actions_performed[] = ['type' => 'move', 'to' => $bestMove]; } 
            else { $cState['enemy_ap'] = 0; }
        } else { $cState['enemy_ap'] = 0; }
    }
    
    $cState['turn'] = 'player'; $cState['player_ap'] = 2; $cState['is_defending'] = false; 
    $died = ($char['hp'] <= 0); if ($died) { $char['hp'] = 0; $cState = NULL; }
    $pdo->prepare("UPDATE characters SET hp=?, enemy_hp=?, combat_state=? WHERE id=?")->execute([$char['hp'], $char['enemy_hp'], json_encode($cState), $charId]);
    echo json_encode(['status'=>'success', 'hp'=>$char['hp'], 'enemy_hp'=>$char['enemy_hp'], 'log'=>$log, 'combat_state'=>$cState, 'player_died'=>$died, 'actions' => $actions_performed]); exit;
}

if ($action === 'get_other_players') {
    $timeoutMinutes = 5;
    
    // Get all other characters in the same world, active within last N minutes
    // Optimization: Only get players nearby
    $range = 15;
    $minX = $char['pos_x'] - $range; $maxX = $char['pos_x'] + $range;
    $minY = $char['pos_y'] - $range; $maxY = $char['pos_y'] + $range;

    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.pos_x, c.pos_y, c.level, u.username
        FROM characters c
            JOIN users u ON c.user_id = u.id
            WHERE c.world_id = ? AND c.id != ? AND c.last_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND c.pos_x BETWEEN ? AND ? AND c.pos_y BETWEEN ? AND ?
            ORDER BY c.last_seen DESC
        ");
        $stmt->execute([$char['world_id'], $charId, $timeoutMinutes, $minX, $maxX, $minY, $maxY]);
        $otherPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $otherPlayers = [];
    }

    // Get ALL online players in this world for the UI list (ignoring distance)
    $stmtList = $pdo->prepare("
        SELECT c.id, c.name, c.level 
        FROM characters c 
        WHERE c.world_id = ? AND c.last_seen > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY c.level DESC
    ");
    $stmtList->execute([$char['world_id'], $timeoutMinutes]);
    $onlineList = $stmtList->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for incoming duel requests
    $stmt = $pdo->prepare("SELECT r.id, c.name as challenger_name FROM duel_requests r JOIN characters c ON r.challenger_id = c.id WHERE r.target_id = ? AND r.status = 'pending'");
    $stmt->execute([$charId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if I am in a duel (to auto-start frontend)
    $myDuelId = $char['duel_id'];
    
    echo json_encode(['status' => 'success', 'players' => $otherPlayers, 'online_list' => $onlineList, 'duel_requests' => $requests, 'my_duel_id' => $myDuelId]); exit;
}

if ($action === 'spend_stat_point') {
    $stat = $input['stat'];
    if ($char['stat_points'] > 0) {
        $char['stat_points']--;
        if ($stat === 'str') $char['base_attack']++;
        elseif ($stat === 'def') $char['base_defense']++;
        elseif ($stat === 'hp') { $char['max_hp'] += 5; $char['hp'] += 5; }
        elseif ($stat === 'eng') { $char['max_energy'] += 1; $char['energy'] += 1; }
        
        $pdo->prepare("UPDATE characters SET stat_points=?, base_attack=?, base_defense=?, max_hp=?, hp=?, max_energy=?, energy=? WHERE id=?")
            ->execute([$char['stat_points'], $char['base_attack'], $char['base_defense'], $char['max_hp'], $char['hp'], $char['max_energy'], $char['energy'], $charId]);
        
        // Calculate attack and defense with equipped items
        $invStmt = $pdo->prepare("SELECT i.id as item_id, i.name, i.type, i.power, i.icon, i.description, COALESCE(inv.item_value, i.price) as price, i.rarity, inv.quantity, inv.is_equipped FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.character_id = ?");
        $invStmt->execute([$charId]);
        $inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalAttack = 1 + ($char['base_attack'] ?? 1); 
        $totalDefense = ($char['base_defense'] ?? 0);
        foreach ($inventory as $item) {
            if ($item['is_equipped'] && $item['type'] == 'weapon') $totalAttack += $item['power'];
            if ($item['is_equipped'] && $item['type'] == 'armor') $totalDefense += $item['power'];
        }

        $char['attack'] = $totalAttack;
        $char['defense'] = $totalDefense;
            
        echo json_encode(['status' => 'success', 'data' => $char]); exit;
    }
    echo json_encode(['status' => 'error', 'message' => 'No points']); exit;
}

// --- SHOPS ---

if ($action === 'get_shop_data') {
    $shopType = $input['shop_type']; // leathersmith, blacksmith, armorer, clergy
    $classId = isset($input['class_id']) ? (int)$input['class_id'] : null;
    
    $items = [];
    if ($shopType === 'leathersmith') {
        // Sells Leather Armor - All tiers
        $stmt = $pdo->query("SELECT * FROM items WHERE id IN (4, 37, 38, 39, 40) ORDER BY price ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($shopType === 'blacksmith') {
        // Sells All Weapons + Light Armor (Robes)
        $stmt = $pdo->query("SELECT * FROM items WHERE id IN (1, 2, 3, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 6, 41, 42, 43, 44) ORDER BY type DESC, price ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($shopType === 'armorer') {
        // Sells Heavy Armor - All tiers
        $stmt = $pdo->query("SELECT * FROM items WHERE id IN (5, 45, 46, 47, 48) ORDER BY price ASC"); 
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($shopType === 'clergy') {
        // Potions and Bandages
        $stmt = $pdo->query("SELECT * FROM items WHERE id IN (7, 8) ORDER BY price ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Filter by class if class_id is provided
    if ($classId !== null && $classId > 0) {
        $items = array_filter($items, function($item) use ($classId) {
            return $item['optimal_class_id'] === null || $item['optimal_class_id'] == $classId;
        });
    }
    
    echo json_encode(['status' => 'success', 'items' => $items, 'gold' => $char['gold']]); exit;
}

if ($action === 'buy_item') {
    $itemId = (int)$input['item_id'];
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) { echo json_encode(['status' => 'error', 'message' => 'Item not found']); exit; }
    if ($char['gold'] < $item['price']) { echo json_encode(['status' => 'error', 'message' => 'Not enough coins!']); exit; }
    
    // Deduct coins
    $newGold = $char['gold'] - $item['price'];
    $pdo->prepare("UPDATE characters SET gold = ? WHERE id = ?")->execute([$newGold, $charId]);
    
    // Add item
    $pdo->prepare("INSERT INTO inventory (character_id, item_id, quantity) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1")
        ->execute([$charId, $itemId]);
        
    echo json_encode(['status' => 'success', 'gold' => $newGold, 'message' => 'Bought ' . $item['name']]); exit;
}

if ($action === 'equip_item') {
    $itemId = (int)$input['item_id'];
    
    // Get item details
    $stmt = $pdo->prepare("SELECT items.type, items.name, inventory.id as inv_id FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND items.id = ?");
    $stmt->execute([$charId, $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) { echo json_encode(['status' => 'error', 'message' => 'Item not found']); exit; }
    if ($item['type'] !== 'weapon' && $item['type'] !== 'armor') { echo json_encode(['status' => 'error', 'message' => 'Cannot equip this item']); exit; }
    
    // Unequip current item of same type
    $stmt = $pdo->prepare("UPDATE inventory SET is_equipped = 0 WHERE character_id = ? AND is_equipped = 1 AND item_id IN (SELECT id FROM items WHERE type = ?)");
    $stmt->execute([$charId, $item['type']]);
    
    // Equip new item
    $pdo->prepare("UPDATE inventory SET is_equipped = 1 WHERE id = ?")->execute([$item['inv_id']]);
    
    echo json_encode(['status' => 'success', 'message' => 'Equipped ' . $item['name']]); exit;
}

if ($action === 'unequip_item') {
    $itemId = (int)$input['item_id'];
    
    $stmt = $pdo->prepare("UPDATE inventory SET is_equipped = 0 WHERE character_id = ? AND item_id = ? AND is_equipped = 1");
    $stmt->execute([$charId, $itemId]);
    
    echo json_encode(['status' => 'success', 'message' => 'Item unequipped']); exit;
}

if ($action === 'sell_item') {
    $itemId = (int)$input['item_id']; // Item ID from items table
    // Check if user has it and if it's equipped
    $stmt = $pdo->prepare("SELECT inventory.id as inv_id, inventory.quantity, inventory.is_equipped, items.price, items.type, inventory.item_value FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND items.id = ?");
    $stmt->execute([$charId, $itemId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row || $row['quantity'] < 1) { echo json_encode(['status' => 'error', 'message' => 'You do not have this item']); exit; }
    if ($row['is_equipped'] == 1) { echo json_encode(['status' => 'error', 'message' => 'Cannot sell equipped items! Unequip first.']); exit; }
    
    // Use scaled item_value if available (100% for drops), otherwise 60% of base price (for shop items)
    if ($row['item_value'] !== null) {
        $sellPrice = max(1, $row['item_value']); // 100% for drops
    } else {
        $sellPrice = max(1, floor($row['price'] * 0.6)); // 60% for shop items
    }
    if ($row['quantity'] > 1) { $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?")->execute([$row['inv_id']]); }
    else { $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$row['inv_id']]); }
    
    $pdo->prepare("UPDATE characters SET gold = gold + ? WHERE id = ?")->execute([$sellPrice, $charId]);
    echo json_encode(['status' => 'success', 'gold' => $char['gold'] + $sellPrice, 'message' => "Sold for $sellPrice coins."]); exit;
}

// --- QUEST SYSTEM ---

if ($action === 'get_available_quests') {
    $shopType = $input['shop_type'] ?? null;
    
    $char = $pdo->query("SELECT level FROM characters WHERE id = $charId")->fetch(PDO::FETCH_ASSOC);
    $level = $char['level'];
    
    // Check total quests in DB
    $totalQuests = $pdo->query("SELECT COUNT(*) FROM quests")->fetchColumn();
    error_log("DEBUG: Total quests in DB: $totalQuests");
    
    // Check if character is in any guild
    $guildCheck = $pdo->prepare("SELECT COUNT(*) FROM guild_members WHERE character_id = ?");
    $guildCheck->execute([$charId]);
    $isInGuild = $guildCheck->fetchColumn() > 0;
    
    $activeStmt = $pdo->prepare("SELECT quest_id FROM character_quests WHERE character_id = ? AND status = 'active'");
    $activeStmt->execute([$charId]);
    $activeQuestIds = $activeStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $query = "SELECT * FROM quests WHERE min_level <= ? AND (max_level IS NULL OR max_level >= ?)";
    $params = [$level, $level];
    
    error_log("DEBUG: Query before guild filter: $query with params: " . json_encode($params));
    
    // Filter guild quests based on membership  
    if ($isInGuild) {
        // Show all quests (both regular and guild) - no additional filter needed
    } else {
        // Show only non-guild quests
        $query .= " AND (guild_required = 0 OR guild_required IS NULL)";
    }
    
    if ($shopType) {
        $query .= " AND shop_type = ?";
        $params[] = $shopType;
    }
    
    error_log("DEBUG: Final query: $query");
    error_log("DEBUG: Level=$level, InGuild=" . ($isInGuild ? 'yes' : 'no') . ", ShopType=" . ($shopType ?? 'null'));
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("DEBUG: Found " . count($quests) . " quests after level/guild filter");
    
    $quests = array_filter($quests, function($q) use ($activeQuestIds) {
        return !in_array($q['id'], $activeQuestIds);
    });
    
    error_log("DEBUG: " . count($quests) . " quests after filtering active (active IDs: " . json_encode($activeQuestIds) . ")");
    
    foreach ($quests as &$quest) {
        $quest['required_items'] = json_decode($quest['required_items'], true);
    }
    
    echo json_encode(['status' => 'success', 'quests' => array_values($quests)]); exit;
}

if ($action === 'accept_quest') {
    $questId = (int)($input['quest_id'] ?? 0);
    
    $quest = $pdo->prepare("SELECT * FROM quests WHERE id = ?");
    $quest->execute([$questId]);
    $quest = $quest->fetch(PDO::FETCH_ASSOC);
    
    if (!$quest) { echo json_encode(['status' => 'error', 'message' => 'Quest not found']); exit; }
    
    $char = $pdo->prepare("SELECT level FROM characters WHERE id = ?");
    $char->execute([$charId]);
    $char = $char->fetch(PDO::FETCH_ASSOC);
    
    if ($char['level'] < $quest['min_level']) {
        echo json_encode(['status' => 'error', 'message' => 'Level too low']); exit;
    }
    
    // Quest 1-3 cannot be done by level 10+ players
    if ($char['level'] >= 10 && in_array($questId, [1, 2, 3])) {
        echo json_encode(['status' => 'error', 'message' => 'This quest is too low level for you']); exit;
    }
    
    // Check if already active
    $existing = $pdo->prepare("SELECT id, status FROM character_quests WHERE character_id = ? AND quest_id = ? AND status = 'active'");
    $existing->execute([$charId, $questId]);
    if ($existing->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Quest already active']); exit;
    }
    
    // For repeatable quests: reset the completed one instead of inserting new
    if ($quest['repeatable']) {
        // Quest is repeatable - check if completed version exists
        $completed = $pdo->prepare("SELECT id FROM character_quests WHERE character_id = ? AND quest_id = ? AND status = 'completed'");
        $completed->execute([$charId, $questId]);
        $completedRow = $completed->fetch();
        
        if ($completedRow) {
            // Reset the completed quest to active
            $pdo->prepare("UPDATE character_quests SET status = 'active', progress = '{}', completed_at = NULL WHERE id = ?")->execute([$completedRow['id']]);
        } else {
            // No completed version yet, insert new
            $stmt = $pdo->prepare("INSERT INTO character_quests (character_id, quest_id, status, progress) VALUES (?, ?, 'active', '{}')");
            $stmt->execute([$charId, $questId]);
        }
    } else {
        // Normal insert for non-repeatable
        $stmt = $pdo->prepare("INSERT INTO character_quests (character_id, quest_id, status, progress) VALUES (?, ?, 'active', '{}')");
        $stmt->execute([$charId, $questId]);
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Quest accepted!']); exit;
}

if ($action === 'get_active_quests') {
    $stmt = $pdo->prepare("
        SELECT cq.id as char_quest_id, cq.status, cq.progress, q.* 
        FROM character_quests cq 
        JOIN quests q ON cq.quest_id = q.id 
        WHERE cq.character_id = ? AND cq.status = 'active'
    ");
    $stmt->execute([$charId]);
    $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($quests as &$quest) {
        $quest['required_items'] = json_decode($quest['required_items'], true);
        $quest['progress'] = json_decode($quest['progress'], true) ?: [];
        
        $canComplete = true;
        foreach ($quest['required_items'] as $reqItem) {
            // SUM all slots with this item (not just first slot)
            $inv = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total_quantity FROM inventory WHERE character_id = ? AND item_id = ?");
            $inv->execute([$charId, $reqItem['id']]);
            $invRow = $inv->fetch(PDO::FETCH_ASSOC);
            $has = $invRow ? (int)$invRow['total_quantity'] : 0;
            
            $quest['progress'][$reqItem['id']] = $has;
            if ($has < $reqItem['quantity']) {
                $canComplete = false;
            }
        }
        $quest['can_complete'] = $canComplete;
    }
    
    echo json_encode(['status' => 'success', 'quests' => $quests]); exit;
}

if ($action === 'complete_quest') {
    $charQuestId = (int)($input['char_quest_id'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT cq.*, q.* 
        FROM character_quests cq 
        JOIN quests q ON cq.quest_id = q.id 
        WHERE cq.id = ? AND cq.character_id = ? AND cq.status = 'active'
    ");
    $stmt->execute([$charQuestId, $charId]);
    $quest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quest) { echo json_encode(['status' => 'error', 'message' => 'Quest not found or not active']); exit; }
    
    $requiredItems = json_decode($quest['required_items'], true);
    
    // FIRST: Check if we have all items BEFORE starting transaction
    foreach ($requiredItems as $reqItem) {
        $inv = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total_qty FROM inventory WHERE character_id = ? AND item_id = ?");
        $inv->execute([$charId, $reqItem['id']]);
        $row = $inv->fetch(PDO::FETCH_ASSOC);
        if ((int)$row['total_qty'] < $reqItem['quantity']) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required items']); exit;
        }
    }
    
    // NOW start transaction for actual removal
    $pdo->beginTransaction();
    try {
        foreach ($requiredItems as $reqItem) {
            // Get ALL inventory slots with this item
            $invStmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE character_id = ? AND item_id = ? ORDER BY id");
            $invStmt->execute([$charId, $reqItem['id']]);
            $invSlots = $invStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Remove items from slots (starting from first, removing as needed)
            $needed = $reqItem['quantity'];
            foreach ($invSlots as $slot) {
                if ($needed <= 0) break;
                $takeFromThisSlot = min($needed, $slot['quantity']);
                if ($slot['quantity'] - $takeFromThisSlot > 0) {
                    $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")->execute([$takeFromThisSlot, $slot['id']]);
                } else {
                    $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$slot['id']]);
                }
                $needed -= $takeFromThisSlot;
            }
        }
        
        $pdo->prepare("UPDATE characters SET gold = gold + ? WHERE id = ?")->execute([$quest['reward_gold'], $charId]);
        
        // Update or insert reputation (more explicit)
        $checkRep = $pdo->prepare("SELECT points FROM reputation WHERE character_id = ?");
        $checkRep->execute([$charId]);
        $repExists = $checkRep->fetch(PDO::FETCH_ASSOC);
        
        if ($repExists) {
            // Update existing
            $newRep = (int)$repExists['points'] + (int)$quest['reward_reputation'];
            $pdo->prepare("UPDATE reputation SET points = ? WHERE character_id = ?")->execute([$newRep, $charId]);
        } else {
            // Insert new
            $pdo->prepare("INSERT INTO reputation (character_id, points) VALUES (?, ?)")->execute([$charId, $quest['reward_reputation']]);
        }
        
        $pdo->prepare("UPDATE character_quests SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$charQuestId]);
        
        $pdo->commit();
        
        // Get final reputation value
        $rep = $pdo->prepare("SELECT points FROM reputation WHERE character_id = ?");
        $rep->execute([$charId]);
        $repRow = $rep->fetch(PDO::FETCH_ASSOC);
        $repPoints = $repRow ? (int)$repRow['points'] : 0;
        
        // Format currency: 100 copper = 1 silver, 100 silver = 1 gold
        $rewardAmount = $quest['reward_gold'];
        if ($rewardAmount >= 10000) {
            $gold = floor($rewardAmount / 10000);
            $remainder = $rewardAmount % 10000;
            $silver = floor($remainder / 100);
            $currencyText = $silver > 0 ? "{$gold} gold, {$silver} silver" : "{$gold} gold";
        } elseif ($rewardAmount >= 100) {
            $silver = floor($rewardAmount / 100);
            $copper = $rewardAmount % 100;
            $currencyText = $copper > 0 ? "{$silver} silver, {$copper} copper" : "{$silver} silver";
        } else {
            $currencyText = "{$rewardAmount} copper coins";
        }
        
        echo json_encode([
            'status' => 'success', 
            'message' => "Quest completed! +{$currencyText}, +{$quest['reward_reputation']} reputation",
            'gold' => $quest['reward_gold'],
            'reputation' => $repPoints
        ]); exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]); exit;
    }
}

if ($action === 'abandon_quest') {
    $charQuestId = (int)($input['char_quest_id'] ?? 0);
    
    $stmt = $pdo->prepare("DELETE FROM character_quests WHERE id = ? AND character_id = ? AND status = 'active'");
    $stmt->execute([$charQuestId, $charId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Quest abandoned']); exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Quest not found']); exit;
    }
}

if ($action === 'get_reputation') {
    $stmt = $pdo->prepare("SELECT points FROM reputation WHERE character_id = ?");
    $stmt->execute([$charId]);
    $rep = $stmt->fetch(PDO::FETCH_ASSOC);
    $points = $rep ? (int)$rep['points'] : 0;
    
    echo json_encode(['status' => 'success', 'reputation' => $points]); exit;
}

if ($action === 'get_guilds') {
    $guilds = $pdo->query("SELECT * FROM guilds ORDER BY required_reputation ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $rep = $pdo->prepare("SELECT points FROM reputation WHERE character_id = ?");
    $rep->execute([$charId]);
    $repRow = $rep->fetch(PDO::FETCH_ASSOC);
    $repPoints = $repRow ? (int)$repRow['points'] : 0;
    
    $member = $pdo->prepare("SELECT guild_id FROM guild_members WHERE character_id = ?");
    $member->execute([$charId]);
    $memberRow = $member->fetch(PDO::FETCH_ASSOC);
    $currentGuildId = $memberRow ? $memberRow['guild_id'] : null;
    
    foreach ($guilds as &$guild) {
        $guild['can_join'] = ($repPoints >= $guild['required_reputation']) && ($currentGuildId === null);
        $guild['is_member'] = ($currentGuildId == $guild['id']);
    }
    
    echo json_encode(['status' => 'success', 'guilds' => $guilds, 'reputation' => $repPoints]); exit;
}

if ($action === 'join_guild') {
    $guildId = (int)($input['guild_id'] ?? 0);
    
    $member = $pdo->prepare("SELECT guild_id FROM guild_members WHERE character_id = ?");
    $member->execute([$charId]);
    if ($member->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Already in a guild']); exit;
    }
    
    $guild = $pdo->query("SELECT * FROM guilds WHERE id = $guildId")->fetch(PDO::FETCH_ASSOC);
    if (!$guild) { echo json_encode(['status' => 'error', 'message' => 'Guild not found']); exit; }
    
    $rep = $pdo->prepare("SELECT points FROM reputation WHERE character_id = ?");
    $rep->execute([$charId]);
    $repRow = $rep->fetch(PDO::FETCH_ASSOC);
    $repPoints = $repRow ? (int)$repRow['points'] : 0;
    
    if ($repPoints < $guild['required_reputation']) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient reputation']); exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO guild_members (guild_id, character_id, rank) VALUES (?, ?, 'member')");
    $stmt->execute([$guildId, $charId]);
    
    echo json_encode(['status' => 'success', 'message' => "Joined {$guild['name']}!"]); exit;
}

?>