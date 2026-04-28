<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Experimental Island Generator</title>
    <style>
        body { background: #050011; color: #e0e0e0; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .panel { background: #1a0b2e; padding: 40px; border-radius: 8px; border: 2px solid #ffd700; text-align: center; width: 400px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); }
        h2 { color: #ffd700; margin-top: 0; }
        input[type=number], input[type=text] { width: 100%; box-sizing: border-box; margin: 10px 0 20px; padding: 10px; border-radius: 4px; border: 1px solid #555; background: #222; color: #fff; }
        button { background: linear-gradient(180deg, #4caf50, #2e7d32); color: #fff; border: 2px solid #1b5e20; padding: 12px 24px; font-weight: bold; cursor: pointer; border-radius: 4px; font-size: 16px; transition: 0.2s; width: 100%; }
        button:hover { transform: scale(1.05); filter: brightness(1.2); }
        .desc { font-size: 12px; color: #aaa; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="panel">
        <h2>🏝️ Generate Island World</h2>
        <p class="desc">Experimental generation that creates a single cohesive landmass surrounded by water using tectonic-blob algorithms mapped on a hex grid.</p>
        <form method="POST">
            <div style="text-align: left; font-size: 12px; color: #ffd700;">World Name</div>
            <input type="text" name="world_name" value="Isle of Dawn" required>
            
            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <div style="text-align: left; font-size: 12px; color: #ffd700;">Width</div>
                    <input type="number" name="width" value="60" min="30" max="200" required>
                </div>
                <div style="flex:1;">
                    <div style="text-align: left; font-size: 12px; color: #ffd700;">Height</div>
                    <input type="number" name="height" value="60" min="30" max="200" required>
                </div>
            </div>
            
            <button type="submit">GENERATE ISLAND</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

$width = max(30, min(200, (int)$_POST['width']));
$height = max(30, min(200, (int)$_POST['height']));
$worldName = trim($_POST['world_name']) ?: 'Experimental Island';

// 1. Initialize Map with Water
$tiles = [];
for ($y = 0; $y < $height; $y++) {
    $row = [];
    for ($x = 0; $x < $width; $x++) {
        $row[] = 'water';
    }
    $tiles[] = $row;
}

// --- Hexagonal Helper Functions ---
function offsetToCube($col, $row) {
    $q = $col - ($row - ($row & 1)) / 2;
    $r = $row;
    $s = -$q - $r;
    return ['q' => $q, 'r' => $r, 's' => $s];
}

function hexDistance($x1, $y1, $x2, $y2) {
    $a = offsetToCube($x1, $y1);
    $b = offsetToCube($x2, $y2);
    return (abs($a['q'] - $b['q']) + abs($a['r'] - $b['r']) + abs($a['s'] - $b['s'])) / 2;
}

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

// 2. Tectonic Plates Island Formation
$cx = (int)($width / 2);
$cy = (int)($height / 2);
$maxRadius = min($width, $height) / 2 - 4; // Zapewnia margines wody na bokach

$plates = [];
$plates[] = ['x' => $cx, 'y' => $cy, 'r' => $maxRadius * 0.6]; // Główne Jądro wyspy

// Dodatkowe nachodzące koła tworzące półwyspy i nieregularny kształt
$numPlates = rand(6, 12);
for ($i = 0; $i < $numPlates; $i++) {
    $angle = rand(0, 360) * M_PI / 180;
    $dist = rand(0, (int)($maxRadius * 0.6));
    $px = $cx + cos($angle) * $dist;
    $py = $cy + sin($angle) * $dist;
    $pr = rand((int)($maxRadius * 0.2), (int)($maxRadius * 0.5));
    $plates[] = ['x' => $px, 'y' => $py, 'r' => $pr];
}

// Negatywne koła "wycinające" ląd (tworzące zatoki i wklęśnięcia wybrzeża)
$negativePlates = [];
$numNegPlates = rand(4, 8);
for ($i = 0; $i < $numNegPlates; $i++) {
    $angle = rand(0, 360) * M_PI / 180;
    $dist = rand((int)($maxRadius * 0.4), (int)($maxRadius * 0.9));
    $px = $cx + cos($angle) * $dist;
    $py = $cy + sin($angle) * $dist;
    $pr = rand((int)($maxRadius * 0.2), (int)($maxRadius * 0.6));
    $negativePlates[] = ['x' => $px, 'y' => $py, 'r' => $pr];
}

for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        $isLand = false;
        foreach ($plates as $p) {
            $dist = hexDistance($x, $y, $p['x'], $p['y']);
            if ($dist <= $p['r'] + rand(-1, 1)) {
                $isLand = true;
                break;
            }
        }
        if ($isLand) {
            foreach ($negativePlates as $np) {
                if (hexDistance($x, $y, $np['x'], $np['y']) <= $np['r'] + rand(-1, 0)) {
                    $isLand = false;
                    break;
                }
            }
        }
        if ($isLand) {
            $tiles[$y][$x] = 'grass';
        }
    }
}

// 3. Wygładzanie (Cellular Automata)
$newTiles = $tiles;
for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        $neighbors = getNeighbors($x, $y, $width, $height);
        $waterCount = 0;
        $grassCount = 0;
        foreach ($neighbors as $n) {
            if ($tiles[$n[1]][$n[0]] === 'water') $waterCount++;
            else $grassCount++;
        }
        // Usuń samotne wysepki lub zasyp dziury
        if ($tiles[$y][$x] === 'water' && $grassCount >= 4) $newTiles[$y][$x] = 'grass';
        if ($tiles[$y][$x] === 'grass' && $waterCount >= 4) $newTiles[$y][$x] = 'water';
    }
}
$tiles = $newTiles;

// Zidentyfikuj główny ocean (flood-fill od krawędzi), by odróżnić go od śródlądowych jezior
$ocean = [];
$queue = [];
for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        if (($x === 0 || $y === 0 || $x === $width - 1 || $y === $height - 1) && $tiles[$y][$x] === 'water') {
            $ocean["{$x}_{$y}"] = true;
            $queue[] = [$x, $y];
        }
    }
}
while (!empty($queue)) {
    $curr = array_shift($queue);
    foreach (getNeighbors($curr[0], $curr[1], $width, $height) as $n) {
        $nx = $n[0]; $ny = $n[1];
        if ($tiles[$ny][$nx] === 'water' && !isset($ocean["{$nx}_{$ny}"])) {
            $ocean["{$nx}_{$ny}"] = true;
            $queue[] = [$nx, $ny];
        }
    }
}

