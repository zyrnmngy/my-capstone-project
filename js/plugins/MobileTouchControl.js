/*:
 * @target MZ
 * @plugindesc Mobile Touch Controls with Virtual D-Pad, Buttons, and Text Input Support v1.4
 * @author Claude
 * @url https://your-website.com
 *
 * @param dPadSize
 * @text D-Pad Size
 * @type number
 * @min 80
 * @max 200
 * @default 120
 * @desc Size of the virtual D-Pad in pixels
 *
 * @param buttonSize
 * @text Button Size
 * @type number
 * @min 50
 * @max 150
 * @default 70
 * @desc Size of action buttons in pixels
 *
 * @param dPadOpacity
 * @text D-Pad Opacity
 * @type number
 * @min 0
 * @max 100
 * @default 60
 * @desc Opacity of the D-Pad (0-100)
 *
 * @param buttonOpacity
 * @text Button Opacity
 * @type number
 * @min 0
 * @max 100
 * @default 60
 * @desc Opacity of action buttons (0-100)
 *
 * @param dPadPosition
 * @text D-Pad Position
 * @type select
 * @option Bottom Left
 * @value bottomLeft
 * @option Bottom Right
 * @value bottomRight
 * @default bottomLeft
 * @desc Position of the virtual D-Pad
 *
 * @param showInDesktop
 * @text Show on Desktop
 * @type boolean
 * @default false
 * @desc Show controls even on desktop/mouse devices
 *
 * @param forceShow
 * @text Force Show Controls
 * @type boolean
 * @default false
 * @desc Force show controls regardless of device (for debugging)
 *
 * @help
 * ============================================================================
 * Mobile Touch Controls Plugin for RPG Maker MZ v1.4
 * ============================================================================
 * 
 * IMPORTANT: The filename MUST be exactly: MobileTouchControls.js
 * 
 * This plugin adds virtual touch controls for mobile devices:
 * - Virtual D-Pad for movement (left side)
 * - Action buttons: ✓ (OK), ✕ (Cancel), ≡ (Menu) (right side)
 * - Mobile keyboard support for text input
 * - Mobile-friendly prompt() replacement with async support
 * 
 * Controls will automatically appear on mobile/touch devices and in mobile
 * emulation mode (Chrome DevTools device toolbar).
 * 
 * Testing in Chrome DevTools:
 * 1. Press F12 to open DevTools
 * 2. Click the device toolbar icon (or Ctrl+Shift+M)
 * 3. Select a mobile device (e.g., Samsung Galaxy)
 * 4. Refresh the page (F5) - controls should now appear
 * 
 * If you want to force controls on desktop without emulation:
 * 1. Set "Force Show Controls" to true in Plugin Manager
 * 2. Remember to set it back to false for production!
 * 
 * ============================================================================
 */

