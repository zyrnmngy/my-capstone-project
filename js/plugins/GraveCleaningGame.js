/*:
 * @target MZ
 * @plugindesc Interactive grave cleaning mini-game with fantasy art style
 * @author Your Name
 * @url https:filibustero-web.com
 *
 * @help GraveCleaningGame.js
 *
 * This plugin creates an interactive grave cleaning mini-game where players
 * must click on dirt spots to clean the grave before time runs out.
 *
 * Plugin Commands:
 * - Start the grave cleaning game
 * 
 * @command startGraveCleaning
 * @text Start Grave Cleaning
 * @desc Starts the grave cleaning mini-game
 *
 * @arg difficulty
 * @text Difficulty
 * @type select
 * @option Easy
 * @value easy
 * @option Normal
 * @value normal
 * @option Hard
 * @value hard
 * @default normal
 * @desc Select the difficulty level
 *
 * @arg timeLimit
 * @text Time Limit (seconds)
 * @type number
 * @min 10
 * @max 300
 * @default 60
 * @desc Time limit in seconds to clean the grave
 *
 * @arg successSwitch
 * @text Success Switch
 * @type switch
 * @default 1
 * @desc Switch to turn ON when player succeeds
 * 
 * * @arg bgmName
 * @text Cemetery BGM
 * @type file
 * @dir audio/bgm
 * @default Theme1
 * @desc Atmospheric cemetery background music
 *
 * @arg failureSwitch
 * @text Failure Switch
 * @type switch
 * @default 2
 * @desc Switch to turn ON when player fails
 */

