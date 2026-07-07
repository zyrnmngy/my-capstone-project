/*:
 * @target MZ
 * @plugindesc Whack-a-Priest Mini Game - Isang masayang cartoon laro para sa RPG Maker MZ
 * @author Your Name
 * @url https:filibustero-web.com
 *
 * @help
 * WHACK-A-PRIEST MINI GAME
 * 
 * Mga Instruksyon:
 * 1. Pindutin ang mga pari na lilitaw sa mga butas
 * 2. Bawat pari ay may iba't ibang puntos:
 *    - Normal na pari: 10 puntos
 *    - Berdeng pari: 20 puntos
 *    - Pulang pari: 50 puntos
 * 3. Mayroon kang 30 segundo para makakuha ng maraming puntos
 * 4. Iwasang pindutin ang bomb - minus 20 puntos!
 * 
 * Para gamitin:
 * - Plugin command: WhackAPriest -> start
 * 
 * @command start
 * @text Simulan ang Laro
 * @desc Simulan ang Whack-a-Priest mini game
 *
 * @param GameDuration
 * @text Tagal ng Laro
 * @type number
 * @desc Tagal ng laro sa segundo (default: 30)
 * @default 30
 * 
 * @param ScoreVariable
 * @text Score Variable
 * @type variable
 * @default 1
 * @desc Variable to store the final score
 * 
 * @param GameBGM
 * @text Game Background Music
 * @type file
 * @dir audio/bgm
 * @default Theme6
 * @desc Joyful background music for the game (from audio/bgm folder)
 */

