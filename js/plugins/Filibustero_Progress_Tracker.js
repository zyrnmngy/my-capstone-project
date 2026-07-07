//=============================================================================
// Filibustero_Progress_Tracker.js
//=============================================================================

/*:
 * @target MZ
 * @plugindesc Tracks player progress and updates HUD.
 * @author Filibustero Team
 *
 * @param developmentMode
 * @text Development Mode
 * @desc Enable development mode to prevent save file conflicts
 * @type boolean
 * @default false
 *
 * @param serverUrl
 * @text Server URL
 * @desc Base URL for the server (change for production)
 * @type string
 * @default https://filibustero-web.com
 *
 * @command SaveProgress
 * @text Save Progress to Server
 * @desc Sends the player's current progress to the server.
 *
 * @command LoadProgress
 * @text Load Progress from Server
 * @desc Loads the player's saved progress from the server.
 *
 * @help
 * This plugin tracks player's score, items, time, and events.
 * Use the debug object in console for manual testing:
 *
 * FilibusteroProgressDebug.setTestValues();
 * FilibusteroProgressDebug.refreshHUD();
 *
 * IMPORTANT FOR DEPLOYMENT:
 * - Set developmentMode to false in production
 * - Change serverUrl to your production server
 * - Clear browser data before deployment testing
 *
 * Version: 1.1
 * License: MIT
 */