(() => {
    'use strict';
    
    const pluginName = 'GraveCleaningGame';
    
    PluginManager.registerCommand(pluginName, 'startGraveCleaning', args => {
        const difficulty = args.difficulty || 'normal';
        const timeLimit = Number(args.timeLimit) || 60;
        const successSwitch = Number(args.successSwitch) || 1;
        const failureSwitch = Number(args.failureSwitch) || 2;
        const bgmName = args.bgmName || 'Theme1';
        
        SceneManager.push(Scene_GraveCleaning);
        SceneManager.prepareNextScene(difficulty, timeLimit, successSwitch, failureSwitch);
    });

    //=============================================================================
    // Scene_GraveCleaning
    //=============================================================================
    
    class Scene_GraveCleaning extends Scene_Base {
        initialize() {
            super.initialize();
            this._difficulty = 'normal';
            this._timeLimit = 60;
            this._successSwitch = 1;
            this._failureSwitch = 2;
            this._bgmName = 'Theme1';
        }
        
        prepare(difficulty, timeLimit, successSwitch, failureSwitch) {
            this._difficulty = difficulty;
            this._timeLimit = timeLimit;
            this._successSwitch = successSwitch;
            this._failureSwitch = failureSwitch;
        }
        
        create() {
            super.create();
            this.playGraveBGM();
            this.createBackground();
            this.createDecorations();
            this.createGrave();
            this.createCleaningTools();
            this.createDirtSpots();
            this.createUI();
            this.startGame();
        }

        playGraveBGM() {
            this._savedBgm = AudioManager.saveBgm();
            AudioManager.playBgm({
                name: this._bgmName,
                volume: 85,
                pitch: 100,
                pan: 0
            });
        }

        stop() {
            super.stop();
            if (this._savedBgm) {
                AudioManager.replayBgm(this._savedBgm);
            }
        }
        
        createBackground() {
            this._backgroundSprite = new Sprite();
            this._backgroundSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
            
            const bitmap = this._backgroundSprite.bitmap;
            const ctx = bitmap.context;
            
            // Gradient sky - mystical blue/purple
            const skyGrad = ctx.createLinearGradient(0, 0, 0, Graphics.height * 0.5);
            skyGrad.addColorStop(0, '#2a3a5a');
            skyGrad.addColorStop(1, '#4a5a7a');
            ctx.fillStyle = skyGrad;
            ctx.fillRect(0, 0, Graphics.width, Graphics.height * 0.5);
            
            // Ground gradient
            const groundGrad = ctx.createLinearGradient(0, Graphics.height * 0.5, 0, Graphics.height);
            groundGrad.addColorStop(0, '#3a5a2a');
            groundGrad.addColorStop(1, '#2a4a1a');
            ctx.fillStyle = groundGrad;
            ctx.fillRect(0, Graphics.height * 0.5, Graphics.width, Graphics.height * 0.5);
            
            // Add some clouds
            ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
            for (let i = 0; i < 5; i++) {
                const x = Math.random() * Graphics.width;
                const y = Math.random() * (Graphics.height * 0.4) + 20;
                ctx.beginPath();
                ctx.arc(x, y, 40 + Math.random() * 20, 0, Math.PI * 2);
                ctx.fill();
            }
            
            this.addChild(this._backgroundSprite);
        }
        
        createDecorations() {
            // Add some glowing particles/light effects
            this._particleSprite = new Sprite();
            this._particleSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
            const ctx = this._particleSprite.bitmap.context;
            
            // Glowing aura around grave area
            const glowGrad = ctx.createRadialGradient(Graphics.width / 2, Graphics.height * 0.6, 50, Graphics.width / 2, Graphics.height * 0.6, 300);
            glowGrad.addColorStop(0, 'rgba(200, 150, 255, 0.15)');
            glowGrad.addColorStop(1, 'rgba(200, 150, 255, 0)');
            ctx.fillStyle = glowGrad;
            ctx.fillRect(0, 0, Graphics.width, Graphics.height);
            
            this.addChild(this._particleSprite);
        }
        
        createGrave() {
            this._graveSprite = new Sprite();
            this._graveSprite.bitmap = new Bitmap(280, 450);
            this._graveSprite.x = Graphics.width / 2 - 140;
            this._graveSprite.y = Graphics.height / 2 - 150;
            
            const bitmap = this._graveSprite.bitmap;
            const ctx = bitmap.context;
            
            // Wooden grave/coffin base
            // Main box
            ctx.fillStyle = '#8B6F47';
            ctx.fillRect(30, 180, 220, 200);
            
            // Wood grain texture
            ctx.strokeStyle = '#6B4F27';
            ctx.lineWidth = 2;
            for (let y = 180; y < 380; y += 30) {
                ctx.beginPath();
                ctx.moveTo(30, y);
                ctx.lineTo(250, y);
                ctx.stroke();
            }
            
            // Horizontal wood planks
            ctx.lineWidth = 1;
            ctx.strokeStyle = '#5B3F17';
            for (let x = 50; x < 250; x += 40) {
                ctx.beginPath();
                ctx.moveTo(x, 180);
                ctx.lineTo(x, 380);
                ctx.stroke();
            }
            
            // Dirt/mud texture on grave
            ctx.fillStyle = 'rgba(139, 69, 19, 0.4)';
            for (let i = 0; i < 60; i++) {
                const x = 30 + Math.random() * 220;
                const y = 180 + Math.random() * 200;
                ctx.fillRect(x, y, 3, 3);
            }
            
            // Engraved text on grave
            ctx.fillStyle = '#2c2c2c';
            ctx.font = 'bold 22px serif';
            ctx.textAlign = 'center';
            ctx.fillText('Padre', 140, 260);
            ctx.fillText('Garrote', 140, 290);
            
            // Golden Cross on top
            ctx.fillStyle = '#FFD700';
            ctx.strokeStyle = '#DAA520';
            ctx.lineWidth = 3;
            
            // Cross vertical
            ctx.fillRect(125, 40, 30, 100);
            // Cross horizontal
            ctx.fillRect(95, 70, 90, 30);
            
            // Cross shadow/depth
            ctx.strokeRect(125, 40, 30, 100);
            ctx.strokeRect(95, 70, 90, 30);
            
            // Cross shine
            ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.fillRect(127, 42, 8, 30);
            ctx.fillRect(97, 72, 25, 8);
            
            this.addChild(this._graveSprite);
        }
        
        createCleaningTools() {
            // Broom on the right side
            this._broomSprite = new Sprite();
            this._broomSprite.bitmap = new Bitmap(120, 300);
            this._broomSprite.x = Graphics.width / 2 + 180;
            this._broomSprite.y = Graphics.height / 2 - 50;
            
            const ctx = this._broomSprite.bitmap.context;
            ctx.save();
            ctx.translate(60, 150);
            ctx.rotate(0.3);
            
            // Broom stick
            ctx.strokeStyle = '#8B4513';
            ctx.lineWidth = 8;
            ctx.beginPath();
            ctx.moveTo(0, -80);
            ctx.lineTo(0, 80);
            ctx.stroke();
            
            // Broom bristles - yellow
            ctx.fillStyle = '#FFD700';
            ctx.beginPath();
            ctx.ellipse(0, 80, 25, 35, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // Bristle details
            ctx.strokeStyle = '#FFA500';
            ctx.lineWidth = 2;
            for (let i = -20; i <= 20; i += 4) {
                ctx.beginPath();
                ctx.moveTo(i, 80);
                ctx.lineTo(i - 5, 110);
                ctx.stroke();
            }
            
            // Broom band
            ctx.fillStyle = '#CC6600';
            ctx.fillRect(-8, 65, 16, 8);
            
            ctx.restore();
            
            this.addChild(this._broomSprite);
            
            // Bucket on the left side
            this._bucketSprite = new Sprite();
            this._bucketSprite.bitmap = new Bitmap(120, 150);
            this._bucketSprite.x = Graphics.width / 2 - 320;
            this._bucketSprite.y = Graphics.height / 2 + 100;
            
            const bctx = this._bucketSprite.bitmap.context;
            
            // Bucket body - golden
            bctx.fillStyle = '#FFD700';
            bctx.beginPath();
            bctx.moveTo(30, 20);
            bctx.lineTo(50, 10);
            bctx.lineTo(90, 10);
            bctx.lineTo(110, 20);
            bctx.lineTo(105, 100);
            bctx.lineTo(25, 100);
            bctx.closePath();
            bctx.fill();
            
            // Bucket outline
            bctx.strokeStyle = '#DAA520';
            bctx.lineWidth = 2;
            bctx.stroke();
            
            // Water inside - blue glow
            bctx.fillStyle = 'rgba(100, 150, 255, 0.6)';
            bctx.beginPath();
            bctx.moveTo(35, 50);
            bctx.lineTo(105, 50);
            bctx.lineTo(100, 95);
            bctx.lineTo(40, 95);
            bctx.closePath();
            bctx.fill();
            
            // Water waves
            bctx.strokeStyle = 'rgba(150, 200, 255, 0.8)';
            bctx.lineWidth = 1;
            bctx.beginPath();
            bctx.moveTo(40, 55);
            bctx.quadraticCurveTo(72.5, 50, 105, 55);
            bctx.stroke();
            
            // Bucket handle
            bctx.strokeStyle = '#DAA520';
            bctx.lineWidth = 4;
            bctx.beginPath();
            bctx.arc(67.5, 0, 40, 0, Math.PI);
            bctx.stroke();
            
            this.addChild(this._bucketSprite);
        }
        
        createDirtSpots() {
            this._dirtSpots = [];
            this._dirtContainer = new Sprite();
            this.addChild(this._dirtContainer);
            
            const difficultySettings = {
                easy: { spots: 18, size: 50 },
                normal: { spots: 28, size: 45 },
                hard: { spots: 38, size: 40 }
            };
            
            const settings = difficultySettings[this._difficulty];
            const graveX = this._graveSprite.x;
            const graveY = this._graveSprite.y;
            
            // Create dirt spots distributed on the grave
            for (let i = 0; i < settings.spots; i++) {
                let x, y;
                
                // Spread dirt across the entire grave
                x = graveX + 50 + Math.random() * 180;
                y = graveY + 100 + Math.random() * 300;
                
                const spot = new Sprite_DirtSpot(x, y, settings.size);
                this._dirtSpots.push(spot);
                this._dirtContainer.addChild(spot);
            }
            
            this._totalDirt = settings.spots;
            this._cleanedDirt = 0;
        }
        
        createUI() {
            // Timer display - top center with ornate border
            this._timerBg = new Sprite();
            this._timerBg.bitmap = new Bitmap(250, 80);
            this._timerBg.x = Graphics.width / 2 - 125;
            this._timerBg.y = 20;
            
            const ctx = this._timerBg.bitmap.context;
            
            // Ornate background
            ctx.fillStyle = '#2a1a0a';
            ctx.fillRect(0, 0, 250, 80);
            ctx.strokeStyle = '#FFD700';
            ctx.lineWidth = 3;
            ctx.strokeRect(5, 5, 240, 70);
            
            // Decorative corners
            ctx.fillStyle = '#FFD700';
            for (let i = 0; i < 4; i++) {
                const x = i % 2 === 0 ? 8 : 242;
                const y = i < 2 ? 8 : 72;
                ctx.fillRect(x - 3, y - 3, 6, 6);
            }
            
            this.addChild(this._timerBg);
            
            // Timer text
            this._timerText = new Sprite();
            this._timerText.bitmap = new Bitmap(250, 80);
            this._timerText.x = Graphics.width / 2 - 125;
            this._timerText.y = 20;
            this.addChild(this._timerText);
            
            // Progress bar background - bottom
            this._progressBg = new Sprite();
            this._progressBg.bitmap = new Bitmap(350, 50);
            this._progressBg.x = Graphics.width / 2 - 175;
            this._progressBg.y = Graphics.height - 70;
            
            const pCtx = this._progressBg.bitmap.context;
            pCtx.fillStyle = '#2a1a0a';
            pCtx.fillRect(0, 0, 350, 50);
            pCtx.strokeStyle = '#FFD700';
            pCtx.lineWidth = 3;
            pCtx.strokeRect(5, 5, 340, 40);
            
            this.addChild(this._progressBg);
            
            // Progress bar fill
            this._progressBar = new Sprite();
            this._progressBar.bitmap = new Bitmap(340, 40);
            this._progressBar.x = Graphics.width / 2 - 170;
            this._progressBar.y = Graphics.height - 65;
            this.addChild(this._progressBar);
        }
        
        startGame() {
            this._timeRemaining = this._timeLimit;
            this._gameActive = true;
            this._gameOver = false;
            this.updateTimer();
            this.updateProgress();
        }
        
        update() {
            super.update();
            
            if (this._gameActive && !this._gameOver) {
                this._timeRemaining -= 1 / 60;
                
                if (this._timeRemaining <= 0) {
                    this._timeRemaining = 0;
                    this.gameFailed();
                }
                
                this.updateTimer();
                this.handleInput();
                
                if (this._cleanedDirt >= this._totalDirt) {
                    this.gameSuccess();
                }
            }
            
            if (this._gameOver && Input.isTriggered('ok')) {
                this.returnToMap();
            }
        }
        
        handleInput() {
            if (TouchInput.isTriggered()) {
                const x = TouchInput.x;
                const y = TouchInput.y;
                
                for (let i = this._dirtSpots.length - 1; i >= 0; i--) {
                    const spot = this._dirtSpots[i];
                    if (spot.isClicked(x, y)) {
                        spot.clean();
                        this._dirtSpots.splice(i, 1);
                        this._cleanedDirt++;
                        this.updateProgress();
                        AudioManager.playSe({name: 'Cursor1', volume: 90, pitch: 100, pan: 0});
                        break;
                    }
                }
            }
        }
        
        updateTimer() {
            const bitmap = this._timerText.bitmap;
            bitmap.clear();
            
            const minutes = Math.floor(this._timeRemaining / 60);
            const seconds = Math.floor(this._timeRemaining % 60);
            const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            bitmap.fontSize = 32;
            bitmap.textColor = this._timeRemaining < 10 ? '#ff4444' : '#FFD700';
            bitmap.outlineColor = '#2a1a0a';
            bitmap.outlineWidth = 3;
            bitmap.drawText(timeString, 0, 20, 250, 40, 'center');
        }
        
        updateProgress() {
            const bitmap = this._progressBar.bitmap;
            bitmap.clear();
            
            const progress = this._cleanedDirt / this._totalDirt;
            const barWidth = 340 * progress;
            
            // Progress bar gradient
            const ctx = bitmap.context;
            const grad = ctx.createLinearGradient(0, 0, barWidth, 0);
            grad.addColorStop(0, '#FF6B6B');
            grad.addColorStop(0.5, '#FFD700');
            grad.addColorStop(1, '#4CAF50');
            
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, barWidth, 40);
            
            // Progress text
            bitmap.fontSize = 20;
            bitmap.textColor = '#FFFFFF';
            bitmap.outlineColor = '#2a1a0a';
            bitmap.outlineWidth = 2;
            bitmap.drawText(`${this._cleanedDirt}/${this._totalDirt} Nalinis`, 0, 8, 340, 24, 'center');
        }
        
        gameSuccess() {
            this._gameActive = false;
            this._gameOver = true;
            
            $gameSwitches.setValue(this._successSwitch, true);
            $gameSwitches.setValue(this._failureSwitch, false);
            
            AudioManager.playSe({name: 'Saint5', volume: 90, pitch: 100, pan: 0});
            
            this.showResult('TAGUMPAY!', '#FFD700', 'Maayos mong nalinis ang libingan!');
        }
        
        gameFailed() {
            this._gameActive = false;
            this._gameOver = true;
            
            $gameSwitches.setValue(this._successSwitch, false);
            $gameSwitches.setValue(this._failureSwitch, true);
            
            AudioManager.playSe({name: 'Devil1', volume: 90, pitch: 100, pan: 0});
            
            this.showResult('TAPOS NA ANG ORAS!', '#ff4444', 'Subukan ulit at gawin ng mas mabilis.');
        }
        
        showResult(title, color, message) {
            const resultSprite = new Sprite();
            resultSprite.bitmap = new Bitmap(500, 250);
            resultSprite.x = Graphics.width / 2 - 250;
            resultSprite.y = Graphics.height / 2 - 125;
            
            const bitmap = resultSprite.bitmap;
            const ctx = bitmap.context;
            
            ctx.fillStyle = 'rgba(0, 0, 0, 0.9)';
            ctx.fillRect(0, 0, 500, 250);
            ctx.shadowColor = color;
            ctx.shadowBlur = 20;
            ctx.strokeStyle = color;
            ctx.lineWidth = 4;
            ctx.strokeRect(8, 8, 484, 234);
            
            bitmap.fontSize = 44;
            bitmap.textColor = color;
            bitmap.outlineColor = '#000000';
            bitmap.outlineWidth = 4;
            bitmap.drawText(title, 0, 30, 500, 50, 'center');
            
            bitmap.fontSize = 24;
            bitmap.textColor = '#FFFFFF';
            bitmap.outlineColor = '#000000';
            bitmap.outlineWidth = 3;
            bitmap.drawText(message, 0, 100, 500, 50, 'center');
            
            bitmap.fontSize = 18;
            bitmap.textColor = '#AAAAAA';
            bitmap.drawText('Pindutin ang OK', 0, 180, 500, 40, 'center');
            
            this.addChild(resultSprite);
            
            resultSprite.opacity = 0;
            const fadeIn = () => {
                if (resultSprite.opacity < 255) {
                    resultSprite.opacity += 15;
                    setTimeout(fadeIn, 16);
                }
            };
            fadeIn();
        }
        
        returnToMap() {
            SceneManager.pop();
        }
    }

    //=============================================================================
    // Sprite_DirtSpot
    //=============================================================================
    
    class Sprite_DirtSpot extends Sprite {
        initialize(x, y, size) {
            super.initialize();
            this.x = x;
            this.y = y;
            this._size = size;
            this._radius = size / 2;
            this.anchor.x = 0.5;
            this.anchor.y = 0.5;
            
            this.createBitmap();
        }
        
        createBitmap() {
            this.bitmap = new Bitmap(this._size, this._size);
            const ctx = this.bitmap.context;
            const center = this._size / 2;
            
            // Dirt spot with dark gradient
            const gradient = ctx.createRadialGradient(center, center, 0, center, center, this._radius);
            gradient.addColorStop(0, '#4a3828');
            gradient.addColorStop(0.6, '#2d1810');
            gradient.addColorStop(1, 'rgba(45, 24, 16, 0)');
            
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(center, center, this._radius, 0, Math.PI * 2);
            ctx.fill();
            
            // Dirt texture - irregular spots
            ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
            for (let i = 0; i < 30; i++) {
                const angle = Math.random() * Math.PI * 2;
                const distance = Math.random() * this._radius * 0.9;
                const dx = Math.cos(angle) * distance;
                const dy = Math.sin(angle) * distance;
                const size = Math.random() * 3 + 1;
                ctx.fillRect(center + dx - size/2, center + dy - size/2, size, size);
            }
            
            // Outer grime ring
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.3)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(center, center, this._radius - 2, 0, Math.PI * 2);
            ctx.stroke();
        }
        
        isClicked(x, y) {
            const dx = x - this.x;
            const dy = y - this.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            return distance <= this._radius;
        }
        
        clean() {
            // Particle effect on clean
            const particles = new Sprite();
            particles.bitmap = new Bitmap(this._size * 2, this._size * 2);
            particles.x = this.x - this._size;
            particles.y = this.y - this._size;
            
            const pctx = particles.bitmap.context;
            pctx.fillStyle = 'rgba(100, 200, 255, 0.6)';
            for (let i = 0; i < 15; i++) {
                const angle = (Math.PI * 2 * i) / 15;
                const distance = 20 + Math.random() * 15;
                const px = Math.cos(angle) * distance + this._size;
                const py = Math.sin(angle) * distance + this._size;
                pctx.beginPath();
                pctx.arc(px, py, 4, 0, Math.PI * 2);
                pctx.fill();
            }
            
            if (this.parent) {
                this.parent.addChild(particles);
            }
            
            // Fade out animation
            const self = this;
            const fadeOut = () => {
                self.opacity -= 30;
                particles.opacity -= 30;
                if (self.opacity > 0) {
                    setTimeout(fadeOut, 16);
                } else {
                    if (self.parent) {
                        self.parent.removeChild(self);
                        if (particles.parent) {
                            particles.parent.removeChild(particles);
                        }
                    }
                }
            };
            fadeOut();
        }
    }

    window.Scene_GraveCleaning = Scene_GraveCleaning;
})();