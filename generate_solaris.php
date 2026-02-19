<?php
set_time_limit(0); // Disable execution time limit for large generation
require 'db.php';

try {
    echo "<body style='background:#121212; color:#e0e0e0; font-family:sans-serif; text-align:center; padding-top:50px;'>";
    echo "<h2>Generating Solaris (500 x 1000)...</h2>";

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
            }
        }
    }

    $width = 500;
    $height = 1000;
    $worldId = 3; // Solaris world_id

    // Create world entry for Solaris if not exists
    $pdo->prepare("INSERT INTO worlds (id, name, width, height, is_tutorial) VALUES (?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE name = VALUES(name), width = VALUES(width), height = VALUES(height), is_tutorial = VALUES(is_tutorial)")
        ->execute([$worldId, 'Solaris', $width, $height]);

    // Clear existing Solaris tiles
    $pdo->exec("DELETE FROM tiles_solaris");

    // Generate tiles in memory
    $tiles = [];
    for ($y = 0; $y < $height; $y++) {
        $row = [];
        for ($x = 0; $x < $width; $x++) {
            $row[] = 'grass';
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

    echo "Phase 1: Placing mountains...<br>";

    // Mountain clusters
    $mountainSeeds = max(4, (int)round(($width * $height) / 440));
    for ($i = 0; $i < $mountainSeeds; $i++) {
        $mx = rand(6, max(6, $width - 7));
        $my = rand(6, max(6, $height - 7));
        if (distanceToEdge($mx, $my, $width, $height) < 6) continue;
        if ($tiles[$my][$mx] === 'water') continue;
        growCluster($tiles, $width, $height, $mx, $my, 'mountain', rand(10, 26), ['water']);
    }

    echo "Phase 2: Placing lakes and rivers...<br>";

    // Mini lakes
    $lakeSeeds = max(3, (int)round(($width * $height) / 820));
    for ($i = 0; $i < $lakeSeeds; $i++) {
        $lx = rand(2, max(2, $width - 3));
        $ly = rand(2, max(2, $height - 3));
        if ($tiles[$ly][$lx] === 'mountain') continue;
        growCluster($tiles, $width, $height, $lx, $ly, 'water', rand(7, 18), ['mountain']);
    }

    // Rivers
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

    echo "Phase 3: Placing forests...<br>";

    // Forest clusters
    $forestSeeds = max(6, (int)round(($width * $height) / 260));
    for ($i = 0; $i < $forestSeeds; $i++) {
        $fx = rand(1, max(1, $width - 2));
        $fy = rand(1, max(1, $height - 2));
        if ($tiles[$fy][$fx] === 'water' || $tiles[$fy][$fx] === 'mountain') continue;
        growCluster($tiles, $width, $height, $fx, $fy, 'forest', rand(16, 40), ['water', 'mountain']);
    }

    // Grass2 around forest
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

    // Grass2 clusters
    $grass2Seeds = max(6, (int)round(($width * $height) / 240));
    for ($i = 0; $i < $grass2Seeds; $i++) {
        $gx = rand(1, max(1, $width - 2));
        $gy = rand(1, max(1, $height - 2));
        if ($tiles[$gy][$gx] === 'water' || $tiles[$gy][$gx] === 'mountain') continue;
        if ($tiles[$gy][$gx] === 'forest') continue;
        growCluster($tiles, $width, $height, $gx, $gy, 'grass2', rand(12, 32), ['water', 'mountain', 'forest']);
    }

    // Mix grass/grass2
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

    echo "Phase 4: Placing cities and villages...<br>";

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
        if (in_array($tiles[$vy][$vx], ['water', 'mountain'], true)) continue;
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
                $sql = "INSERT INTO tiles_solaris (x, y, type) VALUES " . implode(',', $batch);
                $pdo->exec($sql);
                $batch = [];
                echo "Inserted $count tiles...<br>";
            }
        }
    }
    
    // Insert remaining tiles
    if (!empty($batch)) {
        $sql = "INSERT INTO tiles_solaris (x, y, type) VALUES " . implode(',', $batch);
        $pdo->exec($sql);
        echo "Inserted remaining tiles...<br>";
    }

    echo "<h1 style='color:green'>✅ Solaris generated successfully!</h1>";
    echo "<p>Generated " . ($width * $height) . " tiles into tiles_solaris table</p>";
    echo "<p>Size: {$width}x{$height}</p>";
    echo "<p>Capital at (" . $centerSpawn[0] . ", " . $centerSpawn[1] . ")</p>";
    echo "<a href='index.php' style='font-size:20px; font-weight:bold; padding:10px; background:#333; color:white; text-decoration:none;'>RETURN TO GAME</a>";

} catch (PDOException $e) {
    die("SQL Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
