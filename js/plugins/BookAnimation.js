//=============================================================================
// BookAnimation.js
//=============================================================================

/*:
 * @target MZ
 * @plugindesc Creates a magical book opening animation with self-writing text. Tagalog UI.
 * @author Your Name
 *
 * @help BookAnimation.js
 *
 * This plugin creates a stunning book opening animation where text writes itself out.
 * It automatically plays when the player transfers to a specific map.
 *
 * Plugin Parameters:
 * - Configure which map triggers the animation and what text to display
 * 
 * @param autoPlayMapId
 * @text Auto-play Map ID
 * @type number
 * @default 0
 * @desc Map ID where animation plays automatically (0 = disabled)
 *
 * @param autoPlayText
 * @text Auto-play Text
 * @type string
 * @default Ang mga nobela ay maaaring natapos na ... ngunit ang kanilang katotohanan ay nagpapatuloy sa pamamagitan mo
 * @desc Text to display in auto-play animation
 *
 * @param autoPlaySpeed
 * @text Auto-play Speed
 * @type number
 * @default 2
 * @desc Characters per frame for auto-play (higher = faster)
 *
 * @param autoPlaySwitch
 * @text Auto-play Completion Switch
 * @type switch
 * @default 1
 * @desc Switch to turn ON when auto-play animation completes
 *
 * @param onlyPlayOnce
 * @text Play Only Once
 * @type boolean
 * @default true
 * @desc If true, animation only plays once per save file
 *
 * @command showBook
 * @text Show Book Animation
 * @desc Manually show book opening animation with text
 *
 * @arg text
 * @text Display Text
 * @type string
 * @default Ang mga nobela ay maaaring natapos na ... ngunit ang kanilang katotohanan ay nagpapatuloy sa pamamagitan mo
 * @desc Text to display in the book animation
 *
 * @arg speed
 * @text Writing Speed
 * @type number
 * @default 2
 * @desc Characters per frame (higher = faster)
 *
 * @arg switchId
 * @text Completion Switch
 * @type switch
 * @default 1
 * @desc Switch to turn ON when animation completes
 */

