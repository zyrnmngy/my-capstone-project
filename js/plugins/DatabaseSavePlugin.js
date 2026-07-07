//=============================================================================
// Filibustero Progress Bar Plugin - Localhost Development Version
// Version: 2.0.0-dev
// Author: Your Name
//=============================================================================

/*:
 * @target MZ
 * @plugindesc [v2.0.0-dev] Filibustero Progress Bar - Localhost Development
 * @author YourName
 * @help FilibusteroProgressBar.js
 * 
 * @param developmentMode
 * @text Development Mode
 * @desc Enable development mode for localhost testing
 * @type boolean
 * @default true
 * 
 * @param showProgressBar
 * @text Show Progress Bar
 * @desc Show the progress bar on screen (initial state - can be toggled by clicking)
 * @type boolean
 * @default false
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
 * @param apiUrl
 * @text API Base URL
 * @desc Base URL for your game progress API (only used when not in development mode)
 * @type string
 * @default https://filibustero-web.com//filibustero/game_progress_api.php
 * 
 * @param playerId
 * @text Player ID
 * @desc Current player's ID from database
 * @type number
 * @default 1
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
 * @param correctAnswersVariable
 * @text Correct Answers Variable ID
 * @desc Variable ID that stores number of correct answers
 * @type variable
 * @default 19
 * 
 * @param totalQuestionsVariable
 * @text Total Questions Answered Variable ID
 * @desc Variable ID that stores total questions answered
 * @type variable
 * @default 20
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
 * LOCALHOST DEVELOPMENT VERSION
 * 
 * This version works with localhost by:
 * 1. Logging all progress to browser console
 * 2. Storing progress in localStorage for persistence
 * 3. Showing detailed debug information
 * 4. Preparing data for when you deploy online
 * 
 * Variable Setup Required:
 * - Coin Variable (default: 14)
 * - Score Variable (default: 15) 
 * - Current Stage Variable (default: 16)
 * - Completed Quests Variable (default: 17)
 * - Correct Answers Variable (default: 19)
 * - Total Questions Answered Variable (default: 20)
 */

