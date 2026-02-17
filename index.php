<?php
$assetVersion = @filemtime(__DIR__ . '/game.js') ?: time();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HexRealm</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <style>
        /* --- OGÓLNE --- */
        @font-face {
            font-family: 'Ruler9';
            src: url('assets/ui/Ruler 9.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        body { 
            background-color: #121212; 
            color: #e0e0e0; 
            font-family: 'Ruler9', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; padding: 0; 
            overflow: hidden; 
            height: 100vh; 
            cursor: url('assets/ui/Cursor_01.png') 20 18, auto;
        }
        button, input, select, textarea {
            font-family: 'Ruler9', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        button, .tile, .class-card, .tab-btn, .item-slot, input, a, .pointer-cursor, .world-item {
            cursor: url('assets/ui/Cursor_02.png') 20 18, pointer !important;
        }
        #game-layout { display: flex; height: 100vh; }
        
        /* --- TŁO --- */
        #left-panel { 
            flex: 1; overflow: hidden; position: relative; 
            display: flex; align-items: center; justify-content: center; 
            background-color: #050011; 
            background-image: url('img/Starry background  - Layer 02 - Stars.png'), url('img/Starry background  - Layer 01 - Void.png');
            background-repeat: repeat-x; background-size: auto 100%; 
            animation: spaceScroll 60s linear infinite;
            z-index: 0;
        }
        @keyframes spaceScroll { from { background-position: 0 0, 0 0; } to { background-position: -2000px 0, -500px 0; } }
        
        #map { position: absolute; width: 4000px; height: 4000px; background: transparent; top: 0; left: 0; transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1); transform-origin: 0 0; }
        #wind-layer {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 1100;
        }
        .wind-streak {
            position: absolute;
            background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.55), rgba(255,255,255,0));
            opacity: 0.35;
            filter: blur(0.3px);
            animation: windMove var(--dur, 2.6s) linear forwards;
            transform: translate(0, 0) rotate(var(--rot, 0deg));
        }
        @keyframes windMove {
            from { transform: translate(0, 0) rotate(var(--rot, 0deg)); opacity: 0.0; }
            10% { opacity: 0.35; }
            90% { opacity: 0.25; }
            to { transform: translate(var(--dx, 200px), var(--dy, 0px)) rotate(var(--rot, 0deg)); opacity: 0.0; }
        }

        /* --- KAFELKI --- */
        .tile { 
            width: 128px; height: 128px; position: absolute; 
            background-size: 100% 100%; background-repeat: no-repeat;
            image-rendering: pixelated; cursor: pointer;
            filter: drop-shadow(0px 5px 5px rgba(0,0,0,0.5));
        }
        .tile.grass { background-image: url('img/grass.png'); }
        .tile.grass2 { background-image: url('img/grass2.png'); }
        .tile.forest { background-image: url('img/forest.png'); }
        .tile.mountain {
            background-image: none;
            overflow: visible;
        }
        .tile.water { background-image: url('img/water.png'); }
        .tile.hills { background-image: url('img/hills.png'); }
        .tile.hills2 { background-image: url('img/hills2.png'); }
        .tile.farmlands { background-image: url('img/farmlands.png'); }
        .tile.hills,
        .tile.hills2,
        .tile.farmlands {
            background-size: 80% 80%;
            background-position: center;
        }
        .tile.hills {
            background-size: 84% 84%;
            background-position: center calc(50% - 6px);
        }
        .tile.hills2 {
            background-image: none;
            overflow: visible;
        }
        .tile.hills2::after {
            content: '';
            position: absolute;
            left: 0;
            top: -45px;
            width: 128px;
            height: 160px;
            background-image: url('img/hills2.png');
            background-repeat: no-repeat;
            background-size: 84% 100%;
            background-position: center top;
            pointer-events: none;
        }
        .tile.mountain::after {
            content: '';
            position: absolute;
            left: 0;
            top: -8px;
            width: 128px;
            height: 140px;
            background-image: url('img/mountain.png');
            background-repeat: no-repeat;
            background-size: 100% 100%;
            background-position: center top;
            pointer-events: none;
        }
        .tile.city_capital { background-image: url('img/castle.png'); }
        .tile.city_village { background-image: url('img/vilage.png'); }
        .tile:hover { filter: brightness(1.3) drop-shadow(0 0 10px white); z-index: 1200 !important; }
        body.combat-active .tile:hover { z-index: 1200 !important; }
        body.combat-active .player,
        body.combat-active .player.enemy,
        body.combat-active .player.enemy:hover { z-index: 1400 !important; }

        /* --- NOCNE OŚWIETLENIE --- */
        .night-mode .tile.city_capital, 
        .night-mode .tile.city_village {
            filter: brightness(1.8) drop-shadow(0 0 20px rgba(255, 220, 100, 0.9));
        }
        .night-mode .player.in-light {
            filter: brightness(1.3) drop-shadow(0 0 10px rgba(255, 255, 255, 0.5));
        }

        /* --- SMOKE EFFECT --- */
        .smoke-particle {
            position: absolute; width: 12px; height: 12px;
            background: rgba(255, 255, 255, 0.75); border-radius: 50%;
            pointer-events: none;
            animation: smokeAnim 3.2s infinite linear;
        }
        @keyframes smokeAnim {
            0% { transform: translate(0, 0) scale(0.9); opacity: 0.6; }
            100% { transform: translate(var(--smoke-drift, 12px), -55px) scale(3.2); opacity: 0; }
        }

        /* --- GRACZ/WRÓG --- */
        .player { 
            width: 128px; height: 128px; 
            background-repeat: no-repeat; background-position: center bottom; background-size: contain;
            image-rendering: pixelated; position: absolute; z-index: 1300; 
            pointer-events: none; 
            filter: drop-shadow(0px 5px 5px rgba(0,0,0,0.5));
        }
        .player.enemy {
            pointer-events: auto !important; cursor: url('assets/ui/Cursor_02.png') 15 15, crosshair !important;
            z-index: 1300 !important;
        }
        .player.other-player {
            pointer-events: auto !important; cursor: url('assets/ui/Cursor_02.png') 15 15, pointer !important;
        }
        .player.other-player.safe {
            pointer-events: none !important;
        }
        .player.enemy:hover {
            transform: scaleX(-1) scale(1.1); cursor: url('assets/ui/sword.png') 0 0, crosshair !important;
            filter: drop-shadow(0 0 10px red) hue-rotate(150deg) brightness(0.8);
            z-index: 1300 !important;
        }

        /* --- UI PANEL --- */
        #right-panel { 
            width: 350px; 
            background: linear-gradient(180deg, #2a1e14 0%, #1f1610 100%);
            background-image: 
                repeating-linear-gradient(90deg, rgba(0,0,0,0.1) 0px, transparent 1px, transparent 3px, rgba(0,0,0,0.1) 4px),
                repeating-linear-gradient(0deg, rgba(0,0,0,0.05) 0px, transparent 1px, transparent 2px);
            border-left: 3px solid #4a3826;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.5), -5px 0 15px rgba(0,0,0,0.4);
            display: flex; flex-direction: column; z-index: 200; 
            flex-shrink: 0;
            transition: margin-right 0.4s ease-in-out, transform 0.4s ease-in-out;
            image-rendering: pixelated;
        }
        .panel-collapsed #right-panel {
            margin-right: -350px;
        }
        .panel-collapsed #expand-panel-btn {
            display: block;
        }
        .tabs { 
            display: flex; 
            background: linear-gradient(180deg, #3d2f1f 0%, #2a1e14 100%);
            border-bottom: 2px solid #61491f;
            box-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }
        .tab-btn { 
            background: linear-gradient(180deg, rgba(139,115,85,0.75), rgba(101,73,31,0.65));
            color: #c9a875; 
            border: none;
            border-right: 1px solid #4a3826;
            padding: 15px 8px; 
            cursor: pointer; 
            flex: 1; 
            font-weight: bold; 
            font-size: 13px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            transition: all 0.2s;
            position: relative;
        }
        .tab-btn:hover {
            background: linear-gradient(180deg, rgba(139,115,85,0.85), rgba(101,73,31,0.75));
            color: #f4d58d;
        }
        .tab-btn.active { 
            color: #ffeaa7; 
            background: linear-gradient(180deg, #61491f, #4a3826);
            border-bottom: 3px solid #d4af37;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.4);
        }
        .class-filter-btn { 
            background: linear-gradient(135deg, rgba(58,42,26,0.92), rgba(45,31,18,0.98));
            color: #c9a875;
            border: 2px solid #5c4a35;
            padding: 8px 15px; 
            cursor: pointer; 
            font-weight: bold;
            border-radius: 3px;
            font-size: 12px;
            opacity: 0.95;
            transition: all 0.2s;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            box-shadow: 0 2px 0 rgba(0,0,0,0.4);
        }
        .class-filter-btn:hover { 
            opacity: 1;
            background: linear-gradient(135deg, rgba(68,52,36,0.8), rgba(55,41,28,0.9));
            color: #f4d58d;
            transform: translateY(-1px);
            box-shadow: 0 3px 0 rgba(0,0,0,0.5);
        }
        .tab-content { 
            display: none; 
            padding: 20px; 
            overflow-y: auto;
            background: linear-gradient(180deg, rgba(42,30,20,0.85), rgba(31,22,16,0.9));
        }
        .tab-content.active { display: block; }
        
        .bar-container { width: 100%; height: 8px; background: #333; border-radius: 4px; overflow: hidden; margin-top: 5px; }
        
        /* Nowy styl paska (3-częściowy) */
        .big-bar-widget {
            position: relative;
            display: grid; grid-template-columns: 11px 1fr 11px;
            height: 24px; width: 100%;
            image-rendering: pixelated;
        }
        /* Warstwa ramki (na wierzchu) */
        .hb-left { width: 100%; height: 100%; background: url('assets/ui/BigBar_left.png') no-repeat center; background-size: 100% 100%; z-index: 10; position: relative; pointer-events: none; }
        .hb-middle { 
            width: 100%; height: 100%; position: relative;
            background: url('assets/ui/BigBar_middle.png') repeat-x center; background-size: 11px 100%; z-index: 1; pointer-events: none;
        }
        .hb-right { width: 100%; height: 100%; background: url('assets/ui/BigBar_right.png') no-repeat center; background-size: 100% 100%; z-index: 10; position: relative; pointer-events: none; }
        
        /* Warstwa wypełnienia (pomiędzy tłem a końcówkami) */
        .hb-fill-wrapper { position: absolute; top: 5px; left: 0; right: 0; bottom: 5px; width: auto; height: auto; z-index: 5; }
        .hb-fill { 
            height: 100%; background:  url('assets/ui/BigBar_Fill.png') repeat-x center; background-size: auto 100%; 
            transition: width 0.2s; max-width: 100%;
        }
        .hb-fill.energy { filter: hue-rotate(210deg) brightness(1.2); }
        .hb-fill.xp { filter: hue-rotate(100deg) brightness(1.1); }
        
        .bar-fill { height: 100%; width: 50%; transition: width 0.5s; }
        .hp-bar { background: #d32f2f; }
        .en-bar { background: #2196f3; } .xp-bar { background: #00e676; }
        
        .item-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .item-slot { 
            background: linear-gradient(135deg, #352316 0%, #281b0f 50%, #352316 100%);
            border: 2px solid #5c4a35;
            border-radius: 3px;
            height: 60px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center;
            position: relative; 
            transition: all 0.2s;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5), 0 2px 4px rgba(0,0,0,0.3);
            image-rendering: pixelated;
        }
        .item-slot:hover { 
            border-color: #8b7355; 
            background: linear-gradient(135deg, #4a3a2a 0%, #3d2f22 50%, #4a3a2a 100%);
            transform: translateY(-2px);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5), 0 4px 8px rgba(0,0,0,0.4);
        }
        .item-slot.equipped { 
            border-color: #d4af37;
            box-shadow: 0 0 8px #d4af37, inset 0 0 10px rgba(212,175,55,0.3);
        }
        
        /* Ikony przycisków */
        .icon-btn { background: none; border: none; cursor: pointer; padding: 5px; transition: transform 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .icon-btn:hover { transform: scale(1.1); }
        .icon-btn img { width: 32px; height: 32px; image-rendering: pixelated; }

        /* Modale */
        .modal { 
            position: fixed; 
            top: 0; left: 0; 
            width: 100%; height: 100%; 
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(3px);
            z-index: 10050; 
            display: none; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            color: #f4d58d;
        }
        #combat-screen.modal { background: transparent !important; background-color: transparent !important; }
        #combat-screen {
            background-color: #050011;
            background-image: url('img/Starry background  - Layer 02 - Stars.png'), url('img/Starry background  - Layer 01 - Void.png');
            background-repeat: repeat-x;
            background-size: auto 100%;
            background-position: 0 0, 0 0;
            animation: spaceScroll 60s linear infinite;
        }
        body.combat-active {
            background-color: #050011;
            background-image: url('img/Starry background  - Layer 02 - Stars.png'), url('img/Starry background  - Layer 01 - Void.png');
            background-repeat: repeat-x;
            background-size: auto 100%;
            background-position: 0 0, 0 0;
            animation: spaceScroll 60s linear infinite;
        }
        body.combat-active .top-left-ui,
        body.combat-active #world-btn,
        body.combat-active #shop-btn,
        body.combat-active #right-panel,
        body.combat-active #expand-panel-btn,
        body.combat-active #mobile-panel-toggle {
            display: none !important;
        }
        body.combat-active #game-layout {
            display: none !important;
        }
        .combat-btn { 
            padding: 12px 30px; 
            background: linear-gradient(180deg, #8b4513 0%, #6b3410 50%, #4a220c 100%);
            color: #ffeaa7; 
            border: 2px solid #5c3a1f;
            border-radius: 2px;
            font-size: 16px; 
            margin: 10px; 
            cursor: pointer;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            box-shadow: 0 3px 0 #3a1f0c, 0 5px 8px rgba(0,0,0,0.4);
            transition: all 0.1s;
            image-rendering: pixelated;
        }
        .combat-btn:hover {
            background: linear-gradient(180deg, #9b5523 0%, #7b4420 50%, #5a321c 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 0 #3a1f0c, 0 6px 10px rgba(0,0,0,0.5);
        }
        .combat-btn:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 #3a1f0c, 0 2px 4px rgba(0,0,0,0.3);
        }

        /* --- COMBAT LAYOUT (BASE) --- */
        #combat-screen {
            padding: 20px;
            gap: 12px;
            align-items: center;
            justify-content: flex-start;
            box-sizing: border-box;
        }
        #combat-screen .combat-header {
            width: min(1100px, 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0 auto;
        }
        #combat-screen .combat-title {
            color: #e53935;
            margin: 0;
            font-size: 20px;
        }
        #combat-screen .combat-hud {
            width: min(1100px, 100%);
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin: 0 auto;
        }
        #combat-screen .combat-bar-row {
            width: 48%;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        #combat-screen .combat-bar-row.enemy-row {
            align-items: flex-end;
            text-align: right;
        }
        #combat-screen .combat-arena-shell {
            width: min(1100px, 100%);
            display: block;
            margin: 0 auto;
        }
        #combat-controls {
            width: min(1100px, 100%);
            display: flex;
            justify-content: center;
            margin: 0 auto;
        }
        #combat-log {
            width: min(1100px, 100%);
            margin: 0 auto;
        }
        
        #start-screen { display: flex; z-index: 2000; background: #000; overflow: hidden; }
        #start-screen h1,
        #start-screen .combat-btn {
            position: relative;
            z-index: 5;
        }
        .start-bg-layer {
            position: absolute;
            inset: 0;
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
        }
        .start-bg-layer.layer-4 { background-image: url('img/4.png'); z-index: 4; }
        .start-bg-layer.layer-3 { background-image: url('img/3.png'); z-index: 3; }
        .start-bg-layer.layer-2 { background-image: url('img/2.png'); z-index: 2; }
        .start-bg-layer.layer-1 { 
            background-image: url('img/1.png');
            z-index: 1;
            background-repeat: repeat-x;
            background-size: 2000px auto;
            animation: startLayer1Drift 90s linear infinite, cloudFloat 8s ease-in-out infinite;
            will-change: background-position, transform;
        }
        @keyframes startLayer1Drift {
            from { background-position: 0 0; }
            to { background-position: -2000px 0; }
        }
        .class-card { 
            padding: 20px;
            border: 3px solid #5c4a35;
            border-radius: 4px;
            cursor: pointer; 
            text-align: center; 
            width: 120px; 
            transition: 0.3s;
            background: linear-gradient(135deg, #352316 0%, #281b0f 100%);
            box-shadow: 0 4px 8px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
            color: #f4d58d;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            image-rendering: pixelated;
        }
        .class-card:hover { 
            border-color: #d4af37;
            background: linear-gradient(135deg, #4a3a2a 0%, #3d2f22 100%);
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.6), 0 0 10px rgba(212,175,55,0.3);
            color: #ffeaa7;
        }
        
        /* Lista Światów */
        .world-item { 
            background: linear-gradient(135deg, #352316 0%, #281b0f 100%);
            border: 2px solid #5c4a35;
            padding: 15px; 
            margin: 5px; 
            width: 300px; 
            text-align: center;
            border-radius: 3px;
            transition: 0.2s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
            color: #f4d58d;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            image-rendering: pixelated;
        }
        .world-item:hover { 
            background: linear-gradient(135deg, #4a3a2a 0%, #3d2f22 100%);
            border-color: #8b7355;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.6);
            color: #ffeaa7;
        }

        /* Przycisk zmiany świata */
        #world-btn {
            position: absolute; top: 10px; right: 20px; 
            padding: 10px 20px;
            background: linear-gradient(180deg, #2d8b57 0%, #1f6b3f 50%, #164d2e 100%);
            color: #e8f5e9;
            font-weight: bold; 
            border: 2px solid #3d6b4a;
            border-radius: 2px;
            cursor: pointer;
            z-index: 1600; 
            display: none;
            box-shadow: 0 3px 0 #0f3d1e, 0 5px 10px rgba(0,230,118,0.3);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            transition: all 0.1s;
            image-rendering: pixelated;
        }
        #world-btn:hover { 
            background: linear-gradient(180deg, #3d9b67 0%, #2f7b4f 50%, #1f5d3e 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 0 #0f3d1e, 0 6px 12px rgba(0,230,118,0.4);
        }

        /* Przycisk zamknięcia (czerwony X, jak w Windows XP) */
        .modal-panel { 
            position: relative;
            background: linear-gradient(145deg, #38291c, #271a11);
            border: 3px solid #61491f;
            box-shadow: 0 10px 30px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.1);
            border-radius: 4px;
            image-rendering: pixelated;
        }
        /* Auth & Character Selection */
        .auth-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: transparent; z-index: 2001; display: none; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; }
        .auth-bg-layer {
            position: absolute;
            inset: 0;
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }
        .auth-bg-layer.layer-4 { background-image: url('img/4.png'); z-index: 4; }
        .auth-bg-layer.layer-3 { background-image: url('img/3.png'); z-index: 3; }
        .auth-bg-layer.layer-2 { background-image: url('img/2.png'); z-index: 2; }
        .auth-bg-layer.layer-1 {
            background-image: url('img/1.png');
            z-index: 1;
            background-repeat: repeat-x;
            background-size: 2000px auto;
            animation: authLayer1Drift 90s linear infinite, cloudFloat 8s ease-in-out infinite;
            will-change: background-position, transform;
        }
        @keyframes authLayer1Drift {
            from { background-position: 0 0; }
            to { background-position: -2000px 0; }
        }
        @keyframes cloudFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .auth-content {
            position: relative;
            z-index: 5;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 20px;
            width: auto;
            max-width: 92vw;
            box-sizing: border-box;
        }
        .auth-logo {
            display: inline-block;
            width: fit-content;
            max-width: 100%;
            text-align: center;
            padding: 12px 20px;
            border-radius: 4px;
            background: linear-gradient(145deg, rgba(61, 47, 31, 0.6), rgba(42, 30, 20, 0.6));
            background-image: repeating-linear-gradient(45deg, transparent, transparent 8px, rgba(0,0,0,0.12) 8px, rgba(0,0,0,0.12) 16px);
            border: 3px solid #61491f;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8), inset 0 1px 0 rgba(255,255,255,0.1), 0 0 20px rgba(212,175,55,0.2);
            animation: authLogoFloat 6s ease-in-out infinite;
            image-rendering: pixelated;
        }
        .auth-logo-main {
            display: block;
            font-size: clamp(36px, 8vw, 96px);
            line-height: 0.95;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ffeaa7;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9), 0 0 20px rgba(212,175,55,0.5);
            animation: authLogoGlow 4s ease-in-out infinite;
        }
        @keyframes authLogoFloat {
            0% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
            100% { transform: translateY(0); }
        }
        @keyframes authLogoGlow {
            0% { text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9), 0 0 16px rgba(212,175,55,0.4); }
            50% { text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9), 0 0 24px rgba(212,175,55,0.7); }
            100% { text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.9), 0 0 16px rgba(212,175,55,0.4); }
        }
        .auth-form {
            background: rgba(45, 31, 18, 0.6);
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,0.18) 10px, rgba(0,0,0,0.18) 20px),
                linear-gradient(145deg, rgba(56, 41, 28, 0.6), rgba(39, 26, 17, 0.6));
            padding: 24px;
            border-radius: 4px;
            width: min(92vw, 340px);
            color: #f4d58d;
            position: relative;
            border: 3px solid #61491f;
            box-shadow: 0 10px 30px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.1);
            image-rendering: pixelated;
        }
        .auth-form h2 { margin-top: 0; color: #ffeaa7; text-shadow: 2px 2px 4px rgba(0,0,0,0.8); }
        .auth-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background: linear-gradient(135deg, rgba(20,15,10,0.98), rgba(30,20,15,1));
            border: 2px solid #5c4a35;
            color: #f4d58d;
            border-radius: 3px;
            box-sizing: border-box;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.5);
            transition: all 0.2s;
        }
        .auth-form input::placeholder {
            color: #8b7355;
        }
        .auth-form input:focus {
            outline: none;
            border-color: #8b7355;
            box-shadow: 0 0 0 2px rgba(139,115,85,0.3);
            background: linear-gradient(135deg, rgba(30,22,15,0.95), rgba(40,28,20,1));
        }
        .auth-form button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(180deg, #8b4513 0%, #6b3410 50%, #4a220c 100%);
            color: #ffeaa7;
            border: 2px solid #5c3a1f;
            border-radius: 2px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 3px 0 #3a1f0c, 0 5px 8px rgba(0,0,0,0.4);
            transition: all 0.1s;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
        }
        .auth-form button:hover { 
            background: linear-gradient(180deg, #9b5523 0%, #7b4420 50%, #5a321c 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 0 #3a1f0c, 0 6px 10px rgba(0,0,0,0.5);
        }
        .auth-form button:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 #3a1f0c, 0 2px 4px rgba(0,0,0,0.3);
        }
        .auth-form .toggle-link { text-align: center; margin-top: 15px; color: #8b7355; font-size: 12px; }
        .auth-form .toggle-link a { color: #d4af37; cursor: pointer; text-decoration: underline; transition: color 0.2s; }
        .auth-form .toggle-link a:hover { color: #ffeaa7; }
        .auth-form label { color: #c9a875; }
        @media (max-height: 700px) {
            .auth-content { gap: 12px; padding: 12px; }
            .auth-logo-main { font-size: clamp(30px, 7vw, 72px); }
            .auth-form { padding: 22px; }
        }

        .char-selection { 
            background: linear-gradient(145deg, #38291c, #271a11);
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,0.15) 10px, rgba(0,0,0,0.15) 20px);
            padding: 30px; 
            border-radius: 4px;
            border: 3px solid #61491f;
            width: 400px; 
            color: #f4d58d; 
            position: relative; 
            z-index: 5;
            box-shadow: 0 10px 30px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.1);
            image-rendering: pixelated;
        }
        .char-selection h2 { margin-top: 0; color: #ffeaa7; text-shadow: 2px 2px 4px rgba(0,0,0,0.8); }
        .char-selection-close {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.35);
            border: 1px solid #555;
            border-radius: 6px;
            padding: 6px;
        }
        .char-slots { display: flex; flex-direction: column; gap: 10px; margin: 20px 0; }
        .char-slot { 
            background: linear-gradient(135deg, #352316 0%, #281b0f 100%);
            padding: 15px; 
            border-radius: 3px;
            border: 2px solid #5c4a35;
            cursor: pointer; 
            transition: 0.2s;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5), 0 2px 4px rgba(0,0,0,0.3);
        }
        .char-slot:hover { 
            border-color: #8b7355;
            background: linear-gradient(135deg, #4a3a2a 0%, #3d2f22 100%);
            transform: translateY(-2px);
        }
        .char-slot.empty { color: #8b7355; }
        .char-slot-name { font-weight: bold; color: #ffeaa7; text-shadow: 1px 1px 2px rgba(0,0,0,0.8); }
        .char-slot-class { font-size: 12px; color: #c9a875; }
        
        /* --- TOAST NOTIFICATIONS --- */
        #toast-container {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            display: flex; flex-direction: column; gap: 10px; z-index: 10100;
            pointer-events: none;
        }
        .toast {
            background: linear-gradient(135deg, #352316 0%, #281b0f 100%);
            border: 2px solid #61491f;
            border-left: 4px solid #d4af37;
            color: #ffeaa7; 
            padding: 12px 24px; 
            border-radius: 3px;
            min-width: 250px; 
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.1);
            font-size: 14px;
            animation: slideUp 0.3s ease-out forwards; 
            pointer-events: auto;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            image-rendering: pixelated;
        }
        .toast.error { border-left-color: #c62828; color: #ffb3b3; }
        .toast.big { font-size: 16px; padding: 20px 40px; border-left-width: 6px; font-weight: bold; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* --- TRANSITION OVERLAY --- */
        #transition-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #000;
            opacity: 0;
            pointer-events: none;
            z-index: 10005;
            transition: opacity 0.25s ease;
        }
        #transition-overlay.active { opacity: 1; }

        /* --- SMALL MODAL (INPUT) --- */
        .small-modal { 
            position: fixed; 
            top: 0; left: 0; 
            width: 100%; height: 100%; 
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(3px);
            z-index: 10150; 
            display: none; 
            align-items: center; 
            justify-content: center;
        }
        .small-modal-content { 
            background: linear-gradient(145deg, #38291c, #271a11);
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,0.15) 10px, rgba(0,0,0,0.15) 20px);
            padding: 25px;
            border-radius: 4px;
            border: 3px solid #61491f;
            width: 300px; 
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8), inset 0 1px 0 rgba(255,255,255,0.1);
            image-rendering: pixelated;
            color: #f4d58d;
        }
        .small-modal-content h3 {
            color: #ffeaa7;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        .small-modal input { 
            width: 100%; 
            padding: 10px; 
            margin: 15px 0;
            background: linear-gradient(135deg, rgba(20,15,10,0.98), rgba(30,20,15,1));
            border: 2px solid #5c4a35;
            color: #f4d58d;
            border-radius: 3px;
            box-sizing: border-box;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.5);
        }
        .small-modal input::placeholder {
            color: #8b7355;
        }
        .small-modal input:focus {
            outline: none;
            border-color: #8b7355;
            box-shadow: 0 0 0 2px rgba(139,115,85,0.3);
        }

        /* --- LOADING SCREEN --- */
        #loading-screen { z-index: 9999; background: #000; }
        .loading-bar-bg { 
            width: 300px; 
            height: 24px; 
            background: linear-gradient(135deg, #2d1f12, #3a2a1a);\n            border: 3px solid #5c4a35;\n            border-radius: 3px;\n            overflow: hidden; 
            margin-top: 20px; 
            position: relative; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.7), inset 0 2px 4px rgba(0,0,0,0.5);\n            image-rendering: pixelated;
        }
        .loading-bar-fill { 
            height: 100%; 
            background: linear-gradient(180deg, #d4af37, #b8941f);\n            width: 0%; 
            transition: width 0.2s linear; 
            box-shadow: 0 0 10px rgba(212,175,55,0.6);\n        }
        
        /* --- RANGE INPUTS (SLIDERS) --- */
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            height: 8px;
            background: linear-gradient(135deg, #2d1f12, #3a2a1a);
            border: 2px solid #5c4a35;
            border-radius: 2px;
            outline: none;
            cursor: pointer;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            background: linear-gradient(135deg, #8b7355, #6b5845);
            border: 2px solid #5c4a35;
            border-radius: 2px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        input[type="range"]::-moz-range-thumb {
            width: 18px;
            height: 18px;
            background: linear-gradient(135deg, #8b7355, #6b5845);
            border: 2px solid #5c4a35;
            border-radius: 2px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        input[type="range"]::-webkit-slider-thumb:hover {
            background: linear-gradient(135deg, #9b8365, #7b6855);
        }
        input[type="range"]::-moz-range-thumb:hover {
            background: linear-gradient(135deg, #9b8365, #7b6855);
        }

        /* --- RESPAWN EFFECT --- */
        @keyframes respawnAnim {
            0% { transform: scale(0.1); opacity: 0; background: #fff; }
            50% { transform: scale(1.2); opacity: 1; background: cyan; box-shadow: 0 0 50px 20px cyan; }
            100% { transform: scale(2); opacity: 0; background: transparent; }
        }
        .respawn-effect {
            position: absolute; width: 128px; height: 128px;
            border-radius: 50%; pointer-events: none; z-index: 2000;
            animation: respawnAnim 1.0s ease-out forwards;
            mix-blend-mode: screen;
        }

        #mobile-panel-toggle {
            display: none;
        }
        
        /* --- TOP LEFT UI --- */
        .top-left-ui {
            position: absolute; top: 10px; left: 10px; z-index: 1600;
            display: flex; flex-direction: column; align-items: flex-start; gap: 5px;
        }
        .top-left-ui .world-text { color: #c9a875; font-size: 12px; text-shadow: 1px 1px 2px rgba(0,0,0,0.9); }
        .top-left-ui #world-info { color: #ffeaa7; font-weight: bold; font-size: 14px; text-shadow: 1px 1px 3px rgba(0,0,0,0.9); }
        .top-left-ui .online-btn { 
            padding: 5px 10px; 
            font-size: 12px; 
            background: linear-gradient(135deg, rgba(58,42,26,0.9), rgba(45,31,18,0.95));
            border: 2px solid #61491f;
            border-radius: 2px;
            margin:0; 
            min-width:120px; 
            display:flex; 
            justify-content:space-between;
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
            color: #f4d58d;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
        }
        
        /* Online List Dropdown */
        #online-list-dropdown {
            background: linear-gradient(135deg, rgba(58,42,26,0.95), rgba(45,31,18,0.98));
            border: 2px solid #61491f;
            border-radius: 3px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.7);
            color: #f4d58d;
            padding: 8px;
        }
        #online-list-dropdown .online-player-item {
            padding: 6px 8px;
            border-bottom: 1px solid rgba(92,74,53,0.5);
            cursor: pointer;
            transition: all 0.2s;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.8);
        }
        #online-list-dropdown .online-player-item:hover {
            background: rgba(139,115,85,0.3);
            color: #ffeaa7;
        }
        #online-list-dropdown .online-player-item:last-child {
            border-bottom: none;
        }
        
        /* Shop Button */
        #shop-btn {
            padding: 10px 20px;
            background: linear-gradient(180deg, #d4af37 0%, #b8941f 50%, #9c7f18 100%);
            color: #1a1410;
            border: 2px solid #c5a028;
            border-radius: 2px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 3px 0 #7a6012, 0 5px 10px rgba(212,175,55,0.4);
            text-shadow: 1px 1px 1px rgba(255,255,255,0.3);
            transition: all 0.1s;
            image-rendering: pixelated;
        }
        #shop-btn:hover {
            background: linear-gradient(180deg, #e4bf47 0%, #c8a42f 50%, #ac8f28 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 0 #7a6012, 0 6px 12px rgba(212,175,55,0.5);
        }
        #shop-btn:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 #7a6012, 0 2px 4px rgba(212,175,55,0.3);
        }

        /* --- RESPONSIVE / MOBILE --- */
        @media (max-width: 1366px) {
            #game-layout {
                flex-direction: column;
            }
            body { font-size: 18px; }

            /* Hide desktop toggles */
            #right-panel .icon-btn[title*="Hide Panel"],
            #expand-panel-btn {
                display: none !important;
            }

            /* Show and style mobile toggle */
            #mobile-panel-toggle {
                display: block;
                position: fixed;
                bottom: 10px;
                right: 10px;
                z-index: 3001;
                background: rgba(30,30,30,0.85);
                color: white;
                border: 1px solid #555;
                border-radius: 50%;
                width: 26px;
                height: 26px;
                font-size: 12px;
                line-height: 26px; /* Center icon vertically */
                text-align: center;
                box-shadow: 0 0 10px black;
                transition: transform 0.3s;
            }
            
            .tab-content {
                padding: 10px;
            }
            .tab-btn {
                padding: 15px 10px;
                font-size: 16px;
            }
            .item-slot {
                width: 70px; height: 70px; /* Bigger slots */
            }
            .combat-btn {
                padding: 6px 10px; font-size: 11px; /* 60% smaller buttons */
            }
            
            #world-btn {
                top: auto;
                bottom: 20px;
                right: auto;
                left: 20px;
                padding: 5px 8px;
                font-size: 10px;
                position: fixed;
                z-index: 3100;
            }

            #shop-btn {
                bottom: 100px; /* Move up to not be covered by mobile panel toggle */
                padding: 6px 12px;
                font-size: 11px;
                position: fixed;
                z-index: 3100;
            }
            
            /* Bigger Top Left UI */
            .top-left-ui { top: 20px; left: 20px; gap: 10px; }
            .top-left-ui .world-text { font-size: 6px; }
            .top-left-ui #world-info { font-size: 8px; }
            .top-left-ui .online-btn { font-size: 6px; padding: 4px 6px; min-width: 80px; }
            #online-list-dropdown { width: 110px; font-size: 6px; }
            
            /* Bigger Modals for Mobile */
            .modal-panel {
                width: 95% !important;
                max-height: 85vh;
            }

            #settings-modal .modal-panel {
                width: 92vw !important;
                max-height: 80vh;
                padding: 20px;
                overflow: auto;
            }

            #char-selection-modal .char-selection {
                width: 95% !important;
                max-height: 85vh;
                padding: 16px;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            #char-selection-modal .char-slots {
                flex: 1 1 auto;
                overflow-y: auto;
                margin: 12px 0;
                padding-bottom: env(safe-area-inset-bottom);
            }

            #char-selection-modal .combat-btn {
                margin-top: 8px !important;
            }
        }

        @media (min-width: 901px) {
            #game-layout.panel-collapsed #expand-panel-btn {
                display: block !important;
            }
        }

        /* --- MOBILE PORTRAIT (Pionowo - Panel na dole) --- */
        @media (max-width: 1366px) and (orientation: portrait) {
            #right-panel {
                position: fixed; bottom: 0; left: 0;
                width: 100%; height: 35vh;
                border-left: none; border-top: 2px solid #444;
                margin-right: 0 !important;
                transform: translateY(0); z-index: 3000;
                transition: transform 0.3s ease-in-out;
                font-size: 18px;
            }
            
            /* Move buttons with panel in portrait mode */
            #world-btn, #shop-btn {
                position: fixed !important;
                z-index: 10001 !important;
                transition: bottom 0.3s ease-in-out;
                bottom: calc(360px + env(safe-area-inset-bottom)) !important;
            }
            #game-layout.panel-collapsed #left-panel #world-btn,
            #game-layout.panel-collapsed #left-panel #shop-btn {
                bottom: calc(20px + env(safe-area-inset-bottom)) !important;
            }

            #game-layout.panel-collapsed #right-panel {
                transform: translateY(100%);
            }
            .big-bar-widget { height: 32px; }

            /* Adjust shop button for portrait */
            #shop-btn {
                left: 50% !important;
                right: auto !important;
                transform: translateX(-50%) !important;
                bottom: calc(360px + env(safe-area-inset-bottom)) !important;
                padding: 8px 15px;
                font-size: 12px;
                z-index: 10001 !important;
            }

            #shop-modal .modal-panel {
                width: 95% !important;
                height: 80vh !important;
                max-height: 85vh;
            }

            #shop-modal .shop-tabs {
                flex-wrap: wrap;
            }

            #shop-modal .shop-tabs .tab-btn {
                flex: 1 1 33%;
                padding: 8px 6px;
                font-size: 10px;
            }

            #char-selection-modal .char-selection h2 {
                font-size: 16px;
                margin-bottom: 6px;
            }

            #char-selection-modal .char-slot {
                padding: 10px;
            }

        }

        /* --- MOBILE LANDSCAPE (Poziomo - Panel po prawej) --- */
        @media (max-width: 1366px) and (orientation: landscape) {
            #right-panel {
                position: fixed; top: 0; right: 0;
                width: 300px; height: 100%;
                border-left: 2px solid #444; border-top: none;
                margin-right: 0 !important;
                transform: translateX(0); z-index: 3000;
                transition: transform 0.3s ease-in-out;
            }
            #game-layout.panel-collapsed #right-panel {
                transform: translateX(100%);
            }

            #world-btn {
                top: 10px !important;
                left: 10px !important;
                right: auto !important;
                bottom: auto !important;
                position: fixed;
                z-index: 3100;
            }
            #shop-btn {
                left: 50% !important;
                right: auto !important;
                bottom: calc(10px + env(safe-area-inset-bottom)) !important;
                transform: translateX(-50%) !important;
                position: fixed;
                z-index: 10001;
            }

            #char-selection-modal .char-selection {
                max-height: 90vh;
            }
        }
        /* --- MOBILE COMBAT (NEW LAYOUT) --- */
        @media (max-width: 900px) {
            #combat-screen {
                padding: 12px;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding-bottom: calc(16px + env(safe-area-inset-bottom));
            }
            #combat-screen .combat-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            #combat-screen .combat-title {
                font-size: 12px;
                letter-spacing: 1px;
                text-transform: uppercase;
                color: #ff8a80;
                margin: 0;
            }
            #combat-screen .combat-hud {
                display: grid;
                grid-template-columns: 1fr;
                gap: 8px;
            }
            #combat-screen .combat-bar-row {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            #combat-screen .combat-bar-row.enemy-row {
                align-items: flex-end;
                text-align: right;
            }
            #combat-screen .combat-bar-row .label {
                font-size: 12px;
                color: #ddd;
                display: flex;
                justify-content: space-between;
                gap: 8px;
            }
            #combat-screen .big-bar-widget {
                width: 100% !important;
                height: 22px;
            }
            #combat-screen .combat-arena-shell {
                position: relative;
                width: 100%;
                height: 42vh;
                min-height: 240px;
                border: 1px solid #2a2a2a;
                border-radius: 10px;
                overflow: hidden;
                background: rgba(0, 0, 0, 0.35);
                box-shadow: inset 0 0 30px rgba(0,0,0,0.6);
            }
            #combat-screen .combat-arena-shell #combat-arena-container {
                position: absolute;
                left: 0;
                top: 0;
                margin: 0;
            }
            #combat-controls {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                margin: 0;
                width: 100% !important;
                margin-bottom: env(safe-area-inset-bottom);
            }
            #combat-controls .combat-btn {
                padding: 8px 6px;
                font-size: 11px;
                border-radius: 6px;
                line-height: 1.1;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }
            #combat-controls img {
                width: 14px;
                height: 14px;
            }
            #combat-log {
                min-height: 36px;
                font-size: 12px;
                text-align: center;
                margin: 0;
            }
        }

        @media (max-width: 1366px) and (orientation: landscape) {
            #combat-screen {
                padding: 6px;
                gap: 6px;
            }
            #combat-screen .combat-hud {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                align-items: start;
            }
            #combat-screen .combat-bar-row {
                width: auto;
            }
            #combat-screen .combat-bar-row.enemy-row {
                align-items: flex-end;
                text-align: right;
            }
            #combat-screen .combat-arena-shell {
                height: 32vh;
                min-height: 180px;
            }
            #combat-controls {
                display: flex !important;
                flex-wrap: wrap;
                gap: 6px;
                margin: 0;
                width: 100% !important;
                order: 4;
                transform: translateY(-30%);
            }
            #combat-controls .combat-btn {
                padding: 6px 8px;
                font-size: 10px;
            }
            #combat-controls img {
                width: 14px;
                height: 14px;
            }
            #combat-log {
                font-size: 11px;
                min-height: 24px;
                margin-top: 0;
                order: 3;
            }

            .combat-title {
                display: none !important;
            }

            #world-btn {
                top: 10px !important;
                right: 320px !important;
                left: auto !important;
                bottom: auto !important;
                position: fixed;
                z-index: 10001;
            }
            #game-layout.panel-collapsed #world-btn {
                right: 10px !important;
            }

            #shop-btn {
                left: 50% !important;
                right: auto !important;
                bottom: calc(20px + env(safe-area-inset-bottom)) !important;
                transform: translateX(-50%) !important;
                position: fixed;
                z-index: 10001;
            }
        }
    </style>
