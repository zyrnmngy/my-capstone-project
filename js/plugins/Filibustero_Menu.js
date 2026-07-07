//=============================================================================
// Filibustero_Student_Menu.js (STUDENT ONLY VERSION) - FIXED
//=============================================================================

/*:
 * @target MZ
 * @plugindesc [v1.0.5] Filibustero Student Menu System (Fixed)
 * @author YourName
 * @version 1.0.5
 * @description Complete student authentication menu system for Filibustero project - Fixed version
 * 
 * @help Filibustero_Student_Menu.js
 * 
 * This plugin provides a complete student authentication system with login, registration, and forgot password.
 * Make sure this plugin is loaded LAST in the plugin manager!
 */

(() => {
    'use strict';

    // Global variables for user session
    window.FilibusteroAuth = {
        currentUser: null,
        sessionToken: null,
        isLoggedIn: false,
        
        logout: function() {
            console.log("Logging out user:", this.currentUser);
            
            if (window.setCurrentUserId) {
                window.setCurrentUserId(null);
                console.log('✓ Progress plugin user ID cleared');
            }
            
            window.currentUser = null;
            this.currentUser = null;
            this.sessionToken = null;
            this.isLoggedIn = false;
            
            console.log("User logged out successfully");
        }
    };

    //=============================================================================
    // Navigation Bar Sprite
    //=============================================================================
    function Sprite_NavigationBar() {
        this.initialize(...arguments);
    }

    Sprite_NavigationBar.prototype = Object.create(Sprite.prototype);
    Sprite_NavigationBar.prototype.constructor = Sprite_NavigationBar;

    Sprite_NavigationBar.prototype.initialize = function() {
        Sprite.prototype.initialize.call(this);
        this.createBitmap();
        this.createButtons();
        this._isExpanded = false;
        this._targetX = Graphics.width - 60;
        this.x = this._targetX;
        this.y = 10;
        this.z = 1000;
        this.visible = true;
        this.opacity = 255;
        this.setupInteraction();
        console.log("✓ Navigation bar created at:", this.x, this.y);
    };

    Sprite_NavigationBar.prototype.createBitmap = function() {
        this.bitmap = new Bitmap(250, 50);
        this._buttons = [];
    };

    Sprite_NavigationBar.prototype.createButtons = function() {
        // Hamburger button
        this._hamburgerButton = this.createButton(0, 0, 50, 50, "☰", this.toggleMenu.bind(this));
        this.addChild(this._hamburgerButton);

        // Menu container
        this._menuContainer = new Sprite();
        this._menuContainer.bitmap = new Bitmap(200, 200);
        this._menuContainer.x = -200;
        this._menuContainer.y = 0;
        this._menuContainer.visible = false;
        this.addChild(this._menuContainer);

        // Create menu items
        this.createMenuItem(0, "Login", this.onLoginClick.bind(this));
        this.createMenuItem(1, "Register", this.onRegisterClick.bind(this));
        this.createMenuItem(2, "Forgot Password", this.onForgotPasswordClick.bind(this));
        this.createMenuItem(3, "Edit Account", this.onEditAccountClick.bind(this));
    };

    Sprite_NavigationBar.prototype.createButton = function(x, y, width, height, text, callback) {
        const button = new Sprite();
        button.bitmap = new Bitmap(width, height);
        button.x = x;
        button.y = y;
        
        button.bitmap.fillRect(0, 0, width, height, 'rgba(0, 0, 0, 0.7)');
        button.bitmap.strokeRect(0, 0, width, height, 'white');
        
        button.bitmap.fontSize = 24;
        button.bitmap.textColor = 'white';
        button.bitmap.drawText(text, 0, 0, width, height, 'center');
        
        button._callback = callback;
        button._isHovered = false;
        
        return button;
    };

    Sprite_NavigationBar.prototype.createMenuItem = function(index, text, callback) {
        const button = new Sprite();
        const width = 190;
        const height = 40;
        const y = 5 + (index * 45);
        
        button.bitmap = new Bitmap(width, height);
        button.x = 5;
        button.y = y;
        
        button.bitmap.fillRect(0, 0, width, height, 'rgba(50, 50, 150, 0.8)');
        button.bitmap.strokeRect(0, 0, width, height, 'white');
        
        button.bitmap.fontSize = 18;
        button.bitmap.textColor = 'white';
        button.bitmap.drawText(text, 0, 0, width, height, 'center');
        
        button._callback = callback;
        button._isHovered = false;
        button._normalColor = 'rgba(50, 50, 150, 0.8)';
        button._hoverColor = 'rgba(70, 70, 200, 0.9)';
        
        this._menuContainer.addChild(button);
        this._buttons.push(button);
    };

    Sprite_NavigationBar.prototype.setupInteraction = function() {
        this._touchStarted = false;
        this._lastTouchTime = 0;
    };

    Sprite_NavigationBar.prototype.toggleMenu = function() {
        SoundManager.playCursor();
        this._isExpanded = !this._isExpanded;
        this._menuContainer.visible = this._isExpanded;
    };

    Sprite_NavigationBar.prototype.update = function() {
        Sprite.prototype.update.call(this);
        this.updateInteraction();
        this.updateButtonStates();
    };

    Sprite_NavigationBar.prototype.updateInteraction = function() {
        if (TouchInput.isTriggered()) {
            const x = TouchInput.x;
            const y = TouchInput.y;
            
            if (this.isPointInButton(x, y, this._hamburgerButton)) {
                this._hamburgerButton._callback();
                return;
            }
            
            if (this._isExpanded && this._menuContainer.visible) {
                for (const button of this._buttons) {
                    if (this.isPointInMenuButton(x, y, button)) {
                        button._callback();
                        this.toggleMenu();
                        return;
                    }
                }
                
                if (!this.isPointInMenu(x, y)) {
                    this.toggleMenu();
                }
            }
        }
    };

    Sprite_NavigationBar.prototype.updateButtonStates = function() {
        const x = TouchInput.x;
        const y = TouchInput.y;
        
        const hamburgerHovered = this.isPointInButton(x, y, this._hamburgerButton);
        if (hamburgerHovered !== this._hamburgerButton._isHovered) {
            this._hamburgerButton._isHovered = hamburgerHovered;
            this.redrawHamburgerButton();
        }
        
        if (this._isExpanded) {
            for (const button of this._buttons) {
                const hovered = this.isPointInMenuButton(x, y, button);
                if (hovered !== button._isHovered) {
                    button._isHovered = hovered;
                    this.redrawMenuButton(button);
                }
            }
        }
    };

    Sprite_NavigationBar.prototype.redrawHamburgerButton = function() {
        const button = this._hamburgerButton;
        button.bitmap.clear();
        
        const color = button._isHovered ? 'rgba(50, 50, 50, 0.9)' : 'rgba(0, 0, 0, 0.7)';
        button.bitmap.fillRect(0, 0, 50, 50, color);
        button.bitmap.strokeRect(0, 0, 50, 50, 'white');
        
        button.bitmap.fontSize = 24;
        button.bitmap.textColor = 'white';
        button.bitmap.drawText("☰", 0, 0, 50, 50, 'center');
    };

    Sprite_NavigationBar.prototype.redrawMenuButton = function(button) {
        const width = 190;
        const height = 40;
        button.bitmap.clear();
        
        const color = button._isHovered ? button._hoverColor : button._normalColor;
        button.bitmap.fillRect(0, 0, width, height, color);
        button.bitmap.strokeRect(0, 0, width, height, 'white');
        
        button.bitmap.fontSize = 18;
        button.bitmap.textColor = 'white';
        
        const texts = ["Login", "Register", "Forgot Password", "Edit Account"];
        const index = this._buttons.indexOf(button);
        button.bitmap.drawText(texts[index], 0, 0, width, height, 'center');
    };

    Sprite_NavigationBar.prototype.isPointInButton = function(x, y, button) {
        const globalX = this.x + button.x;
        const globalY = this.y + button.y;
        
        return x >= globalX && x <= globalX + button.bitmap.width &&
               y >= globalY && y <= globalY + button.bitmap.height;
    };

    Sprite_NavigationBar.prototype.isPointInMenuButton = function(x, y, button) {
        const globalX = this.x + this._menuContainer.x + button.x;
        const globalY = this.y + this._menuContainer.y + button.y;
        
        return x >= globalX && x <= globalX + button.bitmap.width &&
               y >= globalY && y <= globalY + button.bitmap.height;
    };

    Sprite_NavigationBar.prototype.isPointInMenu = function(x, y) {
        const menuX = this.x + this._menuContainer.x;
        const menuY = this.y + this._menuContainer.y;
        
        return x >= menuX && x <= menuX + 200 &&
               y >= menuY && y <= menuY + 200;
    };

    // Button callbacks
    Sprite_NavigationBar.prototype.onLoginClick = function() {
        console.log("Login clicked");
        SoundManager.playOk();
        SceneManager.push(Scene_Login);
    };

    Sprite_NavigationBar.prototype.onRegisterClick = function() {
        console.log("Register clicked");
        SoundManager.playOk();
        SceneManager.push(Scene_Register);
    };

    Sprite_NavigationBar.prototype.onForgotPasswordClick = function() {
        console.log("Forgot Password clicked");
        SoundManager.playOk();
        SceneManager.push(Scene_ForgotPassword);
    };

    Sprite_NavigationBar.prototype.onEditAccountClick = function() {
        console.log("Edit Account clicked");
        SoundManager.playOk();
        SceneManager.push(Scene_EditAccount);
    };

    // Store original Scene_Title for proper restoration
    const _originalSceneTitle = Scene_Title;

    // FORCE OVERRIDE - Wait for all other plugins to load first
    setTimeout(() => {
        // Force complete override of Scene_Title
        window.Scene_Title = function() {
            this.initialize(...arguments);
        };

        Scene_Title.prototype = Object.create(Scene_Base.prototype);
        Scene_Title.prototype.constructor = Scene_Title;

        Scene_Title.prototype.initialize = function() {
            Scene_Base.prototype.initialize.call(this);
        };

        Scene_Title.prototype.create = function() {
            Scene_Base.prototype.create.call(this);
            this.createBackground();
            this.createForeground();
            this.createWindowLayer();
            this.createCustomCommandWindow();
            this.createNavigationBar(); // ADD NAVIGATION BAR
        };

        Scene_Title.prototype.createNavigationBar = function() {
            if (!this._navigationBar) {
                this._navigationBar = new Sprite_NavigationBar();
                this.addChild(this._navigationBar);
                console.log("✓ Navigation bar added to title screen");
            }
        };

        Scene_Title.prototype.start = function() {
            Scene_Base.prototype.start.call(this);
            SceneManager.clearStack();
            this.playTitleMusic();
            if (this._commandWindow) {
                this._commandWindow.select(0);
                this._commandWindow.activate();
            }
        };

        Scene_Title.prototype.update = function() {
            Scene_Base.prototype.update.call(this);
            if (Input.isTriggered('cancel') || Input.isTriggered('escape')) {
                SoundManager.playCancel();
            }
        };

        Scene_Title.prototype.isBusy = function() {
            return Scene_Base.prototype.isBusy.call(this);
        };

        Scene_Title.prototype.terminate = function() {
            Scene_Base.prototype.terminate.call(this);
            SceneManager.snapForBackground();
        };

        Scene_Title.prototype.createBackground = function() {
            this._backSprite1 = new Sprite(ImageManager.loadTitle1($dataSystem.title1Name));
            this._backSprite2 = new Sprite(ImageManager.loadTitle2($dataSystem.title2Name));
            this.addChild(this._backSprite1);
            this.addChild(this._backSprite2);
        };

        Scene_Title.prototype.createForeground = function() {
            this._gameTitleSprite = new Sprite(new Bitmap(Graphics.width, Graphics.height));
            this.addChild(this._gameTitleSprite);
            if ($dataSystem.optDrawTitle) {
                this.drawGameTitle();
            }
        };

        Scene_Title.prototype.drawGameTitle = function() {
            const x = 20;
            const y = Graphics.height / 4;
            const maxWidth = Graphics.width - x * 2;
            const text = $dataSystem.gameTitle;
            const bitmap = this._gameTitleSprite.bitmap;
            bitmap.fontFace = $gameSystem.mainFontFace();
            bitmap.fontSize = 72;
            bitmap.outlineColor = "black";
            bitmap.outlineWidth = 8;
            bitmap.drawText(text, x, y, maxWidth, 48, "center");
        };

        Scene_Title.prototype.createCustomCommandWindow = function() {
            const rect = this.commandWindowRect();
            this._commandWindow = new Window_FilibusteroTitle(rect);
            this._commandWindow.setHandler('login', this.commandLogin.bind(this));
            this._commandWindow.setHandler('register', this.commandRegister.bind(this));
            this._commandWindow.setHandler('forgot', this.commandForgotPassword.bind(this));
            this._commandWindow.setHandler('editaccount', this.commandEditAccount.bind(this));
            this.addWindow(this._commandWindow);
        };

        Scene_Title.prototype.commandWindowRect = function() {
            const offsetX = $dataSystem.titleCommandWindow.offsetX;
            const offsetY = $dataSystem.titleCommandWindow.offsetY;
            const ww = this.mainCommandWidth();
            const wh = this.calcWindowHeight(4, true);
            const wx = (Graphics.boxWidth - ww) / 2 + offsetX;
            const wy = Graphics.boxHeight - wh - 96 + offsetY;
            return new Rectangle(wx, wy, ww, wh);
        };

        Scene_Title.prototype.mainCommandWidth = function() {
            return 300;
        };

        Scene_Title.prototype.calcWindowHeight = function(numLines, selectable) {
            if (selectable) {
                return Window_Selectable.prototype.fittingHeight(numLines);
            } else {
                return Window_Base.prototype.fittingHeight(numLines);
            }
        };

        Scene_Title.prototype.commandLogin = function() {
            this._commandWindow.deactivate();
            this._commandWindow.close();
            SceneManager.push(Scene_Login);
        };

        Scene_Title.prototype.commandRegister = function() {
            this._commandWindow.deactivate();
            this._commandWindow.close();
            SceneManager.push(Scene_Register);
        };

        Scene_Title.prototype.commandForgotPassword = function() {
            this._commandWindow.deactivate();
            this._commandWindow.close();
            SceneManager.push(Scene_ForgotPassword);
        };

        Scene_Title.prototype.commandEditAccount = function() {
            console.log("Edit Account command triggered");
            this._commandWindow.deactivate();
            this._commandWindow.close();
            SceneManager.push(Scene_EditAccount);
        };

        Scene_Title.prototype.playTitleMusic = function() {
            AudioManager.playBgm($dataSystem.titleBgm);
            AudioManager.stopBgs();
            AudioManager.stopMe();
        };

        console.log("✓ Filibustero Student Menu System with Navigation Bar loaded");
        
    }, 100);

    // Custom Title Command Window
    function Window_FilibusteroTitle() {
        this.initialize(...arguments);
    }

    Window_FilibusteroTitle.prototype = Object.create(Window_Command.prototype);
    Window_FilibusteroTitle.prototype.constructor = Window_FilibusteroTitle;

    Window_FilibusteroTitle.prototype.makeCommandList = function() {
        this.addCommand("LOGIN", 'login');
        this.addCommand("REGISTER ACCOUNT", 'register');
        this.addCommand("FORGOT PASSWORD", 'forgot');
        this.addCommand("EDIT USER ACCOUNT", 'editaccount'); 
    };

    // =============================================
    // LOGIN SCENE
    // =============================================
    function Scene_Login() {
        this.initialize(...arguments);
    }

    Scene_Login.prototype = Object.create(Scene_MenuBase.prototype);
    Scene_Login.prototype.constructor = Scene_Login;

    Scene_Login.prototype.create = function() {
        Scene_MenuBase.prototype.create.call(this);
        this.createHelpWindow();
        this.createLoginWindow();
    };

    Scene_Login.prototype.start = function() {
        Scene_MenuBase.prototype.start.call(this);
        // Properly activate the login window
        if (this._loginWindow) {
            this._loginWindow.select(0);
            this._loginWindow.activate();
        }
    };

    Scene_Login.prototype.update = function() {
        Scene_MenuBase.prototype.update.call(this);
        // Handle cancel to go back
        if (Input.isTriggered('cancel') && this._loginWindow && this._loginWindow.active) {
            SoundManager.playCancel();
            SceneManager.pop();
        }
    };

    Scene_Login.prototype.createHelpWindow = function() {
        const rect = this.helpWindowRect();
        this._helpWindow = new Window_Help(rect);
        this._helpWindow.setText("Enter your username and password to login");
        this.addWindow(this._helpWindow);
    };

    Scene_Login.prototype.helpWindowRect = function() {
        const wx = 0;
        const wy = 0;
        const ww = Graphics.boxWidth;
        const wh = 72;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_Login.prototype.createLoginWindow = function() {
        const rect = new Rectangle(100, 200, Graphics.boxWidth - 200, 230);
        this._loginWindow = new Window_LoginInput(rect);
        this.addWindow(this._loginWindow);
    };

    // Login Input Window
    function Window_LoginInput() {
        this.initialize(...arguments);
    }

    Window_LoginInput.prototype = Object.create(Window_Selectable.prototype);
    Window_LoginInput.prototype.constructor = Window_LoginInput;

    Window_LoginInput.prototype.initialize = function(rect) {
        Window_Selectable.prototype.initialize.call(this, rect);
        this._username = "";
        this._password = "";
        this._maxItems = 3;
        this._isProcessing = false;
        this.refresh();
        this.select(0);
    };

    Window_LoginInput.prototype.maxItems = function() {
        return this._maxItems;
    };

    Window_LoginInput.prototype.itemHeight = function() {
        return 60;
    };

    Window_LoginInput.prototype.drawItem = function(index) {
        const rect = this.itemRect(index);
        this.contents.clearRect(rect.x, rect.y, rect.width, rect.height);
        if (index === 0) {
            this.drawText("Username:", rect.x, rect.y, 120);
            const usernameText = this._username || "Click to enter...";
            this.drawText(usernameText, rect.x + 130, rect.y, rect.width - 130);
        } else if (index === 1) {
            this.drawText("Password:", rect.x, rect.y, 120);
            const passwordText = this._password ? "*".repeat(this._password.length) : "Click to enter...";
            this.drawText(passwordText, rect.x + 130, rect.y, rect.width - 130);
        } else if (index === 2) {
            this.drawText("LOGIN", rect.x, rect.y, rect.width, 'center');
        }
    };

    Window_LoginInput.prototype.refresh = function() {
        this.contents.clear();
        for (let i = 0; i < this.maxItems(); i++) {
            this.drawItem(i);
        }
    };

    Window_LoginInput.prototype.update = function() {
        Window_Selectable.prototype.update.call(this);
        // Only process input if this window is active and not processing
        if (this.active && !this._isProcessing) {
            if (Input.isTriggered('ok')) {
                this.processOk();
            } else if (Input.isTriggered('cancel')) {
                SoundManager.playCancel();
                SceneManager.goto(Scene_Title);
            }
        }
    };

    Window_LoginInput.prototype.processOk = function() {
        if (this._isProcessing) return; // Prevent multiple clicks
        
        const index = this.index();
        SoundManager.playOk();
        
        if (index === 0) {
            this.openTextInput("Enter Username:", this._username, (result) => {
                if (result !== null && result.trim() !== "") {
                    this._username = result.trim();
                    this.refresh();
                }
                this.activate();
            });
        } else if (index === 1) {
            this.openTextInput("Enter Password:", this._password, (result) => {
                if (result !== null && result.trim() !== "") {
                    this._password = result.trim();
                    this.refresh();
                }
                this.activate();
            }, true);
        } else if (index === 2) {
            this.processLogin();
        }
    };

    Window_LoginInput.prototype.openTextInput = function(message, defaultValue, callback, isPassword = false) {
        // Deactivate this window during text input
        this.deactivate();
        
        setTimeout(() => {
            const result = prompt(message, defaultValue || "");
            callback(result);
        }, 100);
    };

    Window_LoginInput.prototype.processLogin = function() {
        console.log("=== LOGIN VALIDATION ===");
        
        // Clear any previous messages
        $gameMessage.clear();
        
        // Validate inputs first
        const errors = [];
        
        if (!this._username || this._username.trim() === "") {
            errors.push("• Username is required");
        }
        
        if (!this._password || this._password.trim() === "") {
            errors.push("• Password is required");
        }
        
        // Show validation errors if any
        if (errors.length > 0) {
            const errorMessage = [
                "\\C[2]Login Errors:\\C[0]",
                ...errors,
                "",
                "Press OK to continue..."
            ].join("\n");
            
            $dataSystem.optConfirmText = errorMessage;
            SceneManager.push(Scene_ConfirmContinue);
            return;
        }
        
        // If validation passes, proceed with login
        this.deactivate();
        this._isProcessing = true;
        this.refresh();
        this.performLogin(this._username.trim(), this._password.trim());
    };

    // FIXED: Enhanced error handling for login function
    Window_LoginInput.prototype.performLogin = function(username, password) {
        console.log("=== LOGIN ATTEMPT ===");
        console.log("Username:", username);
        console.log("Password length:", password.length);
        
        $gameMessage.add("Connecting to server...");

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'https://filibustero-web.com/php/auth.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        const self = this;
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log("=== SERVER RESPONSE DEBUG ===");
                console.log("Status:", xhr.status);
                console.log("Status Text:", xhr.statusText);
                console.log("Response Headers:", xhr.getAllResponseHeaders());
                console.log("Raw Response Text:", xhr.responseText);
                console.log("Response Length:", xhr.responseText.length);
                
                // Reset processing state
                self._isProcessing = false;
                self.refresh();
                
                if (xhr.status === 200) {
                    // Check if response is empty
                    if (!xhr.responseText || xhr.responseText.trim() === '') {
                        console.error("Empty response from server");
                        self.showErrorMessage([
                            "\\C[2]Server Error\\C[0]",
                            "• Server returned empty response",
                            "• Check PHP error logs",
                            "",
                            "Press OK to try again..."
                        ]);
                        return;
                    }
                    
                    // Check if response starts with HTML (indicates PHP error)
                    if (xhr.responseText.trim().startsWith('<')) {
                        console.error("HTML response detected (likely PHP error):", xhr.responseText.substring(0, 200));
                        self.showErrorMessage([
                            "\\C[2]Server Error\\C[0]",
                            "• Server returned HTML instead of JSON",
                            "• Check PHP error logs for details",
                            "• Response preview: " + xhr.responseText.substring(0, 50) + "...",
                            "",
                            "Press OK to try again..."
                        ]);
                        return;
                    }
                    
                    try {
                        console.log("Attempting to parse JSON...");
                        const response = JSON.parse(xhr.responseText);
                        console.log("=== PARSED SERVER RESPONSE ===", response);
                        
                        if (response.success) {
                            // Handle successful login
                            self.handleSuccessfulLogin(response);
                        } else {
                            // Server-side validation failed
                            console.log("Login failed with error:", response.error);
                            self.handleLoginError(response);
                        }
                    } catch (e) {
                        console.error("=== JSON PARSE ERROR ===");
                        console.error("Parse error:", e);
                        console.error("Raw response (first 500 chars):", xhr.responseText.substring(0, 500));
                        console.error("Response contains HTML:", xhr.responseText.includes('<html>'));
                        
                        // Try to extract meaningful error from PHP error response
                        let errorPreview = xhr.responseText.substring(0, 100).replace(/<[^>]*>/g, '');
                        
                        self.showErrorMessage([
                            "\\C[2]JSON Parse Error\\C[0]",
                            "• Server response is not valid JSON",
                            "• This usually indicates a PHP error",
                            "• Error preview: " + errorPreview,
                            "",
                            "Check browser console for full response",
                            "Press OK to try again..."
                        ]);
                    }
                } else {
                    // HTTP error
                    console.error("HTTP Error:", xhr.status, xhr.statusText);
                    self.showErrorMessage([
                        "\\C[2]Connection Error\\C[0]",
                        "• HTTP " + xhr.status + ": " + xhr.statusText,
                        "• Cannot connect to server",
                        "• Please check if the server is running",
                        "",
                        "Press OK to try again..."
                    ]);
                }
            }
        };
        
        
        xhr.onerror = function() {
            console.error("XHR Error:", xhr.statusText);
            self._isProcessing = false;
            self.refresh();
            self.showErrorMessage([
                "\\C[2]Network Error\\C[0]",
                "• Cannot connect to server",
                "• Please check your internet connection",
                "• Make sure the server is running",
                "",
                "Press OK to try again..."
            ]);
        };
        
        xhr.timeout = 15000; // 15 second timeout
        xhr.ontimeout = function() {
            console.error("Request timed out");
            self._isProcessing = false;
            self.refresh();
            self.showErrorMessage([
                "\\C[2]Timeout Error\\C[0]",
                "• Server is taking too long to respond",
                "• Please try again",
                "",
                "Press OK to try again..."
            ]);
        };
        
        const params = `action=login&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`;
        console.log("Sending params:", params);
        console.log("Request URL:", xhr.responseURL || 'https://filibustero-web.com/php/auth.php');
        xhr.send(params);
    };

    // Handle successful login for students only
   // Handle successful login for students only
    Window_LoginInput.prototype.handleSuccessfulLogin = function(response) {
        console.log("=== SUCCESSFUL LOGIN ===");
        console.log("User data:", response.user);
        console.log("Redirect to:", response.redirect_to);
        
        // ============================================================
        // CRITICAL: Store user ID for Cloud Save System
        // ============================================================
        const userId = response.user.id;
        console.log("🔐 STORING USER ID FOR CLOUD SAVE:", userId);
        
        // Store in global variable (cloud save checks this first)
        window.gameUserId = userId;
        console.log("✓ Stored in window.gameUserId:", window.gameUserId);
        
        // Store in localStorage as persistent backup
        try {
            localStorage.setItem('gameUserId', userId.toString());
            console.log("✓ Stored in localStorage.gameUserId:", localStorage.getItem('gameUserId'));
        } catch (e) {
            console.warn("⚠️ localStorage not available:", e);
        }
        
        // ============================================================
        
        // Store user data in auth system - ALIGNED WITH auth.php structure
        window.FilibusteroAuth = {
            currentUser: response.user,
            sessionToken: response.token || null,
            isLoggedIn: true,
            logout: function() {
                console.log("Logging out user:", this.currentUser);
                
                // Clear cloud save user ID
                window.gameUserId = null;
                try {
                    localStorage.removeItem('gameUserId');
                } catch (e) {
                    console.warn("Could not clear localStorage");
                }
                
                // Clear progress plugin user ID
                if (window.setCurrentUserId) {
                    window.setCurrentUserId(null);
                    console.log('✓ Progress plugin user ID cleared');
                }
                
                // Clear global user data
                window.currentUser = null;
                
                // Clear auth data
                this.currentUser = null;
                this.sessionToken = null;
                this.isLoggedIn = false;
                
                console.log("User logged out successfully");
            }
        };
        
        // Set up user ID for progress plugin
        if (response.user && response.user.id) {
            console.log('Setting up user ID for progress plugin:', response.user.id);
            
            // Set the user ID for the progress plugin
            if (window.setCurrentUserId) {
                window.setCurrentUserId(response.user.id.toString());
                console.log('✓ User ID set for progress plugin:', response.user.id);
            } else {
                console.warn('⚠️ Progress plugin not loaded or setCurrentUserId not available');
            }
            // NEW: Set the ID NUMBER for the rankings plugin (it needs this)
            if (window.setCurrentUserIdNumber) {
                window.setCurrentUserIdNumber(response.user.id_number);
                console.log('✓ User ID Number set for rankings plugin:', response.user.id_number);
            } else {
                console.warn('⚠️ Rankings plugin not loaded or setCurrentUserIdNumber not available');
            }
    
            // Store globally for other systems
            window.currentUser = response.user;
        }
        
        // Store basic user data in game variables (ALIGNED WITH auth.php)
        $gameVariables.setValue(10, response.user.id); // User ID
        $gameVariables.setValue(11, response.user.username); // Username
        $gameVariables.setValue(12, response.user.full_name); // Full name
        $gameVariables.setValue(13, response.user.user_type); // User type
        
        // Store user progress data if available (ALIGNED WITH auth.php progress sync)
        if (response.user.progress) {
            $gameVariables.setValue(20, response.user.progress.coins);
            $gameVariables.setValue(21, response.user.progress.score);
            $gameVariables.setValue(22, response.user.progress.current_stage);
            $gameVariables.setValue(23, response.user.progress.completed_quests);
            $gameVariables.setValue(24, response.user.progress.map_changes);
            $gameVariables.setValue(25, response.user.progress.playtime_seconds);
            console.log("✓ User progress data synced from server");
            
            // Trigger progress sync after data is loaded
            setTimeout(() => {
                if (window.debugProgressSync) {
                    console.log('🔄 Triggering progress sync...');
                    window.debugProgressSync();
                }
            }, 1000);
        }
        
        // Store student-specific data if it's a student
        if (response.user.user_type === 'student') {
            $gameVariables.setValue(14, response.user.section || ''); // Section
            $gameVariables.setValue(15, response.user.year_level || ''); // Year level
            $gameVariables.setValue(16, response.user.rizal_professor || ''); // Professor
        }
        
        // Store teacher-specific data if it's a teacher
        if (response.user.user_type === 'teacher' && response.user.sections) {
            $gameVariables.setValue(17, response.user.sections.join(',')); // Sections (comma-separated)
        }
        
        console.log("✓ User data stored successfully");
        console.log("✓ Cloud Save System ready with User ID:", window.gameUserId);
        
        // Show success message
        $gameMessage.add("\\C[3]Login successful!\\C[0]");
        $gameMessage.add(`Welcome, ${response.user.full_name}!`);
        
        // Handle redirection based on auth.php response
        if (response.temp_password || response.redirect_to === 'Scene_ChangePassword') {
            // Store username for password change
            $gameTemp.tempUsername = response.user.username;
            $gameTemp.isTemporaryPassword = true;
            
            // Show message about temporary password
            const message = [
                "\\C[4]Temporary Password Login\\C[0]",
                "",
                "You are logged in with a temporary password.",
                "For security, you must change your password now.",
                "Go to Edit Account",
                "Press OK to continue to password change..."
            ].join("\n");
            
            $dataSystem.optConfirmText = message;
            $dataSystem.optConfirmHandler = function() {
                SceneManager.goto(Scene_EditAccount);
            };
            SceneManager.push(Scene_ConfirmContinue);
            
        } else if (response.redirect_to === 'Scene_TeacherDashboard') {
            // Teacher login - go to teacher dashboard
            setTimeout(() => {
                SceneManager.goto(Scene_TeacherDashboard);
            }, 2000);
            
        } else {
            // Regular student login - go to game menu
            setTimeout(() => {
                SceneManager.goto(Scene_GameMenu);
            }, 2000);
        }
    };

    // Handle login errors - ALIGNED WITH auth.php error responses
    Window_LoginInput.prototype.handleLoginError = function(response) {
        let errorMessage;
        
        if (response.error) {
            if (response.error.includes("not found") || response.error.includes("Account not found")) {
                errorMessage = [
                    "\\C[2]Login Failed\\C[0]",
                    "• Account not found",
                    "• Please check your username",
                    "• Or register a new account",
                    "",
                    "Press OK to try again..."
                ];
            } else if (response.error.includes("password") || response.error.includes("Incorrect password")) {
                errorMessage = [
                    "\\C[2]Login Failed\\C[0]",
                    "• Incorrect password",
                    "• Please check your password",
                    "• Use 'Forgot Password' if needed",
                    "",
                    "Press OK to try again..."
                ];
            } else if (response.error.includes("expired")) {
                errorMessage = [
                    "\\C[2]Login Failed\\C[0]",
                    "• Temporary password has expired",
                    "• Please request a new temporary password",
                    "• Use 'Forgot Password' option",
                    "",
                    "Press OK to try again..."
                ];
            } else {
                errorMessage = [
                    "\\C[2]Login Error\\C[0]",
                    "• " + response.error,
                    "",
                    "Press OK to try again..."
                ];
            }
        } else {
            errorMessage = [
                "\\C[2]Login Error\\C[0]",
                "• Unknown error occurred",
                "",
                "Press OK to try again..."
            ];
        }
        
        this.showErrorMessage(errorMessage);
    };

    // Helper method to show error messages
    Window_LoginInput.prototype.showErrorMessage = function(messageArray) {
        const errorMessage = messageArray.join("\n");
        $dataSystem.optConfirmText = errorMessage;
        SceneManager.push(Scene_ConfirmContinue);
        this.activate();
    };

    // =============================================
    // REGISTER SCENE - STUDENT ONLY
    // =============================================
    function Scene_Register() {
        this.initialize(...arguments);
    }

    Scene_Register.prototype = Object.create(Scene_MenuBase.prototype);
    Scene_Register.prototype.constructor = Scene_Register;

    Scene_Register.prototype.create = function() {
        Scene_MenuBase.prototype.create.call(this);
        this.createHelpWindow();
        this.createStudentFormWindow();
    };

    Scene_Register.prototype.start = function() {
        Scene_MenuBase.prototype.start.call(this);
        if (this._formWindow) {
            this._formWindow.select(0);
            this._formWindow.activate();
        }
    };

    Scene_Register.prototype.update = function() {
        Scene_MenuBase.prototype.update.call(this);
        if (Input.isTriggered('cancel') && this._formWindow && this._formWindow.active) {
            SoundManager.playCancel();
            SceneManager.goto(Scene_Title);
        }
    };

    Scene_Register.prototype.createHelpWindow = function() {
        const rect = this.helpWindowRect();
        this._helpWindow = new Window_Help(rect);
        this._helpWindow.setText("Student Registration - Fill in all fields");
        this.addWindow(this._helpWindow);
    };

    Scene_Register.prototype.helpWindowRect = function() {
        const wx = 0;
        const wy = 0;
        const ww = Graphics.boxWidth;
        const wh = 72;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_Register.prototype.createStudentFormWindow = function() {
        const rect = new Rectangle(50, 100, Graphics.boxWidth - 100, 500);
        this._formWindow = new Window_StudentForm(rect);
        this.addWindow(this._formWindow);
    };

    // Student Registration Form Window
    function Window_StudentForm() {
        this.initialize(...arguments);
    }

    Window_StudentForm.prototype = Object.create(Window_Selectable.prototype);
    Window_StudentForm.prototype.constructor = Window_StudentForm;

    Window_StudentForm.prototype.initialize = function(rect) {
        Window_Selectable.prototype.initialize.call(this, rect);
        this._formData = {
            idNumber: "",
            fullName: "",
            username: "",
            password: "",
            section: "",
            yearLevel: "",
            rizalProfessor: "",
            email: ""
        };
        
        // Define available options
        this._availableSections = [
            "BTLED - ICT",
            "BTLED - HE",
            "BTLED - IA",
            "BS INFOTECH",
            "BSED - MATH",
            "BSED - ENGLISH",
            "BSED -SOCIAL STUDIES",
            "BINDTECH - AT",
            "BINDTECH - CT",
            "BS - COMTECH",
            "BINDTECH - MT",
            "BS BIOLOGY"
        ];
        
        this._availableProfessors = [
            "Prof. Charlene Etcubanas",
            "Prof. Judeimar Ungriano",
            "Prof. Apolonia Espinosa",
            "Prof. Fidel Oblena",
            "Prof. Marissa Cadao"
        ];
        
        this._fieldNames = ["idNumber", "fullName", "username", "password", "section", "yearLevel", "rizalProfessor", "email"];
        this._fieldLabels = [
            "ID Number (XXL-XXXX):",
            "Full Name:",
            "Username:",
            "Password:",
            "Section:",
            "Year Level:",
            "Rizal Professor:",
            "Email Address:"
        ];
        
        this._maxItems = 9; // Fields + register button
        this.refresh();
        this.select(0);
    };

    Window_StudentForm.prototype.maxItems = function() {
        return this._maxItems;
    };

    Window_StudentForm.prototype.itemHeight = function() {
        return 50;
    };

    Window_StudentForm.prototype.drawItem = function(index) {
        const rect = this.itemRect(index);
        this.contents.clearRect(rect.x, rect.y, rect.width, rect.height);
        
        if (index < this._fieldLabels.length) {
            const fieldName = this._fieldNames[index];
            const label = this._fieldLabels[index];
            
            this.drawText(label, rect.x, rect.y, 150);
            
            if (fieldName === "password") {
                const displayValue = this._formData[fieldName] ? "*".repeat(this._formData[fieldName].length) : "Click to enter...";
                this.drawText(displayValue, rect.x + 160, rect.y, rect.width - 160);
            } 
            else if (fieldName === "section") {
                const displayText = this._formData.section || "Click to select...";
                this.drawText(displayText, rect.x + 160, rect.y, rect.width - 160);
            }
            else if (fieldName === "rizalProfessor") {
                const displayText = this._formData.rizalProfessor || "Click to select...";
                this.drawText(displayText, rect.x + 160, rect.y, rect.width - 160);
            }
            else {
                const value = this._formData[fieldName] || "Click to enter...";
                this.drawText(value, rect.x + 160, rect.y, rect.width - 160);
            }
        } else {
            // Register button
            this.drawText("REGISTER AS STUDENT", rect.x, rect.y, rect.width, 'center');
        }
    };

    Window_StudentForm.prototype.processOk = function() {
        const index = this.index();
        SoundManager.playOk();
        
        if (index < this._fieldLabels.length) {
            const fieldName = this._fieldNames[index];
            const label = this._fieldLabels[index];
            
            if (fieldName === "section") {
                this.selectSection();
            } 
            else if (fieldName === "rizalProfessor") {
                this.selectProfessor();
            }
            else {
                this.openTextInput(fieldName, label, fieldName === "password");
            }
        } else {
            this.processRegistration();
        }
    };
    
    Window_StudentForm.prototype.validateEmail = function(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regex.test(email)) {
            alert("❌ Invalid Email Address!\n\nPlease enter a valid email address.\nExample: student@example.com");
            return false;
        }
        return true;
    };

    Window_StudentForm.prototype.validateIdNumber = function(idNumber) {
        const regex = /^\d{2}L-\d{4,5}$/;
        if (!regex.test(idNumber)) {
            alert("Invalid ID Number format!\nPlease use 00L-0000 format (e.g., 23L-4567)\nWhere:\n- First 2 characters are numbers (00)\n- Followed by an 'L' and a hyphen (-)\n- Ending with 4 or 5 digits (0000 or 00000)");
            return false;
        }
        return true;
    };

    Window_StudentForm.prototype.selectSection = function() {
        let message = "Select your section (enter number):\n\n";
        this._availableSections.forEach((section, index) => {
            message += `${index + 1}. ${section}\n`;
        });
        
        const result = prompt(message, "");
        if (result !== null) {
            const selectedIndex = parseInt(result) - 1;
            if (!isNaN(selectedIndex) && selectedIndex >= 0 && selectedIndex < this._availableSections.length) {
                this._formData.section = this._availableSections[selectedIndex];
                this.refresh();
            } else {
                alert("Invalid selection. Please enter a number between 1 and " + this._availableSections.length);
            }
        }
        this.activate();
    };

    Window_StudentForm.prototype.selectProfessor = function() {
        let message = "Select your Rizal professor (enter number):\n\n";
        this._availableProfessors.forEach((professor, index) => {
            message += `${index + 1}. ${professor}\n`;
        });
        
        const result = prompt(message, "");
        if (result !== null) {
            const selectedIndex = parseInt(result) - 1;
            if (!isNaN(selectedIndex) && selectedIndex >= 0 && selectedIndex < this._availableProfessors.length) {
                this._formData.rizalProfessor = this._availableProfessors[selectedIndex];
                this.refresh();
            } else {
                alert("Invalid selection. Please enter a number between 1 and " + this._availableProfessors.length);
            }
        }
        this.activate();
    };

    Window_StudentForm.prototype.openTextInput = function(fieldName, label, isPassword = false) {
        const currentValue = this._formData[fieldName] || "";
        const result = prompt("Enter " + label.replace(":", ""), currentValue);
        
        if (result !== null) {
            // Special validation for ID Number
            if (fieldName === "idNumber") {
                if (!this.validateIdNumber(result)) {
                    this.activate();
                    return;
                }
            }
            if (fieldName === "email") {
                if (!this.validateEmail(result)) {
                    this.activate();
                    return;
                }
             }
            
            this._formData[fieldName] = result;
            this.refresh();
        }
        this.activate();
    };

    Window_StudentForm.prototype.processRegistration = function() {
        console.log("=== VALIDATING STUDENT REGISTRATION ===");
        
        // Clear any previous messages
        $gameMessage.clear();
        
        // Validate all required fields
        const errors = [];
        
        if (!this._formData.fullName || this._formData.fullName.trim() === "") {
            errors.push("• Full Name is required");
        }
        
        if (!this._formData.email || this._formData.email.trim() === "") {
            errors.push("• Email Address is required");
        } else if (!this.validateEmail(this._formData.email)) {
            errors.push("• Valid Email Address is required");
        }
        
        if (!this._formData.username || this._formData.username.trim() === "") {
            errors.push("• Username is required");
        } else if (this._formData.username.length < 3) {
            errors.push("• Username must be at least 3 characters");
        }
        
        if (!this._formData.password || this._formData.password.trim() === "") {
            errors.push("• Password is required");
        } else if (this._formData.password.length < 6) {
            errors.push("• Password must be at least 6 characters");
        }
        
        if (!this._formData.section || this._formData.section.trim() === "") {
            errors.push("• Section is required");
        }
        
        if (!this._formData.yearLevel || this._formData.yearLevel.trim() === "") {
            errors.push("• Year Level is required");
        }
        
        if (!this._formData.rizalProfessor || this._formData.rizalProfessor.trim() === "") {
            errors.push("• Rizal Professor is required");
        }
        
        if (!this._formData.idNumber || this._formData.idNumber.trim() === "") {
            errors.push("• ID Number is required");
        }
        
        // Show errors if any
        if (errors.length > 0) {
            const errorMessage = [
                "\\C[2]Registration Errors:\\C[0]",
                ...errors,
                "",
                "Press OK to continue editing..."
            ].join("\n");
            
            $dataSystem.optConfirmText = errorMessage;
            SceneManager.push(Scene_ConfirmContinue);
            return;
        }
        
        // If validation passes, proceed with registration
        this.deactivate();
        this.performRegistration();
    };

    Window_StudentForm.prototype.performRegistration = function() {
        console.log("=== STUDENT REGISTRATION ATTEMPT ===");
        console.log("Form data:", this._formData);
        
        $gameMessage.add("Connecting to server...");
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'https://filibustero-web.com/php/register.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        const self = this;
        
        xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log("Registration response:", response);
                    
                    if (response.success) {
                        console.log("Registration successful!");
                        $gameMessage.add("Registration successful!");
                        
                        // ✅ FIXED: Handle both response formats
                        const userId = response.user?.id || response.user_id;
                        
                        // ✅ Validate user ID
                        if (!userId || userId === 0) {
                            console.error("❌ Invalid user ID received:", userId);
                            console.error("Full response:", response);
                            $gameMessage.add("Registration error: Invalid user ID received from server");
                            self.activate();
                            return;
                        }
                        
                        console.log("✅ Valid user ID:", userId);
                        
                        // Store user data in multiple places
                        window.gameUserId = userId;
                        window.FilibusteroAuth = {
                            currentUser: {
                                id: userId,
                                user_type: response.user?.user_type || 'student',
                                id_number: response.user?.id_number || self._formData.idNumber,
                                full_name: response.user?.full_name || self._formData.fullName,
                                username: response.user?.username || self._formData.username
                            },
                            sessionToken: null,
                            isLoggedIn: true
                        };
                        
                        // ✅ Also save to localStorage
                        try {
                            localStorage.setItem('gameUserId', userId.toString());
                            console.log("✅ Saved user ID to localStorage:", userId);
                        } catch(e) {
                            console.warn('Could not save to localStorage:', e);
                        }
                        
                        // ✅ Set game variable if available
                        if (typeof $gameVariables !== 'undefined' && $gameVariables) {
                            $gameVariables.setValue(10, userId);
                            console.log("✅ Saved user ID to game variable 10:", userId);
                        }
                        
                        // Debug log all storage locations
                        console.log("=== USER ID STORED IN ===");
                        console.log("window.gameUserId:", window.gameUserId);
                        console.log("window.FilibusteroAuth.currentUser.id:", window.FilibusteroAuth.currentUser.id);
                        console.log("localStorage.gameUserId:", localStorage.getItem('gameUserId'));
                        console.log("$gameVariables.value(10):", typeof $gameVariables !== 'undefined' ? $gameVariables.value(10) : 'N/A');
                        
                        // Show success message
                        $gameMessage.add("Welcome, " + self._formData.fullName + "!");
                        
                        // Wait 2 seconds then go to game menu
                        setTimeout(() => {
                            SceneManager.goto(Scene_GameMenu);
                        }, 2000);
                        
                    } else {
                        console.log("Registration failed:", response.error);
                        $gameMessage.add("Registration failed: " + (response.error || "Unknown error"));
                        self.activate();
                    }
                } catch(e) {
                    console.error("JSON Parse Error:", e);
                    console.error("Raw response:", xhr.responseText);
                    $gameMessage.add("Server error: Invalid response format");
                    self.activate();
                }
            } else {
                console.error("HTTP Error:", xhr.status, xhr.statusText);
                $gameMessage.add("Connection failed. Status: " + xhr.status);
                self.activate();
                }
            }
        };
        
        xhr.onerror = function() {
            console.error("Network error occurred");
            $gameMessage.add("Network error: Cannot connect to server");
            self.activate();
        };
        
        xhr.ontimeout = function() {
            console.error("Request timed out");
            $gameMessage.add("Request timed out");
            self.activate();
        };
        
        xhr.timeout = 15000; // 15 second timeout
        
        // Prepare the data to send
        const params = new URLSearchParams();
        params.append('action', 'register');
        params.append('user_type', 'student');
        params.append('id_number', this._formData.idNumber);
        params.append('full_name', this._formData.fullName);
        params.append('username', this._formData.username);
        params.append('password', this._formData.password);
        params.append('section', this._formData.section);
        params.append('year_level', this._formData.yearLevel);
        params.append('rizal_professor', this._formData.rizalProfessor);
        params.append('email', this._formData.email);
        
        console.log("Sending params:", params.toString());
        xhr.send(params.toString());
    };

    Window_StudentForm.prototype.update = function() {
        Window_Selectable.prototype.update.call(this);
        
        if (this.active) {
            if (Input.isTriggered('ok')) {
                this.processOk();
            } else if (Input.isTriggered('cancel')) {
                SoundManager.playCancel();
                SceneManager.pop();
            }
        }
    };

    Window_StudentForm.prototype.refresh = function() {
        this.contents.clear();
        for (let i = 0; i < this.maxItems(); i++) {
            this.drawItem(i);
        }
    };

    // =============================================
    // CONFIRM CONTINUE SCENE (For validation errors)
    // =============================================
    function Scene_ConfirmContinue() {
        this.initialize(...arguments);
    }

    Scene_ConfirmContinue.prototype = Object.create(Scene_MenuBase.prototype);
    Scene_ConfirmContinue.prototype.constructor = Scene_ConfirmContinue;

    Scene_ConfirmContinue.prototype.create = function() {
        Scene_MenuBase.prototype.create.call(this);
        this.createMessageWindow();
        this.createChoiceWindow();
    };

    Scene_ConfirmContinue.prototype.createMessageWindow = function() {
        const rect = this.messageWindowRect();
        this._messageWindow = new Window_Base(rect);
        this._messageWindow.drawTextEx($dataSystem.optConfirmText, 0, 0);
        this.addWindow(this._messageWindow);
    };

    Scene_ConfirmContinue.prototype.messageWindowRect = function() {
        const ww = 500;
        const wh = this.calcWindowHeight(6); // Adjust based on expected lines
        const wx = (Graphics.boxWidth - ww) / 2;
        const wy = (Graphics.boxHeight - wh) / 2 - 50;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_ConfirmContinue.prototype.createChoiceWindow = function() {
        const rect = this.choiceWindowRect();
        this._choiceWindow = new Window_ConfirmChoice(rect);
        this._choiceWindow.setHandler('ok', this.onConfirmOk.bind(this));
        this._choiceWindow.select(0);
        this._choiceWindow.activate();
        this.addWindow(this._choiceWindow);
    };

    Scene_ConfirmContinue.prototype.choiceWindowRect = function() {
        const ww = 200;
        const wh = Window_Selectable.prototype.fittingHeight(1);
        const wx = (Graphics.boxWidth - ww) / 2;
        const wy = (Graphics.boxHeight - wh) / 2 + 100;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_ConfirmContinue.prototype.onConfirmOk = function() {
        SoundManager.playOk();
        SceneManager.pop();
    };

    Scene_ConfirmContinue.prototype.calcWindowHeight = function(numLines) {
        return Window_Base.prototype.fittingHeight(numLines);
    };

    // =============================================
    // CONFIRM CHOICE WINDOW (Single option)
    // =============================================
    function Window_ConfirmChoice() {
        this.initialize(...arguments);
    }

    Window_ConfirmChoice.prototype = Object.create(Window_Command.prototype);
    Window_ConfirmChoice.prototype.constructor = Window_ConfirmChoice;

    Window_ConfirmChoice.prototype.makeCommandList = function() {
        this.addCommand("OK", 'ok');
    };

    Window_ConfirmChoice.prototype.windowWidth = function() {
        return 200;
    };

    // =============================================
    // GAME MENU SCENE (After Login)
    // =============================================
    function Scene_GameMenu() {
        this.initialize(...arguments);
    }

    Scene_GameMenu.prototype = Object.create(Scene_MenuBase.prototype);
    Scene_GameMenu.prototype.constructor = Scene_GameMenu;

    Scene_GameMenu.prototype.create = function() {
        Scene_MenuBase.prototype.create.call(this);
        this.createHelpWindow();
        this.createGameMenuWindow();
    };

    Scene_GameMenu.prototype.start = function() {
        Scene_MenuBase.prototype.start.call(this);
        if (this._commandWindow) {
            this._commandWindow.select(0);
            this._commandWindow.activate();
        }
    };

    Scene_GameMenu.prototype.update = function() {
        Scene_MenuBase.prototype.update.call(this);
        if (Input.isTriggered('cancel') && this._commandWindow && this._commandWindow.active) {
            // Optional: Add logout confirmation
            SoundManager.playCancel();
        }
    };

    Scene_GameMenu.prototype.createHelpWindow = function() {
        const rect = this.helpWindowRect();
        this._helpWindow = new Window_Help(rect);
        const user = window.FilibusteroAuth.currentUser;
        this._helpWindow.setText(`Welcome, ${user.full_name} (${user.user_type})`);
        this.addWindow(this._helpWindow);
    };

    Scene_GameMenu.prototype.helpWindowRect = function() {
        const wx = 0;
        const wy = 0;
        const ww = Graphics.boxWidth;
        const wh = 72;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_GameMenu.prototype.createGameMenuWindow = function() {
        const rect = this.commandWindowRect();
        this._commandWindow = new Window_GameMenu(rect);
        this._commandWindow.setHandler('newGame', this.commandNewGame.bind(this));
        this._commandWindow.setHandler('loadGame', this.commandLoadGame.bind(this));
        this._commandWindow.setHandler('options', this.commandOptions.bind(this));
        this._commandWindow.setHandler('tutorial', this.commandTutorial.bind(this));
        this._commandWindow.setHandler('logout', this.commandLogout.bind(this));
        this.addWindow(this._commandWindow);
    };

    Scene_GameMenu.prototype.commandWindowRect = function() {
        const ww = 400;
        const wh = Window_Selectable.prototype.fittingHeight(4);
        const wx = (Graphics.boxWidth - ww) / 2;
        const wy = (Graphics.boxHeight - wh) / 2;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_GameMenu.prototype.commandNewGame = function() {
        SoundManager.playOk();
        this.fadeOutAll();
        DataManager.setupNewGame();
        SceneManager.goto(Scene_Map);
    };

    Scene_GameMenu.prototype.commandLoadGame = function() {
        SoundManager.playOk();
        SceneManager.push(Scene_Load);
    };

    Scene_GameMenu.prototype.commandOptions = function() {
        SoundManager.playOk();
        SceneManager.push(Scene_Options);
    };

    Scene_GameMenu.prototype.commandTutorial = function() {
    SoundManager.playOk();
    SceneManager.push(Scene_Tutorial);
    };

    Scene_GameMenu.prototype.commandLogout = function() {
        SoundManager.playOk();
        const user = window.FilibusteroAuth.currentUser;
        const message = `Are you sure you want to logout, ${user.full_name}?`;
        
        $dataSystem.optConfirmText = message;
        SceneManager.push(Scene_ConfirmLogout);
    };

    // =============================================
    // GAME MENU WINDOW
    // =============================================
    function Window_GameMenu() {
        this.initialize(...arguments);
    }

    Window_GameMenu.prototype = Object.create(Window_Command.prototype);
    Window_GameMenu.prototype.constructor = Window_GameMenu;

    Window_GameMenu.prototype.makeCommandList = function() {
        this.addCommand("New Game", "newGame");
        this.addCommand("Load Game", "loadGame");
        this.addCommand("Options", "options");
        this.addCommand("Tutorial", "tutorial");
        this.addCommand("Logout", "logout");
    };

    // =============================================
    // LOGOUT CONFIRMATION SCENE
    // =============================================
    function Scene_ConfirmLogout() {
        this.initialize(...arguments);
    }

    Scene_ConfirmLogout.prototype = Object.create(Scene_MenuBase.prototype);
    Scene_ConfirmLogout.prototype.constructor = Scene_ConfirmLogout;

    Scene_ConfirmLogout.prototype.create = function() {
        Scene_MenuBase.prototype.create.call(this);
        this.createMessageWindow();
        this.createChoiceWindow();
    };

    Scene_ConfirmLogout.prototype.createMessageWindow = function() {
        const rect = this.messageWindowRect();
        this._messageWindow = new Window_Base(rect);
        const user = window.FilibusteroAuth.currentUser;
        const text = `Are you sure you want to logout, ${user.full_name}?`;
        this._messageWindow.drawText(text, 0, 0, this._messageWindow.contentsWidth(), "center");
        this.addWindow(this._messageWindow);
    };

    Scene_ConfirmLogout.prototype.messageWindowRect = function() {
        const ww = 400;
        const wh = 72;
        const wx = (Graphics.boxWidth - ww) / 2;
        const wy = (Graphics.boxHeight - wh) / 2 - 50;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_ConfirmLogout.prototype.createChoiceWindow = function() {
        const rect = this.choiceWindowRect();
        this._choiceWindow = new Window_LogoutChoice(rect);
        this._choiceWindow.setHandler('yes', this.onLogoutYes.bind(this));
        this._choiceWindow.setHandler('no', this.onLogoutNo.bind(this));
        this._choiceWindow.setHandler('cancel', this.onLogoutNo.bind(this));
        this._choiceWindow.select(1); // Default to "No"
        this._choiceWindow.activate();
        this.addWindow(this._choiceWindow);
    };

    Scene_ConfirmLogout.prototype.choiceWindowRect = function() {
        const ww = 200;
        const wh = Window_Selectable.prototype.fittingHeight(2);
        const wx = (Graphics.boxWidth - ww) / 2;
        const wy = (Graphics.boxHeight - wh) / 2 + 50;
        return new Rectangle(wx, wy, ww, wh);
    };

    Scene_ConfirmLogout.prototype.onLogoutYes = function() {
        SoundManager.playOk();
        SceneManager.goto(Scene_Title);
    };

    Scene_ConfirmLogout.prototype.onLogoutNo = function() {
        SoundManager.playCancel();
        SceneManager.goto(Scene_GameMenu);
    };

    // =============================================
    // LOGOUT CHOICE WINDOW
    // =============================================
    function Window_LogoutChoice() {
        this.initialize(...arguments);
    }

    Window_LogoutChoice.prototype = Object.create(Window_Command.prototype);
    Window_LogoutChoice.prototype.constructor = Window_LogoutChoice;

    Window_LogoutChoice.prototype.makeCommandList = function() {
        this.addCommand("Yes", "yes");
        this.addCommand("No", "no");
    };

// =============================================
// ALTERNATIVE: INTEGRATION WITH COMMON EVENTS
// =============================================

// If you're using common events for quiz logic, you can add these calls:

// Script call to start quiz session:
// window.QuizTracker.startSession($gameVariables.value(1)); // where variable 1 is quest ID

// Script call to submit answer:
// window.QuizTracker.submitAnswer(
//     $gameVariables.value(2), // question ID
//     $gameVariables.value(1), // quest ID  
//     String.fromCharCode(65 + $gameVariables.value(3) - 1), // convert 1,2,3,4 to A,B,C,D
//     $gameVariables.value(4) // time spent
// );

// Script call to end session:
// window.QuizTracker.endSession(
//     $gameVariables.value(5), // correct answers
//     $gameVariables.value(6), // total score
//     $gameVariables.value(7)  // completion time
// );

// =============================================
// DEBUGGING HELPERS
// =============================================

// Add these to help debug integration issues

window.QuizDebug = {
    // Test database connection
    testConnection: function() {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'https://filibustero-web.com/php/quiz_api.php?action=test', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log("Connection test result:", xhr.status, xhr.responseText);
            }
        };
        xhr.send();
    },
    
    // Log current user info
    logUser: function() {
        console.log("Current user:", window.FilibusteroAuth?.currentUser);
    },
    
    // Test submit answer
    testSubmitAnswer: function() {
        const user = window.FilibusteroAuth?.currentUser;
        if (!user) {
            console.log("No user logged in");
            return;
        }
        
        window.QuizTracker.submitAnswer(1, 1, 'A', 5); // Test with dummy data
    }
};

// Console commands for testing:
// QuizDebug.testConnection()
// QuizDebug.logUser() 
// QuizDebug.testSubmitAnswer()

console.log("Quiz Database Integration loaded. Use QuizDebug commands to test connection.");

// =============================================
// FORGOT PASSWORD SCENE
// =============================================
function Scene_ForgotPassword() {
    this.initialize(...arguments);
}

Scene_ForgotPassword.prototype = Object.create(Scene_MenuBase.prototype);
Scene_ForgotPassword.prototype.constructor = Scene_ForgotPassword;

Scene_ForgotPassword.prototype.create = function() {
    Scene_MenuBase.prototype.create.call(this);
    this.createHelpWindow();
    this.createForgotPasswordWindow();
};

Scene_ForgotPassword.prototype.start = function() {
    Scene_MenuBase.prototype.start.call(this);
    if (this._forgotWindow) {
        this._forgotWindow.select(0);
        this._forgotWindow.activate();
    }
};

Scene_ForgotPassword.prototype.update = function() {
    Scene_MenuBase.prototype.update.call(this);
        // Handle cancel to go back
        if (Input.isTriggered('cancel') && this._loginWindow && this._loginWindow.active) {
            SoundManager.playCancel();
            SceneManager.pop();
        }
};

Scene_ForgotPassword.prototype.createHelpWindow = function() {
    const rect = this.helpWindowRect();
    this._helpWindow = new Window_Help(rect);
    this._helpWindow.setText("Enter email to retrieve  password");
    this.addWindow(this._helpWindow);
};

Scene_ForgotPassword.prototype.helpWindowRect = function() {
    const wx = 0;
    const wy = 0;
    const ww = Graphics.boxWidth;
    const wh = 72;
    return new Rectangle(wx, wy, ww, wh);
};

Scene_ForgotPassword.prototype.createForgotPasswordWindow = function() {
    const rect = new Rectangle(100, 150, Graphics.boxWidth - 200, 200);
    this._forgotWindow = new Window_ForgotPassword(rect);
    this.addWindow(this._forgotWindow);
};

// =============================================
// FORGOT PASSWORD SCENE - ENHANCED VERSION
// =============================================
function Scene_ForgotPassword() {
    this.initialize.apply(this, arguments);
}

Scene_ForgotPassword.prototype = Object.create(Scene_MenuBase.prototype);
Scene_ForgotPassword.prototype.constructor = Scene_ForgotPassword;

Scene_ForgotPassword.prototype.create = function() {
    Scene_MenuBase.prototype.create.call(this);
    this.createHelpWindow();
    this.createForgotPasswordWindow();
};

Scene_ForgotPassword.prototype.start = function() {
    Scene_MenuBase.prototype.start.call(this);
    if (this._forgotWindow) {
        this._forgotWindow.select(0);
        this._forgotWindow.activate();
    }
};

Scene_ForgotPassword.prototype.update = function() {
    Scene_MenuBase.prototype.update.call(this);
        // Handle cancel to go back
        if (Input.isTriggered('cancel') && this._loginWindow && this._loginWindow.active) {
            SoundManager.playCancel();
            SceneManager.pop();
        }
};

Scene_ForgotPassword.prototype.createHelpWindow = function() {
    const rect = new Rectangle(0, 0, Graphics.boxWidth, 72);
    this._helpWindow = new Window_Help(rect);
    this._helpWindow.setText("Password Recovery - Enter email to retrieve password");
    this.addWindow(this._helpWindow);
};

Scene_ForgotPassword.prototype.createForgotPasswordWindow = function() {
    const rect = new Rectangle(150, 150, Graphics.boxWidth - 300, 200);
    this._forgotWindow = new Window_ForgotPassword(rect);
    this.addWindow(this._forgotWindow);
};

// =============================================
// FORGOT PASSWORD WINDOW - ENHANCED ERROR HANDLING
// =============================================
function Window_ForgotPassword() {
    this.initialize(...arguments);
}

Window_ForgotPassword.prototype = Object.create(Window_Selectable.prototype);
Window_ForgotPassword.prototype.constructor = Window_ForgotPassword;

Window_ForgotPassword.prototype.initialize = function(rect) {
    Window_Selectable.prototype.initialize.call(this, rect);
    this._formData = {
        email: ""  // ✅ CHANGED: From idNumber to email
    };
    this._fieldNames = ["email"];  // ✅ CHANGED
    this._fieldLabels = ["Email Address:"];  // ✅ CHANGED
    this._maxItems = 2;
    this._isProcessing = false;
    this.refresh();
    this.activate();
    this.select(0);
};

Window_ForgotPassword.prototype.maxItems = function() {
    return this._maxItems;
};

Window_ForgotPassword.prototype.itemHeight = function() {
    return 60;
};

Window_ForgotPassword.prototype.drawItem = function(index) {
    const rect = this.itemRect(index);
    this.contents.clearRect(rect.x, rect.y, rect.width, rect.height);
    
    if (this._isProcessing && index === 1) {
        this.changeTextColor(this.systemColor());
    }
    
    if (index === 0) {
        const label = this._fieldLabels[0];
        this.drawText(label, rect.x, rect.y, 150);
        
        const value = this._formData.email || "Click to enter...";  // ✅ CHANGED
        this.drawText(value, rect.x + 160, rect.y, rect.width - 160);
    } else if (index === 1) {
        const retrieveText = this._isProcessing ? "SENDING EMAIL..." : "SEND RESET EMAIL";  // ✅ CHANGED
        this.drawText(retrieveText, rect.x, rect.y, rect.width, 'center');
    }
    
    this.resetTextColor();
};

Window_ForgotPassword.prototype.processOk = function() {
    if (this._isProcessing) return; // Prevent multiple clicks during processing
    
    const index = this.index();
    console.log("Button pressed - index:", index);
    SoundManager.playOk();
    
    if (index === 0) {
        const fieldName = this._fieldNames[0];
        const label = this._fieldLabels[0];
        this.openTextInput(fieldName, label);
    } else if (index === 1) {
        // Retrieve button pressed
        this.processPasswordRetrieval();
    }
};

Window_ForgotPassword.prototype.openTextInput = function(fieldName, label) {
    this.deactivate();
    
    setTimeout(() => {
        const currentValue = this._formData[fieldName] || "";
        const result = prompt("Enter " + label.replace(":", ""), currentValue);
        
        if (result !== null) {
            if (result.trim() === "") {
                alert("❌ Email Address is required!");
                this.activate();
                return;
            }
            
            if (!this.validateEmail(result.trim())) {
                this.activate();
                return;
            }
            
            this._formData[fieldName] = result.trim();
        }
        
        this.refresh();
        this.activate();
    }, 100);
};

// ✅ NEW: Email validation
Window_ForgotPassword.prototype.validateEmail = function(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regex.test(email)) {
        alert("❌ Invalid Email Address!\n\nPlease enter a valid email address.\nExample: student@example.com");
        return false;
    }
    return true;
};

Window_ForgotPassword.prototype.update = function() {
    Window_Selectable.prototype.update.call(this);
    if (this.active && Input.isTriggered('ok')) {
        this.processOk();
    } else if (this.active && Input.isTriggered('cancel')) {
        if (!this._isProcessing) { // Don't allow cancel during processing
            SoundManager.playCancel();
            SceneManager.pop();
        }
    }
};

Window_ForgotPassword.prototype.processPasswordRetrieval = function() {
    console.log("=== VALIDATING PASSWORD RETRIEVAL ===");
    $gameMessage.clear();
    
    const errors = [];
    
    // ✅ CHANGED: Validate email instead of ID number
    if (!this._formData.email || this._formData.email.trim() === "") {
        errors.push("• Email Address is required");
    } else if (!this.validateEmail(this._formData.email)) {
        errors.push("• Valid Email Address is required");
    }
    
    if (errors.length > 0) {
        const errorMessage = [
            "\\C[2]Password Recovery Errors:\\C[0]",
            ...errors,
            "",
            "Press OK to continue editing..."
        ].join("\n");
        
        $dataSystem.optConfirmText = errorMessage;
        SceneManager.push(Scene_ConfirmContinue);
        return;
    }
    
    this.deactivate();
    this.performPasswordRetrieval();
};

Window_ForgotPassword.prototype.performPasswordRetrieval = function() {
    console.log("=== PASSWORD RETRIEVAL ATTEMPT ===");
    console.log("Email:", this._formData.email);  // ✅ CHANGED
    
    this._isProcessing = true;
    this.refresh();
    
    $gameMessage.add("Sending password reset email...");  // ✅ CHANGED
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'https://filibustero-web.com/php/auth.php', true);  // ✅ UPDATED
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    const self = this;
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            self._isProcessing = false;
            self.refresh();
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log("Response:", response);
                    
                    if (response.success) {
                        $gameMessage.clear();
                        
                        // ✅ CHANGED: Email sent confirmation
                        const message = [
                            "\\C[3]✅ Password Reset Email Sent\\C[0]",
                            `A password reset link has been sent:`,
                            
                            `\\C[2]${self._formData.email}\\C[0]`,
                            
                            'NOTICE: Expires in 1 hour',
                            
                            "",
                            "Press OK to return to login..."
                        ].join("\n");
                        
                        $dataSystem.optConfirmText = message;
                        $dataSystem.optConfirmHandler = function() {
                            SceneManager.goto(Scene_Title);
                        };
                        SceneManager.push(Scene_ConfirmContinue);
                        
                    } else {
                        let errorMessage;
                        if (response.error && response.error.includes("not found")) {
                            errorMessage = [
                                "\\C[2]❌ Email Not Found\\C[0]",
                                "",
                                "• No account found with this email address",
                                "• Please check your email and try again",
                                "• Make sure you registered with this email",
                                "",
                                "Press OK to try again..."
                            ].join("\n");
                        } else {
                            errorMessage = [
                                "\\C[2]❌ Password Recovery Failed\\C[0]",
                                "",
                                "• " + (response.error || "Unknown error"),
                                "",
                                "Press OK to try again..."
                            ].join("\n");
                        }
                        
                        $dataSystem.optConfirmText = errorMessage;
                        SceneManager.push(Scene_ConfirmContinue);
                        self.activate();
                    }
                } catch(e) {
                    console.error("JSON Parse Error:", e);
                    const errorMessage = [
                        "\\C[2]❌ System Error\\C[0]",
                        "",
                        "• Invalid server response",
                        "",
                        "Press OK to try again..."
                    ].join("\n");
                    $dataSystem.optConfirmText = errorMessage;
                    SceneManager.push(Scene_ConfirmContinue);
                    self.activate();
                }
            } else {
                const errorMessage = [
                    "\\C[2]❌ Connection Error\\C[0]",
                    "",
                    "• Cannot connect to server",
                    "• Status: " + xhr.status,
                    "",
                    "Press OK to try again..."
                ].join("\n");
                $dataSystem.optConfirmText = errorMessage;
                SceneManager.push(Scene_ConfirmContinue);
                self.activate();
            }
        }
    };
    
    xhr.onerror = function() {
        self._isProcessing = false;
        self.refresh();
        const errorMessage = [
            "\\C[2]❌ Network Error\\C[0]",
            "",
            "• Cannot connect to server",
            "",
            "Press OK to try again..."
        ].join("\n");
        $dataSystem.optConfirmText = errorMessage;
        SceneManager.push(Scene_ConfirmContinue);
        self.activate();
    };
    
    xhr.timeout = 15000;
    
    // ✅ CHANGED: Send email instead of ID number
    const params = `action=forgot_password&email=${encodeURIComponent(this._formData.email)}`;
    console.log("Sending params:", params);
    xhr.send(params);
};

