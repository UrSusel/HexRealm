const mapSize = 20; 
let latestTileCache = [];
let playerMarker = document.createElement('div');
playerMarker.classList.add('player');

let HEX_WIDTH = 150;   
let HEX_HEIGHT = 44;

// --- CLASS MAPPING ---
const CLASS_NAMES = {
    1: 'Warrior',
    2: 'Mage',
    3: 'Rogue'
}; 

// --- SHOP STATE ---
window.currentShopType = 'leathersmith'; // Default shop type
window.currentShopClass = null; // No class filter by default

let gameState = {
    x: 0, y: 0, 
    hp: 100, max_hp: 100,
    energy: 10, max_energy: 10,
    xp: 0, max_xp: 100,
    steps_buffer: 0, 
    enemy_hp: 0, enemy_max_hp: 100,
    in_combat: false,
    tutorial_completed: false,
    is_pvp: false,
    gold: 0,
    stat_points: 0, base_attack: 1, base_defense: 0,
    attack: 1, defense: 0
};

let inCombatMode = false;
let combatState = null;
let lowEnergyWarningShown = false;
let isProcessingTurn = false;
let combatCameraState = { x: 0, y: 0, scale: 1, initialized: false };
let mapScaleCache = { w: 0, h: 0, portrait: null, landscape: null };
let isMobile = Math.min(window.innerWidth, window.innerHeight) <= 900;

// --- AUDIO ---
const AUDIO_PATHS = {
    walk: Array.from({length: 8}, (_, i) => `assets/walking/stepdirt_${i+1}.wav`),
    hit: ['assets/combat/damage/Hit 1.wav', 'assets/combat/damage/Hit 2.wav'],
    damage: Array.from({length: 10}, (_, i) => `assets/combat/damage/damage_${i+1}_ian.wav`),
    combatMusic: "assets/combat/If It's a Fight You Want.ogg"
};

const playlist = [
    'assets/Journey Across the Blue.ogg',
    'assets/World Travelers.ogg',
    'assets/Origins.ogg',
    'assets/Smooth As Glass.ogg',
    "assets/We're Bird People Now.ogg"
];
let explorationAudio = new Audio();
explorationAudio.crossOrigin = 'anonymous';
let combatAudio = new Audio(AUDIO_PATHS.combatMusic);
combatAudio.crossOrigin = 'anonymous';
let waitingAudio = new Audio('img/Waiting.ogg');
waitingAudio.crossOrigin = 'anonymous';
combatAudio.loop = true;
waitingAudio.loop = true;
let isPlaying = false;
let userStoppedMusic = false;
explorationAudio.volume = 0.15;
combatAudio.volume = 0.15;
waitingAudio.volume = 0.15;
let sfxVolume = 0.3;
let currentTrackIndex = -1;
let loopCurrentTrack = false;
let allowWorldBypass = false;
let windActive = false;
let windTimer = null;

let stepInterval = null;
const playerSprites = {
    idle: ['assets/player/idle1.png?v=2', 'assets/player/idle2.png?v=2', 'assets/player/idle3.png?v=2','assets/player/idle4.png?v=2', 'assets/player/idle5.png?v=2', 'assets/player/idle6.png?v=2','assets/player/idle7.png?v=2', 'assets/player/idle8.png?v=2', 'assets/player/idle9.png?v=2'],
    run: ['assets/player/run1.png', 'assets/player/run2.png', 'assets/player/run3.png', 'assets/player/run4.png', 'assets/player/run5.png', 'assets/player/run6.png']
};

let currentAnimState = 'idle';
let currentFrameIndex = 0;
let animationInterval;
let moveTimeout = null;
const ANIMATION_SPEED = 100; // Animation frame speed stays the same
const MOVEMENT_SPEED_PX = 300; // Doubled to match the doubled distance
let combatAnimInterval = null;
let combatFrameIndex = 0;
let pvpActionInFlight = false;
let pvpPollInFlight = false;
let pvpLastActionAt = 0;
const PVP_ACTION_COOLDOWN_MS = 250;

let skillCatalog = [];
let unlockedSkillIds = [];
let selectedSkillIds = [];
let lastSkillUnlockToastLevel = 0;
let skillUnlockMenuOpen = false;
let unlockableSkillIds = [];
let skillResetCostCopper = 0;

let discoveredTiles = {}; // { world_id: { "x_y": "type" } }

function loadDiscoveredTiles() {
    if (!gameState || !gameState.id) return;
    try {
        const saved = localStorage.getItem('rpg_map_' + gameState.id);
        if (saved) discoveredTiles = JSON.parse(saved);
        else discoveredTiles = {};
    } catch (e) {
        discoveredTiles = {};
    }
}

function saveDiscoveredTiles() {
    if (!gameState || !gameState.id) return;
    try {
        localStorage.setItem('rpg_map_' + gameState.id, JSON.stringify(discoveredTiles));
    } catch (e) {}
}

function updateDiscoveredTiles(tiles) {
    if (!gameState || !gameState.world_id) return;
    const wid = gameState.world_id;
    if (!discoveredTiles[wid]) discoveredTiles[wid] = {};
    let changed = false;
    tiles.forEach(t => {
        const key = `${t.x}_${t.y}`;
        if (discoveredTiles[wid][key] !== t.type) {
            discoveredTiles[wid][key] = t.type;
            changed = true;
        }
    });
    if (changed) saveDiscoveredTiles();
}

const GRAPHICS_PRESET_KEY = 'rpg_graphics_preset';
const GRAPHICS_SHADOW_STRENGTH_KEY = 'rpg_graphics_shadow_strength';
let shadowStrength = 0.6;
const lightDirection = {x: 0.45, y: -1};

const GRAPHICS_PRESETS = {
    very_low: {
        label: 'Very Low',
        windEnabled: false,
        windDelayMin: 600,
        windDelayMax: 1200,
        windBurstMin: 0,
        windBurstMax: 0,
        smokeParticlesPerCity: 0,
        combatParticleCount: 0,
        floatingDamageEnabled: false,
        dayNightEnabled: false,
        multiplayerPollMs: 4000,
        animationSpeedMs: 220,
        otherPlayersAnimEnabled: false
    },
    low: {
        label: 'Low',
        windEnabled: false,
        windDelayMin: 450,
        windDelayMax: 950,
        windBurstMin: 0,
        windBurstMax: 0,
        smokeParticlesPerCity: 0,
        combatParticleCount: 0,
        floatingDamageEnabled: false,
        dayNightEnabled: false,
        multiplayerPollMs: 3000,
        animationSpeedMs: 170,
        otherPlayersAnimEnabled: false
    },
    medium: {
        label: 'Medium',
        windEnabled: true,
        windDelayMin: 280,
        windDelayMax: 750,
        windBurstMin: 1,
        windBurstMax: 3,
        smokeParticlesPerCity: 1,
        combatParticleCount: 6,
        floatingDamageEnabled: true,
        dayNightEnabled: true,
        dynamicLightingEnabled: false,
        shadingEnabled: false,
        shadowEnabled: false,
        multiplayerPollMs: 2000,
        animationSpeedMs: 125,
        otherPlayersAnimEnabled: true
    },
    high: {
        label: 'High',
        windEnabled: true,
        windDelayMin: 150,
        windDelayMax: 500,
        windBurstMin: 2,
        windBurstMax: 7,
        smokeParticlesPerCity: 3,
        combatParticleCount: 12,
        floatingDamageEnabled: true,
        dayNightEnabled: true,
        dynamicLightingEnabled: true,
        shadingEnabled: false,
        shadowEnabled: false,
        multiplayerPollMs: 1500,
        animationSpeedMs: 100,
        otherPlayersAnimEnabled: true
    },
    max: {
        label: 'Max',
        windEnabled: true,
        windDelayMin: 120,
        windDelayMax: 350,
        windBurstMin: 3,
        windBurstMax: 9,
        smokeParticlesPerCity: 4,
        combatParticleCount: 18,
        floatingDamageEnabled: true,
        dayNightEnabled: true,
        dynamicLightingEnabled: true,
        shadingEnabled: true,
        shadowEnabled: true,
        multiplayerPollMs: 1000,
        animationSpeedMs: 80,
        otherPlayersAnimEnabled: true
    }
};

let graphicsPreset = 'high';

function isValidGraphicsPreset(value) {
    return value === 'auto' || Boolean(GRAPHICS_PRESETS[value]);
}

function detectAutoGraphicsPreset() {
    const cores = parseInt(navigator.hardwareConcurrency || 4, 10);
    let memory = parseFloat(navigator.deviceMemory || 8);
    if (!Number.isFinite(memory) || memory <= 0) memory = 8;
    const dpr = Math.max(1, parseFloat(window.devicePixelRatio || 1));
    const pixels = Math.max(1, window.innerWidth * window.innerHeight * dpr * dpr);

    const isVeryLow = isMobile || cores <= 2 || memory <= 3;
    const isLow = cores <= 4 || memory <= 5;
    const isHigh = cores >= 6 && memory >= 8;
    const isUltra = cores >= 8 && memory >= 12;

    if (isVeryLow || pixels > 10000000) return 'very_low';
    if (isLow || pixels > 7000000) return 'low';
    if (isUltra) return 'max';
    if (isHigh) return 'high';
    return 'medium';
}

function resolveGraphicsPresetName(preset = graphicsPreset) {
    if (preset === 'auto') return detectAutoGraphicsPreset();
    if (GRAPHICS_PRESETS[preset]) return preset;
    return 'high';
}

function getGraphicsConfig() {
    return GRAPHICS_PRESETS[resolveGraphicsPresetName()] || GRAPHICS_PRESETS.high;
}

function getGraphicsPresetDescription() {
    const cfg = getGraphicsConfig();
    const resolved = resolveGraphicsPresetName();
    if (graphicsPreset === 'auto') {
        const resolvedLabel = GRAPHICS_PRESETS[resolved]?.label || resolved;
        return `Auto: selected ${resolvedLabel} for this device.`;
    }
    if (graphicsPreset === 'very_low') {
        return `${cfg.label}: minimum effects, reduced render distance, maximum performance.`;
    }
    if (graphicsPreset === 'low') {
        return `${cfg.label}: minimal effects, slower multiplayer updates, best performance.`;
    }
    if (graphicsPreset === 'medium') {
        return `${cfg.label}: balanced quality and performance.`;
    }
    if (graphicsPreset === 'high') {
        return `${cfg.label}: high details, shading and effects enabled.`;
    }
    if (graphicsPreset === 'max') {
        return `${cfg.label}: maximum quality with dynamic lighting and shadows.`;
    }
    return `${cfg.label}: full effects and smoothest visuals.`;
}

function loadGraphicsPreset() {
    try {
        const saved = localStorage.getItem(GRAPHICS_PRESET_KEY);
        if (saved && isValidGraphicsPreset(saved)) {
            graphicsPreset = saved;
        }
    } catch {}
}

function loadGraphicsLightingSettings() {
    try {
        const savedStrength = localStorage.getItem(GRAPHICS_SHADOW_STRENGTH_KEY);
        if (savedStrength !== null) {
            shadowStrength = Math.max(0.1, Math.min(1, parseFloat(savedStrength) || 0.6));
        }
    } catch {}
}

function saveGraphicsLightingSettings() {
    try {
        localStorage.setItem(GRAPHICS_SHADOW_STRENGTH_KEY, String(shadowStrength));
    } catch {}
}

function getLightDirectionVector() {
    return lightDirection;
}

function syncLightingSettingsUI() {
    const strengthRange = document.getElementById('shadow-strength-range');
    if (strengthRange) strengthRange.value = String(Math.round(shadowStrength * 100));

    const strengthLabel = document.getElementById('shadow-strength-value');
    if (strengthLabel) strengthLabel.innerText = `${Math.round(shadowStrength * 100)}%`;
}

function setShadowStrength(val) {
    const value = Math.max(0.1, Math.min(1, Number(val) / 100));
    shadowStrength = value;
    saveGraphicsLightingSettings();
    syncLightingSettingsUI();
    renderMapTiles(latestTileCache || []);
}

function setLightDirection(value) {
    // no-op; direction is fixed
}

function stopWindEffect() {
    windActive = false;
    if (windTimer) {
        clearTimeout(windTimer);
        windTimer = null;
    }
    const layer = document.getElementById('wind-layer');
    if (layer) {
        layer.querySelectorAll('.wind-streak').forEach(el => el.remove());
    }
}

function applyGraphicsPreset(preset, save = true) {
    if (!isValidGraphicsPreset(preset)) return;
    graphicsPreset = preset;

    if (save) {
        try {
            localStorage.setItem(GRAPHICS_PRESET_KEY, graphicsPreset);
        } catch {}
    }

    const cfg = getGraphicsConfig();
    if (cfg.windEnabled) {
        if (gameState.world_id === 5) {
            stopWindEffect();
            startSnowEffect();
        } else {
            stopSnowEffect();
            startWindEffect();
        }
    } else {
        stopWindEffect();
        stopSnowEffect();
    }

    if ((cfg.smokeParticlesPerCity || 0) <= 0) {
        document.querySelectorAll('.smoke-particle').forEach(el => el.remove());
    }

    updateDayNightCycle();

    if (latestTileCache && latestTileCache.length > 0) {
        renderMapTiles(latestTileCache);
    }

    if (animationInterval) startPlayerAnimation();
    if (combatAnimInterval) startCombatAnimations();
    if (updatePlayersInterval) startMultiplayerPolling();

    syncGraphicsSettingsUI();
    syncLightingSettingsUI();
}

function setGraphicsPreset(value) {
    if (!isValidGraphicsPreset(value)) return;
    applyGraphicsPreset(value, true);
}

function syncGraphicsSettingsUI() {
    const select = document.getElementById('graphics-preset-select');
    if (select) select.value = graphicsPreset;
    const desc = document.getElementById('graphics-preset-desc');
    if (desc) desc.innerText = getGraphicsPresetDescription();
}

function playSoundEffect(category, damageValue = 0) {
    let src = '';
    if (category === 'walk') { src = AUDIO_PATHS.walk[Math.floor(Math.random() * AUDIO_PATHS.walk.length)]; } 
    else if (category === 'hit') { src = AUDIO_PATHS.hit[Math.floor(Math.random() * AUDIO_PATHS.hit.length)]; } 
    else if (category === 'damage') { let index = Math.ceil(damageValue / 2); if (index < 1) index = 1; if (index > 10) index = 10; src = AUDIO_PATHS.damage[index - 1]; }
    if (src) { const sfx = new Audio(src); sfx.volume = sfxVolume; sfx.play().catch(() => {}); }
}

function startWalkingSound() { if (stepInterval) return; playSoundEffect('walk'); stepInterval = setInterval(() => { playSoundEffect('walk'); }, 400); }
function stopWalkingSound() { if (stepInterval) { clearInterval(stepInterval); stepInterval = null; } }

let tutorialCloudHidden = false;

window.hideTutorialCloud = function() {
    tutorialCloudHidden = true;
    const cloud = document.getElementById('tutorial-cloud');
    if (cloud) cloud.style.display = 'none';
};

function initTutorialCloud() {
    let cloud = document.getElementById('tutorial-cloud');
    if (!cloud) {
        cloud = document.createElement('div');
        cloud.id = 'tutorial-cloud';
        cloud.style.cssText = 'position:fixed; top:120px; left:50%; transform:translateX(-50%); background:rgba(20,20,20,0.95); border:2px solid #ffd700; padding:15px; border-radius:8px; color:white; z-index:999999; text-align:center; max-width:400px; display:none; pointer-events:none; box-shadow:0 4px 15px rgba(0,0,0,0.8), 0 0 10px rgba(255,215,0,0.3); font-size:14px; line-height:1.4; transition: opacity 0.3s;';
        document.body.appendChild(cloud);
    }
}

function updateTutorialCloud() {
    const cloud = document.getElementById('tutorial-cloud');
    if (!cloud) return;

    const startScreen = document.getElementById('start-screen');
    if (!startScreen || startScreen.style.display !== 'none') {
        cloud.style.display = 'none';
        return;
    }

    if (tutorialCloudHidden || gameState.tutorial_completed || gameState.world_id !== 1) {
        cloud.style.display = 'none';
        return;
    }

    cloud.style.display = 'block';

    const closeBtn = '<img src="assets/ui/ex.png" onclick="hideTutorialCloud()" style="pointer-events:auto; position:absolute; top:8px; right:8px; width:20px; height:20px; cursor:pointer; transition: transform 0.1s; filter:drop-shadow(0 0 2px #000);" onmouseover="this.style.transform=\'scale(1.2)\'" onmouseout="this.style.transform=\'scale(1)\'">';

    if (gameState.in_combat) {
        cloud.innerHTML = closeBtn + '<strong style="color:#ffd700; font-size:16px;">⚔️ Combat Tutorial</strong><br><br><span style="color:#aaffaa;"><b>Move:</b></span> Click a nearby tile (costs AP)<br><span style="color:#ffaaaa;"><b>Attack:</b></span> Click the Enemy (costs 2 AP)<br><span style="color:#aaaaff;"><b>Skills:</b></span> Use buttons below (costs 1 AP)<br><br>Defeat the monster to complete the tutorial!';
    } else {
        const isSafeZone = isPlayerInSettlement();
        if (isSafeZone) {
            cloud.innerHTML = closeBtn + '<strong style="color:#ffd700; font-size:16px;">🗺️ Exploration Tutorial</strong><br><br>Welcome to HexRealm!<br>You are currently in a <span style="color:#aaffaa;">Safe Zone (City)</span>.<br><br>Click adjacent tiles to move.<br>Moving in the wild costs <span style="color:#ffffaa;">Energy</span> and can trigger <b>Monster Ambushes</b>.<br><br>Find and defeat a monster to proceed!';
        } else {
            cloud.innerHTML = closeBtn + '<strong style="color:#ffd700; font-size:16px;">🌲 Wilderness</strong><br><br>You are in the wild!<br>Keep moving to explore. Every step costs Energy.<br>Watch out for <b>Monster Ambushes</b>!<br><br>Defeat a monster to complete the tutorial.';
        }
    }
}

// --- START ---

async function startGame() {
    document.getElementById('start-screen').style.display = 'none';
    document.getElementById('game-layout').style.display = 'flex';
    initTutorialCloud();
    applyResponsiveStyles();
    injectButtonSizeStyles();
    stopWaitingMusic();
    
    if (!userStoppedMusic) {
        isPlaying = true;
    }
    playRandomTrack();
    
    // Inject Gold Display if missing
    if (!document.getElementById('gold-display')) {
        const statsPanel = document.getElementById('left-panel');
        if (statsPanel) {
            const goldDiv = document.createElement('div');
            goldDiv.id = 'gold-display';
            goldDiv.style.cssText = "font-size:18px; color:gold; margin:10px 0; font-weight:bold; text-shadow:1px 1px 0 #000;";
            goldDiv.innerHTML = formatCoins(0, true);
            const xpContainer = document.getElementById('xp-container');
            if (xpContainer) xpContainer.parentNode.insertBefore(goldDiv, xpContainer.nextSibling);
            else statsPanel.prepend(goldDiv);
        }
    }

    // Ensure a single shop button exists (avoid duplicate IDs on mobile)
    if (!document.getElementById('shop-btn')) {
        const shopBtn = document.createElement('button');
        shopBtn.id = 'shop-btn';
        shopBtn.innerText = "🏰 Enter Market";
        shopBtn.style.cssText = "padding:10px 20px; background:gold; color:black; border:none; font-weight:bold; cursor:pointer; border-radius:5px; display:none; box-shadow:0 0 10px #000;";
        shopBtn.onclick = () => openCityMenu();
        document.body.appendChild(shopBtn);
    }
    
    if (!document.getElementById('minimap-btn')) {
        const minimapBtn = document.createElement('button');
        minimapBtn.id = 'minimap-btn';
        minimapBtn.innerText = "🗺️ Map";
        minimapBtn.style.cssText = "position:fixed; bottom:20px; left:20px; padding:12px 20px; background:linear-gradient(180deg, #2c3e50, #1a252f); color:#ecf0f1; border:2px solid #34495e; font-weight:bold; cursor:pointer; border-radius:5px; z-index:1000; box-shadow:0 4px 10px rgba(0,0,0,0.8); transition: transform 0.1s;";
        minimapBtn.onmouseover = () => minimapBtn.style.transform = 'scale(1.05)';
        minimapBtn.onmouseout = () => minimapBtn.style.transform = 'scale(1)';
        minimapBtn.onclick = () => toggleMinimap();
        const leftPanel = document.getElementById('left-panel');
        if (leftPanel) {
            leftPanel.appendChild(minimapBtn);
        } else {
            document.body.appendChild(minimapBtn);
        }
    }

    const btn = document.getElementById('music-btn');
    if(btn) { btn.innerText = '🔊'; btn.classList.add('playing'); }
    
    // Show mobile portrait disclaimer on mobile devices
    if (isMobile) {
        const disclaimer = document.getElementById('mobile-disclaimer-modal');
        if (disclaimer) {
            disclaimer.style.display = 'flex';
        }
    }
    
    loadGraphicsLightingSettings();
    applyGraphicsPreset(graphicsPreset, false);
    syncLightingSettingsUI();
    await initGame();
    updateDayNightCycle();
    setInterval(updateDayNightCycle, 60000);
}

function startWindEffect() {
    const cfg = getGraphicsConfig();
    if (!cfg.windEnabled || gameState.world_id === 5) {
        stopWindEffect();
        return;
    }
    if (windActive) return;
    const layer = document.getElementById('wind-layer');
    if (!layer) return;
    windActive = true;
    scheduleWind();
}

function scheduleWind() {
    const cfg = getGraphicsConfig();
    if (!windActive || !cfg.windEnabled) return;
    const delayRange = Math.max(0, cfg.windDelayMax - cfg.windDelayMin);
    const delay = cfg.windDelayMin + Math.random() * delayRange;
    windTimer = setTimeout(() => {
        if (!document.hidden) {
            const burstMax = Math.max(cfg.windBurstMin, cfg.windBurstMax);
            const burstCount = cfg.windBurstMin + Math.floor(Math.random() * (burstMax - cfg.windBurstMin + 1));
            for (let i = 0; i < burstCount; i++) {
                setTimeout(() => createWindStreak(), i * 30);
            }
        }
        scheduleWind();
    }, delay);
}