</head>
<body>

<div id="auth-modal" class="auth-modal">
    <div class="auth-bg-layer layer-1"></div>
    <div class="auth-bg-layer layer-2"></div>
    <div class="auth-bg-layer layer-3"></div>
    <div class="auth-bg-layer layer-4"></div>
    <div class="auth-content">
        <div class="auth-logo" aria-label="HexRealms">
            <span class="auth-logo-main">HexRealms</span>
        </div>
        <div class="auth-form">
            <h2 id="auth-title">Login</h2>
            <div id="login-form">
                <input type="text" id="login-username" placeholder="Username">
                <input type="password" id="login-password" placeholder="Password">
                <label style="display:flex; align-items:center; gap:8px; margin:10px 0; color:#c9a875; font-size:12px;">
                    <input type="checkbox" id="remember-me" style="width:16px; height:16px; cursor:pointer;">
                    Remember me for 7 days
                </label>
                <button onclick="handleLogin()">Login</button>
                <div class="toggle-link">New account? <a onclick="toggleAuthForm()">Register</a></div>
            </div>
            <div id="register-form" style="display:none;">
                <input type="text" id="register-username" placeholder="Username">
                <input type="password" id="register-password" placeholder="Password">
                <input type="password" id="register-password2" placeholder="Confirm password">
                <button onclick="handleRegister()">Register</button>
                <div class="toggle-link">Already have an account? <a onclick="toggleAuthForm()">Login</a></div>
            </div>
        </div>
    </div>