Window_ForgotPassword.prototype.update = function() {
    Window_Selectable.prototype.update.call(this);
    if (this.active && Input.isTriggered('ok')) {
        this.processOk();
    } else if (this.active && Input.isTriggered('cancel')) {
        if (!this._isProcessing) {
            SoundManager.playCancel();
            SceneManager.pop();
        }
    }
};

Window_ForgotPassword.prototype.refresh = function() {
    this.contents.clear();
    for (let i = 0; i < this.maxItems(); i++) {
        this.drawItem(i);
    }
};

// =============================================
// TEMP PASSWORD OPTIONS SCENE - ENHANCED VERSION
// =============================================
function Scene_TempPasswordOptions() {
    this.initialize.apply(this, arguments);
}

Scene_TempPasswordOptions.prototype = Object.create(Scene_MenuBase.prototype);
Scene_TempPasswordOptions.prototype.constructor = Scene_TempPasswordOptions;

Scene_TempPasswordOptions.prototype.create = function() {
    Scene_MenuBase.prototype.create.call(this);
    this.createHelpWindow();
    this.createCommandWindow();
};

Scene_TempPasswordOptions.prototype.createHelpWindow = function() {
    const rect = new Rectangle(0, 0, Graphics.boxWidth, 72);
    this._helpWindow = new Window_Help(rect);
    this._helpWindow.setText("Temporary Password Options - Choose your next action");
    this.addWindow(this._helpWindow);
};