// 4. Generowanie rzadszych drzew (grass2) w głębi lądu (z dala od wody)
for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        if ($tiles[$y][$x] === 'grass') {
            $neighbors = getNeighbors($x, $y, $width, $height);
            $isCoast = false;
            foreach ($neighbors as $n) {
                if ($tiles[$n[1]][$n[0]] === 'water') {
                    $isCoast = true;
                    break;
                }
            }
            if (!$isCoast && rand(1, 100) <= 30) {
                $tiles[$y][$x] = 'grass2';
            }
        }
    }
}

// 5. Generowanie Biome'ów (Góry, Lasy)
function growBiome(&$tiles, $width, $height, $type, $count, $minSize, $maxSize, $allowedOn) {
    for ($i = 0; $i < $count; $i++) {
        $sx = rand(0, $width - 1);
        $sy = rand(0, $height - 1);
        if (!in_array($tiles[$sy][$sx], $allowedOn)) continue;
        
        $size = rand($minSize, $maxSize);
        $tiles[$sy][$sx] = $type;
        $current = [[$sx, $sy]];
        
        for ($s = 0; $s < $size; $s++) {
            if (empty($current)) break;
            $idx = array_rand($current);
            $curr = $current[$idx];
            
            $neighbors = getNeighbors($curr[0], $curr[1], $width, $height);
            shuffle($neighbors);
            foreach ($neighbors as $n) {
                $nx = $n[0]; $ny = $n[1];
                if (in_array($tiles[$ny][$nx], $allowedOn)) {
                    $tiles[$ny][$nx] = $type;
                    $current[] = [$nx, $ny];
                    break;
                }
            }
        }
    }
}

