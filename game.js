const mapSize = 20; 
let playerMarker = document.createElement('div');
playerMarker.classList.add('player');

let HEX_WIDTH = 150;   
let HEX_HEIGHT = 44; 

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
    stat_points: 0, base_attack: 1, base_defense: 0
};

let inCombatMode = false;
let combatState = null;
let lowEnergyWarningShown = false;
let isProcessingTurn = false;
let combatCameraOverviewUntil = 0;
let combatCameraState = { x: 0, y: 0, scale: 1, initialized: false };
let mapScaleCache = { w: 0, h: 0, portrait: null, landscape: null };

// --- AUDIO ---
const AUDIO_PATHS = {
    walk: Array.from({length: 8}, (_, i) => `assets/walking/stepdirt_${i+1}.wav`),
    hit: ['assets/combat/damage/Hit 1.wav', 'assets/combat/damage/Hit 2.wav'],
    damage: Array.from({length: 10}, (_, i) => `assets/combat/damage/damage_${i+1}_ian.wav`),
    combatMusic: "assets/combat/If It's a Fight You Want.ogg"
};

const playlist = ['assets/Journey Across the Blue.ogg', 'assets/World Travelers.ogg'];
let explorationAudio = new Audio();
let combatAudio = new Audio(AUDIO_PATHS.combatMusic);
combatAudio.loop = true;
let isPlaying = false;
explorationAudio.volume = 0.2;
combatAudio.volume = 0.2;
let sfxVolume = 0.3;

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

function playSoundEffect(category, damageValue = 0) {
    let src = '';
    if (category === 'walk') { src = AUDIO_PATHS.walk[Math.floor(Math.random() * AUDIO_PATHS.walk.length)]; } 
    else if (category === 'hit') { src = AUDIO_PATHS.hit[Math.floor(Math.random() * AUDIO_PATHS.hit.length)]; } 
    else if (category === 'damage') { let index = Math.ceil(damageValue / 2); if (index < 1) index = 1; if (index > 10) index = 10; src = AUDIO_PATHS.damage[index - 1]; }
    if (src) { const sfx = new Audio(src); sfx.volume = sfxVolume; sfx.play().catch(() => {}); }
}

function startWalkingSound() { if (stepInterval) return; playSoundEffect('walk'); stepInterval = setInterval(() => { playSoundEffect('walk'); }, 400); }
function stopWalkingSound() { if (stepInterval) { clearInterval(stepInterval); stepInterval = null; } }

// --- START ---

