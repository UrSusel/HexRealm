<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RPG World</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <style>
        /* --- OGÓLNE --- */
        body { 
            background-color: #121212; 
            color: #e0e0e0; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; padding: 0; 
            overflow: hidden; 
            height: 100vh; 
            cursor: url('assets/ui/Cursor_01.png') 20 18, auto;
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
        }
        @keyframes spaceScroll { from { background-position: 0 0, 0 0; } to { background-position: -2000px 0, -500px 0; } }
        
        #map { position: absolute; width: 4000px; height: 4000px; background: transparent; top: 0; left: 0; transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1); transform-origin: 0 0; }

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
        .tile.mountain { background-image: url('img/mountain.png'); }
        .tile.water { background-image: url('img/water.png'); }
        .tile.city_capital { background-image: url('img/castle.png'); }
        .tile.city_village { background-image: url('img/vilage.png'); }
        .tile:hover { filter: brightness(1.3) drop-shadow(0 0 10px white); z-index: 100 !important; }

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
            position: absolute; width: 8px; height: 8px;
            background: rgba(255, 255, 255, 0.6); border-radius: 50%;
            pointer-events: none;
            animation: smokeAnim 2.5s infinite linear;
        }
        @keyframes smokeAnim {
            0% { transform: translate(0, 0) scale(1); opacity: 0.5; }
            100% { transform: translate(10px, -25px) scale(3); opacity: 0; }
        }

        /* --- GRACZ/WRÓG --- */
        .player { 
            width: 128px; height: 128px; 
            background-repeat: no-repeat; background-position: center bottom; background-size: contain;
            image-rendering: pixelated; position: absolute; z-index: 1000; 
            pointer-events: none; 
            filter: drop-shadow(0px 5px 5px rgba(0,0,0,0.5));
        }
        .player.enemy {
            pointer-events: auto !important; cursor: url('assets/ui/Cursor_02.png') 15 15, crosshair !important;
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
        }

        /* --- UI PANEL --- */
        #right-panel { 
            width: 350px; 
            background: #1e1e1e; 
            border-left: 1px solid #333; 
            display: flex; flex-direction: column; z-index: 200; 
            flex-shrink: 0;
            transition: margin-right 0.4s ease-in-out, transform 0.4s ease-in-out;
        }
        .panel-collapsed #right-panel {
            margin-right: -350px;
        }
        .panel-collapsed #expand-panel-btn {
            display: block;
        }
        .tabs { display: flex; background: #252525; border-bottom: 1px solid #333; }
        .tab-btn { background: transparent; color: #888; border: none; padding: 20px; cursor: pointer; flex: 1; font-weight: bold; }
        .tab-btn.active { color: #fff; background: #333; border-bottom: 3px solid #f44336; }
        .tab-content { display: none; padding: 20px; overflow-y: auto; }
        .tab-content.active { display: block; }
        
        .bar-container { width: 100%; height: 8px; background: #333; border-radius: 4px; overflow: hidden; margin-top: 5px; }
        
        /* Nowy styl paska (3-częściowy) */
        .big-bar-widget {
            position: relative;
            display: grid; grid-template-columns: 14px 1fr 14px;
            height: 24px; width: 100%;
            image-rendering: pixelated;
        }
        /* Warstwa ramki (na wierzchu) */
        .hb-left { width: 100%; height: 100%; background: url('assets/ui/BigBar_left.png') no-repeat center; background-size: 100% 100%; z-index: 10; position: relative; pointer-events: none; }
        .hb-middle { 
            width: 100%; height: 100%; position: relative;
            background: url('assets/ui/BigBar_middle.png') repeat-x center; background-size: auto 100%; z-index: 1; pointer-events: none;
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
            background: #252525; border: 1px solid #444; border-radius: 5px; 
            height: 60px; display: flex; flex-direction: column; align-items: center; justify-content: center;
            position: relative; transition: all 0.2s;
        }
        .item-slot:hover { border-color: #aaa; background: #333; }
        .item-slot.equipped { border-color: #ffd700; box-shadow: 0 0 5px #ffd700; }
        
        /* Ikony przycisków */
        .icon-btn { background: none; border: none; cursor: pointer; padding: 5px; transition: transform 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .icon-btn:hover { transform: scale(1.1); }
        .icon-btn img { width: 32px; height: 32px; image-rendering: pixelated; }

        /* Modale */
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 3200; display: none; flex-direction: column; align-items: center; justify-content: center; color: white; }
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
        .combat-btn { padding: 12px 30px; background: #c62828; color: white; border: none; font-size: 16px; margin: 10px; cursor: pointer; border-radius: 4px; font-weight: bold; }

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
        
        #start-screen { display: flex; z-index: 2000; background: #000; }
        .class-card { padding: 20px; border: 2px solid #444; border-radius: 10px; cursor: pointer; text-align: center; width: 120px; transition: 0.3s; }
        .class-card:hover { border-color: #f44336; background: #222; transform: translateY(-5px); }
        
        /* Lista Światów */
        .world-item { background: #333; border: 1px solid #555; padding: 15px; margin: 5px; width: 300px; text-align: center; border-radius: 5px; transition: 0.2s; }
        .world-item:hover { background: #444; border-color: #00e676; }

        /* Przycisk zmiany świata */
        #world-btn {
            position: absolute; top: 10px; right: 20px; 
            padding: 10px 20px; background: #00e676; color: #000; 
            font-weight: bold; border: none; border-radius: 5px; cursor: pointer;
            z-index: 1600; display: none; box-shadow: 0 0 10px rgba(0,230,118,0.5);
        }
        #world-btn:hover { background: #00c853; }

        /* Przycisk zamknięcia (czerwony X, jak w Windows XP) */
        .modal-panel { position: relative; }
        /* Auth & Character Selection */
        .auth-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 2001; display: none; flex-direction: column; align-items: center; justify-content: center; }
        .auth-form { background: #1b1b1b; padding: 30px; border-radius: 8px; width: 300px; color: #fff; }
        .auth-form h2 { margin-top: 0; color: #00e676; }
        .auth-form input { width: 100%; padding: 10px; margin: 10px 0; background: #252525; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .auth-form button { width: 100%; padding: 12px; background: #00e676; color: #000; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .auth-form button:hover { background: #00c853; }
        .auth-form .toggle-link { text-align: center; margin-top: 15px; color: #888; font-size: 12px; }
        .auth-form .toggle-link a { color: #00e676; cursor: pointer; text-decoration: underline; }

        .char-selection { background: #1b1b1b; padding: 30px; border-radius: 8px; width: 400px; color: #fff; }
        .char-selection h2 { margin-top: 0; color: #00e676; }
        .char-slots { display: flex; flex-direction: column; gap: 10px; margin: 20px 0; }
        .char-slot { background: #252525; padding: 15px; border-radius: 5px; border: 1px solid #444; cursor: pointer; transition: 0.2s; }
        .char-slot:hover { border-color: #00e676; background: #2a2a2a; }
        .char-slot.empty { color: #888; }
        .char-slot-name { font-weight: bold; color: #00e676; }
        .char-slot-class { font-size: 12px; color: #bbb; }
        
        /* --- TOAST NOTIFICATIONS --- */
        #toast-container {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            display: flex; flex-direction: column; gap: 10px; z-index: 10000;
            pointer-events: none;
        }
        .toast {
            background: rgba(20, 20, 20, 0.95); border: 1px solid #333; border-left: 4px solid #00e676;
            color: #fff; padding: 12px 24px; border-radius: 4px; min-width: 250px; text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5); font-size: 14px;
            animation: slideUp 0.3s ease-out forwards; pointer-events: auto;
        }
        .toast.error { border-left-color: #f44336; }
        .toast.big { font-size: 16px; padding: 20px 40px; border-left-width: 6px; font-weight: bold; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* --- SMALL MODAL (INPUT) --- */
        .small-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 3000; display: none; align-items: center; justify-content: center; }
        .small-modal-content { background: #1e1e1e; padding: 25px; border-radius: 8px; border: 1px solid #333; width: 300px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        .small-modal input { width: 100%; padding: 10px; margin: 15px 0; background: #252525; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; }

        /* --- LOADING SCREEN --- */
        #loading-screen { z-index: 9999; background: #000; }
        .loading-bar-bg { width: 300px; height: 24px; background: #222; border: 2px solid #444; border-radius: 12px; overflow: hidden; margin-top: 20px; position: relative; box-shadow: 0 0 15px rgba(0,0,0,0.5); }
        .loading-bar-fill { height: 100%; background: #2196f3; width: 0%; transition: width 0.2s linear; box-shadow: 0 0 10px #2196f3; }

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
        .top-left-ui .world-text { color: #aaa; font-size: 12px; }
        .top-left-ui #world-info { color: white; font-weight: bold; font-size: 14px; }
        .top-left-ui .online-btn { padding: 5px 10px; font-size: 12px; background: rgba(0,0,0,0.6); border: 1px solid #444; margin:0; min-width:120px; display:flex; justify-content:space-between; }

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
                transition: bottom 0.3s ease-in-out;
                bottom: calc(35vh + 20px + env(safe-area-inset-bottom)) !important;
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
                left: auto !important;
                right: 20px !important;
                bottom: calc(35vh + 20px + env(safe-area-inset-bottom)) !important;
                padding: 5px 8px;
                font-size: 10px;
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
                left: 10px !important;
                right: auto !important;
                bottom: calc(10px + env(safe-area-inset-bottom)) !important;
                position: fixed;
                z-index: 3100;
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

            #world-btn {
                top: 10px !important;
                right: 320px !important;
                left: auto !important;
                bottom: auto !important;
                position: fixed;
                z-index: 3100;
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
                z-index: 3100;
            }
        }
    </style>
</head>
<body>

<div id="auth-modal" class="auth-modal">
    <div class="auth-form">
        <h2 id="auth-title">Login</h2>
        <div id="login-form">
            <input type="text" id="login-username" placeholder="Username">
            <input type="password" id="login-password" placeholder="Password">
            <label style="display:flex; align-items:center; gap:8px; margin:10px 0; color:#bbb; font-size:12px;">
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

<div id="create-char-modal" class="small-modal">
    <div class="small-modal-content">
        <h3 style="margin-top:0; color:#00e676;">Create Character</h3>
        <input type="text" id="new-char-name" placeholder="Character Name" maxlength="15">
        <div style="display:flex; gap:10px; justify-content:center;">
            <button class="combat-btn" style="background:#444; font-size:12px;" onclick="document.getElementById('create-char-modal').style.display='none'">Cancel</button>
            <button class="combat-btn" style="background:#00e676; color:black; font-size:12px;" onclick="submitNewCharacter()">Create</button>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<div id="char-selection-modal" class="modal">
    <div class="char-selection">
        <h2>Select Character</h2>
        <div class="char-slots" id="char-slots-container"></div>
        <button class="combat-btn" style="width:100%; margin-top:20px;" onclick="document.getElementById('char-selection-modal').style.display='none'">Close</button>
    </div>
</div>

<div id="settings-modal" class="modal">
    <div class="modal-panel" style="background:#1b1b1b; padding:30px; border-radius:8px; width:300px; text-align:center; position:relative;">
        <button class="icon-btn" style="position:absolute; top:10px; right:10px;" onclick="toggleSettings()">
            <img src="assets/ui/ex.png" alt="Close">
        </button>
        
        <h2 style="color:#00e676; margin-top:0;">Settings</h2>
        
        <div style="margin:20px 0; background:#252525; padding:15px; border-radius:5px;">
            <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:10px;">
                <img src="assets/ui/music.png" style="width:24px; height:24px;">
                <span style="color:#ccc;">Music</span>
            </div>
            <div style="display:flex; align-items:center; justify-content:center; gap:15px;">
                <button class="icon-btn" onclick="playMusic()"><img src="assets/ui/play.png" alt="Play"></button>
                <button class="icon-btn" onclick="stopMusic()"><img src="assets/ui/ex.png" alt="Stop"></button>
            </div>
            <input type="range" min="0" max="1" step="0.1" value="0.2" oninput="setVolume(this.value)" style="width:100%; margin-top:10px;">
            
            <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-top:15px; margin-bottom:5px;">
                <span style="color:#ccc;">Sounds</span>
            </div>
            <input type="range" min="0" max="1" step="0.1" value="0.3" oninput="setSfxVolume(this.value)" style="width:100%;">
        </div>

        <button class="combat-btn" style="width:100%; background:#2196f3; margin:5px 0;" onclick="changeCharacter()">Change Character</button>
        <button class="combat-btn" style="width:100%; background:#d32f2f; margin:5px 0;" onclick="handleLogout()">Logout</button>
    </div>
</div>

<div id="start-screen" class="modal" style="display:flex;">
    <h1>RPG WORLD</h1>
    <button class="combat-btn" onclick="showAuthModal()">LOGIN</button>
</div>

<div id="game-layout">
    <div id="left-panel">
        <div id="day-night-overlay" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:1500; transition: background-color 5s;"></div>
        
        <div class="top-left-ui">
            <div class="world-text">
                WORLD: <span id="world-info">...</span>
            </div>
            <div style="position:relative;">
                <button class="combat-btn online-btn" onclick="togglePlayerList()">
                    <span><span style="color:#00e676;">●</span> <span id="online-count-val" style="color:white;">1</span> Online</span> <span>▼</span>
                </button>
                <div id="online-list-dropdown" style="display:none; position:absolute; top:100%; left:0; width: 200px; max-height: 300px; overflow-y: auto; background: rgba(20, 20, 20, 0.95); border: 1px solid #444; border-radius: 4px; margin-top: 5px; padding: 5px;">
                    <!-- List items injected by JS -->
                </div>
            </div>
        </div>

        <button id="world-btn" style="display:none;" onclick="showWorldSelection()">Select World 🌐</button>
        <button id="shop-btn" style="display:none; position:absolute; bottom:20px; left:50%; transform:translateX(-50%); padding:10px 20px; background:gold; color:black; border:none; font-weight:bold; cursor:pointer; border-radius:5px; z-index:1600; box-shadow:0 0 10px #000;" onclick="openCityMenu()">🏰 Enter Market</button>
        <button id="expand-panel-btn" style="display:none; position:absolute; top:55px; right:10px; z-index:1600; background:rgba(0,0,0,0.6); color:white; border:1px solid #444; padding:8px 12px; cursor:pointer; font-weight:bold; border-radius:4px;" onclick="toggleRightPanel()" title="Show Panel (Tab)">«</button>

        <div id="map"></div>
    </div>

    <div id="right-panel">
        <div style="padding:15px; border-bottom:1px solid #333; display:flex; justify-content:space-between; align-items:center;">
            <button class="icon-btn" onclick="toggleRightPanel()" title="Hide Panel (Tab)" style="font-weight:bold; color:#888; font-size:18px;">»</button>
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
        </div>

        <div id="tab-stats" class="tab-content active">
            <h2 id="class-name" style="margin:0;">Character</h2>
            <div style="font-size:12px; color:#888; margin-bottom:20px;">Level <span id="lvl">1</span></div>
            <div>Health: <span id="hp">100 / 100</span></div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill" id="hp-fill" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
            <div style="margin-top:15px;">Energy: <span id="energy">10 / 10</span></div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill energy" id="en-fill" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
            <div style="text-align:right; font-size:11px; color:#666;">Steps: <span id="steps-info">0/10</span></div>
            <div style="margin-top:15px;">XP: <span id="xp-text">0 / 100</span></div>
            <div class="big-bar-widget">
                <div class="hb-fill-wrapper"><div class="hb-fill xp" id="xp-fill" style="width:100%"></div></div>
                <div class="hb-left"></div>
                <div class="hb-middle"></div>
                <div class="hb-right"></div>
            </div>
            <div style="margin-top:15px;">Gold: <span id="gold-val" style="color:gold; font-weight:bold;">0</span> G</div>
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
    <p id="combat-log" style="color:#bbb; margin-top:15px; font-style:italic; height:20px;">Waiting...</p>
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
            <button class="tab-btn active" onclick="loadShop('leathersmith', this)">Leathersmith</button>
            <button class="tab-btn" onclick="loadShop('blacksmith', this)">Blacksmith</button>
            <button class="tab-btn" onclick="loadShop('armorer', this)">Armorer</button>
            <button class="tab-btn" onclick="loadShop('clergy', this)">Clergy</button>
            <button class="tab-btn" onclick="loadSellTab(this)">Sell Loot</button>
        </div>
        <div id="shop-content" style="flex:1; padding:15px; overflow-y:auto; color:#ccc;">Select a merchant...</div>
        <div style="padding:10px; border-top:1px solid #333; text-align:right; font-weight:bold; color:gold;">Your Gold: <span id="shop-gold">0</span> G</div>
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

<script src="game.js?v=20260211_01"></script>
</body>
</html>