$area = $width * $height;
growBiome($tiles, $width, $height, 'mountain', max(3, $area / 400), 10, 30, ['grass']);
growBiome($tiles, $width, $height, 'forest', max(6, $area / 200), 15, 45, ['grass', 'grass2']);

// 6. Wzgórza (Hills) obok Gór
for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        if ($tiles[$y][$x] === 'mountain') {
            foreach (getNeighbors($x, $y, $width, $height) as $n) {
                $nx = $n[0]; $ny = $n[1];
                if ($tiles[$ny][$nx] === 'grass' && rand(1, 100) <= 70) {
                    $tiles[$ny][$nx] = (rand(0, 1) == 0) ? 'hills' : 'hills2';
                }
            }
        }
    }
}

// 7. Osady i Miasta
$validCityTiles = [];
for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        if (in_array($tiles[$y][$x], ['grass', 'forest', 'hills'])) {
            // Upewnij się, że potencjalne miejsce pod miasto nie graniczy z morzem (odległość min. 2 kratki)
            $nearOcean = false;
            foreach (getNeighbors($x, $y, $width, $height) as $n) {
                if (isset($ocean["{$n[0]}_{$n[1]}"])) { $nearOcean = true; break; }
                foreach (getNeighbors($n[0], $n[1], $width, $height) as $n2) {
                    if (isset($ocean["{$n2[0]}_{$n2[1]}"])) { $nearOcean = true; break 2; }
                }
            }
            if (!$nearOcean) {
                $validCityTiles[] = ['x' => $x, 'y' => $y];
            }
        }
    }
}

$numCities = max(1, (int)round($area / 4000));
$capitals = [];

// Pierwsze miasto (stolica) w okolicach centrum
$bestCenter = null;
$minDist = 9999;
foreach ($validCityTiles as $t) {
    $d = hexDistance($t['x'], $t['y'], $cx, $cy);
    if ($d < $minDist) {
        $minDist = $d;
        $bestCenter = $t;
    }
}
if ($bestCenter) {
    $tiles[$bestCenter['y']][$bestCenter['x']] = 'city_capital';
    $capitals[] = $bestCenter;
} else {
    $bestCenter = ['x' => $cx, 'y' => $cy]; // Fallback
    $tiles[$cy][$cx] = 'city_capital';
    $capitals[] = $bestCenter;
}

// Kolejne miasta na obrzeżach wyspy
shuffle($validCityTiles);
foreach ($validCityTiles as $t) {
    if (count($capitals) >= $numCities) break;
    
    $tooClose = false;
    foreach ($capitals as $c) {
        if (hexDistance($t['x'], $t['y'], $c['x'], $c['y']) < 15) {
            $tooClose = true;
            break;
        }
    }
    $distToCenter = hexDistance($t['x'], $t['y'], $cx, $cy);
    if (!$tooClose && $distToCenter > 10) {
        $tiles[$t['y']][$t['x']] = 'city_capital';
        $capitals[] = $t;
    }
}

// Wioski - generowane znacznie rzadziej
$placedVillages = [];