(() => {
    'use strict';

    // Get plugin parameters
    const pluginName = 'Filibustero_Progress_Tracker';
    const parameters = PluginManager.parameters(pluginName);
    const developmentMode = parameters['developmentMode'] === 'true';
    const serverUrl = parameters['serverUrl'] || 'https://filibustero-web.com';

    // Development mode indicator
    if (developmentMode) {
        console.log("🔧 DEVELOPMENT MODE ENABLED - Server sync disabled");
    }

    // =============================================
    // PROGRESS TRACKING SYSTEM
    // =============================================
    
    // Global progress tracker
    window.FilibusteroProgress = {
        // Configuration
        config: {
            developmentMode: developmentMode,
            serverUrl: serverUrl,
            enableServerSync: !developmentMode // Disable server sync in dev mode
        },

        // Progress categories and their weights
        categories: {
            story: { weight: 40, progress: 0 },      // Main story completion
            chapters: { weight: 30, progress: 0 },   // Chapter completion
            quests: { weight: 15, progress: 0 },     // Side quests
            items: { weight: 10, progress: 0 },      // Item collection
            achievements: { weight: 5, progress: 0 } // Special achievements
        },
        
        // Get or create progress data
        getProgressData: function() {
            if (!$gameSystem.progressData) {
                $gameSystem.progressData = {
                    totalSwitches: 100,        // Total story switches
                    completedSwitches: 0,      // Completed story switches
                    totalChapters: 13,         // Total chapters/stages
                    completedChapters: 0,      // Completed chapters
                    totalQuests: 20,           // Total side quests
                    completedQuests: 0,        // Completed side quests
                    totalItems: 50,            // Total collectible items
                    collectedItems: 0,         // Collected items
                    totalAchievements: 15,     // Total achievements
                    unlockedAchievements: 0,   // Unlocked achievements
                    playtimeHours: 0,          // Total playtime
                    lastSaveDate: null,        // Last save date
                    gameStartDate: new Date().toISOString(), // Game start date
                    environmentMode: this.config.developmentMode ? 'development' : 'production' // Track environment
                };
            }
            return $gameSystem.progressData;
        },

        // Check if server sync should be enabled
        shouldSyncWithServer: function() {
            return this.config.enableServerSync && 
                   window.FilibusteroAuth && 
                   window.FilibusteroAuth.isLoggedIn && 
                   !this.config.developmentMode;
        },
        
        // Initialize progress tracking
        initialize: function() {
            // Only initialize if game system exists
            if (!$gameSystem) return;
            
            this.getProgressData(); // This will create the data if it doesn't exist
            this.updateAllProgress();
            
            // Log current mode
            if (this.config.developmentMode) {
                console.log("📊 Progress Tracker: Development mode - Local saves only");
            } else {
                console.log("📊 Progress Tracker: Production mode - Server sync enabled");
            }
        },
        
        // Update all progress categories
        updateAllProgress: function() {
            if (!$gameSystem) return;
            
            this.updateStoryProgress();
            this.updateChapterProgress();
            this.updateQuestProgress();
            this.updateItemProgress();
            this.updateAchievementProgress();
        },
        
        // Update story progress based on switches
        updateStoryProgress: function() {
            if (!$gameSystem || !$gameSwitches) return;
            
            const progressData = this.getProgressData();
            let completedSwitches = 0;
            
            // Count important story switches (1-100)
            for (let i = 1; i <= progressData.totalSwitches; i++) {
                if ($gameSwitches.value(i)) {
                    completedSwitches++;
                }
            }
            
            progressData.completedSwitches = completedSwitches;
            this.categories.story.progress = (completedSwitches / progressData.totalSwitches) * 100;
        },
        
        // Update chapter progress based on variables
        updateChapterProgress: function() {
            if (!$gameSystem || !$gameVariables) return;
            
            const progressData = this.getProgressData();
            
            // Assume variable 1 tracks current chapter/stage
            const currentChapter = $gameVariables.value(1);
            progressData.completedChapters = Math.min(currentChapter, progressData.totalChapters);
            this.categories.chapters.progress = (currentChapter / progressData.totalChapters) * 100;
        },
        
        // Update quest progress
        updateQuestProgress: function() {
            if (!$gameSystem || !$gameSwitches) return;
            
            const progressData = this.getProgressData();
            
            // Count completed quests (switches 101-120)
            let completedQuests = 0;
            for (let i = 101; i <= 120; i++) {
                if ($gameSwitches.value(i)) {
                    completedQuests++;
                }
            }
            
            progressData.completedQuests = completedQuests;
            this.categories.quests.progress = (completedQuests / progressData.totalQuests) * 100;
        },
        
        // Update item collection progress
        updateItemProgress: function() {
            if (!$gameSystem || !$gameParty || !$dataItems) return;
            
            const progressData = this.getProgressData();
            
            // Count unique items in inventory
            let collectedItems = 0;
            // Count different item types
            for (let i = 1; i <= progressData.totalItems; i++) {
                if ($dataItems[i] && $gameParty.hasItem($dataItems[i])) {
                    collectedItems++;
                }
            }
            
            progressData.collectedItems = collectedItems;
            this.categories.items.progress = (collectedItems / progressData.totalItems) * 100;
        },
        
        // Update achievement progress
        updateAchievementProgress: function() {
            if (!$gameSystem || !$gameSwitches) return;
            
            const progressData = this.getProgressData();
            
            // Count achievements (switches 201-215)
            let unlockedAchievements = 0;
            for (let i = 201; i <= 215; i++) {
                if ($gameSwitches.value(i)) {
                    unlockedAchievements++;
                }
            }
            
            progressData.unlockedAchievements = unlockedAchievements;
            this.categories.achievements.progress = (unlockedAchievements / progressData.totalAchievements) * 100;
        },
        
        // Calculate overall completion percentage
        getOverallProgress: function() {
            let totalWeightedProgress = 0;
            let totalWeight = 0;
            
            for (let category in this.categories) {
                const cat = this.categories[category];
                totalWeightedProgress += (cat.progress * cat.weight);
                totalWeight += cat.weight;
            }
            
            return totalWeight > 0 ? (totalWeightedProgress / totalWeight) : 0;
        },
        
        // Get current stage
        getCurrentStage: function() {
            return ($gameVariables && $gameVariables.value(1)) || 1;
        },
        
        // Get coin count (assuming variable 2 stores coins)
        getCoinCount: function() {
            return ($gameVariables && $gameVariables.value(2)) || 0;
        },
        
        // Get playtime in hours
        getPlaytimeHours: function() {
            if ($gameSystem && $gameSystem.playtimeSeconds) {
                return Math.floor($gameSystem.playtimeSeconds() / 3600);
            }
            return 0;
        },
        
        // Save progress to server (only in production mode)
        saveProgressToServer: function() {
            if (!this.shouldSyncWithServer() || !$gameSystem) {
                if (this.config.developmentMode) {
                    console.log("🔧 Dev Mode: Skipping server save");
                }
                return;
            }
            
            const progressData = this.getProgressData();
            const serverData = {
                user_id: window.FilibusteroAuth.currentUser.id,
                overall_progress: this.getOverallProgress(),
                story_progress: this.categories.story.progress,
                chapter_progress: this.categories.chapters.progress,
                quest_progress: this.categories.quests.progress,
                item_progress: this.categories.items.progress,
                achievement_progress: this.categories.achievements.progress,
                playtime_hours: this.getPlaytimeHours(),
                current_stage: this.getCurrentStage(),
                coin_count: this.getCoinCount(),
                last_save_date: new Date().toISOString(),
                completed_switches: progressData.completedSwitches,
                completed_chapters: progressData.completedChapters,
                completed_quests: progressData.completedQuests,
                collected_items: progressData.collectedItems,
                unlocked_achievements: progressData.unlockedAchievements,
                environment_mode: progressData.environmentMode
            };
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `${this.config.serverUrl}/php/save_progress.php`, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                console.log("✅ Progress saved to server successfully");
                            } else {
                                console.error("❌ Failed to save progress:", response.error);
                            }
                        } catch (e) {
                            console.error("❌ Error parsing progress save response:", e);
                        }
                    }
                }
            };
            
            const params = new URLSearchParams(serverData).toString();
            xhr.send(params);
        },
        
        // Load progress from server (only in production mode)
        loadProgressFromServer: function(callback) {
            if (!this.shouldSyncWithServer() || !$gameSystem) {
                if (this.config.developmentMode) {
                    console.log("🔧 Dev Mode: Skipping server load");
                }
                if (callback) callback(false);
                return;
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `${this.config.serverUrl}/php/load_progress.php`, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success && response.progress) {
                                // Update local progress data
                                const serverProgress = response.progress;
                                const progressData = this.getProgressData();
                                
                                // Only load if from same environment or if explicitly allowed
                                if (serverProgress.environment_mode === progressData.environmentMode || 
                                    !serverProgress.environment_mode) {
                                    
                                    progressData.completedSwitches = serverProgress.completed_switches || 0;
                                    progressData.completedChapters = serverProgress.completed_chapters || 0;
                                    progressData.completedQuests = serverProgress.completed_quests || 0;
                                    progressData.collectedItems = serverProgress.collected_items || 0;
                                    progressData.unlockedAchievements = serverProgress.unlocked_achievements || 0;
                                    progressData.playtimeHours = serverProgress.playtime_hours || 0;
                                    progressData.lastSaveDate = serverProgress.last_save_date;
                                    
                                    console.log("✅ Progress loaded from server successfully");
                                    if (callback) callback(true);
                                } else {
                                    console.log("⚠️ Server progress from different environment, skipping");
                                    if (callback) callback(false);
                                }
                            } else {
                                console.log("ℹ️ No saved progress found on server");
                                if (callback) callback(false);
                            }
                        } catch (e) {
                            console.error("❌ Error parsing progress load response:", e);
                            if (callback) callback(false);
                        }
                    } else {
                        console.error("❌ Failed to load progress from server");
                        if (callback) callback(false);
                    }
                }
            };
            
            const params = `user_id=${window.FilibusteroAuth.currentUser.id}`;
            xhr.send(params);
        },

        // Clear development data (utility function)
        clearDevelopmentData: function() {
            if (this.config.developmentMode && $gameSystem && $gameSystem.progressData) {
                $gameSystem.progressData = null;
                console.log("🧹 Development progress data cleared");
                this.initialize();
            }
        }
    };

    // =============================================
    // ENHANCED PROGRESS WINDOW
    // =============================================
    
    // Override the existing Window_Progress
    function Window_Progress() {
        this.initialize(...arguments);
    }

    Window_Progress.prototype = Object.create(Window_Base.prototype);
    Window_Progress.prototype.constructor = Window_Progress;

    Window_Progress.prototype.initialize = function(rect) {
        Window_Base.prototype.initialize.call(this, rect);
        this.refresh();
    };

    Window_Progress.prototype.refresh = function() {
        this.contents.clear();
        
        // Check if auth system is available (skip in dev mode)
        if (!window.FilibusteroProgress.config.developmentMode) {
            if (!window.FilibusteroAuth || !window.FilibusteroAuth.isLoggedIn) {
                this.drawText("Please log in to view progress", 0, 0, this.contentsWidth(), "center");
                return;
            }
        }
        
        // Check if game system is available
        if (!$gameSystem) {
            this.drawText("Game system not ready", 0, 0, this.contentsWidth(), "center");
            return;
        }
        
        // Initialize progress system
        window.FilibusteroProgress.initialize();
        
        let y = 0;
        const lineHeight = this.lineHeight();
        
        // Show mode indicator
        if (window.FilibusteroProgress.config.developmentMode) {
            this.changeTextColor(ColorManager.textColor(2)); // Red
            this.drawText("🔧 DEVELOPMENT MODE", 0, y, this.contentsWidth(), "center");
            y += lineHeight;
        }
        
        // User Info Header (skip in dev mode)
        if (!window.FilibusteroProgress.config.developmentMode && window.FilibusteroAuth && window.FilibusteroAuth.isLoggedIn) {
            const user = window.FilibusteroAuth.currentUser;
            this.changeTextColor(ColorManager.systemColor());
            this.drawText(`Player: ${user.full_name}`, 0, y, this.contentsWidth());
            y += lineHeight;
            this.drawText(`Account Type: ${user.user_type.toUpperCase()}`, 0, y, this.contentsWidth());
            y += lineHeight * 1.5;
        }
        
        // Overall Progress
        this.resetTextColor();
        const overallProgress = window.FilibusteroProgress.getOverallProgress();
        this.drawText("═══ OVERALL PROGRESS ═══", 0, y, this.contentsWidth(), "center");
        y += lineHeight;
        
        // Progress Bar
        this.drawProgressBar(0, y, this.contentsWidth(), overallProgress, `${Math.round(overallProgress)}%`);
        y += lineHeight * 2;
        
        // Current Stage and Coins
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("═══ CURRENT STATUS ═══", 0, y, this.contentsWidth(), "center");
        y += lineHeight;
        
        this.resetTextColor();
        const currentStage = window.FilibusteroProgress.getCurrentStage();
        const coinCount = window.FilibusteroProgress.getCoinCount();
        this.drawText(`Current Stage: ${currentStage}/13`, 0, y, this.contentsWidth());
        y += lineHeight;
        this.drawText(`Coins: ${coinCount}`, 0, y, this.contentsWidth());
        y += lineHeight * 1.5;
        
        // Category Progress
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("═══ DETAILED PROGRESS ═══", 0, y, this.contentsWidth(), "center");
        y += lineHeight;
        
        this.resetTextColor();
        
        // Story Progress
        const storyProgress = window.FilibusteroProgress.categories.story.progress;
        this.drawText("Story Progress:", 0, y, 200);
        this.drawText(`${Math.round(storyProgress)}%`, 200, y, 100);
        y += lineHeight * 0.7;
        this.drawProgressBar(20, y, this.contentsWidth() - 40, storyProgress);
        y += lineHeight * 1.3;
        
        // Chapter Progress
        const chapterProgress = window.FilibusteroProgress.categories.chapters.progress;
        this.drawText("Stage Progress:", 0, y, 200);
        this.drawText(`${Math.round(chapterProgress)}%`, 200, y, 100);
        y += lineHeight * 0.7;
        this.drawProgressBar(20, y, this.contentsWidth() - 40, chapterProgress);
        y += lineHeight * 1.3;
        
        // Quest Progress
        const questProgress = window.FilibusteroProgress.categories.quests.progress;
        this.drawText("Quest Progress:", 0, y, 200);
        this.drawText(`${Math.round(questProgress)}%`, 200, y, 100);
        y += lineHeight * 0.7;
        this.drawProgressBar(20, y, this.contentsWidth() - 40, questProgress);
        y += lineHeight * 1.3;
        
        // Item Collection
        const itemProgress = window.FilibusteroProgress.categories.items.progress;
        this.drawText("Item Collection:", 0, y, 200);
        this.drawText(`${Math.round(itemProgress)}%`, 200, y, 100);
        y += lineHeight * 0.7;
        this.drawProgressBar(20, y, this.contentsWidth() - 40, itemProgress);
        y += lineHeight * 1.3;
        
        // Achievement Progress
        const achievementProgress = window.FilibusteroProgress.categories.achievements.progress;
        this.drawText("Achievements:", 0, y, 200);
        this.drawText(`${Math.round(achievementProgress)}%`, 200, y, 100);
        y += lineHeight * 0.7;
        this.drawProgressBar(20, y, this.contentsWidth() - 40, achievementProgress);
        y += lineHeight * 1.5;
        
        // Statistics
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("═══ STATISTICS ═══", 0, y, this.contentsWidth(), "center");
        y += lineHeight;
        
        this.resetTextColor();
        
        // Detailed stats
        const progressData = window.FilibusteroProgress.getProgressData();
        this.drawText(`Completed Switches: ${progressData.completedSwitches}/${progressData.totalSwitches}`, 0, y, this.contentsWidth());
        y += lineHeight;
        this.drawText(`Completed Stages: ${progressData.completedChapters}/${progressData.totalChapters}`, 0, y, this.contentsWidth());
        y += lineHeight;
        this.drawText(`Completed Quests: ${progressData.completedQuests}/${progressData.totalQuests}`, 0, y, this.contentsWidth());
        y += lineHeight;
        this.drawText(`Collected Items: ${progressData.collectedItems}/${progressData.totalItems}`, 0, y, this.contentsWidth());
        y += lineHeight;
        this.drawText(`Unlocked Achievements: ${progressData.unlockedAchievements}/${progressData.totalAchievements}`, 0, y, this.contentsWidth());
        y += lineHeight;
        
        // Playtime
        const playtimeHours = window.FilibusteroProgress.getPlaytimeHours();
        this.drawText(`Total Playtime: ${playtimeHours} hours`, 0, y, this.contentsWidth());
        y += lineHeight * 1.5;
        
        // Last save info
        if (progressData.lastSaveDate) {
            const lastSave = new Date(progressData.lastSaveDate);
            this.drawText(`Last Save: ${lastSave.toLocaleDateString()}`, 0, y, this.contentsWidth());
            y += lineHeight;
        }
        
        // Environment info
        if (progressData.environmentMode) {
            this.changeTextColor(ColorManager.systemColor());
            this.drawText(`Environment: ${progressData.environmentMode.toUpperCase()}`, 0, y, this.contentsWidth());
            y += lineHeight;
        }
        
        y += lineHeight * 0.5;
        
        // Instructions
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Press ESC or Cancel to return", 0, y, this.contentsWidth(), "center");
        this.resetTextColor();
    };

    // Draw progress bar
    Window_Progress.prototype.drawProgressBar = function(x, y, width, progress, text) {
        const barHeight = 20;
        const borderColor = ColorManager.normalColor();
        const fillColor = ColorManager.textColor(3); // Green
        const backgroundColor = ColorManager.textColor(7); // Gray
        
        // Background
        this.contents.fillRect(x, y, width, barHeight, backgroundColor);
        
        // Progress fill
        const fillWidth = Math.floor((width - 4) * (progress / 100));
        if (fillWidth > 0) {
            this.contents.fillRect(x + 2, y + 2, fillWidth, barHeight - 4, fillColor);
        }
        
        // Border
        this.contents.strokeRect(x, y, width, barHeight, borderColor);
        
        // Text overlay
        if (text) {
            const oldSize = this.contents.fontSize;
            this.contents.fontSize = 14;
            this.contents.textColor = ColorManager.normalColor();
            this.contents.drawText(text, x, y + 2, width, barHeight - 4, "center");
            this.contents.fontSize = oldSize;
        }
    };

    // =============================================
    // ENHANCED PROGRESS HUD WINDOW (In-Game Overlay)
    // =============================================
    
    function Window_ProgressHUD() {
        this.initialize(...arguments);
    }

    Window_ProgressHUD.prototype = Object.create(Window_Base.prototype);
    Window_ProgressHUD.prototype.constructor = Window_ProgressHUD;

    Window_ProgressHUD.prototype.initialize = function() {
        // Made much wider and taller to accommodate all information
        const rect = new Rectangle(Graphics.boxWidth - 350, 10, 350, 140);
        Window_Base.prototype.initialize.call(this, rect);
        this.opacity = 240;
        this.refresh();
    };

    Window_ProgressHUD.prototype.refresh = function() {
        this.contents.clear();
        
        // Check if we should show HUD (skip auth check in dev mode)
        const shouldShow = window.FilibusteroProgress.config.developmentMode || 
                          (window.FilibusteroAuth && window.FilibusteroAuth.isLoggedIn);
        
        if (!shouldShow || !$gameSystem) return;
        
        window.FilibusteroProgress.updateAllProgress();
        const progress = window.FilibusteroProgress.getOverallProgress();
        const currentStage = window.FilibusteroProgress.getCurrentStage();
        const coinCount = window.FilibusteroProgress.getCoinCount();
        
        // Set larger font for better visibility
        this.contents.fontSize = 20;
        const lineHeight = 26;
        let y = 0;
        
        // Show dev mode indicator
        if (window.FilibusteroProgress.config.developmentMode) {
            this.changeTextColor(ColorManager.textColor(2)); // Red
            this.drawText("🔧 DEV", 0, y, 100);
            this.resetTextColor();
        }
        
        // Progress percentage
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Overall Progress:", 0, y, 200);
        this.changeTextColor(ColorManager.crisisColor());
        this.drawText(`${Math.round(progress)}%`, 200, y, 120, "right");
        y += lineHeight;
        
        // Current Stage
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Current Stage:", 0, y, 200);
        this.resetTextColor();
        this.drawText(`${currentStage}/13`, 200, y, 120, "right");
        y += lineHeight;
        
        // Coins
        this.changeTextColor(ColorManager.systemColor());
        this.drawText("Coins:", 0, y, 200);
        this.changeTextColor(ColorManager.textColor(17)); // Gold color
        this.drawText(`${coinCount}`, 200, y, 120, "right");
        y += lineHeight;
        
        // Progress bar
        this.resetTextColor();
        this.drawProgressBar(0, y, this.contentsWidth(), progress);
    };

    Window_ProgressHUD.prototype.drawProgressBar = function(x, y, width, progress) {
        const barHeight = 16;
        const fillColor = ColorManager.textColor(3); // Green
        const backgroundColor = ColorManager.textColor(8); // Dark gray
        const borderColor = ColorManager.normalColor();
        
        // Background
        this.contents.fillRect(x, y, width, barHeight, backgroundColor);
        
        // Progress fill
        const fillWidth = Math.floor((width - 2) * (progress / 100));
        if (fillWidth > 0) {
            this.contents.fillRect(x + 1, y + 1, fillWidth, barHeight - 2, fillColor);
        }
        
        // Border
        this.contents.strokeRect(x, y, width, barHeight, borderColor);
        
        // Progress text overlay
        const oldSize = this.contents.fontSize;
        this.contents.fontSize = 12;
        this.contents.textColor = ColorManager.normalColor();
        this.contents.drawText(`${Math.round(progress)}%`, x, y + 2, width, barHeight - 4, "center");
        this.contents.fontSize = oldSize;
    };

    // =============================================
    // INTEGRATE WITH EXISTING SCENES
    // =============================================
    
    // Add HUD to map scene
    const _Scene_Map_createAllWindows = Scene_Map.prototype.createAllWindows;
    Scene_Map.prototype.createAllWindows = function() {
        _Scene_Map_createAllWindows.call(this);
        
        // Show HUD in dev mode or when logged in
        const shouldShowHUD = window.FilibusteroProgress.config.developmentMode || 
                             (window.FilibusteroAuth && window.FilibusteroAuth.isLoggedIn);
        
        if (shouldShowHUD && $gameSystem) {
            this.createProgressHUD();
        }
    };

    Scene_Map.prototype.createProgressHUD = function() {
        this._progressHUD = new Window_ProgressHUD();
        this.addWindow(this._progressHUD);
        
        // Auto-refresh every 10 seconds for more responsive updates
        this._progressHUDInterval = setInterval(() => {
            if (this._progressHUD && !this._progressHUD.isDestroyed) {
                this._progressHUD.refresh();
            }
        }, 10000);
    };

    // Clean up HUD interval when leaving map
    const _Scene_Map_terminate = Scene_Map.prototype.terminate;
    Scene_Map.prototype.terminate = function() {
        if (this._progressHUDInterval) {
            clearInterval(this._progressHUDInterval);
            this._progressHUDInterval = null;
        }
        _Scene_Map_terminate.call(this);
    };

    // Save progress when saving game (only sync to server in production)
    const _DataManager_saveGame = DataManager.saveGame;
    DataManager.saveGame = function(savefileId) {
        const result = _DataManager_saveGame.call(this, savefileId);
        if (result && window.FilibusteroProgress.shouldSyncWithServer()) {
            window.FilibusteroProgress.saveProgressToServer();
        }
        return result;
    };

   // Load progress when loading game (only sync from server in production)
    const _DataManager_loadGame = DataManager.loadGame;
    DataManager.loadGame = function(savefileId) {
        const result = _DataManager_loadGame.call(this, savefileId);
        if (result && window.FilibusteroProgress.shouldSyncWithServer()) {
            // Load progress from server after local load
            window.FilibusteroProgress.loadProgressFromServer((success) => {
                if (success) {
                    console.log("✅ Server progress synchronized after load");
                } else {
                    console.log("ℹ️ Using local progress data");
                }
            });
        }
        return result;
    };

    // =============================================
    // PLUGIN COMMANDS
    // =============================================
    
    // Register plugin commands
    PluginManager.registerCommand(pluginName, "SaveProgress", args => {
        if (window.FilibusteroProgress.config.developmentMode) {
            console.log("🔧 Dev Mode: Manual save progress command (local only)");
        } else {
            window.FilibusteroProgress.saveProgressToServer();
        }
    });

    PluginManager.registerCommand(pluginName, "LoadProgress", args => {
        if (window.FilibusteroProgress.config.developmentMode) {
            console.log("🔧 Dev Mode: Manual load progress command (local only)");
        } else {
            window.FilibusteroProgress.loadProgressFromServer((success) => {
                if (success) {
                    $gameMessage.add("Progress loaded from server successfully!");
                } else {
                    $gameMessage.add("No server progress found or failed to load.");
                }
            });
        }
        // Add to your plugin commands section
    PluginManager.registerCommand(pluginName, "saveProgress", args => {
        saveProgressToDatabase();
    });

    // Add a global function for events
    window.FilibusteroProgress.saveToDatabase = function() {
        saveProgressToDatabase();
    };
    });

    // =============================================
    // DEVELOPMENT AND DEBUG UTILITIES
    // =============================================
    
    // Debug utilities for development
    window.FilibusteroProgressDebug = {
        // Set test values for development
        setTestValues: function() {
            if (!window.FilibusteroProgress.config.developmentMode) {
                console.log("⚠️ Debug functions only available in development mode");
                return;
            }
            
            if (!$gameSystem) {
                console.log("❌ Game system not available");
                return;
            }
            
            // Set some test switches
            for (let i = 1; i <= 50; i++) {
                $gameSwitches.setValue(i, Math.random() > 0.5);
            }
            
            // Set some test quest switches
            for (let i = 101; i <= 110; i++) {
                $gameSwitches.setValue(i, Math.random() > 0.7);
            }
            
            // Set some test achievement switches
            for (let i = 201; i <= 205; i++) {
                $gameSwitches.setValue(i, Math.random() > 0.8);
            }
            
            // Set current stage
            $gameVariables.setValue(1, Math.floor(Math.random() * 13) + 1);
            
            // Set coin count
            $gameVariables.setValue(2, Math.floor(Math.random() * 10000) + 1000);
            
            console.log("🎮 Test values set successfully");
        },
        
        // Refresh HUD manually
        refreshHUD: function() {
            if (SceneManager._scene && SceneManager._scene._progressHUD) {
                SceneManager._scene._progressHUD.refresh();
                console.log("🔄 HUD refreshed manually");
            } else {
                console.log("❌ HUD not found in current scene");
            }
        },
        
        // Show current progress data
        showProgressData: function() {
            if (!$gameSystem) {
                console.log("❌ Game system not available");
                return;
            }
            
            window.FilibusteroProgress.updateAllProgress();
            const data = window.FilibusteroProgress.getProgressData();
            const overall = window.FilibusteroProgress.getOverallProgress();
            
            console.log("📊 Current Progress Data:");
            console.log(`Overall Progress: ${Math.round(overall)}%`);
            console.log(`Story: ${Math.round(window.FilibusteroProgress.categories.story.progress)}%`);
            console.log(`Chapters: ${Math.round(window.FilibusteroProgress.categories.chapters.progress)}%`);
            console.log(`Quests: ${Math.round(window.FilibusteroProgress.categories.quests.progress)}%`);
            console.log(`Items: ${Math.round(window.FilibusteroProgress.categories.items.progress)}%`);
            console.log(`Achievements: ${Math.round(window.FilibusteroProgress.categories.achievements.progress)}%`);
            console.log("Raw data:", data);
        },
        
        // Clear all progress (development only)
        clearAllProgress: function() {
            if (!window.FilibusteroProgress.config.developmentMode) {
                console.log("⚠️ Clear function only available in development mode");
                return;
            }
            
            if (!$gameSystem) {
                console.log("❌ Game system not available");
                return;
            }
            
            // Clear all switches
            for (let i = 1; i <= 220; i++) {
                $gameSwitches.setValue(i, false);
            }
            
            // Reset variables
            $gameVariables.setValue(1, 1); // Reset to stage 1
            $gameVariables.setValue(2, 0); // Reset coins
            
            // Clear progress data
            window.FilibusteroProgress.clearDevelopmentData();
            
            console.log("🧹 All progress cleared (development mode)");
        }
    };

    // =============================================
    // INITIALIZATION
    // =============================================
    
    // Initialize when the plugin loads
    const _Scene_Boot_start = Scene_Boot.prototype.start;
    Scene_Boot.prototype.start = function() {
        _Scene_Boot_start.call(this);
        
        // Initialize progress system
        if ($gameSystem) {
            window.FilibusteroProgress.initialize();
        }
        
        // Show initialization message
        if (window.FilibusteroProgress.config.developmentMode) {
            console.log("🎮 Filibustero Progress Tracker initialized (Development Mode)");
            console.log("🔧 Available debug commands:");
            console.log("  - FilibusteroProgressDebug.setTestValues()");
            console.log("  - FilibusteroProgressDebug.refreshHUD()");
            console.log("  - FilibusteroProgressDebug.showProgressData()");
            console.log("  - FilibusteroProgressDebug.clearAllProgress()");
        } else {
            console.log("🎮 Filibustero Progress Tracker initialized (Production Mode)");
        }
    };

    // Auto-update progress when switches/variables change
    const _Game_Switches_setValue = Game_Switches.prototype.setValue;
    Game_Switches.prototype.setValue = function(switchId, value) {
        _Game_Switches_setValue.call(this, switchId, value);
        
        // Update progress if this is a tracked switch
        if (switchId >= 1 && switchId <= 220) {
            setTimeout(() => {
                window.FilibusteroProgress.updateAllProgress();
                
                // Refresh HUD if available
                if (SceneManager._scene && SceneManager._scene._progressHUD) {
                    SceneManager._scene._progressHUD.refresh();
                }
            }, 100);
        }
    };

    const _Game_Variables_setValue = Game_Variables.prototype.setValue;
    Game_Variables.prototype.setValue = function(variableId, value) {
        _Game_Variables_setValue.call(this, variableId, value);
        
        // Update progress if this is a tracked variable (stage or coins)
        if (variableId === 1 || variableId === 2) {
            setTimeout(() => {
                window.FilibusteroProgress.updateAllProgress();
                
                // Refresh HUD if available
                if (SceneManager._scene && SceneManager._scene._progressHUD) {
                    SceneManager._scene._progressHUD.refresh();
                }
            }, 100);
        }
    };

    // =============================================
    // SCENE INTEGRATION FOR PROGRESS WINDOW
    // =============================================
    
    // Add progress window to existing scenes that might need it
    // This allows other parts of the game to open the progress window
    window.FilibusteroProgress.openProgressWindow = function() {
        if (!$gameSystem) {
            console.log("❌ Game system not available");
            return;
        }
        
        // Create a simple scene to show progress
        const scene = SceneManager._scene;
        if (scene && scene.constructor === Scene_Map) {
            // Create full-screen progress window
            const rect = new Rectangle(50, 50, Graphics.boxWidth - 100, Graphics.boxHeight - 100);
            const progressWindow = new Window_Progress(rect);
            
            // Add to current scene temporarily
            scene.addWindow(progressWindow);
            progressWindow.activate();
            
            // Handle input to close
            const originalUpdate = progressWindow.update;
            progressWindow.update = function() {
                originalUpdate.call(this);
                
                if (Input.isTriggered('cancel') || Input.isTriggered('menu')) {
                    scene.removeWindow(this);
                    this.destroy();
                }
            };
        }
    };

    // Add this to your FilibusteroProgressBar.js plugin, after the parameters section