</div>

<div id="create-char-modal" class="small-modal">
    <div class="small-modal-content">
        <h3 style="margin-top:0;">Create Character</h3>
        <input type="text" id="new-char-name" placeholder="Character Name" maxlength="15">
        <div style="display:flex; gap:10px; justify-content:center;">
            <button class="combat-btn" style="font-size:12px; filter: brightness(0.7);" onclick="closeCreateCharacter()">Cancel</button>
            <button class="combat-btn" style="font-size:12px;" onclick="submitNewCharacter()">Create</button>
        </div>
    </div>
</div>

<div id="mobile-disclaimer-modal" class="small-modal">
    <div class="small-modal-content" style="width:320px;">
        <h3 style="margin-top:0;">📱 Best Experience</h3>
        <p style="margin:15px 0; font-size:14px;">For best experience, please switch to <strong>Portrait Mode</strong> 📲</p>
        <p style="margin:15px 0; font-size:12px; color:#8b7355;">Flip your phone and rotate to portrait for optimal gameplay.</p>
        <button class="combat-btn" style="width:100%; margin-top:10px;" onclick="document.getElementById('mobile-disclaimer-modal').style.display='none'">Got it!</button>
    </div>
</div>

<div id="toast-container"></div>
<div id="transition-overlay"></div>