// 1. Wioski skupione wokół miast (1-3 na każde miasto)
foreach ($capitals as $c) {
    $villagesNearThisCity = rand(1, 3);
    $placedHere = 0;
    
    shuffle($validCityTiles);
    foreach ($validCityTiles as $t) {
        if ($placedHere >= $villagesNearThisCity) break;
        
        $distToCity = hexDistance($t['x'], $t['y'], $c['x'], $c['y']);
        if ($distToCity >= 3 && $distToCity <= 6) {
            $tooClose = false;
            foreach ($placedVillages as $v) {
                if (hexDistance($t['x'], $t['y'], $v['x'], $v['y']) < 3) {
                    $tooClose = true; break;
                }
            }
            foreach ($capitals as $cap) {
                if ($cap === $c) continue;
                if (hexDistance($t['x'], $t['y'], $cap['x'], $cap['y']) < 3) {
                    $tooClose = true; break;
                }
            }
            if (!$tooClose) {
                $tiles[$t['y']][$t['x']] = 'city_village';
                $placedVillages[] = $t;
                $placedHere++;
            }
        }
    }
}

// 2. Pojedyncze wioski (samotne, rozsiane po mapie)
$numStandaloneVillages = max(2, (int)round($area / 2000));
$placedStandalone = 0;
shuffle($validCityTiles);
foreach ($validCityTiles as $t) {
    if ($placedStandalone >= $numStandaloneVillages) break;
    
    $tooClose = false;
    foreach ($capitals as $c) {
        if (hexDistance($t['x'], $t['y'], $c['x'], $c['y']) < 10) {
            $tooClose = true; break;
        }
    }
    foreach ($placedVillages as $v) {
        if (hexDistance($t['x'], $t['y'], $v['x'], $v['y']) < 6) {
            $tooClose = true; break;
        }
    }
    if (!$tooClose) {
        $tiles[$t['y']][$t['x']] = 'city_village';
        $placedVillages[] = $t;
        $placedStandalone++;
    }
}

// Pola uprawne dookoła miast i wiosek
$allCities = array_merge($capitals, $placedVillages);
foreach ($allCities as $c) {
    foreach (getNeighbors($c['x'], $c['y'], $width, $height) as $n) {
        $nx = $n[0]; $ny = $n[1];
        if (in_array($tiles[$ny][$nx], ['grass', 'grass2', 'forest', 'hills'])) {
            if (rand(1, 100) <= 80) $tiles[$ny][$nx] = 'farmlands';
        }
    }
}

// 8. Zapis Do Bazy Danych
try {
    $stmt = $pdo->prepare("INSERT INTO worlds (name, width, height, is_tutorial) VALUES (?, ?, ?, 0)");
    $stmt->execute([$worldName, $width, $height]);
    $worldId = $pdo->lastInsertId();

    $pdo->beginTransaction();
    $batchSize = 1000;
    $sql_prefix = "INSERT INTO map_tiles (world_id, x, y, type) VALUES ";
    $insert_query_parts = [];
    $insert_data = [];

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $insert_query_parts[] = "(?, ?, ?, ?)";
            $insert_data[] = $worldId;
            $insert_data[] = $x;
            $insert_data[] = $y;
            $insert_data[] = $tiles[$y][$x];

            if (count($insert_query_parts) >= $batchSize) {
                $stmt = $pdo->prepare($sql_prefix . implode(',', $insert_query_parts));
                $stmt->execute($insert_data);
                $insert_query_parts = [];
                $insert_data = [];
            }
        }
    }
    if (!empty($insert_query_parts)) {
        $stmt = $pdo->prepare($sql_prefix . implode(',', $insert_query_parts));
        $stmt->execute($insert_data);
    }
    $pdo->commit();
    
    echo "<body style='background:#050011; color:#e0e0e0; font-family:sans-serif; text-align:center; padding-top:100px;'>";
    echo "<h1 style='color:#00e676; text-shadow:0 0 10px #00e676;'>✔ Success!</h1>";
    echo "<p>Experimental Island World <strong style='color:#ffd700'>$worldName</strong> generated successfully.</p>";
    echo "<br><a href='index.php' style='padding:12px 24px; background:#4caf50; color:white; font-weight:bold; text-decoration:none; border-radius:5px;'>Back to Game</a>";
    echo "</body>";
} catch (Exception $e) {
    $pdo->rollBack();
    die("DB Error: " . $e->getMessage());
}
?>