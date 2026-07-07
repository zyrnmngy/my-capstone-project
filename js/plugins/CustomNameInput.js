/*:
 * @target MZ
 * @plugindesc Custom keyboard-style name input with shift toggle
 * @author YourName
 * @url https://filibustero-web-com
 * @help
 * ============================================================================
 * Introduction
 * ============================================================================
 * This plugin creates a keyboard-style name input interface with a shift
 * toggle instead of displaying both uppercase and lowercase letters.
 * 
 * ============================================================================
 * How to Use
 * ============================================================================
 * 1. Add this plugin to your project's js/plugins folder
 * 2. Enable it in the Plugin Manager
 * 3. Use the Plugin Command "Open Name Input" in your events
 * 4. Set the Actor ID and Max Length as needed
 * 
 * ============================================================================
 * Plugin Commands
 * ============================================================================
 * 
 * @command openNameInput
 * @text Open Name Input
 * @desc Opens the custom keyboard-style name input window
 * 
 * @arg actorId
 * @text Actor ID
 * @type actor
 * @default 1
 * @desc Select which actor's name will be changed
 * 
 * @arg maxLength
 * @text Max Length
 * @type number
 * @min 1
 * @max 16
 * @default 8
 * @desc Maximum number of characters allowed (1-16)
 */

