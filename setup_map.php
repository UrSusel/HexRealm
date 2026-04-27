<?php
set_time_limit(0); // Disable execution time limit for large generation
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
            <div style="margin-bottom: 20px; text-align: left;">
                <div style="color: #888; font-size: 12px; margin-bottom: 5px;">World Type</div>
                <select name="world_type" style="width: 100%; padding: 10px; background: #222; color: #fff; border: 1px solid #444; border-radius: 4px; outline: none; font-size: 14px;">
                    <option value="continent">Continent</option>
                    <option value="island">Island (Fantasy)</option>
                </select>
            </div>
            <div class="labels"><span>Small (25x50)</span><span>Epic (500x1000)</span></div>
            <input type="range" name="world_width" min="25" max="500" step="25" value="50" oninput="updateSize(this.value)">
            <div id="size-display">50 x 100</div>
            <button type="submit">GENERATE WORLD</button>
        </form>
    </div>
    <script>
        function updateSize(val) {
            const w = parseInt(val, 10);
            const h = w * 2;
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

$width = isset($_POST['world_width']) ? max(25, min(500, (int)$_POST['world_width'])) : 50;
$height = $width * 2;

$worldType = $_POST['world_type'] ?? 'continent';

if ($worldType === 'island') {
    $worldName = 'Isle of ' . $names[array_rand($names)];
} else {
    $worldName = $prefixes[array_rand($prefixes)] . ' ' . $names[array_rand($names)];
}

echo "<body style='background:#121212; color:#e0e0e0; font-family:sans-serif; text-align:center; padding-top:50px;'>";
echo "<h2>Creating world: $worldName ($width x $height)...</h2>";

// Setup real-time streaming
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);
echo str_repeat(' ', 4096); // Force browser to start rendering

echo "<canvas id='vizCanvas' width='800' height='500' style='background:#050011; border:2px solid #333; border-radius:4px; max-width:90vw; max-height:60vh; box-shadow: 0 0 20px rgba(0,230,118,0.2); margin: 0 auto; display: block;'></canvas>";
echo "<div id='vizStatus' style='margin-top:15px; font-size: 18px; color:#00e676; font-weight:bold; text-shadow: 0 0 5px #00e676;'>Initializing...</div>";

echo "<script>
    const canvas = document.getElementById('vizCanvas');
    const ctx = canvas.getContext('2d');
    const colors = {
        'grass': '#4caf50', 'grass2': '#8bc34a', 'forest': '#2e7d32',
        'mountain': '#757575', 'water': '#1e88e5', 'city_capital': '#ffd700',
        'city_village': '#ff9800', 'hills': '#a1887f', 'hills2': '#8d6e63',
        'farmlands': '#d4e157'
    };
    function updateMap(tiles, msg) {
        document.getElementById('vizStatus').innerText = msg;
        if (!tiles || tiles.length === 0) return;
        const h = tiles.length;
        const w = tiles[0].length;
        const tW = canvas.width / (w + 0.5);
        const tH = canvas.height / h;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                const offsetX = (y % 2 !== 0) ? (tW / 2) : 0;
                ctx.fillStyle = colors[tiles[y][x]] || '#333';
                ctx.fillRect((x * tW) + offsetX, y * tH, tW + 0.5, tH + 0.5);
            }
        }
    }
</script>";