<div id="char-selection-modal" class="modal">
    <div class="char-selection">
        <h2>Select Character</h2>
        <div class="char-slots" id="char-slots-container"></div>
        <button class="combat-btn" style="width:220px; margin:20px auto 0; display:block;" onclick="closeCharacterSelection()">Close</button>
    </div>
</div>

<div id="settings-modal" class="modal">
    <div class="modal-panel" style="padding:30px; width:300px; text-align:center; position:relative;">
        <button class="icon-btn" style="position:absolute; top:10px; right:10px;" onclick="toggleSettings()">
            <img src="assets/ui/ex.png" alt="Close">
        </button>
        
        <h2 style="margin-top:0;">Settings</h2>
        
        <div style="margin:20px 0; background:linear-gradient(135deg, rgba(20,15,10,0.98), rgba(30,20,15,1.0)); padding:15px; border-radius:4px; border:2px solid #5c4a35;">
            <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:10px;">
                <img src="assets/ui/music.png" style="width:24px; height:24px;">
                <span style="color:#c9a875;">Music</span>
            </div>
            <div style="display:flex; align-items:center; justify-content:center; gap:15px;">
                <button class="icon-btn" onclick="playMusic()"><img src="assets/ui/play.png" alt="Play"></button>
                <button class="icon-btn" onclick="stopMusic()"><img src="assets/ui/ex.png" alt="Stop"></button>
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:10px;">
                <span style="color:#8b7355; font-size:12px;">Volume</span>
                <span id="music-volume-value" style="color:#c9a875; font-size:12px;">15%</span>
            </div>
            <input id="music-volume" type="range" min="0" max="1" step="0.01" value="0.15" oninput="setVolume(this.value)" style="width:100%;">

            <label style="display:flex; align-items:center; gap:8px; margin-top:10px; color:#c9a875; font-size:12px; justify-content:center;">
                <input type="checkbox" id="music-loop-toggle" onchange="setMusicLoop(this.checked)">
                Loop current track
            </label>

            <div style="margin-top:10px; text-align:left;">
                <div style="color:#8b7355; font-size:12px; margin-bottom:6px;">Now playing: <span id="music-now-playing" style="color:#f4d58d;">-</span></div>
                <select id="music-track-select" onchange="setMusicTrack(this.value)" style="width:100%; padding:6px; background:rgba(20,15,10,0.9); border:2px solid #5c4a35; color:#f4d58d; border-radius:3px; font-family: inherit;"></select>
            </div>
            
            <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-top:15px; margin-bottom:5px;">
                <span style="color:#c9a875;">Sounds</span>
            </div>
            <input type="range" min="0" max="1" step="0.05" value="0.3" oninput="setSfxVolume(this.value)" style="width:100%;">
        </div>

        <button class="combat-btn" style="width:100%; margin:5px 0; filter: hue-rotate(180deg);" onclick="changeCharacter()">Change Character</button>
        <button class="combat-btn" style="width:100%; margin:5px 0; filter: hue-rotate(350deg) brightness(1.1);" onclick="handleLogout()">Logout</button>
    </div>