Scene_TempPasswordOptions.prototype.createCommandWindow = function() {
    const rect = new Rectangle(150, 150, Graphics.boxWidth - 300, 250);
    this._commandWindow = new Window_Command(rect);
    this._commandWindow.setHandler('login', this.onLoginCommand.bind(this));
    this._commandWindow.setHandler('editaccount', this.onEditCommand.bind(this));
    this._commandWindow.setHandler('cancel', this.onCancelCommand.bind(this));
    this.addWindow(this._commandWindow);
    
    const self = this;
    this._commandWindow.makeCommandList = function() {
        this.addCommand('Login with Temporary Password', 'login');
        this.addCommand('Edit Account Information', 'editaccount');
        this.addCommand('Return to Title Screen', 'cancel');
    };
    this._commandWindow.refresh();
    this._commandWindow.activate();
    this._commandWindow.select(0);
};

Scene_TempPasswordOptions.prototype.start = function() {
    Scene_MenuBase.prototype.start.call(this);
    if (this._commandWindow) {
        this._commandWindow.activate();
        this._commandWindow.select(0);
    }
};

Scene_TempPasswordOptions.prototype.update = function() {
    Scene_MenuBase.prototype.update.call(this);
    if (Input.isTriggered('cancel')) {
        this.onCancelCommand();
    }
};