function startGame() {
    document.getElementById('start-screen').style.display = 'none';
    document.getElementById('game-layout').style.display = 'flex';
    applyResponsiveStyles();
    playRandomTrack();
    isPlaying = true;
    
    // Inject Gold Display and Shop Button if missing
    if (!document.getElementById('gold-display')) {
        const statsPanel = document.getElementById('left-panel'); // Assuming left panel exists
        if (statsPanel) {
            const goldDiv = document.createElement('div');
            goldDiv.id = 'gold-display';
            goldDiv.style.cssText = "font-size:18px; color:gold; margin:10px 0; font-weight:bold; text-shadow:1px 1px 0 #000;";
            goldDiv.innerText = "0 G";
            // Insert after XP bar or somewhere visible
            const xpContainer = document.getElementById('xp-container');
            if (xpContainer) xpContainer.parentNode.insertBefore(goldDiv, xpContainer.nextSibling);
            else statsPanel.prepend(goldDiv);
        }
        
        // Shop Button
        const actionArea = document.getElementById('action-area') || document.body; // Fallback
        const shopBtn = document.createElement('button');
        shopBtn.id = 'shop-btn';
        shopBtn.innerText = "🏰 Enter Market";
        shopBtn.style.cssText = "position:absolute; bottom:20px; left:50%; transform:translateX(-50%); padding:10px 20px; background:gold; color:black; border:none; font-weight:bold; cursor:pointer; border-radius:5px; display:none; z-index:2000; box-shadow:0 0 10px #000;";
        shopBtn.onclick = () => openCityMenu();
        document.body.appendChild(shopBtn);
    }

    const btn = document.getElementById('music-btn');
    if(btn) { btn.innerText = '🔊'; btn.classList.add('playing'); }
    initGame();
    updateDayNightCycle();
    setInterval(updateDayNightCycle, 60000);
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
            
            // UI Świata
            document.getElementById('world-info').innerText = json.data.world_name || 'Unknown world';
            updateUI(json.data);
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
            }
            renderInventory(json.data.inventory);
            checkLifeStatus();
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
            const btn = document.getElementById('world-btn');
            if (btn) btn.style.display = gameState.tutorial_completed ? 'inline-block' : 'none';
        } else {
            console.warn('get_state failed', json);
        }
    } catch (e) {
        console.error('checkTutorialStatus error', e);
    }
}
async function showWorldSelection() {
    try {
        const data = await apiPost('get_worlds_list');
        const modal = document.getElementById('world-selection');
        const list = document.getElementById('world-list');
        if (!modal || !list) return;
        list.innerHTML = '';

        if (data.status === 'success' && Array.isArray(data.worlds) && data.worlds.length) {
            data.worlds.forEach(w => {
                const el = document.createElement('div');
                el.className = 'world-item';
                el.style.cursor = 'pointer';
                el.innerHTML = `<strong>${escapeHtml(w.name)}</strong>
                                <div style="font-size:12px;color:#ccc">${w.width}x${w.height} • ${w.player_count} graczy</div>`;
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
        const data = await apiPost('join_world', { world_id: worldId });
        if (data.status === 'success') {
            const modal = document.getElementById('world-selection');
            if (modal) modal.style.display = 'none';
            if (typeof initGame === 'function') await initGame();
            else location.reload();
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
async function apiPost(action, body = {}) {
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action }, body))
        });
        return await res.json();
    } catch (e) {
        console.error('apiPost error', e);
        return { status: 'error', message: 'Network error' };
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
    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_map' })
    });
    const result = await res.json();
    if (result.tiles) renderMapTiles(result.tiles);
}

