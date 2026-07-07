/*:
 * @target MZ
 * @plugindesc Dream sequence piano tiles mini-game with music game style
 * @author Your Name
 * @url https:filibustero-web.com
 *
 * @help DreamSequencePianoTiles.js
 *
 * This plugin creates a dream sequence piano tiles mini-game with a modern
 * music game aesthetic. Players must tap tiles in the correct lanes before
 * they reach the hit zone at the bottom.
 *
 * Plugin Commands:
 * - Start the dream sequence piano tiles game
 * 
 * @command startDreamPianoTiles
 * @text Start Dream Piano Tiles
 * @desc Starts the dream sequence piano tiles mini-game
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
 * * @arg bgmName
 * @text Dream BGM
 * @type file
 * @dir audio/bgm
 * @default Castle3
 * @desc Dreamy background music for the mini-game
 *
 * @arg targetScore
 * @text Target Score
 * @type number
 * @min 10
 * @max 100
 * @default 30
 * @desc Number of tiles player must hit to succeed
 *
 * @arg speed
 * @text Scroll Speed
 * @type number
 * @min 1
 * @max 10
 * @decimals 1
 * @default 3.0
 * @desc Speed at which tiles scroll down
 *
 * @arg successSwitch
 * @text Success Switch
 * @type switch
 * @default 3
 * @desc Switch to turn ON when player succeeds
 *
 * @arg failureSwitch
 * @text Failure Switch
 * @type switch
 * @default 4
 * @desc Switch to turn ON when player fails
 *
 * @arg dreamTheme
 * @text Dream Theme
 * @type select
 * @option Sunset
 * @value sunset
 * @option Ocean
 * @value ocean
 * @option Forest
 * @value forest
 * @default sunset
 * @desc Visual theme for the dream sequence
 */

