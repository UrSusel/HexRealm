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
    tutorial_completed: false
};

let inCombatMode = false;
let combatState = null;
let lowEnergyWarningShown = false;

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
    idle: ['assets/player/idle1.png', 'assets/player/idle2.png', 'assets/player/idle3.png','assets/player/idle4.png', 'assets/player/idle5.png', 'assets/player/idle6.png','assets/player/idle7.png', 'assets/player/idle8.png', 'assets/player/idle9.png'],
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
    playRandomTrack();
    isPlaying = true;
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
            document.getElementById('world-info').innerText = json.data.world_name || 'Nieznany świat';
            updateUI(json.data);
            checkTutorialStatus();

            if (gameState.in_combat && json.data.combat_state) {
                combatState = JSON.parse(json.data.combat_state);
                toggleCombatMode(true, gameState.hp, json.data.enemy_hp);
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
            list.innerHTML = '<div style="color:#ccc">Brak dostępnych światów</div>';
        }

        modal.style.display = 'flex';
    } catch (e) {
        console.error('showWorldSelection error', e);
        showToast('Błąd pobierania listy światów', 'error');
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
            showToast(data.message || 'Nie udało się dołączyć do świata', 'error');
        }
    } catch (e) {
        console.error('joinWorld error', e);
        showToast('Błąd połączenia z serwerem', 'error');
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

// Ensure world button visibility on load
document.addEventListener('DOMContentLoaded', () => {
    checkTutorialStatus().catch(e => console.error(e));
});
window.respawnPlayer = async function() {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'respawn' })
        });
        location.reload();
    } catch (e) {
        console.error('respawnPlayer error', e);
        location.reload();
    }
};
window.selectClass = async function(id) {
    try {
        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'select_class', class_id: id })
        });
        location.reload();
    } catch (e) {
        console.error('selectClass error', e);
    }
};

// Run tutorial check on load
document.addEventListener('DOMContentLoaded', () => {
    checkTutorialStatus().catch(e => console.error(e));
});



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
    const moveX = (panel.offsetWidth / 2) - pixelX - 64; 
    const moveY = (panel.offsetHeight / 2) - pixelY - 64;
    map.style.transform = `translate(${moveX}px, ${moveY}px)`;
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
    if (gameState.hp <= 0 || gameState.in_combat) return;
    if (targetX < gameState.x) playerMarker.style.transform = "scaleX(-1)"; else if (targetX > gameState.x) playerMarker.style.transform = "scaleX(1)";

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
            showToast("⚠️ Niska energia! Jeśli nie wrócisz do miasta, będziesz poruszać się tylko o 1 kratkę!", "error big", 8000);
            lowEnergyWarningShown = true;
        } else if (gameState.energy > 3) {
            lowEnergyWarningShown = false;
        }
        
        updatePlayerVisuals(gameState.x, gameState.y, false);
        
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
    if (!inventory || inventory.length === 0) { container.innerHTML = '<div style="color:#666; padding:10px;">Pusty plecak...</div>'; return; }
    inventory.forEach(item => {
        const slot = document.createElement('div');
        slot.className = 'item-slot';
        if (item.is_equipped == 1) slot.classList.add('equipped');
        slot.innerHTML = `<div style="font-size:24px;">${item.icon || '📦'}</div><div style="font-size:11px; margin-top:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.name}</div>${item.quantity > 1 ? `<div style="position:absolute; bottom:2px; right:5px; font-size:10px; color:#aaa;">x${item.quantity}</div>` : ''}`;
        container.appendChild(slot);
    });
}

function toggleCombatMode(active, currentHp, enemyHp = 0) {
    const combatScreen = document.getElementById('combat-screen');
    const mapDiv = document.getElementById('map');
    inCombatMode = active; gameState.in_combat = active;

    if (active && isPlaying) { explorationAudio.pause(); combatAudio.currentTime = 0; combatAudio.play().catch(e => console.log(e)); } 
    else if (!active && isPlaying) { combatAudio.pause(); explorationAudio.play().catch(e => console.log(e)); }

    if (active) {
        stopMultiplayerPolling(); // Stop polling during combat
        startCombatAnimations();
        mapDiv.style.display = 'none'; combatScreen.style.display = 'flex';
        let existingContainer = document.getElementById('combat-arena-container');
        if (existingContainer) existingContainer.remove();
        let container = document.createElement('div');
        container.id = 'combat-arena-container';
        container.style.width = '1100px'; container.style.height = '450px';
        container.style.position = 'relative'; container.style.margin = '20px auto'; 
        combatScreen.insertBefore(container, document.getElementById('combat-log'));
        if (combatState) renderCombatArena();
        
        document.getElementById('enemy-hp').innerText = enemyHp;
        document.getElementById('combat-hp').innerText = gameState.hp;
        
        // Aktualizacja pasków na start walki
        updateBar('combat-hp-bar', gameState.hp, gameState.max_hp);
        // Jeśli wchodzimy w walkę, zakładamy że enemyHp to max (chyba że wczytujemy stan)
        const eMax = gameState.enemy_max_hp > 0 ? gameState.enemy_max_hp : (enemyHp || 100);
        updateBar('combat-enemy-fill', enemyHp, eMax);
        
        updateApDisplay();
    } else {
        mapDiv.style.display = 'block'; combatScreen.style.display = 'none';
        combatState = null;
        stopCombatAnimations();
        loadAndDrawMap();
        updatePlayerVisuals(gameState.x, gameState.y, true);
        startMultiplayerPolling(); // Resume polling after combat
    }
}

