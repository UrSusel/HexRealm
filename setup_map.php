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
$prefixes = ['Kingdom of', 'Duchy of', 'Barony of', 'Principality of', 'Realm of', 'Shire of', 'County of'];
$names = ['Aldor', 'Briar', 'Carth', 'Dunhelm', 'Eldwyn', 'Fallow', 'Gareth', 'Haven', 'Iver', 'Keld', 'Lorien', 'Mire', 'Norwick', 'Oakmoor'];

$scale = isset($_POST['size_scale']) ? (int)$_POST['size_scale'] : 0;
$width = 25 + (int)(75 * ($scale / 100));
$height = 50 + (int)(150 * ($scale / 100));

$worldName = $prefixes[array_rand($prefixes)] . ' ' . $names[array_rand($names)];

echo "<body style='background:#121212; color:#e0e0e0; font-family:sans-serif; text-align:center; padding-top:50px;'>";
echo "<h2>Creating world: $worldName ($width x $height)...</h2>";

try {
    // 1. Dodajemy wpis do tabeli worlds (is_tutorial = 0)
    $stmt = $pdo->prepare("INSERT INTO worlds (name, width, height, is_tutorial) VALUES (?, ?, ?, 0)");
    $stmt->execute([$worldName, $width, $height]);
    $worldId = $pdo->lastInsertId();

    echo "ID nowego świata: $worldId<br>";

    // 2. Generujemy kafelki dla tego konkretnego ID
    $stmt = $pdo->prepare("INSERT INTO map_tiles (world_id, x, y, type) VALUES (?, ?, ?, ?)");
    $count = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            
            $type = (rand(0, 1) == 0) ? 'grass' : 'grass2';
            $rand = rand(1, 100);

            if ($rand > 65) $type = 'forest';
            if ($rand > 85) $type = 'mountain';
            if ($rand > 93) $type = 'water';
            
            // Punkt startowy (spawn) w nowym świecie
            if ($x == 0 && $y == 0) $type = 'city_capital';
            
            $stmt->execute([$worldId, $x, $y, $type]);
            $count++;
        }
    }

    // 3. Dodawanie wiosek
    $villagesToPlace = rand(3, 6);
    $vCount = 0;
    $updateStmt = $pdo->prepare("UPDATE map_tiles SET type = 'city_village' WHERE world_id = ? AND x = ? AND y = ?");

    while ($vCount < $villagesToPlace) {
        $vx = rand(2, max(2, $width - 1));
        $vy = rand(2, max(2, $height - 1));
        $updateStmt->execute([$worldId, $vx, $vy]);
        $vCount++;
    }

    echo "<h1 style='color:green'>SUCCESS! Added world '$worldName'.</h1>";
    echo "<a href='index.php' style='font-size:20px; font-weight:bold; padding:10px; background:#333; color:white; text-decoration:none;'>RETURN TO GAME</a>";

} catch (PDOException $e) {
    die("SQL Error: " . $e->getMessage());
}
?>