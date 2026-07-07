//=============================================================================
// Filibustero Progress Bar Plugin with Enhanced Database Sync
// Version: 2.1.0
// Enhanced to properly sync all game variables with database
//=============================================================================

/*:
 * @target MZ
 * @plugindesc [v2.1.0] Filibustero Progress Bar System with Enhanced Database Sync
 * @author YourName
 * @url 
 * @help FilibusteroProgressBarDB.js
 * 
 * @param serverUrl
 * @text Server URL
 * @desc Base URL for your server (e.g., https://filibustero-web.com/your-game/)
 * @type string
 * @default https://filibustero-web.com/filibustero/
 * 
 * @param showProgressBar
 * @text Show Progress Bar
 * @desc Show the progress bar on screen
 * @type boolean
 * @default true
 * 
 * @param progressBarX
 * @text Progress Bar X Position
 * @desc X position of the progress bar
 * @type number
 * @default 10
 * 
 * @param progressBarY
 * @text Progress Bar Y Position
 * @desc Y position of the progress bar
 * @type number
 * @default 10
 * 
 * @param coinVariable
 * @text Coin Variable ID
 * @desc Variable ID that stores coin count
 * @type variable
 * @default 14
 * 
 * @param scoreVariable
 * @text Score Variable ID
 * @desc Variable ID that stores player score
 * @type variable
 * @default 15
 * 
 * @param currentStageVariable
 * @text Current Stage Variable ID
 * @desc Variable ID that stores current stage
 * @type variable
 * @default 16
 * 
 * @param completedQuestsVariable
 * @text Completed Quests Variable ID
 * @desc Variable ID that stores number of completed quests
 * @type variable
 * @default 17
 * 
 * @param mapChangesVariable
 * @text Map Changes Variable ID
 * @desc Variable ID that stores how many times the player changed maps
 * @type variable
 * @default 18
 * 
 * @param coinCountVariable
 * @text Coin Count Variable ID
 * @desc Variable ID that stores total coins collected
 * @type variable
 * @default 19
 * 
 * @param collectedItemsVariable
 * @text Collected Items Variable ID
 * @desc Variable ID that stores number of collected items
 * @type variable
 * @default 21
 * 
 * @param playtimeVariable
 * @text Playtime Variable ID
 * @desc Variable ID that stores playtime in seconds
 * @type variable
 * @default 22
 * 
 * @param totalQuests
 * @text Total Number of Quests
 * @desc Total number of quests/events in the game
 * @type number
 * @default 39
 * 
 * @param toggleKey
 * @text Toggle Key
 * @desc Key to toggle progress bar visibility
 * @type string
 * @default tab
 * 
 * @param autoSaveInterval
 * @text Auto-Save Interval (seconds)
 * @desc How often to automatically save progress to database (0 = disabled)
 * @type number
 * @default 30
 * 
 * @command addCoin
 * @text Add Coins
 * @desc Add coins to the player's inventory
 * 
 * @arg amount
 * @text Amount
 * @desc Number of coins to add
 * @type number
 * @min 1
 * @default 1
 * 
 * @command addScore
 * @text Add Score
 * @desc Add points to the player's score
 * 
 * @arg amount
 * @text Amount
 * @desc Number of points to add
 * @type number
 * @min 1
 * @default 5
 * 
 * @command collectItem
 * @text Collect Item
 * @desc Increment the collected items counter
 * 
 * @command setStage
 * @text Set Current Stage
 * @desc Set the current stage/level
 * 
 * @arg stage
 * @text Stage Number
 * @desc Stage number to set (1-39)
 * @type number
 * @min 1
 * @max 39
 * @default 1
 * 
 * @command completeQuest
 * @text Complete Quest
 * @desc Mark a quest as completed and update progress
 * 
 * @command saveProgress
 * @text Save Progress to Server
 * @desc Manually save current progress to the database
 * 
 * @command loadProgress
 * @text Load Progress from Server
 * @desc Load saved progress from the database
 * 
 * @command toggleProgressBar
 * @text Toggle Progress Bar
 * @desc Show or hide the progress bar display
 * 
 * This plugin creates a progress bar system for the Filibustero game with enhanced database sync.
 * It automatically loads progress data on login and saves all changes to the server.
 */