function createWindStreak() {
    if (!getGraphicsConfig().windEnabled) return;
    const layer = document.getElementById('wind-layer');
    if (!layer || document.body.classList.contains('combat-active')) return;
    if (layer.childElementCount > 40) return; // Prevent lag from too many particles
    const rect = layer.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) return;

    const margin = 30;
    // Wind can start from different positions (left or middle of screen)
    const startFromLeft = Math.random() > 0.3; // 70% from left, 30% from middle
    const startX = startFromLeft ? -margin : (rect.width * 0.3 + Math.random() * (rect.width * 0.3));
    const startY = Math.random() * rect.height;
    
    // Wind travels variable distance - not always to the end
    const minDx = rect.width * 0.4; // Minimum 40% across screen
    const maxDx = rect.width + margin * 3; // Maximum past the edge
    const dx = minDx + Math.random() * (maxDx - minDx);
    const dy = (Math.random() * 28) - 14; // Slightly more vertical variation

    const streak = document.createElement('div');
    streak.className = 'wind-streak';
    const width = 60 + Math.random() * 180; // Slightly wider streaks
    const height = 1 + Math.floor(Math.random() * 4); // More thickness variety
    const duration = (1.2 + Math.random() * 2.8).toFixed(2) + 's'; // Variable speed
    const opacity = (0.15 + Math.random() * 0.35).toFixed(2); // More opacity variety
    const angle = Math.atan2(dy, dx) * (180 / Math.PI);

    streak.style.left = `${startX}px`;
    streak.style.top = `${startY}px`;
    streak.style.width = `${width}px`;
    streak.style.height = `${height}px`;
    streak.style.opacity = opacity;
    streak.style.setProperty('--dx', `${dx}px`);
    streak.style.setProperty('--dy', `${dy}px`);
    streak.style.setProperty('--dur', duration);
    streak.style.setProperty('--rot', `${angle}deg`);

    layer.appendChild(streak);
    streak.addEventListener('animationend', () => streak.remove(), { once: true });
}

let snowActive = false;
let snowTimer = null;

function startSnowEffect() {
    const cfg = getGraphicsConfig();
    if (!cfg.windEnabled || gameState.world_id !== 5) {
        stopSnowEffect();
        return;
    }
    if (snowActive) return;
    const layer = document.getElementById('snow-layer');
    if (!layer) return;
    snowActive = true;
    scheduleSnow();

    const vignette = document.getElementById('frost-vignette');
    if (vignette) vignette.classList.add('active');
}

function scheduleSnow() {
    const cfg = getGraphicsConfig();
    if (!snowActive || !cfg.windEnabled || gameState.world_id !== 5) return;
    
    if (!document.hidden) {
        const count = cfg.windBurstMax * 2; 
        for (let i = 0; i < count; i++) {
            setTimeout(createSnowflake, Math.random() * 500);
        }
    }
    
    snowTimer = setTimeout(scheduleSnow, 500);
}

function createSnowflake() {
    if (!getGraphicsConfig().windEnabled || gameState.world_id !== 5) return;
    const layer = document.getElementById('snow-layer');
    if (!layer || document.body.classList.contains('combat-active')) return;
    if (layer.childElementCount > 100) return; // Prevent lag from too many particles
    const rect = layer.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) return;

    const startX = -150 + Math.random() * (rect.width + 150);
    const startY = -20;
    
    const size = 3 + Math.random() * 4;
    const duration = 2.5 + Math.random() * 3.5;
    
    // Dynamic wind that sways direction slowly over time
    const timeFactor = Date.now() / 4000;
    const dx = (Math.sin(timeFactor) * 120) + (Math.random() * 50 - 25); 
    const dy = rect.height + 50;

    const flake = document.createElement('div');
    flake.className = 'snowflake';
    flake.style.left = `${startX}px`;
    flake.style.top = `${startY}px`;
    flake.style.width = `${size}px`;
    flake.style.height = `${size}px`;
    flake.style.setProperty('--dx', `${dx}px`);
    flake.style.setProperty('--dy', `${dy}px`);
    flake.style.setProperty('--dur', `${duration}s`);
    flake.style.setProperty('--max-op', `${0.3 + Math.random() * 0.6}`);

    layer.appendChild(flake);
    flake.addEventListener('animationend', () => flake.remove(), { once: true });
}

function stopSnowEffect() {
    snowActive = false;
    if (snowTimer) {
        clearTimeout(snowTimer);
        snowTimer = null;
    }
    const layer = document.getElementById('snow-layer');
    if (layer) {
        layer.querySelectorAll('.snowflake').forEach(el => el.remove());
    }

    const vignette = document.getElementById('frost-vignette');
    if (vignette) vignette.classList.remove('active');
}

async function initGame() {
    try {
        const res = await fetch('api.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_state' }) 
        });
        const json = await res.json();
        
        if (json.status === 'success') {
            if (json.data.class_id === null) {
                document.getElementById('class-selection').style.display = 'flex';
                return;
            }
            document.getElementById('class-selection').style.display = 'none';

            updateLocalState(json.data);
            selectedSkillIds = loadSelectedSkillsFromStorage();
            
            loadDiscoveredTiles();
            
            const cfg = getGraphicsConfig();
            if (cfg.windEnabled) {
                if (gameState.world_id === 5) {
                    stopWindEffect();
                    startSnowEffect();
                } else {
                    stopSnowEffect();
                    startWindEffect();
                }
            } else {
                stopWindEffect();
                stopSnowEffect();
            }
            
            // Ensure player has all skills they should have based on level
            const ensureSkillsRes = await apiPost('ensure_skills_unlocked');
            if (ensureSkillsRes.status === 'success' && Array.isArray(ensureSkillsRes.unlocked_skill_ids)) {
                unlockedSkillIds = ensureSkillsRes.unlocked_skill_ids;
            }
            if (ensureSkillsRes.status === 'success' && Array.isArray(ensureSkillsRes.unlockable_skill_ids)) {
                unlockableSkillIds = ensureSkillsRes.unlockable_skill_ids;
            }
            if (ensureSkillsRes.status === 'success' && ensureSkillsRes.reset_cost_copper !== undefined) {
                skillResetCostCopper = parseInt(ensureSkillsRes.reset_cost_copper, 10) || 0;
            }
            
            // Only load skill catalog if not already loaded (saves a request)
            if (!Array.isArray(skillCatalog) || skillCatalog.length === 0) {
                await ensureSkillCatalogLoaded();
            } else {
                // Reload unlocked skills to stay in sync with backend
                await ensureSkillCatalogLoaded();
            }
            refreshSelectedSkillsByLevel();
            
            // UI Świata
            document.getElementById('world-info').innerText = json.data.world_name || 'Unknown world';
            updateUI(json.data);
            maybeNotifySkillUnlock();
            checkTutorialStatus();

            if (gameState.in_combat && json.data.combat_state) {
                combatState = JSON.parse(json.data.combat_state);
                toggleCombatMode(true, gameState.hp, json.data.enemy_hp);
            } else if (gameState.in_combat && json.data.is_pvp) {
                // Re-enter PvP
                gameState.is_pvp = true;
                toggleCombatMode(true, gameState.hp, 100); // HP will update via poll
                pollPvPState();
            } else {
                await loadAndDrawMap();
                startPlayerAnimation();
                setTimeout(() => { updatePlayerVisuals(gameState.x, gameState.y, true); }, 50);
                startMultiplayerPolling(); // Start polling for other players
                updateShopButtonVisibility();
            }
            renderInventory(json.data.inventory);
            checkLifeStatus();
            updatePlanetChangeButtonVisibility();

            // Load daily quest
            setTimeout(() => { loadDailyQuest(); }, 500);
        } else if (json.code === 'world_missing' || json.allow_world_change) {
            allowWorldBypass = true;
            showToast(json.message || 'World missing. Choose another world.', 'error big', 8000);
            showWorldSelection(true);
        }
    } catch(e) { console.error("Init Error:", e); }
}

// Sprawdza czy guzik ma być widoczny
async function checkTutorialStatus() {
    try {
        const json = await apiPost('get_state');
        if (json.status === 'success') {
            const state = json.data ?? json.state ?? json;
            gameState.tutorial_completed = (state.tutorial_completed == 1 || state.tutorial_completed === true);
            gameState.world_id = parseInt(state.world_id) || 2;
            const btn = document.getElementById('world-btn');
            // Hide world button on planets Solaris (3) and Glaciem (5) since they have no world selection
            const isOnPlanet = gameState.world_id === 3 || gameState.world_id === 5;
            if (btn) btn.style.display = (gameState.tutorial_completed && !isOnPlanet) ? 'inline-block' : 'none';
        } else {
            console.warn('get_state failed', json);
        }
    } catch (e) {
        console.error('checkTutorialStatus error', e);
    }
}
async function showWorldSelection(forceBypass = false) {
    try {
        allowWorldBypass = Boolean(forceBypass) || allowWorldBypass;
        const startTime = performance.now();
        const data = await apiPost('get_worlds_list');
        const pingMs = Math.round(performance.now() - startTime);
        const modal = document.getElementById('world-selection');
        const list = document.getElementById('world-list');
        if (!modal || !list) return;
        list.innerHTML = '';

        if (data.status === 'success' && Array.isArray(data.worlds) && data.worlds.length) {
            let pingColor = '#4caf50'; // Zielony
            if (pingMs > 100) pingColor = '#ff9800'; // Pomarańczowy
            if (pingMs > 250) pingColor = '#f44336'; // Czerwony
            
            data.worlds.forEach(w => {
                const el = document.createElement('div');
                el.className = 'world-item';
                el.style.cursor = 'pointer';
                el.innerHTML = `<strong>${escapeHtml(w.name)}</strong>
                                <div style="font-size:12px;color:#ccc">${w.width}x${w.height} • ${w.player_count} players • <span style="color:${pingColor}">${pingMs}ms</span></div>`;
                el.addEventListener('click', () => joinWorld(parseInt(w.id)));
                list.appendChild(el);
            });
        } else {
            list.innerHTML = '<div style="color:#ccc">No worlds available</div>';
        }

        modal.style.display = 'flex';
    } catch (e) {
        console.error('showWorldSelection error', e);
    }
}
async function joinWorldDebug() {
    try {
        await joinWorld(1);
    } catch (e) {
        showToast('Error fetching world list', 'error');
    }
}

async function joinWorld(worldId) {
    try {
        const isLeavingTutorial = (gameState.world_id === 1);
        const data = await apiPost('join_world', { world_id: worldId, bypass_city: allowWorldBypass });
        if (data.status === 'success') {
            const modal = document.getElementById('world-selection');
            if (modal) modal.style.display = 'none';
            allowWorldBypass = false;
            
            playTransitionOverlay(async () => {
                if (isLeavingTutorial) {
                    window.location.reload();
                } else {
                    if (typeof initGame === 'function') {
                        await initGame();
                        showRespawnEffect();
                    } else window.location.reload();
                }
            });
        } else {
            showToast(data.message || 'Failed to join world', 'error');
        }
    } catch (e) {
        console.error('joinWorld error', e);
        showToast('Server connection error', 'error');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[s]));
}

const COIN_COPPER_PER_SILVER = 100;
const COIN_SILVER_PER_GOLD = 100;
const COIN_COLORS = {
    gold: '#ffd700',
    silver: '#c0c0c0',
    copper: '#b87333'
};

function formatCoins(totalCopper, useHtml = false) {
    const value = Math.max(0, parseInt(totalCopper || 0, 10) || 0);
    const copper = value % COIN_COPPER_PER_SILVER;
    const totalSilver = Math.floor(value / COIN_COPPER_PER_SILVER);
    const silver = totalSilver % COIN_SILVER_PER_GOLD;
    const gold = Math.floor(totalSilver / COIN_SILVER_PER_GOLD);

    const parts = [];
    const wrap = (label, color) => useHtml ? `<span style="color:${color};">${label}</span>` : label;
    if (gold > 0) parts.push(wrap(`${gold} gold coin${gold === 1 ? '' : 's'}`, COIN_COLORS.gold));
    if (silver > 0) parts.push(wrap(`${silver} silver coin${silver === 1 ? '' : 's'}`, COIN_COLORS.silver));
    if (copper > 0 || parts.length === 0) parts.push(wrap(`${copper} copper coin${copper === 1 ? '' : 's'}`, COIN_COLORS.copper));
    return parts.join(' ');
}
async function apiPost(action, body = {}) {
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action }, body))
        });
        const text = await res.text();
        if (!text) {
            console.error('Empty response from server');
            return { status: 'error', message: 'Empty response from server' };
        }
        try {
            return JSON.parse(text);
        } catch (parseError) {
            console.error('JSON Parse Error for action:', action);
            console.error('Response text:', text.substring(0, 500));
            console.error('Response status:', res.status);
            return { status: 'error', message: 'Invalid JSON response' };
        }
    } catch (e) {
        console.error('apiPost error', e);
        return { status: 'error', message: 'Network error' };
    }
}

function getSkillStorageKey() {
    const charId = gameState?.character_id || 'default';
    return `rpg_selected_skills_${charId}`;
}

function getUnlockedSkillCount() {
    return unlockedSkillIds.length;
}

async function ensureSkillCatalogLoaded(forceRefresh = false) {
    if (!forceRefresh && Array.isArray(skillCatalog) && skillCatalog.length > 0) return true;
    const res = await apiPost('get_skill_catalog');
    if (res.status !== 'success' || !Array.isArray(res.skills)) return false;
    skillCatalog = res.skills;
    unlockedSkillIds = Array.isArray(res.unlocked_skill_ids) ? res.unlocked_skill_ids : [];
    unlockableSkillIds = Array.isArray(res.unlockable_skill_ids) ? res.unlockable_skill_ids : [];
    skillResetCostCopper = parseInt(res.reset_cost_copper || 0, 10) || 0;
    return true;
}

function loadSelectedSkillsFromStorage() {
    try {
        const raw = localStorage.getItem(getSkillStorageKey());
        const parsed = raw ? JSON.parse(raw) : [];
        if (!Array.isArray(parsed)) return [];
        return parsed.filter(v => typeof v === 'string').slice(0, 4);
    } catch {
        return [];
    }
}

function saveSelectedSkillsToStorage() {
    localStorage.setItem(getSkillStorageKey(), JSON.stringify(selectedSkillIds.slice(0, 4)));
}

function refreshSelectedSkillsByLevel() {
    const unlockedSet = new Set(unlockedSkillIds);
    selectedSkillIds = selectedSkillIds.filter(id => unlockedSet.has(id)).slice(0, 4);
    saveSelectedSkillsToStorage();
}

function getSelectedSkillsDetailed() {
    if (!Array.isArray(skillCatalog) || skillCatalog.length === 0) return [];
    const byId = new Map(skillCatalog.map(s => [s.id, s]));
    return selectedSkillIds.map(id => byId.get(id)).filter(Boolean);
}

function maybeNotifySkillUnlock() {
    const level = parseInt(gameState.level || 1, 10);
    if (level > 0 && level % 5 === 0 && lastSkillUnlockToastLevel !== level) {
        lastSkillUnlockToastLevel = level;
        showToast(`🎯 Level ${level}! New skill unlock slot available.`, 'success');
    }
}

// Expose single global block for inline handlers — ensure no other block re-exports these later.
window.showWorldSelection = showWorldSelection;
window.joinWorld = joinWorld;

window.respawnPlayer = async function() {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'respawn' })
        });
        document.getElementById('death-screen').style.display = 'none';
        await initGame();
        showRespawnEffect();
    } catch (e) {
        console.error('respawnPlayer error', e);
        showToast("Respawn failed", "error");
    }
};
window.selectClass = async function(id) {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'select_class', class_id: id })
        });
        await initGame();
    } catch (e) {
        console.error('selectClass error', e);
    }
};




async function loadAndDrawMap() {
    const serverPreset = resolveGraphicsPresetName();
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_map', graphics_preset: serverPreset })
    });
    const result = await res.json();
    if (result.tiles) {
        renderMapTiles(result.tiles);
    } else if (result.code === 'world_missing' || result.allow_world_change) {
        allowWorldBypass = true;
        showToast(result.message || 'World missing. Choose another world.', 'error big', 8000);
        showWorldSelection(true);
    }
}

