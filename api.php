<?php
require 'db.php';
header('Content-Type: application/json');
ini_set('display_errors', '0');
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';  // <-- ADD THIS LINE

// --- MIGRATION: Ensure last_seen column exists ---
try {
    $checkCol = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'characters' AND COLUMN_NAME = 'last_seen'");
    if ((int)$checkCol->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE characters ADD COLUMN last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    // --- MIGRATION: Duels ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS duel_requests (id INT AUTO_INCREMENT PRIMARY KEY, challenger_id INT, target_id INT, status VARCHAR(20) DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS active_duels (id INT AUTO_INCREMENT PRIMARY KEY, player1_id INT, player2_id INT, current_turn_id INT, combat_state TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    try { $pdo->exec("ALTER TABLE characters ADD COLUMN duel_id INT DEFAULT NULL"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE active_duels ADD COLUMN turn_start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch(Exception $e){}

} catch (Exception $e) {
    // ignore if migration fails
}

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
        echo json_encode(['status' => 'error', 'message' => 'Username i hasło muszą mieć co najmniej 3 znaki.']); exit;
    }
    if ($password !== $password2) {
        echo json_encode(['status' => 'error', 'message' => 'Hasła się nie zgadzają.']); exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Nazwa użytkownika już zajęta.']); exit;
    }
    
    $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hashedPwd]);
        $newUserId = $pdo->lastInsertId();
        $_SESSION['user_id'] = $newUserId;
        echo json_encode(['status' => 'success', 'user_id' => $newUserId]); exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd bazy danych.']); exit;
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
        echo json_encode(['status' => 'error', 'message' => 'Nieprawidłowa nazwa lub hasło.']); exit;
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
    echo json_encode(['status' => 'error', 'message' => 'Nie zalogowany']); exit;
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
    echo json_encode(['status' => 'error', 'message' => 'Brak postaci']); exit;
}

$STEPS_PER_ENERGY = 10; 
$MAX_SPEED_NORMAL = 5;
$MAX_SPEED_EXHAUSTED = 1;

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
        echo json_encode(['status' => 'error', 'message' => 'Postać nie istnieje.']); exit;
    }
    $_SESSION['char_id'] = $charIdToSelect;
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'create_character') {
    $name = trim($input['name'] ?? '') ?: 'Nowa postać';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM characters WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() >= 3) {
        echo json_encode(['status' => 'error', 'message' => 'Maksymalnie 3 postacie.']); exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO characters (user_id, name, world_id) VALUES (?, ?, 1)");
        $stmt->execute([$userId, $name]);
        $newCharId = $pdo->lastInsertId();
        // Auto-select the new character
        $_SESSION['char_id'] = $newCharId;
        echo json_encode(['status' => 'success', 'character_id' => $newCharId]); exit;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd bazy danych.']); exit;
    }
}