function updateApDisplay() {
    const log = document.getElementById('combat-log');
    if (combatState && combatState.turn === 'player') { 
        log.innerText = `Twój ruch. AP: ${combatState.player_ap}/2.`; 
    } else { 
        log.innerText = "Tura wroga..."; 
    }
}

function renderCombatArena() {
    const container = document.getElementById('combat-arena-container');
    container.innerHTML = ''; 
    if (!combatState || !combatState.tiles) return;
    combatState.tiles.forEach(t => {
        const tile = document.createElement('div'); tile.className = `tile ${t.type}`;
        let offsetX = (t.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
        let posX = (t.x * HEX_WIDTH) + offsetX;
        let posY = (t.y * HEX_HEIGHT);
        tile.style.left = posX + 'px'; tile.style.top = posY + 'px'; tile.style.zIndex = t.y;
        tile.onclick = () => { if(combatState.turn === 'player' && combatState.player_ap >= 1) handleCombatMove(t.x, t.y); };
        container.appendChild(tile);
    });
    createCombatEntity(combatState.player_pos, 'player', container);
    createCombatEntity(combatState.enemy_pos, 'enemy', container);
    updateApDisplay();
    if (combatState.turn === 'enemy') setTimeout(handleEnemyTurn, 500);
}

function createCombatEntity(pos, type, container) {
    const el = document.createElement('div'); el.className = `player ${type}`; el.id = `combat-${type}`; 
    let off = (pos.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
    el.style.left = ((pos.x * HEX_WIDTH) + off - 10) + 'px';
    el.style.top = ((pos.y * HEX_HEIGHT) - 24) + 'px';
    el.style.zIndex = 100;
    el.dataset.animState = 'idle';
    el.style.backgroundImage = `url('assets/player/idle1.png')`;
    if (type === 'enemy') { el.style.filter = "hue-rotate(150deg) brightness(0.8)"; el.style.transform = "scaleX(-1)"; el.onclick = () => { if(combatState.turn === 'player') handleCombatAttack(); }; }
    container.appendChild(el);
}

function animateCombatMove(type, targetPos) {
    const el = document.getElementById(`combat-${type}`);
    if (!el) return;
    let off = (targetPos.y % 2 !== 0) ? (HEX_WIDTH / 2) : 0;
    let targetPxX = (targetPos.x * HEX_WIDTH) + off - 10;
    let targetPxY = (targetPos.y * HEX_HEIGHT) - 24;
    el.dataset.animState = 'run'; startWalkingSound();
    el.style.transition = "left 0.4s linear, top 0.4s linear"; el.style.left = targetPxX + 'px'; el.style.top = targetPxY + 'px';
    setTimeout(() => { el.dataset.animState = 'idle'; stopWalkingSound(); }, 400);
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
        
        combatState = json.combat_state;
        renderCombatArena();
        if (json.dmg_dealt) {
            const enemyEl = document.getElementById('combat-enemy');
            if (enemyEl) { spawnCombatParticles(enemyEl, '#ffffff'); showFloatingDamage(enemyEl, json.dmg_dealt, '#ffeb3b'); }
        }
        if (json.win) { 
            setTimeout(async () => { 
                toggleCombatMode(false); 
                
                // Najpierw odświeżamy stan, żeby flaga tutorial_completed się zaktualizowała
                await initGame();
                
                // Jeśli tutorial właśnie się skończył (wg odpowiedzi z walki)
                if (json.tutorial_finished) {
                    showWorldSelection();
                } else {
                    // Popup usunięty na żądanie
                }
            }, 1000); 
        }
    } else { document.getElementById('combat-log').innerText = json.message; }
}

async function handleEnemyTurn() {
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
                    
                    document.getElementById('combat-log').innerText = json.log;
                    if (json.player_died) { toggleCombatMode(false); checkLifeStatus(); } else { combatState = json.combat_state; renderCombatArena(); }
                }, 500); return;
            }
            const action = actions[index];
            if (action.type === 'move') { animateCombatMove('enemy', action.to); setTimeout(() => playAction(index + 1), 600); } 
            else if (action.type === 'attack') {
                const pEl = document.getElementById('combat-player'); playSoundEffect('hit');
                if (action.dmg > 0) setTimeout(() => playSoundEffect('damage', action.dmg), 100);
                if(pEl) pEl.style.filter = "brightness(0.5) sepia(1) hue-rotate(-50deg) saturate(5)"; 
                if(pEl && action.dmg > 0) { spawnCombatParticles(pEl, '#d32f2f'); showFloatingDamage(pEl, action.dmg, '#ff1744'); }
                setTimeout(() => { if(pEl) pEl.style.filter = ""; }, 200);
                setTimeout(() => playAction(index + 1), 400);
            }
        }; playAction(0);
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
    // Luźne porównanie (==) bo PHP może zwrócić "1" lub 1
    gameState.tutorial_completed = (data.tutorial_completed == 1);
}

