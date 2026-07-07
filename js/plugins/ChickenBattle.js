//=============================================================================
// Chicken Battle Scene Plugin - Pixel Art Enhanced Version
// Version: 2.2.0
// For RPG Maker MZ
//=============================================================================

/*:
 * @target MZ
 * @plugindesc Interactive chicken battle with pixel art graphics
 * @author YourName
 * @base PluginCommonBase
 *
 * @command startChickenBattle
 * @text Start Chicken Battle
 * @desc Starts the chicken battle scene
 *
 * @arg waveCount
 * @text Number of Waves
 * @type number
 * @min 1
 * @max 10
 * @default 3
 * @desc How many waves of chickens to fight
 *
 * @arg chickensPerWave
 * @text Chickens Per Wave
 * @type number
 * @min 1
 * @max 20
 * @default 5
 * @desc Number of chickens in each wave
 *
 * @arg difficulty
 * @text Difficulty
 * @type select
 * @option Easy
 * @option Normal
 * @option Hard
 * @default Normal
 * @desc Battle difficulty level
 *
 * @arg bgmName
 * @text Battle BGM
 * @type file
 * @dir audio/bgm
 * @default Battle1
 * @desc Background music for the battle (from audio/bgm folder)
 *
 * @help
 * =============================================================================
 * Chicken Battle Scene Plugin - Pixel Art Edition
 * =============================================================================
 * 
 * This plugin creates an interactive side-scrolling battle scene with
 * charming pixel art graphics!
 * 
 * Plugin Commands:
 * - startChickenBattle: Launches the chicken battle scene
 * 
 * Controls during battle:
 * - Arrow Keys: Move player
 * - Z/Space: Attack
 * - X/Escape: Return to map (lose battle)
 * 
 * Victory Condition: Defeat all waves of chickens
 * Defeat Condition: Player health reaches 0 or press cancel
 * 
 * After battle, Variable #0001 is set to:
 * - 1 if victory
 * - 0 if defeat
 * 
 * =============================================================================
 */