function renderMapTiles(tiles) {
    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;

    // Remove old tiles but keep player marker
    mapDiv.querySelectorAll('.tile').forEach(e => e.remove());
    if (!playerMarker.parentNode) mapDiv.appendChild(playerMarker);

    tiles.forEach(t => { 
        const tile = document.createElement('div');
        tile.className = `tile ${t.type}`;
        
        let offsetX = (parseInt(t.y) % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
        let posX = (parseInt(t.x) * HEX_WIDTH) + offsetX;
        let posY = (parseInt(t.y) * HEX_HEIGHT);
        if (t.type === 'mountain') posY -= 20; 
        if (t.type === 'city_capital') {
            posY -= 5;
            for(let i=0; i<3; i++) {
                const s = document.createElement('div');
                s.className = 'smoke-particle';
                s.style.left = '60px'; s.style.top = '40px'; 
                s.style.animationDelay = (i * 0.8) + 's';
                tile.appendChild(s);
            }
        }
        if (t.type === 'city_village') {
            posY -= 10; // Moved up by 10px total (5px more than before)
            for(let i=0; i<3; i++) {
                const s = document.createElement('div');
                s.className = 'smoke-particle';
                s.style.left = '75px'; s.style.top = '50px'; 
                s.style.animationDelay = (i * 0.8) + 's';
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
    });
}

function updatePlayerVisuals(x, y, isInstant = false) {
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

            setAnimationState('run');
            
            playerMarker.style.transition = `top ${duration}s linear, left ${duration}s linear`;
            playerMarker.style.left = targetPixelX + 'px';
            playerMarker.style.top = targetPixelY + 'px';

            if (moveTimeout) clearTimeout(moveTimeout);
            moveTimeout = setTimeout(() => { setAnimationState('idle'); }, duration * 1000);
        }
        playerMarker.style.zIndex = 1000; 
        centerMapOnPlayer(tLeft, tTop);
    }
}

function centerMapOnPlayer(pixelX, pixelY) {
    const panel = document.getElementById('left-panel');
    const map = document.getElementById('map');
    if (!panel || !map) return;

    let viewportWidth = panel.offsetWidth;
    let viewportHeight = panel.offsetHeight;
    
    // Mobile adjustments (auto-zoom based on viewport width)
    let scale = 1;
    let offsetY = 0;
    const isPortrait = window.innerHeight > window.innerWidth;
    const isMobile = Math.min(window.innerWidth, window.innerHeight) <= 900;

    if (isMobile) {
        scale = isPortrait ? 0.68 : 0.62;

        const layout = document.getElementById('game-layout');
        const rightPanel = document.getElementById('right-panel');
        if (isPortrait && layout && rightPanel && !layout.classList.contains('panel-collapsed')) {
            const panelHeight = rightPanel.getBoundingClientRect().height || 0;
            offsetY = -(panelHeight * 0.2);
        }
    }

    // Calculate center based on scale (transform-origin is 0 0)
    const moveX = (viewportWidth / 2) - (pixelX + 64) * scale;
    const moveY = (viewportHeight / 2) + offsetY - (pixelY + 64) * scale;

    map.style.transform = `translate(${moveX}px, ${moveY}px) scale(${scale})`;

    // Debug: show live scale on mobile
    if (isMobile) {
        let dbg = document.getElementById('debug-scale');
        if (!dbg) {
            dbg = document.createElement('div');
            dbg.id = 'debug-scale';
            dbg.style.cssText = "position:fixed; bottom:8px; left:8px; z-index:9999; padding:4px 8px; background:rgba(0,0,0,0.6); color:#0f0; font-size:12px; border:1px solid #0f0; border-radius:4px;";
            document.body.appendChild(dbg);
        }
        dbg.textContent = `scale: ${scale.toFixed(3)}`;
    }
}

function setAnimationState(newState) {
    if (currentAnimState === newState) return;
    currentAnimState = newState; currentFrameIndex = 0; updatePlayerSprite();
    if (newState === 'run') { startWalkingSound(); } else { stopWalkingSound(); }
}

function startPlayerAnimation() {
    if (animationInterval) clearInterval(animationInterval);
    updatePlayerSprite();
    animationInterval = setInterval(() => {
        currentFrameIndex++;
        if (currentFrameIndex >= playerSprites[currentAnimState].length) currentFrameIndex = 0;
        updatePlayerSprite();
    }, ANIMATION_SPEED);
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

    const res = await fetch('api.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'move', x: targetX, y: targetY })
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
        
        // Check if in city to show shop button
        const isCity = document.querySelector(`.tile[data-x='${result.new_x}'][data-y='${result.new_y}']`).className.includes('city');
        const shopBtn = document.getElementById('shop-btn');
        if (shopBtn) shopBtn.style.display = isCity ? 'block' : 'none';

        if (result.local_tiles) {
        renderMapTiles(result.local_tiles);
        }
        
        updateUI(result);
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
    // Simple usage logic for consumables
    if (item.item_id == 7 || item.item_id == 8) { // Bandage or Potion
        if (gameState.in_combat) {
            useItem(item.item_id); // Combat usage
        } else {
            // Out of combat usage
            const res = await apiPost('use_item', { item_id: item.item_id });
            if (res.status === 'success') {
                gameState.hp = parseInt(res.hp);
                updateUI({ hp: gameState.hp });
                showToast(res.message, 'success');
                // Refresh inventory
                const state = await apiPost('get_state');
                if (state.status === 'success') renderInventory(state.data.inventory);
            } else {
                showToast(res.message, 'error');
            }
        }
    } else {
        // Show info for non-consumables
        showToast(`${item.name}: ${item.description || 'Item'}`, 'info');
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
    const expandPanelBtn = document.getElementById('expand-panel-btn');
    const mobilePanelToggle = document.getElementById('mobile-panel-toggle');
    inCombatMode = active; gameState.in_combat = active;
    if (active) isProcessingTurn = false;
    if (document.body) document.body.classList.toggle('combat-active', active);

    if (gameLayout) gameLayout.style.display = active ? 'none' : 'flex';
    if (topLeftUi) topLeftUi.style.display = active ? 'none' : '';
    if (rightPanel) rightPanel.style.display = active ? 'none' : '';
    if (worldBtn) worldBtn.style.display = active ? 'none' : worldBtn.style.display;
    if (shopBtn) shopBtn.style.display = active ? 'none' : shopBtn.style.display;
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
        combatCameraOverviewUntil = Date.now() + 1200;
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
    } else {
        mapDiv.style.display = 'block'; combatScreen.style.display = 'none';
        combatState = null;
        gameState.is_pvp = false;
        stopCombatAnimations();
        combatCameraOverviewUntil = 0;
        loadAndDrawMap();
        updatePlayerVisuals(gameState.x, gameState.y, true);
        startMultiplayerPolling(); // Resume polling after combat
    }
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
    updateCombatCamera();
    
    // Only trigger AI turn if it's PvE
    if (!gameState.is_pvp && combatState.turn === 'enemy' && !isProcessingTurn) setTimeout(handleEnemyTurn, 500);
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
        el.style.backgroundImage = `url('assets/player/Idle1.png')`;
        el.style.backgroundImage = `url('assets/player/idle1.png?v=2')`;
        // Smooth transition for movement
        el.style.transition = "left 0.6s linear, top 0.6s linear, filter 0.2s";
        
        if (type === 'enemy') { 
            el.style.transform = "scaleX(-1)"; 
            el.onclick = () => { if(combatState.turn === 'player') { if(gameState.is_pvp) handlePvPAttack(); else handleCombatAttack(); } }; 
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
        // Enemy visuals based on type
        let hue = "150deg"; // Standard (Blue/Purpleish)
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
        // Player visuals
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
    combatFrameIndex = 0;
    combatAnimInterval = setInterval(() => {
        combatFrameIndex++;
        updateCombatSprites();
    }, ANIMATION_SPEED);
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
    if(data.stat_points !== undefined) gameState.stat_points = parseInt(data.stat_points);
    if(data.base_attack !== undefined) gameState.base_attack = parseInt(data.base_attack);
    if(data.base_defense !== undefined) gameState.base_defense = parseInt(data.base_defense);
    if(data.name) gameState.name = data.name;
    if(data.id) gameState.id = parseInt(data.id);
}

function updateUI(data) {
    if(!data) return;
    if(data.hp !== undefined) { const maxHp = data.max_hp || gameState.max_hp; document.getElementById('hp').innerText = `${data.hp} / ${maxHp}`; document.getElementById('hp-fill').style.width = (data.hp / maxHp * 100) + '%'; }
    if(data.energy !== undefined) { const maxEn = data.max_energy || gameState.max_energy; document.getElementById('energy').innerText = `${data.energy} / ${maxEn}`; document.getElementById('en-fill').style.width = (data.energy / maxEn * 100) + '%'; }
    if(data.steps_buffer !== undefined) document.getElementById('steps-info').innerText = data.steps_buffer + '/10';
    if(data.xp !== undefined) { const maxXp = data.max_xp || gameState.max_xp; document.getElementById('xp-text').innerText = `${data.xp} / ${maxXp}`; document.getElementById('xp-fill').style.width = (data.xp / maxXp * 100) + '%'; }
    if(data.level) document.getElementById('lvl').innerText = data.level;
    if(data.gold !== undefined || gameState.gold !== undefined) { const g = data.gold !== undefined ? data.gold : gameState.gold; const gel = document.getElementById('gold-val'); if(gel) gel.innerText = g; }
    updateAttributesUI(data);
}

function updateBar(elementId, current, max) {
    const el = document.getElementById(elementId);
    if (el) el.style.width = Math.max(0, Math.min(100, (current / max * 100))) + '%';
}

function checkLifeStatus() { const ds = document.getElementById('death-screen'); if (gameState.hp <= 0) ds.style.display = 'flex'; else ds.style.display = 'none'; }

function updateAttributesUI(data) {
    const pts = (data.stat_points !== undefined) ? parseInt(data.stat_points) : gameState.stat_points;
    const atk = (data.base_attack !== undefined) ? parseInt(data.base_attack) : gameState.base_attack;
    const def = (data.base_defense !== undefined) ? parseInt(data.base_defense) : gameState.base_defense;
    
    const el = document.getElementById('stat-points-val');
    if(el) el.innerText = pts;
    
    const list = document.getElementById('attributes-list');
    if(list) {
        const createRow = (label, val, statKey, bonus = "+1") => `
            <div style="display:flex; justify-content:space-between; align-items:center; background:#252525; padding:10px; border-radius:4px; border:1px solid #444;">
                <span>${label}: <strong style="color:white">${val}</strong></span>
                ${pts > 0 ? `<button class="icon-btn" style="background:#00e676; color:black; width:24px; height:24px; border-radius:4px; font-weight:bold; font-size:16px; padding:0; display:flex; align-items:center; justify-content:center;" onclick="spendPoint('${statKey}')" title="${bonus}">+</button>` : ''}
            </div>`;
            
        list.innerHTML = 
            createRow('Strength (Attack)', atk, 'str') +
            createRow('Defense', def, 'def') +
            createRow('Max HP', data.max_hp || gameState.max_hp, 'hp', '+5') +
            createRow('Max Energy', data.max_energy || gameState.max_energy, 'eng');
    }
}

window.spendPoint = async function(stat) {
    const res = await apiPost('spend_stat_point', { stat });
    if(res.status === 'success') {
        updateLocalState(res.data);
        updateUI(res.data);
    }
}


window.switchTab = function(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}

function toggleSettings() {
    const modal = document.getElementById('settings-modal');
    if (modal.style.display === 'flex') modal.style.display = 'none';
    else modal.style.display = 'flex';
}

function playMusic() {
    if (isPlaying) return;
    if (inCombatMode) { combatAudio.play(); } else { if (!explorationAudio.src) playRandomTrack(); else explorationAudio.play(); }
    isPlaying = true;
}

function stopMusic() {
    explorationAudio.pause(); combatAudio.pause();
    isPlaying = false;
}

function setVolume(val) { explorationAudio.volume = val; combatAudio.volume = val; }
function setSfxVolume(val) { sfxVolume = val; }
function playRandomTrack() { let next = Math.floor(Math.random() * playlist.length); explorationAudio.src = playlist[next]; explorationAudio.play().catch(e => console.log("Autoplay blocked:", e)); }
explorationAudio.addEventListener('ended', playRandomTrack);

// --- AUTH & CHARACTER SELECTION ---

function showAuthModal() {
    document.getElementById('start-screen').style.display = 'none';
    document.getElementById('auth-modal').style.display = 'flex';
    document.getElementById('login-form').style.display = 'block';
    document.getElementById('register-form').style.display = 'none';
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
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Registration error.', 'error');
    }
}

async function handleLogout() {
    stopMultiplayerPolling(); // Clean up polling
    stopMusic();
    await apiPost('logout_account');
    
    // Reset UI
    document.getElementById('logout-btn').style.display = 'none';
    document.getElementById('settings-modal').style.display = 'none';
    document.getElementById('game-layout').style.display = 'none';
    document.getElementById('start-screen').style.display = 'flex';
    
    // Reset Game State
    gameState = {
        x: 0, y: 0, hp: 100, max_hp: 100, energy: 10, max_energy: 10,
        xp: 0, max_xp: 100, steps_buffer: 0, enemy_hp: 0, enemy_max_hp: 100,
        in_combat: false, tutorial_completed: false, is_pvp: false
    };
    combatState = null;
    inCombatMode = false;
    isProcessingTurn = false;
    
    // Clear map
    const mapDiv = document.getElementById('map');
    if (mapDiv) mapDiv.innerHTML = '';
    playerMarker = document.createElement('div'); playerMarker.classList.add('player');
    
    showAuthModal();
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
                    <img src="assets/ui/ex.png" style="position:absolute; top:8px; right:8px; width:24px; height:24px; cursor:pointer; z-index:10; filter:drop-shadow(0 0 2px #000);" onclick="event.stopPropagation(); confirmDeleteCharacter(${char.id})">
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
        startGame();
    }
}

function createNewCharacter() {
    document.getElementById('create-char-modal').style.display = 'flex';
    document.getElementById('new-char-name').value = '';
    document.getElementById('new-char-name').focus();
}

async function submitNewCharacter() {
    const nameInput = document.getElementById('new-char-name');
    const name = nameInput.value.trim() || "New character";
    const data = await apiPost('create_character', { name });
    if (data.status === 'success') {
        document.getElementById('create-char-modal').style.display = 'none';
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Cannot create character.', 'error');
    }
}

window.confirmDeleteCharacter = function(charId) {
    let modal = document.getElementById('delete-confirm-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'delete-confirm-modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); display:flex; justify-content:center; align-items:center; z-index:9999;';
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
window.changeCharacter = changeCharacter;
window.loadCharacterSelection = loadCharacterSelection;
window.setSfxVolume = setSfxVolume;
window.selectCharacter = selectCharacter;
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
        'img/grass.png', 'img/grass2.png', 'img/forest.png', 'img/mountain.png', 'img/water.png', 'img/castle.png', 'img/vilage.png'
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
    checkRememberedLogin();
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
    updateOtherPlayers(); // Call once immediately
    updatePlayersInterval = setInterval(updateOtherPlayers, 1500); // Poll every 1.5 seconds
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
    }, ANIMATION_SPEED);
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
        marker.style.backgroundImage = `url('assets/player/Idle1.png')`;
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
    const rect = targetEl.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    for (let i = 0; i < 12; i++) {
        const p = document.createElement('div');
        p.style.position = 'fixed'; p.style.left = centerX + 'px'; p.style.top = centerY + 'px';
        p.style.width = (Math.random() * 6 + 4) + 'px'; p.style.height = p.style.width;
        p.style.backgroundColor = color; p.style.borderRadius = '50%';
        p.style.pointerEvents = 'none'; p.style.zIndex = 9999;
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
    setTimeout(() => effect.remove(), 1000);
}