(() => {
    'use strict';

    const pluginName = 'MobileTouchControls';
    const parameters = PluginManager.parameters(pluginName);
    
    const dPadSize = Number(parameters['dPadSize'] || 120);
    const buttonSize = Number(parameters['buttonSize'] || 70);
    const dPadOpacity = Number(parameters['dPadOpacity'] || 60) / 100;
    const buttonOpacity = Number(parameters['buttonOpacity'] || 60) / 100;
    const dPadPosition = String(parameters['dPadPosition'] || 'bottomLeft');
    const showInDesktop = String(parameters['showInDesktop'] || 'false') === 'true';
    const forceShow = String(parameters['forceShow'] || 'false') === 'true';

    console.log('[MobileControls] ============================================');
    console.log('[MobileControls] Plugin loading...');
    console.log('[MobileControls] Parameters:', { dPadSize, buttonSize, dPadOpacity, buttonOpacity, forceShow, showInDesktop });

    // Enhanced touch detection - checks multiple indicators
    const isTouchDevice = () => {
        if (forceShow) {
            console.log('[MobileControls] Force show is enabled');
            return true;
        }
        
        if (showInDesktop) {
            console.log('[MobileControls] Show in desktop is enabled');
            return true;
        }
        
        // Check for touch support
        const hasTouchScreen = (
            ('ontouchstart' in window) || 
            (navigator.maxTouchPoints > 0) || 
            (navigator.msMaxTouchPoints > 0)
        );
        
        // Check user agent for mobile devices
        const isMobileUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        // Check pointer type (coarse = touch)
        const hasCoarsePointer = window.matchMedia("(pointer: coarse)").matches;
        
        // Check if we're in mobile viewport (common mobile screen width)
        const isMobileViewport = window.innerWidth <= 768;
        
        // Check if touch events are available (works in Chrome DevTools mobile emulation)
        const hasTouchEvents = 'TouchEvent' in window;
        
        const isTouch = hasTouchScreen || isMobileUA || hasCoarsePointer || (isMobileViewport && hasTouchEvents);
        
        console.log('[MobileControls] Touch detection:', { 
            hasTouchScreen, 
            isMobileUA, 
            hasCoarsePointer,
            isMobileViewport,
            hasTouchEvents,
            windowWidth: window.innerWidth,
            finalResult: isTouch 
        });
        
        return isTouch;
    };

    // Global async prompt function
    window.$mobilePrompt = function(message, defaultValue = '') {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.85);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                box-sizing: border-box;
            `;

            const dialog = document.createElement('div');
            dialog.style.cssText = `
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                border: 4px solid #00d4ff;
                border-radius: 16px;
                padding: 24px;
                max-width: 90%;
                width: 450px;
                box-shadow: 0 10px 40px rgba(0, 212, 255, 0.3);
            `;

            const messageDiv = document.createElement('div');
            messageDiv.textContent = message || 'Please enter a value:';
            messageDiv.style.cssText = `
                color: white;
                font-size: 20px;
                margin-bottom: 18px;
                font-family: 'Arial', sans-serif;
                line-height: 1.5;
                font-weight: 500;
                text-align: center;
            `;
            dialog.appendChild(messageDiv);

            const input = document.createElement('input');
            input.type = 'text';
            input.value = defaultValue || '';
            input.style.cssText = `
                width: 100%;
                padding: 16px;
                font-size: 18px;
                border: 3px solid #00d4ff;
                background: rgba(0, 0, 0, 0.5);
                color: white;
                border-radius: 10px;
                margin-bottom: 18px;
                box-sizing: border-box;
                font-family: 'Arial', sans-serif;
                outline: none;
            `;
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('autocapitalize', 'off');
            dialog.appendChild(input);

            const buttonContainer = document.createElement('div');
            buttonContainer.style.cssText = `
                display: flex;
                gap: 12px;
            `;

            const okButton = document.createElement('button');
            okButton.textContent = 'OK';
            okButton.style.cssText = `
                flex: 1;
                padding: 16px;
                font-size: 18px;
                background: linear-gradient(135deg, #00b894 0%, #00d4aa 100%);
                color: white;
                border: 3px solid white;
                border-radius: 10px;
                cursor: pointer;
                font-weight: bold;
                font-family: 'Arial', sans-serif;
            `;
            
            const cancelButton = document.createElement('button');
            cancelButton.textContent = 'Cancel';
            cancelButton.style.cssText = `
                flex: 1;
                padding: 16px;
                font-size: 18px;
                background: linear-gradient(135deg, #d63031 0%, #ff4757 100%);
                color: white;
                border: 3px solid white;
                border-radius: 10px;
                cursor: pointer;
                font-weight: bold;
                font-family: 'Arial', sans-serif;
            `;

            const cleanup = () => {
                if (overlay.parentNode) {
                    document.body.removeChild(overlay);
                }
            };

            const handleOk = (e) => {
                if (e) e.preventDefault();
                const value = input.value;
                cleanup();
                resolve(value);
            };

            const handleCancel = (e) => {
                if (e) e.preventDefault();
                cleanup();
                resolve(null);
            };

            okButton.onclick = handleOk;
            cancelButton.onclick = handleCancel;

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    handleOk(e);
                } else if (e.key === 'Escape') {
                    handleCancel(e);
                }
            });

            buttonContainer.appendChild(okButton);
            buttonContainer.appendChild(cancelButton);
            dialog.appendChild(buttonContainer);
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            setTimeout(() => {
                input.focus();
                input.select();
            }, 100);
        });
    };

    class MobileControls {
        constructor() {
            this._container = null;
            this._dPad = null;
            this._buttons = {};
            this._activeTouch = {};
            this._currentDirection = null;
            this._activeButtons = new Set();
            this._mobileInput = null;
            this._initialized = false;
            this._isVisible = false;
        }

        initialize() {
            const shouldShow = isTouchDevice();
            
            console.log('[MobileControls] Should show controls:', shouldShow);
            
            if (!shouldShow) {
                console.log('[MobileControls] Controls will not be shown (not a touch device)');
                this._isVisible = false;
                return;
            }
            
            console.log('[MobileControls] Creating controls...');
            
            try {
                this.createContainer();
                this.createDPad();
                this.createButtons();
                this.createMobileInput();
                this.setupEventListeners();
                this.patchTextInput();
                this._initialized = true;
                this._isVisible = true;
                console.log('[MobileControls] ✓ Controls created successfully!');
                console.log('[MobileControls] Controls visible:', this._container ? 'YES' : 'NO');
            } catch (error) {
                console.error('[MobileControls] ✗ Error during initialization:', error);
            }
        }

        destroy() {
            if (this._container && this._container.parentNode) {
                this._container.parentNode.removeChild(this._container);
            }
            if (this._mobileInput && this._mobileInput.parentNode) {
                this._mobileInput.parentNode.removeChild(this._mobileInput);
            }
            this._container = null;
            this._dPad = null;
            this._buttons = {};
            this._initialized = false;
            this._isVisible = false;
            console.log('[MobileControls] Controls destroyed');
        }

        refresh() {
            const shouldShow = isTouchDevice();
            
            if (shouldShow && !this._isVisible) {
                console.log('[MobileControls] Showing controls...');
                this.destroy();
                this.initialize();
            } else if (!shouldShow && this._isVisible) {
                console.log('[MobileControls] Hiding controls...');
                this.destroy();
            }
        }

        createContainer() {
            this._container = document.createElement('div');
            this._container.id = 'mobileControls';
            this._container.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 9999;
                user-select: none;
                -webkit-user-select: none;
                -webkit-touch-callout: none;
            `;
            document.body.appendChild(this._container);
            console.log('[MobileControls] ✓ Container created');
        }

        createDPad() {
            const dPad = document.createElement('div');
            const radius = dPadSize / 2;
            const margin = 20;
            
            const leftPos = dPadPosition === 'bottomLeft' ? margin : '';
            const rightPos = dPadPosition === 'bottomRight' ? margin : '';
            
            dPad.style.cssText = `
                position: absolute;
                bottom: ${margin}px;
                ${leftPos ? `left: ${leftPos}px;` : ''}
                ${rightPos ? `right: ${rightPos}px;` : ''}
                width: ${dPadSize}px;
                height: ${dPadSize}px;
                pointer-events: auto;
                opacity: ${dPadOpacity};
                touch-action: none;
                -webkit-touch-callout: none;
            `;

            const outer = document.createElement('div');
            outer.style.cssText = `
                position: absolute;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                background: rgba(0, 0, 0, 0.5);
                border: 4px solid rgba(255, 255, 255, 0.7);
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            `;
            dPad.appendChild(outer);

            const stick = document.createElement('div');
            stick.id = 'dPadStick';
            const stickSize = dPadSize * 0.4;
            stick.style.cssText = `
                position: absolute;
                width: ${stickSize}px;
                height: ${stickSize}px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.8);
                border: 3px solid rgba(255, 255, 255, 1);
                left: ${(dPadSize - stickSize) / 2}px;
                top: ${(dPadSize - stickSize) / 2}px;
                transition: all 0.1s;
                pointer-events: none;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            `;
            dPad.appendChild(stick);

            this._dPad = { element: dPad, stick: stick, radius: radius };
            this._container.appendChild(dPad);
            console.log('[MobileControls] ✓ D-Pad created');
        }

        createButtons() {
            const margin = 20;
            const spacing = 15;
            const buttons = [
                { name: 'ok', symbol: 'OK', color: 'rgba(0, 200, 0, 0.8)', bottom: margin + buttonSize + spacing, key: 'ok', tooltip: 'Confirm' },
                { name: 'cancel', symbol: '↩', color: 'rgba(200, 0, 0, 0.8)', bottom: margin, key: 'cancel', tooltip: 'Return' }
            ];

            buttons.forEach((btn) => {
                const button = document.createElement('div');
                button.id = `btn_${btn.name}`;
                button.style.cssText = `
                    position: absolute;
                    right: ${margin}px;
                    bottom: ${btn.bottom}px;
                    width: ${buttonSize}px;
                    height: ${buttonSize}px;
                    border-radius: 50%;
                    background: ${btn.color};
                    border: 4px solid rgba(255, 255, 255, 0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: ${buttonSize * 0.5}px;
                    font-weight: bold;
                    color: white;
                    pointer-events: auto;
                    opacity: ${buttonOpacity};
                    transition: transform 0.1s, opacity 0.1s;
                    touch-action: none;
                    -webkit-touch-callout: none;
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
                    line-height: 1;
                `;
                button.textContent = btn.symbol;
                button.dataset.key = btn.key;
                button.title = btn.tooltip;

                this._buttons[btn.name] = button;
                this._container.appendChild(button);
            });
            console.log('[MobileControls] ✓ Buttons created:', Object.keys(this._buttons));
        }

        createMobileInput() {
            this._mobileInput = document.createElement('input');
            this._mobileInput.type = 'text';
            this._mobileInput.id = 'mobileTextInput';
            this._mobileInput.style.cssText = `
                position: absolute;
                left: -9999px;
                top: -9999px;
                width: 1px;
                height: 1px;
                opacity: 0;
                pointer-events: none;
            `;
            document.body.appendChild(this._mobileInput);
            console.log('[MobileControls] ✓ Mobile input created');
        }

        setupEventListeners() {
            // D-Pad events
            this._dPad.element.addEventListener('touchstart', this.onDPadStart.bind(this), { passive: false });
            this._dPad.element.addEventListener('touchmove', this.onDPadMove.bind(this), { passive: false });
            this._dPad.element.addEventListener('touchend', this.onDPadEnd.bind(this), { passive: false });
            this._dPad.element.addEventListener('touchcancel', this.onDPadEnd.bind(this), { passive: false });

            // Also add mouse support for desktop testing (when forceShow is enabled)
            if (forceShow || showInDesktop) {
                this._dPad.element.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this._activeTouch.dpad = 'mouse';
                    this.updateDPadMouse(e);
                    
                    const onMouseMove = (e) => {
                        if (this._activeTouch.dpad === 'mouse') {
                            this.updateDPadMouse(e);
                        }
                    };
                    
                    const onMouseUp = (e) => {
                        this.resetDPad();
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    };
                    
                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
            }

            // Button events
            Object.values(this._buttons).forEach(button => {
                button.addEventListener('touchstart', this.onButtonStart.bind(this), { passive: false });
                button.addEventListener('touchend', this.onButtonEnd.bind(this), { passive: false });
                button.addEventListener('touchcancel', this.onButtonEnd.bind(this), { passive: false });
                
                // Also add mouse support for desktop testing (when forceShow is enabled)
                if (forceShow || showInDesktop) {
                    button.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        this.onButtonStart(e);
                    });
                    button.addEventListener('mouseup', (e) => {
                        e.preventDefault();
                        this.onButtonEnd(e);
                    });
                }
            });
            
            console.log('[MobileControls] ✓ Event listeners set up');
        }

        patchTextInput() {
            const _Window_NameInput_processHandling = Window_NameInput.prototype.processHandling;
            Window_NameInput.prototype.processHandling = function() {
                if (isTouchDevice() && this.isOpen() && this.active) {
                    const mobileInput = document.getElementById('mobileTextInput');
                    if (mobileInput && !mobileInput._isFocused) {
                        mobileInput._isFocused = true;
                        mobileInput.value = this._editWindow._name;
                        mobileInput.style.position = 'fixed';
                        mobileInput.style.left = '50%';
                        mobileInput.style.top = '50%';
                        mobileInput.style.transform = 'translate(-50%, -50%)';
                        mobileInput.style.width = '80%';
                        mobileInput.style.height = '50px';
                        mobileInput.style.fontSize = '18px';
                        mobileInput.style.opacity = '1';
                        mobileInput.style.pointerEvents = 'auto';
                        mobileInput.style.zIndex = '10000';
                        mobileInput.style.border = '3px solid white';
                        mobileInput.style.background = 'rgba(0, 0, 0, 0.8)';
                        mobileInput.style.color = 'white';
                        mobileInput.style.padding = '12px';
                        mobileInput.style.borderRadius = '8px';
                        mobileInput.maxLength = this._editWindow._maxLength;
                        
                        mobileInput.focus();
                        
                        mobileInput.oninput = () => {
                            this._editWindow._name = mobileInput.value;
                            this._editWindow.refresh();
                        };
                    }
                }
                _Window_NameInput_processHandling.call(this);
            };

            const _Window_NameInput_onNameOk = Window_NameInput.prototype.onNameOk;
            Window_NameInput.prototype.onNameOk = function() {
                const mobileInput = document.getElementById('mobileTextInput');
                if (mobileInput) {
                    mobileInput._isFocused = false;
                    mobileInput.style.left = '-9999px';
                    mobileInput.style.top = '-9999px';
                    mobileInput.style.opacity = '0';
                    mobileInput.style.pointerEvents = 'none';
                    mobileInput.blur();
                }
                _Window_NameInput_onNameOk.call(this);
            };
        }

        onDPadStart(e) {
            e.preventDefault();
            console.log('[MobileControls] D-Pad touched');
            const touch = e.changedTouches[0];
            this._activeTouch.dpad = touch.identifier;
            this.updateDPad(touch);
        }

        onDPadMove(e) {
            e.preventDefault();
            const touch = Array.from(e.changedTouches).find(t => t.identifier === this._activeTouch.dpad);
            if (touch) this.updateDPad(touch);
        }

        onDPadEnd(e) {
            e.preventDefault();
            console.log('[MobileControls] D-Pad released');
            this.resetDPad();
        }

        updateDPadMouse(mouseEvent) {
            const touch = {
                clientX: mouseEvent.clientX,
                clientY: mouseEvent.clientY
            };
            this.updateDPad(touch);
        }

        updateDPad(touch) {
            const rect = this._dPad.element.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const dx = touch.clientX - centerX;
            const dy = touch.clientY - centerY;
            const distance = Math.sqrt(dx * dx + dy * dy);
            const angle = Math.atan2(dy, dx);
            
            const maxDistance = this._dPad.radius * 0.6;
            const limitedDistance = Math.min(distance, maxDistance);
            const stickX = Math.cos(angle) * limitedDistance;
            const stickY = Math.sin(angle) * limitedDistance;
            
            this._dPad.stick.style.transform = `translate(${stickX}px, ${stickY}px)`;

            const deadZone = this._dPad.radius * 0.2;
            if (distance < deadZone) {
                this.setDirection(null);
                return;
            }

            const degrees = (angle * 180 / Math.PI + 360) % 360;
            let direction = null;

            if (degrees >= 337.5 || degrees < 22.5) direction = 'right';
            else if (degrees >= 22.5 && degrees < 67.5) direction = 'downright';
            else if (degrees >= 67.5 && degrees < 112.5) direction = 'down';
            else if (degrees >= 112.5 && degrees < 157.5) direction = 'downleft';
            else if (degrees >= 157.5 && degrees < 202.5) direction = 'left';
            else if (degrees >= 202.5 && degrees < 247.5) direction = 'upleft';
            else if (degrees >= 247.5 && degrees < 292.5) direction = 'up';
            else if (degrees >= 292.5 && degrees < 337.5) direction = 'upright';

            this.setDirection(direction);
        }

        resetDPad() {
            this._activeTouch.dpad = null;
            this._dPad.stick.style.transform = 'translate(0, 0)';
            this.setDirection(null);
        }

        setDirection(direction) {
            if (this._currentDirection === direction) return;

            // Release old directions
            if (this._currentDirection) {
                if (this._currentDirection === 'downright') {
                    this.releaseKey('down');
                    this.releaseKey('right');
                } else if (this._currentDirection === 'downleft') {
                    this.releaseKey('down');
                    this.releaseKey('left');
                } else if (this._currentDirection === 'upright') {
                    this.releaseKey('up');
                    this.releaseKey('right');
                } else if (this._currentDirection === 'upleft') {
                    this.releaseKey('up');
                    this.releaseKey('left');
                } else {
                    this.releaseKey(this._currentDirection);
                }
            }

            this._currentDirection = direction;

            // Press new directions
            if (direction) {
                console.log('[MobileControls] Direction:', direction);
                if (direction === 'downright') {
                    this.pressKey('down');
                    this.pressKey('right');
                } else if (direction === 'downleft') {
                    this.pressKey('down');
                    this.pressKey('left');
                } else if (direction === 'upright') {
                    this.pressKey('up');
                    this.pressKey('right');
                } else if (direction === 'upleft') {
                    this.pressKey('up');
                    this.pressKey('left');
                } else {
                    this.pressKey(direction);
                }
            }
        }

        onButtonStart(e) {
            e.preventDefault();
            const button = e.currentTarget;
            const key = button.dataset.key;
            
            console.log('[MobileControls] Button pressed:', key);
            
            button.style.transform = 'scale(0.9)';
            button.style.opacity = '1';
            
            this._activeButtons.add(key);
            this.pressKey(key);
        }

        onButtonEnd(e) {
            e.preventDefault();
            const button = e.currentTarget;
            const key = button.dataset.key;
            
            console.log('[MobileControls] Button released:', key);
            
            button.style.transform = 'scale(1)';
            button.style.opacity = buttonOpacity;
            
            this._activeButtons.delete(key);
            this.releaseKey(key);
        }

        pressKey(keyName) {
            if (!Input._currentState) {
                console.error('[MobileControls] Input._currentState is undefined!');
                return;
            }
            
            if (Input._currentState[keyName]) return;
            
            Input._currentState[keyName] = true;
            Input._latestButton = keyName;
            Input._pressedTime = 0;
            Input._date = Date.now();
            
            console.log('[MobileControls] Key pressed:', keyName, 'State:', Input._currentState[keyName]);
        }

        releaseKey(keyName) {
            if (!Input._currentState) return;
            
            Input._currentState[keyName] = false;
            console.log('[MobileControls] Key released:', keyName);
        }
    }

    // Initialize after everything is loaded
    const _Scene_Boot_start = Scene_Boot.prototype.start;
    Scene_Boot.prototype.start = function() {
        _Scene_Boot_start.call(this);
        console.log('[MobileControls] Scene_Boot.start called');
        
        // Small delay to ensure everything is ready
        setTimeout(() => {
            if (!window.mobileControls) {
                console.log('[MobileControls] Creating MobileControls instance...');
                window.mobileControls = new MobileControls();
                window.mobileControls.initialize();
            }
        }, 100);
    };

    // Listen for viewport changes (helps with device emulation)
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.mobileControls) {
                console.log('[MobileControls] Window resized, checking if controls need refresh...');
                window.mobileControls.refresh();
            }
        }, 300);
    });

    // Also detect first touch event (for Chrome DevTools emulation)
    let touchDetected = false;
    document.addEventListener('touchstart', () => {
        if (!touchDetected) {
            touchDetected = true;
            console.log('[MobileControls] Touch event detected, checking controls...');
            if (window.mobileControls) {
                window.mobileControls.refresh();
            }
        }
    }, { once: true, passive: true });

    console.log('[MobileControls] Plugin loaded successfully');
    console.log('[MobileControls] ============================================');

})();