function renderMapTiles(tiles) {
    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;
    const cfg = getGraphicsConfig();
    const smokeCount = Math.max(0, parseInt(cfg.smokeParticlesPerCity || 0, 10));
    const resolvedPreset = resolveGraphicsPresetName();

    const sourceTiles = Array.isArray(tiles) ? tiles : [];
    updateDiscoveredTiles(sourceTiles);
    latestTileCache = sourceTiles;
    const tilesToRender = (resolvedPreset === 'very_low')
        ? sourceTiles.filter(t => {
            const tx = parseInt(t.x, 10);
            const ty = parseInt(t.y, 10);
            const dx = Math.abs(tx - parseInt(gameState.x || 0, 10));
            const dy = Math.abs(ty - parseInt(gameState.y || 0, 10));
            return dx <= 5 && dy <= 7;
        })
        : sourceTiles;

    const existingTiles = new Map();
    mapDiv.querySelectorAll('.tile').forEach(e => {
        existingTiles.set(`${e.dataset.x}_${e.dataset.y}`, e);
    });

    const newKeys = new Set();
    const moveDuration = window.currentMoveDuration || 0;

    const lightSources = [];
    const playerX = parseInt(gameState.x || 0, 10);
    const playerY = parseInt(gameState.y || 0, 10);
    const pOffsetX = (playerY % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
    const pPosX = (playerX * HEX_WIDTH) + pOffsetX;
    const pPosY = (playerY * HEX_HEIGHT);
    lightSources.push({ x: pPosX, y: pPosY, radius: 750 });

    if (cfg.dynamicLightingEnabled) {
        sourceTiles.forEach(st => {
            if (st.type === 'city_capital' || st.type === 'city_village') {
                const tx = parseInt(st.x, 10);
                const ty = parseInt(st.y, 10);
                const tOffX = (ty % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
                lightSources.push({
                    x: (tx * HEX_WIDTH) + tOffX,
                    y: (ty * HEX_HEIGHT),
                    radius: 450
                });
            }
        });
    }

    tilesToRender.forEach(t => { 
        const key = `${t.x}_${t.y}`;
        newKeys.add(key);

        let tile = existingTiles.get(key);
        let isNew = false;

        if (!tile) {
            tile = document.createElement('div');
            tile.className = `tile ${t.type}`;
            
            let offsetX = (parseInt(t.y) % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
            let posX = (parseInt(t.x) * HEX_WIDTH) + offsetX;
            let posY = (parseInt(t.y) * HEX_HEIGHT);
            if (t.type === 'mountain' || t.type === 'wmountain') posY -= 20; 
            if (t.type === 'city_capital') {
                posY -= 5;
                for(let i=0; i<smokeCount; i++) {
                    const s = document.createElement('div');
                    s.className = 'smoke-particle';
                    s.style.left = '60px'; s.style.top = '40px'; 
                    s.style.animationDelay = (i * 0.8) + 's';
                    s.style.animationDuration = (2.8 + Math.random() * 1.2).toFixed(2) + 's';
                    s.style.setProperty('--smoke-drift', `${6 + Math.random() * 18}px`);
                    tile.appendChild(s);
                }
            }
            if (t.type === 'city_village') {
                posY -= 10;
                for(let i=0; i<smokeCount; i++) {
                    const s = document.createElement('div');
                    s.className = 'smoke-particle';
                    s.style.left = '75px'; s.style.top = '50px'; 
                    s.style.animationDelay = (i * 0.8) + 's';
                    s.style.animationDuration = (2.8 + Math.random() * 1.2).toFixed(2) + 's';
                    s.style.setProperty('--smoke-drift', `${6 + Math.random() * 18}px`);
                    tile.appendChild(s);
                }
            }
            
            tile.style.left = posX + 'px';
            tile.style.top = posY + 'px';
            tile.style.zIndex = parseInt(t.y);
            tile.dataset.x = t.x; 
            tile.dataset.y = t.y;
            tile.onclick = () => attemptMove(t.x, t.y);
            mapDiv.appendChild(tile);
            isNew = true;
        } else {
            const expectedClass = `tile ${t.type}`;
            if (tile.className !== expectedClass) {
                tile.className = expectedClass;
            }
        }

        const tx = parseInt(t.x, 10);
        const ty = parseInt(t.y, 10);
        const dx = tx - playerX;
        const dy = ty - playerY;

        const tOffsetX = (ty % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
        const tPosX = (tx * HEX_WIDTH) + tOffsetX;
        const tPosY = (ty * HEX_HEIGHT);

        let lightAdjust = 1;
        if (cfg.dynamicLightingEnabled) {
            let maxBase = 0;
            for (let i = 0; i < lightSources.length; i++) {
                const src = lightSources[i];
                const pxDist = Math.hypot(tPosX - src.x, tPosY - src.y);
                const base = Math.max(0, 1 - (pxDist / src.radius));
                if (base > maxBase) maxBase = base;
            }
            maxBase = Math.max(0.42, maxBase);
            lightAdjust = Math.min(1, maxBase + 0.18);
        }

        const shadowCastingTypes = new Set(['mountain', 'forest', 'city_capital', 'city_village', 'hills', 'hills2', 'wmountain', 'wforest', 'whills', 'whills2']);
        let newFilter = '';
        if (cfg.shadingEnabled && shadowCastingTypes.has(t.type)) {
            const dir = getLightDirectionVector();
            let stretch = 1;
            if (t.type === 'mountain' || t.type === 'hills2' || t.type === 'wmountain' || t.type === 'whills2') stretch = 2.8;
            else if (t.type === 'hills' || t.type === 'whills') stretch = 1.7;
            
            const shadowX = ((dir.x * 8) + (dx * 0.16)) * stretch;
            const shadowY = ((dir.y * 8) + (dy * 0.16)) * stretch;
            const blur = Math.max(0.6, 3 - (shadowStrength * 1.2));
            const shadowOpacity = Math.min(0.92, 0.2 + shadowStrength * 0.55);
            newFilter = `brightness(${lightAdjust.toFixed(2)}) drop-shadow(${shadowX.toFixed(1)}px ${shadowY.toFixed(1)}px ${blur.toFixed(1)}px rgba(0,0,0,${shadowOpacity.toFixed(2)}))`;
        } else {
            newFilter = `brightness(${lightAdjust.toFixed(2)})`;
        }

        if (!isNew && moveDuration > 0 && cfg.dynamicLightingEnabled) {
            tile.style.transition = `filter ${moveDuration}s linear`;
        } else {
            tile.style.transition = 'none';
        }
        
        tile.style.filter = newFilter;
    });

    existingTiles.forEach((tile, key) => {
        if (!newKeys.has(key)) tile.remove();
    });

    if (!playerMarker.parentNode) mapDiv.appendChild(playerMarker);
}

function updatePlayerVisuals(x, y, isInstant = false, isUiToggle = false) {
    const targetTile = document.querySelector(`.tile[data-x='${x}'][data-y='${y}']`);
    if (targetTile) {
        const tLeft = targetTile.offsetLeft;
        const tTop = targetTile.offsetTop;
        const targetPixelX = tLeft - 10; 
        const targetPixelY = tTop - 24;
        
        // Sprawdź czy pole jest oświetlone (miasto) dla efektu nocy
        if (targetTile.classList.contains('city_capital') || targetTile.classList.contains('city_village')) {
            playerMarker.classList.add('in-light');
        } else {
            playerMarker.classList.remove('in-light');
        }

            let transitionDuration = 0;

        if (isInstant) {
            playerMarker.style.transition = 'none';
            playerMarker.style.left = targetPixelX + 'px';
            playerMarker.style.top = targetPixelY + 'px';
            setAnimationState('idle');
        } else {
            const currentLeft = parseFloat(playerMarker.style.left || 0);
            const currentTop = parseFloat(playerMarker.style.top || 0);

            const deltaX = targetPixelX - currentLeft;
            const deltaY = targetPixelY - currentTop;
            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            const duration = distance / MOVEMENT_SPEED_PX; 
                transitionDuration = duration;

            setAnimationState('run');
            
            playerMarker.style.transition = `top ${duration}s linear, left ${duration}s linear`;
            playerMarker.style.left = targetPixelX + 'px';
            playerMarker.style.top = targetPixelY + 'px';

            if (moveTimeout) clearTimeout(moveTimeout);
            moveTimeout = setTimeout(() => { setAnimationState('idle'); }, duration * 1000);
        }
        window.currentMoveDuration = transitionDuration;
        playerMarker.style.zIndex = 1300; 
            centerMapOnPlayer(tLeft, tTop, transitionDuration, isUiToggle);
    }
}

function centerMapOnPlayer(pixelX, pixelY, transitionDuration = 0, isUiToggle = false) {
    const panel = document.getElementById('left-panel');
    const map = document.getElementById('map');
    if (!panel || !map) return;

    let viewportWidth = panel.offsetWidth;
    let viewportHeight = panel.offsetHeight;
    
    // Mobile adjustments (auto-zoom based on viewport width)
    let scale = 1;
    let offsetY = 0;
    const isPortrait = window.innerHeight > window.innerWidth;
    isMobile = Math.min(window.innerWidth, window.innerHeight) <= 900;
    const isDesktop = window.innerWidth > 1366;

    const layout = document.getElementById('game-layout');
    const rightPanel = document.getElementById('right-panel');

    if (isUiToggle && isDesktop && layout) {
        // Przewidywanie szerokości ekranu po wysunięciu dla animacji na PC
        viewportWidth = layout.classList.contains('panel-collapsed') ? window.innerWidth : window.innerWidth - 350;
    }

    if (isMobile || !isDesktop) {
        scale = isPortrait ? 0.68 : 0.62;

        if (isPortrait && layout && rightPanel && !layout.classList.contains('panel-collapsed')) {
            const panelHeight = rightPanel.getBoundingClientRect().height || 0;
            offsetY = -(panelHeight * 0.25);
        }
    }

    // Calculate center based on scale (transform-origin is 0 0)
    const moveX = (viewportWidth / 2) - (pixelX + 64) * scale;
    const moveY = (viewportHeight / 2) + offsetY - (pixelY + 64) * scale;

    if (isUiToggle) {
        const animDur = isDesktop ? 0.4 : 0.3;
        map.style.transition = `transform ${animDur}s ease-in-out`;
        map.style.willChange = 'transform';
    } else if (transitionDuration > 0) {
        const cameraDuration = transitionDuration + 0.65;
        map.style.transition = `transform ${cameraDuration}s ease-out`;
        map.style.willChange = 'transform';
    } else {
        map.style.transition = 'none';
    }

    map.style.transform = `translate(${moveX}px, ${moveY}px) scale(${scale})`;
}

function setAnimationState(newState) {
    if (currentAnimState === newState) return;
    currentAnimState = newState; currentFrameIndex = 0; updatePlayerSprite();
    if (newState === 'run') { startWalkingSound(); } else { stopWalkingSound(); }
}

function startPlayerAnimation() {
    if (animationInterval) clearInterval(animationInterval);
    const cfg = getGraphicsConfig();
    const speedMs = Math.max(60, parseInt(cfg.animationSpeedMs || ANIMATION_SPEED, 10));
    updatePlayerSprite();
    animationInterval = setInterval(() => {
        currentFrameIndex++;
        if (currentFrameIndex >= playerSprites[currentAnimState].length) currentFrameIndex = 0;
        updatePlayerSprite();
    }, speedMs);
}

function updatePlayerSprite() {
    const frames = playerSprites[currentAnimState];
    if (frames && frames.length > 0) playerMarker.style.backgroundImage = `url('${frames[currentFrameIndex]}')`;
}

async function attemptMove(targetX, targetY) {
    if (gameState.hp <= 0 || gameState.in_combat || gameState.is_pvp) return;
    if (targetX < gameState.x) playerMarker.style.transform = "scaleX(-1)"; else if (targetX > gameState.x) playerMarker.style.transform = "scaleX(1)";
    
    // Prevent moving to self (resting should be explicit, prevents accidental clicks)
    if (targetX === gameState.x && targetY === gameState.y) return;

    // Check if target is a safe zone (city/village)
    const targetTile = document.querySelector(`.tile[data-x='${targetX}'][data-y='${targetY}']`);
    const isSafeZone = targetTile && (targetTile.classList.contains('city_capital') || targetTile.classList.contains('city_village'));

    // Check for interaction with other players (only outside safe zones)
    if (!isSafeZone) {
        const targetPlayerId = Object.keys(otherPlayers).find(id => 
            otherPlayers[id].x == targetX && otherPlayers[id].y == targetY
        );
        if (targetPlayerId) {
            openPlayerMenu(targetPlayerId);
            return;
        }
    }

    const serverPreset = resolveGraphicsPresetName();
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'move', x: targetX, y: targetY, graphics_preset: serverPreset })
    });
    const result = await res.json();

    if (result.status === 'success') {
        gameState.x = result.new_x; gameState.y = result.new_y;
        gameState.hp = parseInt(result.hp); gameState.energy = parseInt(result.energy);
        
        if (gameState.energy <= 3 && !lowEnergyWarningShown) {
            showToast("⚠️ Low energy! If you don't return to a city, you will only move 1 tile!", "error big", 8000);
            lowEnergyWarningShown = true;
        } else if (gameState.energy > 3) {
            lowEnergyWarningShown = false;
        }
        
        updatePlayerVisuals(gameState.x, gameState.y, false);
        
        if (result.local_tiles) {
        renderMapTiles(result.local_tiles);
        }
        
        updateUI(result);
        updateShopButtonVisibility();
        if (result.encounter) { setTimeout(() => { initGame(); }, 1000); }
    }
}

function renderInventory(inventory) {
    const container = document.getElementById('inventory-grid');
    if (!container) return;
    container.innerHTML = '';
    if (!inventory || inventory.length === 0) { container.innerHTML = '<div style="color:#666; padding:10px;">Empty backpack...</div>'; return; }
    inventory.forEach(item => {
        const slot = document.createElement('div');
        slot.className = 'item-slot';
        if (item.is_equipped == 1) slot.classList.add('equipped');
        
        // Add click handler for usage/info
        slot.onclick = () => handleInventoryClick(item);
        
        slot.innerHTML = `<div style="font-size:24px;">${item.icon || '📦'}</div><div style="font-size:11px; margin-top:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.name}</div>${item.quantity > 1 ? `<div style="position:absolute; bottom:2px; right:5px; font-size:10px; color:#aaa;">x${item.quantity}</div>` : ''}`;
        container.appendChild(slot);
    });
}

async function handleInventoryClick(item) {
    // Consumables - use directly
    if (item.type === 'consumable') {
        if (gameState.in_combat) {
            if (!combatState || combatState.turn !== 'player' || isProcessingTurn) {
                showToast('Wait for your turn.', 'error');
                return;
            }
            useItem(item.item_id);
        } else {
            const res = await apiPost('use_item', { item_id: item.item_id });
            if (res.status === 'success') {
                gameState.hp = parseInt(res.hp);
                updateUI({ hp: gameState.hp });
                showToast(res.message, 'success');
                const state = await apiPost('get_state');
                if (state.status === 'success') renderInventory(state.data.inventory);
            } else {
                showToast(res.message, 'error');
            }
        }
        return;
    }

    // Equipment & Drops - show context menu
    if (item.type === 'weapon' || item.type === 'armor' || item.type === 'drop') {
        showItemMenu(item);
    }
}