function viz($tiles, $msg) {
    echo "<script>updateMap(" . json_encode($tiles) . ", " . json_encode($msg) . ");</script>\n";
    @flush();
    usleep(300000); // 300ms pause for visual effect
}

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

    // 1. Dodajemy wpis do tabeli worlds (is_tutorial = 0)
    $stmt = $pdo->prepare("INSERT INTO worlds (name, width, height, is_tutorial) VALUES (?, ?, ?, 0)");
    $stmt->execute([$worldName, $width, $height]);
    $worldId = $pdo->lastInsertId();

    echo "ID nowego świata: $worldId<br>";

    // 2. Generujemy kafelki w pamieci
    $tiles = [];
    if ($worldType === 'continent') {
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $row[] = 'grass';
            }
            $tiles[] = $row;
        }

        viz($tiles, "Generating Base Landmass...");

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

        viz($tiles, "Flooding Edges...");

        $mountainSeeds = max(4, (int)round(($width * $height) / 440));
        for ($i = 0; $i < $mountainSeeds; $i++) {
            $mx = rand(6, max(6, $width - 7));
            $my = rand(6, max(6, $height - 7));
            if (distanceToEdge($mx, $my, $width, $height) < 6) continue;
            if ($tiles[$my][$mx] === 'water') continue;
            growCluster($tiles, $width, $height, $mx, $my, 'mountain', rand(10, 26), ['water']);
        }

        viz($tiles, "Raising Mountains...");

        $lakeSeeds = max(3, (int)round(($width * $height) / 820));
        for ($i = 0; $i < $lakeSeeds; $i++) {
            $lx = rand(2, max(2, $width - 3));
            $ly = rand(2, max(2, $height - 3));
            if ($tiles[$ly][$lx] === 'mountain') continue;
            growCluster($tiles, $width, $height, $lx, $ly, 'water', rand(7, 18), ['mountain']);
        }

        viz($tiles, "Forming Lakes...");

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

        viz($tiles, "Carving Rivers...");

        $forestSeeds = max(6, (int)round(($width * $height) / 260));
        for ($i = 0; $i < $forestSeeds; $i++) {
            $fx = rand(1, max(1, $width - 2));
            $fy = rand(1, max(1, $height - 2));
            if ($tiles[$fy][$fx] === 'water' || $tiles[$fy][$fx] === 'mountain') continue;
            growCluster($tiles, $width, $height, $fx, $fy, 'forest', rand(16, 40), ['water', 'mountain']);
        }

        viz($tiles, "Growing Forests...");

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

        $grass2Seeds = max(6, (int)round(($width * $height) / 240));
        for ($i = 0; $i < $grass2Seeds; $i++) {
            $gx = rand(1, max(1, $width - 2));
            $gy = rand(1, max(1, $height - 2));
            if ($tiles[$gy][$gx] === 'water' || $tiles[$gy][$gx] === 'mountain') continue;
            if ($tiles[$gy][$gx] === 'forest') continue;
            growCluster($tiles, $width, $height, $gx, $gy, 'grass2', rand(12, 32), ['water', 'mountain', 'forest']);
        }

        viz($tiles, "Adding Vegetation Variety...");

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

        $centerSpawn = pickCenterSpawn($width, $height, $tiles);
        $tiles[$centerSpawn[1]][$centerSpawn[0]] = 'city_capital';

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

        viz($tiles, "Establishing Capital...");

        applyFarmlandsRadius($tiles, $width, $height, $centerSpawn[0], $centerSpawn[1], 3);
        foreach ($nearCoords as $c) {
            applyFarmlandsRadius($tiles, $width, $height, $c[0], $c[1], 2);
        }

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

        viz($tiles, "Placing Settlements...");

        applyHills($tiles, $width, $height);
        applyFarmlands($tiles, $width, $height);

        viz($tiles, "Finalizing Details...");

    } else {
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $row[] = 'water';
            }
            $tiles[] = $row;
        }

        viz($tiles, "Flooding World...");

        $cx = (int)($width / 2);
        $cy = (int)($height / 2);
        $maxRadiusX = $width / 2 - 4;
        $maxRadiusY = $height / 2 - 4;
        $baseRadius = min($maxRadiusX, $maxRadiusY);

        $plates = [];
        
        $spineCount = 5;
        if ($maxRadiusY > $maxRadiusX * 1.2) {
            $step = ($maxRadiusY * 1.6) / ($spineCount - 1);
            $startY = $cy - ($maxRadiusY * 0.8);
            for ($i = 0; $i < $spineCount; $i++) {
                $plates[] = ['x' => $cx, 'y' => $startY + ($i * $step), 'r' => $maxRadiusX * 0.75];
            }
        } elseif ($maxRadiusX > $maxRadiusY * 1.2) {
            $step = ($maxRadiusX * 1.6) / ($spineCount - 1);
            $startX = $cx - ($maxRadiusX * 0.8);
            for ($i = 0; $i < $spineCount; $i++) {
                $plates[] = ['x' => $startX + ($i * $step), 'y' => $cy, 'r' => $maxRadiusY * 0.75];
            }
        } else {
            $plates[] = ['x' => $cx, 'y' => $cy, 'r' => $baseRadius * 0.8];
            $plates[] = ['x' => $cx - $baseRadius*0.3, 'y' => $cy - $baseRadius*0.3, 'r' => $baseRadius * 0.6];
            $plates[] = ['x' => $cx + $baseRadius*0.3, 'y' => $cy + $baseRadius*0.3, 'r' => $baseRadius * 0.6];
        }

        $area = $width * $height;
        $numPlates = max(15, min(60, (int)($area / 300)));

        for ($i = 0; $i < $numPlates; $i++) {
            $angle = rand(0, 360) * M_PI / 180;
            $distX = rand(0, (int)($maxRadiusX * 0.7));
            $distY = rand(0, (int)($maxRadiusY * 0.7));
            $px = $cx + cos($angle) * $distX;
            $py = $cy + sin($angle) * $distY;
            $pr = rand((int)($baseRadius * 0.2), (int)($baseRadius * 0.5));
            $plates[] = ['x' => $px, 'y' => $py, 'r' => $pr];
        }

        $negativePlates = [];
        $numNegPlates = rand((int)($numPlates * 0.4), (int)($numPlates * 0.8));
        for ($i = 0; $i < $numNegPlates; $i++) {
            $angle = rand(0, 360) * M_PI / 180;
            $distX = rand((int)($maxRadiusX * 0.6), (int)($maxRadiusX * 0.95));
            $distY = rand((int)($maxRadiusY * 0.6), (int)($maxRadiusY * 0.95));
            $px = $cx + cos($angle) * $distX;
            $py = $cy + sin($angle) * $distY;
            $pr = rand((int)($baseRadius * 0.15), (int)($baseRadius * 0.45));
            $negativePlates[] = ['x' => $px, 'y' => $py, 'r' => $pr];
        }

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $isLand = false;
                foreach ($plates as $p) {
                    $dist = hexDistance($x, $y, $p['x'], $p['y']);
                    if ($dist <= $p['r'] + rand(-3, 3)) {
                        $isLand = true;
                        break;
                    }
                }
                if ($isLand) {
                    foreach ($negativePlates as $np) {
                        if (hexDistance($x, $y, $np['x'], $np['y']) <= $np['r'] + rand(-2, 1)) {
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

        viz($tiles, "Raising Tectonic Plates...");

        for ($iter = 0; $iter < 5; $iter++) {
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
                    if ($tiles[$y][$x] === 'water' && $grassCount >= 4) $newTiles[$y][$x] = 'grass';
                    if ($tiles[$y][$x] === 'grass' && $waterCount >= 4) $newTiles[$y][$x] = 'water';
                    // Introduce organic noise on edges to break circular/blocky shapes
                    if ($tiles[$y][$x] === 'grass' && $waterCount === 3 && rand(1, 100) <= 20) $newTiles[$y][$x] = 'water';
                    if ($tiles[$y][$x] === 'water' && $grassCount === 3 && rand(1, 100) <= 20) $newTiles[$y][$x] = 'grass';
                }
            }
            $tiles = $newTiles;
            viz($tiles, "Erosion and Smoothing (Pass " . ($iter + 1) . "/5)...");
        }
        
        viz($tiles, "Sinking Disconnected Islands...");

        $visited = [];
        $landmasses = [];
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($tiles[$y][$x] === 'grass' && !isset($visited["{$x}_{$y}"])) {
                    $mass = [];
                    $q = [[$x, $y]];
                    $visited["{$x}_{$y}"] = true;
                    
                    while (!empty($q)) {
                        $curr = array_shift($q);
                        $mass[] = $curr;
                        
                        foreach (getNeighbors($curr[0], $curr[1], $width, $height) as $n) {
                            $nx = $n[0]; $ny = $n[1];
                            if ($tiles[$ny][$nx] === 'grass' && !isset($visited["{$nx}_{$ny}"])) {
                                $visited["{$nx}_{$ny}"] = true;
                                $q[] = [$nx, $ny];
                            }
                        }
                    }
                    $landmasses[] = $mass;
                }
            }
        }
        
        $largestIndex = 0;
        $maxSize = 0;
        foreach ($landmasses as $idx => $mass) {
            if (count($mass) > $maxSize) {
                $maxSize = count($mass);
                $largestIndex = $idx;
            }
        }
        
        foreach ($landmasses as $idx => $mass) {
            if ($idx !== $largestIndex) {
                foreach ($mass as $cell) {
                    $tiles[$cell[1]][$cell[0]] = 'water';
                }
            }
        }

        viz($tiles, "Identifying Ocean...");

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

        viz($tiles, "Adding Vegetation...");

        $area = $width * $height;
        growBiome($tiles, $width, $height, 'mountain', max(3, $area / 400), 10, 30, ['grass']);
        growBiome($tiles, $width, $height, 'forest', max(6, $area / 200), 15, 45, ['grass', 'grass2']);

        viz($tiles, "Growing Biomes...");

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

        $validCityTiles = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (in_array($tiles[$y][$x], ['grass', 'forest', 'hills'])) {
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
            $bestCenter = ['x' => $cx, 'y' => $cy];
            $tiles[$cy][$cx] = 'city_capital';
            $capitals[] = $bestCenter;
        } 

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

        $placedVillages = [];
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

        $allCities = array_merge($capitals, $placedVillages);
        foreach ($allCities as $c) {
            foreach (getNeighbors($c['x'], $c['y'], $width, $height) as $n) {
                $nx = $n[0]; $ny = $n[1];
                if (in_array($tiles[$ny][$nx], ['grass', 'grass2', 'forest', 'hills'])) {
                    if (rand(1, 100) <= 80) $tiles[$ny][$nx] = 'farmlands';
                }
            }
        }
        
        viz($tiles, "Establishing Civilizations...");
    }

    // 5. Zapis do bazy
    viz($tiles, "Saving to Database (This may take a moment)...");
    
    $batchSize = 2000;
    $count = 0;
    $batch = [];
    
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $batch[] = "({$worldId}, {$x}, {$y}, '" . addslashes($tiles[$y][$x]) . "')";
            $count++;
            
            if ($count % $batchSize === 0) {
                $sql = "INSERT INTO map_tiles (world_id, x, y, type) VALUES " . implode(',', $batch);
                $pdo->exec($sql);
                $batch = [];
            }
        }
    }
    
    if (!empty($batch)) {
        $sql = "INSERT INTO map_tiles (world_id, x, y, type) VALUES " . implode(',', $batch);
        $pdo->exec($sql);
    }

    echo "<script>document.getElementById('vizStatus').innerText = 'Generation Complete!';</script>";
    echo "<h1 style='color:green'>SUCCESS! Added world '$worldName'.</h1>";
    echo "<a href='index.php' style='font-size:20px; font-weight:bold; padding:10px; background:#333; color:white; text-decoration:none;'>RETURN TO GAME</a>";

} catch (PDOException $e) {
    die("SQL Error: " . $e->getMessage());
}
?>