Scene_TempPasswordOptions.prototype.onLoginCommand = function() {
    SoundManager.playOk();
    SceneManager.push(Scene_Login);
};

Scene_TempPasswordOptions.prototype.onEditCommand = function() {
    SoundManager.playOk();
    SceneManager.push(Scene_EditAccount);
};

Scene_TempPasswordOptions.prototype.onCancelCommand = function() {
    SoundManager.playCancel();
    
    // Show confirmation dialog
    const confirmMessage = [
        "\\C[4]⚠️ Confirm Action\\C[0]",
        "",
        "Are you sure you want to return to the title screen?",
        "",
        "• Your temporary password will remain active",
        "• You can still use it to login later",
        "• It will expire in 1 hour",
        "",
        "Press OK to return to title screen..."
    ].join("\n");
    
    $dataSystem = $dataSystem || {};
    $dataSystem.optConfirmText = confirmMessage;
    $dataSystem.optConfirmHandler = function() {
        // Clear temporary data
        if ($gameTemp) {
            $gameTemp.tempUsername = null;
            $gameTemp.tempPassword = null;
            $gameTemp.isTemporaryPassword = false;
        }
        
        
        SceneManager.goto(Scene_Title);
    };
    SceneManager.push(Scene_ConfirmContinue);
};

// =============================================
// EDIT ACCOUNT SCENE - ENHANCED VERSION
// =============================================
function Scene_EditAccount() {
    this.initialize.apply(this, arguments);
}

