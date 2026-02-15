<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>RPG World Generator</title>
    <style>
        body { background: #121212; color: #e0e0e0; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .panel { background: #1e1e1e; padding: 40px; border-radius: 8px; border: 1px solid #333; text-align: center; width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h2 { color: #00e676; margin-top: 0; }
        input[type=range] { width: 100%; margin: 20px 0; cursor: pointer; accent-color: #00e676; }
        button { background: #00e676; color: #000; border: none; padding: 12px 24px; font-weight: bold; cursor: pointer; border-radius: 4px; font-size: 16px; transition: 0.2s; }
        button:hover { background: #00c853; transform: scale(1.05); }
        .labels { display: flex; justify-content: space-between; font-size: 12px; color: #888; margin-bottom: 5px; }
        #size-display { font-size: 24px; font-weight: bold; color: #fff; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="panel">
        <h2>Create New World</h2>
        <form method="POST">
            <div class="labels"><span>Small (25x50)</span><span>Huge (100x200)</span></div>
            <input type="range" name="size_scale" min="0" max="100" value="0" oninput="updateSize(this.value)">
            <div id="size-display">25 x 50</div>
            <button type="submit">GENERATE WORLD</button>
        </form>
    </div>
    <script>
        function updateSize(val) {
            const w = 25 + Math.round(75 * (val / 100));
            const h = 50 + Math.round(150 * (val / 100));
            document.getElementById('size-display').innerText = `${w} x ${h}`;
        }
    </script>
</body>
</html>
<?php
exit;
}

// Medieval-themed name generators
$prefixes = [
    'Kingdom of', 'Duchy of', 'Barony of', 'Principality of', 'Realm of', 'Shire of', 'County of',
    'Highlands of', 'Marches of', 'Protectorate of', 'Freehold of', 'Dominion of', 'Throne of'
];
$names = [
    'Aldor', 'Briar', 'Carth', 'Dunhelm', 'Eldwyn', 'Fallow', 'Gareth', 'Haven', 'Iver', 'Keld',
    'Lorien', 'Mire', 'Norwick', 'Oakmoor', 'Ravenholt', 'Stormveil', 'Greywatch', 'Valemor',
    'Ashenford', 'Brindle', 'Cindervale', 'Drakemoor', 'Everspring', 'Frostmere', 'Goldmere',
    'Hollowfen', 'Ironreach', 'Jadewood', 'Kingsrest', 'Lakeshire', 'Mistvale', 'Northpass',
    'Oakhollow', 'Pinewatch', 'Queensfell', 'Redwynd', 'Silverkeep', 'Thornfield', 'Umberford',
    'Westmarch', 'Wyrmhollow'
];

$scale = isset($_POST['size_scale']) ? (int)$_POST['size_scale'] : 0;
$width = 25 + (int)(75 * ($scale / 100));
$height = 50 + (int)(150 * ($scale / 100));

$worldName = $prefixes[array_rand($prefixes)] . ' ' . $names[array_rand($names)];

echo "<body style='background:#121212; color:#e0e0e0; font-family:sans-serif; text-align:center; padding-top:50px;'>";
echo "<h2>Creating world: $worldName ($width x $height)...</h2>";

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

    function applyHills(&$tiles, $width, $height) {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($tiles[$y][$x] !== 'mountain') continue;
                foreach (getNeighbors($x, $y, $width, $height) as $n) {
                    $nx = $n[0]; $ny = $n[1];
                    $t = $tiles[$ny][$nx];
                    if (in_array($t, ['grass', 'grass2', 'forest'], true)) {
                        if (rand(1, 100) <= 80) {
                            $tiles[$ny][$nx] = (rand(0, 1) === 0) ? 'hills' : 'hills2';
                        }
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
                        if (rand(1, 100) <= 60) {
                            $tiles[$ny][$nx] = 'farmlands';
                        }
                    }
                }
                if (rand(1, 100) <= 50) {
                    foreach (getNeighbors($x, $y, $width, $height) as $n) {
                        $nx = $n[0]; $ny = $n[1];
                        foreach (getNeighbors($nx, $ny, $width, $height) as $n2) {
                            $sx = $n2[0]; $sy = $n2[1];
                            if ($tiles[$sy][$sx] === 'grass' || $tiles[$sy][$sx] === 'grass2') {
                                if (rand(1, 100) <= 25) {
                                    $tiles[$sy][$sx] = 'farmlands';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    function distanceToEdge($x, $y, $width, $height) {
        return min($x, $y, $width - 1 - $x, $height - 1 - $y);
    }

    function pickCenterSpawn($width, $height, $tiles) {
        $cx = (int)floor($width / 2);
        $cy = (int)floor($height / 2);
        if ($tiles[$cy][$cx] !== 'water' && $tiles[$cy][$cx] !== 'mountain') {
            return [$cx, $cy];
        }
        for ($r = 1; $r < max($width, $height); $r++) {
            for ($y = max(0, $cy - $r); $y <= min($height - 1, $cy + $r); $y++) {
                for ($x = max(0, $cx - $r); $x <= min($width - 1, $cx + $r); $x++) {
                    if (abs($x - $cx) !== $r && abs($y - $cy) !== $r) continue;
                    if ($tiles[$y][$x] !== 'water' && $tiles[$y][$x] !== 'mountain') {
                        return [$x, $y];
                    }
                }
            }
        }
        return [$cx, $cy];
    }

    function growCluster(&$tiles, $width, $height, $seedX, $seedY, $type, $size, $avoid = []) {
        $x = $seedX; $y = $seedY;
        $tiles[$y][$x] = $type;
        for ($i = 0; $i < $size; $i++) {
            $neighbors = getNeighbors($x, $y, $width, $height);
            if (!$neighbors) break;
            $pick = $neighbors[array_rand($neighbors)];
            $nx = $pick[0]; $ny = $pick[1];
            if (in_array($tiles[$ny][$nx], $avoid, true)) {
                continue;
            }
            $tiles[$ny][$nx] = $type;
            $x = $nx; $y = $ny;
        }
    }

    function applyFarmlandsRadius(&$tiles, $width, $height, $cx, $cy, $radius) {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $d = hexDistance($x, $y, $cx, $cy);
                if ($d > $radius) continue;
                $t = $tiles[$y][$x];
                if (!in_array($t, ['grass', 'grass2', 'forest', 'hills', 'hills2'], true)) continue;
                $chance = 0;
                if ($d <= 1) $chance = 90;
                elseif ($d <= 2) $chance = 70;
                else $chance = 45;
                if (rand(1, 100) <= $chance) {
                    $tiles[$y][$x] = 'farmlands';
                }
            }
        }
    }

    // 1. Dodajemy wpis do tabeli worlds (is_tutorial = 0)
    $stmt = $pdo->prepare("INSERT INTO worlds (name, width, height, is_tutorial) VALUES (?, ?, ?, 0)");
    $stmt->execute([$worldName, $width, $height]);
    $worldId = $pdo->lastInsertId();

    echo "ID nowego świata: $worldId<br>";

    // 2. Generujemy kafelki w pamieci
    $tiles = [];
    for ($y = 0; $y < $height; $y++) {
        $row = [];
        for ($x = 0; $x < $width; $x++) {
            $row[] = 'grass';
        }
        $tiles[] = $row;
    }

    // Woda na brzegach (stale 3 pasy na gorze i dole mapy)
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $d = distanceToEdge($x, $y, $width, $height);
            $topBand = ($y <= 2);
            $bottomBand = ($y >= $height - 3);
            if ($topBand || $bottomBand) {
                $tiles[$y][$x] = 'water';
                continue;
            }
            if ($d === 0) {
                $tiles[$y][$x] = 'water';
            } elseif ($d === 1 && rand(1, 100) <= 65) {
                $tiles[$y][$x] = 'water';
            } elseif ($d === 2 && rand(1, 100) <= 35) {
                $tiles[$y][$x] = 'water';
            }
            if ($d === 3 && rand(1, 100) <= 25) {
                $tiles[$y][$x] = 'water';
            }
        }
    }

    // Skupiska gor
    $mountainSeeds = max(4, (int)round(($width * $height) / 440));
    for ($i = 0; $i < $mountainSeeds; $i++) {
        $mx = rand(6, max(6, $width - 7));
        $my = rand(6, max(6, $height - 7));
        if (distanceToEdge($mx, $my, $width, $height) < 6) continue;
        if ($tiles[$my][$mx] === 'water') continue;
        growCluster($tiles, $width, $height, $mx, $my, 'mountain', rand(10, 26), ['water']);
    }

    // Mini jeziora
    $lakeSeeds = max(3, (int)round(($width * $height) / 820));
    for ($i = 0; $i < $lakeSeeds; $i++) {
        $lx = rand(2, max(2, $width - 3));
        $ly = rand(2, max(2, $height - 3));
        if ($tiles[$ly][$lx] === 'mountain') continue;
        growCluster($tiles, $width, $height, $lx, $ly, 'water', rand(7, 18), ['mountain']);
    }

    // Mini rzeki (krotkie strugi)
    $riverSeeds = max(3, (int)round(($width * $height) / 900));
    for ($i = 0; $i < $riverSeeds; $i++) {
        $rx = rand(1, max(1, $width - 2));
        $ry = rand(1, max(1, $height - 2));
        if ($tiles[$ry][$rx] === 'mountain') continue;
        $len = rand(12, 28);
        $x = $rx; $y = $ry;
        for ($s = 0; $s < $len; $s++) {
            if ($tiles[$y][$x] !== 'mountain') {
                $tiles[$y][$x] = 'water';
            }
            $neighbors = getNeighbors($x, $y, $width, $height);
            if (!$neighbors) break;
            $pick = $neighbors[array_rand($neighbors)];
            $x = $pick[0]; $y = $pick[1];
            $x = max(1, min($width - 2, $x));
            $y = max(1, min($height - 2, $y));
        }
    }

    // Skupiska lasu
    $forestSeeds = max(6, (int)round(($width * $height) / 260));
    for ($i = 0; $i < $forestSeeds; $i++) {
        $fx = rand(1, max(1, $width - 2));
        $fy = rand(1, max(1, $height - 2));
        if ($tiles[$fy][$fx] === 'water' || $tiles[$fy][$fx] === 'mountain') continue;
        growCluster($tiles, $width, $height, $fx, $fy, 'forest', rand(16, 40), ['water', 'mountain']);
    }

    // Grass2 wokol lasu (male laski przy lesie)
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ($tiles[$y][$x] !== 'forest') continue;
            foreach (getNeighbors($x, $y, $width, $height) as $n) {
                $nx = $n[0]; $ny = $n[1];
                if ($tiles[$ny][$nx] === 'grass' && rand(1, 100) <= 55) {
                    $tiles[$ny][$nx] = 'grass2';
                }
            }
        }
    }

    // Skupiska grass2 (male laski)
    $grass2Seeds = max(6, (int)round(($width * $height) / 240));
    for ($i = 0; $i < $grass2Seeds; $i++) {
        $gx = rand(1, max(1, $width - 2));
        $gy = rand(1, max(1, $height - 2));
        if ($tiles[$gy][$gx] === 'water' || $tiles[$gy][$gx] === 'mountain') continue;
        if ($tiles[$gy][$gx] === 'forest') continue;
        growCluster($tiles, $width, $height, $gx, $gy, 'grass2', rand(12, 32), ['water', 'mountain', 'forest']);
    }

    // Mieszanie grass/grass2 zeby nie bylo zbyt jednolicie
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $t = $tiles[$y][$x];
            if ($t === 'grass' && rand(1, 100) <= 12) {
                $tiles[$y][$x] = 'grass2';
            } elseif ($t === 'grass2' && rand(1, 100) <= 4) {
                $tiles[$y][$x] = 'grass';
            }
        }
    }

    // Punkt startowy (spawn) w nowym swiecie - centrum mapy
    $centerSpawn = pickCenterSpawn($width, $height, $tiles);
    $tiles[$centerSpawn[1]][$centerSpawn[0]] = 'city_capital';

    // 2-4 wiosek blisko miasta (nie wliczane do puli)
    $nearbyVillages = rand(2, 4);
    $placedNear = 0;
    $nearAttempts = $nearbyVillages * 20;
    $nearCoords = [];
    while ($placedNear < $nearbyVillages && $nearAttempts-- > 0) {
        $vx = rand(max(1, $centerSpawn[0] - 3), min($width - 2, $centerSpawn[0] + 3));
        $vy = rand(max(1, $centerSpawn[1] - 3), min($height - 2, $centerSpawn[1] + 3));
        $dist = hexDistance($vx, $vy, $centerSpawn[0], $centerSpawn[1]);
        if ($dist < 2 || $dist > 3) continue;
        if (in_array($tiles[$vy][$vx], ['water', 'mountain', 'city_capital', 'city_village'], true)) continue;
        $tooClose = false;
        foreach ($nearCoords as $c) {
            if (hexDistance($vx, $vy, $c[0], $c[1]) < 2) { $tooClose = true; break; }
        }
        if ($tooClose) continue;
        $tiles[$vy][$vx] = 'city_village';
        $nearCoords[] = [$vx, $vy];
        $placedNear++;
    }

    // Duze pola wokol miasta i pobliskich wiosek
    applyFarmlandsRadius($tiles, $width, $height, $centerSpawn[0], $centerSpawn[1], 3);
    foreach ($nearCoords as $c) {
        applyFarmlandsRadius($tiles, $width, $height, $c[0], $c[1], 2);
    }

    // 3. Dodawanie wiosek
    $area = $width * $height;
    $scaleFactor = min(1, $area / (100 * 200));
    $villagesBase = 3 + (int)round(8 * $scaleFactor);
    $villagesToPlace = max(3, min(10, $villagesBase + rand(0, 2)));
    $vCount = 0;
    $maxAttempts = $villagesToPlace * 20;
    while ($vCount < $villagesToPlace) {
        if ($maxAttempts-- <= 0) break;
        $vx = rand(1, max(1, $width - 2));
        $vy = rand(1, max(1, $height - 2));
        if ($tiles[$vy][$vx] === 'city_capital') continue;
        if (in_array($tiles[$vy][$vx], ['water', 'mountain'], true)) continue;
        if (abs($vx - $centerSpawn[0]) + abs($vy - $centerSpawn[1]) < 6) continue;
        $tiles[$vy][$vx] = 'city_village';
        $vCount++;
    }

    // 4. Hills obok gor, farmlands wokol wiosek
    applyHills($tiles, $width, $height);
    applyFarmlands($tiles, $width, $height);

    // 5. Zapis do bazy
    $stmt = $pdo->prepare("INSERT INTO map_tiles (world_id, x, y, type) VALUES (?, ?, ?, ?)");
    $count = 0;
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $stmt->execute([$worldId, $x, $y, $tiles[$y][$x]]);
            $count++;
        }
    }

    echo "<h1 style='color:green'>SUCCESS! Added world '$worldName'.</h1>";
    echo "<a href='index.php' style='font-size:20px; font-weight:bold; padding:10px; background:#333; color:white; text-decoration:none;'>RETURN TO GAME</a>";

} catch (PDOException $e) {
    die("SQL Error: " . $e->getMessage());
}
?>