(() => {
    'use strict';
    
    const pluginName = 'FilibusteroProgressBar';
    const parameters = PluginManager.parameters(pluginName);
    
    const developmentMode = parameters['developmentMode'] === 'true';
    const showProgressBar = parameters['showProgressBar'] === 'true';
    const progressBarX = Number(parameters['progressBarX'] || 10);
    const progressBarY = Number(parameters['progressBarY'] || 10);
    const apiUrl = parameters['apiUrl'] || 'https://filibustero-web.com//php/progress_sync.php';
    const playerId = Number(parameters['playerId'] || 1);
    const coinVariable = Number(parameters['coinVariable'] || 14);
    const scoreVariable = Number(parameters['scoreVariable'] || 15);
    const currentStageVariable = Number(parameters['currentStageVariable'] || 16);
    const completedQuestsVariable = Number(parameters['completedQuestsVariable'] || 17);
    const correctAnswersVariable = Number(parameters['correctAnswersVariable'] || 19);
    const totalQuestionsVariable = Number(parameters['totalQuestionsVariable'] || 20);
    const totalQuests = Number(parameters['totalQuests'] || 39);

        // Persistent state variables   
    let progressBarVisible = showProgressBar;
     let progressBarMinimized = false;
    let progressBarWindow = null;
    let gameStartTime = Date.now();
    let progressHistory = [];
    
    // Load persistent state from localStorage
    try {
        const savedState = localStorage.getItem('filibustero_progress_ui_state');
        if (savedState) {
            const state = JSON.parse(savedState);
            progressBarVisible = state.visible !== undefined ? state.visible : progressBarVisible;
            progressBarMinimized = state.minimized !== undefined ? state.minimized : progressBarMinimized;
        }
    } catch (e) {
        console.warn('Could not load UI state from localStorage:', e);
    }

    // Save persistent state to localStorage
    function saveUIState() {
        try {
            const state = {
                visible: progressBarVisible,
                minimized: progressBarMinimized
            };
            localStorage.setItem('filibustero_progress_ui_state', JSON.stringify(state));
        } catch (e) {
            console.warn('Could not save UI state to localStorage:', e);
        }
    }
    // Development Mode Functions
    //-----------------------------------------------------------------------------
    
    function logProgressUpdate(action, data) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = {
            timestamp: timestamp,
            action: action,
            data: { ...data },
            gameTime: Math.floor((Date.now() - gameStartTime) / 1000)
        };
        
        progressHistory.push(logEntry);
        
        console.group(`🎮 FILIBUSTERO PROGRESS UPDATE - ${timestamp}`);
        console.log('Action:', action);
        console.log('Player ID:', data.player_id);
        console.log('Progress Data:', data);
        console.log('Progress %:', Math.min(Math.floor((data.completed_quests / totalQuests) * 100), 100) + '%');
        console.groupEnd();
        
        // Store in localStorage for persistence
        try {
            localStorage.setItem('filibustero_progress_current', JSON.stringify(data));
            localStorage.setItem('filibustero_progress_history', JSON.stringify(progressHistory.slice(-50))); // Keep last 50 entries
        } catch (e) {
            console.warn('Could not save to localStorage:', e);
        }
    }
    
    function getCurrentProgressData() {
        return {
            player_id: playerId,
            coins: $gameVariables.value(coinVariable) || 0,
            score: $gameVariables.value(scoreVariable) || 0,
            current_stage: Math.max(0, $gameVariables.value(currentStageVariable) || 0),
            completed_quests: $gameVariables.value(completedQuestsVariable) || 0,
            correct_answers: $gameVariables.value(correctAnswersVariable) || 0,
            total_questions_answered: $gameVariables.value(totalQuestionsVariable) || 0,
            play_time: Math.floor((Date.now() - gameStartTime) / 1000),
            timestamp: new Date().toISOString()
        };
    }
    
    function syncProgressToDatabase(force = false, action = 'auto_update') {
        const currentData = getCurrentProgressData();
        
        if (developmentMode) {
            // Development mode: Log and store locally
            logProgressUpdate(action, currentData);
            
            if (progressBarWindow) {
                progressBarWindow.showSyncIndicator();
            }
            
            return Promise.resolve({ success: true, mode: 'development' });
        } else {
            // Production mode: Actual HTTP request
            return makeApiRequest('update_progress', 'POST', currentData)
                .then(response => {
                    if (response.success) {
                        logProgressUpdate(action + '_synced', currentData);
                        if (progressBarWindow) {
                            progressBarWindow.showSyncIndicator();
                        }
                    }
                    return response;
                })
                .catch(error => {
                    console.error('❌ Sync failed:', error);
                    // Fallback to local storage in case of network issues
                    logProgressUpdate(action + '_failed', currentData);
                    throw error;
                });
        }
    }
    
    function makeApiRequest(endpoint, method = 'GET', data = null) {
        return new Promise((resolve, reject) => {
            console.log('🌐 API Request:', { endpoint, method, url: `${apiUrl}?action=${endpoint}` });
            
            const xhr = new XMLHttpRequest();
            const url = `${apiUrl}?action=${endpoint}`;
            
            xhr.open(method, url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    console.log('📡 Response:', { status: xhr.status, response: xhr.responseText });
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (e) {
                            console.error('❌ JSON parse error:', e, 'Response:', xhr.responseText);
                            reject(new Error('Invalid JSON response'));
                        }
                    } else {
                        reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                    }
                }
            };
            
            xhr.onerror = () => reject(new Error('Network error'));
            xhr.ontimeout = () => reject(new Error('Request timeout'));
            xhr.timeout = 10000;
            
            if (data && method === 'POST') {
                xhr.send(JSON.stringify(data));
            } else {
                xhr.send();
            }
        });
    }

    // Initialize variables and load saved progress
    const _DataManager_createGameObjects = DataManager.createGameObjects;
    DataManager.createGameObjects = function() {
        _DataManager_createGameObjects.call(this);
        
        // Load from localStorage if available (development mode)
        if (developmentMode) {
            try {
                const savedProgress = localStorage.getItem('filibustero_progress_current');
                if (savedProgress) {
                    const data = JSON.parse(savedProgress);
                    console.log('📂 Loading saved progress from localStorage:', data);
                    
                    $gameVariables.setValue(coinVariable, data.coins || 0);
                    $gameVariables.setValue(scoreVariable, data.score || 0);
                    $gameVariables.setValue(currentStageVariable, data.current_stage || 0);
                    $gameVariables.setValue(completedQuestsVariable, data.completed_quests || 0);
                    $gameVariables.setValue(correctAnswersVariable, data.correct_answers || 0);
                    $gameVariables.setValue(totalQuestionsVariable, data.total_questions_answered || 0);
                }
            } catch (e) {
                console.warn('Could not load saved progress:', e);
            }
        }
        
        // Set initial values if not already set
        if ($gameVariables.value(currentStageVariable)) {
            $gameVariables.setValue(currentStageVariable, 0);
        }
        if ($gameVariables.value(scoreVariable) === 0) {
            $gameVariables.setValue(scoreVariable, 0);
        }
        if ($gameVariables.value(correctAnswersVariable) === 0) {
            $gameVariables.setValue(correctAnswersVariable, 0);
        }
        if ($gameVariables.value(totalQuestionsVariable) === 0) {
            $gameVariables.setValue(totalQuestionsVariable, 0);
        }
        
        console.log('🎮 Filibustero Progress System initialized');
        console.log('Mode:', developmentMode ? 'Development (localhost)' : 'Production');
        console.log('Player ID:', playerId);
    };

    //-----------------------------------------------------------------------------
    // Progress Bar Window (Updated for Click-to-Toggle)
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
            this._syncIndicatorTimer = 0;
            
            // Set initial size based on minimized state
            if (this._isMinimized) {
                this.move(this.x, this.y, this._minWidth, this._minHeight);
            }
            
            this.refresh();
        }
        
        update() {
            super.update();
            this.updateMouseInput();
            this.updateSyncIndicator();
        }
        
        updateSyncIndicator() {
            if (this._syncIndicatorTimer > 0) {
                this._syncIndicatorTimer--;
                if (this._syncIndicatorTimer === 0) {
                    this.refresh();
                }
            }
        }
        
        showSyncIndicator() {
            this._syncIndicatorTimer = 120; // Show for 2 seconds at 60fps
            this.refresh();
        }
        
        updateMouseInput() {
            if (TouchInput.isTriggered()) {
                const mouseX = TouchInput.x - this.x;
                const mouseY = TouchInput.y - this.y;
                
                // Check if click is within the window bounds
                if (mouseX >= 0 && mouseX <= this.width &&
                    mouseY >= 0 && mouseY <= this.height) {
                    this.toggleMinimize();
                }
            }
        }
        
        toggleMinimize() {
            this._isMinimized = !this._isMinimized;
            progressBarMinimized = this._isMinimized; // Update global state
            
            if (this._isMinimized) {
                // Minimize - keep same X position
                this.move(this.x, this.y, this._minWidth, this._minHeight);
            } else {
                // Maximize - restore full size
                this.move(this.x, this.y, this._fullWidth, this._fullHeight);
            }
            
            // Save the state
            saveUIState();
            
            this.refresh();
        }
        
        refresh() {
            this.contents.clear();
            
            if (this._isMinimized) {
                this.drawMinimizedContent();
            } else {
                this.drawProgressInfo();
            }
        }
        
        drawMinimizedContent() {
            const completedQuests = $gameVariables.value(completedQuestsVariable) || 0;
            const progressPercent = Math.min(Math.floor((completedQuests / totalQuests) * 100), 100);
            const currentStage = $gameVariables.value(currentStageVariable) || 0;
            
            // Draw background with visual cue that it's clickable
            // this.contents.fillRect(0, 0, this.contentsWidth(), this.contentsHeight(), ColorManager.dimColor1());

            // Compact progress display
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("Progress:", 5, 8, 60);
            this.changeTextColor(ColorManager.crisisColor());
            this.drawText(`${progressPercent}%`, 65, 8, 40);
            
            // Stage info
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(`Stage ${currentStage}`, 110, 8, 60);
            
            // Click hint
            this.changeTextColor(ColorManager.textColor(8)); // Gray
            this.contents.fontSize = 16;
            this.drawText("Click to expand", this.contentsWidth() - 100, 20, 100);
            this.contents.fontSize = 28; // Reset
            
            this.resetTextColor();
        }
            
        drawProgressInfo() {
            const coins = $gameVariables.value(coinVariable) || 0;
            const score = $gameVariables.value(scoreVariable) || 0;
            const currentStage = Math.max(0, $gameVariables.value(currentStageVariable) || 0);
            const completedQuests = $gameVariables.value(completedQuestsVariable) || 0;
            const correctAnswers = $gameVariables.value(correctAnswersVariable) || 0;
            const totalAnswered = $gameVariables.value(totalQuestionsVariable) || 0;
            
            // Calculate progress percentage
            const progressPercent = Math.min(Math.floor((completedQuests / totalQuests) * 100), 100);
            
            // Calculate accuracy
            const accuracy = totalAnswered > 0 ? Math.floor((correctAnswers / totalAnswered) * 100) : 0;
            
            // Draw background with visual cue that it's clickable
            // this.contents.fillRect(0, 0, this.contentsWidth(), this.contentsHeight(), ColorManager.dimColor1());
            
            // Draw title
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("Story Progress", 10, 0, 200);
            
            // Click hint
            this.changeTextColor(ColorManager.textColor(8)); // Gray
            this.contents.fontSize = 16;
            this.drawText("Click to minimize", 220, 5, 100);
            this.contents.fontSize = 28; // Reset
            
            // Development mode indicator
            if (developmentMode) {
                this.changeTextColor(ColorManager.textColor(6)); // Yellow
                this.drawText("DEV", 320, 0, 50);
            }
            
            // Draw sync indicator
            if (this._syncIndicatorTimer > 0) {
                this.changeTextColor(ColorManager.textColor(3)); // Green
                const syncText = developmentMode ? "● LOGGED" : "● SYNCED";
                this.drawText(syncText, 280, 25, 100);
            }
            
            // Draw progress bar
            const barWidth = 200;
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
            this.drawText("Stage:", 10, 70, 60);
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(`${currentStage}/29`, 70, 70, 60);
            
            // Coins
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("Coins:", 150, 70, 60);
            this.changeTextColor(ColorManager.textColor(14)); // Gold color
            this.drawText(coins.toString(), 210, 70, 60);
            
            // Score
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("Score:", 10, 95, 60);
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(score.toString(), 70, 95, 60);
            
            // Quests completed
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("Quests:", 150, 95, 80);
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(`${completedQuests}/39`, 230, 95, 60);
            
            // Development info
            if (developmentMode) {
                this.changeTextColor(ColorManager.textColor(6)); // Yellow
                this.drawText("Check console for database sync data", 10, 145, 400);
                
                this.changeTextColor(ColorManager.textColor(8)); // Gray
                this.drawText(`Player ID: ${playerId} | Play Time: ${Math.floor((Date.now() - gameStartTime) / 1000)}s`, 10, 165, 400);
            }
        }
    }

    //-----------------------------------------------------------------------------
    // Plugin Commands
    //-----------------------------------------------------------------------------
    
    // Add coin
    PluginManager.registerCommand(pluginName, "addCoin", args => {
        const amount = Number(args.amount) || 1;
        const currentCoins = $gameVariables.value(coinVariable);
        $gameVariables.setValue(coinVariable, currentCoins + amount);
        if (progressBarWindow) progressBarWindow.refresh();
        syncProgressToDatabase(false, 'add_coin');
    });
    
    // Add score
    PluginManager.registerCommand(pluginName, "addScore", args => {
        const amount = Number(args.amount) || 5;
        const currentScore = $gameVariables.value(scoreVariable);
        $gameVariables.setValue(scoreVariable, Math.max(0, currentScore + amount));
        if (progressBarWindow) progressBarWindow.refresh();
        syncProgressToDatabase(false, 'add_score');
    });
    
    // Record answer
    PluginManager.registerCommand(pluginName, "recordAnswer", args => {
        const isCorrect = args.correct === 'true';
        const currentTotal = $gameVariables.value(totalQuestionsVariable);
        const currentCorrect = $gameVariables.value(correctAnswersVariable);
        
        $gameVariables.setValue(totalQuestionsVariable, currentTotal + 1);
        if (isCorrect) {
            $gameVariables.setValue(correctAnswersVariable, currentCorrect + 1);
            // Add score for correct answer
            const currentScore = $gameVariables.value(scoreVariable);
            $gameVariables.setValue(scoreVariable, currentScore + 5);
        } else {
            // Subtract score for incorrect answer
            const currentScore = $gameVariables.value(scoreVariable);
            $gameVariables.setValue(scoreVariable, Math.max(0, currentScore - 1));
        }
        
        if (progressBarWindow) progressBarWindow.refresh();
        syncProgressToDatabase(false, isCorrect ? 'correct_answer' : 'wrong_answer');
    });
    
    // Set stage
    PluginManager.registerCommand(pluginName, "setStage", args => {
        const stage = Number(args.stage) || 0;
        $gameVariables.setValue(currentStageVariable, Math.min(39, Math.max(0, stage)));
        if (progressBarWindow) progressBarWindow.refresh();
        syncProgressToDatabase(false, 'set_stage');
    });
    
    // Complete quest
    PluginManager.registerCommand(pluginName, "completeQuest", args => {
        const currentCompleted = $gameVariables.value(completedQuestsVariable);
        $gameVariables.setValue(completedQuestsVariable, Math.min(totalQuests, currentCompleted + 1));
        
        // Auto-advance stage based on quest completion
        const newStage = Math.floor(newCompleted / Math.max(1, Math.floor(totalQuests /39))) + 1;
        $gameVariables.setValue(currentStageVariable, Math.min(12, newStage)); // Max 12 if starting from 0
        
        if (progressBarWindow) progressBarWindow.refresh();
        syncProgressToDatabase(false, 'complete_quest');
    });
    
    // Export progress data (for manual database insertion)
    PluginManager.registerCommand(pluginName, "exportProgress", args => {
        const currentData = getCurrentProgressData();
        const sqlInsert = `INSERT INTO game_progress 
            (player_id, coins, score, current_stage, completed_quests, correct_answers, total_questions_answered, play_time, progress_percentage) 
            VALUES (${currentData.player_id}, ${currentData.coins}, ${currentData.score}, ${currentData.current_stage}, ${currentData.completed_quests}, ${currentData.correct_answers}, ${currentData.total_questions_answered}, ${currentData.play_time}, ${Math.min(Math.floor((currentData.completed_quests / totalQuests) * 100), 100)})
            ON DUPLICATE KEY UPDATE 
            coins=${currentData.coins}, score=${currentData.score}, current_stage=${currentData.current_stage}, completed_quests=${currentData.completed_quests}, correct_answers=${currentData.correct_answers}, total_questions_answered=${currentData.total_questions_answered}, play_time=${currentData.play_time}, progress_percentage=${Math.min(Math.floor((currentData.completed_quests / totalQuests) * 100), 100)};`;
        
        console.group('📊 EXPORT PROGRESS DATA');
        console.log('Current Progress Data:', currentData);
        console.log('SQL Insert Statement:', sqlInsert);
        console.log('Copy the SQL above and run it in phpMyAdmin to manually update the database');
        console.groupEnd();
        
        // Also show in game message
        $gameMessage.add('Progress exported to console!');
        $gameMessage.add('Check browser console for SQL statement.');
    });
    
    // Show progress history
    PluginManager.registerCommand(pluginName, "showHistory", args => {
        console.group('📈 PROGRESS HISTORY');
        console.log('Total actions logged:', progressHistory.length);
        console.table(progressHistory.slice(-10)); // Show last 10 actions
        console.groupEnd();
    });
    
    // Toggle progress bar
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

    //-----------------------------------------------------------------------------
    // Scene Integration
    //-----------------------------------------------------------------------------
    
    const _Scene_Map_createAllWindows = Scene_Map.prototype.createAllWindows;
    Scene_Map.prototype.createAllWindows = function() {
        _Scene_Map_createAllWindows.call(this);
        this.createProgressBarWindow();
    };
    
    Scene_Map.prototype.createProgressBarWindow = function() {
        progressBarWindow = new Window_ProgressBar();
        this.addChild(progressBarWindow);
        
        // Set visibility based on persistent state
        if (progressBarVisible) {
            progressBarWindow.show();
        } else {
            progressBarWindow.hide();
        }
    };

    //-----------------------------------------------------------------------------
    // Game Variables Hook
    //-----------------------------------------------------------------------------
    
    const _Game_Variables_setValue = Game_Variables.prototype.setValue;
    Game_Variables.prototype.setValue = function(variableId, value) {
        const oldValue = this.value(variableId);
        _Game_Variables_setValue.call(this, variableId, value);
        
        // Refresh progress bar when relevant variables change
        const relevantVariables = [
            coinVariable, 
            scoreVariable, 
            currentStageVariable, 
            completedQuestsVariable,
            correctAnswersVariable,
            totalQuestionsVariable
        ];
        
        if (relevantVariables.includes(variableId) && oldValue !== value) {
            if (progressBarWindow) {
                progressBarWindow.refresh();
            }
            
            // Log the change in development mode
            if (developmentMode && oldValue !== value) {
                console.log(`🔄 Variable ${variableId} changed: ${oldValue} → ${value}`);
                setTimeout(() => syncProgressToDatabase(false, 'variable_change'), 100);
            }
        }
    };

    //-----------------------------------------------------------------------------
    // Utility Functions for Events
    //-----------------------------------------------------------------------------
    
    // Global functions that can be called from events
    window.FilibusteroProgress = {
        addCoin: function(amount = 1) {
            const currentCoins = $gameVariables.value(coinVariable);
            $gameVariables.setValue(coinVariable, currentCoins + amount);
            console.log(`💰 Added ${amount} coins. Total: ${currentCoins + amount}`);
        },
        
        addScore: function(amount = 5) {
            const currentScore = $gameVariables.value(scoreVariable);
            $gameVariables.setValue(scoreVariable, Math.max(0, currentScore + amount));
            console.log(`⭐ Added ${amount} score. Total: ${Math.max(0, currentScore + amount)}`);
        },
        
        subtractScore: function(amount = 1) {
            const currentScore = $gameVariables.value(scoreVariable);
            const newScore = Math.max(0, currentScore - amount);
            $gameVariables.setValue(scoreVariable, newScore);
            console.log(`💔 Lost ${amount} score. Total: ${newScore}`);
        },
        
        recordAnswer: function(isCorrect) {
            const currentTotal = $gameVariables.value(totalQuestionsVariable);
            const currentCorrect = $gameVariables.value(correctAnswersVariable);
            
            $gameVariables.setValue(totalQuestionsVariable, currentTotal + 1);
            
            if (isCorrect) {
                $gameVariables.setValue(correctAnswersVariable, currentCorrect + 1);
                this.addScore(5); // 5 points for correct answer
                console.log(`✅ Correct answer! Accuracy: ${Math.floor(((currentCorrect + 1) / (currentTotal + 1)) * 100)}%`);
            } else {
                this.subtractScore(1); // -1 point for incorrect answer
                console.log(`❌ Wrong answer! Accuracy: ${Math.floor((currentCorrect / (currentTotal + 1)) * 100)}%`);
            }
        },
        
        completeQuest: function() {
            const currentCompleted = $gameVariables.value(completedQuestsVariable);
            const newCompleted = Math.min(totalQuests, currentCompleted + 1);
            $gameVariables.setValue(completedQuestsVariable, newCompleted);
            
            // Auto-advance stage
            const newStage = Math.floor(newCompleted / Math.max(1, Math.floor(totalQuests / 39))) + 1;
            $gameVariables.setValue(currentStageVariable, Math.min(39, Math.max(0, newStage)));

            const progressPercent = Math.floor((newCompleted / totalQuests) * 100);
            console.log(`🎯 Quest completed! Progress: ${progressPercent}% (${newCompleted}/${totalQuests}) - Stage: ${newStage}`);
        },
        
        setStage: function(stage) {
            $gameVariables.setValue(currentStageVariable, Math.min(39, Math.max(0, stage)));
            console.log(`🏁 Stage set to: ${stage}`);
        },
        
        getProgress: function() {
            const completedQuests = $gameVariables.value(completedQuestsVariable);
            return Math.min(Math.floor((completedQuests / totalQuests) * 100), 100);
        },
        
        getCoins: function() {
            return $gameVariables.value(coinVariable);
        },
        
        getScore: function() {
            return $gameVariables.value(scoreVariable);
        },
        
        getCurrentStage: function() {
            return $gameVariables.value(currentStageVariable);
        },
        
        getAccuracy: function() {
            const total = $gameVariables.value(totalQuestionsVariable);
            const correct = $gameVariables.value(correctAnswersVariable);
            return total > 0 ? Math.floor((correct / total) * 100) : 0;
        },
        
        getStats: function() {
            const stats = {
                coins: this.getCoins(),
                score: this.getScore(),
                stage: this.getCurrentStage(),
                progress: this.getProgress(),
                accuracy: this.getAccuracy(),
                correctAnswers: $gameVariables.value(correctAnswersVariable),
                totalAnswered: $gameVariables.value(totalQuestionsVariable),
                completedQuests: $gameVariables.value(completedQuestsVariable),
                playTime: Math.floor((Date.now() - gameStartTime) / 1000)
            };
            
            console.log('📊 Current Game Stats:', stats);
            return stats;
        },
        
        // Show/Hide progress bar programmatically
        showProgressBar: function() {
            progressBarVisible = true;
            saveUIState();
            if (progressBarWindow) {
                progressBarWindow.show();
            }
            console.log('📊 Progress bar shown');
        },
        
        hideProgressBar: function() {
            progressBarVisible = false;
            saveUIState();
            if (progressBarWindow) {
                progressBarWindow.hide();
            }
            console.log('📊 Progress bar hidden');
        },
        
        toggleProgressBar: function() {
            if (progressBarVisible) {
                this.hideProgressBar();
            } else {
                this.showProgressBar();
            }
        },
        
        // Development helper functions
        exportToSQL: function() {
            const currentData = getCurrentProgressData();
            const progressPercent = Math.min(Math.floor((currentData.completed_quests / totalQuests) * 100), 100);
            
            const sqlInsert = `INSERT INTO game_progress 
                (player_id, coins, score, current_stage, completed_quests, correct_answers, total_questions_answered, play_time, progress_percentage) 
                VALUES (${currentData.player_id}, ${currentData.coins}, ${currentData.score}, ${currentData.current_stage}, ${currentData.completed_quests}, ${currentData.correct_answers}, ${currentData.total_questions_answered}, ${currentData.play_time}, ${progressPercent})
                ON DUPLICATE KEY UPDATE 
                coins=${currentData.coins}, score=${currentData.score}, current_stage=${currentData.current_stage}, completed_quests=${currentData.completed_quests}, correct_answers=${currentData.correct_answers}, total_questions_answered=${currentData.total_questions_answered}, play_time=${currentData.play_time}, progress_percentage=${progressPercent};`;
            
            console.group('📋 SQL EXPORT');
            console.log('Current Data:', currentData);
            console.log('SQL Statement:');
            console.log(sqlInsert);
            console.log('\n📝 Instructions:');
            console.log('1. Copy the SQL statement above');
            console.log('2. Open phpMyAdmin');
            console.log('3. Go to your filibustero_db database');
            console.log('4. Click on SQL tab');
            console.log('5. Paste and execute the SQL');
            console.groupEnd();
            
            return sqlInsert;
        },
        
        showProgressHistory: function() {
            console.group('📈 PROGRESS HISTORY');
            console.log('Total actions logged:', progressHistory.length);
            if (progressHistory.length > 0) {
                console.log('Recent actions:');
                console.table(progressHistory.slice(-10));
                
                console.log('\nAll progress data stored in localStorage:');
                try {
                    const current = JSON.parse(localStorage.getItem('filibustero_progress_current') || '{}');
                    const history = JSON.parse(localStorage.getItem('filibustero_progress_history') || '[]');
                    console.log('Current:', current);
                    console.log('History entries:', history.length);
                } catch (e) {
                    console.log('No localStorage data available');
                }
            } else {
                console.log('No progress history yet. Play the game to see updates!');
            }
            console.groupEnd();
            
            return progressHistory;
        },
        
        resetProgress: function(confirm = false) {
            if (!confirm) {
                console.log('⚠️ To reset progress, call: FilibusteroProgress.resetProgress(true)');
                return;
            }
            
            console.log('🔄 Resetting all progress...');
            
            // Reset all variables
            $gameVariables.setValue(coinVariable, 0);
            $gameVariables.setValue(scoreVariable, 0);
            $gameVariables.setValue(currentStageVariable, 0);
            $gameVariables.setValue(completedQuestsVariable, 0);
            $gameVariables.setValue(correctAnswersVariable, 0);
            $gameVariables.setValue(totalQuestionsVariable, 0);
            
            // Clear localStorage
            try {
                localStorage.removeItem('filibustero_progress_current');
                localStorage.removeItem('filibustero_progress_history');
                 localStorage.removeItem('filibustero_progress_ui_state');
            } catch (e) {
                console.warn('Could not clear localStorage:', e);
            }
            
            // Reset tracking variables
            progressHistory = [];
            gameStartTime = Date.now();
             progressBarVisible = showProgressBarInitial;
            progressBarMinimized = false;
            
            if (progressBarWindow) {
                progressBarWindow.refresh();
                if (progressBarVisible) {
                    progressBarWindow.show();
                } else {
                    progressBarWindow.hide();
                }
            }
            
            console.log('✅ Progress reset complete!');
        }
    };

    // Add some helpful console commands for development
    if (developmentMode) {
        console.log('\n🎮 FILIBUSTERO DEVELOPMENT MODE ACTIVE');
        console.log('=====================================');
        console.log('Available console commands:');
        console.log('• FilibusteroProgress.getStats() - Show current stats');
        console.log('• FilibusteroProgress.exportToSQL() - Export SQL for database');
        console.log('• FilibusteroProgress.showProgressHistory() - Show all progress changes');
        console.log('• FilibusteroProgress.resetProgress(true) - Reset all progress');
        console.log('• FilibusteroProgress.addCoin(10) - Add coins');
        console.log('• FilibusteroProgress.addScore(50) - Add score');
        console.log('• FilibusteroProgress.recordAnswer(true/false) - Record quiz answer');
        console.log('• FilibusteroProgress.completeQuest() - Complete a quest');
        console.log('• FilibusteroProgress.showProgressBar() - Show progress bar');
        console.log('• FilibusteroProgress.hideProgressBar() - Hide progress bar');
        console.log('• FilibusteroProgress.toggleProgressBar() - Toggle visibility');
        console.log('=====================================');
        console.log('🖱️  Click anywhere on progress bar to minimize/maximize');
        console.log('⚙️  Progress bar state persists between map changes');
        console.log('=====================================\n');
        
        // Auto-sync initial state
        setTimeout(() => {
            syncProgressToDatabase(true, 'game_start');
        }, 1000);
    }

})();