</div>

<div id="guilds-modal" class="modal">
    <div class="modal-panel" style="width:500px; max-height:600px; display:flex; flex-direction:column;">
        <div style="padding:15px; border-bottom:2px solid #61491f; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0;">Guilds</h2>
            <button class="icon-btn" onclick="document.getElementById('guilds-modal').style.display='none'"><img src="assets/ui/ex.png" alt="Close"></button>
        </div>
        <div style="padding:10px 15px; background:linear-gradient(135deg, rgba(20,15,10,0.98), rgba(30,20,15,1.0)); border-bottom:2px solid #5c4a35;">
            <div style="font-size:13px; color:#c9a875;">Your Reputation: <span id="guild-reputation-val" style="color:#d4af37;">0</span></div>
        </div>
        <div id="guilds-content" style="flex:1; padding:15px; overflow-y:auto; color:#f4d58d;">Loading...</div>
    </div>
</div>

<div id="start-screen" class="modal" style="display:flex;">
    <div class="start-bg-layer layer-1"></div>
    <div class="start-bg-layer layer-2"></div>
    <div class="start-bg-layer layer-3"></div>
    <div class="start-bg-layer layer-4"></div>
    <h1 style="color:#ffeaa7; text-shadow: 3px 3px 6px rgba(0,0,0,0.9), 0 0 20px rgba(212,175,55,0.4);">HexRealms</h1>
    <button class="combat-btn" onclick="showAuthModal()">LOGIN</button>