(() => {
    'use strict';
    
    const pluginName = 'FilibusteroProgressBarDB';
    const parameters = PluginManager.parameters(pluginName);
    
    const serverUrl = parameters['serverUrl'] || 'https://filibustero-web.com/php/';
    const showProgressBar = parameters['showProgressBar'] === 'true';
    const progressBarX = Number(parameters['progressBarX'] || 10);
    const progressBarY = Number(parameters['progressBarY'] || 10);
    const coinVariable = Number(parameters['coinVariable'] || 14);
    const scoreVariable = Number(parameters['scoreVariable'] || 15);
    const currentStageVariable = Number(parameters['currentStageVariable'] || 16);
    const completedQuestsVariable = Number(parameters['completedQuestsVariable'] || 17);
    const mapChangesVariable = Number(parameters['mapChangesVariable'] || 18);
    const coinCountVariable = Number(parameters['coinCountVariable'] || 19);
    const collectedItemsVariable = Number(parameters['collectedItemsVariable'] || 21);
    const playtimeVariable = Number(parameters['playtimeVariable'] || 22);
    const totalQuests = Number(parameters['totalQuests'] || 39);
    const toggleKey = parameters['toggleKey'] || 'tab';
    const autoSaveInterval = Number(parameters['autoSaveInterval'] || 30);
    
    let progressBarVisible = showProgressBar;
    let progressBarWindow = null;
    let currentUserId = null;
    let lastSaveTime = 0;
    let autoSaveTimer = null;
    let initialLoadComplete = false;
    
    //-----------------------------------------------------------------------------
    // Enhanced Database Communication Functions
    //-----------------------------------------------------------------------------
    
    function makeRequest(url, data, callback) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    callback(response);
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.error('Response text:', xhr.responseText);
                    callback({ success: false, error: 'Invalid server response' });
                }
            }
        };
        
        xhr.onerror = function() {
            console.error('Network error occurred');
            callback({ success: false, error: 'Network error' });
        };
        
        const formData = new URLSearchParams();
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        xhr.send(formData.toString());
    }
    
    function loadProgressFromServer(userId) {
        if (!userId) return;
        
        console.log('Loading progress from server for user:', userId);
        const url = serverUrl + 'auth.php';
        makeRequest(url, { 
            action: 'get_progress',
            user_id: userId 
        }, function(response) {
            if (response.success && response.progress_data) {
                const progress = response.progress_data;
                
                console.log('Progress data received:', progress);
                
                // Load ALL progress data into game variables
                $gameVariables.setValue(coinVariable, progress.coins || 0);
                $gameVariables.setValue(scoreVariable, progress.score || 0);
                $gameVariables.setValue(currentStageVariable, progress.current_stage || 0);
                $gameVariables.setValue(completedQuestsVariable, progress.completed_quests || 0);
                $gameVariables.setValue(mapChangesVariable, progress.map_changes || 0);
                $gameVariables.setValue(coinCountVariable, progress.coin_count || 0);
                $gameVariables.setValue(collectedItemsVariable, progress.collected_items || 0);
                
                // Update playtime if available
                if (progress.playtime_seconds) {
                    $gameSystem._playtimeSeconds = progress.playtime_seconds;
                }
                
                initialLoadComplete = true;
                
                console.log('All progress variables loaded:');
                console.log('- Coins:', $gameVariables.value(coinVariable));
                console.log('- Score:', $gameVariables.value(scoreVariable));
                console.log('- Stage:', $gameVariables.value(currentStageVariable));
                console.log('- Completed Quests:', $gameVariables.value(completedQuestsVariable));
                console.log('- Map Changes:', $gameVariables.value(mapChangesVariable));
                console.log('- Coin Count:', $gameVariables.value(coinCountVariable));
                console.log('- Collected Items:', $gameVariables.value(collectedItemsVariable));
                
                if (progressBarWindow) {
                    progressBarWindow.refresh();
                }
                
                $gameMessage.add('\\C[3]Progress loaded from server!\\C[0]');
            } else {
                console.log('No progress data found on server or error occurred:', response.error);
                initialLoadComplete = true;
            }
        });
    }
    
    function saveProgressToServer(userId, forceUpdate = false) {
        if (!userId || !initialLoadComplete) return;
        
        const currentTime = Date.now();
        if (!forceUpdate && (currentTime - lastSaveTime) < 5000) {
            return;
        }
        
        const progressData = {
            action: 'update_progress',
            user_id: userId,
            coins: $gameVariables.value(coinVariable) || 0,
            score: $gameVariables.value(scoreVariable) || 0,
            current_stage: $gameVariables.value(currentStageVariable) || 1,
            completed_quests: $gameVariables.value(completedQuestsVariable) || 0,
            map_changes: $gameVariables.value(mapChangesVariable) || 0,
            coin_count: $gameVariables.value(coinCountVariable) || 0,
            collected_items: $gameVariables.value(collectedItemsVariable) || 0,
            playtime_seconds: (() => {
                try {
                    if ($gameSystem.playtimeSeconds && typeof $gameSystem.playtimeSeconds === 'function') {
                        return Math.floor($gameSystem.playtimeSeconds());
                    } else if ($gameSystem._playtimeSeconds) {
                        return Math.floor($gameSystem._playtimeSeconds);
                    } else {
                        return 0;
                    }
                } catch (e) {
                    return 0;
                }
            })()
        };
        
        console.log('Saving progress to server:', progressData);
        
        const url = serverUrl + 'auth.php';
        makeRequest(url, progressData, function(response) {
            if (response.success) {
                lastSaveTime = currentTime;
                console.log('Progress saved successfully');
                
                if (forceUpdate) {
                    $gameMessage.add('\\C[3]Progress saved to server!\\C[0]');
                }
            } else {
                console.error('Failed to save progress:', response.error);
                if (forceUpdate) {
                    $gameMessage.add('\\C[2]Failed to save progress!\\C[0]');
                }
            }
        });
    }
    
    //-----------------------------------------------------------------------------
    // User Authentication Integration
    //-----------------------------------------------------------------------------
    
    window.setCurrentUserId = function(userId) {
        currentUserId = userId;
        initialLoadComplete = false;
        console.log('Current user ID set to:', userId);
        
        // Load progress from server with a delay to ensure game is ready
        setTimeout(() => {
            loadProgressFromServer(userId);
            
            // Start auto-save timer after loading
            if (autoSaveInterval > 0) {
                startAutoSave();
            }
        }, 1000);
    };
    
    window.clearCurrentUserId = function() {
        if (currentUserId) {
            saveProgressToServer(currentUserId, true);
        }
        currentUserId = null;
        initialLoadComplete = false;
        stopAutoSave();
    };
    
    function startAutoSave() {
        if (autoSaveTimer) {
            clearInterval(autoSaveTimer);
        }
        
        if (autoSaveInterval > 0 && currentUserId) {
            autoSaveTimer = setInterval(() => {
                if (initialLoadComplete) {
                    saveProgressToServer(currentUserId);
                }
            }, autoSaveInterval * 1000);
            console.log('Auto-save started with interval:', autoSaveInterval, 'seconds');
        }
    }
    
    function stopAutoSave() {
        if (autoSaveTimer) {
            clearInterval(autoSaveTimer);
            autoSaveTimer = null;
            console.log('Auto-save stopped');
        }
    }

    //-----------------------------------------------------------------------------
    // Initialize variables on new game/load
    //-----------------------------------------------------------------------------
    
    const _DataManager_createGameObjects = DataManager.createGameObjects;
    DataManager.createGameObjects = function() {
        _DataManager_createGameObjects.call(this);
        
        // Set initial values if not already set (only for new games)
        if ($gameVariables.value(currentStageVariable) === 0) {
            $gameVariables.setValue(currentStageVariable, 1);
        }
    };

    //-----------------------------------------------------------------------------
    // Enhanced Progress Bar Window
    //-----------------------------------------------------------------------------
    
   class Window_ProgressBar extends Window_Base {
    constructor() {
        // Define dimensions first
        const fullWidth = 380;
        const fullHeight = 160;
        
        // Create window rectangle and call super first
        const rect = new Rectangle(progressBarX, progressBarY, fullWidth, fullHeight);
        super(rect);
        
        // Now set instance properties
        this._isMinimized = false;
        this._fullWidth = fullWidth;
        this._fullHeight = fullHeight;
        this._minWidth = 200;
        this._minHeight = 40;
        
        this.opacity = 220;
        
        // Button properties
        this._buttonWidth = 20;
        this._buttonHeight = 20;
        this._buttonMargin = 5;
        
        this.refresh();
    }
    
    update() {
        super.update();
        this.updateMouseInput();
    }
    
    updateMouseInput() {
        if (TouchInput.isTriggered()) {
            const buttonRect = this.getMinimizeButtonRect();
            const mouseX = TouchInput.x - this.x;
            const mouseY = TouchInput.y - this.y;
            
            if (mouseX >= buttonRect.x && mouseX <= buttonRect.x + buttonRect.width &&
                mouseY >= buttonRect.y && mouseY <= buttonRect.y + buttonRect.height) {
                this.toggleMinimize();
            }
        }
    }
    
    getMinimizeButtonRect() {
        return {
            x: this.contentsWidth() - this._buttonWidth - this._buttonMargin,
            y: this._buttonMargin,
            width: this._buttonWidth,
            height: this._buttonHeight
        };
    }
    
    toggleMinimize() {
        this._isMinimized = !this._isMinimized;
        
        if (this._isMinimized) {
            // Minimize - keep same X position
            this.move(this.x, this.y, this._minWidth, this._minHeight);
        } else {
            // Maximize - restore full size
            this.move(this.x, this.y, this._fullWidth, this._fullHeight);
        }
        
        this.refresh();
    }
    
    refresh() {
        this.contents.clear();
        this.drawMinimizeButton();
        
        if (this._isMinimized) {
            this.drawMinimizedContent();
        } else {
            this.drawProgressInfo();
        }
    }
    
    drawMinimizeButton() {
        const buttonRect = this.getMinimizeButtonRect();
        const x = buttonRect.x;
        const y = buttonRect.y;
        const width = buttonRect.width;
        const height = buttonRect.height;
        
        // Button background
        this.contents.fillRect(x, y, width, height, ColorManager.dimColor1());
        
        // Button border
        this.contents.strokeRect(x, y, width, height, ColorManager.normalColor(), 2);
        
        // Button symbol
        this.changeTextColor(ColorManager.normalColor());
        this.contents.fontSize = 14;
        
        if (this._isMinimized) {
            // Draw maximize symbol (square)
            this.contents.fillRect(x + 4, y + 4, width - 8, height - 8, ColorManager.normalColor());
        } else {
            // Draw minimize symbol (line)
            this.contents.fillRect(x + 3, y + height/2 - 1, width - 6, 2, ColorManager.normalColor());
        }
        
        this.contents.fontSize = 28; // Reset font size
        this.resetTextColor();
    }
    
    drawMinimizedContent() {
        const completedQuests = $gameVariables.value(completedQuestsVariable) || 0;
        const progressPercent = Math.min(Math.floor((completedQuests / totalQuests) * 100), 100);
        const currentStage = $gameVariables.value(currentStageVariable) || 0;
        
        // Compact progress display (leave space for button)
        const buttonRect = this.getMinimizeButtonRect();
        const availableWidth = buttonRect.x - 10;
        
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Progress:", 5, 8, 60);
        this.changeTextColor(ColorManager.crisisColor());
        this.drawText(`${progressPercent}%`, 65, 8, 40);
        
        // Stage info if there's space
        if (availableWidth > 120) {
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(`Stage ${currentStage}`, 110, 8, 60);
        }
        
        this.resetTextColor();
    }
    
    drawProgressInfo() {
        const coins = $gameVariables.value(coinVariable) || 0;
        const score = $gameVariables.value(scoreVariable) || 0;
        const currentStage = $gameVariables.value(currentStageVariable) || 0;
        const completedQuests = $gameVariables.value(completedQuestsVariable) || 0;
        const collectedItems = $gameVariables.value(collectedItemsVariable) || 0;
        const coinCount = $gameVariables.value(coinCountVariable) || 0;
        
        // Calculate progress percentage
        const progressPercent = Math.min(Math.floor((completedQuests / totalQuests) * 100), 100);

        console.log('Progress Bar - Completed Quests:', completedQuests, 'Total:', totalQuests, 'Percent:', progressPercent);
        
        // Draw title with minimize hint
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Story Progress", 10, 0, 200);
        
        // Minimize hint in top right
        this.contents.fontSize = 16;
        this.changeTextColor(ColorManager.textColor(8)); // Gray
        this.drawText("[PgUp] Minimize", this.contentsWidth() - 120, 2, 120, "right");
        this.contents.fontSize = 28; // Reset font size
        
        // Draw progress bar
        const barWidth = 250;
        const barHeight = 30;
        const barX = 30;
        const barY = 30;
        
        // Background
        this.contents.fillRect(barX, barY, barWidth, barHeight, ColorManager.gaugeBackColor());
        
        // Progress fill
        const fillWidth = Math.floor((barWidth * progressPercent) / 100);
        const progressColor = progressPercent === 100 ? ColorManager.powerUpColor() : ColorManager.hpGaugeColor1();
        this.contents.fillRect(barX, barY, fillWidth, barHeight, progressColor);
        
        // Progress text
        this.changeTextColor(ColorManager.normalColor());
        this.drawText(`${progressPercent}%`, barX + barWidth + 10, barY - 5, 60);
        
        // Stage info
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Stage:", 10, 65, 60);
        this.changeTextColor(ColorManager.normalColor());
        this.drawText(`${currentStage}/29`, 70, 65, 60);
        
        // Quests
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Quests:", 150, 65, 60);
        this.changeTextColor(ColorManager.normalColor());
        this.drawText(`${completedQuests}/39`, 210, 65, 80);
        
        // Coins (current)
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Coins:", 10, 90, 60);
        this.changeTextColor(ColorManager.textColor(14));
        this.drawText(coins.toString(), 70, 90, 60);
        
        // Total coins collected
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Total:", 150, 90, 60);
        this.changeTextColor(ColorManager.textColor(6));
        this.drawText(coinCount.toString(), 210, 90, 60);
        
        // Score
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Score:", 10, 115, 60);
        this.changeTextColor(ColorManager.normalColor());
        this.drawText(score.toString(), 70, 115, 100);
        
        // Items collected
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Items:", 150, 115, 60);
        this.changeTextColor(ColorManager.textColor(3));
        this.drawText(collectedItems.toString(), 210, 115, 60);
        
        // Connection status and playtime
        this.changeTextColor(currentUserId ? ColorManager.textColor(3) : ColorManager.textColor(2));
        this.drawText(currentUserId ? "Online" : "Offline", 320, 65, 100);
        
        // Playtime
        const playtime = $gameSystem.playtimeText();
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Time:", 320, 90, 50);
        this.changeTextColor(ColorManager.normalColor());
        this.contents.fontSize = 18;
        this.drawText(playtime, 320, 115, 120);
        this.contents.fontSize = 28; // Reset font size
        
        this.resetTextColor();
    }
}


    //-----------------------------------------------------------------------------
    // Enhanced Plugin Commands
    //-----------------------------------------------------------------------------
    
    PluginManager.registerCommand(pluginName, "addCoin", args => {
        const amount = Number(args.amount) || 1;
        const currentCoins = $gameVariables.value(coinVariable);
        const currentCoinCount = $gameVariables.value(coinCountVariable);
        
        $gameVariables.setValue(coinVariable, currentCoins + amount);
        $gameVariables.setValue(coinCountVariable, currentCoinCount + amount);
        
        if (progressBarWindow) progressBarWindow.refresh();
        
        if (currentUserId && initialLoadComplete) {
            setTimeout(() => saveProgressToServer(currentUserId), 100);
        }
    });
    
    PluginManager.registerCommand(pluginName, "addScore", args => {
        const amount = Number(args.amount) || 5;
        const currentScore = $gameVariables.value(scoreVariable);
        $gameVariables.setValue(scoreVariable, Math.max(0, currentScore + amount));
        if (progressBarWindow) progressBarWindow.refresh();
        
        if (currentUserId && initialLoadComplete) {
            setTimeout(() => saveProgressToServer(currentUserId), 100);
        }
    });

    PluginManager.registerCommand(pluginName, "collectItem", args => {
        const currentItems = $gameVariables.value(collectedItemsVariable);
        $gameVariables.setValue(collectedItemsVariable, currentItems + 1);
        
        if (progressBarWindow) progressBarWindow.refresh();
        
        if (currentUserId && initialLoadComplete) {
            setTimeout(() => saveProgressToServer(currentUserId), 100);
        }
    });
    
    PluginManager.registerCommand(pluginName, "setStage", args => {
        const stage = Number(args.stage) || 1;
        $gameVariables.setValue(currentStageVariable, Math.min(13, Math.max(1, stage)));
        if (progressBarWindow) progressBarWindow.refresh();
        
        if (currentUserId && initialLoadComplete) {
            setTimeout(() => saveProgressToServer(currentUserId), 100);
        }
    });
    
    PluginManager.registerCommand(pluginName, "completeQuest", args => {
        const currentCompleted = $gameVariables.value(completedQuestsVariable);
        const newCompleted = Math.min(totalQuests, currentCompleted + 1);
        $gameVariables.setValue(completedQuestsVariable, newCompleted);
        
        // Auto-advance stage based on quest completion
        const newStage = Math.floor(newCompleted / Math.max(1, Math.floor(totalQuests / 39))) + 1;
        $gameVariables.setValue(currentStageVariable, Math.min(39, newStage));

        if (progressBarWindow) progressBarWindow.refresh();
        
        if (currentUserId && initialLoadComplete) {
            setTimeout(() => saveProgressToServer(currentUserId), 100);
        }
    });
    
    PluginManager.registerCommand(pluginName, "saveProgress", args => {
        if (currentUserId) {
            saveProgressToServer(currentUserId, true);
        } else {
            $gameMessage.add('\\C[2]Not connected to server!\\C[0]');
        }
    });
    
    PluginManager.registerCommand(pluginName, "loadProgress", args => {
        if (currentUserId) {
            loadProgressFromServer(currentUserId);
        } else {
            $gameMessage.add('\\C[2]Not connected to server!\\C[0]');
        }
    });
    
    PluginManager.registerCommand(pluginName, "toggleProgressBar", args => {
        progressBarVisible = !progressBarVisible;
        if (progressBarWindow) {
            if (progressBarVisible) {
                progressBarWindow.show();
            } else {
                progressBarWindow.hide();
            }
        }
    });

    PluginManager.registerCommand(pluginName, "completeQuest", args => {
    const currentCompleted = $gameVariables.value(completedQuestsVariable);
    const newCompleted = Math.min(totalQuests, currentCompleted + 1);
    $gameVariables.setValue(completedQuestsVariable, newCompleted);
    
    // Force immediate save instead of delayed
    if (currentUserId && initialLoadComplete) {
        saveProgressToServer(currentUserId, true); // true forces immediate save
    }
    
    if (progressBarWindow) progressBarWindow.refresh();
});

    //-----------------------------------------------------------------------------
    // Scene Integration
    //-----------------------------------------------------------------------------
    
    const _Scene_Map_createAllWindows = Scene_Map.prototype.createAllWindows;
    Scene_Map.prototype.createAllWindows = function() {
        _Scene_Map_createAllWindows.call(this);
        this.createProgressBarWindow();
    };
    
    Scene_Map.prototype.createProgressBarWindow = function() {
        if (showProgressBar) {
            progressBarWindow = new Window_ProgressBar();
            this.addChild(progressBarWindow);
            if (!progressBarVisible) {
                progressBarWindow.hide();
            }
        }
    };
    
    const _Scene_Map_update = Scene_Map.prototype.update;
    Scene_Map.prototype.update = function() {
        _Scene_Map_update.call(this);
        this.updateProgressBarToggle();
    };
    
    Scene_Map.prototype.updateProgressBarToggle = function() {
        if (Input.isTriggered(toggleKey) && progressBarWindow) {
            progressBarVisible = !progressBarVisible;
            if (progressBarVisible) {
                progressBarWindow.show();
            } else {
                progressBarWindow.hide();
            }
        }
    };

    //-----------------------------------------------------------------------------
    // Enhanced Game Variables Hook with Database Sync
    //-----------------------------------------------------------------------------
    
    const _Game_Variables_setValue = Game_Variables.prototype.setValue;
    Game_Variables.prototype.setValue = function(variableId, value) {
        const oldValue = this._data[variableId];
        _Game_Variables_setValue.call(this, variableId, value);
        
        // Track all game progress variables
        const trackedVariables = [
            coinVariable, scoreVariable, currentStageVariable, 
            completedQuestsVariable, mapChangesVariable, coinCountVariable,
            collectedItemsVariable
        ];
        
        if (trackedVariables.includes(variableId)) {
            if (progressBarWindow) {
                progressBarWindow.refresh();
            }
            
            // Only save if value actually changed and initial load is complete
            if (oldValue !== value && currentUserId && initialLoadComplete) {
                setTimeout(() => saveProgressToServer(currentUserId), 200);
            }
        }
    };

    //-----------------------------------------------------------------------------
    // Map Transfer Hook
    //-----------------------------------------------------------------------------
    
    const _Game_Player_performTransfer = Game_Player.prototype.performTransfer;
    Game_Player.prototype.performTransfer = function() {
        _Game_Player_performTransfer.call(this);
        
        // Only track map changes after initial load
        if (initialLoadComplete) {
            const currentMapChanges = $gameVariables.value(mapChangesVariable);
            $gameVariables.setValue(mapChangesVariable, currentMapChanges + 1);
        }
    };

    //-----------------------------------------------------------------------------
    // Save/Load Integration
    //-----------------------------------------------------------------------------
    
    const _Scene_Save_onSavefileOk = Scene_Save.prototype.onSavefileOk;
    Scene_Save.prototype.onSavefileOk = function() {
        _Scene_Save_onSavefileOk.call(this);
        
        if (currentUserId && initialLoadComplete) {
            saveProgressToServer(currentUserId, true);
        }
    };

    //-----------------------------------------------------------------------------
    // Enhanced Utility Functions
    //-----------------------------------------------------------------------------
    
    window.FilibusteroProgress = {
        addCoin: function(amount = 1) {
            const currentCoins = $gameVariables.value(coinVariable);
            const currentCoinCount = $gameVariables.value(coinCountVariable);
            $gameVariables.setValue(coinVariable, currentCoins + amount);
            $gameVariables.setValue(coinCountVariable, currentCoinCount + amount);
        },
        
        subtractCoin: function(amount = 1) {
            const currentCoins = $gameVariables.value(coinVariable);
            $gameVariables.setValue(coinVariable, Math.max(0, currentCoins - amount));
        },
        
        addScore: function(amount = 5) {
            const currentScore = $gameVariables.value(scoreVariable);
            $gameVariables.setValue(scoreVariable, Math.max(0, currentScore + amount));
        },
        
        subtractScore: function(amount = 1) {
            const currentScore = $gameVariables.value(scoreVariable);
            $gameVariables.setValue(scoreVariable, Math.max(0, currentScore - amount));
        },
        
        collectItem: function() {
            const currentItems = $gameVariables.value(collectedItemsVariable);
            $gameVariables.setValue(collectedItemsVariable, currentItems + 1);
        },
        
        completeQuest: function() {
            const currentCompleted = $gameVariables.value(completedQuestsVariable);
            const newCompleted = Math.min(totalQuests, currentCompleted + 1);
            $gameVariables.setValue(completedQuestsVariable, newCompleted);
            
            // Auto-advance stage
            const newStage = Math.floor(newCompleted / Math.max(1, Math.floor(totalQuests / 39)));
            $gameVariables.setValue(currentStageVariable, Math.min(39, newStage));
        },
        
        setStage: function(stage) {
            $gameVariables.setValue(currentStageVariable, Math.min(39, Math.max(1, stage)));
        },
        
        // Getters
        getProgress: function() {
            const completedQuests = $gameVariables.value(completedQuestsVariable);
            return Math.min(Math.floor((completedQuests / totalQuests) * 100), 100);
        },
        
        getCoins: function() { return $gameVariables.value(coinVariable); },
        getScore: function() { return $gameVariables.value(scoreVariable); },
        getCurrentStage: function() { return $gameVariables.value(currentStageVariable); },
        getCompletedQuests: function() { return $gameVariables.value(completedQuestsVariable); },
        getCollectedItems: function() { return $gameVariables.value(collectedItemsVariable); },
        getCoinCount: function() { return $gameVariables.value(coinCountVariable); },
        getMapChanges: function() { return $gameVariables.value(mapChangesVariable); },
        
        // Database functions
        saveToServer: function() {
            if (currentUserId && initialLoadComplete) {
                saveProgressToServer(currentUserId, true);
            } else {
                console.log('Cannot save - no user logged in or initial load not complete');
            }
        },
        
        loadFromServer: function() {
            if (currentUserId) {
                loadProgressFromServer(currentUserId);
            } else {
                console.log('Cannot load - no user logged in');
            }
        },
        
        setUserId: function(userId) { setCurrentUserId(userId); },
        getCurrentUserId: function() { return currentUserId; },
        isLoadComplete: function() { return initialLoadComplete; }
    };

})();