(() => {
    const pluginName = "ChickenBattle";

    PluginManager.registerCommand(pluginName, "startChickenBattle", args => {
        const waveCount = Number(args.waveCount) || 3;
        const chickensPerWave = Number(args.chickensPerWave) || 5;
        const difficulty = args.difficulty || "Normal";
        const bgmName = args.bgmName || "Battle1";
        
        SceneManager.push(Scene_ChickenBattle);
        SceneManager.prepareNextScene(waveCount, chickensPerWave, difficulty, bgmName);
    });

    class Scene_ChickenBattle extends Scene_Base {
        prepare(waveCount, chickensPerWave, difficulty, bgmName) {
            this._waveCount = waveCount;
            this._chickensPerWave = chickensPerWave;
            this._difficulty = difficulty;
            this._bgmName = bgmName;
        }

        create() {
            super.create();
            this.createBackground();
            this.createGameObjects();
            this.createUI();
            this._battleState = "active";
            this.playBattleBGM();
        }

        playBattleBGM() {
            // Save the current BGM to restore later
            this._savedBgm = AudioManager.saveBgm();
            
            // Play the battle BGM
            AudioManager.playBgm({
                name: this._bgmName,
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
            this.drawPixelArtBackground(this._backgroundSprite.bitmap);
            this.addChild(this._backgroundSprite);
        }

        drawPixelArtBackground(bitmap) {
            const ctx = bitmap.context;
            ctx.imageSmoothingEnabled = false;
            
            // Sky gradient (blue)
            const skyGrad = ctx.createLinearGradient(0, 0, 0, Graphics.height * 0.6);
            skyGrad.addColorStop(0, '#87CEEB');
            skyGrad.addColorStop(1, '#B0E0E6');
            ctx.fillStyle = skyGrad;
            ctx.fillRect(0, 0, Graphics.width, Graphics.height * 0.6);
            
            // Clouds (pixel style)
            this.drawPixelClouds(ctx);
            
            // Ground/grass
            const groundStart = Graphics.height * 0.6;
            ctx.fillStyle = '#2D5016';
            ctx.fillRect(0, groundStart, Graphics.width, Graphics.height * 0.2);
            
            // Grass line
            ctx.fillStyle = '#4CAF50';
            ctx.fillRect(0, groundStart, Graphics.width, 8);
            
            // Dirt
            ctx.fillStyle = '#8B6F47';
            ctx.fillRect(0, groundStart + 8, Graphics.width, Graphics.height * 0.4);
            
            // Pixel dirt pattern
            this.drawPixelDirtPattern(ctx, groundStart + 8);
            
            // Trees and scenery
            this.drawPixelTrees(ctx);
            this.drawPixelBushes(ctx);
        }

        drawPixelClouds(ctx) {
            const cloudData = [
                {x: 50, y: 40, size: 3},
                {x: 200, y: 80, size: 2},
                {x: 350, y: 50, size: 3},
                {x: Graphics.width - 150, y: 60, size: 2}
            ];
            
            ctx.fillStyle = '#FFFFFF';
            cloudData.forEach(cloud => {
                // Simple pixel cloud
                for (let i = 0; i < cloud.size; i++) {
                    ctx.fillRect(cloud.x + i * 20, cloud.y, 16, 12);
                }
            });
        }

        drawPixelDirtPattern(ctx, startY) {
            ctx.fillStyle = '#6B5D3F';
            for (let x = 0; x < Graphics.width; x += 16) {
                for (let y = startY; y < Graphics.height; y += 16) {
                    if ((Math.floor(x / 16) + Math.floor(y / 16)) % 2 === 0) {
                        ctx.fillRect(x, y, 8, 8);
                    }
                }
            }
        }

        drawPixelTrees(ctx) {
            // Tree 1
            this.drawPixelTree(ctx, 100, Graphics.height * 0.5 - 80, 40, 80);
            // Tree 2
            this.drawPixelTree(ctx, Graphics.width - 150, Graphics.height * 0.5 - 60, 35, 70);
        }

        drawPixelTree(ctx, x, y, trunkWidth, trunkHeight) {
            // Trunk
            ctx.fillStyle = '#654321';
            ctx.fillRect(x - trunkWidth / 2, y, trunkWidth, trunkHeight);
            
            // Foliage - pixel squares
            ctx.fillStyle = '#228B22';
            const foliageSize = 40;
            ctx.fillRect(x - foliageSize / 2, y - foliageSize, foliageSize, foliageSize);
            ctx.fillRect(x - foliageSize / 2 + 10, y - foliageSize - 20, foliageSize - 20, foliageSize);
            
            // Light green highlights
            ctx.fillStyle = '#32CD32';
            ctx.fillRect(x - foliageSize / 2 + 8, y - foliageSize + 10, 16, 16);
        }

        drawPixelBushes(ctx) {
            ctx.fillStyle = '#4CAF50';
            // Bush 1
            ctx.fillRect(150, Graphics.height * 0.55, 40, 20);
            ctx.fillStyle = '#66BB6A';
            ctx.fillRect(155, Graphics.height * 0.53, 30, 12);
            
            // Bush 2
            ctx.fillStyle = '#4CAF50';
            ctx.fillRect(Graphics.width - 200, Graphics.height * 0.55, 50, 20);
            ctx.fillStyle = '#66BB6A';
            ctx.fillRect(Graphics.width - 195, Graphics.height * 0.53, 40, 12);
        }

        createGameObjects() {
            this._currentWave = 1;
            this._chickens = [];
            this._projectiles = [];
            
            const diffMultiplier = {
                "Easy": 0.7,
                "Normal": 1.0,
                "Hard": 1.5
            }[this._difficulty];

            this._player = {
                x: 100,
                y: Graphics.height * 0.55,
                width: 32,
                height: 32,
                health: 100,
                maxHealth: 100,
                speed: 4,
                attackCooldown: 0,
                sprite: this.createPlayerSprite(),
                frame: 0
            };

            this._chickenHealth = Math.floor(30 * diffMultiplier);
            this._chickenSpeed = 1 + (diffMultiplier - 1) * 0.5;
            this._chickenDamage = Math.floor(10 * diffMultiplier);

            this.spawnWave();
        }

        createPlayerSprite() {
            const sprite = new Sprite();
            sprite.bitmap = new Bitmap(32, 32);
            this.drawPixelCharacter(sprite.bitmap, 0);
            this.addChild(sprite);
            return sprite;
        }

        drawPixelCharacter(bitmap, frame) {
            const ctx = bitmap.context;
            ctx.imageSmoothingEnabled = false;
            ctx.clearRect(0, 0, 32, 32);
            
            const wobble = frame % 2 === 0 ? 0 : 1;
            const px = 1; // pixel size unit
            
            // Head - pink
            ctx.fillStyle = '#FFDBAC';
            ctx.fillRect(10, 4, 12, 10);
            
            // Hair - brown
            ctx.fillStyle = '#6B4423';
            ctx.fillRect(9, 2, 14, 3);
            ctx.fillRect(8, 4, 2, 6);
            
            // Eyes
            ctx.fillStyle = '#000000';
            ctx.fillRect(12, 6, 2, 2);
            ctx.fillRect(18, 6, 2, 2);
            
            // Body - blue shirt
            ctx.fillStyle = '#4169E1';
            ctx.fillRect(9, 14, 14, 10);
            
            // Arms
            ctx.fillStyle = '#FFDBAC';
            ctx.fillRect(5, 15, 3, 8);
            ctx.fillRect(24, 15, 3, 8);
            
            // Pants - dark
            ctx.fillStyle = '#2C1810';
            ctx.fillRect(10, 24, 5, 6);
            ctx.fillRect(17, 24, 5, 6);
            
            // Shoes - brown
            ctx.fillStyle = '#8B4513';
            ctx.fillRect(10, 30, 5, 2);
            ctx.fillRect(17, 30, 5, 2);
            
            // Add shine/highlight on shirt
            ctx.fillStyle = '#6495ED';
            ctx.fillRect(11, 16, 2, 3);
        }

        spawnWave() {
            const spacing = 120;
            
            for (let i = 0; i < this._chickensPerWave; i++) {
                const chicken = {
                    x: Graphics.width + i * spacing,
                    y: Graphics.height * 0.5 + Math.random() * 40 - 20,
                    width: 32,
                    height: 32,
                    health: this._chickenHealth,
                    maxHealth: this._chickenHealth,
                    speed: this._chickenSpeed + Math.random() * 0.5,
                    attackCooldown: 0,
                    animFrame: 0,
                    sprite: this.createChickenSprite()
                };
                this._chickens.push(chicken);
            }
        }

        createChickenSprite() {
            const sprite = new Sprite();
            sprite.bitmap = new Bitmap(32, 32);
            this.drawPixelChicken(sprite.bitmap, 0);
            this.addChild(sprite);
            return sprite;
        }

        drawPixelChicken(bitmap, frame) {
            const ctx = bitmap.context;
            ctx.imageSmoothingEnabled = false;
            ctx.clearRect(0, 0, 32, 32);
            
            const wobble = frame % 2 === 0 ? 0 : 1;
            
            // Body - white
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(8, 14 + wobble, 16, 12);
            ctx.fillStyle = '#F0F0F0';
            ctx.fillRect(9, 15 + wobble, 14, 10);
            
            // Head - white
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(14, 6, 10, 10);
            ctx.fillStyle = '#F0F0F0';
            ctx.fillRect(15, 7, 8, 8);
            
            // Comb - red
            ctx.fillStyle = '#FF3333';
            ctx.fillRect(20, 4, 3, 3);
            ctx.fillRect(22, 5, 2, 3);
            ctx.fillRect(20, 5, 2, 2);
            
            // Wattle - red
            ctx.fillStyle = '#FF5555';
            ctx.fillRect(24, 13, 2, 3);
            
            // Beak - orange
            ctx.fillStyle = '#FFA500';
            ctx.fillRect(24, 11, 4, 3);
            
            // Eye - black with white
            ctx.fillStyle = '#000000';
            ctx.fillRect(22, 9, 2, 2);
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(23, 9, 1, 1);
            
            // Wing - darker
            ctx.fillStyle = '#E0E0E0';
            ctx.fillRect(9, 16 + wobble, 6, 8);
            
            // Tail - light
            ctx.fillStyle = '#F5F5F5';
            ctx.fillRect(6, 14 + wobble, 3, 6);
            ctx.fillStyle = '#E8E8E8';
            ctx.fillRect(5, 13 + wobble, 2, 5);
            
            // Legs - orange
            ctx.fillStyle = '#FFA500';
            ctx.fillRect(12, 26 + wobble, 2, 4);
            ctx.fillRect(18, 26 + wobble, 2, 4);
            ctx.fillRect(11, 30 + wobble, 4, 1);
            ctx.fillRect(17, 30 + wobble, 4, 1);
        }

        createUI() {
            this._uiSprite = new Sprite();
            this._uiSprite.bitmap = new Bitmap(Graphics.width, 80);
            this.addChild(this._uiSprite);
            this.updateUI();
        }

        updateUI() {
            const bitmap = this._uiSprite.bitmap;
            bitmap.clear();
            const ctx = bitmap.context;
            ctx.imageSmoothingEnabled = false;
            
            // Background panel
            bitmap.fillRect(0, 0, Graphics.width, 80, '#2C2C2C');
            bitmap.fillRect(2, 2, Graphics.width - 4, 76, '#1A1A1A');
            
            // Player health label
            bitmap.fontSize = 20;
            bitmap.textColor = '#FFFFFF';
            bitmap.outlineColor = '#000000';
            bitmap.outlineWidth = 3;
            bitmap.drawText('HP', 10, 8, 40, 24, 'left');
            
            // Health bar frame
            bitmap.fillRect(50, 12, 202, 24, '#000000');
            bitmap.fillRect(52, 14, 198, 20, '#330000');
            
            // Health bar fill
            const healthWidth = Math.floor((this._player.health / this._player.maxHealth) * 198);
            const healthGrad = ctx.createLinearGradient(52, 14, 52 + healthWidth, 14);
            healthGrad.addColorStop(0, '#00DD00');
            healthGrad.addColorStop(1, '#00AA00');
            ctx.fillStyle = healthGrad;
            ctx.fillRect(52, 14, healthWidth, 20);
            
            // Health text
            bitmap.fontSize = 16;
            bitmap.textColor = '#FFFFFF';
            bitmap.drawText(`${this._player.health}/${this._player.maxHealth}`, 52, 14, 198, 20, 'center');
            
            // Wave counter
            bitmap.fontSize = 20;
            bitmap.drawText(`Wave: ${this._currentWave}/${this._waveCount}`, Graphics.width - 200, 8, 190, 24, 'right');
            
            // Chicken counter
            bitmap.fontSize = 18;
            bitmap.drawText(`Chickens: ${this._chickens.length}`, Graphics.width - 200, 36, 190, 24, 'right');
            
            // Controls
            bitmap.fontSize = 14;
            bitmap.textColor = '#AAAAAA';
            bitmap.outlineWidth = 2;
            bitmap.drawText('ARROWS: Move | SPACE: Attack | ESC: Quit', 10, 50, Graphics.width - 20, 20, 'center');
        }

        update() {
            super.update();
            
            if (this._battleState === "active") {
                this.updateInput();
                this.updatePlayer();
                this.updateChickens();
                this.updateProjectiles();
                this.updateCollisions();
                this.updateUI();
                this.checkWaveComplete();
                this.checkBattleEnd();
            }
        }

        updateInput() {
            // Use standard RPG Maker MZ input checks
            if (Input.isPressed('left') || Input.isPressed('right')) {
                const dir = Input.isPressed('left') ? -1 : 1;
                this._player.x += this._player.speed * dir;
                this._player.x = Math.max(0, Math.min(Graphics.width - this._player.width, this._player.x));
            }
            
            if (Input.isPressed('up') || Input.isPressed('down')) {
                const dir = Input.isPressed('up') ? -1 : 1;
                this._player.y += this._player.speed * dir;
                const groundLevel = Graphics.height * 0.55;
                this._player.y = Math.max(groundLevel - 100, Math.min(groundLevel, this._player.y));
            }
            
            // Attack with Z/Space
            if ((Input.isTriggered('ok') || Input.isPressed('ok')) && this._player.attackCooldown <= 0) {
                this.playerAttack();
                this._player.attackCooldown = 15;
            }
            
            // Escape with X/Esc
            if (Input.isTriggered('cancel')) {
                this.endBattle(false);
            }
            
            if (this._player.attackCooldown > 0) {
                this._player.attackCooldown--;
            }
        }

        playerAttack() {
            const projectile = {
                x: this._player.x + this._player.width,
                y: this._player.y + this._player.height / 2,
                width: 12,
                height: 12,
                speed: 7,
                damage: 15,
                sprite: this.createProjectileSprite()
            };
            this._projectiles.push(projectile);
            
            // Play attack sound effect
            AudioManager.playSe({
                name: 'Attack1',
                volume: 90,
                pitch: 100,
                pan: 0
            });
        }

        createProjectileSprite() {
            const sprite = new Sprite();
            sprite.bitmap = new Bitmap(12, 12);
            const ctx = sprite.bitmap.context;
            ctx.imageSmoothingEnabled = false;
            
            ctx.fillStyle = '#FFD700';
            ctx.fillRect(2, 2, 8, 8);
            ctx.fillStyle = '#FFA500';
            ctx.fillRect(3, 3, 6, 6);
            
            this.addChild(sprite);
            return sprite;
        }

        updatePlayer() {
            this._player.sprite.x = this._player.x;
            this._player.sprite.y = this._player.y;
            
            if (Graphics.frameCount % 20 === 0) {
                this._player.frame = (this._player.frame + 1) % 2;
                this.drawPixelCharacter(this._player.sprite.bitmap, this._player.frame);
            }
        }

        updateChickens() {
            for (let i = this._chickens.length - 1; i >= 0; i--) {
                const chicken = this._chickens[i];
                
                if (chicken.x > this._player.x + 80) {
                    chicken.x -= chicken.speed;
                }
                
                chicken.y += Math.sin(Graphics.frameCount * 0.05 + i) * 0.3;
                
                if (Graphics.frameCount % 15 === 0) {
                    chicken.animFrame = (chicken.animFrame + 1) % 2;
                    this.drawPixelChicken(chicken.sprite.bitmap, chicken.animFrame);
                }
                
                if (chicken.attackCooldown <= 0 && 
                    Math.abs(chicken.x - this._player.x) < 70 &&
                    Math.abs(chicken.y - this._player.y) < 50) {
                    this._player.health -= this._chickenDamage;
                    chicken.attackCooldown = 50;
                    this.showDamageEffect(this._player.x, this._player.y);
                    
                    // Play damage sound
                    AudioManager.playSe({
                        name: 'Damage1',
                        volume: 90,
                        pitch: 100,
                        pan: 0
                    });
                }
                
                if (chicken.attackCooldown > 0) {
                    chicken.attackCooldown--;
                }
                
                chicken.sprite.x = chicken.x;
                chicken.sprite.y = chicken.y;
                
                if (chicken.health <= 0) {
                    this.removeChild(chicken.sprite);
                    this._chickens.splice(i, 1);
                }
            }
        }

        showDamageEffect(x, y) {
            const sprite = new Sprite();
            sprite.bitmap = new Bitmap(40, 24);
            sprite.bitmap.textColor = '#FF0000';
            sprite.bitmap.fontSize = 16;
            sprite.bitmap.outlineColor = '#000000';
            sprite.bitmap.outlineWidth = 2;
            sprite.bitmap.drawText('-' + this._chickenDamage, 0, 4, 40, 24, 'center');
            sprite.x = x;
            sprite.y = y - 20;
            sprite.opacity = 255;
            this.addChild(sprite);
            
            setTimeout(() => {
                this.removeChild(sprite);
            }, 500);
        }

        updateProjectiles() {
            for (let i = this._projectiles.length - 1; i >= 0; i--) {
                const proj = this._projectiles[i];
                proj.x += proj.speed;
                proj.sprite.x = proj.x;
                proj.sprite.y = proj.y;
                
                if (proj.x > Graphics.width) {
                    this.removeChild(proj.sprite);
                    this._projectiles.splice(i, 1);
                }
            }
        }

        updateCollisions() {
            for (let i = this._projectiles.length - 1; i >= 0; i--) {
                const proj = this._projectiles[i];
                
                for (let j = 0; j < this._chickens.length; j++) {
                    const chicken = this._chickens[j];
                    
                    if (this.checkCollision(proj, chicken)) {
                        chicken.health -= proj.damage;
                        this.removeChild(proj.sprite);
                        this._projectiles.splice(i, 1);
                        this.showHitEffect(chicken.x, chicken.y);
                        
                        // Play hit sound
                        AudioManager.playSe({
                            name: 'Damage2',
                            volume: 80,
                            pitch: 120,
                            pan: 0
                        });
                        break;
                    }
                }
            }
        }

        showHitEffect(x, y) {
            const sprite = new Sprite();
            sprite.bitmap = new Bitmap(24, 24);
            const ctx = sprite.bitmap.context;
            ctx.imageSmoothingEnabled = false;
            
            ctx.fillStyle = '#FFFF00';
            ctx.fillRect(4, 4, 16, 16);
            ctx.fillStyle = '#FFD700';
            ctx.fillRect(6, 6, 12, 12);
            
            sprite.x = x;
            sprite.y = y;
            this.addChild(sprite);
            
            setTimeout(() => {
                this.removeChild(sprite);
            }, 200);
        }

        checkCollision(obj1, obj2) {
            return obj1.x < obj2.x + obj2.width &&
                   obj1.x + obj1.width > obj2.x &&
                   obj1.y < obj2.y + obj2.height &&
                   obj1.y + obj1.height > obj2.y;
        }

        checkWaveComplete() {
            if (this._chickens.length === 0 && this._currentWave < this._waveCount) {
                this._currentWave++;
                setTimeout(() => {
                    this.spawnWave();
                }, 1000);
            }
        }

        checkBattleEnd() {
            if (this._player.health <= 0) {
                this.endBattle(false);
            } else if (this._chickens.length === 0 && this._currentWave >= this._waveCount) {
                this.endBattle(true);
            }
        }

        endBattle(victory) {
            this._battleState = "ended";
            $gameVariables.setValue(1, victory ? 1 : 0);
            
            // Play victory or defeat sound
            if (victory) {
                AudioManager.playSe({
                    name: 'Saint5',
                    volume: 90,
                    pitch: 100,
                    pan: 0
                });
            } else {
                AudioManager.playSe({
                    name: 'Devil1',
                    volume: 90,
                    pitch: 100,
                    pan: 0
                });
            }
            
            const text = victory ? "Victory!" : "Defeated...";
            this.showResultText(text, victory);
            
            setTimeout(() => {
                SceneManager.pop();
            }, 2000);
        }

        showResultText(text, victory) {
            const sprite = new Sprite();
            sprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
            const ctx = sprite.bitmap.context;
            ctx.imageSmoothingEnabled = false;
            
            ctx.fillStyle = 'rgba(0,0,0,0.7)';
            ctx.fillRect(0, 0, Graphics.width, Graphics.height);
            
            sprite.bitmap.fontSize = 48;
            sprite.bitmap.textColor = victory ? '#00FF00' : '#FF0000';
            sprite.bitmap.outlineColor = '#000000';
            sprite.bitmap.outlineWidth = 6;
            sprite.bitmap.drawText(text, 0, Graphics.height / 2 - 50, Graphics.width, 48, 'center');
            
            this.addChild(sprite);
        }
    }

    window.Scene_ChickenBattle = Scene_ChickenBattle;
})();