</div>

<div id="game-layout">
    <div id="left-panel">
        <div id="day-night-overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:1500; transition: background-color 5s;"></div>
        <div id="wind-layer"></div>
        
        <div class="top-left-ui">
            <div class="world-text">
                WORLD: <span id="world-info">...</span>
            </div>
            <div style="position:relative;">
                <button class="combat-btn online-btn" onclick="togglePlayerList()">
                    <span><span style="color:#d4af37;">●</span> <span id="online-count-val" style="color:#ffeaa7;">1</span> Online</span> <span>▼</span>
                </button>
                <div id="online-list-dropdown" style="display:none; position:absolute; top:100%; left:0; width: 200px; max-height: 300px; overflow-y: auto; margin-top: 5px; padding: 5px;">
                    <!-- List items injected by JS -->
                </div>
            </div>
        </div>

        <button id="world-btn" style="display:none;" onclick="showWorldSelection()">Select World 🌐</button>
        <button id="shop-btn" style="display:none; position:absolute; bottom:20px; left:50%; transform:translateX(-50%); z-index:1600;" onclick="openCityMenu()">🏰 Enter Market</button>
        <button id="expand-panel-btn" style="display:none; position:absolute; top:55px; right:10px; z-index:1600; background:linear-gradient(135deg, rgba(58,42,26,0.9), rgba(45,31,18,0.95)); color:#f4d58d; border:2px solid #61491f; padding:8px 12px; cursor:pointer; font-weight:bold; border-radius:2px; box-shadow: 0 2px 6px rgba(0,0,0,0.6); text-shadow: 1px 1px 2px rgba(0,0,0,0.8);" onclick="toggleRightPanel()" title="Show Panel (Tab)">«</button>

        <div id="map"></div>
    </div>

    <div id="right-panel">
        <div style="padding:15px; border-bottom:2px solid #61491f; background:linear-gradient(180deg, #3d2f1f 0%, #2a1e14 100%); display:flex; justify-content:space-between; align-items:center;">
            <button class="icon-btn" onclick="toggleRightPanel()" title="Hide Panel (Tab)" style="font-weight:bold; color:#c9a875; font-size:18px;">»</button>
            <div style="display:flex; gap:10px;">
                <button class="icon-btn" onclick="toggleSettings()">
                    <img src="assets/ui/cogwheel.png" alt="Settings">
                </button>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('stats')">Character</button>
            <button class="tab-btn" onclick="switchTab('inventory')">Inventory</button>
            <button class="tab-btn" onclick="switchTab('attributes')">Attributes</button>
            <button class="tab-btn" onclick="switchTab('quests')">Quests</button>
        </div>

        <div id="tab-stats" class="tab-content active">
            <h2 id="class-name" style="margin:0; color:#ffeaa7;">Character</h2>
            <div style="font-size:12px; color:#8b7355; margin-bottom:20px;">Level <span id="lvl">1</span> - <span id="char-class" style="color:#c9a875;">Unknown</span></div>
            <div style="color:#f4d58d;">Health: <span id="hp">100 / 100</span></div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill" id="hp-fill" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
            <div style="margin-top:15px; color:#f4d58d;">Energy: <span id="energy">10 / 10</span></div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill energy" id="en-fill" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
            <div style="text-align:right; font-size:11px; color:#5c4a35;">Steps: <span id="steps-info">0/10</span></div>
            <div style="margin-top:15px; color:#f4d58d;">XP: <span id="xp-text">0 / 100</span></div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill xp" id="xp-fill" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
            <div style="margin-top:15px; color:#f4d58d;">Coins: <span id="gold-val" style="color:#d4af37; font-weight:bold;">0 copper coins</span></div>
        </div>

        <div id="tab-inventory" class="tab-content">
            <h3>Backpack</h3>
            <div id="inventory-grid" class="item-grid"></div>
        </div>
        
        <div id="tab-attributes" class="tab-content">
            <div style="text-align:center; margin-bottom:15px;">
                <div style="font-size:14px; color:#888;">Available Points</div>
                <div id="stat-points-val" style="font-size:24px; color:#ffd700; font-weight:bold;">0</div>
            </div>
            <div id="attributes-list" style="display:flex; flex-direction:column; gap:10px;">
                <!-- JS will populate this -->
            </div>
        </div>

        <div id="tab-quests" class="tab-content">
            <h3>Active Quests</h3>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <div style="font-size:13px; color:#888;">Reputation: <span id="reputation-val" style="color:#ffd700;">0</span></div>
                <button class="combat-btn" style="padding:5px 10px; font-size:11px;" onclick="openGuildsModal()">Guilds</button>
            </div>
            <div id="active-quests-list" style="display:flex; flex-direction:column; gap:10px; max-height:400px; overflow-y:auto;">
                <div style="text-align:center; color:#666; padding:20px; font-size:13px;">No active quests</div>
            </div>
        </div>
    </div>
