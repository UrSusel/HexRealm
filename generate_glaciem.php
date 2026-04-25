<?php
set_time_limit(0); // Disable execution time limit for large generation
require 'db.php';

try {
    echo "<body style='background:#121212; color:#e0e0e0; font-family:sans-serif; text-align:center; padding-top:50px;'>";
    echo "<h2>Generating Glaciem (500 x 1000)...</h2>";

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

    function distanceToEdge($x, $y, $width, $height) {
        return min($x, $y, $width - 1 - $x, $height - 1 - $y);
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

    function pickCenterSpawn($width, $height, $tiles) {
        $cx = (int)floor($width / 2);
        $cy = (int)floor($height / 2);
        if ($tiles[$cy][$cx] !== 'wwater' && $tiles[$cy][$cx] !== 'wmountain') {
            return [$cx, $cy];
        }
        for ($r = 1; $r < max($width, $height); $r++) {
            for ($y = max(0, $cy - $r); $y <= min($height - 1, $cy + $r); $y++) {
                for ($x = max(0, $cx - $r); $x <= min($width - 1, $cx + $r); $x++) {
                    if (abs($x - $cx) !== $r && abs($y - $cy) !== $r) continue;
                    if ($tiles[$y][$x] !== 'wwater' && $tiles[$y][$x] !== 'wmountain') {
                        return [$x, $y];
                    }
                }
            }
        }
        return [$cx, $cy];
    }

    function applyFarmlandsRadius(&$tiles, $width, $height, $cx, $cy, $radius) {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $d = hexDistance($x, $y, $cx, $cy);
                if ($d > $radius) continue;
                $t = $tiles[$y][$x];
                if (!in_array($t, ['wgrass', 'wgrass2', 'wforest', 'whills', 'whills2'], true)) continue;
                $chance = 0;
                if ($d <= 1) $chance = 90;
                elseif ($d <= 2) $chance = 70;
                else $chance = 45;
                if (rand(1, 100) <= $chance) {
                    $tiles[$y][$x] = 'wfarmlands';
                }
            }
        }
    }

    function applyHills(&$tiles, $width, $height) {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($tiles[$y][$x] !== 'wmountain') continue;
                foreach (getNeighbors($x, $y, $width, $height) as $n) {
                    $nx = $n[0]; $ny = $n[1];
                    $t = $tiles[$ny][$nx];
                    if (in_array($t, ['wgrass', 'wgrass2', 'wforest'], true)) {
                        if (rand(1, 100) <= 80) {
                            $tiles[$ny][$nx] = (rand(0, 1) === 0) ? 'whills' : 'whills2';
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
                    if (in_array($t, ['wgrass', 'wgrass2', 'wforest', 'whills', 'whills2'], true)) {
                        if (rand(1, 100) <= 60) {
                            $tiles[$ny][$nx] = 'wfarmlands';
                        }
                    }
                }
            }
        }
    }

    $width = 500;
    $height = 1000;
    $worldId = 5; // Glaciem world_id

    // Create world entry for Glaciem if not exists
    $pdo->prepare("INSERT INTO worlds (id, name, width, height, is_tutorial) VALUES (?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE name = VALUES(name), width = VALUES(width), height = VALUES(height), is_tutorial = VALUES(is_tutorial)")
        ->execute([$worldId, 'Glaciem', $width, $height]);

    // Clear existing Glaciem tiles
    $pdo->exec("DELETE FROM tiles_glaciem");

    // Generate tiles in memory
    $tiles = [];
    for ($y = 0; $y < $height; $y++) {
        $row = [];
        for ($x = 0; $x < $width; $x++) {
            $row[] = 'wgrass';
        }
        $tiles[] = $row;
    }

    // Water on edges
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $d = distanceToEdge($x, $y, $width, $height);
            $topBand = ($y <= 2);
            $bottomBand = ($y >= $height - 3);
            if ($topBand || $bottomBand) {
                $tiles[$y][$x] = 'wwater';
                continue;
            }
            if ($d === 0) {
                $tiles[$y][$x] = 'wwater';
            } elseif ($d === 1 && rand(1, 100) <= 65) {
                $tiles[$y][$x] = 'wwater';
            } elseif ($d === 2 && rand(1, 100) <= 35) {
                $tiles[$y][$x] = 'wwater';
            }
            if ($d === 3 && rand(1, 100) <= 25) {
                $tiles[$y][$x] = 'wwater';
            }
        }
    }

    echo "Phase 1: Placing icy mountain peaks...<br>";

    // Mountain clusters (more frequent, larger for an ice planet)
    $mountainSeeds = max(6, (int)round(($width * $height) / 300));
    for ($i = 0; $i < $mountainSeeds; $i++) {
        $mx = rand(6, max(6, $width - 7));
        $my = rand(6, max(6, $height - 7));
        if (distanceToEdge($mx, $my, $width, $height) < 6) continue;
        if ($tiles[$my][$mx] === 'wwater') continue;
        growCluster($tiles, $width, $height, $mx, $my, 'wmountain', rand(15, 35), ['wwater']);
    }

    echo "Phase 2: Placing frozen lakes and ice rivers...<br>";

    // Mini lakes (frozen lakes, more frequent)
    $lakeSeeds = max(5, (int)round(($width * $height) / 500));
    for ($i = 0; $i < $lakeSeeds; $i++) {
        $lx = rand(2, max(2, $width - 3));
        $ly = rand(2, max(2, $height - 3));
        if ($tiles[$ly][$lx] === 'wmountain') continue;
        growCluster($tiles, $width, $height, $lx, $ly, 'wwater', rand(15, 35), ['wmountain']);
    }

    // Rivers
    $riverSeeds = max(4, (int)round(($width * $height) / 700));
    for ($i = 0; $i < $riverSeeds; $i++) {
        $rx = rand(1, max(1, $width - 2));
        $ry = rand(1, max(1, $height - 2));
        if ($tiles[$ry][$rx] === 'wmountain') continue;
        $len = rand(12, 28);
        $x = $rx; $y = $ry;
        for ($s = 0; $s < $len; $s++) {
            if ($tiles[$y][$x] !== 'wmountain') {
                $tiles[$y][$x] = 'wwater';
            }
            $neighbors = getNeighbors($x, $y, $width, $height);
            if (!$neighbors) break;
            $pick = $neighbors[array_rand($neighbors)];
            $x = $pick[0]; $y = $pick[1];
            $x = max(1, min($width - 2, $x));
            $y = max(1, min($height - 2, $y));
        }
    }

    echo "Phase 3: Placing sparse frozen forests and deep snow...<br>";

    // Forest clusters (sparser, smaller)
    $forestSeeds = max(4, (int)round(($width * $height) / 500));
    for ($i = 0; $i < $forestSeeds; $i++) {
        $fx = rand(1, max(1, $width - 2));
        $fy = rand(1, max(1, $height - 2));
        if ($tiles[$fy][$fx] === 'wwater' || $tiles[$fy][$fx] === 'wmountain') continue;
        growCluster($tiles, $width, $height, $fx, $fy, 'wforest', rand(10, 25), ['wwater', 'wmountain']);
    }

    // Grass2 around forest
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ($tiles[$y][$x] !== 'wforest') continue;
            foreach (getNeighbors($x, $y, $width, $height) as $n) {
                $nx = $n[0]; $ny = $n[1];
                if ($tiles[$ny][$nx] === 'wgrass' && rand(1, 100) <= 55) {
                    $tiles[$ny][$nx] = 'wgrass2';
                }
            }
        }
    }

    // Grass2 clusters (Huge deep snow plains)
    $grass2Seeds = max(8, (int)round(($width * $height) / 180));
    for ($i = 0; $i < $grass2Seeds; $i++) {
        $gx = rand(1, max(1, $width - 2));
        $gy = rand(1, max(1, $height - 2));
        if ($tiles[$gy][$gx] === 'wwater' || $tiles[$gy][$gx] === 'wmountain') continue;
        if ($tiles[$gy][$gx] === 'wforest') continue;
        growCluster($tiles, $width, $height, $gx, $gy, 'wgrass2', rand(20, 45), ['wwater', 'wmountain', 'wforest']);
    }

    // Mix grass/grass2
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $t = $tiles[$y][$x];
            if ($t === 'wgrass' && rand(1, 100) <= 25) {
                $tiles[$y][$x] = 'wgrass2';
            } elseif ($t === 'wgrass2' && rand(1, 100) <= 2) {
                $tiles[$y][$x] = 'wgrass';
            }
        }
    }

    echo "Phase 4: Placing winter settlements...<br>";

    // Capital at center
    $centerSpawn = pickCenterSpawn($width, $height, $tiles);
    $tiles[$centerSpawn[1]][$centerSpawn[0]] = 'city_capital';

    // Villages near capital
    $nearbyVillages = rand(2, 4);
    $placedNear = 0;
    $nearAttempts = $nearbyVillages * 20;
    $nearCoords = [];
    while ($placedNear < $nearbyVillages && $nearAttempts-- > 0) {
        $vx = rand(max(1, $centerSpawn[0] - 3), min($width - 2, $centerSpawn[0] + 3));
        $vy = rand(max(1, $centerSpawn[1] - 3), min($height - 2, $centerSpawn[1] + 3));
        $dist = hexDistance($vx, $vy, $centerSpawn[0], $centerSpawn[1]);
        if ($dist < 2 || $dist > 3) continue;
        if (in_array($tiles[$vy][$vx], ['wwater', 'wmountain', 'city_capital', 'city_village'], true)) continue;
        $tooClose = false;
        foreach ($nearCoords as $c) {
            if (hexDistance($vx, $vy, $c[0], $c[1]) < 2) { $tooClose = true; break; }
        }
        if ($tooClose) continue;
        $tiles[$vy][$vx] = 'city_village';
        $nearCoords[] = [$vx, $vy];
        $placedNear++;
    }

    // Farmlands around capital
    applyFarmlandsRadius($tiles, $width, $height, $centerSpawn[0], $centerSpawn[1], 3);
    foreach ($nearCoords as $c) {
        applyFarmlandsRadius($tiles, $width, $height, $c[0], $c[1], 2);
    }

    // Scattered villages
    $area = $width * $height;
    $scaleFactor = min(1, $area / (100 * 200));
    $villagesBase = 3 + (int)round(8 * $scaleFactor);
    $villagesToPlace = max(3, min(30, $villagesBase + rand(0, 2)));
    $vCount = 0;
    $maxAttempts = $villagesToPlace * 20;
    while ($vCount < $villagesToPlace) {
        if ($maxAttempts-- <= 0) break;
        $vx = rand(1, max(1, $width - 2));
        $vy = rand(1, max(1, $height - 2));
        if ($tiles[$vy][$vx] === 'city_capital') continue;
        if (in_array($tiles[$vy][$vx], ['wwater', 'wmountain'], true)) continue;
        if (abs($vx - $centerSpawn[0]) + abs($vy - $centerSpawn[1]) < 6) continue;
        $tiles[$vy][$vx] = 'city_village';
        $vCount++;
    }

    echo "Phase 5: Finalizing...<br>";

    // Hills and farmlands
    applyHills($tiles, $width, $height);
    applyFarmlands($tiles, $width, $height);

    echo "Phase 6: Inserting into database...<br>";

    // Batch insert tiles (1000 at a time for performance)
    $batchSize = 1000;
    $count = 0;
    $batch = [];
    
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $batch[] = "({$x}, {$y}, '" . addslashes($tiles[$y][$x]) . "')";
            $count++;
            
            // Every 1000 tiles, execute batch insert
            if ($count % $batchSize === 0) {
                $sql = "INSERT INTO tiles_glaciem (x, y, type) VALUES " . implode(',', $batch);
                $pdo->exec($sql);
                $batch = [];
                echo "Inserted $count tiles...<br>";
            }
        }
    }
    
    // Insert remaining tiles
    if (!empty($batch)) {
        $sql = "INSERT INTO tiles_glaciem (x, y, type) VALUES " . implode(',', $batch);
        $pdo->exec($sql);
        echo "Inserted remaining tiles...<br>";
    }

    echo "<h1 style='color:green'>✅ Glaciem generated successfully!</h1>";
    echo "<p>Generated " . ($width * $height) . " tiles into tiles_glaciem table</p>";
    echo "<p>Size: {$width}x{$height}</p>";
    echo "<p>Capital at (" . $centerSpawn[0] . ", " . $centerSpawn[1] . ")</p>";
    echo "<a href='index.php' style='font-size:20px; font-weight:bold; padding:10px; background:#333; color:white; text-decoration:none;'>RETURN TO GAME</a>";

} catch (PDOException $e) {
    die("SQL Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