Scene_EditAccount.prototype = Object.create(Scene_MenuBase.prototype);
Scene_EditAccount.prototype.constructor = Scene_EditAccount;

Scene_EditAccount.prototype.create = function() {
    Scene_MenuBase.prototype.create.call(this);
    this.createHelpWindow();
    this.createEditAccountWindow();
};

Scene_EditAccount.prototype.start = function() {
    Scene_MenuBase.prototype.start.call(this);
    if (this._editWindow) {
        this._editWindow.select(0);
        this._editWindow.activate();
    }
};

Scene_EditAccount.prototype.update = function() {
    Scene_MenuBase.prototype.update.call(this);
        // Handle cancel to go back
        if (Input.isTriggered('cancel') && this._loginWindow && this._loginWindow.active) {
            SoundManager.playCancel();
            SceneManager.pop();
        }
};

Scene_EditAccount.prototype.createHelpWindow = function() {
    const rect = new Rectangle(0, 0, Graphics.boxWidth, 72);
    this._helpWindow = new Window_Help(rect);
    this._helpWindow.setText("Edit your account information - Fill in required fields");
    this.addWindow(this._helpWindow);
};

Scene_EditAccount.prototype.createEditAccountWindow = function() {
    const rect = new Rectangle(100, 100, Graphics.boxWidth - 200, 350);
    this._editWindow = new Window_EditAccount(rect);
    this.addWindow(this._editWindow);
};

