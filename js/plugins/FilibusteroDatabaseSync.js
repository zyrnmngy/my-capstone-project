// Add this to a new .js file in your RPG Maker MZ js/plugins folder
// File name: FilibusteroDatabaseSync.js

/*:
 * @target MZ
 * @plugindesc [v1.1.0] Filibustero Database Sync - Fixed
 * @author YourName
 * @url 
 * @help FilibusteroDatabaseSync.js
 * 
 * This plugin syncs your Filibustero progress with the database
 * 
 * @param apiUrl
 * @desc API Server URL (your PHP API)
 * @default https://filibustero-web.com/filibustero_api.php
 * 
 * @param dynamicPlayerId
 * @desc Use dynamic player ID from login system
 * @type boolean
 * @default true
 * 
 * @param fallbackPlayerId
 * @desc Fallback Player ID if dynamic fails
 * @type number
 * @default 1
 * 
 * @param autoSave
 * @desc Auto-save progress on variable changes
 * @type boolean
 * @default true
 * 
 * @param saveInterval
 * @desc Auto-save interval in seconds (0 to disable)
 * @type number
 * @default 30
 */

(() => {
    'use strict';
    
    const pluginName = 'FilibusteroDatabaseSync';
    const parameters = PluginManager.parameters(pluginName);
    
    const apiUrl = parameters['apiUrl'] || 'https://filibustero-web.com/php/progress_sync.php';
    const dynamicPlayerId = parameters['dynamicPlayerId'] === 'true';
    const fallbackPlayerId = Number(parameters['fallbackPlayerId'] || 1);
    const autoSave = parameters['autoSave'] === 'true';
    const saveInterval = Number(parameters['saveInterval'] || 30);
    
    // Variable IDs from your progress bar plugin
    const coinVariable = 14;
    const scoreVariable = 15;
    const currentStageVariable = 16;
    const completedQuestsVariable = 17;
    
    let sessionId = null;
    let autoSaveTimer = null;
    let lastSaveData = {};
    let currentPlayerId = null;

    // Function to get current player ID dynamically
    function getCurrentPlayerId() {
        if (!dynamicPlayerId) {
            return fallbackPlayerId;
        }

        // Try multiple methods to get the current user/player ID
        
        // Method 1: Check if there's a global user ID variable
        if (typeof window.currentUserId !== 'undefined' && window.currentUserId) {
            console.log('Using window.currentUserId:', window.currentUserId);
            return window.currentUserId;
        }
        
        // Method 2: Check if there's a game variable storing user ID
        if ($gameVariables && $gameVariables.value(1)) { // Assuming variable 1 stores user ID
            console.log('Using game variable 1 for user ID:', $gameVariables.value(1));
            return $gameVariables.value(1);
        }
        
        // Method 3: Check if there's a saved player ID in localStorage (if available)
        try {
            const savedPlayerId = localStorage.getItem('currentPlayerId');
            if (savedPlayerId) {
                console.log('Using localStorage player ID:', savedPlayerId);
                return parseInt(savedPlayerId);
            }
        } catch (e) {
            console.log('localStorage not available');
        }
        
        // Method 4: Check session storage or other storage methods
        if (typeof sessionStorage !== 'undefined') {
            try {
                const sessionPlayerId = sessionStorage.getItem('playerId');
                if (sessionPlayerId) {
                    console.log('Using sessionStorage player ID:', sessionPlayerId);
                    return parseInt(sessionPlayerId);
                }
            } catch (e) {
                console.log('sessionStorage not available');
            }
        }
        
        // Method 5: Check if there's a player data system
        if ($dataSystem && $dataSystem.currentPlayerId) {
            console.log('Using $dataSystem.currentPlayerId:', $dataSystem.currentPlayerId);
            return $dataSystem.currentPlayerId;
        }
        
        console.log('No dynamic player ID found, using fallback:', fallbackPlayerId);
        return fallbackPlayerId;
    }

    // Set player ID function (to be called from your login system)
    window.setCurrentPlayerId = function(playerId) {
        console.log('Setting current player ID to:', playerId);
        currentPlayerId = playerId;
        window.currentUserId = playerId;
        
        // Store in game variable for persistence
        if ($gameVariables) {
            $gameVariables.setValue(1, playerId);
        }
        
        // Store in data system
        if ($dataSystem) {
            $dataSystem.currentPlayerId = playerId;
        }
        
        // Try to store in localStorage if available
        try {
            localStorage.setItem('currentPlayerId', playerId.toString());
        } catch (e) {
            console.log('Could not save to localStorage');
        }
    };

    // Database sync functions
    window.FilibusteroDatabase = {
        // Get current player ID
        getPlayerId() {
            if (currentPlayerId) return currentPlayerId;
            currentPlayerId = getCurrentPlayerId();
            return currentPlayerId;
        },

        // Load progress from database - FIXED VERSION
        async loadProgress() {
            try {
                const playerId = this.getPlayerId();
                console.log('Loading progress for player ID:', playerId);
                
                const response = await fetch(`${apiUrl}?action=get_progress&player_id=${playerId}`);
                const result = await response.json();
                
                if (result.success && result.data) {
                    const data = result.data;
                    console.log('Progress loaded:', data);
                    
                    // CRITICAL: Verify this data belongs to the current user
                    if (data.player_id !== playerId && data.user_id !== playerId) {
                        console.error('SECURITY ALERT: Attempted to load progress for different user!');
                        console.error('Expected user ID:', playerId, 'Received user ID:', data.player_id || data.user_id);
                        
                        // Create fresh progress instead of loading foreign data
                        this.initializeFreshProgress();
                        $gameMessage.add('\\C[2]Security check failed - starting fresh!');
                        return true;
                    }
                    
                    // Update game variables with database values
                    $gameVariables.setValue(coinVariable, data.coins || 0);
                    $gameVariables.setValue(scoreVariable, data.score || 0);
                    $gameVariables.setValue(currentStageVariable, data.current_stage || 0);
                    $gameVariables.setValue(completedQuestsVariable, data.completed_quests || 0);
                    
                    // Store additional progress data
                    $dataSystem.filibusteroProgress = {
                        correct_answers: data.correct_answers || 0,
                        total_questions_answered: data.total_questions_answered || 0,
                        play_time: data.play_time || 0,
                        game_completed: data.game_completed || 0,
                        progress_percentage: data.progress_percentage || 0,
                        last_updated: data.last_updated
                    };
                    
                    // Refresh progress bar if it exists
                    if (window.progressBarWindow) {
                        window.progressBarWindow.refresh();
                    }
                    
                    // Also sync with user_progress table
                    await this.syncUserProgress();
                    
                    $gameMessage.add('\\C[3]Progress loaded successfully!');
                    $gameMessage.add(`Player ID: ${playerId}`);
                    $gameMessage.add(`Last saved: ${new Date(data.last_updated).toLocaleString()}`);
                    return true;
                } else if (result.success && !result.data) {
                    console.log('No saved progress found, starting fresh for player:', playerId);
                    this.initializeFreshProgress();
                    $gameMessage.add(`\\C[6]No saved progress found for player ${playerId} - starting fresh!`);
                    return true;
                } else {
                    throw new Error(result.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Failed to load progress:', error);
                $gameMessage.add('\\C[2]Failed to load progress from server');
                $gameMessage.add('\\C[0]Check your internet connection');
                return false;
            }
        },

        // Add this helper method to initialize fresh progress
        initializeFreshProgress() {
            $gameVariables.setValue(coinVariable, 0);
            $gameVariables.setValue(scoreVariable, 0);
            $gameVariables.setValue(currentStageVariable, 1); // Start at stage 1
            $gameVariables.setValue(completedQuestsVariable, 0);
            
            $dataSystem.filibusteroProgress = {
                correct_answers: 0,
                total_questions_answered: 0,
                play_time: 0,
                game_completed: 0,
                progress_percentage: 0,
                last_updated: null
            };
        },
        
        // Save progress to database
        async saveProgress() {
            try {
                const playerId = this.getPlayerId();
                console.log('Saving progress for player ID:', playerId);
                
                // Initialize progress data if not exists
                if (!$dataSystem.filibusteroProgress) {
                    $dataSystem.filibusteroProgress = {
                        correct_answers: 0,
                        total_questions_answered: 0,
                        play_time: 0,
                        game_completed: 0
                    };
                }
                
                // Collect current progress data
                const progressData = {
                    player_id: playerId,
                    coins: $gameVariables.value(coinVariable) || 0,
                    score: $gameVariables.value(scoreVariable) || 0,
                    current_stage: $gameVariables.value(currentStageVariable) || 0,
                    completed_quests: $gameVariables.value(completedQuestsVariable) || 0,
                    correct_answers: $dataSystem.filibusteroProgress.correct_answers || 0,
                    total_questions_answered: $dataSystem.filibusteroProgress.total_questions_answered || 0,
                    play_time: $dataSystem.filibusteroProgress.play_time + Math.floor($gameSystem.playtimeSeconds()) || 0
                };
                
                console.log('Sending progress data:', progressData);
                
                // Check if data actually changed (avoid unnecessary saves)
                const dataChanged = JSON.stringify(progressData) !== JSON.stringify(lastSaveData);
                if (!dataChanged && Object.keys(lastSaveData).length > 0) {
                    console.log('No changes detected, skipping save');
                    return true;
                }
                
                const response = await fetch(`${apiUrl}?action=update_progress`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(progressData)
                });
                
                const result = await response.json();
                console.log('Save response:', result);
                
                if (result.success) {
                    console.log('Progress saved successfully:', result);
                    lastSaveData = { ...progressData };
                    
                    // Update stored progress data
                    $dataSystem.filibusteroProgress.progress_percentage = result.progress_percentage;
                    $dataSystem.filibusteroProgress.last_updated = new Date().toISOString();
                    
                    // Also update user_progress table
                    await this.syncUserProgress();
                    
                    $gameMessage.add('\\C[3]Progress saved successfully!');
                    $gameMessage.add(`Player: ${playerId} | Progress: ${result.progress_percentage}%`);
                    return true;
                } else {
                    throw new Error(result.error || 'Save failed');
                }
            } catch (error) {
                console.error('Failed to save progress:', error);
                $gameMessage.add('\\C[2]Failed to save progress to server');
                $gameMessage.add('\\C[0]Progress saved locally only');
                return false;
            }
        },

        // Sync with user_progress table
        async syncUserProgress() {
            try {
                const playerId = this.getPlayerId();
                
                const userProgressData = {
                    user_id: playerId, // This should match the user_id in your users table
                    coin_count: $gameVariables.value(coinVariable) || 0,
                    current_stage: $gameVariables.value(currentStageVariable) || 0,
                    completed_quests: $gameVariables.value(completedQuestsVariable) || 0,
                    overall_progress: Math.min(($gameVariables.value(completedQuestsVariable) / 25) * 100, 100),
                    quest_progress: Math.min(($gameVariables.value(completedQuestsVariable) / 25) * 100, 100)
                };
                
                const response = await fetch(`${apiUrl}?action=sync_user_progress`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(userProgressData)
                });
                
                const result = await response.json();
                console.log('User progress sync result:', result);
                
            } catch (error) {
                console.error('Failed to sync user progress:', error);
            }
        },
        
        // Start game session
        async startSession() {
            try {
                const playerId = this.getPlayerId();
                console.log('Starting session for player ID:', playerId);
                
                const response = await fetch(`${apiUrl}?action=start_session`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ player_id: playerId })
                });
                
                const result = await response.json();
                console.log('Session start result:', result);
                
                if (result.success) {
                    sessionId = result.session_id;
                    console.log('Game session started:', sessionId, 'for player:', playerId);
                    return true;
                }
            } catch (error) {
                console.error('Failed to start session:', error);
            }
            return false;
        },
        
        // End game session
        async endSession() {
            if (sessionId) {
                try {
                    const response = await fetch(`${apiUrl}?action=end_session`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ session_id: sessionId })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        console.log('Game session ended');
                        sessionId = null;
                    }
                } catch (error) {
                    console.error('Failed to end session:', error);
                }
            }
        },
        
        // Manual sync function
        async syncProgress() {
            console.log('Manual sync initiated...');
            await this.saveProgress();
        }
    };
    
    // Initialize progress data structure
    const initializeProgressData = function() {
        if (!$dataSystem.filibusteroProgress) {
            $dataSystem.filibusteroProgress = {
                correct_answers: 0,
                total_questions_answered: 0,
                play_time: 0,
                game_completed: 0,
                progress_percentage: 0,
                last_updated: null
            };
        }
    };
    
    // Auto-initialize on game start
    const _Scene_Boot_start = Scene_Boot.prototype.start;
    Scene_Boot.prototype.start = function() {
        _Scene_Boot_start.call(this);
        initializeProgressData();
        
        // Start session and load progress with delay
        setTimeout(async () => {
            const playerId = FilibusteroDatabase.getPlayerId();
            console.log('Boot complete, using player ID:', playerId);
            
            await FilibusteroDatabase.startSession();
            await FilibusteroDatabase.loadProgress();
        }, 1000);
    };
    
    // Auto-save timer
    if (saveInterval > 0) {
        const startAutoSave = function() {
            if (autoSaveTimer) clearInterval(autoSaveTimer);
            
            autoSaveTimer = setInterval(async () => {
                if ($gameParty && $gameParty.exists() && !$gameMessage.isBusy()) {
                    await FilibusteroDatabase.saveProgress();
                }
            }, saveInterval * 1000);
        };
        
        const _Scene_Map_start = Scene_Map.prototype.start;
        Scene_Map.prototype.start = function() {
            _Scene_Map_start.call(this);
            startAutoSave();
        };
    }
    
    // Hook into variable changes for auto-save
    if (autoSave) {
        const _Game_Variables_setValue = Game_Variables.prototype.setValue;
        Game_Variables.prototype.setValue = function(variableId, value) {
            const oldValue = this._data[variableId];
            _Game_Variables_setValue.call(this, variableId, value);
            
            // Auto-save when Filibustero variables change
            if ([coinVariable, scoreVariable, currentStageVariable, completedQuestsVariable].includes(variableId)) {
                if (oldValue !== value) {
                    console.log(`Variable ${variableId} changed from ${oldValue} to ${value}`);
                    // Debounced save (wait 2 seconds after last change)
                    clearTimeout(this._autoSaveTimeout);
                    this._autoSaveTimeout = setTimeout(async () => {
                        if (!$gameMessage.isBusy()) {
                            await FilibusteroDatabase.saveProgress();
                        }
                    }, 2000);
                }
            }
        };
    }
    
    // Add menu commands
    const _Window_MenuCommand_makeCommandList = Window_MenuCommand.prototype.makeCommandList;
    Window_MenuCommand.prototype.makeCommandList = function() {
        _Window_MenuCommand_makeCommandList.call(this);
        this.addCommand('Save Progress', 'saveProgress', true);
        this.addCommand('Load Progress', 'loadProgress', true);
        this.addCommand('Sync Data', 'syncProgress', true);
        this.addCommand('Show Player ID', 'showPlayerId', true);
    };
    
    const _Scene_Menu_createCommandWindow = Scene_Menu.prototype.createCommandWindow;
    Scene_Menu.prototype.createCommandWindow = function() {
        _Scene_Menu_createCommandWindow.call(this);
        this._commandWindow.setHandler('saveProgress', this.commandSaveProgress.bind(this));
        this._commandWindow.setHandler('loadProgress', this.commandLoadProgress.bind(this));
        this._commandWindow.setHandler('syncProgress', this.commandSyncProgress.bind(this));
        this._commandWindow.setHandler('showPlayerId', this.commandShowPlayerId.bind(this));
    };
    
    Scene_Menu.prototype.commandSaveProgress = async function() {
        this._commandWindow.close();
        await FilibusteroDatabase.saveProgress();
        this._commandWindow.open();
        this._commandWindow.activate();
    };
    
    Scene_Menu.prototype.commandLoadProgress = async function() {
        this._commandWindow.close();
        await FilibusteroDatabase.loadProgress();
        this._commandWindow.open();
        this._commandWindow.activate();
    };
    
    Scene_Menu.prototype.commandSyncProgress = async function() {
        this._commandWindow.close();
        await FilibusteroDatabase.syncProgress();
        this._commandWindow.open();
        this._commandWindow.activate();
    };

    Scene_Menu.prototype.commandShowPlayerId = function() {
        const playerId = FilibusteroDatabase.getPlayerId();
        $gameMessage.add(`\\C[3]Current Player ID: ${playerId}`);
        $gameMessage.add(`\\C[0]Coins: ${$gameVariables.value(coinVariable)}`);
        $gameMessage.add(`\\C[0]Score: ${$gameVariables.value(scoreVariable)}`);
        $gameMessage.add(`\\C[0]Stage: ${$gameVariables.value(currentStageVariable)}`);
        $gameMessage.add(`\\C[0]Quests: ${$gameVariables.value(completedQuestsVariable)}`);
        this._commandWindow.activate();
    };
    
    // Save progress when closing game
    const _SceneManager_terminate = SceneManager.terminate;
    SceneManager.terminate = function() {
        FilibusteroDatabase.endSession();
        FilibusteroDatabase.saveProgress();
        _SceneManager_terminate.call(this);
    };
    
    // Extend the global FilibusteroProgress object with database functions
    if (window.FilibusteroProgress) {
        // Add database save to existing functions
        const originalAddCoin = window.FilibusteroProgress.addCoin;
        window.FilibusteroProgress.addCoin = function(amount = 1) {
            console.log('Adding coins:', amount);
            originalAddCoin.call(this, amount);
            if (autoSave) {
                setTimeout(() => FilibusteroDatabase.saveProgress(), 1000);
            }
        };
        
        const originalAddScore = window.FilibusteroProgress.addScore;
        window.FilibusteroProgress.addScore = function(amount = 5) {
            console.log('Adding score:', amount);
            originalAddScore.call(this, amount);
            // Track correct answers
            if (!$dataSystem.filibusteroProgress) initializeProgressData();
            if (amount > 0) $dataSystem.filibusteroProgress.correct_answers++;
            $dataSystem.filibusteroProgress.total_questions_answered++;
            
            if (autoSave) {
                setTimeout(() => FilibusteroDatabase.saveProgress(), 1000);
            }
        };
        
        const originalSubtractScore = window.FilibusteroProgress.subtractScore;
        window.FilibusteroProgress.subtractScore = function(amount = 1) {
            console.log('Subtracting score:', amount);
            originalSubtractScore.call(this, amount);
            // Track total questions
            if (!$dataSystem.filibusteroProgress) initializeProgressData();
            $dataSystem.filibusteroProgress.total_questions_answered++;
            
            if (autoSave) {
                setTimeout(() => FilibusteroDatabase.saveProgress(), 1000);
            }
        };
        
        const originalCompleteQuest = window.FilibusteroProgress.completeQuest;
        window.FilibusteroProgress.completeQuest = function() {
            console.log('Completing quest');
            originalCompleteQuest.call(this);
            
            // Check if game is completed
            const completedQuests = $gameVariables.value(completedQuestsVariable);
            if (completedQuests >= 25) {
                if (!$dataSystem.filibusteroProgress) initializeProgressData();
                $dataSystem.filibusteroProgress.game_completed = 1;
            }
            
            if (autoSave) {
                setTimeout(() => FilibusteroDatabase.saveProgress(), 1000);
            }
        };
        
        // Add database functions to the global object
        window.FilibusteroProgress.saveToDatabase = () => FilibusteroDatabase.saveProgress();
        window.FilibusteroProgress.loadFromDatabase = () => FilibusteroDatabase.loadProgress();
        window.FilibusteroProgress.syncWithDatabase = () => FilibusteroDatabase.syncProgress();
        window.FilibusteroProgress.setPlayerId = (id) => setCurrentPlayerId(id);
        window.FilibusteroProgress.getPlayerId = () => FilibusteroDatabase.getPlayerId();
    }

})();