function showItemMenu(item) {
    let modal = document.getElementById('item-menu-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'item-menu-modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:10000;';
        modal.innerHTML = `
            <div style="background:#1a1a1a; border:2px solid gold; border-radius:8px; padding:20px; min-width:300px; box-shadow:0 0 20px rgba(255,215,0,0.5);">
                <h3 id="item-menu-title" style="color:gold; margin:0 0 15px 0; text-align:center;"></h3>
                <p id="item-menu-desc" style="color:#ccc; font-size:14px; margin:0 0 20px 0; text-align:center;"></p>
                <div id="item-menu-buttons" style="display:flex; flex-direction:column; gap:10px;"></div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };
    }
    
    const title = modal.querySelector('#item-menu-title');
    const desc = modal.querySelector('#item-menu-desc');
    const buttons = modal.querySelector('#item-menu-buttons');
    
    title.innerText = item.name + (item.is_equipped ? ' ✓' : '');
    desc.innerText = item.description || '';
    buttons.innerHTML = '';
    
    // Equip/Unequip buttons for weapons and armor
    if (item.type === 'weapon' || item.type === 'armor') {
        if (item.is_equipped == 1) {
            const unequipBtn = document.createElement('button');
            unequipBtn.innerText = '🔓 Unequip';
            unequipBtn.style.cssText = 'padding:10px; background:#555; color:white; border:none; border-radius:4px; cursor:pointer; font-size:16px;';
            unequipBtn.onclick = () => { handleUnequipItem(item); modal.style.display = 'none'; };
            buttons.appendChild(unequipBtn);
        } else {
            const equipBtn = document.createElement('button');
            equipBtn.innerText = '⚔️ Equip';
            equipBtn.style.cssText = 'padding:10px; background:#4CAF50; color:white; border:none; border-radius:4px; cursor:pointer; font-size:16px;';
            equipBtn.onclick = () => { handleEquipItem(item); modal.style.display = 'none'; };
            buttons.appendChild(equipBtn);
        }
    }
    
    // Sell button (only if not equipped)
    if (item.is_equipped != 1) {
        const sellBtn = document.createElement('button');
        let sellPrice = item.price; // Default to base price
        // Check if item has item_value (it's a drop with scaled value = 100%), otherwise apply 60% to normal shop items
        if (item.item_value !== undefined && item.item_value !== null) {
            sellPrice = Math.max(1, item.item_value); // 100% for drops
        } else {
            sellPrice = Math.max(1, Math.floor(item.price * 0.6)); // 60% for shop items
        }
        sellBtn.innerHTML = `💰 Sell (${formatCoins(sellPrice, true)})`;
        sellBtn.style.cssText = 'padding:10px; background:linear-gradient(180deg, #6b3a17, #3e240f); color:#f4d58d; border:2px solid #5c4a35; border-radius:4px; cursor:pointer; font-size:16px; text-shadow:1px 1px 1px rgba(0,0,0,0.7); box-shadow:0 2px 0 #2b1a0c;';
        sellBtn.onclick = () => { handleSellFromInventory(item); modal.style.display = 'none'; };
        buttons.appendChild(sellBtn);
    }
    
    // Cancel button
    const cancelBtn = document.createElement('button');
    cancelBtn.innerText = 'Cancel';
    cancelBtn.style.cssText = 'padding:10px; background:#333; color:white; border:none; border-radius:4px; cursor:pointer; font-size:16px;';
    cancelBtn.onclick = () => { modal.style.display = 'none'; };
    buttons.appendChild(cancelBtn);
    
    modal.style.display = 'flex';
}

async function handleEquipItem(item) {
    const res = await apiPost('equip_item', { item_id: item.item_id });
    if (res.status === 'success') {
        showToast(res.message, 'success');
        const state = await apiPost('get_state');
        if (state.status === 'success') {
            renderInventory(state.data.inventory);
            updateUI(state.data);
        }
    } else {
        showToast(res.message, 'error');
    }
}

async function handleUnequipItem(item) {
    const res = await apiPost('unequip_item', { item_id: item.item_id });
    if (res.status === 'success') {
        showToast(res.message, 'success');
        const state = await apiPost('get_state');
        if (state.status === 'success') {
            renderInventory(state.data.inventory);
            updateUI(state.data);
        }
    } else {
        showToast(res.message, 'error');
    }
}

async function handleSellFromInventory(item) {
    let sellPrice = item.price;
    if (item.item_value !== undefined && item.item_value !== null) {
        sellPrice = Math.max(1, item.item_value); // 100% for drops
    } else {
        sellPrice = Math.max(1, Math.floor(item.price * 0.6)); // 60% for shop items
    }
    if (!confirm(`Sell ${item.name} for ${formatCoins(sellPrice)}?`)) return;
    
    const res = await apiPost('sell_item', { item_id: item.item_id });
    if (res.status === 'success') {
        showToast(res.message, 'success');
        gameState.gold = res.gold;
        const state = await apiPost('get_state');
        if (state.status === 'success') {
            renderInventory(state.data.inventory);
            updateUI(state.data);
        }
    } else {
        showToast(res.message, 'error');
    }
}

function toggleCombatMode(active, currentHp, enemyHp = 0) {
    const combatScreen = document.getElementById('combat-screen');
    const mapDiv = document.getElementById('map');
    const gameLayout = document.getElementById('game-layout');
    const topLeftUi = document.querySelector('.top-left-ui');
    const rightPanel = document.getElementById('right-panel');
    const worldBtn = document.getElementById('world-btn');
    const shopBtn = document.getElementById('shop-btn');
    const minimapBtn = document.getElementById('minimap-btn');
    const expandPanelBtn = document.getElementById('expand-panel-btn');
    const mobilePanelToggle = document.getElementById('mobile-panel-toggle');
    inCombatMode = active; gameState.in_combat = active;
    if (active) isProcessingTurn = false;
    updateShopButtonVisibility();
    pvpPollInFlight = false;
    pvpLastActionAt = 0;
    if (document.body) document.body.classList.toggle('combat-active', active);

    if (gameLayout) gameLayout.style.display = active ? 'none' : 'flex';
    if (topLeftUi) topLeftUi.style.display = active ? 'none' : '';
    if (rightPanel) rightPanel.style.display = active ? 'none' : '';
    if (worldBtn) worldBtn.style.display = active ? 'none' : worldBtn.style.display;
    if (minimapBtn) minimapBtn.style.display = active ? 'none' : 'block';
    if (expandPanelBtn) expandPanelBtn.style.display = active ? 'none' : '';
    if (mobilePanelToggle) mobilePanelToggle.style.display = active ? 'none' : '';

    // Audio handling
    if (active && isPlaying) { explorationAudio.pause(); combatAudio.currentTime = 0; combatAudio.play().catch(e => console.log(e)); } 
    else if (!active && isPlaying) { combatAudio.pause(); explorationAudio.play().catch(e => console.log(e)); }

    if (active) {
        stopMultiplayerPolling(); // Stop polling during combat
        updateCombatBackground();
        startCombatAnimations();
        mapDiv.style.display = 'none'; combatScreen.style.display = 'flex';
        let existingContainer = document.getElementById('combat-arena-container');
        if (existingContainer) existingContainer.remove();
        let container = document.createElement('div');
        container.id = 'combat-arena-container';
        container.style.width = '1100px'; container.style.height = '450px';
        container.style.position = 'relative'; container.style.margin = '20px auto';
        const arenaShell = document.getElementById('combat-arena-shell');
        if (arenaShell) {
            arenaShell.innerHTML = '';
            arenaShell.appendChild(container);
        } else {
            combatScreen.insertBefore(container, document.getElementById('combat-log'));
        }
        if (combatState) renderCombatArena();
        
        document.getElementById('enemy-hp').innerText = enemyHp;
        document.getElementById('combat-hp').innerText = gameState.hp;
        
        // Update Enemy Name if element exists
        const nameEl = document.getElementById('enemy-name');
        if (nameEl && combatState) {
            const lvl = combatState.enemy_level || 1;
            nameEl.innerText = `${combatState.enemy_name || 'Enemy'} (Lvl ${lvl})`;
        }
        
        // Aktualizacja pasków na start walki
        updateBar('combat-hp-bar', gameState.hp, gameState.max_hp);
        // Jeśli wchodzimy w walkę, zakładamy że enemyHp to max (chyba że wczytujemy stan)
        const eMax = gameState.enemy_max_hp > 0 ? gameState.enemy_max_hp : (enemyHp || 100);
        updateBar('combat-enemy-fill', enemyHp, eMax);
        
        if (!gameState.is_pvp) updateApDisplay();
        renderCombatSkillQuickbar();
    } else {
        mapDiv.style.display = 'block'; combatScreen.style.display = 'none';
        combatState = null;
        gameState.is_pvp = false;
        stopCombatAnimations();
        combatCameraState.initialized = false;
        loadAndDrawMap();
        updatePlayerVisuals(gameState.x, gameState.y, true);
        startMultiplayerPolling(); // Resume polling after combat
        renderCombatSkillQuickbar();
    }
    updateTutorialCloud();
}

function updateApDisplay() {
    const log = document.getElementById('combat-log');
    if (!combatState) return;

    const eName = combatState.enemy_name || 'ENEMY';
    let turnMsg = '';
    if (combatState.turn === 'player') {
        turnMsg = `<span style="color:#4f4; font-size:1.2em; font-weight:bold;">YOUR TURN (AP: ${combatState.player_ap})</span> <span style="font-size:0.8em; color:#fff;">⏱️ ${combatState.turn_remaining || 30}s</span>`;
    } else {
        turnMsg = `<span style="color:#f66; font-size:1.2em; font-weight:bold;">${eName.toUpperCase()} MOVE...</span> <span style="font-size:0.8em; color:#ccc;">⏱️ ${combatState.turn_remaining || 30}s</span>`;
    }

    // Show server log if available, otherwise just turn status
    if (combatState.log) {
        log.innerHTML = `${turnMsg}<br><span style="color:#ccc; font-size:0.9em;">${combatState.log}</span>`;
    } else {
        log.innerHTML = turnMsg;
    }
}

function renderCombatArena() {
    const container = document.getElementById('combat-arena-container');
    if (!container || !combatState || !combatState.tiles) return;

    // 1. Render Tiles only if they don't exist (prevents flickering)
    if (container.querySelectorAll('.tile').length === 0) {
        combatState.tiles.forEach(t => {
            const tile = document.createElement('div'); tile.className = `tile ${t.type}`;
            let offsetX = (t.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
            let posX = (t.x * HEX_WIDTH) + offsetX;
            let posY = (t.y * HEX_HEIGHT);
            tile.style.left = posX + 'px'; tile.style.top = posY + 'px'; tile.style.zIndex = t.y;
            tile.onclick = () => { 
                if(combatState.turn === 'player' && combatState.player_ap >= 1) {
                    if (gameState.is_pvp) handlePvPMove(t.x, t.y);
                    else handleCombatMove(t.x, t.y); 
                }
            };
            container.appendChild(tile);
        });
    }

    // 2. Update Entities (Create or Move)
    updateCombatEntity(combatState.player_pos, 'player', container);
    updateCombatEntity(combatState.enemy_pos, 'enemy', container);

    updateApDisplay();
    renderCombatSkillQuickbar();
    updateCombatCamera();
    
    // Only trigger AI turn if it's PvE
    if (!gameState.is_pvp && combatState.turn === 'enemy' && !isProcessingTurn) setTimeout(handleEnemyTurn, 500);
}

function renderCombatSkillQuickbar() {
    const quickbar = document.getElementById('combat-skill-quickbar');
    if (!quickbar) return;

    if (!inCombatMode || !gameState.in_combat) {
        quickbar.innerHTML = '';
        return;
    }

    const selected = getSelectedSkillsDetailed().slice(0, 4);
    if (!selected.length) {
        quickbar.innerHTML = '<div style="font-size:12px; color:#888;">No skills in loadout (configure in Attributes).</div>';
        return;
    }

    const isPlayerTurn = !!combatState && combatState.turn === 'player';
    quickbar.innerHTML = selected.map((s, index) => `
        <button class="combat-btn" style="margin:0; padding:6px 10px; min-width:120px; display:flex; align-items:center; gap:6px; justify-content:flex-start;" onclick="handleCombatUseSkill('${s.id}')" ${isPlayerTurn ? '' : 'disabled'}>
            <span style="display:inline-flex; width:18px; height:18px; border-radius:3px; align-items:center; justify-content:center; background:#222; color:#ffeaa7; font-size:11px; font-weight:bold; border:1px solid #5c4a35;">${index + 1}</span>
            <img src="${s.icon_path}" alt="${escapeHtml(s.name)}" style="width:18px; height:18px; image-rendering:pixelated;">
            <span style="font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(s.name)}</span>
        </button>
    `).join('');
}

function updateCombatEntity(pos, type, container) {
    let el = document.getElementById(`combat-${type}`);
    
    // Create if missing
    if (!el) {
        el = document.createElement('div'); 
        el.className = `player ${type}`; 
        el.id = `combat-${type}`; 
        el.style.zIndex = 100;
        el.dataset.animState = 'idle';
        el.style.backgroundImage = `url('assets/player/idle1.png?v=2')`;
        // Smooth transition for movement
        el.style.transition = "left 0.6s linear, top 0.6s linear, filter 0.2s";
        
        if (type === 'enemy') { 
            el.style.transform = "scaleX(-1)"; 
            el.onclick = () => { if (combatState.turn === 'player') { if (gameState.is_pvp) handlePvPAttack(); else handleCombatAttack(); } }; 
        }
        container.appendChild(el);
    }

    // Update Position
    let off = (pos.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
    let targetX = ((pos.x * HEX_WIDTH) + off - 10);
    let targetY = ((pos.y * HEX_HEIGHT) - 24);
    
    // Check for movement to animate and rotate
    const currentLeft = parseFloat(el.style.left || el.offsetLeft);
    const currentTop = parseFloat(el.style.top || el.offsetTop);
    
    if (Math.abs(targetX - currentLeft) > 1 || Math.abs(targetY - currentTop) > 1) {
        el.dataset.animState = 'run';
        
        // Rotate based on movement direction
        if (targetX > currentLeft) {
            el.style.transform = "scaleX(1)";
        } else if (targetX < currentLeft) {
            el.style.transform = "scaleX(-1)";
        }
        
        // Reset to idle after movement (transition is 0.3s)
        if (el.moveTimeout) clearTimeout(el.moveTimeout);
        el.moveTimeout = setTimeout(() => {
            el.dataset.animState = 'idle';
        }, 600);
    }
    
    el.style.left = targetX + 'px';
    el.style.top = targetY + 'px';

    // Visuals for Turn
    const isTurn = (combatState.turn === type);
    if (type === 'enemy') {
        
        let hue = "150deg"; 
        let sat = "100%";
        let bright = "1.0";
        
        if (combatState.enemy_type === 'green') { hue = "90deg"; bright = "1.2"; }
        else if (combatState.enemy_type === 'yellow') { hue = "50deg"; bright = "1.8"; sat = "200%"; }
        else if (combatState.enemy_type === 'orange') { hue = "25deg"; sat = "250%"; bright = "1.1"; }
        else if (combatState.enemy_type === 'red') { hue = "0deg"; sat = "200%"; bright = "0.8"; }
        
        let filter = `hue-rotate(${hue}) saturate(${sat}) brightness(${isTurn ? parseFloat(bright)*1.2 : parseFloat(bright)*0.8})`;
        if (isTurn) filter += " drop-shadow(0 0 5px red)";
        el.style.filter = filter;
    } else {
        
        if (isTurn) el.style.filter = "brightness(1.2) drop-shadow(0 0 5px gold)";
        else el.style.filter = "";
    }
}

function animateCombatMove(type, targetPos) {
    const el = document.getElementById(`combat-${type}`);
    if (!el) return;
    let off = (targetPos.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
    let targetPxX = (targetPos.x * HEX_WIDTH) + off - 10;
    let targetPxY = (targetPos.y * HEX_HEIGHT) - 24;
    el.dataset.animState = 'run'; startWalkingSound();
    el.style.transition = "left 0.6s linear, top 0.6s linear"; el.style.left = targetPxX + 'px'; el.style.top = targetPxY + 'px';
    setTimeout(() => { el.dataset.animState = 'idle'; stopWalkingSound(); }, 600);
}

function startCombatAnimations() {
    if (combatAnimInterval) clearInterval(combatAnimInterval);
    const cfg = getGraphicsConfig();
    const speedMs = Math.max(60, parseInt(cfg.animationSpeedMs || ANIMATION_SPEED, 10));
    combatFrameIndex = 0;
    combatAnimInterval = setInterval(() => {
        combatFrameIndex++;
        updateCombatSprites();
    }, speedMs);
}

function stopCombatAnimations() {
    if (combatAnimInterval) clearInterval(combatAnimInterval);
    combatAnimInterval = null;
}

function updateCombatSprites() {
    ['combat-player', 'combat-enemy'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        
        const state = el.dataset.animState || 'idle';
        const frames = playerSprites[state];
        if (!frames || frames.length === 0) return;
        
        const frame = frames[combatFrameIndex % frames.length];
        el.style.backgroundImage = `url('${frame}')`;
    });
}

async function handleCombatMove(x, y) {
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_move', x: x, y: y }) 
    });
    const json = await res.json();
    if (json.status === 'success') { animateCombatMove('player', {x: x, y: y}); setTimeout(() => { combatState = json.combat_state; renderCombatArena(); }, 400); } 
    else { document.getElementById('combat-log').innerText = json.message; }
}

async function handleCombatDefend() {
    if (!combatState || combatState.turn !== 'player') return;
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_defend' }) 
    });
    const json = await res.json();
    if (json.status === 'success') { document.getElementById('combat-log').innerText = json.message; combatState = json.combat_state; renderCombatArena(); }
}

// --- POPRAWIONA FUNKCJA WALKI ---
async function handleCombatAttack() {
    playSoundEffect('hit');
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_attack' }) 
    });
    const json = await res.json();
    if (json.status === 'success') {
        document.getElementById('enemy-hp').innerText = json.enemy_hp;
        document.getElementById('combat-log').innerText = json.log;
        
        // Aktualizacja pasków po ataku
        updateBar('combat-enemy-fill', json.enemy_hp, gameState.enemy_max_hp);
        
        if (json.gold !== undefined) {
            gameState.gold = parseInt(json.gold);
            updateUI({ gold: gameState.gold });
        }
        
        combatState = json.combat_state;
        renderCombatArena();
        if (json.dmg_dealt) {
            const enemyEl = document.getElementById('combat-enemy');
            if (enemyEl) { spawnCombatParticles(enemyEl, '#ffffff'); showFloatingDamage(enemyEl, json.dmg_dealt, '#ffeb3b'); }
        }
        if (json.win) { 
            showCombatResult(json.xp_gain, json.gold_gain, json.loot, json.tutorial_finished);
        }
    } else { document.getElementById('combat-log').innerText = json.message; }
}

async function handleEnemyTurn() {
    if (isProcessingTurn) return;
    isProcessingTurn = true;

    try {
        const res = await fetch('api.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'enemy_turn' }) 
        });
        const json = await res.json();
        if (json.status === 'success') {
            const actions = json.actions || [];
            const playAction = (index) => {
                if (index >= actions.length) {
                    setTimeout(() => {
                        document.getElementById('combat-hp').innerText = json.hp;
                        
                        // Aktualizacja paska gracza po turze wroga
                        updateBar('combat-hp-bar', json.hp, gameState.max_hp);
                        
                        // Sync enemy HP from server response
                        if (json.enemy_hp !== undefined) {
                            gameState.enemy_hp = parseInt(json.enemy_hp);
                            document.getElementById('enemy-hp').innerText = gameState.enemy_hp;
                            updateBar('combat-enemy-fill', gameState.enemy_hp, gameState.enemy_max_hp);
                        }

                        document.getElementById('combat-log').innerText = json.log;
                        isProcessingTurn = false; // Reset flag
                        if (json.player_died) { 
                            gameState.hp = 0;
                            toggleCombatMode(false); 
                            checkLifeStatus(); 
                        } else { combatState = json.combat_state; renderCombatArena(); }
                    }, 500); return;
                }
                const action = actions[index];
                if (action.type === 'move') { animateCombatMove('enemy', action.to); setTimeout(() => playAction(index + 1), 700); } 
                else if (action.type === 'attack') {
                    const pEl = document.getElementById('combat-player'); playSoundEffect('hit');
                    if (action.dmg > 0) setTimeout(() => playSoundEffect('damage', action.dmg), 100);
                    if(pEl) pEl.style.filter = "brightness(0.5) sepia(1) hue-rotate(-50deg) saturate(5)"; 
                    if(pEl && action.dmg > 0) { spawnCombatParticles(pEl, '#d32f2f'); showFloatingDamage(pEl, action.dmg, '#ff1744'); }
                    setTimeout(() => { if(pEl) pEl.style.filter = ""; }, 200);
                    setTimeout(() => playAction(index + 1), 400);
                }
                else if (action.type === 'heal') {
                    const eEl = document.getElementById('combat-enemy');
                    if(eEl) {
                        spawnCombatParticles(eEl, '#00e676'); // Green particles
                        showFloatingDamage(eEl, '+' + action.amount, '#00e676');
                        // Update enemy HP bar visually immediately
                        const currentHp = parseInt(document.getElementById('enemy-hp').innerText || 0);
                        const newHp = currentHp + action.amount;
                        document.getElementById('enemy-hp').innerText = newHp;
                        updateBar('combat-enemy-fill', newHp, gameState.enemy_max_hp);
                    }
                    setTimeout(() => playAction(index + 1), 500);
                }
            }; playAction(0);
        } else {
            isProcessingTurn = false;
        }
    } catch (e) {
        isProcessingTurn = false;
        console.error(e);
    }
}

async function useItem(itemId) {
    if (!inCombatMode) return;
    if (!combatState || combatState.turn !== 'player' || isProcessingTurn) {
        showToast('Wait for your turn.', 'error');
        return;
    }
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'combat_use_item', item_id: itemId }) 
    });
    const json = await res.json();
    if (json.status === 'success') {
        document.getElementById('combat-hp').innerText = json.hp;
        document.getElementById('combat-log').innerText = json.message;
        updateBar('combat-hp-bar', json.hp, gameState.max_hp);
        combatState = json.combat_state;
        renderCombatArena();
    }
}

function closeSkillSelectionModal() {
    const modal = document.getElementById('skill-selection-modal');
    if (modal) modal.style.display = 'none';
}

function renderSkillSelectionModal() {
    const body = document.getElementById('skill-selection-body');
    if (!body) return;

    const level = parseInt(gameState.level || 1, 10);
    const shouldHave = Math.min(4, Math.max(0, Math.floor(level / 5)));
    const unlockedSet = new Set(unlockedSkillIds);
    const unlockableSet = new Set(unlockableSkillIds);
    const unlockedCount = getUnlockedSkillCount();
    const unlocksRemaining = Math.max(0, shouldHave - unlockedCount);
    const selectedSet = new Set(selectedSkillIds);
    const lockableSkills = skillCatalog.filter(s => !unlockedSet.has(s.id));
    const playerClassId = parseInt(gameState.class_id || 0, 10);
    const canReset = unlockedCount > 0 && (skillResetCostCopper <= 0 || (gameState.gold || 0) >= skillResetCostCopper);

    const getSkillLockReason = (skill) => {
        if (unlocksRemaining <= 0) return 'No free unlock slots.';
        if (skill.path_class_id && playerClassId && parseInt(skill.path_class_id, 10) !== playerClassId) {
            const pathClassName = CLASS_NAMES[parseInt(skill.path_class_id, 10)] || `Class ${skill.path_class_id}`;
            return `Class specialization: ${pathClassName} only.`;
        }
        if (unlockableSet.has(skill.id)) return '';
        const requiredLevel = parseInt(skill.required_level || 1, 10);
        if (level < requiredLevel) return `Requires level ${requiredLevel}.`;
        if (skill.prerequisite_skill_id && !unlockedSet.has(skill.prerequisite_skill_id)) return 'Requires previous tier in this branch.';
        return 'Locked by progression.';
    };

    const unlockPanel = `
        <div style="margin-bottom:12px; padding:10px; border:1px solid #444; border-radius:6px; background:#202020;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:6px;">
                <span style="color:#ffeaa7; font-weight:bold;">Skill Unlocks (${CLASS_NAMES[playerClassId] || 'Unknown'})</span>
                <div style="display:flex; gap:6px; align-items:center;">
                    <button class="combat-btn" style="margin:0; padding:4px 8px; font-size:11px;" onclick="toggleUnlockSkillMenu()">${skillUnlockMenuOpen ? 'Hide Menu' : 'Unlock'}</button>
                    <button class="combat-btn" style="margin:0; padding:4px 8px; font-size:11px;" onclick="resetSkillTree()" ${canReset ? '' : 'disabled'}>Reset (${formatCoins(skillResetCostCopper)})</button>
                </div>
            </div>
            <div style="font-size:11px; color:#bbb;">
                Level: ${level} | Eligible: ${shouldHave}/4 | Unlocked: ${unlockedCount}/4 | Remaining: ${unlocksRemaining}
            </div>
            <div style="font-size:10px; color:#888; margin-top:4px;">Skills are earned at levels 5, 10, 15 and 20.</div>
        </div>`;
    const unlockedSkills = skillCatalog.filter(s => unlockedSet.has(s.id));
    const unlockGroups = ['Buffs', 'Debuffs', 'Spells'];

    const unlockMenuHtml = skillUnlockMenuOpen
        ? `<div style="margin-bottom:12px; padding:10px; border:1px solid #444; border-radius:6px; background:#1d1d1d;">
            <div style="font-weight:bold; color:#ffeaa7; margin-bottom:8px;">Choose skill to unlock</div>
            ${unlocksRemaining <= 0
                ? `<div style="font-size:11px; color:#aaa;">No unlock slots available for your current level.</div>`
                : lockableSkills.length === 0
                    ? `<div style="font-size:11px; color:#aaa;">All skills are already unlocked.</div>`
                    : unlockGroups.map(group => {
                        const skills = lockableSkills.filter(s => s.category === group);
                        if (!skills.length) return '';
                        const cards = skills.map(s => {
                            const lockReason = getSkillLockReason(s);
                            const isDisabled = lockReason !== '';
                            const tier = parseInt(s.tier || 1, 10);
                            const pathName = s.path_name || (CLASS_NAMES[parseInt(s.path_class_id || 0, 10)] || 'General');
                            return `<div style="display:flex; justify-content:space-between; gap:8px; align-items:center; border:1px solid #444; border-radius:4px; padding:6px; background:#202020; opacity:${isDisabled ? '0.75' : '1'};">
                                <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                                    <img src="${s.icon_path}" alt="${escapeHtml(s.name)}" style="width:24px; height:24px; image-rendering:pixelated;">
                                    <div style="display:flex; flex-direction:column; gap:2px; min-width:0;">
                                        <span style="font-size:12px; color:#fff;">${escapeHtml(s.name)} <span style="color:#ffeaa7; font-size:10px;">(Tier ${tier})</span></span>
                                        <span style="font-size:10px; color:#aaa;">${escapeHtml(s.description || '')}</span>
                                        <span style="font-size:10px; color:#9cc2ff;">Path: ${escapeHtml(pathName)}</span>
                                        <span style="font-size:10px; color:${isDisabled ? '#ff9a9a' : '#8fe39f'};">${isDisabled ? escapeHtml(lockReason) : 'Ready to unlock'}</span>
                                    </div>
                                </div>
                                <button class="combat-btn" style="margin:0; padding:4px 8px; font-size:11px; white-space:nowrap;" onclick="unlockSkillById('${s.id}')" ${isDisabled ? 'disabled' : ''}>Unlock</button>
                            </div>`;
                        }).join('');
                        return `<div style="margin-bottom:10px;"><div style="font-weight:bold; color:#ffd700; margin-bottom:6px;">${group}</div><div style="display:grid; gap:6px;">${cards}</div></div>`;
                    }).join('')
            }
        </div>`
        : '';
    
    const groups = ['Buffs', 'Debuffs', 'Spells'];
    const loadoutHtml = groups.map(group => {
        const skills = unlockedSkills.filter(s => s.category === group);
        if (!skills.length) return '';
        const cards = skills.map(s => {
            const checked = selectedSet.has(s.id);
            const shouldDisable = !checked && selectedSkillIds.length >= 4;
            return `<label style="display:flex; gap:8px; align-items:center; border:1px solid #444; border-radius:4px; padding:6px; background:#1d1d1d; opacity:${shouldDisable ? '0.6' : '1'}; cursor:pointer;">
                <input type="checkbox" ${checked ? 'checked' : ''} ${shouldDisable ? 'disabled' : ''} onchange="toggleSkillSelection('${s.id}', this.checked)">
                <img src="${s.icon_path}" alt="${escapeHtml(s.name)}" style="width:24px; height:24px; image-rendering:pixelated;">
                <div style="display:flex; flex-direction:column; gap:2px;">
                    <span style="font-size:12px; color:#fff;">${escapeHtml(s.name)}</span>
                    <span style="font-size:10px; color:#aaa;">${escapeHtml(s.description || '')}</span>
                </div>
            </label>`;
        }).join('');

        return `<div style="margin-bottom:12px;"><div style="font-weight:bold; color:#ffd700; margin-bottom:6px;">${group}</div><div style="display:grid; gap:6px;">${cards}</div></div>`;
    }).join('');

    const loadoutSection = `
        <div style="margin-bottom:8px; font-weight:bold; color:#ffeaa7;">Combat Loadout</div>
        ${unlockedCount <= 0
            ? `<div style="color:#aaa; text-align:center; padding:8px 4px 12px;">No unlocked skills yet.</div>`
            : loadoutHtml
        }`;

    body.innerHTML = `${unlockPanel}${unlockMenuHtml}${loadoutSection}`;
}

window.toggleUnlockSkillMenu = function() {
    skillUnlockMenuOpen = !skillUnlockMenuOpen;
    renderSkillSelectionModal();
};

window.unlockSkillById = async function(skillId) {
    const res = await apiPost('unlock_skill', { skill_id: skillId });
    if (res.status !== 'success') {
        if (Array.isArray(res.unlocked_skill_ids)) {
            unlockedSkillIds = res.unlocked_skill_ids;
        }
        if (Array.isArray(res.unlockable_skill_ids)) {
            unlockableSkillIds = res.unlockable_skill_ids;
        }
        if (res.reset_cost_copper !== undefined) {
            skillResetCostCopper = parseInt(res.reset_cost_copper, 10) || 0;
        }
        updateAttributesUI(gameState);
        renderSkillSelectionModal();
        showToast(res.message || 'Unable to unlock this skill.', 'error');
        return;
    }

    if (Array.isArray(res.unlocked_skill_ids)) {
        unlockedSkillIds = res.unlocked_skill_ids;
    } else {
        await ensureSkillCatalogLoaded(true);
    }
    if (Array.isArray(res.unlockable_skill_ids)) {
        unlockableSkillIds = res.unlockable_skill_ids;
    }
    if (res.reset_cost_copper !== undefined) {
        skillResetCostCopper = parseInt(res.reset_cost_copper, 10) || 0;
    }

    refreshSelectedSkillsByLevel();
    updateAttributesUI(gameState);
    renderSkillSelectionModal();
    const unlockedName = res.unlocked_skill?.name || 'skill';
    showToast(`🎯 Unlocked: ${unlockedName}`, 'success');
};

window.resetSkillTree = async function() {
    const unlockedCount = getUnlockedSkillCount();
    if (unlockedCount <= 0) {
        showToast('No unlocked skills to reset.', 'error');
        return;
    }

    const costLabel = formatCoins(skillResetCostCopper || 0);
    if (!confirm(`Reset your entire skill tree for ${costLabel}?`)) {
        return;
    }

    const res = await apiPost('reset_skill_tree');
    if (res.status !== 'success') {
        if (res.cost_copper !== undefined) {
            skillResetCostCopper = parseInt(res.cost_copper, 10) || skillResetCostCopper;
        }
        showToast(res.message || 'Failed to reset skill tree.', 'error');
        return;
    }

    unlockedSkillIds = Array.isArray(res.unlocked_skill_ids) ? res.unlocked_skill_ids : [];
    unlockableSkillIds = Array.isArray(res.unlockable_skill_ids) ? res.unlockable_skill_ids : [];
    if (res.cost_copper !== undefined) {
        skillResetCostCopper = parseInt(res.cost_copper, 10) || skillResetCostCopper;
    }
    if (res.gold !== undefined) {
        gameState.gold = parseInt(res.gold, 10) || 0;
        updateUI({ gold: gameState.gold });
    }

    refreshSelectedSkillsByLevel();
    updateAttributesUI(gameState);
    renderSkillSelectionModal();
    showToast('🔁 Skill tree reset complete.', 'success');
};

window.toggleSkillSelection = function(skillId, isChecked) {
    if (isChecked) {
        if (selectedSkillIds.includes(skillId)) return;
        if (selectedSkillIds.length >= 4) {
            showToast('Max 4 skills in combat loadout.', 'error');
            renderSkillSelectionModal();
            return;
        }
        selectedSkillIds.push(skillId);
    } else {
        selectedSkillIds = selectedSkillIds.filter(id => id !== skillId);
    }
    selectedSkillIds = selectedSkillIds.slice(0, 4);
    saveSelectedSkillsToStorage();
    updateAttributesUI(gameState);
    renderSkillSelectionModal();
};

window.openSkillSelectionModal = async function() {
    const ok = await ensureSkillCatalogLoaded(true);
    if (!ok) {
        showToast('Unable to load skills.', 'error');
        return;
    }
    skillUnlockMenuOpen = false;
    refreshSelectedSkillsByLevel();
    const modal = document.getElementById('skill-selection-modal');
    if (!modal) return;
    renderSkillSelectionModal();
    modal.style.display = 'flex';
};

function closeCombatSkillsModal() {
    const modal = document.getElementById('combat-skills-modal');
    if (modal) modal.style.display = 'none';
}

function renderCombatSkillsModal() {
    const body = document.getElementById('combat-skills-body');
    if (!body) return;

    const selected = getSelectedSkillsDetailed();
    if (!selected.length) {
        body.innerHTML = '<div style="color:#aaa; text-align:center;">No skills selected in Attributes.</div>';
        return;
    }

    body.innerHTML = selected.map(s => `
        <button class="combat-btn" style="display:flex; align-items:center; gap:8px; width:100%; margin:0 0 6px 0; justify-content:flex-start;" onclick="handleCombatUseSkill('${s.id}')">
            <img src="${s.icon_path}" alt="${escapeHtml(s.name)}" style="width:22px; height:22px; image-rendering:pixelated;">
            <span>${escapeHtml(s.name)}</span>
        </button>
    `).join('');
}

window.openCombatSkillsModal = function() {
    if (!combatState || combatState.turn !== 'player') {
        showToast('You can use skills only on your turn.', 'error');
        return;
    }
    const modal = document.getElementById('combat-skills-modal');
    if (!modal) return;
    renderCombatSkillsModal();
    modal.style.display = 'flex';
};

window.handleCombatUseSkill = async function(skillId) {
    if (!combatState || combatState.turn !== 'player') return;
    const res = await apiPost('combat_use_skill', {
        skill_id: skillId,
        selected_skills: selectedSkillIds
    });
    if (res.status !== 'success') {
        showToast(res.message || 'Skill failed.', 'error');
        return;
    }

    if (res.hp !== undefined) {
        gameState.hp = parseInt(res.hp, 10);
        document.getElementById('combat-hp').innerText = res.hp;
        updateBar('combat-hp-bar', res.hp, gameState.max_hp);
    }
    if (res.enemy_hp !== undefined) {
        gameState.enemy_hp = parseInt(res.enemy_hp, 10);
        document.getElementById('enemy-hp').innerText = res.enemy_hp;
        updateBar('combat-enemy-fill', res.enemy_hp, gameState.enemy_max_hp);
    }

    document.getElementById('combat-log').innerText = res.log || 'Skill used.';
    combatState = res.combat_state;
    closeCombatSkillsModal();
    renderCombatArena();
    renderCombatSkillQuickbar();

    if (res.win) {
        showCombatResult(res.xp_gain, res.gold_gain, res.loot, res.tutorial_finished);
    }
};

window.closeSkillSelectionModal = closeSkillSelectionModal;
window.closeCombatSkillsModal = closeCombatSkillsModal;

function updateLocalState(data) {
    gameState.x = parseInt(data.pos_x);
    gameState.y = parseInt(data.pos_y);
    gameState.hp = parseInt(data.hp);
    gameState.max_hp = parseInt(data.max_hp) || 100;
    gameState.energy = parseInt(data.energy);
    gameState.max_energy = parseInt(data.max_energy) || 10;
    if (gameState.energy > 3) lowEnergyWarningShown = false;
    gameState.xp = parseInt(data.xp);
    gameState.max_xp = parseInt(data.max_xp) || 100;
    gameState.steps_buffer = parseInt(data.steps_buffer);
    gameState.enemy_hp = parseInt(data.enemy_hp) || 0;
    gameState.enemy_max_hp = parseInt(data.enemy_max_hp) || 100;
    gameState.in_combat = (data.in_combat == 1);
    gameState.is_pvp = (data.is_pvp === true);
    // Luźne porównanie (==) bo PHP może zwrócić "1" lub 1
    gameState.tutorial_completed = (data.tutorial_completed == 1);
    gameState.gold = parseInt(data.gold || 0);
    gameState.world_id = parseInt(data.world_id || 2);
    if(data.level !== undefined) gameState.level = parseInt(data.level);
    if(data.stat_points !== undefined) gameState.stat_points = parseInt(data.stat_points);
    if(data.name) gameState.name = data.name;
    if(data.class_id) gameState.class_id = parseInt(data.class_id);
    if(data.base_attack !== undefined) gameState.base_attack = parseInt(data.base_attack);
    if(data.base_defense !== undefined) gameState.base_defense = parseInt(data.base_defense);
    if(data.attack !== undefined) gameState.attack = parseInt(data.attack);
    if(data.defense !== undefined) gameState.defense = parseInt(data.defense);
    if(data.name) gameState.name = data.name;
    if(data.id) gameState.id = parseInt(data.id);
    if(data.inventory) gameState.inventory = data.inventory;
}

function updateUI(data) {
    if(!data) return;
    if(data.hp !== undefined) { 
        const maxHp = data.max_hp || gameState.max_hp; 
        document.getElementById('hp').innerText = `${data.hp} / ${maxHp}`; 
        document.getElementById('hp-fill').style.width = (data.hp / maxHp * 100) + '%'; 
        const mHpVal = document.getElementById('mini-hp-val');
        if(mHpVal) mHpVal.innerText = data.hp;
        const mHpFill = document.getElementById('mini-hp-fill');
        if(mHpFill) mHpFill.style.width = (data.hp / maxHp * 100) + '%';
    }
    if(data.energy !== undefined) { const maxEn = data.max_energy || gameState.max_energy; document.getElementById('energy').innerText = `${data.energy} / ${maxEn}`; document.getElementById('en-fill').style.width = (data.energy / maxEn * 100) + '%'; }
    if(data.steps_buffer !== undefined) document.getElementById('steps-info').innerText = data.steps_buffer + '/10';
    if(data.xp !== undefined) { 
        const maxXp = data.max_xp || gameState.max_xp; 
        document.getElementById('xp-text').innerText = `${data.xp} / ${maxXp}`; 
        document.getElementById('xp-fill').style.width = (data.xp / maxXp * 100) + '%'; 
        const mXpVal = document.getElementById('mini-xp-val');
        if(mXpVal) mXpVal.innerText = data.xp;
        const mXpFill = document.getElementById('mini-xp-fill');
        if(mXpFill) mXpFill.style.width = (data.xp / maxXp * 100) + '%';
    }
    if(data.level) document.getElementById('lvl').innerText = data.level;
    if(data.name || gameState.name) { const charName = data.name || gameState.name; document.getElementById('class-name').innerText = charName; }
    if(data.class_id || gameState.class_id) { const classId = data.class_id || gameState.class_id; const className = CLASS_NAMES[classId] || 'Unknown'; const classEl = document.getElementById('char-class'); if(classEl) classEl.innerText = className; }
    if(data.gold !== undefined || gameState.gold !== undefined) {
        const g = data.gold !== undefined ? data.gold : gameState.gold;
        const gel = document.getElementById('gold-val');
        if (gel) gel.innerHTML = formatCoins(g, true);
        const mapGoldEl = document.getElementById('gold-display');
        if (mapGoldEl) mapGoldEl.innerHTML = formatCoins(g, true);
    }
    refreshSelectedSkillsByLevel();
    updateAttributesUI(data);
    maybeNotifySkillUnlock();
    updateTutorialCloud();
}

function updateBar(elementId, current, max) {
    const el = document.getElementById(elementId);
    if (el) el.style.width = Math.max(0, Math.min(100, (current / max * 100))) + '%';
}

function checkLifeStatus() { const ds = document.getElementById('death-screen'); if (gameState.hp <= 0) ds.style.display = 'flex'; else ds.style.display = 'none'; }

function updateAttributesUI(data) {
    const pts = (data.stat_points !== undefined) ? parseInt(data.stat_points) : gameState.stat_points;
    const baseAtk = (data.base_attack !== undefined) ? parseInt(data.base_attack) : gameState.base_attack;
    const baseDef = (data.base_defense !== undefined) ? parseInt(data.base_defense) : gameState.base_defense;
    const totalAtk = (data.attack !== undefined) ? parseInt(data.attack) : (gameState.attack || baseAtk);
    const totalDef = (data.defense !== undefined) ? parseInt(data.defense) : (gameState.defense || baseDef);
    
    const el = document.getElementById('stat-points-val');
    if(el) el.innerText = pts;
    
    const list = document.getElementById('attributes-list');
    if(list) {
        const createRow = (label, val, statKey, bonus = "+1") => `
            <div style="display:flex; justify-content:space-between; align-items:center; background:#252525; padding:10px; border-radius:4px; border:1px solid #444;">
                <span>${label}: <strong style="color:white">${val}</strong></span>
                ${pts > 0 ? `<button class="icon-btn" style="background:#00e676; color:black; width:24px; height:24px; border-radius:4px; font-weight:bold; font-size:16px; padding:0; display:flex; align-items:center; justify-content:center;" onclick="spendPoint('${statKey}')" title="${bonus}">+</button>` : ''}
            </div>`;

        const unlockedCount = getUnlockedSkillCount();
        const selectedDetailed = getSelectedSkillsDetailed();
        const selectedHtml = selectedDetailed.length
            ? selectedDetailed.map(s => `<div style="display:flex; align-items:center; gap:8px; background:#1f1f1f; border:1px solid #444; padding:6px; border-radius:4px;">
                    <img src="${s.icon_path}" alt="${escapeHtml(s.name)}" style="width:24px; height:24px; image-rendering:pixelated;">
                    <span style="font-size:12px; color:#ddd;">${escapeHtml(s.name)}</span>
               </div>`).join('')
            : `<div style="font-size:12px; color:#888;">No skills in loadout.</div>`;

        list.innerHTML = 
            createRow('Attack', totalAtk, 'str') +
            createRow('Defense', totalDef, 'def') +
            createRow('Max HP', data.max_hp || gameState.max_hp, 'hp', '+5') +
            createRow('Max Energy', data.max_energy || gameState.max_energy, 'eng') +
            `<div style="margin-top:8px; background:#252525; padding:10px; border-radius:4px; border:1px solid #444;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="color:#ffeaa7; font-weight:bold;">Combat Loadout</span>
                    <button class="combat-btn" style="margin:0; padding:4px 8px; font-size:11px;" onclick="openSkillSelectionModal()">Unlock / Configure</button>
                </div>
                <div style="font-size:11px; color:#bbb; margin-bottom:8px;">Unlocked: ${unlockedCount} | In loadout: ${selectedDetailed.length}/4</div>
                <div style="display:grid; gap:6px;">${selectedHtml}</div>
            </div>`;
    }
}

window.spendPoint = async function(stat) {
    const res = await apiPost('spend_stat_point', { stat });
    if(res.status === 'success') {
        updateLocalState(res.data);
        updateUI(res.data);
        // Refresh full state to sync attack/defense calculations
        await refreshState();
    }
}


window.switchTab = function(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}

function toggleSettings() {
    const modal = document.getElementById('settings-modal');
    const displayModal = modal.style.display;
    const isOpening = displayModal !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    if (isOpening) {
        syncMusicSettingsUI();
        syncGraphicsSettingsUI();
        const help = document.getElementById('keyboard-shortcuts-help');
        if (help) help.style.display = 'none';
    }
}

function toggleShortcutHelp() {
    const help = document.getElementById('keyboard-shortcuts-help');
    if (!help) return;
    help.style.display = (help.style.display === 'none' || !help.style.display) ? 'block' : 'none';
}

function playMusic() {
    if (isPlaying) return;
    userStoppedMusic = false;
    waitingAudio.pause();
    if (inCombatMode) {
        explorationAudio.pause();
        combatAudio.play().catch(() => {});
    } else {
        combatAudio.pause();
        if (!explorationAudio.src) {
            if (currentTrackIndex >= 0) setMusicTrack(currentTrackIndex);
            else playRandomTrack();
        }
        explorationAudio.play().catch(() => {});
    }
    isPlaying = true;
}

function stopMusic() {
    explorationAudio.pause(); combatAudio.pause(); waitingAudio.pause();
    isPlaying = false;
    userStoppedMusic = true;
}

function setVolume(val) {
    const volume = Math.max(0, Math.min(1, parseFloat(val)));
    explorationAudio.volume = volume;
    combatAudio.volume = volume;
    waitingAudio.volume = volume;

    const label = document.getElementById('music-volume-value');
    if (label) label.innerText = `${Math.round(volume * 100)}%`;
    const slider = document.getElementById('music-volume');
    if (slider && slider.value !== String(volume)) slider.value = volume;
}
function setSfxVolume(val) { sfxVolume = val; }
function getTrackLabel(src) {
    if (!src) return '-';
    const file = src.split('/').pop() || src;
    return file.replace(/\.[^/.]+$/, '');
}
function updateNowPlaying() {
    const label = document.getElementById('music-now-playing');
    if (label) label.innerText = currentTrackIndex >= 0 ? getTrackLabel(playlist[currentTrackIndex]) : '-';
}
function populateMusicList() {
    const select = document.getElementById('music-track-select');
    if (!select) return;
    select.innerHTML = '';
    playlist.forEach((src, idx) => {
        const option = document.createElement('option');
        option.value = String(idx);
        option.textContent = getTrackLabel(src);
        select.appendChild(option);
    });
}
function syncMusicSettingsUI() {
    const toggle = document.getElementById('music-loop-toggle');
    if (toggle) toggle.checked = loopCurrentTrack;
    const select = document.getElementById('music-track-select');
    if (select && currentTrackIndex >= 0) select.value = String(currentTrackIndex);
    updateNowPlaying();
    setVolume(explorationAudio.volume);
}
function setMusicLoop(enabled) {
    loopCurrentTrack = Boolean(enabled);
    explorationAudio.loop = loopCurrentTrack;
    const toggle = document.getElementById('music-loop-toggle');
    if (toggle) toggle.checked = loopCurrentTrack;
}
function setMusicTrack(value) {
    const idx = parseInt(value, 10);
    if (Number.isNaN(idx) || idx < 0 || idx >= playlist.length) return;
    currentTrackIndex = idx;
    explorationAudio.src = playlist[currentTrackIndex];
    explorationAudio.loop = loopCurrentTrack;
    updateNowPlaying();
    if (isPlaying && !userStoppedMusic && !inCombatMode) {
        explorationAudio.play().catch(() => {
            isPlaying = false;
        });
    }
}
function playRandomTrack() {
    if (!playlist.length) return;
    let next = Math.floor(Math.random() * playlist.length);
    if (playlist.length > 1 && next === currentTrackIndex) next = (next + 1) % playlist.length;
    currentTrackIndex = next;
    explorationAudio.src = playlist[currentTrackIndex];
    explorationAudio.loop = loopCurrentTrack;
    updateNowPlaying();
    if (isPlaying && !userStoppedMusic && !inCombatMode) {
        explorationAudio.play().catch(() => {
            isPlaying = false;
        });
    }
    syncMusicSettingsUI();
}
function handleTrackEnded() {
    if (!loopCurrentTrack) playRandomTrack();
}
explorationAudio.addEventListener('ended', handleTrackEnded);

function playWaitingMusic() {
    explorationAudio.pause();
    combatAudio.pause();
    waitingAudio.currentTime = 0;
    waitingAudio.play().catch(() => {});
}

function stopWaitingMusic() {
    waitingAudio.pause();
    waitingAudio.currentTime = 0;
}

// --- AUTH & CHARACTER SELECTION ---

function showAuthModal() {
    document.getElementById('start-screen').style.display = 'none';
    const authModal = document.getElementById('auth-modal');
    if (authModal) {
        authModal.style.display = 'flex';
    }
    document.getElementById('login-form').style.display = 'block';
    document.getElementById('register-form').style.display = 'none';
    if (!userStoppedMusic) playWaitingMusic();
    // Ensure game-layout is hidden when auth modal is shown
    const gameLayout = document.getElementById('game-layout');
    if (gameLayout) {
        gameLayout.style.display = 'none';
    }
}

function toggleAuthForm() {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const title = document.getElementById('auth-title');
    
    if (loginForm.style.display === 'none') {
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
        title.innerText = 'Zaloguj się';
    } else {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
        title.innerText = 'Zarejestruj się';
    }
}

async function handleLogin() {
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    const rememberMe = document.getElementById('remember-me').checked;
    
    if (!username || !password) {
        showToast('Fill in all fields.', 'error');
        return;
    }
    
    const data = await apiPost('login_account', { username, password, remember_me: rememberMe });
    if (data.status === 'success') {
        document.getElementById('auth-modal').style.display = 'none';
        stopWaitingMusic();
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Login error.', 'error');
    }
}

async function handleRegister() {
    const username = document.getElementById('register-username').value.trim();
    const password = document.getElementById('register-password').value;
    const password2 = document.getElementById('register-password2').value;
    
    if (!username || !password || !password2) {
        showToast('Fill in all fields.', 'error');
        return;
    }
    
    const data = await apiPost('register_account', { username, password, password2 });
    if (data.status === 'success') {
        document.getElementById('auth-modal').style.display = 'none';
        stopWaitingMusic();
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Registration error.', 'error');
    }
}

async function handleLogout() {
    stopMultiplayerPolling(); // Clean up polling
    stopMusic();
    await apiPost('logout_account');
    location.reload();
}

async function changeCharacter() {
    document.getElementById('settings-modal').style.display = 'none';
    document.getElementById('game-layout').style.display = 'none';
    await loadCharacterSelection();
}

async function loadCharacterSelection() {
    preloadAssets();
    // Ensure game-layout is hidden when character selection is loaded
    const gameLayout = document.getElementById('game-layout');
    if (gameLayout) {
        gameLayout.style.display = 'none';
    }
    const closeBtn = document.getElementById('char-selection-close');
    if (closeBtn) {
        closeBtn.style.display = (gameState && gameState.id) ? 'inline-flex' : 'none';
    }
    try {
        const data = await apiPost('get_characters');
        if (data.status !== 'success') {
            showToast('Error fetching characters.', 'error');
            return;
        }
        
        const container = document.getElementById('char-slots-container');
        container.innerHTML = '';
        
        data.characters.forEach((char, idx) => {
            const slot = document.createElement('div');
            slot.className = 'char-slot' + (char.id ? '' : ' empty');
            
            if (char.id) {
                slot.style.position = 'relative';
                slot.innerHTML = `
                    <div class="char-slot-name">${escapeHtml(char.name)}</div>
                    <div class="char-slot-class">Level ${char.level}</div>
                    <img src="assets/ui/ex.png" style="position:absolute; top:50%; right:8px; transform: translateY(-50%); width:24px; height:24px; cursor:pointer; z-index:10; filter:drop-shadow(0 0 2px #000);" onclick="event.stopPropagation(); confirmDeleteCharacter(${char.id})">
                `;
                slot.onclick = () => selectCharacter(char.id);
            } else {
                slot.innerHTML = '<div>+ Create new character</div>';
                slot.onclick = () => createNewCharacter();
            }
            container.appendChild(slot);
        });
        
        document.getElementById('char-selection-modal').style.display = 'flex';
    } catch (e) {
        console.error('loadCharacterSelection error', e);
    }
}

async function selectCharacter(charId) {
    const data = await apiPost('select_character', { character_id: charId });
    if (data.status === 'success') {
        document.getElementById('char-selection-modal').style.display = 'none';
        playTransitionOverlay(async () => {
            await startGame();
        });
    }
}

function createNewCharacter() {
    const modal = document.getElementById('create-char-modal');
    const selection = document.getElementById('char-selection-modal');
    if (selection) selection.style.display = 'none';
    if (!modal) return;
    modal.style.display = 'flex';
    const nameInput = document.getElementById('new-char-name');
    if (nameInput) {
        nameInput.value = '';
        nameInput.focus();
    }
}

async function submitNewCharacter() {
    const nameInput = document.getElementById('new-char-name');
    const name = nameInput.value.trim() || "New character";
    const data = await apiPost('create_character', { name });
    if (data.status === 'success') {
        closeCreateCharacter();
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Cannot create character.', 'error');
    }
}

function closeCreateCharacter() {
    const modal = document.getElementById('create-char-modal');
    const selection = document.getElementById('char-selection-modal');
    if (modal) modal.style.display = 'none';
    if (selection) selection.style.display = 'flex';
}

function closeCharacterSelection() {
    if (!gameState || !gameState.id) return;
    playTransitionOverlay(async () => {
        const modal = document.getElementById('char-selection-modal');
        if (modal) modal.style.display = 'none';
        const gameLayout = document.getElementById('game-layout');
        if (gameLayout) gameLayout.style.display = 'flex';
        if (!gameState.in_combat) startMultiplayerPolling();
    });
}

function playTransitionOverlay(done) {
    let overlay = document.getElementById('transition-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'transition-overlay';
        document.body.appendChild(overlay);
    }
    
    overlay.classList.remove('active');
    
    overlay.style.cssText = `
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: radial-gradient(circle, #1a0b2e 0%, #050011 100%);
        z-index: 999999;
        pointer-events: all;
        opacity: 0;
        transform: scale(1.2);
        transition: opacity 0.8s ease, transform 0.8s cubic-bezier(0.25, 1, 0.5, 1);
        display: block;
    `;
    
    void overlay.offsetWidth; // Force layout reflow
    
    overlay.style.opacity = '1';
    overlay.style.transform = 'scale(1)';
    
    setTimeout(async () => {
        if (typeof done === 'function') await done();
        
        overlay.style.opacity = '0';
        overlay.style.transform = 'scale(1.5)';
        
        setTimeout(() => {
            overlay.style.display = 'none';
            overlay.style.pointerEvents = 'none';
        }, 850);
        
    }, 850);
}

window.closeCreateCharacter = closeCreateCharacter;
window.closeCharacterSelection = closeCharacterSelection;
window.playTransitionOverlay = playTransitionOverlay;

window.confirmDeleteCharacter = function(charId) {
    let modal = document.getElementById('delete-confirm-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'delete-confirm-modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); display:flex; justify-content:center; align-items:center; z-index:10200;';
        modal.innerHTML = `
            <div style="background:#222; padding:25px; border:2px solid #555; border-radius:10px; text-align:center; color:white; box-shadow:0 0 20px #000;">
                <h2 style="margin-top:0; margin-bottom:20px;">Are you sure?</h2>
                <div style="display:flex; gap:30px; justify-content:center;">
                    <img id="del-btn-yes" src="assets/ui/play.png" style="width:50px; height:50px; cursor:pointer; transition:transform 0.1s;" title="Delete">
                    <img id="del-btn-no" src="assets/ui/ex.png" style="width:50px; height:50px; cursor:pointer; transition:transform 0.1s;" title="Cancel">
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('del-btn-no').onclick = () => modal.style.display = 'none';
    }
    
    modal.style.display = 'flex';
    const yesBtn = document.getElementById('del-btn-yes');
    const newYes = yesBtn.cloneNode(true); // Remove old listeners
    yesBtn.parentNode.replaceChild(newYes, yesBtn);
    
    newYes.onclick = async () => {
        modal.style.display = 'none';
        const res = await apiPost('delete_character', { character_id: charId });
        if (res.status === 'success') loadCharacterSelection();
        else showToast(res.message || 'Delete error', 'error');
    };
}

// Check for remembered login on page load
async function checkRememberedLogin() {
    try {
        const data = await apiPost('check_remembered_login');
        if (data.status === 'success' && data.user_id) {
            // Auto-login successful - show character selection
            document.getElementById('start-screen').style.display = 'none';
            await loadCharacterSelection();
        } else {
            // Not logged in - show auth modal
            showAuthModal();
        }
    } catch (e) {
        console.error('checkRememberedLogin error', e);
        showAuthModal();
    }
}

window.showAuthModal = showAuthModal;
window.toggleAuthForm = toggleAuthForm;
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.handleLogout = handleLogout;
window.toggleSettings = toggleSettings;
window.joinWorldDebug = joinWorldDebug;
window.playMusic = playMusic;
window.stopMusic = stopMusic;
window.setVolume = setVolume;
window.setMusicLoop = setMusicLoop;
window.setMusicTrack = setMusicTrack;
window.setGraphicsPreset = setGraphicsPreset;
window.changeCharacter = changeCharacter;
window.loadCharacterSelection = loadCharacterSelection;
window.setSfxVolume = setSfxVolume;
window.selectCharacter = selectCharacter;
window.toggleShortcutHelp = toggleShortcutHelp;
window.createNewCharacter = createNewCharacter;
window.submitNewCharacter = submitNewCharacter;

// --- PRELOADER ---
let assetsPreloaded = false;
function preloadAssets() {
    if (assetsPreloaded) return;
    assetsPreloaded = true;

    const screen = document.getElementById('loading-screen');
    const bar = document.getElementById('loading-bar-fill');
    const txt = document.getElementById('loading-text');
    if (screen) screen.style.display = 'flex';

    // 1. Obrazy (Sprite'y, UI, Mapa)
    const images = [
        ...playerSprites.idle, ...playerSprites.run,
        'assets/ui/Cursor_01.png', 'assets/ui/Cursor_02.png', 'assets/ui/sword.png',
        'assets/ui/BigBar_left.png', 'assets/ui/BigBar_middle.png', 'assets/ui/BigBar_right.png', 'assets/ui/BigBar_Fill.png',
        'img/grass.png', 'img/grass2.png', 'img/forest.png', 'img/mountain.png', 'img/hills.png', 'img/hills2.png', 'img/farmlands.png', 'img/water.png', 'img/castle.png', 'img/vilage.png',
        'img/winter/wgrass.png', 'img/winter/wgrass2.png', 'img/winter/forest.png', 'img/winter/wmountain.png', 'img/winter/hills.png', 'img/winter/hills2.png', 'img/winter/wfarmlands.png', 'img/winter/wwater.png'
    ];

    // 2. Dźwięki
    const sounds = [
        ...AUDIO_PATHS.walk, ...AUDIO_PATHS.hit, ...AUDIO_PATHS.damage,
        AUDIO_PATHS.combatMusic, 'assets/ui/misc_1.wav',
        ...playlist
    ];

    let total = images.length + sounds.length;
    let loaded = 0;

    const updateProgress = () => {
        loaded++;
        const pct = Math.floor((loaded / total) * 100);
        if (bar) bar.style.width = pct + '%';
        if (txt) txt.innerText = `Downloading: ${pct}% (${loaded}/${total})`;
        
        if (loaded >= total) {
            setTimeout(() => { if (screen) screen.style.display = 'none'; }, 500);
        }
    };

    // Load Images
    images.forEach(src => {
        const img = new Image();
        img.onload = updateProgress;
        img.onerror = updateProgress; // Count errors too to avoid hanging
        img.src = src;
    });

    // Load Sounds
    sounds.forEach(src => {
        const a = new Audio();
        a.addEventListener('canplaythrough', updateProgress, { once: true });
        a.addEventListener('error', updateProgress, { once: true });
        a.src = src;
        a.preload = 'auto';
        a.load();
    });
    
    // Fallback (max 10s waiting)
    setTimeout(() => { if (screen) screen.style.display = 'none'; }, 10000);
}

// On initial page load, check for remembered login
document.addEventListener('DOMContentLoaded', () => {
    loadGraphicsPreset();
    checkRememberedLogin();
    populateMusicList();
    syncMusicSettingsUI();
    syncGraphicsSettingsUI();
    // Ensure game-layout is hidden by default on page load
    const gameLayout = document.getElementById('game-layout');
    if (gameLayout) {
        gameLayout.style.display = 'none';
    }
});

let otherPlayers = {}; // Track other players by ID: { id: { x, y, name, level, marker } }
let otherPlayerMarkers = {}; // DOM elements for other players
let updatePlayersInterval = null;
let otherPlayersAnimInterval = null;

// --- MULTIPLAYER POLLING ---

function startMultiplayerPolling() {
    if (updatePlayersInterval) clearInterval(updatePlayersInterval);
    const cfg = getGraphicsConfig();
    const pollMs = Math.max(1000, parseInt(cfg.multiplayerPollMs || 1500, 10));
    updateOtherPlayers(); // Call once immediately
    updatePlayersInterval = setInterval(updateOtherPlayers, pollMs);
    startOtherPlayersAnimationLoop();
}

function stopMultiplayerPolling() {
    if (updatePlayersInterval) clearInterval(updatePlayersInterval);
    stopOtherPlayersAnimationLoop();
    // Remove all other player markers
    Object.keys(otherPlayerMarkers).forEach(id => {
        if (otherPlayerMarkers[id]) otherPlayerMarkers[id].remove();
    });
    otherPlayers = {};
    otherPlayerMarkers = {};
}

function startOtherPlayersAnimationLoop() {
    if (otherPlayersAnimInterval) clearInterval(otherPlayersAnimInterval);
    const cfg = getGraphicsConfig();
    if (!cfg.otherPlayersAnimEnabled) {
        Object.values(otherPlayerMarkers).forEach(marker => {
            marker.style.backgroundImage = `url('${playerSprites.idle[0]}')`;
            marker.dataset.frameIndex = 0;
        });
        return;
    }
    const speedMs = Math.max(60, parseInt(cfg.animationSpeedMs || ANIMATION_SPEED, 10));
    otherPlayersAnimInterval = setInterval(() => {
        Object.values(otherPlayerMarkers).forEach(marker => {
            let state = marker.dataset.animState || 'idle';
            let frameIdx = parseInt(marker.dataset.frameIndex || 0); 
            
            const frames = playerSprites[state];
            if (frames && frames.length > 0) {
                marker.style.backgroundImage = `url('${frames[frameIdx]}')`;
                frameIdx = (frameIdx + 1) % frames.length;
                marker.dataset.frameIndex = frameIdx;
            }
        });
    }, speedMs);
}

function stopOtherPlayersAnimationLoop() {
    if (otherPlayersAnimInterval) clearInterval(otherPlayersAnimInterval);
    otherPlayersAnimInterval = null;
}

async function updateOtherPlayers() {
    try {
        const data = await apiPost('get_other_players');
        if (data.status === 'success') {
            const players = data.players || [];
            const activeIds = new Set(players.map(p => String(p.id)));
            
            // Remove players that are no longer active
            Object.keys(otherPlayers).forEach(id => {
                if (!activeIds.has(id)) {
                    if (otherPlayerMarkers[id]) {
                        otherPlayerMarkers[id].remove();
                        delete otherPlayerMarkers[id];
                    }
                    delete otherPlayers[id];
                }
            });
            
            // Update Online List UI
            if (data.online_list) updateOnlineListUI(data.online_list);

            // Update or add players
            if (data.duel_requests && data.duel_requests.length > 0) {
                data.duel_requests.forEach(req => showDuelRequest(req));
            }
            
            if (data.my_duel_id && !gameState.in_combat) {
                // Auto-start duel if server says we are in one
                gameState.is_pvp = true;
                toggleCombatMode(true, gameState.hp, 100);
                pollPvPState();
            }

            players.forEach(p => {
                if (otherPlayers[p.id]) {
                    // Update existing player position
                    otherPlayers[p.id].x = parseInt(p.pos_x);
                    otherPlayers[p.id].y = parseInt(p.pos_y);
                    otherPlayers[p.id].level = p.level;
                    otherPlayers[p.id].name = p.name;
                    renderOtherPlayer(p.id);
                } else {
                    // Add new player
                    otherPlayers[p.id] = {
                        x: parseInt(p.pos_x),
                        y: parseInt(p.pos_y),
                        name: p.name,
                        level: p.level,
                        username: p.username
                    };
                    renderOtherPlayer(p.id);
                }
            });
            
        }
    } catch (e) {
        console.error('updateOtherPlayers error', e);
    }
}

function updateOnlineListUI(listData) {
    const listEl = document.getElementById('online-list-dropdown');
    const countVal = document.getElementById('online-count-val');
    
    if (countVal) countVal.innerText = listData.length;

    if (listEl) {
        listEl.innerHTML = '';
        listData.forEach(p => {
            const row = document.createElement('div');
            row.style.cssText = "padding: 6px; border-bottom: 1px solid #333; font-size: 12px; display: flex; justify-content: space-between; align-items:center;";
            
            const isSelf = (p.id == gameState.id); // Assuming gameState has ID now
            if (isSelf) row.style.color = "#4caf50"; 
            else row.style.color = "#ccc";

            row.innerHTML = `<span>${escapeHtml(p.name)}</span> <span style="color:#666; font-size:10px;">Lvl ${p.level}</span>`;
            
            if (!isSelf) {
                row.style.cursor = "pointer";
                row.onmouseover = () => row.style.background = "#333";
                row.onmouseout = () => row.style.background = "transparent";
                // Optional: Click to interact (e.g. whisper or track)
                // row.onclick = () => ...
            }
            listEl.appendChild(row);
        });
    }
}

window.togglePlayerList = function() {
    const el = document.getElementById('online-list-dropdown');
    if (el) el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

function renderOtherPlayer(playerId) {
    const player = otherPlayers[playerId];
    if (!player) return;
    
    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;
    
    // Calculate position (Math fallback if tile missing)
    const targetTile = document.querySelector(`.tile[data-x='${player.x}'][data-y='${player.y}']`);
    let targetPixelX, targetPixelY;
    
    if (targetTile) {
        targetPixelX = targetTile.offsetLeft - 10;
        targetPixelY = targetTile.offsetTop - 24;
    } else {
        let offsetX = (player.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
        targetPixelX = (player.x * HEX_WIDTH) + offsetX - 10;
        targetPixelY = (player.y * HEX_HEIGHT) - 24;
    }
    
    let marker = otherPlayerMarkers[playerId];


    if (!marker) {
        // Create new marker
        marker = document.createElement('div');
        marker.className = 'player other-player';
        marker.id = `other-player-${playerId}`;
        marker.style.left = targetPixelX + 'px';
        marker.style.top = targetPixelY + 'px';
        marker.style.zIndex = 500; // Between map and own player
        marker.style.display = 'block'; // Ensure visible

        marker.onclick = (e) => { e.stopPropagation(); openPlayerMenu(playerId); };
        marker.style.backgroundImage = `url('assets/player/idle1.png?v=2')`;

        marker.dataset.animState = 'idle';
        marker.dataset.frameIndex = 0;
        marker.dataset.lastX = targetPixelX;
        marker.dataset.lastY = targetPixelY;
        
        // Add label with name and level
        const label = document.createElement('div');
        label.className = 'player-label';
        label.style.position = 'absolute';
        label.style.bottom = '-25px';
        label.style.left = '50%';
        label.style.transform = 'translateX(-50%)';
        label.style.whiteSpace = 'nowrap';
        label.style.fontSize = '12px';
        label.style.color = '#aaffaa';
        label.style.textShadow = '0 0 3px #000';
        label.style.fontWeight = 'bold';
        label.innerHTML = `${escapeHtml(player.name)} (Lvl ${player.level})`;
        
        marker.appendChild(label);
        mapDiv.appendChild(marker);
        otherPlayerMarkers[playerId] = marker;
    } else {
        marker.style.display = 'block';
        // REMOVED: mapDiv.appendChild(marker); -- This was causing the crash/freeze by re-inserting DOM node constantly
        const label = marker.querySelector('.player-label');
        if (label) label.innerHTML = `${escapeHtml(player.name)} (Lvl ${player.level})`;

        const currentLeft = parseFloat(marker.dataset.lastX);

        const currentTop = parseFloat(marker.dataset.lastY);
        const deltaX = targetPixelX - currentLeft;
        const deltaY = targetPixelY - currentTop;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

        if (distance > 10) {
            const duration = distance / MOVEMENT_SPEED_PX;
            if (targetPixelX < currentLeft) {
                marker.style.transform = "scaleX(-1)";
                if (label) label.style.transform = "translateX(-50%) scaleX(-1)";
            } else if (targetPixelX > currentLeft) {
                marker.style.transform = "scaleX(1)";
                if (label) label.style.transform = "translateX(-50%) scaleX(1)";
            }
            marker.style.transition = `top ${duration}s linear, left ${duration}s linear`;
            marker.style.left = targetPixelX + 'px';
            marker.style.top = targetPixelY + 'px';
            if (marker.dataset.animState !== 'run') {
                marker.dataset.animState = 'run';
                marker.dataset.frameIndex = 0;
            }
            
            if (marker.moveTimeout) clearTimeout(marker.moveTimeout);
            marker.moveTimeout = setTimeout(() => { 

                marker.dataset.animState = 'idle'; 
                marker.dataset.frameIndex = 0;
            }, duration * 1000);

        } else {
            marker.style.transition = 'none';
            marker.style.left = targetPixelX + 'px';
            marker.style.top = targetPixelY + 'px';
        }

        marker.dataset.lastX = targetPixelX;
        marker.dataset.lastY = targetPixelY;
    }

    // Check if player is in safe zone (City/Village) to allow clicking the tile underneath
    if (targetTile && (targetTile.classList.contains('city_capital') || targetTile.classList.contains('city_village'))) {
        marker.classList.add('safe');
    } else {
        marker.classList.remove('safe');
    }

}

function spawnCombatParticles(targetEl, color) {
    if (!targetEl) return;
    const cfg = getGraphicsConfig();
    const particleCount = Math.max(0, parseInt(cfg.combatParticleCount || 0, 10));
    if (particleCount <= 0) return;
    const rect = targetEl.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    for (let i = 0; i < particleCount; i++) {
        const p = document.createElement('div');
        p.style.position = 'fixed'; p.style.left = centerX + 'px'; p.style.top = centerY + 'px';
        p.style.width = (Math.random() * 6 + 4) + 'px'; p.style.height = p.style.width;
        p.style.backgroundColor = color; p.style.borderRadius = '50%';
        p.style.pointerEvents = 'none'; p.style.zIndex = 99999;
        document.body.appendChild(p);

        const angle = Math.random() * Math.PI * 2;
        const dist = Math.random() * 80 + 30;
        const tx = Math.cos(angle) * dist; const ty = Math.sin(angle) * dist;

        p.animate([
            { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
            { transform: `translate(calc(-50% + ${tx}px), calc(-50% + ${ty}px)) scale(0)`, opacity: 0 }
        ], { duration: 600, easing: 'ease-out' }).onfinish = () => p.remove();
    }
}

function showRespawnEffect() {
    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;

    // Fix: Obliczamy pozycję na podstawie gameState (gdzie już jesteśmy po respawnie),
    // zamiast brać pozycję znacznika, który może się jeszcze przesuwać.
    const tile = document.querySelector(`.tile[data-x='${gameState.x}'][data-y='${gameState.y}']`);
    let targetX, targetY;

    if (tile) {
        targetX = tile.offsetLeft - 10;
        targetY = tile.offsetTop - 24;
    } else {
        // Fallback, jeśli kafelek jeszcze się nie wyrenderował (rzadkie)
        let offsetX = (gameState.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
        targetX = (gameState.x * HEX_WIDTH) + offsetX - 10;
        targetY = (gameState.y * HEX_HEIGHT) - 24;
    }

    const effect = document.createElement('div');
    effect.className = 'respawn-effect';
    effect.style.left = targetX + 'px';
    effect.style.top = targetY + 'px';
    
    mapDiv.appendChild(effect);
    
    const teleSound = new Audio('assets/ui/misc_1.wav'); // Replace with 'assets/ui/teleport.wav' if you have a specific sound file
    teleSound.volume = sfxVolume;
    teleSound.playbackRate = 0.6; // Lower pitch for a teleportation effect
    teleSound.play().catch(() => {});

    setTimeout(() => effect.remove(), 1000);
}

function showFloatingDamage(targetEl, amount, color) {
    if (!targetEl) return;
    if (!getGraphicsConfig().floatingDamageEnabled) return;
    const rect = targetEl.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const topY = rect.top;
    const el = document.createElement('div');
    el.innerText = amount; el.style.position = 'fixed'; el.style.left = centerX + 'px'; el.style.top = topY + 'px';
    el.style.color = color; el.style.fontWeight = '900'; el.style.fontSize = '32px';
    el.style.textShadow = '2px 2px 0 #000, -1px -1px 0 #000'; el.style.pointerEvents = 'none'; el.style.zIndex = 99999;
    el.style.transform = 'translate(-50%, 0)'; el.style.fontFamily = "'Segoe UI', sans-serif";
    document.body.appendChild(el);
    el.animate([ { transform: 'translate(-50%, -20px) scale(0.5)', opacity: 0 }, { transform: 'translate(-50%, -60px) scale(1.2)', opacity: 1, offset: 0.2 }, { transform: 'translate(-50%, -120px) scale(1)', opacity: 0 } ], { duration: 1200, easing: 'ease-out' }).onfinish = () => el.remove();
}

// --- PVP FUNCTIONS ---

window.openPlayerMenu = function(playerId) {
    const player = otherPlayers[playerId];
    if (!player) return;

    const existing = document.getElementById('player-menu-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'player-menu-modal';
    modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:2000;';
    
    modal.innerHTML = `
        <div style="background:#222; padding:20px; border:2px solid #555; border-radius:8px; min-width:200px; text-align:center; color:white; box-shadow:0 0 15px #000;">
            <h3 style="margin-top:0; color:#aaffaa;">${escapeHtml(player.name)}</h3>
            <div style="font-size:12px; color:#ccc; margin-bottom:15px;">Level ${player.level}</div>
            <button style="width:100%; margin-bottom:10px; padding:8px; cursor:pointer; background:#5a2; border:none; color:white; border-radius:4px;" onclick="sendDuelRequest(${playerId}); document.getElementById('player-menu-modal').remove()">⚔️ Challenge to duel</button>
            <button style="width:100%; padding:8px; cursor:pointer; background:#444; border:none; color:white; border-radius:4px;" onclick="document.getElementById('player-menu-modal').remove()">Cancel</button>
        </div>
    `;
    document.body.appendChild(modal);
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
}

window.sendDuelRequest = async function(targetId) {
    const res = await apiPost('send_duel_request', { target_id: targetId });
    if (res.status === 'success') showToast(res.message);
    else showToast(res.message, 'error');
}

function showDuelRequest(req) {
    if (document.getElementById(`duel-req-${req.id}`)) return; // Already showing
    
    const toast = document.createElement('div');
    toast.id = `duel-req-${req.id}`;
    toast.className = 'toast info';
    toast.style.animation = 'none'; // Persistent until clicked
    toast.innerHTML = `
        <div><strong>${escapeHtml(req.challenger_name)}</strong> challenges you!</div>
        <div style="margin-top:5px; display:flex; gap:10px;">
            <button onclick="respondDuel(${req.id}, 'accept')">Fight!</button>
            <button onclick="respondDuel(${req.id}, 'reject')">Reject</button>
        </div>
    `;
    document.getElementById('toast-container').appendChild(toast);
}

window.respondDuel = async function(reqId, response) {
    const el = document.getElementById(`duel-req-${reqId}`);
    if (el) el.remove();
    
    const res = await apiPost('respond_duel_request', { request_id: reqId, response: response });
    if (res.status === 'success' && response === 'accept') {
        gameState.is_pvp = true;
        toggleCombatMode(true, gameState.hp, 100);
        pollPvPState();
    }
}

async function pollPvPState() {
    if (!gameState.is_pvp || !gameState.in_combat) return;
    if (pvpPollInFlight) return;
    pvpPollInFlight = true;
    
    try {
        const res = await apiPost('get_duel_state');
        if (res.status === 'ended') {
            if (res.hp !== undefined) gameState.hp = parseInt(res.hp);
            pvpActionInFlight = false;
            toggleCombatMode(false);
            checkLifeStatus();
            if (gameState.hp > 0) showToast(res.message || "Duel ended.");
            return;
        }
        
        if (res.status === 'success') {
            const oldHp = gameState.hp;
            combatState = res.combat_state;
            gameState.hp = parseInt(res.my_hp);
            gameState.enemy_hp = parseInt(res.enemy_hp);
            gameState.enemy_max_hp = parseInt(res.enemy_max_hp);
            combatState.turn_remaining = res.turn_remaining;
            
            renderCombatArena();
            updateBar('combat-hp-bar', gameState.hp, gameState.max_hp);
            updateBar('combat-enemy-fill', gameState.enemy_hp, gameState.enemy_max_hp);
            document.getElementById('combat-hp').innerText = gameState.hp;
            document.getElementById('enemy-hp').innerText = gameState.enemy_hp;
            
            // Detect damage taken (Visuals/Audio for victim)
            if (gameState.hp < oldHp) {
                const dmg = oldHp - gameState.hp;
                playSoundEffect('hit');
                playSoundEffect('damage', dmg);
                const playerEl = document.getElementById('combat-player');
                if (playerEl) {
                    spawnCombatParticles(playerEl, '#d32f2f');
                    showFloatingDamage(playerEl, dmg, '#ff1744');
                    playerEl.style.filter = "brightness(0.5) sepia(1) hue-rotate(-50deg) saturate(5)";
                    setTimeout(() => { playerEl.style.filter = ""; }, 200);
                }
            }
            
            // Log is handled in renderCombatArena -> updateApDisplay
        }
    } finally {
        pvpPollInFlight = false;
    }
    
    setTimeout(pollPvPState, 500);
}

function canPvPAct() {
    if (!gameState.is_pvp || !gameState.in_combat || !combatState) return false;
    if (combatState.turn !== 'player') return false;
    if (pvpActionInFlight || pvpPollInFlight) return false;
    const now = Date.now();
    if (now - pvpLastActionAt < PVP_ACTION_COOLDOWN_MS) return false;
    return true;
}

async function handlePvPMove(x, y) {
    if (!canPvPAct()) return;
    pvpActionInFlight = true;
    pvpLastActionAt = Date.now();
    try {
        const res = await apiPost('pvp_action', { sub_action: 'move', x, y });
        if (res.status !== 'success') showToast(res.message || 'Invalid move', 'error');
    } finally {
        pvpActionInFlight = false;
    }
    pollPvPState();
}

async function handlePvPAttack() { 
    if (!canPvPAct()) return;
    pvpActionInFlight = true;
    pvpLastActionAt = Date.now();
    try {
        const res = await apiPost('pvp_action', { sub_action: 'attack' });
        if (res.status === 'success') {
            playSoundEffect('hit');
            const enemyEl = document.getElementById('combat-enemy');
            if (enemyEl && res.dmg) {
                 spawnCombatParticles(enemyEl, '#ffffff'); 
                 showFloatingDamage(enemyEl, res.dmg, '#ffeb3b');
            }
            if (res.win) {
                 showToast("You won the duel!");
                 setTimeout(() => {
                     toggleCombatMode(false);
                     initGame(); // Refresh state to get XP/Level up
                 }, 1500);
            }
        } else {
            showToast(res.message, 'error');
        }
    } finally {
        pvpActionInFlight = false;
    }
    pollPvPState();
}


function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerText = message;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.5s forwards';
        setTimeout(() => toast.remove(), 500);
    }, duration);
}

// --- UI SOUNDS ---
const uiClickSound = new Audio('assets/ui/misc_1.wav');
function playUiSound() {
    const sfx = uiClickSound.cloneNode();
    sfx.volume = sfxVolume;
    sfx.play().catch(() => {});
}

document.addEventListener('click', (e) => {
    if (e.target.closest('button, .class-card, .world-item, .close-x, .char-slot, .tab-btn, .icon-btn, a, input[type="checkbox"]')) {
        playUiSound();
    }
});

document.addEventListener('pointerdown', () => {
    if (!userStoppedMusic) {
        const gameLayout = document.getElementById('game-layout');
        if (isElementVisible(gameLayout)) {
            if (!isPlaying) playMusic();
        } else {
            const startScreen = document.getElementById('start-screen');
            const authModal = document.getElementById('auth-modal');
            const charSelection = document.getElementById('char-selection-modal');
            
            if (isElementVisible(startScreen) || isElementVisible(authModal) || isElementVisible(charSelection)) {
                if (waitingAudio.paused) playWaitingMusic();
            }
        }
    }
});

function updateDayNightCycle() {
    const overlay = document.getElementById('day-night-overlay');
    if (!overlay) return;

    const cfg = getGraphicsConfig();
    if (!cfg.dayNightEnabled) {
        overlay.style.backgroundColor = 'rgba(0, 0, 0, 0)';
        document.body.classList.remove('night-mode');
        return;
    }
    
    const hour = new Date().getHours();
    let color = 'rgba(0, 0, 0, 0)'; // Dzień (domyślnie)
    let isNight = false;
    const isGlaciem = gameState.world_id === 5;

    if (hour >= 21 || hour < 5) {
        color = isGlaciem ? 'rgba(0, 10, 30, 0.75)' : 'rgba(0, 5, 20, 0.6)'; // Noc
        isNight = true;
    } else if (hour >= 5 && hour < 8) {
        color = isGlaciem ? 'rgba(80, 120, 160, 0.35)' : 'rgba(200, 100, 50, 0.2)'; // Świt
    } else if (hour >= 17 && hour < 21) {
        color = isGlaciem ? 'rgba(30, 40, 70, 0.45)' : 'rgba(80, 40, 100, 0.3)'; // Zmierzch
    } else {
        if (isGlaciem) color = 'rgba(20, 30, 50, 0.25)'; // Zimny, mroczny dzień na Glaciem
    }
    
    overlay.style.backgroundColor = color;
    if (isNight) document.body.classList.add('night-mode');
    else document.body.classList.remove('night-mode');
}

// --- SHOP SYSTEM ---

window.openCityMenu = function() {
    const modal = document.getElementById('shop-modal');
    if (modal) {
        modal.style.display = 'flex';
        const goldEl = document.getElementById('shop-gold');
        if(goldEl) goldEl.innerHTML = formatCoins(gameState.gold, true);
        // Reset filter state to "All Items"
        window.currentShopType = 'leathersmith';
        window.currentShopClass = null;
        loadShop('leathersmith', modal.querySelector('.shop-tabs .tab-btn')); // Default load with all items
    }
}

window.loadShop = async function(type, btn, classId) {
    // Ensure shop state variables exist
    if (typeof window.currentShopType === 'undefined') window.currentShopType = 'leathersmith';
    if (typeof window.currentShopClass === 'undefined') window.currentShopClass = null;
    
    // Store current filter for switching between tabs
    // If switching merchant tab (type provided), reset class filter to null
    if (type !== null && type !== undefined) {
        window.currentShopType = type;
        window.currentShopClass = null; // Reset class filter when changing merchants
    }
    
    if (classId !== undefined) {
        window.currentShopClass = classId;
    }
    
    const merchantType = window.currentShopType || 'leathersmith';
    const selectedClass = window.currentShopClass;
    
    // Update merchant tab button active state
    document.querySelectorAll('#shop-modal .shop-tabs .tab-btn').forEach(b => b.classList.remove('active'));
    if(btn && btn.classList) {
        btn.classList.add('active');
    } else if (window.currentShopType) {
        // Re-activate the merchant tab by type
        const btns = Array.from(document.querySelectorAll('#shop-modal .shop-tabs .tab-btn'));
        const merchantMap = { 'leathersmith': 0, 'blacksmith': 1, 'armorer': 2, 'clergy': 3 };
        if (merchantMap[window.currentShopType] !== undefined) {
            btns[merchantMap[window.currentShopType]].classList.add('active');
        }
    }
    
    // Update class filter button states
    document.querySelectorAll('.class-filter-btn').forEach(b => b.style.opacity = '0.6');
    if (selectedClass === null || selectedClass === undefined) {
        // "All Items" button (index 3)
        const btns = Array.from(document.querySelectorAll('.class-filter-btn'));
        if (btns[3]) btns[3].style.opacity = '1';
    } else {
        // Class-specific button (Warrior=0, Mage=1, Rogue=2)
        const btns = Array.from(document.querySelectorAll('.class-filter-btn'));
        if (btns[selectedClass - 1]) btns[selectedClass - 1].style.opacity = '1';
    }
    
    const container = document.getElementById('shop-content');
    if (!container) return; // Safety check
    
    container.innerHTML = 'Loading...';
    
    const res = await apiPost('get_shop_data', { shop_type: merchantType, class_id: selectedClass });
    if (res.status === 'success') {
        container.innerHTML = '';
        if (res.items.length === 0) { 
            container.innerHTML = 'Out of stock.'; 
            return; 
        }
        
        res.items.forEach(item => {
            const row = document.createElement('div');
            row.style.cssText = "display:flex; justify-content:space-between; align-items:center; padding:10px; background:#252525; margin-bottom:5px; border-radius:4px;";
            row.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="font-size:24px;">${item.icon}</div>
                    <div>
                        <div style="color:#fff; font-weight:bold;">${item.name}</div>
                        <div style="font-size:11px; color:#888;">${item.description || 'No description'}</div>
                    </div>
                </div>
                <button onclick="buyItem(${item.id}, ${item.price})" style="background:linear-gradient(180deg, #7a4b1e, #4b2f16); border:2px solid #5c4a35; color:#f4d58d; padding:6px 10px; cursor:pointer; border-radius:3px; text-shadow:1px 1px 1px rgba(0,0,0,0.6); box-shadow:0 2px 0 #2b1a0c;">Buy (${formatCoins(item.price, true)})</button>
            `;
            container.appendChild(row);
        });
    }
}

window.loadSellTab = async function(btn) {
    document.querySelectorAll('#shop-modal .shop-tabs .tab-btn').forEach(b => b.classList.remove('active'));
    if(btn) btn.classList.add('active');
    
    const container = document.getElementById('shop-content');
    container.innerHTML = 'Loading inventory...';
    
    // Fetch fresh state to get inventory
    const res = await apiPost('get_state');
    if (res.status === 'success') {
        container.innerHTML = '';
        const sellable = res.data.inventory.filter(i => i.type === 'drop'); 
        
        if (sellable.length === 0) { container.innerHTML = 'No loot to sell.'; return; }
        
        sellable.forEach(item => {
            let sellPrice = item.price;
            if (item.item_value !== undefined && item.item_value !== null) {
                sellPrice = Math.max(1, item.item_value); // 100% for drops
            } else {
                sellPrice = Math.max(1, Math.floor(item.price * 0.6)); // 60% for shop items
            }
            const row = document.createElement('div');
            row.style.cssText = "display:flex; justify-content:space-between; align-items:center; padding:10px; background:#252525; margin-bottom:5px; border-radius:4px;";
            row.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="font-size:24px;">${item.icon}</div>
                    <div>
                        <div style="color:#fff; font-weight:bold;">${item.name} (x${item.quantity})</div>
                        <div style="font-size:11px; color:#aaa;">${item.rarity}</div>
                    </div>
                </div>
                <button onclick="sellItem(${item.item_id})" style="background:linear-gradient(180deg, #6b3a17, #3e240f); border:2px solid #5c4a35; color:#f4d58d; padding:6px 10px; cursor:pointer; border-radius:3px; text-shadow:1px 1px 1px rgba(0,0,0,0.7); box-shadow:0 2px 0 #2b1a0c;">Sell (${formatCoins(sellPrice, true)})</button>
            `;
            container.appendChild(row);
        });
    }
}

window.buyItem = async function(id, price) {
    if (gameState.gold < price) { showToast("Not enough coins!", "error"); return; }
    const res = await apiPost('buy_item', { item_id: id });
    if (res.status === 'success') {
        gameState.gold = res.gold;
        const gel = document.getElementById('shop-gold'); if(gel) gel.innerHTML = formatCoins(gameState.gold, true);
        updateUI({ gold: gameState.gold });
        showToast(res.message, "success");
        
        // Refresh inventory immediately
        const state = await apiPost('get_state');
        if (state.status === 'success') renderInventory(state.data.inventory);
        
    } else { showToast(res.message, "error"); }
}

window.sellItem = async function(id) {
    const res = await apiPost('sell_item', { item_id: id });
    if (res.status === 'success') {
        gameState.gold = res.gold;
        const gel = document.getElementById('shop-gold'); if(gel) gel.innerHTML = formatCoins(gameState.gold, true);
        updateUI({ gold: gameState.gold });
        showToast(res.message, "success");
        loadSellTab(document.querySelector('#shop-modal .tab-btn:last-child')); // Refresh list
    } else { showToast(res.message, "error"); }
}

window.toggleRightPanel = function() {
    const layout = document.getElementById('game-layout');
    if (!layout) return;

    layout.classList.toggle('panel-collapsed');

    const mobileToggle = document.getElementById('mobile-panel-toggle');
    if (mobileToggle) {
        mobileToggle.innerHTML = layout.classList.contains('panel-collapsed') ? '☰' : '✕';
    }

    // Re-center map immediately with a UI transition to match the panel animation
    if (typeof updatePlayerVisuals === 'function') {
        updatePlayerVisuals(gameState.x, gameState.y, true, true);
    }
}

function isElementVisible(el) {
    if (!el) return false;
    const style = window.getComputedStyle(el);
    return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
}

function isPlayerInSettlement() {
    const tile = document.querySelector(`.tile[data-x='${gameState.x}'][data-y='${gameState.y}']`);
    if (!tile) return false;
    return tile.classList.contains('city_capital') || tile.classList.contains('city_village');
}

function updateShopButtonVisibility() {
    const shopBtn = document.getElementById('shop-btn');
    if (!shopBtn) return;

    // Ukryj w trakcie walki
    if (gameState.in_combat) {
        shopBtn.style.display = 'none';
        return;
    }

    // Sprawdź typ kafelka z pamięci podręcznej dla większej niezawodności
    let isCity = false;
    if (latestTileCache && latestTileCache.length > 0) {
        const playerTile = latestTileCache.find(t => t.x == gameState.x && t.y == gameState.y);
        if (playerTile) {
            isCity = playerTile.type.includes('city');
        }
    } else {
        isCity = isPlayerInSettlement(); // Opcja zapasowa
    }
    
    shopBtn.style.display = isCity ? 'inline-flex' : 'none';
}

function closeOpenOverlayByPriority() {
    const closeMap = [
        { id: 'minimap-modal', close: () => toggleMinimap() },
        { id: 'mobile-disclaimer-modal', close: () => { document.getElementById('mobile-disclaimer-modal').style.display = 'none'; } },
        { id: 'item-menu-modal', close: () => { document.getElementById('item-menu-modal').style.display = 'none'; } },
        { id: 'skill-selection-modal', close: () => closeSkillSelectionModal() },
        { id: 'combat-skills-modal', close: () => closeCombatSkillsModal() },
        { id: 'planet-change-modal', close: () => closePlanetChangeModal() },
        { id: 'guilds-modal', close: () => { document.getElementById('guilds-modal').style.display = 'none'; } },
        { id: 'shop-modal', close: () => { document.getElementById('shop-modal').style.display = 'none'; } },
        { id: 'world-selection', close: () => { document.getElementById('world-selection').style.display = 'none'; } },
        { id: 'create-char-modal', close: () => closeCreateCharacter() },
        { id: 'char-selection-modal', close: () => closeCharacterSelection() },
        { id: 'combat-result-modal', close: () => closeCombatResult() },
        { id: 'settings-modal', close: () => toggleSettings() }
    ];

    for (const item of closeMap) {
        const modal = document.getElementById(item.id);
        if (isElementVisible(modal)) {
            item.close();
            return true;
        }
    }

    return false;
}

function handleEnterShortcut() {
    const authModal = document.getElementById('auth-modal');
    if (isElementVisible(authModal)) {
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');
        if (registerForm && registerForm.style.display !== 'none' && loginForm && loginForm.style.display === 'none') {
            handleRegister();
        } else {
            handleLogin();
        }
        return true;
    }

    const createCharModal = document.getElementById('create-char-modal');
    if (isElementVisible(createCharModal)) {
        submitNewCharacter();
        return true;
    }

    const gameLayout = document.getElementById('game-layout');
    if (isElementVisible(gameLayout) && !inCombatMode && isPlayerInSettlement()) {
        const settingsModal = document.getElementById('settings-modal');
        const shopModal = document.getElementById('shop-modal');
        if (!isElementVisible(settingsModal) && !isElementVisible(shopModal)) {
            openCityMenu();
            return true;
        }
    }

    return false;
}

function handleEscapeShortcut() {
    if (closeOpenOverlayByPriority()) {
        return true;
    }

    const gameLayout = document.getElementById('game-layout');
    if (isElementVisible(gameLayout) && !inCombatMode) {
        toggleSettings();
        return true;
    }

    return false;
}

function handleCombatShortcutKey(e) {
    if (!inCombatMode || !gameState.in_combat) return false;

    const combatScreen = document.getElementById('combat-screen');
    if (!isElementVisible(combatScreen)) return false;

    const blockedBy = [
        'combat-result-modal',
        'settings-modal',
        'skill-selection-modal',
        'planet-change-modal',
        'world-selection'
    ];
    for (const modalId of blockedBy) {
        if (isElementVisible(document.getElementById(modalId))) return false;
    }

    const key = String(e.key || '').toLowerCase();

    if (key === 'a') {
        handleCombatAttack();
        return true;
    }

    if (key === 'd') {
        handleCombatDefend();
        return true;
    }

    if (key >= '1' && key <= '4') {
        const selected = getSelectedSkillsDetailed();
        const index = parseInt(key, 10) - 1;
        const skill = selected[index];
        if (!skill) {
            showToast(`No skill in slot ${key}.`, 'error');
            return true;
        }
        handleCombatUseSkill(skill.id);
        return true;
    }

    return false;
}

document.addEventListener('keydown', (e) => {
    const active = document.activeElement;
    const tag = active?.tagName;
    const isTyping = !!active && (tag === 'INPUT' || tag === 'TEXTAREA' || active.isContentEditable);

    if (e.key === 'Escape') {
        if (handleEscapeShortcut()) {
            e.preventDefault();
        }
        return;
    }

    if (e.key === 'Enter') {
        if (handleEnterShortcut()) {
            e.preventDefault();
        }
        return;
    }

    if (isTyping) return;

    if (!e.repeat && handleCombatShortcutKey(e)) {
        e.preventDefault();
        return;
    }

    if (e.key === 'Tab') {
        e.preventDefault();
        toggleRightPanel();
    }
});

// Handle resize/orientation change to update map scale and centering
window.addEventListener('resize', () => {
    clearTimeout(window.resizeTimer);
    window.resizeTimer = setTimeout(() => {
        if (typeof updatePlayerVisuals === 'function' && gameState) {
            updatePlayerVisuals(gameState.x, gameState.y, true);
        }
        if (gameState && gameState.in_combat) {
            updateCombatCamera();
        }
    }, 200);
});

// --- COMBAT RESULT WINDOW ---
window.showCombatResult = function(xp, gold, loot, tutorialFinished) {
    const modal = document.getElementById('combat-result-modal');
    const content = document.getElementById('combat-result-content');
    
    // Store tutorial flag to handle it after closing
    modal.dataset.tutorialFinished = tutorialFinished ? "true" : "false";

    let html = `<div style="font-size:20px; margin-bottom:10px; font-weight:bold;">+${xp} XP</div>`;
    html += `<div style="font-size:20px; color:gold; margin-bottom:10px; font-weight:bold;">+${formatCoins(gold, true)}</div>`;
    
    if (loot) {
        html += `<div style="margin-top:20px; padding:15px; background:#333; border-radius:5px; border:1px solid #555;">
            <div style="color:#aaa; font-size:12px; margin-bottom:5px; text-transform:uppercase;">Loot Found</div>
            <div style="font-size:18px; color:#00e676; font-weight:bold;">${loot}</div>
        </div>`;
    }
    
    content.innerHTML = html;
    modal.style.display = 'flex';
}

window.closeCombatResult = async function() {
    const modal = document.getElementById('combat-result-modal');
    const tutorialFinished = modal.dataset.tutorialFinished === "true";
    
    modal.style.display = 'none';
    toggleCombatMode(false);
    
    // Refresh game state (XP, Level, Inventory, Quests) - use refreshState for faster updates
    await refreshState();
    await loadActiveQuests();

    if (tutorialFinished) {
        showWorldSelection();
    }
}

// --- RESPONSIVE UI HELPERS ---
function updateCombatBackground() {
    const leftPanel = document.getElementById('left-panel');
    const combatScreen = document.getElementById('combat-screen');
    if (leftPanel && combatScreen) {
        combatScreen.style.background = 'none';
        combatScreen.style.backgroundColor = '#050011';
        combatScreen.style.backgroundImage = "url('img/Starry background  - Layer 02 - Stars.png'), url('img/Starry background  - Layer 01 - Void.png')";
        combatScreen.style.backgroundRepeat = 'repeat-x';
        combatScreen.style.backgroundSize = 'auto 100%';
        combatScreen.style.backgroundPosition = '0 0, 0 0';
        combatScreen.style.animation = 'spaceScroll 60s linear infinite';
        combatScreen.style.boxShadow = 'none';
        combatScreen.style.filter = 'none';
    }
}

function applyResponsiveStyles() {
    if (!document.getElementById('transparent-ui-style')) {
        const style = document.createElement('style');
        style.id = 'transparent-ui-style';
        style.innerHTML = `
            #left-panel, #right-panel, .bottom-tabs, #bottom-bar {
                background-color: rgba(20, 20, 20, 0.75) !important;
                backdrop-filter: blur(5px);
            }
            .close-x {
                background: url('assets/ui/ex.png') center/contain no-repeat !important;
                color: transparent !important;
                border: none !important;
                width: 24px !important;
                height: 24px !important;
                min-width: 24px !important;
                min-height: 24px !important;
                padding: 0 !important;
                overflow: hidden;
                display: inline-block;
                filter: drop-shadow(0 0 2px #000);
                cursor: pointer;
            }
            .close-x:hover {
                transform: scale(1.1);
            }
            
            /* Poprawki dla modala Select World */
            #world-selection > div {
                position: relative !important;
                box-sizing: border-box !important;
                display: flex !important;
                flex-direction: column !important;
            }
            #world-selection > div > div:first-child {
                width: 100% !important;
            }
            #world-selection h2 {
                text-align: center !important;
                width: 100% !important;
                margin: 0 0 20px 0 !important;
            }
            #world-selection .close-x {
                position: absolute !important;
                top: 15px !important;
                right: 15px !important;
                margin: 0 !important;
            }
            #world-list {
                width: 100% !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
            }
            .world-item {
                width: 100% !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                text-align: center !important;
            }
        `;
        document.head.appendChild(style);
    }
}

function injectButtonSizeStyles() {
    const styleId = 'unified-button-styles';
    const existingStyle = document.getElementById(styleId);
    if (existingStyle) existingStyle.remove(); // Usuń istniejące style, aby je zaktualizować

    const style = document.createElement('style');
    style.id = styleId;
    style.innerHTML = `
        /* Ogólne style dla wszystkich trzech przycisków */
        #world-btn, #planet-change-btn, #shop-btn {
            width: 180px;
            height: 42px;
            padding: 0;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: none;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.5);
            transition: transform 0.1s, box-shadow 0.2s;
        }

        #world-btn:hover, #planet-change-btn:hover, #shop-btn:hover {
            transform: scale(1.03);
        }

        /* Specyficzny styl dla przycisku marketu */
        #shop-btn {
            background: gold;
            color: black;
            box-shadow: 0 0 10px #000;
        }

        @media (max-width: 900px) {
            .bottom-tabs {
                display: flex;
                justify-content: flex-end; /* Wyrównuje przyciski do prawej */
                gap: 5px; /* Zmniejsza odstęp między przyciskami */
                padding: 0 5px; /* Dodaje mały margines od krawędzi ekranu */
                align-items: center;
                height: 100%;
            }

            #world-btn, #planet-change-btn, #shop-btn {
                width: 125px;  /* Dodatkowo zmniejszona szerokość */
                height: 34px;  /* Dodatkowo zmniejszona wysokość */
                font-size: 12px; /* Dodatkowo zmniejszona czcionka */
            }
        }
    `;
    document.head.appendChild(style);
}
function updateCombatCamera() {
    const container = document.getElementById('combat-arena-container');
    const screen = document.getElementById('combat-screen');
    const arenaShell = document.getElementById('combat-arena-shell');
    if (!container || !screen) return;

    isMobile = Math.min(window.innerWidth, window.innerHeight) <= 900;
    if (!isMobile) {
        container.style.transform = '';
        container.style.transformOrigin = '';
        container.style.margin = '20px auto';
        container.style.transition = '';
        combatCameraState.initialized = false;
        return;
    }

    container.style.transition = 'transform 0.4s ease-out';
    container.style.willChange = 'transform';

    const isPortrait = window.innerHeight > window.innerWidth;
    const viewW = arenaShell ? arenaShell.clientWidth : screen.clientWidth;
    const viewH = arenaShell ? arenaShell.clientHeight : screen.clientHeight;
    const contW = container.offsetWidth || 1100;
    const contH = container.offsetHeight || 450;

    const fitScale = Math.min(viewW / contW, viewH / contH) * 0.95;
    let scale = Math.min(fitScale * (isPortrait ? 2.6 : 1.8), isPortrait ? 1.6 : 1.3);
    let moveX = 0;
    let moveY = 0;

    if (combatState) {
        const enemyEl = document.getElementById('combat-enemy');
        const playerEl = document.getElementById('combat-player');
        const target = (combatState.turn === 'enemy' ? enemyEl : playerEl) || playerEl || enemyEl;
        if (target) {
            const targetX = (parseFloat(target.style.left) || target.offsetLeft || 0) + (target.offsetWidth || 64) / 2;
            const targetY = (parseFloat(target.style.top) || target.offsetTop || 0) + (target.offsetHeight || 64) / 2;
            moveX = (viewW / 2) - (targetX * scale);
            moveY = (viewH / 2) - (targetY * scale);
        } else {
            moveX = (viewW - contW * scale) / 2;
            moveY = (viewH - contH * scale) / 2;
        }
    }

    // Clamp to keep arena visible
    const minX = Math.min(0, viewW - contW * scale);
    const maxX = Math.max(0, viewW - contW * scale);
    const minY = Math.min(0, viewH - contH * scale);
    const maxY = Math.max(0, viewH - contH * scale);
    if (!isPortrait) {
        moveX = Math.min(maxX, Math.max(minX, moveX));
        moveY = Math.min(maxY, Math.max(minY, moveY));
    }

    container.style.margin = '0';
    container.style.transformOrigin = '0 0';
    container.style.left = '0';
    container.style.top = '0';

    if (!combatCameraState.initialized) {
        container.style.transition = 'none';
        combatCameraState = { x: moveX, y: moveY, scale, initialized: true };
        void container.offsetHeight;
    } else {
        combatCameraState.x = moveX;
        combatCameraState.y = moveY;
        combatCameraState.scale = scale;
    }

    container.style.transform = `translate(${combatCameraState.x}px, ${combatCameraState.y}px) scale(${combatCameraState.scale})`;
}

// --- QUEST SYSTEM ---

// Item name mapping for quest display
const questItemNames = {
    20: 'Rat Tail',
    21: 'Goblin Ear',
    22: 'Bandit Insignia',
    23: 'Lava Core',
    24: 'Demon Horn'
};

function formatCurrency(amount) {
    if (amount >= 10000) {
        const gold = Math.floor(amount / 10000);
        const remainder = amount % 10000;
        const silver = Math.floor(remainder / 100);
        if (silver > 0) {
            return `${gold} gold, ${silver} silver`;
        }
        return `${gold} gold`;
    } else if (amount >= 100) {
        const silver = Math.floor(amount / 100);
        const copper = amount % 100;
        if (copper > 0) {
            return `${silver} silver, ${copper} copper`;
        }
        return `${silver} silver`;
    }
    return `${amount} copper coins`;
}

async function loadQuestsTab(btn) {
    if (btn) {
        document.querySelectorAll('.shop-tabs .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    
    const container = document.getElementById('shop-content');
    const res = await apiPost('get_available_quests', {});
    
    console.log('loadQuestsTab response:', res); // DEBUG
    
    if (res.status === 'error') {
        container.innerHTML = '<div style="text-align:center; color:#f44336; padding:20px;">Error: ' + (res.message || 'Unknown error') + '</div>';
        return;
    }
    
    if (res.status === 'success') {
        container.innerHTML = '<h3 style="margin-top:0; color:#ffd700;">Available Quests</h3>';
        
        console.log('Number of quests:', res.quests.length); // DEBUG
        
        if (res.quests.length === 0) {
            container.innerHTML += '<div style="text-align:center; color:#666; padding:20px;">No quests available.</div>';
            return;
        }
        
        res.quests.forEach(quest => {
            const reqItems = quest.required_items.map(item => {
                const itemName = questItemNames[item.id] || `Item #${item.id}`;
                return `<span style="color:#aaa;">${item.quantity}x ${itemName}</span>`;
            }).join(', ');
            
            const isGuildQuest = (quest.guild_required == 1 || quest.guild_required === true);
            const guildBadge = isGuildQuest
                ? '<span style="background:#9c27b0; color:white; padding:3px 8px; border-radius:3px; font-size:10px; margin-left:8px;">⚔️ GUILD</span>' 
                : '';
            
            const div = document.createElement('div');
            const borderColor = isGuildQuest ? '#9c27b0' : '#ffd700';
            div.style.cssText = `background:#252525; padding:15px; margin-bottom:10px; border-radius:5px; border-left:3px solid ${borderColor};`;
            div.innerHTML = `
                <div style="font-weight:bold; color:#ffd700; font-size:15px; margin-bottom:5px; display:flex; align-items:center;">
                    ${quest.title}${guildBadge}
                </div>
                <div style="color:#ccc; font-size:13px; margin-bottom:8px;">${quest.description}</div>
                <div style="color:#888; font-size:12px; margin-bottom:5px;">Required: ${reqItems}</div>
                <div style="color:#888; font-size:12px; margin-bottom:10px;">
                    Reward: <span style="color:#4caf50;">${formatCurrency(quest.reward_gold)}</span>, 
                    <span style="color:#2196f3;">+${quest.reward_reputation} reputation</span>
                </div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <button onclick="acceptQuest(${quest.id})" class="combat-btn" style="padding:8px 15px; font-size:12px; background:#4caf50;">Accept Quest</button>
                    <span style="color:#666; font-size:11px;">Min Level: ${quest.min_level}</span>
                </div>
            `;
            container.appendChild(div);
        });
    }
}

async function acceptQuest(questId) {
    const res = await apiPost('accept_quest', { quest_id: questId });
    if (res.status === 'success') {
        showToast(res.message || 'Quest accepted!', 'success');
        await loadQuestsTab();
        await loadActiveQuests();
    } else {
        showToast(res.message || 'Failed to accept quest', 'error');
    }
}

// --- DAILY QUEST SYSTEM ---

window.currentDailyQuest = null;

async function loadDailyQuest() {
    const res = await apiPost('init_daily_quest', {});
    if (res.status === 'success') {
        window.currentDailyQuest = res.daily_quest;
        displayDailyQuest(res.daily_quest);
    } else {
        console.error('Failed to load daily quest:', res.message);
    }
}

function displayDailyQuest(quest) {
    const container = document.getElementById('daily-quest-container');
    const display = document.getElementById('daily-quest-display');
    const btn = document.getElementById('daily-quest-btn');
    
    if (!container || !display) return;
    
    container.style.display = 'block';
    
    const itemsHtml = (quest.required_items || []).map(item => {
        const itemName = questItemNames[item.id] || `Item #${item.id}`;
        return `${item.quantity}x ${itemName}`;
    }).join(', ');
    
    display.innerHTML = `
        <div style="margin-bottom:5px;"><strong>${quest.title}</strong></div>
        <div style="font-size:11px; color:#c9a875; margin-bottom:5px;">${quest.description}</div>
        <div style="font-size:11px; border-top:1px solid rgba(212,175,55,0.3); padding-top:6px;">
            Required: ${itemsHtml}<br>
            Reward: <span style="color:#d4af37;">${quest.reward_gold} coins</span>, +${quest.reward_reputation} reputation
        </div>
    `;
    
    btn.textContent = 'Complete Daily Quest';
    btn.disabled = false;
}

window.completeCurrentDaily = async function() {
    if (!window.currentDailyQuest) {
        showToast('No active daily quest.', 'error');
        return;
    }
    
    const res = await apiPost('complete_daily_quest', { quest_id: window.currentDailyQuest.id });
    if (res.status === 'success') {
        showToast(res.message + ` +${res.reward_gold} coins, +${res.reward_reputation} rep`, 'success');
        const btn = document.getElementById('daily-quest-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = '✓ Completed!';
        }
        // Refresh character gold and other stats
        const state = await apiPost('get_state');
        if (state.status === 'success') {
            updateUI(state.data);
        }
    } else {
        showToast(res.message || 'Failed to complete daily quest', 'error');
    }
};

async function loadActiveQuests() {
    const res = await apiPost('get_active_quests');
    
    if (res.status === 'success') {
        const container = document.getElementById('active-quests-list');
        const repVal = document.getElementById('reputation-val');
        
        // Update reputation display
        const repRes = await apiPost('get_reputation');
        if (repRes.status === 'success') {
            repVal.textContent = repRes.reputation || 0;
        }
        
        if (res.quests.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:#666; padding:20px; font-size:13px;">No active quests</div>';
            return;
        }
        
        container.innerHTML = '';
        res.quests.forEach(quest => {
            const progressHtml = quest.required_items.map(item => {
                const has = quest.progress[item.id] || 0;
                const needed = item.quantity;
                const color = has >= needed ? '#4caf50' : '#f44336';
                const itemName = questItemNames[item.id] || `Item #${item.id}`;
                return `<div style="color:${color}; font-size:12px;">${has}/${needed} x ${itemName}</div>`;
            }).join('');
            
            const div = document.createElement('div');
            div.style.cssText = "background:#252525; padding:12px; border-radius:5px; border-left:3px solid " + (quest.can_complete ? '#4caf50' : '#666');
            div.innerHTML = `
                <div style="font-weight:bold; color:#ffd700; font-size:14px; margin-bottom:5px;">${quest.title}</div>
                <div style="color:#aaa; font-size:12px; margin-bottom:8px;">${quest.description}</div>
                <div style="margin-bottom:8px;">${progressHtml}</div>
                <div style="display:flex; gap:5px;">
                    ${quest.can_complete ? 
                        `<button onclick="completeQuest(${quest.char_quest_id})" class="combat-btn" style="padding:5px 10px; font-size:11px; background:#4caf50;">Complete</button>` : 
                        `<span style="color:#666; font-size:11px;">Collect required items</span>`
                    }
                    <button onclick="abandonQuest(${quest.char_quest_id})" class="combat-btn" style="padding:5px 10px; font-size:11px; background:#d32f2f;">Abandon</button>
                </div>
            `;
            container.appendChild(div);
        });
    }
}

async function completeQuest(charQuestId) {
    const res = await apiPost('complete_quest', { char_quest_id: charQuestId });
    if (res.status === 'success') {
        showToast(res.message, 'success');
        // Update reputation immediately from response
        const repVal = document.getElementById('reputation-val');
        if (repVal && res.reputation !== undefined) {
            repVal.textContent = res.reputation;
        }
        await refreshState();      // Refresh inventory
        await loadActiveQuests();   // Refresh quests
        playSfx('pickup');
    } else {
        showToast(res.message, 'error');
    }
}

async function refreshState() {
    const res = await apiPost('get_state');
    if (res.status === 'success') {
        updateLocalState(res.data);
        updateUI(res.data);
        renderInventory(res.data.inventory);
    }
}

async function abandonQuest(charQuestId) {
    if (!confirm('Are you sure you want to abandon this quest?')) return;
    
    const res = await apiPost('abandon_quest', { char_quest_id: charQuestId });
    if (res.status === 'success') {
        showToast(res.message, 'success');
        await loadActiveQuests();
    } else {
        showToast(res.message, 'error');
    }
}

async function openGuildsModal() {
    document.getElementById('guilds-modal').style.display = 'flex';
    await loadGuilds();
}

async function loadGuilds() {
    const res = await apiPost('get_guilds');
    
    if (res.status === 'success') {
        const container = document.getElementById('guilds-content');
        const repVal = document.getElementById('guild-reputation-val');
        
        repVal.textContent = res.reputation;
        
        if (res.guilds.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:#666; padding:20px;">No guilds available.</div>';
            return;
        }
        
        container.innerHTML = '';
        res.guilds.forEach(guild => {
            const div = document.createElement('div');
            div.style.cssText = "background:#252525; padding:15px; margin-bottom:10px; border-radius:5px; border-left:3px solid " + 
                (guild.is_member ? '#4caf50' : guild.can_join ? '#ffd700' : '#666');
            
            div.innerHTML = `
                <div style="font-weight:bold; color:#ffd700; font-size:16px; margin-bottom:5px;">${guild.name}</div>
                <div style="color:#ccc; font-size:13px; margin-bottom:10px;">${guild.description}</div>
                <div style="color:#888; font-size:12px; margin-bottom:10px;">Required Reputation: ${guild.required_reputation}</div>
                ${guild.is_member ? 
                    '<div style="color:#4caf50; font-weight:bold;">✓ Member</div>' : 
                    guild.can_join ? 
                        `<button onclick="joinGuild(${guild.id})" class="combat-btn" style="padding:8px 15px; font-size:12px; background:#4caf50;">Join Guild</button>` :
                        `<div style="color:#666; font-size:12px;">Insufficient reputation (${res.reputation}/${guild.required_reputation})</div>`
                }
            `;
            container.appendChild(div);
        });
    }
}

async function joinGuild(guildId) {
    const res = await apiPost('join_guild', { guild_id: guildId });
    if (res.status === 'success') {
        showToast(res.message, 'success');
        await loadGuilds();
        playSfx('levelup');
    } else {
        showToast(res.message, 'error');
    }
}

// --- PLANET CHANGE SYSTEM ---
function updatePlanetChangeButtonVisibility() {
    const btn = document.getElementById('planet-change-btn');
    if (!btn) return;
    
    const level = gameState.level || 1;
    if (level >= 15) {
        btn.style.display = 'block';
    } else {
        btn.style.display = 'none';
    }
}

async function openPlanetChangeModal() {
    const modal = document.getElementById('planet-change-modal');
    if (!modal) return;
    
    // Just show the modal - cost will be checked when they try to travel
    modal.style.display = 'flex';
}

function closePlanetChangeModal() {
    const modal = document.getElementById('planet-change-modal');
    if (modal) modal.style.display = 'none';
}

async function purchasePlanetTravel(planetName) {
    const res = await apiPost('get_state');
    if (res.status !== 'success') {
        showToast('Failed to check player status.', 'error');
        return;
    }
    
    const level = parseInt(res.data.level) || 1;
    const gold = parseInt(res.data.gold) || 0;
    
    // Planet-specific requirements
    const planetRequirements = {
        'Terra': { min_level: 0, cost_copper: 0, description: 'Free - Your home planet' },
        'Glaciem': { min_level: 15, cost_copper: 1500, description: '15 Silver (1500 Copper)' },
        'Solaris': { min_level: 30, cost_copper: 10000, description: '1 Gold (10000 Copper)' }
    };
    
    if (!planetRequirements[planetName]) {
        showToast('Unknown planet.', 'error');
        return;
    }
    
    const req = planetRequirements[planetName];
    
    // Check level requirement
    if (level < req.min_level) {
        showToast(`You need level ${req.min_level} to travel to ${planetName}! Current: ${level}/${req.min_level}`, 'error');
        return;
    }
    
    // Check cost
    if (req.cost_copper > 0 && gold < req.cost_copper) {
        const needed = req.cost_copper - gold;
        showToast(`Not enough coins! Need ${req.cost_copper} copper, you have ${gold}. Missing: ${needed} copper`, 'error');
        return;
    }
    
    // Send teleport request to API
    const teleRes = await apiPost('teleport_planet', { planet_name: planetName, cost: req.cost_copper });
    if (teleRes.status === 'success') {
        showToast(`✨ Traveled to ${planetName}!`, 'success');
        closePlanetChangeModal();
        
        playTransitionOverlay(async () => {
            if (typeof initGame === 'function') {
                await initGame();
                showRespawnEffect();
            } else {
                window.location.reload();
            }
        });
    } else {
        showToast(teleRes.message || 'Travel failed.', 'error');
    }
}
// Wrap original switchTab to load quests when switching to quest tab
const _originalSwitchTab = switchTab;
switchTab = function(tabName) {
    if (tabName === 'quests') {
        loadActiveQuests();
    }
    _originalSwitchTab(tabName);
};

window.toggleMinimap = function() {
    let modal = document.getElementById('minimap-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'minimap-modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); display:none; justify-content:center; align-items:center; z-index:10500;';
        modal.innerHTML = `
            <div style="background:#1a1a1a; padding:20px; border:2px solid #444; border-radius:10px; text-align:center; box-shadow:0 0 20px #000; width: 90vw; max-width: 800px; height: 85vh; display: flex; flex-direction: column;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2 style="color:gold; margin:0;">🗺️ World Map</h2>
                    <img src="assets/ui/ex.png" onclick="toggleMinimap()" style="width:28px; height:28px; cursor:pointer; transition:transform 0.1s; filter:drop-shadow(0 0 2px #000);" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" alt="X">
                </div>
                <div id="minimap-scroll-container" style="overflow:auto; background:#050011; border:1px solid #333; flex-grow:1; display:flex; padding: 20px; position:relative; border-radius: 5px;">
                    <canvas id="minimap-canvas" style="image-rendering:pixelated; margin: auto;"></canvas>
                </div>
                <div style="color:#aaa; font-size:12px; margin-top:10px;">Explore the world to reveal more tiles. Red dot is your position.</div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.onclick = (e) => { if (e.target === modal) toggleMinimap(); };
    }
    
    if (modal.style.display === 'none' || !modal.style.display) {
        modal.style.display = 'flex';
        drawMinimap();
    } else {
        modal.style.display = 'none';
    }
};

function drawMinimap() {
    const canvas = document.getElementById('minimap-canvas');
    const container = document.getElementById('minimap-scroll-container');
    if (!canvas || !gameState.world_id) return;
    const wid = gameState.world_id;
    const tiles = discoveredTiles[wid] || {};
    
    const keys = Object.keys(tiles);
    if (keys.length === 0) return;

    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    keys.forEach(k => {
        const [xStr, yStr] = k.split('_');
        const x = parseInt(xStr), y = parseInt(yStr);
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
    });

    const pad = 3;
    minX = Math.max(0, minX - pad);
    maxX += pad;
    minY = Math.max(0, minY - pad);
    maxY += pad;

    const gridW = maxX - minX + 1;
    const gridH = maxY - minY + 1;
    
    const TILE_W = 12; 
    const TILE_H = 7; 
    canvas.width = gridW * TILE_W + (TILE_W / 2);
    canvas.height = gridH * TILE_H;

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const colors = {
        'grass': '#4caf50', 'grass2': '#8bc34a', 'forest': '#2e7d32',
        'mountain': '#757575', 'water': '#1e88e5', 'city_capital': '#ffd700',
        'city_village': '#ff9800', 'hills': '#a1887f', 'hills2': '#8d6e63',
        'farmlands': '#d4e157',
        'wgrass': '#e0f7fa', 'wgrass2': '#b2ebf2', 'wforest': '#4dd0e1',
        'wmountain': '#9e9e9e', 'wwater': '#4fc3f7', 'whills': '#cfd8dc',
        'whills2': '#b0bec5', 'wfarmlands': '#fff59d'
    };

    keys.forEach(k => {
        const [xStr, yStr] = k.split('_');
        const x = parseInt(xStr), y = parseInt(yStr);
        const type = tiles[k];
        
        const offsetX = (y % 2 !== 0) ? (TILE_W / 2) : 0;
        const px = (x - minX) * TILE_W + offsetX;
        const py = (y - minY) * TILE_H;

        ctx.fillStyle = colors[type] || '#333';
        ctx.fillRect(px, py, TILE_W + 1, TILE_H + 1); // +1 ukrywa ewentualne przerwy pomiędzy pikselami
    });

    // Kropka gracza
    const pxX = (gameState.x - minX) * TILE_W + ((gameState.y % 2 !== 0) ? (TILE_W / 2) : 0);
    const pxY = (gameState.y - minY) * TILE_H;
    
    ctx.fillStyle = '#ff1744';
    ctx.beginPath();
    ctx.arc(pxX + TILE_W/2, pxY + TILE_H/2, Math.max(3, TILE_H/1.5), 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 1;
    ctx.stroke();

    // Wycentrowanie Scrolla automatycznie na postać
    if (container) {
        setTimeout(() => {
            const padding = 20; 
            const targetScrollLeft = pxX + padding - container.clientWidth / 2;
            const targetScrollTop = pxY + padding - container.clientHeight / 2;
            
            container.scrollTo({
                left: Math.max(0, targetScrollLeft),
                top: Math.max(0, targetScrollTop),
                behavior: 'smooth'
            });
        }, 10);
    }
}