// =============================================
// EDIT ACCOUNT WINDOW - ENHANCED ERROR HANDLING
// =============================================
function Window_EditAccount() {
    this.initialize.apply(this, arguments);
}

Window_EditAccount.prototype = Object.create(Window_Selectable.prototype);
Window_EditAccount.prototype.constructor = Window_EditAccount;

Window_EditAccount.prototype.initialize = function(rect) {
    Window_Selectable.prototype.initialize.call(this, rect);
    this._formData = {
        idNumber: "",
        currentUsername: "",
        currentPassword: "",
        newUsername: "",
        newPassword: ""
    };
    this._fieldNames = ["idNumber", "currentUsername", "currentPassword", "newUsername", "newPassword"];
    this._fieldLabels = [
        "ID Number (##L-####):",
        "Current Username:",
        "Current Password:",
        "New Username:",
        "New Password:"
    ];
    this._maxItems = 6; // Fields + save button
    this._isProcessing = false;
    this.refresh();
    this.activate();
    this.select(0);
};

Window_EditAccount.prototype.maxItems = function() {
    return this._maxItems;
};

Window_EditAccount.prototype.itemHeight = function() {
    return 50;
};

Window_EditAccount.prototype.drawItem = function(index) {
    const rect = this.itemRect(index);
    this.contents.clearRect(rect.x, rect.y, rect.width, rect.height);
    
    // Change text color based on processing state
    if (this._isProcessing && index === 5) {
        this.changeTextColor(this.systemColor());
    }
    
    if (index < this._fieldLabels.length) {
        const fieldName = this._fieldNames[index];
        const label = this._fieldLabels[index];
        
        this.drawText(label, rect.x, rect.y, 150);
        
        if (fieldName === "currentPassword" || fieldName === "newPassword") {
            const displayValue = this._formData[fieldName] ? 
                "*".repeat(this._formData[fieldName].length) : 
                (fieldName === "newPassword" ? "(Required)" : "Click to enter...");
            this.drawText(displayValue, rect.x + 160, rect.y, rect.width - 160);
        } else {
            let value = this._formData[fieldName];
            if (!value) {
                value = (fieldName === "newUsername" || fieldName === "newPassword") ? 
                    "(Optional)" : "Click to enter...";
            }
            this.drawText(value, rect.x + 160, rect.y, rect.width - 160);
        }
    } else {
        // Save button
        const saveText = this._isProcessing ? "PROCESSING..." : "SAVE CHANGES";
        this.drawText(saveText, rect.x, rect.y, rect.width, 'center');
    }
    this.resetTextColor();
};