function showFloatingDamage(targetEl, amount, color) {
    if (!targetEl) return;
    const rect = targetEl.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const topY = rect.top;
    const el = document.createElement('div');
    el.innerText = amount; el.style.position = 'fixed'; el.style.left = centerX + 'px'; el.style.top = topY + 'px';
    el.style.color = color; el.style.fontWeight = '900'; el.style.fontSize = '32px';
    el.style.textShadow = '2px 2px 0 #000, -1px -1px 0 #000'; el.style.pointerEvents = 'none'; el.style.zIndex = 10000;
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
    
    const res = await apiPost('get_duel_state');
    if (res.status === 'ended') {
        if (res.hp !== undefined) gameState.hp = parseInt(res.hp);
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
    
    setTimeout(pollPvPState, 500);
}

async function handlePvPMove(x, y) { await apiPost('pvp_action', { sub_action: 'move', x, y }); }

async function handlePvPAttack() { 
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

function updateDayNightCycle() {
    const overlay = document.getElementById('day-night-overlay');
    if (!overlay) return;
    
    const hour = new Date().getHours();
    let color = 'rgba(0, 0, 0, 0)'; // Dzień (domyślnie)
    let isNight = false;

    if (hour >= 21 || hour < 5) {
        color = 'rgba(0, 5, 20, 0.6)'; // Noc
        isNight = true;
    } else if (hour >= 5 && hour < 8) {
        color = 'rgba(200, 100, 50, 0.2)'; // Świt
    } else if (hour >= 17 && hour < 21) {
        color = 'rgba(80, 40, 100, 0.3)'; // Zmierzch
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
        if(goldEl) goldEl.innerText = gameState.gold;
        loadShop('leathersmith', modal.querySelector('.tab-btn')); // Default load
    }
}

window.loadShop = async function(type, btn) {
    document.querySelectorAll('#shop-modal .tab-btn').forEach(b => b.classList.remove('active'));
    if(btn) btn.classList.add('active');
    
    const container = document.getElementById('shop-content');
    container.innerHTML = 'Loading...';
    
    const res = await apiPost('get_shop_data', { shop_type: type });
    if (res.status === 'success') {
        container.innerHTML = '';
        if (res.items.length === 0) { container.innerHTML = 'Out of stock.'; return; }
        
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
                <button onclick="buyItem(${item.id}, ${item.price})" style="background:#4caf50; border:none; color:white; padding:5px 10px; cursor:pointer; border-radius:3px;">Buy (${item.price} G)</button>
            `;
            container.appendChild(row);
        });
    }
}

window.loadSellTab = async function(btn) {
    document.querySelectorAll('#shop-modal .tab-btn').forEach(b => b.classList.remove('active'));
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
            const sellPrice = Math.floor(item.price * 0.5);
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
                <button onclick="sellItem(${item.item_id})" style="background:#ff9800; border:none; color:black; padding:5px 10px; cursor:pointer; border-radius:3px;">Sell (${sellPrice} G)</button>
            `;
            container.appendChild(row);
        });
    }
}