function updateUI(data) {
    if(!data) return;
    if(data.hp !== undefined) { const maxHp = data.max_hp || gameState.max_hp; document.getElementById('hp').innerText = `${data.hp} / ${maxHp}`; document.getElementById('hp-fill').style.width = (data.hp / maxHp * 100) + '%'; }
    if(data.energy !== undefined) { const maxEn = data.max_energy || gameState.max_energy; document.getElementById('energy').innerText = `${data.energy} / ${maxEn}`; document.getElementById('en-fill').style.width = (data.energy / maxEn * 100) + '%'; }
    if(data.steps_buffer !== undefined) document.getElementById('steps-info').innerText = data.steps_buffer + '/10';
    if(data.xp !== undefined) { const maxXp = data.max_xp || gameState.max_xp; document.getElementById('xp-text').innerText = `${data.xp} / ${maxXp}`; document.getElementById('xp-fill').style.width = (data.xp / maxXp * 100) + '%'; }
    if(data.level) document.getElementById('lvl').innerText = data.level;
}

function updateBar(elementId, current, max) {
    const el = document.getElementById(elementId);
    if (el) el.style.width = Math.max(0, Math.min(100, (current / max * 100))) + '%';
}

function checkLifeStatus() { const ds = document.getElementById('death-screen'); if (gameState.hp <= 0) ds.style.display = 'flex'; else ds.style.display = 'none'; }



window.selectClass = async function(id) { await fetch('api.php', { method: 'POST', body: JSON.stringify({ action: 'select_class', class_id: id }) }); location.reload(); }
window.respawnPlayer = async function() { await fetch('api.php', { method: 'POST', body: JSON.stringify({ action: 'respawn' }) }); location.reload(); }
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
        showToast('Uzupełnij wszystkie pola.', 'error');
        return;
    }
    
    const data = await apiPost('login_account', { username, password, remember_me: rememberMe });
    if (data.status === 'success') {
        document.getElementById('auth-modal').style.display = 'none';
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Błąd logowania.', 'error');
    }
}

async function handleRegister() {
    const username = document.getElementById('register-username').value.trim();
    const password = document.getElementById('register-password').value;
    const password2 = document.getElementById('register-password2').value;
    
    if (!username || !password || !password2) {
        showToast('Uzupełnij wszystkie pola.', 'error');
        return;
    }
    
    const data = await apiPost('register_account', { username, password, password2 });
    if (data.status === 'success') {
        document.getElementById('auth-modal').style.display = 'none';
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Błąd rejestracji.', 'error');
    }
}

async function handleLogout() {
    stopMultiplayerPolling(); // Clean up polling
    await apiPost('logout_account');
    document.getElementById('logout-btn').style.display = 'none';
    document.getElementById('settings-modal').style.display = 'none';
    document.getElementById('start-screen').style.display = 'flex';
    document.getElementById('game-layout').style.display = 'none';
    showAuthModal();
}

async function changeCharacter() {
    document.getElementById('settings-modal').style.display = 'none';
    document.getElementById('game-layout').style.display = 'none';
    await loadCharacterSelection();
}