Window_EditAccount.prototype.processOk = function() {
    if (this._isProcessing) return; // Prevent multiple clicks during processing
    
    const index = this.index();
    console.log("Button pressed - index:", index);
    SoundManager.playOk();
    
    if (index < this._fieldLabels.length) {
        const fieldName = this._fieldNames[index];
        const label = this._fieldLabels[index];
        const isPassword = fieldName.includes("Password");
        
        this.openTextInput(fieldName, label, isPassword);
    } else {
        // Save button pressed
        this.processAccountUpdate();
    }
};

Window_EditAccount.prototype.openTextInput = function(fieldName, label, isPassword = false) {
    this.deactivate();
    
    // Add a small delay to ensure deactivation takes effect
    setTimeout(() => {
        const currentValue = this._formData[fieldName] || "";
        const result = prompt("Enter " + label.replace(":", ""), currentValue);
        
        if (result !== null) {
            // Special validation for ID Number
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
            // Validation for required current fields
            else if ((fieldName === "currentUsername" || fieldName === "currentPassword") && result.trim() === "") {
                alert(`❌ ${label.replace(":", "")} is required!`);
                this.activate();
                return;
            }
            // Validation for new password length if provided
            else if (fieldName === "newPassword" && result.trim() !== "" && result.trim().length < 6) {
                alert("❌ New password must be at least 6 characters!");
                this.activate();
                return;
            }
            
            this._formData[fieldName] = result.trim();
        }
        
        this.refresh();
        this.activate();
    }, 100);
};