window.buyItem = async function(id, price) {
    if (gameState.gold < price) { showToast("Not enough gold!", "error"); return; }
    const res = await apiPost('buy_item', { item_id: id });
    if (res.status === 'success') {
        gameState.gold = res.gold;
        const gel = document.getElementById('shop-gold'); if(gel) gel.innerText = gameState.gold;
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
        const gel = document.getElementById('shop-gold'); if(gel) gel.innerText = gameState.gold;
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

    // Re-center map after animation finishes
    setTimeout(() => {
        if (typeof updatePlayerVisuals === 'function') {
            updatePlayerVisuals(gameState.x, gameState.y, true);
        }
    }, 400);
}

// Add keyboard shortcut listener for the right panel
document.addEventListener('keydown', (e) => {
    // Do not trigger shortcut if user is typing in an input field
    if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
        return;
    }
    if (e.key === 'Tab') {
        e.preventDefault(); // Prevent default focus switching behavior
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
    html += `<div style="font-size:20px; color:gold; margin-bottom:10px; font-weight:bold;">+${gold} Gold</div>`;
    
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
    
    // Refresh game state (XP, Level, Inventory)
    await initGame();

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
    return;
}

function updateCombatCamera() {
    const container = document.getElementById('combat-arena-container');
    const screen = document.getElementById('combat-screen');
    const arenaShell = document.getElementById('combat-arena-shell');
    if (!container || !screen) return;

    const isMobile = Math.min(window.innerWidth, window.innerHeight) <= 900;
    if (!isMobile) {
        container.style.transform = '';
        container.style.transformOrigin = '';
        container.style.margin = '20px auto';
        container.style.transition = '';
        combatCameraState.initialized = false;
        return;
    }

    container.style.transition = 'none';
    container.style.willChange = 'transform';

    const isPortrait = window.innerHeight > window.innerWidth;
    const viewW = arenaShell ? arenaShell.clientWidth : screen.clientWidth;
    const viewH = arenaShell ? arenaShell.clientHeight : screen.clientHeight;
    const contW = container.offsetWidth || 1100;
    const contH = container.offsetHeight || 450;
    const now = Date.now();

    const fitScale = Math.min(viewW / contW, viewH / contH) * 0.95;
    let scale = fitScale;
    if (!isPortrait) {
        scale = Math.min(fitScale * 1.5, 1.25);
    }
    let moveX = (viewW - contW * scale) / 2;
    let moveY = (viewH - contH * scale) / 2;

    let hasTarget = false;
    if (combatState && now > combatCameraOverviewUntil) {
        // Track active turn target
        scale = Math.min(fitScale * (isPortrait ? 2.6 : 1.8), isPortrait ? 1.6 : 1.3);
        const enemyEl = document.getElementById('combat-enemy');
        const playerEl = document.getElementById('combat-player');
        const target = (combatState.turn === 'enemy' ? enemyEl : playerEl) || enemyEl || playerEl;
        if (target) {
            hasTarget = true;
            const targetX = target.offsetLeft + target.offsetWidth / 2;
            const targetY = target.offsetTop + target.offsetHeight / 2;
            moveX = (viewW / 2) - (targetX * scale);
            moveY = (viewH / 2) - (targetY * scale);
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
        combatCameraState = { x: moveX, y: moveY, scale, initialized: true };
    } else if (hasTarget) {
        combatCameraState.x = moveX;
        combatCameraState.y = moveY;
        combatCameraState.scale = scale;
    } else {
        const smooth = isPortrait ? 0.18 : 0.12;
        combatCameraState.x += (moveX - combatCameraState.x) * smooth;
        combatCameraState.y += (moveY - combatCameraState.y) * smooth;
        combatCameraState.scale += (scale - combatCameraState.scale) * smooth;
    }

    container.style.transform = `translate(${combatCameraState.x}px, ${combatCameraState.y}px) scale(${combatCameraState.scale})`;
}