</div>

<div id="combat-screen" class="modal">
    <div class="combat-header">
        <h2 class="combat-title">Tactical Combat</h2>
    </div>
    <div class="combat-hud">
        <div class="combat-bar-row">
            <div class="label" style="color:#4caf50;">
                <span>YOU</span>
                <span><span id="combat-hp">100</span> HP</span>
            </div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill" id="combat-hp-bar" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
        </div>
        <div class="combat-bar-row enemy-row">
            <div class="label" style="color:#f44336;">
                <span id="enemy-name">ENEMY</span>
                <span><span id="enemy-hp">??</span> HP</span>
            </div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill" id="combat-enemy-fill" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
        </div>
    </div>
    <div class="combat-arena-shell" id="combat-arena-shell"></div>
    <div id="combat-controls" style="margin-top:20px; display:flex; gap:10px; justify-content: center;">
        <button class="combat-btn" style="background:#4caf50; display:flex; align-items:center; gap:5px;" onclick="handleCombatDefend()">
            <img src="assets/ui/shield.png" style="width:20px; height:20px;"> Defend (1AP)
        </button>
        <button class="combat-btn" style="background:#2196f3;" onclick="useItem(7)">🧪 Potion (2AP)</button>
        <button class="combat-btn" style="background:#ff9800;" onclick="useItem(8)">🩹 Bandage (2AP)</button>
    </div>
    <p id="combat-log" style="color:#c9a875; margin-top:15px; font-style:italic; height:20px; text-shadow: 1px 1px 2px rgba(0,0,0,0.9);">Waiting...</p>