(() => {
    'use strict';
    
    const pluginName = 'WhackAPriest';
    const parameters = PluginManager.parameters(pluginName);
    
    const settings = {
        gameDuration: Number(parameters.GameDuration) || 30,
        scoreVariable: Number(parameters.ScoreVariable) || 1,
        gameBGM: parameters.GameBGM || 'Field1'
    };

    PluginManager.registerCommand(pluginName, 'start', args => {
        SceneManager.push(Scene_WhackAPriest);
    });

    //=============================================================================
    // Scene_WhackAPriest
    //=============================================================================
    
    class Scene_WhackAPriest extends Scene_Base {
        initialize() {
            super.initialize();
            this._score = 0;
            this._timeLeft = settings.gameDuration;
            this._gameActive = false;
            this._gameOver = false;
        }
        
        create() {
            super.create();
            this.createBackground();
            this.createGameContainer();
            this.createUI();
            this.playGameBGM();
            this.startGame();
        }
        
        playGameBGM() {
            // Save the current BGM to restore later
            this._savedBgm = AudioManager.saveBgm();
            
            // Play the joyful game BGM
            AudioManager.playBgm({
                name: settings.gameBGM,
                volume: 90,
                pitch: 100,
                pan: 0
            });
        }
        
        stop() {
            super.stop();
            // Restore the previous BGM when leaving the scene
            if (this._savedBgm) {
                AudioManager.replayBgm(this._savedBgm);
            }
        }
        
        createBackground() {
            this._backgroundSprite = new Sprite();
            this._backgroundSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
            
            const bitmap = this._backgroundSprite.bitmap;
            const ctx = bitmap.context;
            const width = Graphics.width;
            const height = Graphics.height;
            
            // Sky gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, height * 0.6);
            gradient.addColorStop(0, '#87CEEB');
            gradient.addColorStop(1, '#E0F6FF');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, width, height * 0.6);
            
            // Grass
            const grassGradient = ctx.createLinearGradient(0, height * 0.6, 0, height);
            grassGradient.addColorStop(0, '#90EE90');
            grassGradient.addColorStop(1, '#32CD32');
            ctx.fillStyle = grassGradient;
            ctx.fillRect(0, height * 0.6, width, height * 0.4);
            
            // Add clouds
            this.drawCloud(ctx, 100, 50, 80);
            this.drawCloud(ctx, 300, 80, 100);
            this.drawCloud(ctx, 600, 40, 90);
            
            // Add flowers
            this.drawFlower(ctx, 50, height * 0.65, '#FF69B4');
            this.drawFlower(ctx, 150, height * 0.7, '#FFD700');
            this.drawFlower(ctx, width - 80, height * 0.68, '#FF1493');
            
            this.addChild(this._backgroundSprite);
        }
        
        drawCloud(ctx, x, y, size) {
            ctx.fillStyle = '#FFFFFF';
            ctx.beginPath();
            ctx.arc(x, y, size * 0.5, 0, Math.PI * 2);
            ctx.arc(x + size * 0.4, y, size * 0.4, 0, Math.PI * 2);
            ctx.arc(x + size * 0.8, y, size * 0.5, 0, Math.PI * 2);
            ctx.arc(x + size * 0.4, y - size * 0.3, size * 0.4, 0, Math.PI * 2);
            ctx.fill();
        }
        
        drawFlower(ctx, x, y, color) {
            const petalSize = 8;
            
            // Petals
            ctx.fillStyle = color;
            for (let i = 0; i < 5; i++) {
                const angle = (i * Math.PI * 2) / 5;
                const px = x + Math.cos(angle) * petalSize;
                const py = y + Math.sin(angle) * petalSize;
                ctx.beginPath();
                ctx.arc(px, py, petalSize, 0, Math.PI * 2);
                ctx.fill();
            }
            
            // Center
            ctx.fillStyle = '#FFD700';
            ctx.beginPath();
            ctx.arc(x, y, petalSize * 0.6, 0, Math.PI * 2);
            ctx.fill();
            
            // Stem
            ctx.strokeStyle = '#228B22';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x, y + 20);
            ctx.stroke();
        }
        
        createGameContainer() {
            this._holes = [];
            this._priests = [];
            this._hitEffects = [];
            this._gameContainer = new Sprite();
            this.addChild(this._gameContainer);
            
            this.createHoles();
        }
        
        createHoles() {
            const positions = [
                { x: 150, y: 280 }, { x: 320, y: 280 }, { x: 490, y: 280 },
                { x: 150, y: 400 }, { x: 320, y: 400 }, { x: 490, y: 400 },
                { x: 150, y: 520 }, { x: 320, y: 520 }, { x: 490, y: 520 }
            ];
            
            positions.forEach(pos => {
                const holeSprite = new Sprite_Hole();
                holeSprite.setup(pos.x, pos.y);
                this._holes.push({
                    x: pos.x,
                    y: pos.y,
                    sprite: holeSprite,
                    occupied: false
                });
                this._gameContainer.addChild(holeSprite);
            });
        }
        
        createUI() {
            // Title
            this._titleSprite = new Sprite();
            this._titleSprite.bitmap = new Bitmap(Graphics.width, 80);
            this._titleSprite.y = 10;
            this.addChild(this._titleSprite);
            
            // Score
            this._scoreSprite = new Sprite();
            this._scoreSprite.bitmap = new Bitmap(300, 80);
            this._scoreSprite.x = 20;
            this._scoreSprite.y = 60;
            this.addChild(this._scoreSprite);
            
            // Timer
            this._timerSprite = new Sprite();
            this._timerSprite.bitmap = new Bitmap(300, 80);
            this._timerSprite.x = Graphics.width - 220;
            this._timerSprite.y = 60;
            this.addChild(this._timerSprite);
            
            // Instructions
            this._instructionSprite = new Sprite();
            this._instructionSprite.bitmap = new Bitmap(Graphics.width, 80);
            this._instructionSprite.y = Graphics.height - 80;
            this.addChild(this._instructionSprite);
            
            this.updateUI();
        }
        
        updateUI() {
            // Title
            const titleBitmap = this._titleSprite.bitmap;
            titleBitmap.clear();
            titleBitmap.fontSize = 32;
            titleBitmap.textColor = '#FF69B4';
            titleBitmap.outlineColor = '#000000';
            titleBitmap.outlineWidth = 5;
            
            // Score
            const scoreBitmap = this._scoreSprite.bitmap;
            scoreBitmap.clear();
            scoreBitmap.fontSize = 28;
            scoreBitmap.textColor = '#FFD700';
            scoreBitmap.outlineColor = '#000000';
            scoreBitmap.outlineWidth = 4;
            scoreBitmap.drawText(`★ Puntos: ${this._score}`, 0, 10, 300, 60, 'left');
            
            // Timer
            const timerBitmap = this._timerSprite.bitmap;
            timerBitmap.clear();
            timerBitmap.fontSize = 28;
            const timeColor = this._timeLeft < 10 ? '#FF0000' : '#00FF00';
            timerBitmap.textColor = timeColor;
            timerBitmap.outlineColor = '#000000';
            timerBitmap.outlineWidth = 4;
            timerBitmap.drawText(`⏰ Oras: ${Math.ceil(this._timeLeft)}s`, 0, 10, 300, 60, 'left');
            
            // Instructions
            const instBitmap = this._instructionSprite.bitmap;
            instBitmap.clear();
            instBitmap.fontSize = 18;
            instBitmap.textColor = '#FFFFFF';
            instBitmap.outlineColor = '#000000';
            instBitmap.outlineWidth = 3;
            instBitmap.drawText('Pindutin ang mga pari!', 0, 0, Graphics.width, 40, 'center');
            instBitmap.fontSize = 16;
            instBitmap.drawText('Kayumanggi=10 | Berde=20 | Pula=50 | Bomb=-20', 0, 35, Graphics.width, 40, 'center');
        }
        
        startGame() {
            this._score = 0;
            this._timeLeft = settings.gameDuration;
            this._gameActive = true;
            this._spawnTimer = 30;
            
            // Play start sound effect
            AudioManager.playSe({
                name: 'Bell1',
                volume: 90,
                pitch: 100,
                pan: 0
            });
        }
        
        update() {
            super.update();
            
            if (this._gameActive) {
                this.updateGame();
            }
            
            this.updatePriests();
            this.updateHitEffects();
            this.updateUI();
            
            if (this._gameOver && Input.isTriggered('ok')) {
                this.returnToMap();
            }
        }
        
        updateGame() {
            this._timeLeft -= 1/60;
            
            if (this._timeLeft <= 0) {
                this.endGame();
                return;
            }
            
            this._spawnTimer--;
            if (this._spawnTimer <= 0) {
                this.spawnPriest();
                this._spawnTimer = Math.randomInt(40) + 30;
            }
            
            this.handleInput();
        }
        
        spawnPriest() {
            const availableHoles = this._holes.filter(hole => !hole.occupied);
            if (availableHoles.length === 0) return;
            
            const hole = availableHoles[Math.randomInt(availableHoles.length)];
            const priestType = this.getRandomPriestType();
            
            const priest = new Sprite_Priest();
            priest.setup(hole.x, hole.y, priestType);
            
            this._priests.push({
                sprite: priest,
                hole: hole,
                timer: 100,
                points: this.getPointsForType(priestType),
                type: priestType
            });
            
            hole.occupied = true;
            this._gameContainer.addChild(priest);
        }
        
        getRandomPriestType() {
            const rand = Math.random();
            if (rand < 0.6) return 'normal';
            if (rand < 0.85) return 'rare';
            if (rand < 0.95) return 'super';
            return 'bomb';
        }
        
        getPointsForType(type) {
            switch(type) {
                case 'normal': return 10;
                case 'rare': return 20;
                case 'super': return 50;
                case 'bomb': return -20;
                default: return 10;
            }
        }
        
        updatePriests() {
            for (let i = this._priests.length - 1; i >= 0; i--) {
                const priest = this._priests[i];
                priest.sprite.updateAnimation();
                priest.timer--;
                
                if (priest.timer <= 0) {
                    priest.hole.occupied = false;
                    this._gameContainer.removeChild(priest.sprite);
                    this._priests.splice(i, 1);
                }
            }
        }
        
        updateHitEffects() {
            for (let i = this._hitEffects.length - 1; i >= 0; i--) {
                const effect = this._hitEffects[i];
                effect.sprite.y -= 2;
                effect.sprite.opacity -= 5;
                effect.timer--;
                
                if (effect.timer <= 0) {
                    this._gameContainer.removeChild(effect.sprite);
                    this._hitEffects.splice(i, 1);
                }
            }
        }
        
        handleInput() {
            if (TouchInput.isTriggered()) {
                const x = TouchInput.x;
                const y = TouchInput.y;
                
                for (let i = this._priests.length - 1; i >= 0; i--) {
                    const priest = this._priests[i];
                    if (priest.sprite.isClicked(x, y)) {
                        this.hitPriest(priest, i);
                        break;
                    }
                }
            }
        }
        
        hitPriest(priest, index) {
            this._score += priest.points;
            if (this._score < 0) this._score = 0;
            
            this.createHitEffect(priest.sprite.x, priest.sprite.y - 60, priest.points);
            
            if (priest.type === 'bomb') {
                AudioManager.playSe({name: 'Buzzer1', volume: 90, pitch: 100, pan: 0});
            } else {
                AudioManager.playSe({name: 'Cursor1', volume: 90, pitch: 120 + priest.points, pan: 0});
            }
            
            priest.hole.occupied = false;
            this._gameContainer.removeChild(priest.sprite);
            this._priests.splice(index, 1);
        }
        
        createHitEffect(x, y, points) {
            const effectSprite = new Sprite();
            effectSprite.bitmap = new Bitmap(60, 40);
            effectSprite.x = x;
            effectSprite.y = y;
            
            const text = points > 0 ? `+${points}` : `${points}`;
            const color = points > 0 ? '#00FF00' : '#FF0000';
            
            effectSprite.bitmap.fontSize = 32;
            effectSprite.bitmap.textColor = color;
            effectSprite.bitmap.outlineColor = '#000000';
            effectSprite.bitmap.outlineWidth = 5;
            effectSprite.bitmap.drawText(text, 0, 0, 60, 40, 'center');
            
            this._hitEffects.push({
                sprite: effectSprite,
                timer: 50
            });
            
            this._gameContainer.addChild(effectSprite);
        }
        
        endGame() {
            this._gameActive = false;
            this._gameOver = true;
            
            $gameVariables.setValue(settings.scoreVariable, this._score);
            
            // Play victory/end sound effect
            AudioManager.playSe({
                name: 'Item1',
                volume: 90,
                pitch: 100,
                pan: 0
            });
            
            this.showResult();
        }
        
        showResult() {
            const resultSprite = new Sprite();
            resultSprite.bitmap = new Bitmap(400, 200);
            resultSprite.x = Graphics.width / 2 - 200;
            resultSprite.y = Graphics.height / 2 - 100;
            
            const bitmap = resultSprite.bitmap;
            const ctx = bitmap.context;
            
            // Background
            ctx.fillStyle = 'rgba(0, 0, 0, 0.9)';
            ctx.fillRect(0, 0, 400, 200);
            
            // Border
            ctx.strokeStyle = '#FFD700';
            ctx.lineWidth = 5;
            ctx.strokeRect(5, 5, 390, 190);
            
            // Text
            bitmap.fontSize = 40;
            bitmap.textColor = '#FF6347';
            bitmap.outlineColor = '#000000';
            bitmap.outlineWidth = 5;
            bitmap.drawText('TAPOS NA!', 0, 20, 400, 50, 'center');
            
            bitmap.fontSize = 32;
            bitmap.textColor = '#FFD700';
            bitmap.drawText('Iyong Puntos:', 0, 70, 400, 50, 'center');
            
            bitmap.fontSize = 48;
            bitmap.drawText(`${this._score}`, 0, 110, 400, 60, 'center');
            
            this.addChild(resultSprite);
        }
        
        returnToMap() {
            SceneManager.pop();
        }
    }

    //=============================================================================
    // Sprite_Hole
    //=============================================================================
    
    class Sprite_Hole extends Sprite {
        initialize() {
            super.initialize();
        }
        
        setup(x, y) {
            this.x = x;
            this.y = y;
            this.anchor.x = 0.5;
            this.anchor.y = 0.5;
            this.createBitmap();
        }
        
        createBitmap() {
            this.bitmap = new Bitmap(100, 50);
            const ctx = this.bitmap.context;
            
            // Black hole ellipse
            ctx.fillStyle = '#000000';
            ctx.beginPath();
            ctx.ellipse(50, 25, 50, 20, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // Brown dirt outline
            ctx.strokeStyle = '#8B4513';
            ctx.lineWidth = 4;
            ctx.beginPath();
            ctx.ellipse(50, 25, 50, 20, 0, 0, Math.PI * 2);
            ctx.stroke();
        }
    }

    //=============================================================================
    // Sprite_Priest
    //=============================================================================
    
    class Sprite_Priest extends Sprite {
        initialize() {
            super.initialize();
            this._animFrame = 0;
            this._popUp = 0;
            this._maxPopUp = 15;
        }
        
        setup(x, y, type) {
            this.x = x;
            this.y = y;
            this._type = type;
            this._baseY = y;
            this.anchor.x = 0.5;
            this.anchor.y = 1;
            this.createBitmap();
        }
        
        createBitmap() {
            this.bitmap = new Bitmap(100, 120);
            this.drawPriest();
        }
        
        drawPriest() {
            const ctx = this.bitmap.context;
            const centerX = 50;
            const centerY = 90;
            
            // Color based on type
            let bodyColor, hatColor;
            switch(this._type) {
                case 'normal':
                    bodyColor = '#8B4513';
                    hatColor = '#000000';
                    break;
                case 'rare':
                    bodyColor = '#32CD32';
                    hatColor = '#228B22';
                    break;
                case 'super':
                    bodyColor = '#FF4444';
                    hatColor = '#CC0000';
                    break;
                case 'bomb':
                    bodyColor = '#333333';
                    hatColor = '#FF0000';
                    break;
            }
            
            // Body (rounded)
            ctx.fillStyle = bodyColor;
            ctx.beginPath();
            ctx.ellipse(centerX, centerY, 35, 45, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // Black outline
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 3;
            ctx.stroke();
            
            // Face
            ctx.fillStyle = '#FFDAB9';
            ctx.beginPath();
            ctx.ellipse(centerX, centerY - 10, 25, 28, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            
            // Eyes
            ctx.fillStyle = '#000000';
            ctx.beginPath();
            ctx.arc(centerX - 10, centerY - 15, 4, 0, Math.PI * 2);
            ctx.arc(centerX + 10, centerY - 15, 4, 0, Math.PI * 2);
            ctx.fill();
            
            // Smile
            ctx.beginPath();
            ctx.arc(centerX, centerY - 5, 12, 0.2, Math.PI - 0.2);
            ctx.lineWidth = 2;
            ctx.stroke();
            
            // Priest collar/hat
            if (this._type !== 'bomb') {
                ctx.fillStyle = hatColor;
                ctx.fillRect(centerX - 30, centerY - 45, 60, 10);
                
                // Cross on collar
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(centerX - 3, centerY + 20, 6, 15);
                ctx.fillRect(centerX - 8, centerY + 25, 16, 6);
            } else {
                // Bomb fuse
                ctx.strokeStyle = '#8B4513';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(centerX, centerY - 45);
                ctx.lineTo(centerX + 5, centerY - 55);
                ctx.stroke();
                
                // Spark
                ctx.fillStyle = '#FF6600';
                ctx.beginPath();
                ctx.arc(centerX + 5, centerY - 55, 5, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        
        updateAnimation() {
            this._animFrame++;
            
            if (this._popUp < this._maxPopUp) {
                this._popUp += 2;
                this.y = this._baseY - this._popUp * 3;
            }
            
            // Slight bobbing animation
            const bobAmount = Math.sin(this._animFrame * 0.1) * 2;
            this.y = this._baseY - this._popUp * 3 + bobAmount;
        }
        
        isClicked(x, y) {
            const dx = x - this.x;
            const dy = y - this.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            return distance <= 40;
        }
    }

    window.Scene_WhackAPriest = Scene_WhackAPriest;
})();