(() => {
    const pluginName = "CustomNameInput";

    PluginManager.registerCommand(pluginName, "openNameInput", args => {
        const actorId = Number(args.actorId) || 1;
        const maxLength = Number(args.maxLength) || 8;
        SceneManager.push(Scene_CustomNameInput);
        SceneManager.prepareNextScene(actorId, maxLength);
    });

    // Custom Scene for Name Input
    class Scene_CustomNameInput extends Scene_MenuBase {
        prepare(actorId, maxLength) {
            this._actorId = actorId;
            this._maxLength = maxLength;
            this._actor = $gameActors.actor(actorId);
        }

        create() {
            super.create();
            this._editWindow = new Window_CustomNameEdit(this._actor, this._maxLength);
            this.addWindow(this._editWindow);
            this._inputWindow = new Window_CustomNameInput(this._editWindow);
            this.addWindow(this._inputWindow);
            this._inputWindow.setHandler("ok", this.onInputOk.bind(this));
            
            // Add keyboard event listener
            this._keyboardHandler = this.onKeyPress.bind(this);
            document.addEventListener('keydown', this._keyboardHandler);
            
            // Add mobile input support
            this.createMobileInput();
        }

        createMobileInput() {
            // Create hidden input field for mobile keyboards
            this._mobileInput = document.createElement('input');
            this._mobileInput.type = 'text';
            this._mobileInput.style.position = 'absolute';
            this._mobileInput.style.opacity = '0';
            this._mobileInput.style.pointerEvents = 'none';
            this._mobileInput.style.left = '-9999px';
            document.body.appendChild(this._mobileInput);
            
            this._mobileInput.addEventListener('input', (e) => {
                const value = e.target.value;
                if (value) {
                    const char = value[value.length - 1];
                    this._editWindow.add(char);
                    this._mobileInput.value = '';
                }
            });
            
            // Focus on mobile input for touch devices
            if ('ontouchstart' in window) {
                this._mobileInput.focus();
            }
        }

        onKeyPress(event) {
            if (!this._inputWindow.active) return;
            
            const key = event.key;
            
            // Handle letter and number keys
            if (key.length === 1 && !event.ctrlKey && !event.altKey) {
                event.preventDefault();
                this._editWindow.add(key);
                this._inputWindow.playOkSound();
                return;
            }
            
            // Handle special keys
            switch(key) {
                case 'Backspace':
                    event.preventDefault();
                    if (this._editWindow.back()) {
                        this._inputWindow.playOkSound();
                    } else {
                        this._inputWindow.playBuzzerSound();
                    }
                    break;
                case 'Enter':
                    event.preventDefault();
                    this.onInputOk();
                    break;
                case 'Escape':
                    event.preventDefault();
                    if (this._editWindow.name().length > 0) {
                        this.onInputOk();
                    }
                    break;
                case 'Shift':
                    // Shift is handled automatically by the browser
                    break;
            }
        }

        terminate() {
            super.terminate();
            // Remove keyboard event listener
            document.removeEventListener('keydown', this._keyboardHandler);
            // Remove mobile input field
            if (this._mobileInput && this._mobileInput.parentNode) {
                this._mobileInput.parentNode.removeChild(this._mobileInput);
            }
        }

        onInputOk() {
            this._actor.setName(this._editWindow.name());
            SceneManager.pop();
        }
    }

    // Edit Window (displays current name)
    class Window_CustomNameEdit extends Window_StatusBase {
        constructor(actor, maxLength) {
            const rect = Window_CustomNameEdit.prototype.windowRect();
            super(rect);
            this._actor = actor;
            this._maxLength = maxLength;
            this._name = actor.name().slice(0, this._maxLength);
            this._index = this._name.length;
            this.refresh();
        }

        windowRect() {
            const ww = 600;
            const wh = this.fittingHeight(4);
            const wx = (Graphics.boxWidth - ww) / 2;
            const wy = (Graphics.boxHeight - (wh + 9 * 48)) / 2;
            return new Rectangle(wx, wy, ww, wh);
        }

        name() {
            return this._name;
        }

        add(ch) {
            if (this._index < this._maxLength) {
                this._name += ch;
                this._index++;
                this.refresh();
                return true;
            }
            return false;
        }

        back() {
            if (this._index > 0) {
                this._index--;
                this._name = this._name.slice(0, this._index);
                this.refresh();
                return true;
            }
            return false;
        }

        refresh() {
            this.contents.clear();
            this.drawActorFace(this._actor, 0, 0);
            const x = 168;
            const y = 36;
            const width = this.contentsWidth() - x;
            this.drawTextEx(this._name, x, y, width);
            const underlineX = x;
            const underlineY = y + this.lineHeight() + 8;
            const underlineWidth = this.textWidth("M") * this._maxLength;
            const color = ColorManager.normalColor();
            this.contents.fillRect(underlineX, underlineY, underlineWidth, 2, color);
        }
    }

    // Input Window (keyboard layout)
    class Window_CustomNameInput extends Window_Selectable {
        constructor(editWindow) {
            const rect = Window_CustomNameInput.prototype.windowRect();
            super(rect);
            this._editWindow = editWindow;
            this._shift = false;
            this.refresh();
            this.select(0);
            this.activate();
        }

        windowRect() {
            const ww = 600;
            const wh = this.fittingHeight(9);
            const wx = (Graphics.boxWidth - ww) / 2;
            const wy = (Graphics.boxHeight - wh) / 2 + 80;
            return new Rectangle(wx, wy, ww, wh);
        }

        maxCols() {
            return 10;
        }

        maxItems() {
            return this._keys ? this._keys.length : 0;
        }

        itemRect(index) {
            const rect = super.itemRect(index);
            rect.x += 8;
            rect.y += 8;
            return rect;
        }

        refresh() {
            this._keys = this.getKeyLayout();
            this.contents.clear();
            this.drawAllItems();
        }

        getKeyLayout() {
            const lower = [
                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
                'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't',
                'u', 'v', 'w', 'x', 'y', 'z', '1', '2', '3', '4',
                '5', '6', '7', '8', '9', '0', '-', '_', ' ', 'BACK',
                'SHIFT', 'OK'
            ];
            
            const upper = [
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
                'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
                'U', 'V', 'W', 'X', 'Y', 'Z', '!', '@', '#', '$',
                '%', '^', '&', '*', '(', ')', '+', '=', ' ', 'BACK',
                'SHIFT', 'OK'
            ];
            
            return this._shift ? upper : lower;
        }

        drawItem(index) {
            const rect = this.itemLineRect(index);
            const key = this._keys[index];
            let text = key;
            
            if (key === 'SHIFT') {
                text = this._shift ? '⬆ SHIFT' : '⬇ shift';
            } else if (key === 'BACK') {
                text = '⌫ Back';
            } else if (key === 'OK') {
                text = '✓ OK';
            }
            
            this.drawText(text, rect.x, rect.y, rect.width, 'center');
        }

        processOk() {
            const key = this._keys[this.index()];
            
            if (key === 'OK') {
                this.callOkHandler();
            } else if (key === 'BACK') {
                this.processBack();
            } else if (key === 'SHIFT') {
                this.toggleShift();
            } else {
                if (this._editWindow.add(key)) {
                    this.playOkSound();
                } else {
                    this.playBuzzerSound();
                }
            }
        }

        processBack() {
            if (this._editWindow.back()) {
                this.playOkSound();
            } else {
                this.playBuzzerSound();
            }
        }

        toggleShift() {
            this._shift = !this._shift;
            this.refresh();
            this.playOkSound();
        }

        cursorPagedown() {
            // Prevent page scrolling
        }

        cursorPageup() {
            // Prevent page scrolling
        }
    }

    window.Scene_CustomNameInput = Scene_CustomNameInput;
})();