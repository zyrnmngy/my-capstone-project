//=============================================================================
// Filibustero_MobileInput.js
//=============================================================================

/*:
 * @target MZ
 * @plugindesc [v1.1.0] Mobile-friendly input system with on-screen keyboard
 * @author YourName
 * @version 1.1.0
 * 
 * @help Filibustero_MobileInput.js
 * 
 * This plugin provides a mobile-friendly input system that replaces
 * the browser's prompt() with a custom on-screen keyboard.
 * 
 * IMPORTANT: Load this plugin BEFORE Filibustero_Student_Menu.js
 */

(() => {
    'use strict';

    //=============================================================================
    // Mobile Input Scene
    //=============================================================================
    function Scene_MobileInput() {
        this.initialize(...arguments);
    }

    Scene_MobileInput.prototype = Object.create(Scene_MenuBase.prototype);
    Scene_MobileInput.prototype.constructor = Scene_MobileInput;

    Scene_MobileInput.prototype.initialize = function(title, defaultValue, callback, isPassword) {
        Scene_MenuBase.prototype.initialize.call(this);
        this._title = title || "Enter Text";
        this._defaultValue = defaultValue || "";
        this._callback = callback;
        this._isPassword = isPassword || false;
        this._inputValue = this._defaultValue;
    };

    Scene_MobileInput.prototype.create = function() {
        Scene_MenuBase.prototype.create.call(this);
        this.createBackground();
        this.createTitleWindow();
        this._inputDisplayWindow = null; // Explicitly set to null - no display window
        this.createKeyboardWindow();
        this.createButtonWindow();
    };

    Scene_MobileInput.prototype.createBackground = function() {
        this._backgroundSprite = new Sprite();
        this._backgroundSprite.bitmap = new Bitmap(Graphics.width, Graphics.height);
        this._backgroundSprite.bitmap.fillRect(0, 0, Graphics.width, Graphics.height, 'rgba(0, 0, 0, 0.7)');
        this.addChild(this._backgroundSprite);
    };

    Scene_MobileInput.prototype.createTitleWindow = function() {
        const rect = new Rectangle(40, 10, Graphics.boxWidth - 80, 50);
        this._titleWindow = new Window_Base(rect);
        this._titleWindow.drawText(this._title, 0, 0, this._titleWindow.contentsWidth(), 'center');
        this.addWindow(this._titleWindow);
    };

    Scene_MobileInput.prototype.createInputDisplayWindow = function() {
        const rect = new Rectangle(40, 65, Graphics.boxWidth - 80, 60);
        this._inputDisplayWindow = new Window_MobileInputDisplay(rect, this._isPassword);
        this._inputDisplayWindow.setValue(this._inputValue);
        this.addWindow(this._inputDisplayWindow);
    };

    Scene_MobileInput.prototype.createKeyboardWindow = function() {
        const rect = new Rectangle(40, 70, Graphics.boxWidth - 80, 400);
        this._keyboardWindow = new Window_MobileKeyboard(rect);
        this._keyboardWindow.setHandler('key', this.onKeyPress.bind(this));
        this._keyboardWindow.setHandler('backspace', this.onBackspace.bind(this));
        this._keyboardWindow.setHandler('space', this.onSpace.bind(this));
        this._keyboardWindow.setHandler('clear', this.onClear.bind(this));
        this._keyboardWindow.setBackgroundType(0);
        this._keyboardWindow.activate();
        this.addWindow(this._keyboardWindow);
    };

    Scene_MobileInput.prototype.createButtonWindow = function() {
        const rect = new Rectangle(40, 475, Graphics.boxWidth - 80, 60);
        this._buttonWindow = new Window_MobileInputButtons(rect);
        this._buttonWindow.setHandler('ok', this.onOk.bind(this));
        this._buttonWindow.setHandler('cancel', this.onCancel.bind(this));
        this.addWindow(this._buttonWindow);
    };

    Scene_MobileInput.prototype.onKeyPress = function(key) {
        console.log("Key pressed:", key);
        this._inputValue += key;
        if (this._inputDisplayWindow) {
            this._inputDisplayWindow.setValue(this._inputValue);
        }
        this._keyboardWindow.activate();
    };

    Scene_MobileInput.prototype.onBackspace = function() {
        console.log("Backspace called, current value:", this._inputValue);
        if (this._inputValue.length > 0) {
            this._inputValue = this._inputValue.slice(0, -1);
            if (this._inputDisplayWindow) {
                this._inputDisplayWindow.setValue(this._inputValue);
            }
        }
        this._keyboardWindow.activate();
    };

    Scene_MobileInput.prototype.onSpace = function() {
        console.log("Space pressed");
        this._inputValue += " ";
        if (this._inputDisplayWindow) {
            this._inputDisplayWindow.setValue(this._inputValue);
        }
        this._keyboardWindow.activate();
    };

    Scene_MobileInput.prototype.onClear = function() {
        console.log("Clear pressed");
        this._inputValue = "";
        if (this._inputDisplayWindow) {
            this._inputDisplayWindow.setValue(this._inputValue);
        }
        this._keyboardWindow.activate();
    };

    Scene_MobileInput.prototype.onOk = function() {
        SoundManager.playOk();
        if (this._callback) {
            this._callback(this._inputValue);
        }
        SceneManager.pop();
    };

    Scene_MobileInput.prototype.onCancel = function() {
        SoundManager.playCancel();
        if (this._callback) {
            this._callback(null);
        }
        SceneManager.pop();
    };

    //=============================================================================
    // Input Display Window
    //=============================================================================
    function Window_MobileInputDisplay() {
        this.initialize(...arguments);
    }

    Window_MobileInputDisplay.prototype = Object.create(Window_Base.prototype);
    Window_MobileInputDisplay.prototype.constructor = Window_MobileInputDisplay;

    Window_MobileInputDisplay.prototype.initialize = function(rect, isPassword) {
        Window_Base.prototype.initialize.call(this, rect);
        this._isPassword = isPassword;
        this._value = "";
    };

    Window_MobileInputDisplay.prototype.setValue = function(value) {
        this._value = value;
        this.refresh();
    };

    Window_MobileInputDisplay.prototype.refresh = function() {
        this.contents.clear();
        const displayText = this._isPassword ? "*".repeat(this._value.length) : this._value;
        this.drawText(displayText, 0, 0, this.contentsWidth(), 'left');
    };

    //=============================================================================
    // Mobile Keyboard Window
    //=============================================================================
    function Window_MobileKeyboard() {
        this.initialize(...arguments);
    }

    Window_MobileKeyboard.prototype = Object.create(Window_Selectable.prototype);
    Window_MobileKeyboard.prototype.constructor = Window_MobileKeyboard;

    Window_MobileKeyboard.prototype.initialize = function(rect) {
        Window_Selectable.prototype.initialize.call(this, rect);
        this._mode = 'lowercase'; // 'lowercase', 'uppercase', 'numbers'
        this._keys = this.getKeys();
        this.refresh();
        this.select(0);
    };

    Window_MobileKeyboard.prototype.getKeys = function() {
        const lowercase = [
            ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
            ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
            ['z', 'x', 'c', 'v', 'b', 'n', 'm']
        ];
        
        const uppercase = [
            ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
            ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L'],
            ['Z', 'X', 'C', 'V', 'B', 'N', 'M']
        ];
        
        const numbers = [
            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
            ['-', '_', '@', '.', ',', '!', '?', '#', '$', '%'],
            ['(', ')', '[', ']', '/', '\\', '*', '+', '=', '&']
        ];
        
        return {
            lowercase: lowercase,
            uppercase: uppercase,
            numbers: numbers
        };
    };

    Window_MobileKeyboard.prototype.maxCols = function() {
        return 10;
    };

    Window_MobileKeyboard.prototype.maxItems = function() {
        const keys = this._keys[this._mode];
        let total = 0;
        for (let row of keys) {
            total += row.length;
        }
        return total + 4; // +4 for SPACE, CLEAR, BACKSPACE, MODE buttons
    };

    Window_MobileKeyboard.prototype.itemHeight = function() {
        return 44;
    };

    Window_MobileKeyboard.prototype.itemWidth = function() {
        return Math.floor((this.innerWidth - this.colSpacing() * 9) / 10);
    };

    Window_MobileKeyboard.prototype.getKeyAt = function(index) {
        const keys = this._keys[this._mode];
        let currentIndex = 0;
        
        // First, find all regular keys
        for (let row = 0; row < keys.length; row++) {
            for (let col = 0; col < keys[row].length; col++) {
                if (currentIndex === index) {
                    return keys[row][col];
                }
                currentIndex++;
            }
        }
        
        // Then special buttons in order
        const specialButtons = ['BACKSPACE', 'SPACE', 'CLEAR', 'MODE'];
        let specialIndex = index - currentIndex;
        
        if (specialIndex >= 0 && specialIndex < specialButtons.length) {
            return specialButtons[specialIndex];
        }
        
        return null;
    };

    Window_MobileKeyboard.prototype.drawItem = function(index) {
        const rect = this.itemRect(index);
        const key = this.getKeyAt(index);
        
        if (!key) return;
        
        let bgColor = 'rgba(50, 100, 200, 0.4)';
        let textAlign = 'center';
        let displayText = key;
        
        // Special handling for each button type
        if (key === 'BACKSPACE') {
            bgColor = 'rgba(200, 100, 50, 0.5)';
            displayText = '⬅ BACK';
        } else if (key === 'SPACE') {
            bgColor = 'rgba(50, 150, 100, 0.5)';
            displayText = 'SPACE';
        } else if (key === 'CLEAR') {
            bgColor = 'rgba(180, 50, 50, 0.5)';
            displayText = 'CLEAR';
        } else if (key === 'MODE') {
            bgColor = 'rgba(150, 100, 200, 0.5)';
            displayText = '🔄 ' + this._mode.toUpperCase().substring(0, 3);
        }
        
        // Draw button background
        this.contents.fillRect(rect.x, rect.y, rect.width, rect.height, bgColor);
        this.contents.strokeRect(rect.x, rect.y, rect.width, rect.height, 'white');
        
        // Draw key text
        this.changeTextColor(ColorManager.normalColor());
        this.drawText(displayText, rect.x, rect.y, rect.width, textAlign);
    };

    Window_MobileKeyboard.prototype.processOk = function() {
        const key = this.getKeyAt(this.index());
        
        if (key === 'BACKSPACE') {
            SoundManager.playOk();
            this.callHandler('backspace');
        } else if (key === 'MODE') {
            this.toggleMode();
        } else if (key === 'SPACE') {
            SoundManager.playOk();
            this.callHandler('space');
        } else if (key === 'CLEAR') {
            SoundManager.playOk();
            this.callHandler('clear');
        } else if (key) {
            SoundManager.playOk();
            this.callHandler('key', key);
        }
    };

    Window_MobileKeyboard.prototype.updateInputScroll = function() {
        // Override to prevent scrolling issues
    };

    Window_MobileKeyboard.prototype.toggleMode = function() {
        if (this._mode === 'lowercase') {
            this._mode = 'uppercase';
        } else if (this._mode === 'uppercase') {
            this._mode = 'numbers';
        } else {
            this._mode = 'lowercase';
        }
        this.refresh();
        SoundManager.playCursor();
    };

    Window_MobileKeyboard.prototype.itemRect = function(index) {
        const keys = this._keys[this._mode];
        let currentIndex = 0;
        let row = 0;
        let col = 0;
        
        // Find row and column for regular keys
        for (let r = 0; r < keys.length; r++) {
            for (let c = 0; c < keys[r].length; c++) {
                if (currentIndex === index) {
                    row = r;
                    col = c;
                    break;
                }
                currentIndex++;
            }
            if (currentIndex > index) break;
        }
        
        // Special buttons on row 4 (bottom row)
        if (index >= currentIndex) {
            row = keys.length; // Row 4
            const specialIndex = index - currentIndex;
            col = specialIndex * 2.5; // Spread them out
        }
        
        const itemWidth = this.itemWidth();
        const itemHeight = this.itemHeight();
        const colSpacing = this.colSpacing();
        const rowSpacing = this.rowSpacing();
        
        const x = col * (itemWidth + colSpacing);
        const y = row * (itemHeight + rowSpacing);
        
        return new Rectangle(x, y, itemWidth, itemHeight);
    };

    //=============================================================================
    // Button Window (OK/Cancel)
    //=============================================================================
    function Window_MobileInputButtons() {
        this.initialize(...arguments);
    }

    Window_MobileInputButtons.prototype = Object.create(Window_Command.prototype);
    Window_MobileInputButtons.prototype.constructor = Window_MobileInputButtons;

    Window_MobileInputButtons.prototype.makeCommandList = function() {
        this.addCommand("OK", 'ok');
        this.addCommand("Cancel", 'cancel');
    };

    Window_MobileInputButtons.prototype.maxCols = function() {
        return 2;
    };

    //=============================================================================
    // Global Mobile Input Function
    //=============================================================================
    window.mobilePrompt = function(message, defaultValue, callback, isPassword) {
        const scene = new Scene_MobileInput(message, defaultValue, callback, isPassword);
        SceneManager.push(scene);
    };

    // Override the openTextInput method in the student menu plugin
    const _Scene_Boot_start = Scene_Boot.prototype.start;
    Scene_Boot.prototype.start = function() {
        _Scene_Boot_start.call(this);
        
        setTimeout(() => {
            if (Window_LoginInput) {
                Window_LoginInput.prototype.openTextInput = function(message, defaultValue, callback, isPassword) {
                    this.deactivate();
                    window.mobilePrompt(message, defaultValue, (result) => {
                        callback(result);
                    }, isPassword);
                };
            }
            
            if (Window_StudentForm) {
                Window_StudentForm.prototype.openTextInput = function(fieldName, label, isPassword) {
                    this.deactivate();
                    const currentValue = this._formData[fieldName] || "";
                    window.mobilePrompt("Enter " + label.replace(":", ""), currentValue, (result) => {
                        if (result !== null) {
                            if (fieldName === "idNumber") {
                                if (!this.validateIdNumber(result)) {
                                    this.activate();
                                    return;
                                }
                            }
                            this._formData[fieldName] = result;
                            this.refresh();
                        }
                        this.activate();
                    }, isPassword);
                };
            }
            
            if (Window_ForgotPassword) {
                Window_ForgotPassword.prototype.openTextInput = function(fieldName, label) {
                    this.deactivate();
                    const currentValue = this._formData[fieldName] || "";
                    window.mobilePrompt("Enter " + label.replace(":", ""), currentValue, (result) => {
                        if (result !== null) {
                            if (result.trim() === "") {
                                alert("❌ ID Number is required!");
                                this.activate();
                                return;
                            }
                            if (!this.validateIdNumber(result.trim())) {
                                this.activate();
                                return;
                            }
                            this._formData[fieldName] = result.trim();
                        }
                        this.refresh();
                        this.activate();
                    });
                };
            }
            
            if (Window_EditAccount) {
                Window_EditAccount.prototype.openTextInput = function(fieldName, label, isPassword) {
                    this.deactivate();
                    const currentValue = this._formData[fieldName] || "";
                    window.mobilePrompt("Enter " + label.replace(":", ""), currentValue, (result) => {
                        if (result !== null) {
                            if (fieldName === "idNumber") {
                                if (result.trim() === "") {
                                    alert("❌ ID Number is required!");
                                    this.activate();
                                    return;
                                }
                                if (!this.validateIdNumber(result.trim())) {
                                    this.activate();
                                    return;
                                }
                            }
                            else if ((fieldName === "currentUsername" || fieldName === "currentPassword") && result.trim() === "") {
                                alert(`❌ ${label.replace(":", "")} is required!`);
                                this.activate();
                                return;
                            }
                            else if (fieldName === "newPassword" && result.trim() !== "" && result.trim().length < 6) {
                                alert("❌ New password must be at least 6 characters!");
                                this.activate();
                                return;
                            }
                            this._formData[fieldName] = result.trim();
                        }
                        this.refresh();
                        this.activate();
                    }, isPassword);
                };
            }
            
            console.log("✓ Mobile input system integrated with student menu");
        }, 500);
    };

    console.log("✓ Mobile input plugin loaded");

})();