if ($action === 'delete_character') {
    $targetId = (int)$input['character_id'];
    
    $stmt = $pdo->prepare("SELECT id, in_combat FROM characters WHERE id = ? AND user_id = ?");
    $stmt->execute([$targetId, $userId]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['status' => 'error', 'message' => 'Brak dostępu.']); exit; }
    
    if ($row['in_combat']) { echo json_encode(['status' => 'error', 'message' => 'Nie można usunąć postaci w trakcie walki!']); exit; }
    
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
    
    $stmt = $pdo->prepare("SELECT id FROM worlds WHERE id = ?");
    $stmt->execute([$targetWorldId]);
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Świat nie istnieje.']); exit;
    }

    // Check player limit (20)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM characters WHERE world_id = ?");
    $stmt->execute([$targetWorldId]);
    if ($stmt->fetchColumn() >= 20) {
        echo json_encode(['status' => 'error', 'message' => 'Świat jest pełny (20/20).']); exit;
    }

    $curWorldId = (int)($char['world_id'] ?? 0);
    $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE pos_x = VALUES(pos_x), pos_y = VALUES(pos_y)")
        ->execute([$charId, $curWorldId, (int)$char['pos_x'], (int)$char['pos_y']]);

   
    if ($curWorldId != 1) {
        $posX = (int)($char['pos_x'] ?? 0);
        $posY = (int)($char['pos_y'] ?? 0);
        $tileStmt = $pdo->prepare("SELECT type FROM map_tiles WHERE x = ? AND y = ? AND world_id = ? LIMIT 1");
        $tileStmt->execute([$posX, $posY, $curWorldId]);
        $curTile = $tileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$curTile || strpos($curTile['type'], 'city') === false) {
            echo json_encode(['status' => 'error', 'message' => 'Musisz być w mieście lub wiosce, by zmienić świat.']); exit;
        }
    }

    
    $posStmt = $pdo->prepare("SELECT pos_x, pos_y FROM saved_positions WHERE character_id = ? AND world_id = ? LIMIT 1");
    $posStmt->execute([$charId, $targetWorldId]);
    $saved = $posStmt->fetch(PDO::FETCH_ASSOC);
    $newX = $saved ? (int)$saved['pos_x'] : 0;
    $newY = $saved ? (int)$saved['pos_y'] : 0;

    
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
    
    
    $pdo->prepare("UPDATE characters SET class_id = ?, hp = ?, max_hp = ?, energy = ?, max_energy = ?, world_id = 1, tutorial_completed = 0 WHERE id = ?")
        ->execute([$classId, $cls['base_hp'], $cls['base_hp'], $cls['base_energy'], $cls['base_energy'], $charId]);
    
    $pdo->prepare("DELETE FROM inventory WHERE character_id = ?")->execute([$charId]);
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

    $invStmt = $pdo->prepare("SELECT i.id as item_id, i.name, i.type, i.power, i.icon, inv.quantity, inv.is_equipped FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.character_id = ?");
    $invStmt->execute([$charId]);
    $inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAttack = 1 + ($char['base_attack'] ?? 1); 
    foreach ($inventory as $item) {
        if ($item['is_equipped'] && $item['type'] == 'weapon') $totalAttack += $item['power'];
    }

    $char['attack'] = $totalAttack;
    $char['inventory'] = $inventory;
    $char['speed'] = ($char['energy'] > 0) ? $MAX_SPEED_NORMAL : $MAX_SPEED_EXHAUSTED;
    
    // Pobierz nazwę świata
    $wStmt = $pdo->prepare("SELECT name FROM worlds WHERE id = ?");
    $wStmt->execute([$char['world_id']]);
    $worldName = $wStmt->fetchColumn();
    $char['world_name'] = $worldName;

    if ($char['in_combat'] && empty($char['combat_state'])) {
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
    echo json_encode(['status' => 'success', 'tiles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

if ($action === 'move') {
    $targetX = (int)$input['x']; $targetY = (int)$input['y'];

    if ($char['hp'] <= 0) { echo json_encode(['status' => 'dead', 'message' => 'Jesteś martwy.']); exit; }
    if ($char['in_combat']) { echo json_encode(['status' => 'error', 'message' => 'Jesteś w walce!']); exit; }

    $currentSpeed = ($char['energy'] > 0) ? $MAX_SPEED_NORMAL : $MAX_SPEED_EXHAUSTED;
    $dist = getGameDistance($char['pos_x'], $char['pos_y'], $targetX, $targetY);
    
    if ($dist > $currentSpeed) { echo json_encode(['status' => 'error', 'message' => 'Za daleko!']); exit; }

    
    $tileStmt = $pdo->prepare("SELECT type FROM map_tiles WHERE x = ? AND y = ? AND world_id = ?");
    $tileStmt->execute([$targetX, $targetY, $char['world_id']]);
    $targetTile = $tileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetTile || $targetTile['type'] === 'water' || $targetTile['type'] === 'mountain') {
        echo json_encode(['status' => 'error', 'message' => 'Teren niedostępny!']); exit;
    }

    $isSafe = (strpos($targetTile['type'], 'city') !== false);
    $encounter = false; $enemyHp = 0; $msg = "Podróżujesz...";

    if ($isSafe) {
        $char['hp'] = $char['max_hp']; $char['energy'] = $char['max_energy']; $char['steps_buffer'] = 0;
        $msg = "Odpoczywasz w mieście.";
    } else {
        $char['steps_buffer'] += $dist;
        while ($char['steps_buffer'] >= $STEPS_PER_ENERGY) {
            if ($char['energy'] > 0) { $char['energy']--; $char['steps_buffer'] -= $STEPS_PER_ENERGY; } else break;
        }

        
        $chance = 15;
        if ($char['world_id'] == 1 && $char['tutorial_completed'] == 0) {
            $chance = 35; // 35% szansy w tutorialu żeby szybko spotkać wroga
        }

        if (rand(1, 100) <= $chance) { 
            $encounter = true;
            $enemyHp = rand(30, 60);
            
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
                'is_defending' => false
            ];
            
            $pdo->prepare("UPDATE characters SET in_combat = 1, enemy_hp = ?, enemy_max_hp = ?, pos_x = ?, pos_y = ?, energy = ?, steps_buffer = ?, combat_state = ? WHERE id = ?")
                ->execute([$enemyHp, $enemyHp, $targetX, $targetY, $char['energy'], $char['steps_buffer'], json_encode($combatState), $charId]);
            $msg = "⚔️ ZASADZKA!";
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
        $msg = "⚔️ ZASADZKA!";
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
    $pdo->prepare("UPDATE characters SET hp = ?, energy = ?, steps_buffer = 0, pos_x = 0, pos_y = 0, in_combat = 0, combat_state = NULL WHERE id = ?")
        ->execute([$stats['max_hp'], $stats['max_energy'], $charId]);

    
    $pdo->prepare("INSERT INTO saved_positions (character_id, world_id, pos_x, pos_y) VALUES (?, ?, 0, 0)
        ON DUPLICATE KEY UPDATE pos_x = 0, pos_y = 0")
        ->execute([$charId, (int)$stats['world_id']]);

    echo json_encode(['status' => 'success']); exit;
}

// --- DUEL SYSTEM ---

if ($action === 'send_duel_request') {
    $targetId = (int)$input['target_id'];
    if ($targetId == $charId) { echo json_encode(['status' => 'error', 'message' => 'Nie możesz walczyć sam ze sobą.']); exit; }
    
    // Check distance
    $stmt = $pdo->prepare("SELECT pos_x, pos_y, in_combat, duel_id FROM characters WHERE id = ?");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    
    if (!$target) { echo json_encode(['status' => 'error', 'message' => 'Gracz nie istnieje.']); exit; }
    if ($target['in_combat'] || $target['duel_id']) { echo json_encode(['status' => 'error', 'message' => 'Gracz jest zajęty walką.']); exit; }
    
    $dist = getGameDistance($char['pos_x'], $char['pos_y'], $target['pos_x'], $target['pos_y']);
    if ($dist > 5) { echo json_encode(['status' => 'error', 'message' => 'Za daleko!']); exit; }
    
    // Check existing requests
    $stmt = $pdo->prepare("SELECT id FROM duel_requests WHERE challenger_id = ? AND target_id = ? AND status = 'pending'");
    $stmt->execute([$charId, $targetId]);
    if ($stmt->fetch()) { echo json_encode(['status' => 'error', 'message' => 'Już wysłałeś wyzwanie.']); exit; }
    
    $pdo->prepare("INSERT INTO duel_requests (challenger_id, target_id) VALUES (?, ?)")->execute([$charId, $targetId]);
    echo json_encode(['status' => 'success', 'message' => 'Wyzwanie wysłane!']); exit;
}

if ($action === 'respond_duel_request') {
    $reqId = (int)$input['request_id'];
    $response = $input['response']; // 'accept' or 'reject'
    
    $stmt = $pdo->prepare("SELECT * FROM duel_requests WHERE id = ? AND target_id = ? AND status = 'pending'");
    $stmt->execute([$reqId, $charId]);
    $req = $stmt->fetch();
    
    if (!$req) { echo json_encode(['status' => 'error', 'message' => 'Wyzwanie nieaktualne.']); exit; }
    
    if ($response === 'reject') {
        $pdo->prepare("UPDATE duel_requests SET status = 'rejected' WHERE id = ?")->execute([$reqId]);
        echo json_encode(['status' => 'success']); exit;
    }
    
    if ($response === 'accept') {
        // Initialize Duel
        $challengerId = $req['challenger_id'];
        
        // Create Arena
        $arenaTiles = [];
        for ($ay = 0; $ay < 5; $ay++) {
            for ($ax = 0; $ax < 7; $ax++) {
                $arenaTiles[] = ['x' => $ax, 'y' => $ay, 'type' => 'grass'];
            }
        }
        
        $combatState = [
            'p1_pos' => ['x' => 1, 'y' => 2], // Challenger
            'p2_pos' => ['x' => 5, 'y' => 2], // Target (You)
            'tiles' => $arenaTiles,
            'turn_id' => $challengerId, // Challenger starts
            'p1_ap' => 2,
            'p2_ap' => 2,
            'log' => 'Pojedynek rozpoczęty!'
        ];
        
        $pdo->prepare("INSERT INTO active_duels (player1_id, player2_id, current_turn_id, combat_state, turn_start_time) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$challengerId, $charId, $challengerId, json_encode($combatState)]);
        $duelId = $pdo->lastInsertId();
        
        // Update both players
        $pdo->prepare("UPDATE characters SET duel_id = ?, in_combat = 1 WHERE id = ?")->execute([$duelId, $challengerId]);
        $pdo->prepare("UPDATE characters SET duel_id = ?, in_combat = 1 WHERE id = ?")->execute([$duelId, $charId]);
        
        $pdo->prepare("UPDATE duel_requests SET status = 'accepted' WHERE id = ?")->execute([$reqId]);
        
        echo json_encode(['status' => 'success', 'duel_id' => $duelId]); exit;
    }
}

if ($action === 'get_duel_state') {
    if (!$char['duel_id']) { echo json_encode(['status' => 'ended']); exit; }
    
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
    $stmt = $pdo->prepare("SELECT last_seen FROM characters WHERE id = ?");
    $stmt->execute([$oppId]);
    $oppLast = $stmt->fetchColumn();
    
    if (time() - strtotime($oppLast) > 40) {
        // End duel due to disconnect
        $pdo->prepare("DELETE FROM active_duels WHERE id = ?")->execute([$duel['id']]);
        $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$duel['player1_id']]);
        $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$duel['player2_id']]);
        echo json_encode(['status' => 'ended', 'message' => 'Przeciwnik rozłączony!']); exit;
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
        $state['log'] = "Czas minął! Zmiana tury.";
        
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
    if (!$char['duel_id']) { echo json_encode(['status' => 'error', 'message' => 'Brak pojedynku']); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM active_duels WHERE id = ?");
    $stmt->execute([$char['duel_id']]);
    $duel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($duel['current_turn_id'] != $charId) { echo json_encode(['status' => 'error', 'message' => 'Nie twoja tura!']); exit; }
    
    $state = json_decode($duel['combat_state'], true);
    $isP1 = ($charId == $duel['player1_id']);
    $myKey = $isP1 ? 'p1' : 'p2';
    $enKey = $isP1 ? 'p2' : 'p1';
    
    if ($subAction === 'move') {
        $tx = (int)$input['x']; $ty = (int)$input['y'];
        $myPos = $state[$myKey.'_pos'];
        $enPos = $state[$enKey.'_pos'];
        
        $dist = getGameDistance($myPos['x'], $myPos['y'], $tx, $ty);
        if ($dist > 2.2) { echo json_encode(['status' => 'error', 'message' => 'Za daleko']); exit; }
        if ($state[$myKey.'_ap'] < ceil($dist)) { echo json_encode(['status' => 'error', 'message' => 'Brak AP']); exit; }
        if ($tx == $enPos['x'] && $ty == $enPos['y']) { echo json_encode(['status' => 'error', 'message' => 'Zajęte']); exit; }
        
        $state[$myKey.'_pos'] = ['x' => $tx, 'y' => $ty];
        $state[$myKey.'_ap'] -= ceil($dist);
    }
    
    if ($subAction === 'attack') {
        if ($state[$myKey.'_ap'] < 2) { echo json_encode(['status' => 'error', 'message' => 'Brak AP']); exit; }
        $myPos = $state[$myKey.'_pos']; $enPos = $state[$enKey.'_pos'];
        $dist = getGameDistance($myPos['x'], $myPos['y'], $enPos['x'], $enPos['y']);
        if ($dist > 2.2) { echo json_encode(['status' => 'error', 'message' => 'Za daleko']); exit; }
        
        $dmg = rand(10, 15) + $char['base_attack'];
        $enemyId = $isP1 ? $duel['player2_id'] : $duel['player1_id'];
        
        // Check Enemy HP for Kill
        $stmt = $pdo->prepare("SELECT hp, name FROM characters WHERE id = ?");
        $stmt->execute([$enemyId]);
        $enemy = $stmt->fetch();
        $newHp = $enemy['hp'] - $dmg;
        
        if ($newHp <= 0) {
            // KILL - End Duel
            $pdo->prepare("UPDATE characters SET hp = 0, duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$enemyId]);
            $pdo->prepare("UPDATE characters SET duel_id = NULL, in_combat = 0 WHERE id = ?")->execute([$charId]);
            $pdo->prepare("DELETE FROM active_duels WHERE id = ?")->execute([$duel['id']]);
            echo json_encode(['status' => 'success', 'dmg' => $dmg, 'win' => true, 'log' => "Pokonałeś gracza " . $enemy['name'] . "!"]); exit;
        } else {
            $pdo->prepare("UPDATE characters SET hp = ? WHERE id = ?")->execute([$newHp, $enemyId]);
            $state[$myKey.'_ap'] = 0;
            $state['log'] = "Gracz " . $char['name'] . " zadaje $dmg obrażeń!";
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
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Tura przeciwnika!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak AP!']); exit; }

    $tileType = 'water';
    foreach ($cState['tiles'] as $t) { if ($t['x'] == $tx && $t['y'] == $ty) { $tileType = $t['type']; break; } }
    if ($tileType === 'water') { echo json_encode(['status' => 'error', 'message' => 'Woda!']); exit; }
    if ($tx == $cState['enemy_pos']['x'] && $ty == $cState['enemy_pos']['y']) { echo json_encode(['status' => 'error', 'message' => 'Tam stoi wróg!']); exit; }
    
    $moveDist = getGameDistance($cState['player_pos']['x'], $cState['player_pos']['y'], $tx, $ty);
    $isHorizontal = ($cState['player_pos']['y'] == $ty);
    $maxDist = $isHorizontal ? 2.2 : 1.1;
    if ($moveDist > $maxDist) { echo json_encode(['status' => 'error', 'message' => 'Za daleko.']); exit; }
    
    $apCost = (int)ceil($moveDist);
    if ($cState['player_ap'] < $apCost) { echo json_encode(['status' => 'error', 'message' => 'Brak AP!']); exit; }
    
    $cState['player_ap'] -= $apCost;
    $cState['player_pos'] = ['x' => $tx, 'y' => $ty];
    if ($cState['player_ap'] <= 0) { $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2; }
    
    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState]); exit;
}

if ($action === 'combat_defend') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'player') { echo json_encode(['status' => 'error', 'message' => 'Tura przeciwnika!']); exit; }
    if ($cState['player_ap'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak AP!']); exit; }
    $cState['player_ap'] -= 1;
    $cState['is_defending'] = true; 
    if ($cState['player_ap'] <= 0) { $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2; }
    $pdo->prepare("UPDATE characters SET combat_state = ? WHERE id = ?")->execute([json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'combat_state' => $cState, 'message' => '🛡️ Postawa obronna! (-50% obrażeń)']); exit;
}

if ($action === 'combat_attack') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['player_ap'] < 2) { echo json_encode(['status' => 'error', 'message' => 'Atak wymaga 2 AP!']); exit; }
    $dist = getGameDistance($cState['player_pos']['x'], $cState['player_pos']['y'], $cState['enemy_pos']['x'], $cState['enemy_pos']['y']);
    $isHorizontal = ($cState['player_pos']['y'] == $cState['enemy_pos']['y']);
    $maxDist = $isHorizontal ? 2.2 : 1.1;
    if ($dist > $maxDist) { echo json_encode(['status' => 'error', 'message' => 'Wróg za daleko!']); exit;
    }
    
    $invStmt = $pdo->prepare("SELECT items.power FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND is_equipped = 1 AND items.type = 'weapon'");
    $invStmt->execute([$charId]);
    $weaponDmg = $invStmt->fetchColumn() ?: 0;
    
    $dmg = rand(10, 15) + $char['base_attack'] + $weaponDmg;
    $char['enemy_hp'] -= $dmg;
    $cState['player_ap'] = 0; 
    
    $log = "Zadajesz $dmg obrażeń!";
    $win = false;
    $tutorialFinishedNow = false;
    
    if ($char['enemy_hp'] <= 0) {
        $win = true; $xp = rand(15, 25);
        $char['xp'] += $xp; $char['in_combat'] = 0; $char['combat_state'] = NULL;
        
        // --- SPRAWDZENIE UKOŃCZENIA TUTORIALU ---
        if ($char['world_id'] == 1 && $char['tutorial_completed'] == 0) {
            $pdo->prepare("UPDATE characters SET tutorial_completed = 1 WHERE id = ?")->execute([$charId]);
            $char['tutorial_completed'] = 1;
            $tutorialFinishedNow = true;
            $log .= " WYGRANA! Ukończyłeś Tutorial!";
        } else {
            if ($char['xp'] >= $char['max_xp']) {
                $char['level']++; $char['xp'] = 0; $char['max_xp'] *= 1.2;
                $char['max_hp'] += 10; $char['hp'] = $char['max_hp'];
                $log .= " WYGRANA! AWANS!";
            } else {
                $log .= " WYGRANA!";
            }
        }
    } else {
        $cState['turn'] = 'enemy';
        $cState['enemy_ap'] = 2;
    }
    
    $pdo->prepare("UPDATE characters SET hp=?, enemy_hp=?, xp=?, max_xp=?, level=?, max_hp=?, in_combat=?, combat_state=? WHERE id=?")
        ->execute([$char['hp'], max(0,$char['enemy_hp']), $char['xp'], $char['max_xp'], $char['level'], $char['max_hp'], $char['in_combat'], json_encode($cState), $charId]);
        
    
    echo json_encode([
        'status' => 'success', 
        'enemy_hp' => max(0,$char['enemy_hp']), 
        'dmg_dealt' => $dmg,
        'win' => $win, 
        'log' => $log, 
        'combat_state' => $cState,
        'tutorial_finished' => $tutorialFinishedNow
    ]); exit;
}

if ($action === 'combat_use_item') {
    $itemId = (int)$input['item_id'];
    $stmt = $pdo->prepare("SELECT inventory.id, items.power, inventory.quantity FROM inventory JOIN items ON inventory.item_id = items.id WHERE character_id = ? AND items.id = ?");
    $stmt->execute([$charId, $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item || $item['quantity'] < 1) { echo json_encode(['status' => 'error', 'message' => 'Brak przedmiotu!']); exit; }
    
    $heal = $item['power'];
    $char['hp'] = min($char['max_hp'], $char['hp'] + $heal);
    if ($item['quantity'] > 1) { $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE id = ?")->execute([$item['id']]); } 
    else { $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$item['id']]); }
    
    $cState = json_decode($char['combat_state'], true);
    $cState['player_ap'] = 0; $cState['turn'] = 'enemy'; $cState['enemy_ap'] = 2;

    $pdo->prepare("UPDATE characters SET hp = ?, combat_state = ? WHERE id = ?")->execute([$char['hp'], json_encode($cState), $charId]);
    echo json_encode(['status' => 'success', 'hp' => $char['hp'], 'combat_state' => $cState, 'message' => "Uleczono o $heal HP. Tura wroga."]); exit;
}

if ($action === 'enemy_turn') {
    $cState = json_decode($char['combat_state'], true);
    if ($cState['turn'] !== 'enemy') { echo json_encode(['status' => 'error']); exit; }
    $log = ""; $actions_performed = []; 
    
    while ($cState['enemy_ap'] > 0) {
        $pl = $cState['player_pos']; $en = $cState['enemy_pos'];
        $dist = getGameDistance($pl['x'], $pl['y'], $en['x'], $en['y']);
        
        $isHorizontal = ($pl['y'] == $en['y']);
        $maxDist = $isHorizontal ? 2.2 : 1.1;
        if ($dist <= $maxDist && $cState['enemy_ap'] >= 2) {
            $dmg = rand(10, 18);
            if (!empty($cState['is_defending'])) {
                $dmg = ceil($dmg * 0.5);
                $log = "Wróg atakuje! Blokujesz ($dmg dmg).";
            } else {
                $log = "Wróg atakuje! Tracisz $dmg HP.";
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
    $pdo->prepare("UPDATE characters SET hp=?, combat_state=? WHERE id=?")->execute([$char['hp'], json_encode($cState), $charId]);
    echo json_encode(['status'=>'success', 'hp'=>$char['hp'], 'log'=>$log, 'combat_state'=>$cState, 'player_died'=>$died, 'actions' => $actions_performed]); exit;
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
    
    // Check for incoming duel requests
    $stmt = $pdo->prepare("SELECT r.id, c.name as challenger_name FROM duel_requests r JOIN characters c ON r.challenger_id = c.id WHERE r.target_id = ? AND r.status = 'pending'");
    $stmt->execute([$charId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if I am in a duel (to auto-start frontend)
    $myDuelId = $char['duel_id'];
    
    echo json_encode(['status' => 'success', 'players' => $otherPlayers, 'duel_requests' => $requests, 'my_duel_id' => $myDuelId]); exit;
}
?>