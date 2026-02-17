<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credits - HexRealms</title>
    <style>
        :root {
            --bg-dark: #1b1410;
            --bg-mid: #2a1e14;
            --bg-light: #3d2f1f;
            --gold: #d4af37;
            --sand: #f4d58d;
            --brown: #5c4a35;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Georgia", "Times New Roman", serif;
            color: var(--sand);
            background: radial-gradient(circle at top, #3a2b1c 0%, #20160f 55%, #150f0a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }

        .panel {
            width: min(820px, 95vw);
            background: linear-gradient(180deg, rgba(30,20,15,0.98), rgba(20,15,10,0.98));
            border: 2px solid var(--brown);
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            padding: 28px 30px;
        }

        h1 {
            margin: 0 0 10px;
            color: var(--gold);
            letter-spacing: 1px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.7);
        }

        .subtitle {
            margin: 0 0 20px;
            color: #c9a875;
            font-size: 14px;
        }

        .credit-group {
            margin: 18px 0;
            padding: 14px 16px;
            background: rgba(20,15,10,0.6);
            border: 1px solid var(--brown);
            border-radius: 4px;
        }

        .credit-group h2 {
            margin: 0 0 8px;
            color: var(--gold);
            font-size: 18px;
        }

        ul {
            margin: 0;
            padding-left: 18px;
            line-height: 1.6;
            font-size: 14px;
        }

        a {
            color: #ffd27d;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 18px;
            border: 2px solid #61491f;
            background: linear-gradient(135deg, rgba(58,42,26,0.95), rgba(45,31,18,0.95));
            color: #f4d58d;
            text-decoration: none;
            border-radius: 3px;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
        }

        .btn:hover {
            filter: brightness(1.07);
        }

        @media (max-width: 600px) {
            body {
                padding: 16px;
            }

            .panel {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Credits</h1>
        <p class="subtitle">List of assets and authors used in the game.</p>

        <div class="credit-group">
            <h2>Graphics</h2>
            <ul>
                <li>Nature Landscapes Free Pixel Art - Free Game Assets (GUI, Sprite, Tilesets) - License: CraftPix File License - <a href="https://free-game-assets.itch.io/nature-landscapes-free-pixel-art" target="_blank" rel="noopener">pack page</a> / <a href="https://craftpix.net/file-licenses/" target="_blank" rel="noopener">license</a></li>
                <li>Fantasy Hex Tiles - The Clover Patch (CuddlyClover) - License: see pack page (explicit license; attribution requested) - <a href="https://cuddlyclover.itch.io/fantasy-hex-tiles" target="_blank" rel="noopener">pack page</a></li>
                <li>Tiny Swords - Pixel Frog - License: free for personal/commercial use, credit not required, no redistribution/resale - <a href="https://pixelfrog-assets.itch.io/tiny-swords" target="_blank" rel="noopener">pack page</a></li>
            </ul>
        </div>

        <div class="credit-group">
            <h2>Audio</h2>
            <ul>
                <li>Super Dialogue Audio Pack - Dillon Becker - License: CC BY 4.0 - <a href="https://dillonbecker.itch.io/sdap" target="_blank" rel="noopener">pack page</a> / <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">license</a></li>
                <li>High Quality 16-bit RPG Music - HydroGene - License: CC0 - <a href="https://hydrogene.itch.io/high-quality-16-bit-music" target="_blank" rel="noopener">pack page</a> / <a href="https://creativecommons.org/publicdomain/zero/1.0/" target="_blank" rel="noopener">license</a></li>
            </ul>
        </div>

        <div class="credit-group">
            <h2>Other</h2>
            <ul>
                <li>Ruler (pixel font) - somepx (Ivano Palmentieri) - License: free for personal/commercial use with attribution; no redistribution/resale/sublicense; other restrictions apply - <a href="https://somepx.itch.io/free-font-ruler" target="_blank" rel="noopener">pack page</a></li>
            </ul>
        </div>

        <div class="actions">
            <a class="btn" href="index.php">Back to game</a>
        </div>
    </div>
</body>
</html>