</div>

<div id="death-screen" class="modal">
    <h1 style="color:#f44336;">YOU DIED</h1>
    <button class="combat-btn" onclick="respawnPlayer()">Respawn</button>
</div>

<div id="loading-screen" class="modal">
    <h2 style="color:#2196f3; text-transform: uppercase; letter-spacing: 2px;">Loading Assets</h2>
    <div class="loading-bar-bg">
        <div id="loading-bar-fill" class="loading-bar-fill"></div>
    </div>
    <div id="loading-text" style="color:#888; margin-top:10px; font-family:monospace; font-size:12px;">Initializing...</div>
</div>

<div id="world-selection" class="modal" style="display:none;">
    <div class="modal-panel" style="background:#1b1b1b; padding:20px; border-radius:8px; width:400px; max-height:70vh; overflow:auto; color:#fff;">
        <button class="icon-btn" style="position:absolute; top:10px; right:10px;" onclick="document.getElementById('world-selection').style.display='none'">
            <img src="assets/ui/ex.png" alt="Close">
        </button>

        <h2 style="color:#00e676; margin-top:0;">Select World</h2>
        <div id="world-list" style="display:flex; flex-direction:column; gap:8px; margin-top:10px;"></div>
    </div>
</div>

<div id="shop-modal" class="modal">
    <div class="modal-panel" style="background:#1e1e1e; width:600px; height:500px; border:1px solid #444; border-radius:8px; display:flex; flex-direction:column;">
        <div style="padding:15px; border-bottom:1px solid #333; display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0; color:#e0e0e0;">City Market</h2>
            <button class="icon-btn" onclick="document.getElementById('shop-modal').style.display='none'"><img src="assets/ui/ex.png" alt="Close"></button>
        </div>
        <div class="shop-tabs" style="display:flex; background:#111;">
            <button type="button" class="tab-btn active" onclick="loadShop('leathersmith', this, null); return false;">Leathersmith</button>
            <button type="button" class="tab-btn" onclick="loadShop('blacksmith', this, null); return false;">Blacksmith</button>
            <button type="button" class="tab-btn" onclick="loadShop('armorer', this, null); return false;">Armorer</button>
            <button type="button" class="tab-btn" onclick="loadShop('clergy', this, null); return false;">Clergy</button>
            <button type="button" class="tab-btn" onclick="loadSellTab(this); return false;">Sell Loot</button>
            <button type="button" class="tab-btn" onclick="loadQuestsTab(this); return false;">Quests</button>
        </div>
        <div style="display:flex; gap:8px; padding:10px 15px; background:#0a0a0a; border-bottom:1px solid #333;">
            <button type="button" class="class-filter-btn" onclick="loadShop(null, null, 1); return false;">Warrior</button>
            <button type="button" class="class-filter-btn" onclick="loadShop(null, null, 2); return false;">Mage</button>
            <button type="button" class="class-filter-btn" onclick="loadShop(null, null, 3); return false;">Rogue</button>
            <button type="button" class="class-filter-btn" onclick="loadShop(null, null, null); return false;" style="margin-left:auto;">All Items</button>
        </div>
        <div id="shop-content" style="flex:1; padding:15px; overflow-y:auto; color:#ccc;">Select a merchant...</div>
        <div style="padding:10px; border-top:1px solid #333; text-align:right; font-weight:bold; color:gold;">Your Coins: <span id="shop-gold">0 copper coins</span></div>
    </div>
</div>

<div id="combat-result-modal" class="modal">
    <div class="modal-panel" style="background:#1b1b1b; padding:30px; border-radius:8px; width:300px; text-align:center; border: 2px solid #ffd700; box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);">
        <h2 style="color:#ffd700; margin-top:0; text-transform:uppercase; letter-spacing:2px;">Victory!</h2>
        <div id="combat-result-content" style="margin: 20px 0; color: #fff;"></div>
        <button class="combat-btn" style="background:#4caf50; width:100%; margin:0;" onclick="closeCombatResult()">Continue</button>
    </div>
</div>

<div id="class-selection" class="modal">
    <h1>Select Class</h1>
    <div style="display:flex; gap:20px;">
        <div class="class-card" onclick="selectClass(1)"><h3>Warrior</h3></div>
        <div class="class-card" onclick="selectClass(2)"><h3>Mage</h3></div>
        <div class="class-card" onclick="selectClass(3)"><h3>Rogue</h3></div>
    </div>
</div>

<button id="mobile-panel-toggle" onclick="toggleRightPanel()">▼</button>

<script src="game.js?v=<?php echo $assetVersion; ?>"></script>
</body>
</html>