(() => {
    'use strict';
    
    const pluginName = 'BookAnimation';
    const parameters = PluginManager.parameters(pluginName);
    
    const Config = {
        autoPlayMapId: Number(parameters.autoPlayMapId || 0),
        autoPlayText: String(parameters.autoPlayText || "Ang mga nobela ay maaaring natapos na ... ngunit ang kanilang katotohanan ay nagpapatuloy sa pamamagitan mo"),
        autoPlaySpeed: Number(parameters.autoPlaySpeed || 2),
        autoPlaySwitch: Number(parameters.autoPlaySwitch || 1),
        onlyPlayOnce: parameters.onlyPlayOnce === "true"
    };
    
    // Store if animation has been played
    const _DataManager_createGameObjects = DataManager.createGameObjects;
    DataManager.createGameObjects = function() {
        _DataManager_createGameObjects.call(this);
        $gameSystem._bookAnimationPlayed = $gameSystem._bookAnimationPlayed || false;
    };
    
    const _DataManager_extractSaveContents = DataManager.extractSaveContents;
    DataManager.extractSaveContents = function(contents) {
        _DataManager_extractSaveContents.call(this, contents);
        $gameSystem._bookAnimationPlayed = $gameSystem._bookAnimationPlayed || false;
    };
    
    PluginManager.registerCommand(pluginName, 'showBook', args => {
        const text = String(args.text || Config.autoPlayText);
        const speed = Number(args.speed) || Config.autoPlaySpeed;
        const switchId = Number(args.switchId) || Config.autoPlaySwitch;
        
        SceneManager.push(Scene_BookAnimation);
        SceneManager.prepareNextScene(text, speed, switchId, false);
    });

    //=============================================================================
    // Scene_Map - Auto-play functionality
    //=============================================================================
    
    const _Scene_Map_start = Scene_Map.prototype.start;
    Scene_Map.prototype.start = function() {
        _Scene_Map_start.call(this);
        this.checkAutoPlayBookAnimation();
    };
    
    Scene_Map.prototype.checkAutoPlayBookAnimation = function() {
        if (Config.autoPlayMapId > 0 && $gameMap.mapId() === Config.autoPlayMapId) {
            if (Config.onlyPlayOnce && $gameSystem._bookAnimationPlayed) {
                return;
            }
            this._autoPlayWait = 30;
        }
    };
    
    const _Scene_Map_update = Scene_Map.prototype.update;
    Scene_Map.prototype.update = function() {
        _Scene_Map_update.call(this);
        this.updateAutoPlayBookAnimation();
    };
    
    Scene_Map.prototype.updateAutoPlayBookAnimation = function() {
        if (this._autoPlayWait > 0) {
            this._autoPlayWait--;
            if (this._autoPlayWait === 0) {
                this.startAutoPlayBookAnimation();
            }
        }
    };
    
    Scene_Map.prototype.startAutoPlayBookAnimation = function() {
        if (Config.onlyPlayOnce) {
            $gameSystem._bookAnimationPlayed = true;
        }
        
        SceneManager.push(Scene_BookAnimation);
        SceneManager.prepareNextScene(Config.autoPlayText, Config.autoPlaySpeed, Config.autoPlaySwitch, true);
    };

    //=============================================================================
    // Scene_BookAnimation
    //=============================================================================
    
    class Scene_BookAnimation extends Scene_Base {
        initialize() {
            super.initialize();
            this._displayText = "";
            this._fullText = "";
            this._writingSpeed = 2;
            this._completionSwitch = 1;
            this._charIndex = 0;
            this._bookOpenProgress = 0;
            this._animationPhase = "opening";
            this._isAutoPlay = false;
            this._particles = [];
        }
        
        prepare(text, speed, switchId, isAutoPlay = false) {
            this._fullText = text;
            this._writingSpeed = speed;
            this._completionSwitch = switchId;
            this._isAutoPlay = isAutoPlay;
        }
        
        create() {
            super.create();
            this.createBackground();
            this.createParticles();
            this.createBook();
            this.createInstructions();
            this.startAnimation();
        }
        
        createBackground() {
            this._backgroundSprite = new Sprite();
            this._backgroundSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
            
            const bitmap = this._backgroundSprite.bitmap;
            const ctx = bitmap.context;
            
            // Dark atmospheric gradient
            const bgGrad = ctx.createRadialGradient(Graphics.width / 2, Graphics.height / 2, 0, Graphics.width / 2, Graphics.height / 2, Graphics.width);
            bgGrad.addColorStop(0, '#2a2a4e');
            bgGrad.addColorStop(0.5, '#1a1a2e');
            bgGrad.addColorStop(1, '#0f0f1e');
            ctx.fillStyle = bgGrad;
            ctx.fillRect(0, 0, Graphics.width, Graphics.height);
            
            this.addChild(this._backgroundSprite);
        }
        
        createParticles() {
            this._particleLayer = new Sprite();
            this._particleLayer.bitmap = new Bitmap(Graphics.width, Graphics.height);
            this.addChild(this._particleLayer);

            this._butterflies = [];
        }
        
        createBook() {
            this._bookContainer = new Sprite();
            this._bookContainer.x = Graphics.width / 2;
            this._bookContainer.y = Graphics.height / 2;
            this._bookContainer.anchor.x = 0.5;
            this._bookContainer.anchor.y = 0.5;
            
            this._bookWidth = 520;
            this._bookHeight = 380;
            const pageWidth = this._bookWidth / 2 - 20;
            const pageHeight = this._bookHeight - 60;
            
            // Book spine (center divider)
            this._spine = new Sprite();
            this._spine.bitmap = new Bitmap(30, this._bookHeight);
            this.drawSpine(this._spine.bitmap);
            this._spine.x = 0;
            this._spine.y = 0;
            this._spine.anchor.x = 0.5;
            this._spine.anchor.y = 0.5;
            this._bookContainer.addChild(this._spine);
            
            // Left cover
            this._coverLeft = new Sprite();
            this._coverLeft.bitmap = new Bitmap(pageWidth + 10, this._bookHeight);
            this.drawBookCover(this._coverLeft.bitmap, true);
            this._coverLeft.x = -(pageWidth + 10) / 2 - 15;
            this._coverLeft.y = 0;
            this._coverLeft.anchor.x = 0.5;
            this._coverLeft.anchor.y = 0.5;
            this._bookContainer.addChild(this._coverLeft);
            
            // Right cover
            this._coverRight = new Sprite();
            this._coverRight.bitmap = new Bitmap(pageWidth + 10, this._bookHeight);
            this.drawBookCover(this._coverRight.bitmap, false);
            this._coverRight.x = (pageWidth + 10) / 2 + 15;
            this._coverRight.y = 0;
            this._coverRight.anchor.x = 0.5;
            this._coverRight.anchor.y = 0.5;
            this._bookContainer.addChild(this._coverRight);
            
            // Left page
            this._pageLeft = new Sprite();
            this._pageLeft.bitmap = new Bitmap(pageWidth, pageHeight);
            this.drawPage(this._pageLeft.bitmap);
            this._pageLeft.x = -pageWidth / 2 - 15;
            this._pageLeft.y = 0;
            this._pageLeft.anchor.x = 0.5;
            this._pageLeft.anchor.y = 0.5;
            this._pageLeft.opacity = 0;
            this._bookContainer.addChild(this._pageLeft);
            
            // Right page
            this._pageRight = new Sprite();
            this._pageRight.bitmap = new Bitmap(pageWidth, pageHeight);
            this.drawPage(this._pageRight.bitmap);
            this._pageRight.x = pageWidth / 2 + 15;
            this._pageRight.y = 0;
            this._pageRight.anchor.x = 0.5;
            this._pageRight.anchor.y = 0.5;
            this._pageRight.opacity = 0;
            this._bookContainer.addChild(this._pageRight);
            
            // Text display on right page
            this._textSprite = new Sprite();
            this._textSprite.bitmap = new Bitmap(pageWidth - 30, pageHeight - 30);
            this._textSprite.x = pageWidth / 2 + 15;
            this._textSprite.y = 0;
            this._textSprite.anchor.x = 0.5;
            this._textSprite.anchor.y = 0.5;
            this._textSprite.bitmap.fontSize = 18;
            this._textSprite.bitmap.textColor = '#3a3a3a';
            this._textSprite.bitmap.outlineColor = 'rgba(255, 255, 255, 0)';
            this._textSprite.bitmap.outlineWidth = 0;
            this._textSprite.opacity = 0;
            this._bookContainer.addChild(this._textSprite);
            
            // Inner glow effect
            this._glowSprite = new Sprite();
            this._glowSprite.bitmap = new Bitmap(this._bookWidth + 100, this._bookHeight + 100);
            this.drawGlow(this._glowSprite.bitmap);
            this._glowSprite.x = 0;
            this._glowSprite.y = 0;
            this._glowSprite.anchor.x = 0.5;
            this._glowSprite.anchor.y = 0.5;
            this._glowSprite.opacity = 0;
            this._bookContainer.addChild(this._glowSprite);
            
            this._bookContainer.scale.x = 0;
            this._bookContainer.scale.y = 0;
            this.addChild(this._bookContainer);
        }
        
        drawBookCover(bitmap, isLeft) {
            const ctx = bitmap.context;
            const width = bitmap.width;
            const height = bitmap.height;
            
            // Leather texture base
            const coverGrad = ctx.createLinearGradient(0, 0, width, 0);
            coverGrad.addColorStop(0, '#4a3a7a');
            coverGrad.addColorStop(0.5, '#3d2d6a');
            coverGrad.addColorStop(1, '#2a1a50');
            
            ctx.fillStyle = coverGrad;
            ctx.fillRect(0, 0, width, height);
            
            // Subtle vignette
            const vignetteGrad = ctx.createRadialGradient(width / 2, height / 2, 0, width / 2, height / 2, Math.max(width, height));
            vignetteGrad.addColorStop(0, 'rgba(255, 255, 255, 0.1)');
            vignetteGrad.addColorStop(1, 'rgba(0, 0, 0, 0.3)');
            ctx.fillStyle = vignetteGrad;
            ctx.fillRect(0, 0, width, height);
            
            // Decorative border
            ctx.strokeStyle = 'rgba(200, 180, 150, 0.4)';
            ctx.lineWidth = 2;
            ctx.strokeRect(8, 8, width - 16, height - 16);
            
            // Inner accent line
            ctx.strokeStyle = 'rgba(200, 180, 150, 0.2)';
            ctx.lineWidth = 1;
            ctx.strokeRect(12, 12, width - 24, height - 24);
        }
        
        drawSpine(bitmap) {
            const ctx = bitmap.context;
            const width = bitmap.width;
            const height = bitmap.height;
            
            const spineGrad = ctx.createLinearGradient(0, 0, 0, height);
            spineGrad.addColorStop(0, '#3a2a5a');
            spineGrad.addColorStop(0.5, '#2a1a4a');
            spineGrad.addColorStop(1, '#3a2a5a');
            
            ctx.fillStyle = spineGrad;
            ctx.fillRect(0, 0, width, height);
            
            // Highlight
            ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.fillRect(2, 10, 4, height - 20);
        }
        
        drawPage(bitmap) {
            const ctx = bitmap.context;
            const width = bitmap.width;
            const height = bitmap.height;
            
            // Aged paper gradient
            const pageGrad = ctx.createLinearGradient(0, 0, 0, height);
            pageGrad.addColorStop(0, '#fdfcf9');
            pageGrad.addColorStop(1, '#f5f2ed');
            
            ctx.fillStyle = pageGrad;
            ctx.fillRect(0, 0, width, height);
            
            // Subtle aging
            ctx.fillStyle = 'rgba(180, 160, 140, 0.04)';
            for (let i = 0; i < 150; i++) {
                const x = Math.random() * width;
                const y = Math.random() * height;
                const size = Math.random() * 2;
                ctx.fillRect(x, y, size, size);
            }
            
            // Curved shadow at spine
            const shadowGrad = ctx.createLinearGradient(0, 0, 15, 0);
            shadowGrad.addColorStop(0, 'rgba(0, 0, 0, 0.08)');
            shadowGrad.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = shadowGrad;
            ctx.fillRect(0, 0, 15, height);
        }
        
        drawGlow(bitmap) {
            const ctx = bitmap.context;
            const width = bitmap.width;
            const height = bitmap.height;
            
            const glowGrad = ctx.createRadialGradient(width / 2, height / 2, 0, width / 2, height / 2, Math.min(width, height) / 2);
            glowGrad.addColorStop(0, 'rgba(255, 220, 150, 0.3)');
            glowGrad.addColorStop(1, 'rgba(255, 220, 150, 0)');
            
            ctx.fillStyle = glowGrad;
            ctx.fillRect(0, 0, width, height);
        }
        
        createInstructions() {
            this._instructionSprite = new Sprite();
            this._instructionSprite.bitmap = new Bitmap(400, 50);
            this._instructionSprite.x = Graphics.width / 2 - 200;
            this._instructionSprite.y = Graphics.height - 80;
            this._instructionSprite.bitmap.fontSize = 16;
            this._instructionSprite.bitmap.textColor = '#e0d7c3';
            this._instructionSprite.bitmap.outlineColor = '#000000';
            this._instructionSprite.bitmap.outlineWidth = 3;
            this._instructionSprite.bitmap.drawText('Pindutin ang SPACE o i-click para magpatuloy', 0, 0, 400, 50, 'center');
            this._instructionSprite.opacity = 0;
            this.addChild(this._instructionSprite);
        }
        
        startAnimation() {
            this._displayText = "";
            this._charIndex = 0;
            this._bookOpenProgress = 0;
            this._animationPhase = "opening";
            AudioManager.playSe({name: 'Book1', volume: 80, pitch: 100, pan: 0});
        }
        
        update() {
            super.update();
            this.updateParticles();
            
            switch (this._animationPhase) {
                case "opening":
                    this.updateBookOpening();
                    break;
                case "writing":
                    this.updateTextWriting();
                    break;
                case "complete":
                    this.updateCompletion();
                    break;
            }
            
            if (Input.isTriggered('ok') || TouchInput.isTriggered()) {
                this.handleInput();
            }
        }
        
        updateParticles() {
            const bitmap = this._particleLayer.bitmap;
            bitmap.clear();
            const ctx = bitmap.context;

            // Add magical floating butterflies ✨
            if (Math.random() < 0.05 && this._animationPhase !== "complete") {
                this._butterflies.push({
                    x: Graphics.width / 2 + (Math.random() - 0.5) * 100,
                    y: Graphics.height / 2 + 20 + Math.random() * 40,
                    vx: (Math.random() - 0.5) * 1.0,
                    vy: -1.2 - Math.random() * 0.8,
                    life: 120 + Math.random() * 80,
                    size: 10 + Math.random() * 6,
                    angle: Math.random() * Math.PI * 2,
                });
            }

            // Draw floating butterflies
            ctx.save();
            ctx.font = "bold 18px serif";
            for (let i = this._butterflies.length - 1; i >= 0; i--) {
                let b = this._butterflies[i];
                b.x += b.vx;
                b.y += b.vy;
                b.life--;
                b.angle += 0.15;

                let alpha = Math.min(1, b.life / 40, (160 - b.life) / 40);
                ctx.globalAlpha = alpha * 0.9;

                ctx.translate(b.x, b.y);
                ctx.rotate(Math.sin(b.angle) * 0.4);
                ctx.fillStyle = "rgba(255,255,255," + alpha + ")";
                ctx.fillText("🦋", -b.size / 2, b.size / 2);
                ctx.setTransform(1,0,0,1,0,0);

                if (b.life <= 0) this._butterflies.splice(i, 1);
            }
            ctx.restore();
        }

        
        updateBookOpening() {
            this._bookOpenProgress += 0.035;

            if (this._bookOpenProgress >= 1) {
                this._bookOpenProgress = 1;
                this._animationPhase = "writing";
                this._glowSprite.opacity = 255;
                this._pageLeft.opacity = 255;
                this._pageRight.opacity = 255;
                this._textSprite.opacity = 255;
                AudioManager.playSe({name: 'Book2', volume: 70, pitch: 120, pan: 0});
            }

            // Smooth easing for magic effect
            const easeOut = 1 - Math.pow(1 - this._bookOpenProgress, 3);
            
            this._bookContainer.scale.x = easeOut;
            this._bookContainer.scale.y = easeOut;
            
            // Floating magical lift while opening
            this._bookContainer.y = Graphics.height / 2 - easeOut * 20;

            // Enhanced glow pulse
            this._glowSprite.opacity = easeOut * 220 + Math.sin(Date.now() * 0.003) * 25;

            // Shadow depth effect on covers
            this._coverLeft.opacity = 255 - easeOut * 80;
            this._coverRight.opacity = 255 - easeOut * 80;
        }
        
        updateTextWriting() {
            if (this._instructionSprite.opacity < 200) {
                this._instructionSprite.opacity += 3;
            }
            
            if (this._charIndex < this._fullText.length) {
                const charsToAdd = Math.min(this._writingSpeed, this._fullText.length - this._charIndex);
                for (let i = 0; i < charsToAdd; i++) {
                    this._displayText += this._fullText[this._charIndex];
                    this._charIndex++;
                }
                this.updateTextDisplay();
                
                if (this._charIndex % 4 === 0) {
                    AudioManager.playSe({name: 'Cursor1', volume: 20, pitch: 160, pan: 0});
                }
            } else {
                this._animationPhase = "complete";
                $gameSwitches.setValue(this._completionSwitch, true);
                AudioManager.playSe({name: 'Decision1', volume: 70, pitch: 100, pan: 0});
            }
        }
        
        updateCompletion() {
            // Gentle floating effect
            const time = Date.now() * 0.0005;
            this._bookContainer.y = Graphics.height / 2 + Math.sin(time) * 3;
            
            // Glow pulse
            this._glowSprite.opacity = 150 + Math.sin(time * 1.5) * 50;
            
            if (this._isAutoPlay) {
                if (!this._autoCloseTimer) {
                    this._autoCloseTimer = 240; // 4 seconds at 60fps
                } else {
                    this._autoCloseTimer--;
                    if (this._autoCloseTimer <= 0) {
                        this.returnToMap();
                    }
                }
            }
        }
        
        updateTextDisplay() {
            const bitmap = this._textSprite.bitmap;
            bitmap.clear();
            
            bitmap.fontSize = 20;
            bitmap.textColor = '#2d1f13';
            bitmap.outlineWidth = 4;
            bitmap.outlineColor = 'rgba(255,255,255,0.4)';

            const lines = this.wrapText(this._displayText, bitmap.width - 10);
            const lineHeight = 32;
            const startY = 15;
            
            for (let i = 0; i < lines.length; i++) {
                // Soft fade for freshly written words
                let alpha = (i === lines.length - 1 && this._animationPhase === "writing") ? 0.9 : 1;
                bitmap.textColor = `rgba(50,30,20,${alpha})`;
                bitmap.drawText(lines[i], 5, startY + i * lineHeight, bitmap.width - 10, lineHeight, 'left');
            }

            // Glowing cursor effect
            if (this._animationPhase === "writing" && this._charIndex < this._fullText.length) {
                const lastLine = lines[lines.length - 1] || '';
                const cursorX = this.measureTextWidth(lastLine) + 5;
                const cursorY = startY + (lines.length - 1) * lineHeight;
                if (Math.floor(Date.now() / 400) % 2 === 0) {
                    bitmap.fillRect(cursorX, cursorY + 2, 3, lineHeight - 4, '#5c4b35');
                }
            }
        }
        
        wrapText(text, maxWidth) {
            const words = text.split(' ');
            const lines = [];
            let currentLine = '';
            
            for (const word of words) {
                const testLine = currentLine ? currentLine + ' ' + word : word;
                const testWidth = this._textSprite.bitmap.measureTextWidth(testLine);
                
                if (testWidth > maxWidth && currentLine !== '') {
                    lines.push(currentLine);
                    currentLine = word;
                } else {
                    currentLine = testLine;
                }
            }
            
            if (currentLine) {
                lines.push(currentLine);
            }
            
            return lines;
        }
        
        measureTextWidth(text) {
            return this._textSprite.bitmap.measureTextWidth(text);
        }
        
        handleInput() {
            if (this._animationPhase === "opening") {
                this._bookOpenProgress = 1;
                this._bookContainer.scale.x = 1;
                this._bookContainer.scale.y = 1;
                this._glowSprite.opacity = 200;
                this._pageLeft.opacity = 255;
                this._pageRight.opacity = 255;
                this._textSprite.opacity = 255;
                this._animationPhase = "writing";
            } else if (this._animationPhase === "writing") {
                this._displayText = this._fullText;
                this._charIndex = this._fullText.length;
                this.updateTextDisplay();
                this._animationPhase = "complete";
                $gameSwitches.setValue(this._completionSwitch, true);
            } else if (this._animationPhase === "complete") {
                this.returnToMap();
            }
        }
        
        returnToMap() {
            SceneManager.pop();
        }
    }

    window.Scene_BookAnimation = Scene_BookAnimation;
})();