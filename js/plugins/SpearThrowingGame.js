/*:
 * @target MZ
 * @plugindesc Spear throwing mini-game with flat illustration style
 * @author Your Name
 * @url https:filibustero-web.com
 *
 * @help SpearThrowingGame.js
 *
 * This plugin creates a tense spear throwing mini-game where players must
 * time their throw perfectly as a crocodile approaches. Hit the target zone
 * on the power bar for a successful throw!
 *
 * Plugin Commands:
 * - Start the spear throwing mini-game
 * 
 * @command startSpearThrow
 * @text Start Spear Throw
 * @desc Starts the spear throwing mini-game
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
 * @desc Select the difficulty level (affects red zone size and crocodile speed)
 *
 * @arg attempts
 * @text Number of Attempts
 * @type number
 * @min 1
 * @max 5
 * @default 3
 * @desc Number of chances player has to hit the crocodile
 *
 * @arg crocodileSpeed
 * @text Crocodile Speed
 * @type number
 * @min 0.5
 * @max 5
 * @decimals 1
 * @default 2.0
 * @desc Speed at which crocodile approaches
 *
 * @arg powerBarSpeed
 * @text Power Bar Speed
 * @type number
 * @min 1
 * @max 10
 * @decimals 1
 * @default 3.0
 * @desc Speed of the power bar indicator
 *
 * @arg successSwitch
 * @text Success Switch
 * @type switch
 * @default 5
 * @desc Switch to turn ON when player defeats the crocodile
 *
 * @arg failureSwitch
 * @text Failure Switch
 * @type switch
 * @default 6
 * @desc Switch to turn ON when player is attacked by crocodile
 * 
 *  @arg bgmName
 * @text Cemetery BGM
 * @type file
 * @dir audio/bgm
 * @default Battle4
 * @desc Atmospheric cemetery background music
 *
 * @arg environment
 * @text Environment
 * @type select
 * @option Swamp
 * @value swamp
 * @option River
 * @value river
 * @option Jungle Shore
 * @value jungle
 * @default river
 * @desc Visual environment for the encounter
 */