async function loadCharacterSelection() {
    try {
        const data = await apiPost('get_characters');
        if (data.status !== 'success') {
            showToast('Błąd pobierania postaci.', 'error');
            return;
        }
        
        const container = document.getElementById('char-slots-container');
        container.innerHTML = '';
        
        data.characters.forEach((char, idx) => {
            const slot = document.createElement('div');
            slot.className = 'char-slot' + (char.id ? '' : ' empty');
            
            if (char.id) {
                slot.innerHTML = `
                    <div class="char-slot-name">${escapeHtml(char.name)}</div>
                    <div class="char-slot-class">Poziom ${char.level}</div>
                `;
                slot.onclick = () => selectCharacter(char.id);
            } else {
                slot.innerHTML = '<div>+ Utwórz nową postać</div>';
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
    const name = nameInput.value.trim() || "Nowa postać";
    const data = await apiPost('create_character', { name });
    if (data.status === 'success') {
        document.getElementById('create-char-modal').style.display = 'none';
        await loadCharacterSelection();
    } else {
        showToast(data.message || 'Nie można utworzyć postaci.', 'error');
    }
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
window.playMusic = playMusic;
window.stopMusic = stopMusic;
window.changeCharacter = changeCharacter;
window.loadCharacterSelection = loadCharacterSelection;
window.setSfxVolume = setSfxVolume;
window.selectCharacter = selectCharacter;
window.createNewCharacter = createNewCharacter;
window.submitNewCharacter = submitNewCharacter;

// On initial page load, check for remembered login
document.addEventListener('DOMContentLoaded', () => {
    checkRememberedLogin();
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
                frameIdx = (frameIdx + 1) % frames.length;
                marker.style.backgroundImage = `url('${frames[frameIdx]}')`;
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
            const activeIds = new Set(players.map(p => p.id));
            
            // Remove players that are no longer active
            Object.keys(otherPlayers).forEach(id => {
                if (!activeIds.has(parseInt(id))) {
                    if (otherPlayerMarkers[id]) {
                        otherPlayerMarkers[id].remove();
                        delete otherPlayerMarkers[id];
                    }
                    delete otherPlayers[id];
                }
            });
            
            // Update or add players
            players.forEach(p => {
                if (otherPlayers[p.id]) {
                    // Update existing player position
                    otherPlayers[p.id].x = p.pos_x;
                    otherPlayers[p.id].y = p.pos_y;
                    otherPlayers[p.id].level = p.level;
                    renderOtherPlayer(p.id);
                } else {
                    // Add new player
                    otherPlayers[p.id] = {
                        x: p.pos_x,
                        y: p.pos_y,
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

function renderOtherPlayer(playerId) {
    const player = otherPlayers[playerId];
    if (!player) return;
    
    const mapDiv = document.getElementById('map');
    if (!mapDiv) return;
    
    // Find tile position
    const targetTile = document.querySelector(`.tile[data-x='${player.x}'][data-y='${player.y}']`);
    
    // If tile is not rendered (fog of war), hide marker if it exists
    if (!targetTile) {
        if (otherPlayerMarkers[playerId]) otherPlayerMarkers[playerId].style.display = 'none';
        return;
    }
    
    const tLeft = targetTile.offsetLeft;
    const tTop = targetTile.offsetTop;
    const targetPixelX = tLeft - 10;
    const targetPixelY = tTop - 24;
    
    let marker = otherPlayerMarkers[playerId];

    if (!marker) {
        // Create new marker
        marker = document.createElement('div');
        marker.className = 'player other-player';
        marker.id = `other-player-${playerId}`;
        marker.style.left = targetPixelX + 'px';
        marker.style.top = targetPixelY + 'px';
        marker.style.zIndex = 500; // Between map and own player
        marker.style.backgroundImage = `url('assets/player/idle1.png')`;
        
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
        label.innerText = `${player.name} (Lvl ${player.level})`;
        
        marker.appendChild(label);
        mapDiv.appendChild(marker);
        otherPlayerMarkers[playerId] = marker;
    } else {
        marker.style.display = 'block';
        const label = marker.querySelector('.player-label');
        if (label) label.innerText = `${player.name} (Lvl ${player.level})`;

        const currentLeft = parseFloat(marker.dataset.lastX);
        const currentTop = parseFloat(marker.dataset.lastY);
        const deltaX = targetPixelX - currentLeft;
        const deltaY = targetPixelY - currentTop;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

        if (distance > 10) {
            const duration = distance / MOVEMENT_SPEED_PX;
            if (targetPixelX < currentLeft) marker.style.transform = "scaleX(-1)";
            else if (targetPixelX > currentLeft) marker.style.transform = "scaleX(1)";
            
            marker.style.transition = `top ${duration}s linear, left ${duration}s linear`;
            marker.style.left = targetPixelX + 'px';
            marker.style.top = targetPixelY + 'px';
            marker.dataset.animState = 'run';
            
            if (marker.moveTimeout) clearTimeout(marker.moveTimeout);
            marker.moveTimeout = setTimeout(() => { marker.dataset.animState = 'idle'; }, duration * 1000);
        } else {
            marker.style.transition = 'none';
            marker.style.left = targetPixelX + 'px';
            marker.style.top = targetPixelY + 'px';
        }
        marker.dataset.lastX = targetPixelX;
        marker.dataset.lastY = targetPixelY;
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
const uiClickSound = new Audio('assets/ui/misc 1.wav');
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