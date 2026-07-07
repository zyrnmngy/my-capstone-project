//=============================================================================
// Filibustero_SplashScreen.js
//=============================================================================

/*:
 * @target MZ
 * @plugindesc [v1.0.2] Filibustero Loading Splash Screen - Magnifying Glass (Dark Theme)
 * @author YourName
 * @version 1.0.2
 * @description Displays a loading splash screen with a searching magnifying glass animation.
 * 
 * @help Filibustero_SplashScreen.js
 * 
 * This plugin MUST be loaded BEFORE Filibustero_Student_Menu.js!
 * It creates a loading screen that appears before the login menu.
 * 
 * @param loadingDuration
 * @text Loading Duration
 * @type number
 * @min 1
 * @max 10
 * @default 3
 * @desc Duration of the loading animation in seconds
 * 
 * @param backgroundColor
 * @text Background Color
 * @type string
 * @default #1a1a1a
 * @desc Background color for the splash screen (hex color)
 * 
 * @param foregroundColor
 * @text Foreground Color
 * @type string
 * @default #d4a01e
 * @desc Color of the magnifying glass and text (hex color)
 */

(() => {
    'use strict';

    const pluginName = "Filibustero_SplashScreen";
    const parameters = PluginManager.parameters(pluginName);
    const loadingDuration = Number(parameters['loadingDuration'] || 3) * 1000;
    const backgroundColor = String(parameters['backgroundColor'] || '#1a1a1a');
    const foregroundColor = String(parameters['foregroundColor'] || '#d4a01e');

    //=============================================================================
    // Scene_Splash - The Loading Splash Screen
    //=============================================================================
    
    function Scene_Splash() {
        this.initialize(...arguments);
    }

    Scene_Splash.prototype = Object.create(Scene_Base.prototype);
    Scene_Splash.prototype.constructor = Scene_Splash;

    Scene_Splash.prototype.initialize = function() {
        Scene_Base.prototype.initialize.call(this);
        this._loadingProgress = 0;
        this._startTime = Date.now();
        this._magnifyX = Graphics.width / 4;
        this._magnifyY = Graphics.height / 3;
        this._magnifyAngle = 0;
        this._targetX = Graphics.width / 4;
        this._targetY = Graphics.height / 3;
        this._searchPattern = 0;
        this._searchTimer = 0;
    };

    Scene_Splash.prototype.create = function() {
        Scene_Base.prototype.create.call(this);
        this.createBackground();
        this.createSearchArea();
        this.createMagnifyingGlass();
        this.createTitle();
        this.createLoadingText();
        this.generateSearchPath();
    };

    Scene_Splash.prototype.createBackground = function() {
        this._backgroundSprite = new Sprite();
        this._backgroundSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
        this._backgroundSprite.bitmap.fillRect(0, 0, Graphics.width, Graphics.height, backgroundColor);
        this.addChild(this._backgroundSprite);
    };

    Scene_Splash.prototype.createSearchArea = function() {
        // Create subtle document/clue patterns in background
        this._searchAreaSprite = new Sprite();
        this._searchAreaSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
        this.addChild(this._searchAreaSprite);
        
        const bitmap = this._searchAreaSprite.bitmap;
        const ctx = bitmap.context;
        
        // Draw subtle document lines with golden tint
        ctx.strokeStyle = 'rgba(212, 160, 30, 0.1)';
        ctx.lineWidth = 1;
        
        for (let i = 0; i < 20; i++) {
            const y = (i + 1) * (Graphics.height / 20);
            ctx.beginPath();
            ctx.moveTo(100, y);
            ctx.lineTo(Graphics.width - 100, y);
            ctx.stroke();
        }
        
        // Draw some "clue" circles with golden glow
        ctx.strokeStyle = 'rgba(212, 160, 30, 0.15)';
        ctx.lineWidth = 2;
        for (let i = 0; i < 5; i++) {
            const x = Math.random() * (Graphics.width - 200) + 100;
            const y = Math.random() * (Graphics.height - 200) + 100;
            const radius = Math.random() * 30 + 20;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.stroke();
        }
        
        bitmap.baseTexture.update();
    };

    Scene_Splash.prototype.createMagnifyingGlass = function() {
        this._magnifySprite = new Sprite();
        this._magnifySprite.bitmap = new Bitmap(200, 200);
        this._magnifySprite.anchor.x = 0.5;
        this._magnifySprite.anchor.y = 0.5;
        this.addChild(this._magnifySprite);
        
        this.drawMagnifyingGlass();
    };

    Scene_Splash.prototype.drawMagnifyingGlass = function() {
        const bitmap = this._magnifySprite.bitmap;
        const ctx = bitmap.context;
        const centerX = 100;
        const centerY = 100;
        
        ctx.clearRect(0, 0, 200, 200);
        ctx.save();
        
        // Glass lens (outer circle) with golden color
        ctx.strokeStyle = foregroundColor;
        ctx.lineWidth = 6;
        ctx.beginPath();
        ctx.arc(centerX, centerY, 45, 0, Math.PI * 2);
        ctx.stroke();
        
        // Inner lens shine with warm glow
        ctx.strokeStyle = 'rgba(212, 160, 30, 0.4)';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.arc(centerX, centerY, 40, 0, Math.PI * 2);
        ctx.stroke();
        
        // Lens reflection with golden highlight
        ctx.strokeStyle = 'rgba(255, 215, 100, 0.6)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(centerX - 10, centerY - 10, 15, 0, Math.PI * 1.5);
        ctx.stroke();
        
        // Handle with golden color
        ctx.strokeStyle = foregroundColor;
        ctx.lineWidth = 8;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(centerX + 35, centerY + 35);
        ctx.lineTo(centerX + 70, centerY + 70);
        ctx.stroke();
        
        // Handle end cap
        ctx.fillStyle = foregroundColor;
        ctx.beginPath();
        ctx.arc(centerX + 70, centerY + 70, 6, 0, Math.PI * 2);
        ctx.fill();
        
        ctx.restore();
        bitmap.baseTexture.update();
    };

    Scene_Splash.prototype.createTitle = function() {
        this._titleSprite = new Sprite();
        this._titleSprite.bitmap = new Bitmap(Graphics.width, 120);
        this._titleSprite.bitmap.fontSize = 56;
        this._titleSprite.bitmap.fontFace = $gameSystem ? $gameSystem.mainFontFace() : 'GameFont';
        this._titleSprite.bitmap.textColor = foregroundColor;
        this._titleSprite.bitmap.outlineColor = 'rgba(0, 0, 0, 0.8)';
        this._titleSprite.bitmap.outlineWidth = 4;
        this._titleSprite.y = Graphics.height / 2 + 80;
        
        const text = "FILIBUSTERO";
        this._titleSprite.bitmap.drawText(text, 0, 0, Graphics.width, 80, 'center');
        
        this.addChild(this._titleSprite);
    };

    Scene_Splash.prototype.createLoadingText = function() {
        this._loadingSprite = new Sprite();
        this._loadingSprite.bitmap = new Bitmap(Graphics.width, 100);
        this._loadingSprite.bitmap.fontSize = 20;
        this._loadingSprite.bitmap.textColor = foregroundColor;
        this._loadingSprite.y = Graphics.height - 100;
        this.addChild(this._loadingSprite);
        
        this._statusSprite = new Sprite();
        this._statusSprite.bitmap = new Bitmap(Graphics.width, 60);
        this._statusSprite.bitmap.fontSize = 16;
        this._statusSprite.bitmap.textColor = 'rgba(212, 160, 30, 0.7)';
        this._statusSprite.y = Graphics.height - 60;
        this.addChild(this._statusSprite);
        
        this._dots = 0;
        this._dotTimer = 0;
    };

    Scene_Splash.prototype.generateSearchPath = function() {
        // Generate random search path points
        this._searchPath = [];
        const margin = 150;
        
        for (let i = 0; i < 8; i++) {
            this._searchPath.push({
                x: Math.random() * (Graphics.width - margin * 2) + margin,
                y: Math.random() * (Graphics.height - margin * 2) + margin / 2
            });
        }
        
        this._currentPathIndex = 0;
        this._targetX = this._searchPath[0].x;
        this._targetY = this._searchPath[0].y;
    };

    Scene_Splash.prototype.update = function() {
        Scene_Base.prototype.update.call(this);
        
        const elapsed = Date.now() - this._startTime;
        this._loadingProgress = Math.min(elapsed / loadingDuration, 1);
        
        this.updateMagnifyingGlass();
        this.updateLoadingText();
        
        if (this._loadingProgress >= 1 && !this._transitioning) {
            this._transitioning = true;
            this.fadeOutAll();
            
            setTimeout(() => {
                SceneManager.goto(Scene_Title);
            }, 500);
        }
    };

    Scene_Splash.prototype.updateMagnifyingGlass = function() {
        this._searchTimer++;
        
        // Move magnifying glass towards target
        const speed = 3;
        const dx = this._targetX - this._magnifyX;
        const dy = this._targetY - this._magnifyY;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        if (distance > 5) {
            this._magnifyX += (dx / distance) * speed;
            this._magnifyY += (dy / distance) * speed;
            
            // Rotate slightly based on movement direction
            this._magnifyAngle = Math.atan2(dy, dx) + Math.PI / 4;
        } else {
            // Reached target, move to next point
            this._currentPathIndex = (this._currentPathIndex + 1) % this._searchPath.length;
            this._targetX = this._searchPath[this._currentPathIndex].x;
            this._targetY = this._searchPath[this._currentPathIndex].y;
        }
        
        // Add slight bobbing motion
        const bobbing = Math.sin(this._searchTimer / 10) * 3;
        
        // Update magnifying glass position
        this._magnifySprite.x = this._magnifyX;
        this._magnifySprite.y = this._magnifyY + bobbing;
        this._magnifySprite.rotation = this._magnifyAngle + Math.sin(this._searchTimer / 20) * 0.1;
        
        // Slight scale pulse
        const scale = 1 + Math.sin(this._searchTimer / 15) * 0.05;
        this._magnifySprite.scale.x = scale;
        this._magnifySprite.scale.y = scale;
    };

    Scene_Splash.prototype.updateLoadingText = function() {
        this._dotTimer++;
        
        if (this._dotTimer > 20) {
            this._dotTimer = 0;
            this._dots = (this._dots + 1) % 4;
        }
        
        const bitmap = this._loadingSprite.bitmap;
        bitmap.clear();
        
        const percent = Math.floor(this._loadingProgress * 100);
        const dots = ".".repeat(this._dots);
        const text = `Searching${dots} ${percent}%`;
        
        bitmap.drawText(text, 0, 20, Graphics.width, 48, 'center');
        
        // Update status text
        const statusBitmap = this._statusSprite.bitmap;
        statusBitmap.clear();
        
        let statusText = "Looking for clues";
        if (this._loadingProgress > 0.3 && this._loadingProgress < 0.6) {
            statusText = "Investigating evidence";
        } else if (this._loadingProgress >= 0.6) {
            statusText = "Almost there";
        }
        
        statusBitmap.drawText(statusText, 0, 0, Graphics.width, 40, 'center');
    };

    Scene_Splash.prototype.fadeOutAll = function() {
        this._fadeSign = -1;
        this._fadeDuration = 30;
        this._fadeWhite = false;
        this._fadeOpacity = 255;
    };

    //=============================================================================
    // Scene_Boot - Redirect to Splash Screen
    //=============================================================================

    const _Scene_Boot_startNormalGame = Scene_Boot.prototype.startNormalGame;
    Scene_Boot.prototype.startNormalGame = function() {
        this.checkPlayerLocation();
        DataManager.setupNewGame();
        SceneManager.goto(Scene_Splash);
        Window_TitleCommand.initCommandPosition();
    };

    console.log("Filibustero Splash Screen Plugin loaded successfully!");

})();