Window_EditAccount.prototype.validateIdNumber = function(idNumber) {
    const regex = /^\d{2}L-\d{4,5}$/;
    if (!regex.test(idNumber)) {
        alert("❌ Invalid ID Number format!\n\nPlease use ##L-#### format (e.g., 12A-5678)\nWhere:\n- First 2 characters are numbers (00)\n- Followed by a letter (A-Z) and hyphen (-)\n- Ending with 4 digits (0000)");
        return false;
    }
    return true;
};

Window_EditAccount.prototype.update = function() {
    Window_Selectable.prototype.update.call(this);
    if (this.active && Input.isTriggered('ok')) {
        this.processOk();
    } else if (this.active && Input.isTriggered('cancel')) {
        if (!this._isProcessing) { // Don't allow cancel during processing
            SoundManager.playCancel();
            SceneManager.pop();
        }
    }
};

Window_EditAccount.prototype.processAccountUpdate = function() {
    console.log("=== VALIDATING ACCOUNT UPDATE ===");
    
    // Clear any previous messages
    $gameMessage.clear();
    
    // Validate all required fields
    const errors = [];
    
    if (!this._formData.idNumber || this._formData.idNumber.trim() === "") {
        errors.push("• ID Number is required");
    } else if (!/^\d{2}[L]-\d{4,5}$/.test(this._formData.idNumber)) {
        errors.push("• ID Number must be in ##L-#### format (e.g., 12L-5678)");
    }
    
    if (!this._formData.currentUsername || this._formData.currentUsername.trim() === "") {
        errors.push("• Current Username is required");
    }
    
    if (!this._formData.currentPassword || this._formData.currentPassword.trim() === "") {
        errors.push("• Current Password is required");
    }
    
    // Validate new password length if provided
    if (this._formData.newPassword && this._formData.newPassword.length < 6) {
        errors.push("• New password must be at least 6 characters");
    }
    
    // Show errors if any
    if (errors.length > 0) {
        const errorMessage = [
            "\\C[2]Account Update Errors:\\C[0]",
            ...errors,
            "",
            "Press OK to continue editing..."
        ].join("\n");
        
        // Use the same error display system as student registration
        $dataSystem.optConfirmText = errorMessage;
        SceneManager.push(Scene_ConfirmContinue);
        return;
    }
    
    // If validation passes, proceed with update
    this.deactivate();
    this.performAccountUpdate();
};

Window_EditAccount.prototype.performAccountUpdate = function() {
    console.log("=== ACCOUNT UPDATE ATTEMPT ===");
    console.log("Form data:", this._formData);
    
    // Set processing state
    this._isProcessing = true;
    this.refresh();
    
    $gameMessage.add("Updating account information...");
    
    const params = {
        idNumber: this._formData.idNumber,
        currentUsername: this._formData.currentUsername,
        temporaryPassword: this._formData.currentPassword,
        newUsername: this._formData.newUsername || null,
        newPassword: this._formData.newPassword || null,
        isTemporaryPasswordUpdate: true // Flag to indicate this is from temp password flow
    };

    console.log("Sending request with params:", params);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'https://filibustero-web.com/php/auth.php?action=edit_account', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    const self = this;
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            console.log("Server response status:", xhr.status);
            console.log("Server response text:", xhr.responseText);
            
            self._isProcessing = false; // Reset processing state
            self.refresh();
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log("Parsed response:", response);
                    
                    if (response.success) {
                        console.log("Account update successful!");
                        SoundManager.playOk();
                        
                        // Clear messages and show success
                        $gameMessage.clear();
                        $gameMessage.add("✅ Account updated successfully!");
                        $gameMessage.add(response.message || "Your changes have been saved.");
                        
                        // Update auth system if username changed
                        if (window.FilibusteroAuth && window.FilibusteroAuth.currentUser) {
                            if (self._formData.newUsername) {
                                window.FilibusteroAuth.currentUser.username = self._formData.newUsername;
                            }
                        }
                        
                        // Wait 2 seconds then return to previous scene
                        setTimeout(() => {
                            SceneManager.pop();
                        }, 2000);
                        
                    } else {
                        console.log("Account update failed:", response.error || response.message);
                        
                        // Handle specific temporary password error
                        if (response.message && response.message.includes("temporary password")) {
                            const errorMessage = [
                                "\\C[2]❌ Temporary Password Error\\C[0]",
                                "",
                                "• The current password does not match",
                                "  the temporary password that has been",
                                "  generated for your account!",
                                "",
                                "• Please use the exact temporary password provided",
                                "• Check for any extra spaces or typing errors",
                                "",
                                "Press OK to try again..."
                            ].join("\n");
                            
                            $dataSystem = $dataSystem || {};
                            $dataSystem.optConfirmText = errorMessage;
                            $dataSystem.optConfirmHandler = null;
                            SceneManager.push(Scene_ConfirmContinue);
                        } else {
                            $gameMessage.clear();
                            $gameMessage.add("❌ Update failed: " + (response.error || response.message || "Unknown error"));
                        }
                        self.activate();
                    }
                } catch (e) {
                    console.error("JSON Parse Error:", e);
                    console.error("Raw response:", xhr.responseText);
                    $gameMessage.clear();
                    $gameMessage.add("❌ Server error: Invalid response format");
                    self.activate();
                }
            } else {
                console.error("HTTP Error:", xhr.status, xhr.statusText);
                $gameMessage.clear();
                $gameMessage.add("❌ Connection failed. Is the server running?");
                $gameMessage.add("Status: " + xhr.status);
                self.activate();
            }
        }
    };
    
    xhr.onerror = function() {
        console.error("Network error occurred");
        self._isProcessing = false;
        self.refresh();
        $gameMessage.clear();
        $gameMessage.add("❌ Network error: Cannot connect to server");
        self.activate();
    };
    
    xhr.ontimeout = function() {
        console.error("Request timed out");
        self._isProcessing = false;
        self.refresh();
        $gameMessage.clear();
        $gameMessage.add("❌ Request timed out");
        self.activate();
    };
    
    // Set timeout to match student registration
    xhr.timeout = 15000; // 15 seconds
    
    console.log("Sending request...");
    xhr.send(JSON.stringify(params));
};

// =============================================
// TUTORIAL SCENE
// =============================================

function Scene_Tutorial() {
    this.initialize(...arguments);
}

Scene_Tutorial.prototype = Object.create(Scene_MenuBase.prototype);
Scene_Tutorial.prototype.constructor = Scene_Tutorial;

Scene_Tutorial.prototype.create = function() {
    Scene_MenuBase.prototype.create.call(this);
    this.createHelpWindow();
    this.createTutorialWindow();
};

Scene_Tutorial.prototype.start = function() {
    Scene_MenuBase.prototype.start.call(this);
    if (this._tutorialWindow) {
        this._tutorialWindow.activate();
    }
};

Scene_Tutorial.prototype.createHelpWindow = function() {
    const rect = new Rectangle(0, 0, Graphics.boxWidth, 72);
    this._helpWindow = new Window_Help(rect);
    this._helpWindow.setText("Game Controls and Instructions");
    this.addWindow(this._helpWindow);
};

Scene_Tutorial.prototype.createTutorialWindow = function() {
    const rect = new Rectangle(50, 80, Graphics.boxWidth - 100, Graphics.boxHeight - 130);
    this._tutorialWindow = new Window_Tutorial(rect);
    this.addWindow(this._tutorialWindow);
};

Scene_Tutorial.prototype.update = function() {
    Scene_MenuBase.prototype.update.call(this);
    if (Input.isTriggered('cancel')) {
        SoundManager.playCancel();
        SceneManager.pop();
    }
};

// Tutorial Window
function Window_Tutorial() {
    this.initialize(...arguments);
}

Window_Tutorial.prototype = Object.create(Window_Selectable.prototype);
Window_Tutorial.prototype.constructor = Window_Tutorial;

Window_Tutorial.prototype.initialize = function(rect) {
    Window_Selectable.prototype.initialize.call(this, rect);
    this._tutorialText = [
        "\\C[3]=== GAME CONTROLS ===\\C[0]",
        "",
        "\\C[2]MOVEMENT:\\C[0]",
        "• Arrow Keys (dekstop) | Controller (mobile)", 
        "- Move your character around the map",
        "• Use all four directions to navigate",
        "",
        "\\C[2]INTERACT & PROGRESS:\\C[0]",
        "• SPACEBAR or ENTER (desktop) | OK [green]", 
        "- Skip dialogue and advance scenes", 
        "get items and talk to NPCs",
        "• X Key | Back Button [Red] - Cancel/Go back",
        "",
        "\\C[2]MENU:\\C[0]",
        "• ESC Key | Back Button [Red] - Open/Close pause menu",
        "",
        "\\C[2]GAMEPLAY TIPS:\\C[0]",
        "• Pay attention to NPC dialogue for clues",
        "• Explore all areas to find items and secrets",
        "• Save your progress frequently",
        
        "",
        "\\C[2]QUIZ SECTIONS:\\C[0]",
        "• Answer questions to progress the story",
        "• Your answers affect your final score",
        "• Take your time - no time limit per question",
        "",
        "Press ESC or Cancel button to return to menu.",
        "Enjoy your adventure!",
        "All rights reserved © Filibustero 2025"
    ];
    this.refresh();
};

Window_Tutorial.prototype.maxItems = function() {
    return this._tutorialText.length;
};

Window_Tutorial.prototype.itemHeight = function() {
    return 36;
};

Window_Tutorial.prototype.drawItem = function(index) {
    const rect = this.itemRect(index);
    this.drawTextEx(this._tutorialText[index], rect.x, rect.y, rect.width);
};

Window_Tutorial.prototype.update = function() {
    Window_Selectable.prototype.update.call(this);
};

// =============================================
// SCENE CALLING FUNCTION
// =============================================
function callEditAccountScene() {
    debugLog("Calling EditAccount scene");
    SceneManager.push(Scene_EditAccount);
}
})();