(() => {
    'use strict';
    
    const pluginName = 'DreamSequencePianoTiles';
    
    PluginManager.registerCommand(pluginName, 'startDreamPianoTiles', args => {
        const difficulty = args.difficulty || 'normal';
        const targetScore = Number(args.targetScore) || 30;
        const speed = Number(args.speed) || 3.0;
        const successSwitch = Number(args.successSwitch) || 3;
        const failureSwitch = Number(args.failureSwitch) || 4;
        const dreamTheme = args.dreamTheme || 'sunset';
        const bgmName = args.bgmName || 'Castle3';
        
        SceneManager.push(Scene_DreamPianoTiles);
        SceneManager.prepareNextScene(difficulty, targetScore, speed, successSwitch, failureSwitch, dreamTheme);
    });

    //=============================================================================
    // Scene_DreamPianoTiles
    //=============================================================================
    
    class Scene_DreamPianoTiles extends Scene_Base {
        initialize() {
            super.initialize();
            this._difficulty = 'normal';
            this._targetScore = 30;
            this._speed = 3.0;
            this._successSwitch = 3;
            this._failureSwitch = 4;
            this._dreamTheme = 'sunset';
            this._bgmName = 'Castle3'
        }
        
        prepare(difficulty, targetScore, speed, successSwitch, failureSwitch, dreamTheme) {
            this._difficulty = difficulty;
            this._targetScore = targetScore;
            this._speed = speed;
            this._successSwitch = successSwitch;
            this._failureSwitch = failureSwitch;
            this._dreamTheme = dreamTheme;
        }
        
        create() {
            super.create();
            this.createBackground();
            this.playDreamBGM(); 
            this.createLanes();
            this.createFeatherPen();
            this.createHitZone();
            this.createUI();
            this.startGame();
        }
        playDreamBGM() {
            this._savedBgm = AudioManager.saveBgm();
            AudioManager.playBgm({
                name: this._bgmName,
                volume: 90,
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
            
            // Theme-based background with gradient
            const themes = {
                sunset: { 
                    colors: ['#FF6B35', '#F7931E', '#FDB833', '#FFF200'],
                    glowColor: 'rgba(255, 107, 53, 0.3)'
                },
                ocean: { 
                    colors: ['#1A1A4D', '#2D3E7E', '#4B63B8', '#6B8FD4'],
                    glowColor: 'rgba(43, 62, 126, 0.3)'
                },
                forest: { 
                    colors: ['#1B4332', '#2D6A4F', '#40916C', '#52B788'],
                    glowColor: 'rgba(27, 67, 50, 0.3)'
                }
            };
            
            const theme = themes[this._dreamTheme] || themes.sunset;
            
            // Main gradient background
            const gradient = ctx.createLinearGradient(0, 0, 0, Graphics.height);
            gradient.addColorStop(0, theme.colors[0]);
            gradient.addColorStop(0.33, theme.colors[1]);
            gradient.addColorStop(0.66, theme.colors[2]);
            gradient.addColorStop(1, theme.colors[3]);
            
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, Graphics.width, Graphics.height);
            
            // Add particle/glow effects
            for (let i = 0; i < 30; i++) {
                const x = Math.random() * Graphics.width;
                const y = Math.random() * Graphics.height;
                const radius = Math.random() * 40 + 20;
                
                ctx.fillStyle = theme.glowColor;
                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.fill();
            }
            
            // Animated lines/waves
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.lineWidth = 2;
            for (let i = 0; i < 5; i++) {
                ctx.beginPath();
                ctx.moveTo(0, Graphics.height * 0.2 + i * 60);
                ctx.lineTo(Graphics.width, Graphics.height * 0.2 + i * 60);
                ctx.stroke();
            }
            
            this.addChild(this._backgroundSprite);
        }
        
        createLanes() {
            this._laneWidth = Graphics.width / 4;
            this._numLanes = 4;
            this._laneStartX = 0;
            this._currentLane = 1; // Start in second lane
            
            // Container for all tiles
            this._tilesContainer = new Sprite();
            this.addChild(this._tilesContainer);
            
            // Draw lane dividers
            this._linesSprite = new Sprite();
            this._linesSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
            const bitmap = this._linesSprite.bitmap;
            const ctx = bitmap.context;
            
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
            ctx.lineWidth = 3;
            
            // Vertical lane dividers
            for (let i = 1; i < this._numLanes; i++) {
                const x = i * this._laneWidth;
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, Graphics.height);
                ctx.stroke();
            }
            
            this.addChild(this._linesSprite);
            
            // Active tiles array
            this._tiles = [];
        }
        
        createFeatherPen() {
            this._featherPen = new Sprite_FeatherPen(this._dreamTheme);
            this._featherPen.y = Graphics.height - 140;
            this.addChild(this._featherPen);
            this.updatePenPosition();
        }
        
        updatePenPosition() {
            const targetX = this._currentLane * this._laneWidth + this._laneWidth / 2;
            this._featherPen.targetX = targetX;
        }
        
        createHitZone() {
            this._hitZoneY = Graphics.height - 120;
            this._hitZoneSprite = new Sprite();
            this._hitZoneSprite.bitmap = new Bitmap(Graphics.width, 150);
            this._hitZoneSprite.x = 0;
            this._hitZoneSprite.y = this._hitZoneY - 75;
            
            const ctx = this._hitZoneSprite.bitmap.context;
            
            // Hit zone background
            ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
            ctx.fillRect(0, 0, Graphics.width, 150);
            
            // Target hit line - glowing effect
            ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
            ctx.fillRect(0, 70, Graphics.width, 10);
            
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(0, 75);
            ctx.lineTo(Graphics.width, 75);
            ctx.stroke();
            
            // Shadow effect
            ctx.fillStyle = 'rgba(0, 0, 0, 0.2)';
            ctx.fillRect(0, 80, Graphics.width, 20);
            
            this.addChild(this._hitZoneSprite);
        }
        
        createUI() {
            // Score display
            this._scoreSprite = new Sprite();
            this._scoreSprite.bitmap = new Bitmap(300, 80);
            this._scoreSprite.x = 20;
            this._scoreSprite.y = 20;
            this.addChild(this._scoreSprite);
            
            // Target display
            this._targetSprite = new Sprite();
            this._targetSprite.bitmap = new Bitmap(300, 80);
            this._targetSprite.x = Graphics.width - 320;
            this._targetSprite.y = 20;
            this.addChild(this._targetSprite);
            
            // Combo display - center top
            this._comboSprite = new Sprite();
            this._comboSprite.bitmap = new Bitmap(250, 100);
            this._comboSprite.x = Graphics.width / 2 - 125;
            this._comboSprite.y = 60;
            this.addChild(this._comboSprite);
            
            // Misses display
            this._missesSprite = new Sprite();
            this._missesSprite.bitmap = new Bitmap(300, 60);
            this._missesSprite.x = Graphics.width / 2 - 150;
            this._missesSprite.y = Graphics.height - 100;
            this.addChild(this._missesSprite);
        }
        
        startGame() {
            this._score = 0;
            this._misses = 0;
            this._combo = 0;
            this._maxCombo = 0;
            this._gameActive = true;
            this._gameOver = false;
            
            // Difficulty-based spawn rate
            const spawnRates = {
                easy: 90,
                normal: 60,
                hard: 45
            };
            this._spawnRate = spawnRates[this._difficulty] || 60;
            this._spawnCounter = this._spawnRate;
            
            // Allow 10 misses before game over
            this._maxMisses = 10;
            
            this.updateScoreDisplay();
            this.updateTargetDisplay();
            this.updateMissesDisplay();
        }
        
        update() {
            super.update();
            
            if (this._gameActive && !this._gameOver) {
                this.updateTiles();
                this.spawnTiles();
                this.handleInput();
            }
            
            if (this._gameOver && Input.isTriggered('ok')) {
                this.returnToMap();
            }
        }
        
        updateTiles() {
            for (let i = this._tiles.length - 1; i >= 0; i--) {
                const tile = this._tiles[i];
                tile.y += this._speed;
                
                // Check if tile is in hit zone
                if (!tile.hit && tile.y >= this._hitZoneY - 40 && tile.y <= this._hitZoneY + 40) {
                    if (tile.lane === this._currentLane) {
                        this.hitTile(tile);
                    }
                } else if (!tile.hit && tile.y > this._hitZoneY + 50) {
                    // Missed tile
                    this.missTile(tile);
                }
                
                // Remove off-screen tiles
                if (tile.y > Graphics.height + 50) {
                    this._tilesContainer.removeChild(tile);
                    this._tiles.splice(i, 1);
                }
            }
        }
        
        spawnTiles() {
            this._spawnCounter--;
            if (this._spawnCounter <= 0) {
                this._spawnCounter = this._spawnRate;
                
                // Random lane
                const lane = Math.floor(Math.random() * this._numLanes);
                const tile = new Sprite_NoteTile(lane, this._laneWidth, this._dreamTheme);
                this._tiles.push(tile);
                this._tilesContainer.addChild(tile);
            }
        }
        
        handleInput() {
            if (Input.isTriggered('left') && this._currentLane > 0) {
                this._currentLane--;
                this.updatePenPosition();
                AudioManager.playSe({name: 'Cursor1', volume: 50, pitch: 100, pan: 0});
            } else if (Input.isTriggered('right') && this._currentLane < this._numLanes - 1) {
                this._currentLane++;
                this.updatePenPosition();
                AudioManager.playSe({name: 'Cursor1', volume: 50, pitch: 100, pan: 0});
            }
        }
        
        hitTile(tile) {
            tile.hit = true;
            tile.setColorTone([100, 255, 100, 0]);
            
            this._score++;
            this._combo++;
            if (this._combo > this._maxCombo) {
                this._maxCombo = this._combo;
            }
            
            AudioManager.playSe({name: 'Decision2', volume: 70, pitch: 100 + this._combo * 2, pan: 0});
            
            this.updateScoreDisplay();
            this.updateComboDisplay();
            
            // Check win condition
            if (this._score >= this._targetScore) {
                this.dreamSuccess();
            }
            
            // Fade out tile
            const fadeOut = () => {
                tile.opacity -= 30;
                if (tile.opacity > 0) {
                    setTimeout(fadeOut, 16);
                }
            };
            fadeOut();
        }
        
        missTile(tile) {
            if (!tile.missed) {
                tile.missed = true;
                this._misses++;
                this._combo = 0;
                
                AudioManager.playSe({name: 'Buzzer1', volume: 60, pitch: 80, pan: 0});
                
                this.updateComboDisplay();
                this.updateMissesDisplay();
                
                if (this._misses >= this._maxMisses) {
                    this.dreamFailed();
                }
            }
        }
        
        updateScoreDisplay() {
            const bitmap = this._scoreSprite.bitmap;
            bitmap.clear();
            
            bitmap.fontSize = 32;
            bitmap.textColor = '#FFFFFF';
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.8)';
            bitmap.outlineWidth = 4;
            bitmap.drawText('Iskor', 0, 0, 300, 40, 'left');
            
            bitmap.fontSize = 48;
            bitmap.textColor = '#FFD700';
            bitmap.drawText(`${this._score}`, 0, 38, 300, 48, 'left');
        }
        
        updateTargetDisplay() {
            const bitmap = this._targetSprite.bitmap;
            bitmap.clear();
            
            bitmap.fontSize = 24;
            bitmap.textColor = '#FFFFFF';
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.8)';
            bitmap.outlineWidth = 4;
            bitmap.drawText('Target', 0, 0, 300, 35, 'right');
            
            bitmap.fontSize = 44;
            bitmap.textColor = '#FFD700';
            bitmap.drawText(`${this._targetScore}`, 0, 35, 300, 45, 'right');
        }
        
        updateMissesDisplay() {
            const bitmap = this._missesSprite.bitmap;
            bitmap.clear();
            
            bitmap.fontSize = 22;
            bitmap.textColor = this._misses >= 7 ? '#FF4444' : '#FFFFFF';
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.8)';
            bitmap.outlineWidth = 3;
            
            const missLabel = 'Maling Tap';
            bitmap.drawText(`${missLabel}: ${this._misses}/10`, 0, 0, 300, 30, 'center');
        }
        
        updateComboDisplay() {
            const bitmap = this._comboSprite.bitmap;
            bitmap.clear();
            
            if (this._combo > 0) {
                const scale = Math.min(this._combo * 0.05, 0.5);
                
                bitmap.fontSize = 36;
                bitmap.textColor = '#FFD700';
                bitmap.outlineColor = 'rgba(0, 0, 0, 0.8)';
                bitmap.outlineWidth = 4;
                bitmap.drawText(`${this._combo}x`, 0, 0, 250, 50, 'center');
                
                bitmap.fontSize = 24;
                bitmap.textColor = '#FFFF00';
                bitmap.drawText('KOMBO!', 0, 50, 250, 35, 'center');
                
                // Pulse effect
                this._comboSprite.scale.x = 1 + scale;
                this._comboSprite.scale.y = 1 + scale;
                setTimeout(() => {
                    this._comboSprite.scale.x = 1.0;
                    this._comboSprite.scale.y = 1.0;
                }, 100);
            }
        }
        
        dreamSuccess() {
            this._gameActive = false;
            this._gameOver = true;
            
            $gameSwitches.setValue(this._successSwitch, true);
            $gameSwitches.setValue(this._failureSwitch, false);
            
            AudioManager.playSe({name: 'Saint5', volume: 90, pitch: 100, pan: 0});
            
            const message = `Iskor: ${this._score}\nMax Kombo: ${this._maxCombo}x`;
            this.showResult('TAGUMPAY!', '#FFD700', message, 'Pindutin ang OK');
        }
        
        dreamFailed() {
            this._gameActive = false;
            this._gameOver = true;
            
            $gameSwitches.setValue(this._successSwitch, false);
            $gameSwitches.setValue(this._failureSwitch, true);
            
            AudioManager.playSe({name: 'Devil1', volume: 90, pitch: 100, pan: 0});
            
            const message = `Iskor: ${this._score}\nSadyang maraming maling tap...`;
            this.showResult('TAPOS NA!', '#FF4444', message, 'Pindutin ang OK');
        }
        
        showResult(title, color, message, instruction) {
            const resultSprite = new Sprite();
            resultSprite.bitmap = new Bitmap(500, 300);
            resultSprite.x = Graphics.width / 2 - 250;
            resultSprite.y = Graphics.height / 2 - 150;
            
            const bitmap = resultSprite.bitmap;
            const ctx = bitmap.context;
            
            ctx.fillStyle = 'rgba(0, 0, 0, 0.9)';
            ctx.fillRect(0, 0, 500, 300);
            ctx.shadowColor = color;
            ctx.shadowBlur = 15;
            ctx.strokeStyle = color;
            ctx.lineWidth = 4;
            ctx.strokeRect(4, 4, 492, 292);
            
            bitmap.fontSize = 40;
            bitmap.textColor = color;
            bitmap.outlineColor = 'rgba(0, 0, 0, 0.8)';
            bitmap.outlineWidth = 4;
            bitmap.drawText(title, 0, 40, 500, 50, 'center');
            
            bitmap.fontSize = 24;
            bitmap.textColor = '#FFFFFF';
            const lines = message.split('\n');
            lines.forEach((line, index) => {
                bitmap.drawText(line, 0, 120 + index * 35, 500, 40, 'center');
            });
            
            bitmap.fontSize = 20;
            bitmap.textColor = '#AAAAAA';
            bitmap.drawText(instruction, 0, 230, 500, 40, 'center');
            
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
    // Sprite_FeatherPen
    //=============================================================================
    
    class Sprite_FeatherPen extends Sprite {
        initialize(theme) {
            super.initialize();
            this._theme = theme;
            this.anchor.x = 0.5;
            this.anchor.y = 0.5;
            this.createPenBitmap();
            
            // Gentle bobbing animation
            this._bobCounter = 0;
            this.targetX = this.x;
        }
        
        createPenBitmap() {
            const size = 100;
            this.bitmap = new Bitmap(size, size);
            const ctx = this.bitmap.context;
            
            ctx.save();
            ctx.translate(size / 2, size / 2);
            ctx.rotate(-Math.PI / 4);
            
            // Feather colors based on theme
            const colors = {
                sunset: { quill: '#8B4513', feather: '#FFD700', tip: '#FF6B35' },
                ocean: { quill: '#1A3A52', feather: '#6B8FD4', tip: '#4B63B8' },
                forest: { quill: '#1B4332', feather: '#52B788', tip: '#40916C' }
            };
            
            const color = colors[this._theme] || colors.sunset;
            
            // Draw feather main shape
            const featherGradient = ctx.createLinearGradient(-20, -30, 20, 30);
            featherGradient.addColorStop(0, color.feather);
            featherGradient.addColorStop(0.5, color.feather);
            featherGradient.addColorStop(1, color.quill);
            
            ctx.fillStyle = featherGradient;
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.6)';
            ctx.lineWidth = 2;
            
            // Feather shape
            ctx.beginPath();
            ctx.moveTo(0, -35);
            ctx.quadraticCurveTo(15, -20, 14, 0);
            ctx.quadraticCurveTo(12, 20, 8, 35);
            ctx.lineTo(2, 38);
            ctx.lineTo(-2, 38);
            ctx.quadraticCurveTo(-12, 20, -14, 0);
            ctx.quadraticCurveTo(-15, -20, 0, -35);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
            
            // Quill details - feather texture
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.25)';
            ctx.lineWidth = 1;
            for (let i = -30; i < 35; i += 5) {
                ctx.beginPath();
                ctx.moveTo(0, i);
                ctx.lineTo(8, i + 2);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(0, i);
                ctx.lineTo(-8, i + 2);
                ctx.stroke();
            }
            
            // Center vein
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.4)';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(0, -35);
            ctx.lineTo(0, 38);
            ctx.stroke();
            
            // Nib (writing tip) - shiny metallic look
            ctx.fillStyle = color.tip;
            ctx.beginPath();
            ctx.moveTo(0, 38);
            ctx.lineTo(3, 44);
            ctx.lineTo(-3, 44);
            ctx.closePath();
            ctx.fill();
            
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.5)';
            ctx.lineWidth = 1;
            ctx.stroke();
            
            // Nib shine
            ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
            ctx.beginPath();
            ctx.moveTo(-1, 39);
            ctx.lineTo(1, 39);
            ctx.lineTo(0, 42);
            ctx.closePath();
            ctx.fill();
            
            ctx.restore();
        }
        
        update() {
            super.update();
            
            // Smooth movement to target lane
            if (this.targetX !== undefined) {
                const diff = this.targetX - this.x;
                this.x += diff * 0.25;
            }
            
            // Gentle bobbing
            this._bobCounter += 0.08;
            const baseY = this.y;
            this.y = baseY + Math.sin(this._bobCounter) * 4;
        }
    }

    //=============================================================================
    // Sprite_NoteTile
    //=============================================================================
    
    class Sprite_NoteTile extends Sprite {
        initialize(lane, laneWidth, theme) {
            super.initialize();
            this.lane = lane;
            this.hit = false;
            this.missed = false;
            this._theme = theme;
            
            this.x = lane * laneWidth + laneWidth / 2;
            this.y = -40;
            
            this.anchor.x = 0.5;
            this.anchor.y = 0.5;
            
            this.createTileBitmap(laneWidth);
        }
        
        createTileBitmap(laneWidth) {
            const width = laneWidth - 8;
            const height = 60;
            
            this.bitmap = new Bitmap(width, height);
            const ctx = this.bitmap.context;
            
            // Theme-based tile colors - vibrant music game style
            const colors = {
                sunset: { fill: '#FF6B35', glow: 'rgba(255, 107, 53, 0.6)', accent: '#FDB833' },
                ocean: { fill: '#4B63B8', glow: 'rgba(75, 99, 184, 0.6)', accent: '#6B8FD4' },
                forest: { fill: '#40916C', glow: 'rgba(64, 145, 108, 0.6)', accent: '#52B788' }
            };
            
            const color = colors[this._theme] || colors.sunset;
            
            // Outer glow
            ctx.fillStyle = color.glow;
            ctx.fillRect(2, 2, width - 4, height - 4);
            
            // Main tile
            ctx.fillStyle = color.fill;
            ctx.fillRect(4, 4, width - 8, height - 8);
            
            // Highlight on top
            ctx.fillStyle = color.accent;
            ctx.fillRect(6, 6, width - 12, 8);
            
            // Glossy effect
            ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.fillRect(8, 8, width - 16, 6);
            
            // Bottom shadow
            ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
            ctx.fillRect(4, height - 8, width - 8, 4);
            
            // Center circle icon
            ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
            ctx.beginPath();
            ctx.arc(width / 2, height / 2, 12, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.fillStyle = color.fill;
            ctx.beginPath();
            ctx.arc(width / 2, height / 2, 10, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    window.Scene_DreamPianoTiles = Scene_DreamPianoTiles;
})();