(() => {
    'use strict';
    
    const pluginName = 'SpearThrowingGame';
    
    PluginManager.registerCommand(pluginName, 'startSpearThrow', args => {
        const difficulty = args.difficulty || 'normal';
        const attempts = Number(args.attempts) || 3;
        const crocodileSpeed = Number(args.crocodileSpeed) || 2.0;
        const powerBarSpeed = Number(args.powerBarSpeed) || 3.0;
        const successSwitch = Number(args.successSwitch) || 5;
        const failureSwitch = Number(args.failureSwitch) || 6;
        const environment = args.environment || 'river';
        const bgmName = args.bgmName || 'Battle4';
        
        SceneManager.push(Scene_SpearThrow);
        SceneManager.prepareNextScene(difficulty, attempts, crocodileSpeed, powerBarSpeed, successSwitch, failureSwitch, environment);
    });

    //=============================================================================
    // Scene_SpearThrow
    //=============================================================================
    
    class Scene_SpearThrow extends Scene_Base {
        initialize() {
            super.initialize();
            this._difficulty = 'normal';
            this._attempts = 3;
            this._crocodileSpeed = 2.0;
            this._powerBarSpeed = 3.0;
            this._successSwitch = 5;
            this._failureSwitch = 6;
            this._environment = 'river';
            this._bgmName = 'Battle4';
        }
        
        prepare(difficulty, attempts, crocodileSpeed, powerBarSpeed, successSwitch, failureSwitch, environment) {
            this._difficulty = difficulty;
            this._attempts = attempts;
            this._crocodileSpeed = crocodileSpeed;
            this._powerBarSpeed = powerBarSpeed;
            this._successSwitch = successSwitch;
            this._failureSwitch = failureSwitch;
            this._environment = environment;
        }
        
        create() {
            super.create();
            this.playSpearThrowBGM();
            this.createBackground();
            this.createLayers();
            this.createPlayer();
            this.createCrocodile();
            this.createPowerBar();
            this.createUI();
            this.startEncounter();
        }

        playSpearThrowBGM() {
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
            
            // Sky - bright blue gradient with layers
            const skyGrad = ctx.createLinearGradient(0, 0, 0, Graphics.height * 0.3);
            skyGrad.addColorStop(0, '#87CEEB');
            skyGrad.addColorStop(1, '#B0E0E6');
            ctx.fillStyle = skyGrad;
            ctx.fillRect(0, 0, Graphics.width, Graphics.height * 0.3);
            
            // Mountains/hills in distance - layered
            ctx.fillStyle = '#87CEEB';
            ctx.beginPath();
            ctx.moveTo(0, Graphics.height * 0.3);
            for (let x = 0; x < Graphics.width; x += 40) {
                ctx.lineTo(x, Graphics.height * 0.2 + Math.sin(x * 0.01) * 20);
            }
            ctx.lineTo(Graphics.width, Graphics.height * 0.3);
            ctx.fill();
            
            // Distant mountains - darker
            ctx.fillStyle = '#6BA8D4';
            ctx.beginPath();
            ctx.moveTo(0, Graphics.height * 0.35);
            for (let x = 0; x < Graphics.width; x += 50) {
                ctx.lineTo(x, Graphics.height * 0.25 + Math.sin(x * 0.008 + 2) * 30);
            }
            ctx.lineTo(Graphics.width, Graphics.height * 0.35);
            ctx.fill();
            
            // Clouds - flat design style
            this.drawFlatClouds(ctx);
            
            // Water - multiple layers with gradient
            const waterGrad = ctx.createLinearGradient(0, Graphics.height * 0.55, 0, Graphics.height * 0.85);
            waterGrad.addColorStop(0, '#5FA3D1');
            waterGrad.addColorStop(1, '#1E5A7F');
            ctx.fillStyle = waterGrad;
            ctx.fillRect(0, Graphics.height * 0.55, Graphics.width, Graphics.height * 0.3);
            
            // Water details/ripples
            this.drawWaterDetails(ctx);
            
            // Foreground - shore with grass
            ctx.fillStyle = '#3A5D2F';
            ctx.fillRect(0, Graphics.height * 0.85, Graphics.width, Graphics.height * 0.15);
            
            // Grass tufts - flat design
            this.drawGrassTufts(ctx);
            
            this.addChild(this._backgroundSprite);
        }
        
        drawFlatClouds(ctx) {
            const clouds = [
                {x: 100, y: 60, size: 1.2},
                {x: 350, y: 80, size: 0.9},
                {x: Graphics.width - 150, y: 100, size: 1.0}
            ];
            
            ctx.fillStyle = '#FFFFFF';
            clouds.forEach(cloud => {
                // Cloud body - rounded rectangles
                ctx.beginPath();
                ctx.arc(cloud.x - 30 * cloud.size, cloud.y, 25 * cloud.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.arc(cloud.x, cloud.y - 5 * cloud.size, 35 * cloud.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.arc(cloud.x + 30 * cloud.size, cloud.y, 25 * cloud.size, 0, Math.PI * 2);
                ctx.fill();
            });
        }
        
        drawWaterDetails(ctx) {
            // Water lines/waves
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.lineWidth = 2;
            for (let i = 0; i < 5; i++) {
                ctx.beginPath();
                ctx.moveTo(0, Graphics.height * 0.55 + i * 12);
                ctx.lineTo(Graphics.width, Graphics.height * 0.55 + i * 12);
                ctx.stroke();
            }
            
            // Water shimmer circles
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.lineWidth = 2;
            for (let i = 0; i < 8; i++) {
                const x = (i * Graphics.width / 8) + 50;
                const y = Graphics.height * 0.7;
                ctx.beginPath();
                ctx.arc(x, y, 15 + i * 5, 0, Math.PI * 2);
                ctx.stroke();
            }
        }
        
        drawGrassTufts(ctx) {
            ctx.fillStyle = '#2D4820';
            for (let i = 0; i < Graphics.width; i += 30) {
                // Grass tuft
                ctx.beginPath();
                ctx.moveTo(i, Graphics.height * 0.85);
                ctx.lineTo(i - 8, Graphics.height * 0.82);
                ctx.lineTo(i - 4, Graphics.height * 0.80);
                ctx.lineTo(i, Graphics.height * 0.78);
                ctx.lineTo(i + 4, Graphics.height * 0.80);
                ctx.lineTo(i + 8, Graphics.height * 0.82);
                ctx.closePath();
                ctx.fill();
            }
        }
        
        createLayers() {
            // Additional scenic layer sprites can go here
        }
        
        createPlayer() {
            this._playerSprite = new Sprite_FlatPlayer();
            this._playerSprite.x = Graphics.width / 4;
            this._playerSprite.y = Graphics.height * 0.78;
            this.addChild(this._playerSprite);
            
            // Spear in hand
            this._spearSprite = new Sprite_FlatSpear(false);
            this._spearSprite.x = this._playerSprite.x + 20;
            this._spearSprite.y = this._playerSprite.y - 20;
            this.addChild(this._spearSprite);
            
            // Thrown spear (hidden initially)
            this._thrownSpearSprite = new Sprite_FlatSpear(true);
            this._thrownSpearSprite.visible = false;
            this.addChild(this._thrownSpearSprite);
        }
        
        createCrocodile() {
            this._crocodileSprite = new Sprite_FlatCrocodile();
            this._crocodileSprite.x = Graphics.width + 150;
            this._crocodileSprite.y = Graphics.height * 0.68;
            this.addChild(this._crocodileSprite);
            
            this._crocodileStartX = Graphics.width + 150;
            this._crocodileAttackX = Graphics.width / 2;
        }
        
        createPowerBar() {
            const barWidth = 400;
            const barHeight = 60;
            
            // Power bar background
            this._powerBarBg = new Sprite();
            this._powerBarBg.bitmap = new Bitmap(barWidth, barHeight);
            this._powerBarBg.x = Graphics.width / 2 - barWidth / 2;
            this._powerBarBg.y = 50;
            
            const ctx = this._powerBarBg.bitmap.context;
            ctx.fillStyle = 'rgba(20, 20, 20, 0.8)';
            ctx.fillRect(0, 0, barWidth, barHeight);
            ctx.strokeStyle = '#E8B14F';
            ctx.lineWidth = 4;
            ctx.strokeRect(4, 4, barWidth - 8, barHeight - 8);
            
            this.addChild(this._powerBarBg);
            
            // Power zones
            this._powerZones = new Sprite();
            this._powerZones.bitmap = new Bitmap(barWidth - 20, barHeight - 20);
            this._powerZones.x = Graphics.width / 2 - barWidth / 2 + 10;
            this._powerZones.y = 60;
            
            this.drawPowerZones();
            this.addChild(this._powerZones);
            
            // Power indicator
            this._powerIndicator = new Sprite();
            this._powerIndicator.bitmap = new Bitmap(12, barHeight - 20);
            this._powerIndicator.y = 60;
            
            const indCtx = this._powerIndicator.bitmap.context;
            indCtx.fillStyle = '#FFD700';
            indCtx.fillRect(0, 0, 12, barHeight - 20);
            indCtx.shadowColor = '#FFD700';
            indCtx.shadowBlur = 20;
            
            this.addChild(this._powerIndicator);
            
            this._powerBarX = Graphics.width / 2 - barWidth / 2 + 10;
            this._powerBarWidth = barWidth - 20;
            this._indicatorPosition = 0;
            this._indicatorDirection = 1;
        }
        
        drawPowerZones() {
            const bitmap = this._powerZones.bitmap;
            const ctx = bitmap.context;
            const width = bitmap.width;
            const height = bitmap.height;
            
            const redZoneSizes = {
                easy: 0.3,
                normal: 0.2,
                hard: 0.12
            };
            
            const redZoneSize = redZoneSizes[this._difficulty] || 0.2;
            const redZoneStart = 0.55;
            
            // Miss zone (left) - flat design
            ctx.fillStyle = '#8B7355';
            ctx.fillRect(0, 0, width * redZoneStart, height);
            
            // Perfect zone (middle) - bright green
            ctx.fillStyle = '#4CAF50';
            ctx.fillRect(width * redZoneStart, 0, width * redZoneSize, height);
            
            // Miss zone (right)
            ctx.fillStyle = '#8B7355';
            ctx.fillRect(width * (redZoneStart + redZoneSize), 0, width * (1 - redZoneStart - redZoneSize), height);
            
            // Zone labels - flat style text
            ctx.fillStyle = '#FFFFFF';
            ctx.font = 'bold 14px Arial';
            ctx.textAlign = 'center';
            
            ctx.fillText('MAAGA', width * 0.25, height / 2 + 6);
            ctx.fillText('PERPEKTO!', width * (redZoneStart + redZoneSize / 2), height / 2 + 6);
            ctx.fillText('HULI', width * (redZoneStart + redZoneSize + 0.2), height / 2 + 6);
            
            this._redZoneStart = redZoneStart;
            this._redZoneEnd = redZoneStart + redZoneSize;
        }
        
        createUI() {
            // Title/instruction at top
            this._instructionSprite = new Sprite();
            this._instructionSprite.bitmap = new Bitmap(Graphics.width, 80);
            this._instructionSprite.y = 10;
            
            const bitmap = this._instructionSprite.bitmap;
            bitmap.fontSize = 28;
            bitmap.textColor = '#FFFFFF';
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.8)';
            bitmap.outlineWidth = 5;
            bitmap.drawText('Pindutin ang OK para ihagis ang sibat!', 0, 20, Graphics.width, 40, 'center');
            
            this.addChild(this._instructionSprite);
            
            // Attempts counter - top right
            this._attemptsSprite = new Sprite();
            this._attemptsSprite.bitmap = new Bitmap(200, 80);
            this._attemptsSprite.x = Graphics.width - 220;
            this._attemptsSprite.y = Graphics.height - 100;
            this.addChild(this._attemptsSprite);
            
            // Retry prompt (hidden initially)
            this._retrySprite = new Sprite();
            this._retrySprite.bitmap = new Bitmap(Graphics.width, 60);
            this._retrySprite.y = Graphics.height - 140;
            this._retrySprite.visible = false;
            
            const retryBitmap = this._retrySprite.bitmap;
            retryBitmap.fontSize = 26;
            retryBitmap.textColor = '#FFD700';
            retryBitmap.outlineColor = 'rgba(0, 0, 0, 0.9)';
            retryBitmap.outlineWidth = 5;
            retryBitmap.drawText('Pindutin ang OK para subukan ulit!', 0, 10, Graphics.width, 40, 'center');
            
            this.addChild(this._retrySprite);
            
            this.updateAttemptsDisplay();
        }
        
        startEncounter() {
            this._phase = 'approaching';
            this._gameActive = true;
            this._gameOver = false;
            this._currentAttempt = 0;
            this._hasThrown = false;
            this._powerBarActive = true;
            this._waitingForRetry = false;
        }
        
        update() {
            super.update();
            
            if (this._gameActive && !this._gameOver) {
                if (this._phase === 'approaching') {
                    this.updateApproaching();
                    this.updatePowerBar();
                    this.handleThrowInput();
                } else if (this._phase === 'throwing') {
                    this.updateThrowing();
                }
            }
            
            // Handle retry input
            if (this._waitingForRetry && (Input.isTriggered('ok') || (typeof TouchInput !== 'undefined' && TouchInput.isTriggered()))) {
                this.retryAttempt();
            }
            
            if (this._gameOver && this._gameOverDelay <= 0 && Input.isTriggered('ok')) {
                this.returnToMap();
            }
            
            if (this._gameOverDelay > 0) {
                this._gameOverDelay--;
            }
        }
        
        updateApproaching() {
            this._crocodileSprite.x -= this._crocodileSpeed;
            
            if (this._crocodileSprite.x <= this._crocodileAttackX && !this._hasThrown) {
                this.crocodileAttacks();
            }
        }
        
        updatePowerBar() {
            if (!this._powerBarActive) return;
            
            this._indicatorPosition += this._indicatorDirection * (this._powerBarSpeed / 60);
            
            if (this._indicatorPosition >= 1) {
                this._indicatorPosition = 1;
                this._indicatorDirection = -1;
            } else if (this._indicatorPosition <= 0) {
                this._indicatorPosition = 0;
                this._indicatorDirection = 1;
            }
            
            this._powerIndicator.x = this._powerBarX + this._indicatorPosition * this._powerBarWidth;
        }
        
        handleThrowInput() {
            if (Input.isTriggered('ok') || (typeof TouchInput !== 'undefined' && TouchInput.isTriggered())) {
                this.throwSpear();
            }
        }
        
        throwSpear() {
            if (this._hasThrown || this._waitingForRetry) return;
            
            this._hasThrown = true;
            this._powerBarActive = false;
            this._phase = 'throwing';
            
            const inRedZone = this._indicatorPosition >= this._redZoneStart && 
                            this._indicatorPosition <= this._redZoneEnd;
            
            this._spearSprite.visible = false;
            
            this._thrownSpearSprite.visible = true;
            this._thrownSpearSprite.x = this._playerSprite.x + 30;
            this._thrownSpearSprite.y = this._playerSprite.y - 15;
            this._thrownSpearSprite.rotation = -Math.PI / 6;
            
            this._spearStartX = this._thrownSpearSprite.x;
            this._spearStartY = this._thrownSpearSprite.y;
            this._spearTargetX = this._crocodileSprite.x;
            this._spearTargetY = this._crocodileSprite.y - 20;
            this._spearProgress = 0;
            
            this._playerSprite.throw();
            
            this._throwResult = inRedZone ? 'hit' : 'miss';
        }
        
        updateThrowing() {
            this._spearProgress += 0.05;
            
            if (this._spearProgress >= 1) {
                this._spearProgress = 1;
                
                if (this._throwResult === 'hit') {
                    this.spearHit();
                } else {
                    this.spearMiss();
                }
            }
            
            const t = this._spearProgress;
            const arc = Math.sin(t * Math.PI) * 60;
            
            this._thrownSpearSprite.x = this._spearStartX + (this._spearTargetX - this._spearStartX) * t;
            this._thrownSpearSprite.y = this._spearStartY + (this._spearTargetY - this._spearStartY) * t - arc;
            this._thrownSpearSprite.rotation = -Math.PI / 6 + t * Math.PI / 3;
        }
        
        spearHit() {
            this._phase = 'result_delay';
            this._gameActive = false;
            this._gameOver = true;
            
            this._crocodileSprite.setColorTone([255, 100, 100, 0]);
            this._crocodileSprite.defeat();
            
            $gameSwitches.setValue(this._successSwitch, true);
            $gameSwitches.setValue(this._failureSwitch, false);
            
            AudioManager.playSe({name: 'Saint5', volume: 90, pitch: 100, pan: 0});
            
            setTimeout(() => {
                this.showResult('TAGUMPAY!', '#4CAF50', 'Perpekto ang iyong paghagis!', 'Pindutin ang OK upang magpatuloy');
                this._gameOverDelay = 60;
            }, 1000);
        }
        
        spearMiss() {
            this._phase = 'result_delay';
            this._currentAttempt++;
            
            const missType = this._indicatorPosition < this._redZoneStart ? 'MASYADONG MAAGA!' : 'MASYADONG HULI!';
            
            this._powerZones.setColorTone([255, 0, 0, 0]);
            setTimeout(() => {
                this._powerZones.setColorTone([0, 0, 0, 0]);
            }, 200);
            
            if (this._currentAttempt < this._attempts) {
                setTimeout(() => {
                    this.promptForRetry(missType);
                }, 1500);
            } else {
                setTimeout(() => {
                    this.crocodileAttacks();
                }, 1000);
            }
            
            this.updateAttemptsDisplay();
        }
        
        promptForRetry(missType) {
            this.showTemporaryMessage(missType, '#FF8C42');
            
            // Show retry prompt
            this._retrySprite.visible = true;
            this._waitingForRetry = true;
        }
        
        retryAttempt() {
            this._retrySprite.visible = false;
            this._waitingForRetry = false;
            
            this._thrownSpearSprite.visible = false;
            this._spearSprite.visible = true;
            this._hasThrown = false;
            
            // Reset crocodile position (move it back)
            this._crocodileSprite.x = Math.min(this._crocodileSprite.x + 150, this._crocodileStartX);
            
            this._powerBarActive = true;
            this._indicatorPosition = 0;
            this._indicatorDirection = 1;
            
            this._phase = 'approaching';
            this._gameActive = true;
        }
        
        crocodileAttacks() {
            this._phase = 'result_delay';
            this._gameActive = false;
            this._gameOver = true;
            
            this._crocodileSprite.attack();
            
            this.shakeScreen();
            
            $gameSwitches.setValue(this._successSwitch, false);
            $gameSwitches.setValue(this._failureSwitch, true);
            
            AudioManager.playSe({name: 'Devil1', volume: 90, pitch: 100, pan: 0});
            
            setTimeout(() => {
                this.showResult('NABIGO KA!', '#E74C3C', 'Napakabilis ng buwaya...', 'Pindutin ang OK upang magpatuloy');
                this._gameOverDelay = 60;
            }, 1500);
        }
        
        shakeScreen() {
            let shakeCount = 0;
            const maxShakes = 10;
            const shakeInterval = setInterval(() => {
                if (shakeCount >= maxShakes) {
                    clearInterval(shakeInterval);
                    this.x = 0;
                    this.y = 0;
                    return;
                }
                
                this.x = (Math.random() - 0.5) * 20;
                this.y = (Math.random() - 0.5) * 20;
                shakeCount++;
            }, 50);
        }
        
        updateAttemptsDisplay() {
            const bitmap = this._attemptsSprite.bitmap;
            bitmap.clear();
            
            bitmap.fontSize = 24;
            bitmap.textColor = '#FFFFFF';
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.9)';
            bitmap.outlineWidth = 4;
            
            const remaining = this._attempts - this._currentAttempt;
            const color = remaining <= 1 ? '#FF6B6B' : '#FFFFFF';
            bitmap.textColor = color;
            
            bitmap.drawText(`Pagkakataon: ${remaining}/${this._attempts}`, 0, 20, 200, 40, 'right');
        }
        
        showTemporaryMessage(text, color) {
            const msgSprite = new Sprite();
            msgSprite.bitmap = new Bitmap(400, 80);
            msgSprite.x = Graphics.width / 2 - 200;
            msgSprite.y = Graphics.height / 2 - 40;
            
            const bitmap = msgSprite.bitmap;
            bitmap.fontSize = 40;
            bitmap.textColor = color;
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.9)';
            bitmap.outlineWidth = 6;
            bitmap.drawText(text, 0, 20, 400, 40, 'center');
            
            this.addChild(msgSprite);
            
            msgSprite.opacity = 255;
            const fadeOut = () => {
                msgSprite.opacity -= 15;
                if (msgSprite.opacity > 0) {
                    setTimeout(fadeOut, 30);
                } else {
                    this.removeChild(msgSprite);
                }
            };
            setTimeout(fadeOut, 500);
        }
        
        showResult(title, color, message, instruction) {
            const resultSprite = new Sprite();
            resultSprite.bitmap = new Bitmap(600, 300);
            resultSprite.x = Graphics.width / 2 - 300;
            resultSprite.y = Graphics.height / 2 - 150;
            
            const bitmap = resultSprite.bitmap;
            const ctx = bitmap.context;
            
            ctx.fillStyle = 'rgba(20, 20, 20, 0.95)';
            ctx.fillRect(0, 0, 600, 300);
            ctx.strokeStyle = color;
            ctx.lineWidth = 5;
            ctx.strokeRect(6, 6, 588, 288);
            
            bitmap.fontSize = 44;
            bitmap.textColor = color;
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.9)';
            bitmap.outlineWidth = 5;
            bitmap.drawText(title, 0, 50, 600, 50, 'center');
            
            bitmap.fontSize = 24;
            bitmap.textColor = '#FFFFFF';
            bitmap.drawText(message, 0, 130, 600, 40, 'center');
            
            bitmap.fontSize = 18;
            bitmap.textColor = '#CCCCCC';
            bitmap.drawText(instruction, 0, 220, 600, 40, 'center');
            
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
    // Sprite_FlatPlayer - Flat design character
    //=============================================================================
    
    class Sprite_FlatPlayer extends Sprite {
        initialize() {
            super.initialize();
            this.anchor.x = 0.5;
            this.anchor.y = 1;
            this.createPlayerBitmap();
            this._throwAnimation = 0;
        }
        
        createPlayerBitmap() {
            const width = 50;
            const height = 70;
            this.bitmap = new Bitmap(width, height);
            const ctx = this.bitmap.context;
            
            // Flat design hunter character - bright colors
            // Head - circle
            ctx.fillStyle = '#FFB366';
            ctx.beginPath();
            ctx.arc(width / 2, 12, 8, 0, Math.PI * 2);
            ctx.fill();
            
            // Hair - flat design
            ctx.fillStyle = '#4A3728';
            ctx.fillRect(width / 2 - 9, 5, 18, 8);
            
            // Eyes - simple dots
            ctx.fillStyle = '#000000';
            ctx.fillRect(width / 2 - 4, 10, 2, 2);
            ctx.fillRect(width / 2 + 2, 10, 2, 2);
            
            // Body - trapezoid shape
            ctx.fillStyle = '#FF6B35';
            ctx.beginPath();
            ctx.moveTo(width / 2 - 10, 20);
            ctx.lineTo(width / 2 - 12, 45);
            ctx.lineTo(width / 2 + 12, 45);
            ctx.lineTo(width / 2 + 10, 20);
            ctx.closePath();
            ctx.fill();
            
            // Arms
            ctx.fillStyle = '#FFB366';
            ctx.fillRect(width / 2 - 15, 22, 5, 18);
            ctx.fillRect(width / 2 + 10, 20, 5, 20);
            
            // Legs
            ctx.fillStyle = '#2C1810';
            ctx.fillRect(width / 2 - 6, 45, 4, 22);
            ctx.fillRect(width / 2 + 2, 45, 4, 22);
            
            // Feet
            ctx.fillStyle = '#8B4513';
            ctx.fillRect(width / 2 - 7, 66, 6, 4);
            ctx.fillRect(width / 2 + 1, 66, 6, 4);
        }
        
        throw() {
            this._throwAnimation = 20;
        }
        
        update() {
            super.update();
            
            if (this._throwAnimation > 0) {
                this._throwAnimation--;
                const progress = 1 - (this._throwAnimation / 20);
                this.rotation = progress * 0.2;
                
                if (this._throwAnimation === 0) {
                    this.rotation = 0;
                }
            }
        }
    }

    //=============================================================================
    // Sprite_FlatSpear - Flat design spear
    //=============================================================================
    
    class Sprite_FlatSpear extends Sprite {
        initialize(isThrown) {
            super.initialize();
            this.anchor.x = 0.5;
            this.anchor.y = 0.5;
            this._isThrown = isThrown;
            this.createSpearBitmap();
        }
        
        createSpearBitmap() {
            const length = this._isThrown ? 90 : 75;
            const width = 12;
            this.bitmap = new Bitmap(length, width);
            const ctx = this.bitmap.context;
            
            // Spear shaft - flat brown
            ctx.fillStyle = '#8B6F47';
            ctx.fillRect(0, width / 2 - 2, length - 20, 4);
            
            // Spear tip - triangular, bright color
            ctx.fillStyle = '#FF6B35';
            ctx.beginPath();
            ctx.moveTo(length - 20, width / 2 - 5);
            ctx.lineTo(length, width / 2);
            ctx.lineTo(length - 20, width / 2 + 5);
            ctx.closePath();
            ctx.fill();
            
            // Tip outline for clarity
            ctx.strokeStyle = '#E74C3C';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(length - 20, width / 2 - 5);
            ctx.lineTo(length, width / 2);
            ctx.lineTo(length - 20, width / 2 + 5);
            ctx.closePath();
            ctx.stroke();
        }
    }

    //=============================================================================
    // Sprite_FlatCrocodile - Flat design crocodile
    //=============================================================================
    
    class Sprite_FlatCrocodile extends Sprite {
        initialize() {
            super.initialize();
            this.anchor.x = 0.5;
            this.anchor.y = 1;
            this.createCrocodileBitmap();
            this._animCounter = 0;
            this._isAttacking = false;
            this._isDefeated = false;
        }
        
        createCrocodileBitmap() {
            const width = 200;
            const height = 100;
            this.bitmap = new Bitmap(width, height);
            const ctx = this.bitmap.context;
            
            // Flat design crocodile - green
            // Shadow
            ctx.fillStyle = 'rgba(0, 0, 0, 0.15)';
            ctx.beginPath();
            ctx.ellipse(width / 2, height - 8, 85, 15, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // Tail
            ctx.fillStyle = '#5FA856';
            ctx.beginPath();
            ctx.moveTo(width / 2 + 60, height - 30);
            ctx.quadraticCurveTo(width / 2 + 90, height - 35, width / 2 + 110, height - 25);
            ctx.quadraticCurveTo(width / 2 + 90, height - 18, width / 2 + 60, height - 20);
            ctx.closePath();
            ctx.fill();
            
            // Tail edge
            ctx.strokeStyle = '#4A8C42';
            ctx.lineWidth = 3;
            ctx.stroke();
            
            // Main body - large oval
            ctx.fillStyle = '#5FA856';
            ctx.beginPath();
            ctx.ellipse(width / 2, height - 28, 65, 25, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // Body edge
            ctx.strokeStyle = '#4A8C42';
            ctx.lineWidth = 3;
            ctx.stroke();
            
            // Back ridge bumps
            ctx.fillStyle = '#4A8C42';
            for (let i = 0; i < 7; i++) {
                const x = width / 2 - 40 + i * 12;
                ctx.beginPath();
                ctx.arc(x, height - 48, 5, 0, Math.PI);
                ctx.fill();
            }
            
            // Belly - lighter green
            ctx.fillStyle = '#8FBC8F';
            ctx.beginPath();
            ctx.ellipse(width / 2, height - 22, 58, 18, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // Legs - four solid rectangles
            ctx.fillStyle = '#5FA856';
            // Back legs
            ctx.fillRect(width / 2 + 25, height - 22, 12, 25);
            ctx.fillRect(width / 2 + 42, height - 22, 12, 25);
            // Front legs
            ctx.fillRect(width / 2 - 50, height - 22, 12, 25);
            ctx.fillRect(width / 2 - 33, height - 22, 12, 25);
            
            // Leg edges
            ctx.strokeStyle = '#4A8C42';
            ctx.lineWidth = 2;
            for (let legX of [width / 2 + 25, width / 2 + 42, width / 2 - 50, width / 2 - 33]) {
                ctx.strokeRect(legX, height - 22, 12, 25);
            }
            
            // Head - separate section
            ctx.fillStyle = '#5FA856';
            ctx.beginPath();
            ctx.ellipse(width / 2 - 55, height - 32, 22, 18, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            
            // Snout - long triangular
            ctx.fillStyle = '#5FA856';
            ctx.beginPath();
            ctx.moveTo(width / 2 - 77, height - 32);
            ctx.lineTo(width / 2 - 110, height - 30);
            ctx.lineTo(width / 2 - 110, height - 24);
            ctx.lineTo(width / 2 - 77, height - 26);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
            
            // Nostril
            ctx.fillStyle = '#000000';
            ctx.beginPath();
            ctx.arc(width / 2 - 105, height - 30, 2, 0, Math.PI * 2);
            ctx.fill();
            
            // Eye - yellow menacing
            ctx.fillStyle = '#FFD700';
            ctx.beginPath();
            ctx.ellipse(width / 2 - 60, height - 40, 7, 6, 0, 0, Math.PI * 2);
            ctx.fill();
            
            // Pupil - vertical slit
            ctx.fillStyle = '#000000';
            ctx.fillRect(width / 2 - 62, height - 44, 2, 10);
            
            // Teeth - white lines on jaw
            ctx.strokeStyle = '#FFFFFF';
            ctx.lineWidth = 2;
            for (let i = 0; i < 10; i++) {
                const toothX = width / 2 - 108 + i * 3;
                ctx.beginPath();
                ctx.moveTo(toothX, height - 25);
                ctx.lineTo(toothX, height - 20);
                ctx.stroke();
            }
        }
        
        update() {
            super.update();
            
            if (!this._isDefeated && !this._isAttacking) {
                // Gentle bobbing animation
                this._animCounter += 0.06;
                this.y += Math.sin(this._animCounter) * 0.8;
            }
            
            if (this._isAttacking && this._attackFrame < this._maxAttackFrames) {
                this._attackFrame++;
                const progress = this._attackFrame / this._maxAttackFrames;
                
                if (progress < 0.5) {
                    this.x -= 10;
                    this.rotation = -progress * 0.3;
                    this.scaleX = 1 + progress * 0.15;
                } else {
                    this.rotation = -0.15 + (progress - 0.5) * 0.3;
                }
                
                if (this._attackFrame >= this._maxAttackFrames) {
                    this.rotation = 0.15;
                }
            }
            
            if (this._isDefeated && this._defeatFrame < this._maxDefeatFrames) {
                this._defeatFrame++;
                const progress = this._defeatFrame / this._maxDefeatFrames;
                
                this.rotation = progress * Math.PI * 0.8;
                this.opacity = 255 - (progress * 150);
                this.y += progress * 2;
                this.x += (progress - 0.5) * 1.5;
            }
        }
        
        attack() {
            this._isAttacking = true;
            this._attackFrame = 0;
            this._maxAttackFrames = 30;
        }
        
        defeat() {
            this._isDefeated = true;
            this._defeatFrame = 0;
            this._maxDefeatFrames = 80;
        }
    }

    // Register the scene globally
    window.Scene_SpearThrow = Scene_SpearThrow;
})();