// Database connection parameters
const dbHost = "127.0.0.1";
const dbName = "filibustero_db";
const dbUser = "root"; // Change as needed
const dbPass = ""; // Change as needed

// Function to save progress to database
function saveProgressToDatabase() {
    // Get current progress values
    const coins = $gameVariables.value(coinVariable);
    const score = $gameVariables.value(scoreVariable);
    const currentStage = $gameVariables.value(currentStageVariable);
    const completedQuests = $gameVariables.value(completedQuestsVariable);
    const progressPercent = Math.min(Math.floor((completedQuests / totalQuests) * 100), 100);
    
    // Get user ID (you'll need to set this variable when user logs in)
    const userId = $gameVariables.value(1); // Assuming user ID is stored in variable 1
    
    if (!userId) {
        console.warn("Cannot save progress: No user ID found");
        return;
    }
    
    // Make AJAX call to save progress
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'https://filibustero-web.com/save_progress.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            console.log("Progress saved successfully");
        } else {
            console.error("Error saving progress:", xhr.statusText);
        }
    };
    
    xhr.onerror = function() {
        console.error("Request failed");
    };
    
    const data = JSON.stringify({
        user_id: userId,
        coins: coins,
        score: score,
        current_stage: currentStage,
        progress_percentage: progressPercent,
        completed_quests: completedQuests
    });
    
    xhr.send(data);
}

// Add auto-save functionality
const _Scene_Map_update = Scene_Map.prototype.update;
Scene_Map.prototype.update = function() {
    _Scene_Map_update.call(this);
    this.updateProgressBarToggle();
    this.updateAutoSave();
};

Scene_Map.prototype.updateAutoSave = function() {
    if (!this._lastSaveTime) this._lastSaveTime = 0;
    
    // Auto-save every 60 seconds (600 frames)
    if (Graphics.frameCount - this._lastSaveTime > 600) {
        saveProgressToDatabase();
        this._lastSaveTime = Graphics.frameCount;
    }
};

    

})();