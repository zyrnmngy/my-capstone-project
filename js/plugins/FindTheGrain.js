//=============================================================================
// FindTheGrain.js
//=============================================================================

/*:
 * @target MZ
 * @plugindesc Find the grain under rocks mini-game with cartoonish design. Tagalog UI.
 * @author Your Name
 *
 * @help FindTheGrain.js
 *
 * This plugin creates an interactive mini-game where players must find a grain
 * hidden under rocks. Each round the rocks shuffle randomly over 3 rounds total.
 *
 * Plugin Commands:
 * - Start the grain finding game
 * 
 * @command startFindGrain
 * @text Start Find the Grain
 * @desc Starts the find the grain mini-game
 *
 * @arg successSwitch
 * @text Success Switch
 * @type switch
 * @default 1
 * @desc Switch to turn ON when player finds grain
 * 
 * @arg bgmName
 * @text Cemetery BGM
 * @type file
 * @dir audio/bgm
 * @default Town2
 * @desc Atmospheric cemetery background music
 *
 * @arg failureSwitch
 * @text Failure Switch
 * @type switch
 * @default 2
 * @desc Switch to turn ON when player picks wrong rock
 *
 */

(() => {
    'use strict';
    
    const pluginName = 'FindTheGrain';
    
    PluginManager.registerCommand(pluginName, 'startFindGrain', args => {
        const successSwitch = Number(args.successSwitch) || 1;
        const failureSwitch = Number(args.failureSwitch) || 2;
        const bgmName = args.bgmName || 'Town2';
        
        SceneManager.push(Scene_FindTheGrain);
        SceneManager.prepareNextScene(successSwitch, failureSwitch);
    });

    //=============================================================================
    // Scene_FindTheGrain
    //=============================================================================
    
    class Scene_FindTheGrain extends Scene_Base {
        initialize() {
            super.initialize();
            this._successSwitch = 1;
            this._failureSwitch = 2;
            this._currentRound = 0;
            this._totalRounds = 3;
            this._score = 0;
            this._bgmName = 'Town2';
        }
        
        prepare(successSwitch, failureSwitch) {
            this._successSwitch = successSwitch;
            this._failureSwitch = failureSwitch;
        }
        
        create() {
            super.create();
            this.playFindGrainBGM();
            this.createBackground();
            this.createDecorations();
            this.createRockContainer();
            this.createUI();
            this.startGame();
        }

        playFindGrainBGM() {
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
            
            // Gradient sky - bright and cartoonish
            const skyGrad = ctx.createLinearGradient(0, 0, 0, Graphics.height * 0.6);
            skyGrad.addColorStop(0, '#87CEEB');
            skyGrad.addColorStop(1, '#E0F6FF');
            ctx.fillStyle = skyGrad;
            ctx.fillRect(0, 0, Graphics.width, Graphics.height * 0.6);
            
            // Ground gradient - earthy brown
            const groundGrad = ctx.createLinearGradient(0, Graphics.height * 0.6, 0, Graphics.height);
            groundGrad.addColorStop(0, '#8B7355');
            groundGrad.addColorStop(1, '#6B5344');
            ctx.fillStyle = groundGrad;
            ctx.fillRect(0, Graphics.height * 0.6, Graphics.width, Graphics.height * 0.4);
            
            // Add some clouds
            ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
            for (let i = 0; i < 3; i++) {
                const x = 100 + i * 300;
                const y = 80 + Math.random() * 50;
                this.drawCloud(ctx, x, y, 50);
            }
            
            // Add sun
            ctx.fillStyle = '#FFD700';
            ctx.beginPath();
            ctx.arc(Graphics.width - 100, 100, 50, 0, Math.PI * 2);
            ctx.fill();
            
            this.addChild(this._backgroundSprite);
        }
        
        drawCloud(ctx, x, y, size) {
            ctx.beginPath();
            ctx.arc(x, y, size, 0, Math.PI * 2);
            ctx.arc(x + size * 0.6, y - size * 0.2, size * 0.8, 0, Math.PI * 2);
            ctx.arc(x - size * 0.6, y - size * 0.2, size * 0.8, 0, Math.PI * 2);
            ctx.fill();
        }
        
        createDecorations() {
            // Decorative grass
            this._grassSprite = new Sprite();
            this._grassSprite.bitmap = new Bitmap(Graphics.width, 100);
            this._grassSprite.y = Graphics.height * 0.55;
            
            const ctx = this._grassSprite.bitmap.context;
            ctx.fillStyle = '#5a8a3a';
            
            for (let i = 0; i < Graphics.width; i += 15) {
                ctx.fillRect(i, 80, 8, 20);
                ctx.fillRect(i + 8, 75, 8, 25);
            }
            
            this.addChild(this._grassSprite);
        }
        
        createRockContainer() {
            this._rocks = [];
            this._rockContainer = new Sprite();
            this.addChild(this._rockContainer);
        }
        
        setupRound() {
            // Clear previous rocks
            this._rockContainer.removeChildren();
            this._rocks = [];
            
            const rockCount = 5;
            const grainIndex = Math.floor(Math.random() * rockCount);
            
            const startX = (Graphics.width - (rockCount * 120 + (rockCount - 1) * 20)) / 2;
            const startY = Graphics.height / 2 + 40;
            
            for (let i = 0; i < rockCount; i++) {
                const x = startX + i * 140;
                const y = startY;
                
                const rock = new Sprite_Rock();
                rock.setup(x, y, 100, i === grainIndex);
                this._rocks.push(rock);
                this._rockContainer.addChild(rock);
            }
            
            // Show the grain first
            this._rocks[grainIndex].showGrainPreview();
            this._showMessage('Alalahanin kung saan ang butil!');
            this._messageColor = '#FFFF00';
            
            // Start shuffling after showing the grain for 2 seconds
            this._previewWait = 120;
            this._shuffleWait = 0;
            this._shuffling = false;
            this._showingPreview = true;
            this._shuffleCount = 0;
            this._maxShuffles = 8 + this._currentRound * 2;
            this._attemptsLeft = 2; // 2 chances to find the grain
        }
        
        performShuffle() {
            // Pick two random rocks to swap
            const idx1 = Math.floor(Math.random() * this._rocks.length);
            let idx2 = Math.floor(Math.random() * this._rocks.length);
            while (idx2 === idx1) {
                idx2 = Math.floor(Math.random() * this._rocks.length);
            }
            
            const rock1 = this._rocks[idx1];
            const rock2 = this._rocks[idx2];
            
            // Swap their target positions
            const tempX = rock1._targetX;
            const tempY = rock1._targetY;
            
            rock1._targetX = rock2._targetX;
            rock1._targetY = rock2._targetY;
            rock2._targetX = tempX;
            rock2._targetY = tempY;
            
            // Swap in array
            this._rocks[idx1] = rock2;
            this._rocks[idx2] = rock1;
            
            this._shuffleCount++;
            
            if (this._shuffleCount >= this._maxShuffles) {
                this._shuffling = false;
                this._gameActive = true;
                this._showMessage('Piliin ang bato na may butil!');
                this._messageColor = '#FFFFFF';
            } else {
                this._shuffleWait = 20;
            }
        }
        
        createUI() {
            // Round display - top
            this._roundDisplay = new Sprite();
            this._roundDisplay.bitmap = new Bitmap(300, 100);
            this._roundDisplay.x = 50;
            this._roundDisplay.y = 30;
            this.addChild(this._roundDisplay);
            
            // Score display - top right
            this._scoreDisplay = new Sprite();
            this._scoreDisplay.bitmap = new Bitmap(300, 100);
            this._scoreDisplay.x = Graphics.width - 350;
            this._scoreDisplay.y = 30;
            this.addChild(this._scoreDisplay);
            
            // Message display - center
            this._messageDisplay = new Sprite();
            this._messageDisplay.bitmap = new Bitmap(400, 80);
            this._messageDisplay.x = Graphics.width / 2 - 200;
            this._messageDisplay.y = 150;
            this.addChild(this._messageDisplay);
        }
        
        startGame() {
            this._currentRound = 1;
            this._score = 0;
            this._gameActive = false;
            this._gameOver = false;
            this._waitCount = 0;
            this._shuffling = false;
            this._showingPreview = false;
            this._shouldContinue = false;
            this._showMessage('');
            this.updateRoundDisplay();
            this.setupRound();
        }
        
        update() {
            super.update();
            
            // Update rock positions for shuffle animation
            for (const rock of this._rocks) {
                rock.updatePosition();
            }
            
            // Handle preview phase (showing grain before shuffle)
            if (this._showingPreview) {
                if (this._previewWait > 0) {
                    this._previewWait--;
                } else {
                    // Hide grain and start shuffling
                    for (const rock of this._rocks) {
                        rock.hideGrainPreview();
                    }
                    this._showingPreview = false;
                    this._shuffling = true;
                    this._shuffleWait = 30;
                    this._showMessage('Panoorin mabuti!');
                    this._messageColor = '#FFFFFF';
                }
            }
            // Handle shuffling
            else if (this._shuffling) {
                if (this._shuffleWait > 0) {
                    this._shuffleWait--;
                } else {
                    this.performShuffle();
                }
            } else if (this._gameActive && !this._gameOver) {
                this.handleInput();
            }
            
            if (this._waitCount > 0) {
                this._waitCount--;
                if (this._waitCount <= 0 && !this._gameOver) {
                    if (this._shouldContinue) {
                        this._gameActive = true;
                        this._shouldContinue = false;
                        this._showMessage(`${this._attemptsLeft} pagkakataon pa!`);
                        this._messageColor = '#FFAA00';
                    } else {
                        this.nextRound();
                    }
                }
            }
            
            if (this._gameOver && Input.isTriggered('ok')) {
                this.returnToMap();
            }
            
            // Update score display
            const scoreBitmap = this._scoreDisplay.bitmap;
            scoreBitmap.clear();
            scoreBitmap.fontSize = 24;
            scoreBitmap.textColor = '#00DD00';
            scoreBitmap.outlineColor = '#000000';
            scoreBitmap.outlineWidth = 2;
            scoreBitmap.drawText(`Puntos: ${this._score}`, 0, 30, 300, 40, 'right');
            
            // Show attempts left during gameplay
            if (this._gameActive && !this._gameOver && this._attemptsLeft > 0) {
                scoreBitmap.fontSize = 20;
                scoreBitmap.textColor = '#FFAA00';
                scoreBitmap.drawText(`Pagkakataon: ${this._attemptsLeft}`, 0, 60, 300, 40, 'right');
            }
        }
        
        handleInput() {
            if (TouchInput.isTriggered()) {
                const x = TouchInput.x;
                const y = TouchInput.y;
                
                for (const rock of this._rocks) {
                    if (rock.isClicked(x, y) && !rock._revealed) {
                        rock.reveal();
                        this.onRockClicked(rock._hasGrain);
                        break;
                    }
                }
            }
        }
        
        onRockClicked(hasGrain) {
            if (hasGrain) {
                this._score++;
                this._showMessage('Tumpak! Nakita mo ang butil!');
                this._messageColor = '#00AA00';
                AudioManager.playSe({name: 'Cursor1', volume: 90, pitch: 150, pan: 0});
                this._gameActive = false;
                this._waitCount = 60;
                this._shouldContinue = false;
            } else {
                this._attemptsLeft--;
                
                if (this._attemptsLeft > 0) {
                    // Still have attempts left
                    this._showMessage(`Mali! Subukan ulit! ${this._attemptsLeft} pagkakataon pa!`);
                    this._messageColor = '#FFAA00';
                    AudioManager.playSe({name: 'Buzzer1', volume: 90, pitch: 100, pan: 0});
                    this._gameActive = false;
                    this._waitCount = 60;
                    this._shouldContinue = true;
                } else {
                    // No more attempts, reveal the grain
                    this._showMessage('Walang pagkakataon pa! Ito ang butil!');
                    this._messageColor = '#AA0000';
                    AudioManager.playSe({name: 'Buzzer1', volume: 90, pitch: 80, pan: 0});
                    
                    // Reveal the grain location
                    for (const rock of this._rocks) {
                        if (rock._hasGrain && !rock._revealed) {
                            rock.reveal();
                        }
                    }
                    
                    this._gameActive = false;
                    this._waitCount = 90;
                    this._shouldContinue = false;
                }
            }
        }
        
        nextRound() {
            this._currentRound++;
            
            if (this._currentRound > this._totalRounds) {
                this.gameEnd();
            } else {
                this._showMessage('');
                this._gameActive = false;
                this.setupRound();
                this.updateRoundDisplay();
            }
        }
        
        gameEnd() {
            this._gameActive = false;
            this._gameOver = true;
            
            if (this._score >= 2) {
                $gameSwitches.setValue(this._successSwitch, true);
                $gameSwitches.setValue(this._failureSwitch, false);
                AudioManager.playSe({name: 'Saint5', volume: 90, pitch: 100, pan: 0});
                this.showResult('TAGUMPAY!', '#FFD700', `Nakuha mo: ${this._score}/${this._totalRounds} butil!`);
            } else {
                $gameSwitches.setValue(this._successSwitch, false);
                $gameSwitches.setValue(this._failureSwitch, true);
                AudioManager.playSe({name: 'Devil1', volume: 90, pitch: 100, pan: 0});
                this.showResult('MABIGAT!', '#ff4444', `Nakuha mo lang: ${this._score}/${this._totalRounds} butil. Subukan ulit!`);
            }
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
        
        _showMessage(text) {
            const bitmap = this._messageDisplay.bitmap;
            bitmap.clear();
            
            if (text) {
                bitmap.fontSize = 28;
                bitmap.textColor = this._messageColor || '#FFFFFF';
                bitmap.outlineColor = '#000000';
                bitmap.outlineWidth = 3;
                bitmap.drawText(text, 0, 20, 400, 40, 'center');
            }
        }
        
        updateRoundDisplay() {
            const bitmap = this._roundDisplay.bitmap;
            bitmap.clear();
            
            bitmap.fontSize = 24;
            bitmap.textColor = '#FFD700';
            bitmap.outlineColor = '#000000';
            bitmap.outlineWidth = 2;
            bitmap.drawText(`Rodada: ${this._currentRound}/${this._totalRounds}`, 0, 30, 300, 40, 'left');
        }
        
        returnToMap() {
            SceneManager.pop();
        }
    }

    //=============================================================================
    // Sprite_Rock
    //=============================================================================
    
    class Sprite_Rock extends Sprite {
        initialize() {
            super.initialize();
        }
        
        setup(x, y, size, hasGrain) {
            this.x = x;
            this.y = y;
            this._targetX = x;
            this._targetY = y;
            this._size = size;
            this._radius = size / 2;
            this._hasGrain = hasGrain;
            this._revealed = false;
            this.anchor.x = 0.5;
            this.anchor.y = 0.5;
            
            this.createBitmap();
        }
        
        createBitmap() {
            this.bitmap = new Bitmap(this._size * 1.2, this._size * 1.2);
            this.drawRock();
        }
        
        drawRock() {
            const ctx = this.bitmap.context;
            const centerX = this._size * 0.6;
            const centerY = this._size * 0.6;
            const radius = this._radius;
            
            // Validate values before creating gradient
            if (!isFinite(centerX) || !isFinite(centerY) || !isFinite(radius) || radius <= 0) {
                console.error('Invalid rock dimensions:', centerX, centerY, radius);
                return;
            }
            
            // Main rock body - brown with gradient
            const rockGrad = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, radius);
            rockGrad.addColorStop(0, '#A0826D');
            rockGrad.addColorStop(0.5, '#8B7355');
            rockGrad.addColorStop(1, '#6B5344');
            
            ctx.fillStyle = rockGrad;
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.fill();
            
            // Rock highlight
            ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.beginPath();
            ctx.arc(centerX - 15, centerY - 15, 20, 0, Math.PI * 2);
            ctx.fill();
            
            // Rock shadow
            ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
            ctx.beginPath();
            ctx.arc(centerX + 15, centerY + 15, 18, 0, Math.PI * 2);
            ctx.fill();
            
            // Rock texture
            ctx.fillStyle = 'rgba(0, 0, 0, 0.2)';
            for (let i = 0; i < 20; i++) {
                const angle = Math.random() * Math.PI * 2;
                const distance = Math.random() * radius * 0.7;
                const dx = Math.cos(angle) * distance;
                const dy = Math.sin(angle) * distance;
                const size = Math.random() * 3 + 1;
                ctx.fillRect(centerX + dx - size/2, centerY + dy - size/2, size, size);
            }
            
            // Rock border
            ctx.strokeStyle = '#4B3320';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.stroke();
        }
        
        updatePosition() {
            const speed = 0.15;
            const dx = this._targetX - this.x;
            const dy = this._targetY - this.y;
            
            if (Math.abs(dx) > 1 || Math.abs(dy) > 1) {
                this.x += dx * speed;
                this.y += dy * speed;
            } else {
                this.x = this._targetX;
                this.y = this._targetY;
            }
        }
        
        showGrainPreview() {
            if (!this._hasGrain) return;
            
            this._previewSprite = new Sprite();
            this._previewSprite.bitmap = new Bitmap(60, 60);
            this._previewSprite.anchor.x = 0.5;
            this._previewSprite.anchor.y = 0.5;
            this._previewSprite.x = 0;
            this._previewSprite.y = -this._radius - 40;
            
            const ctx = this._previewSprite.bitmap.context;
            const centerX = 30;
            const centerY = 30;
            
            // Draw small grain preview
            const grainGrad = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, 20);
            grainGrad.addColorStop(0, '#FFFF99');
            grainGrad.addColorStop(0.5, '#FFD700');
            grainGrad.addColorStop(1, '#DAA520');
            
            ctx.fillStyle = grainGrad;
            ctx.beginPath();
            ctx.arc(centerX, centerY, 20, 0, Math.PI * 2);
            ctx.fill();
            
            // Grain shine
            ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
            ctx.beginPath();
            ctx.arc(centerX - 8, centerY - 8, 6, 0, Math.PI * 2);
            ctx.fill();
            
            // Arrow pointing down
            ctx.fillStyle = '#FFD700';
            ctx.beginPath();
            ctx.moveTo(centerX, 50);
            ctx.lineTo(centerX - 8, 45);
            ctx.lineTo(centerX + 8, 45);
            ctx.closePath();
            ctx.fill();
            
            this.addChild(this._previewSprite);
        }
        
        hideGrainPreview() {
            if (this._previewSprite) {
                this.removeChild(this._previewSprite);
                this._previewSprite = null;
            }
        }
        
        isClicked(x, y) {
            const dx = x - this.x;
            const dy = y - this.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            return distance <= this._radius;
        }
        
        reveal() {
            this._revealed = true;
            this.bitmap.clear();
            
            const ctx = this.bitmap.context;
            const centerX = this._size * 0.6;
            const centerY = this._size * 0.6;
            
            if (this._hasGrain) {
                // Draw grain
                const grainGrad = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, 25);
                grainGrad.addColorStop(0, '#FFFF99');
                grainGrad.addColorStop(0.5, '#FFD700');
                grainGrad.addColorStop(1, '#DAA520');
                
                ctx.fillStyle = grainGrad;
                ctx.beginPath();
                ctx.arc(centerX, centerY, 25, 0, Math.PI * 2);
                ctx.fill();
                
                // Grain shine
                ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
                ctx.beginPath();
                ctx.arc(centerX - 10, centerY - 10, 8, 0, Math.PI * 2);
                ctx.fill();
                
                // Grain border
                ctx.strokeStyle = '#DAA520';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.arc(centerX, centerY, 25, 0, Math.PI * 2);
                ctx.stroke();
            } else {
                // Empty rock
                ctx.fillStyle = '#D3D3D3';
                ctx.beginPath();
                ctx.arc(centerX, centerY, this._radius, 0, Math.PI * 2);
                ctx.fill();
                
                ctx.strokeStyle = '#A9A9A9';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.arc(centerX, centerY, this._radius, 0, Math.PI * 2);
                ctx.stroke();
            }
        }
    }

    window.Scene_FindTheGrain = Scene_FindTheGrain;
})();