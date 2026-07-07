//=============================================================================
// RPG Maker MZ - Enhanced God Mode Plugin
//=============================================================================

/*:
 * @target MZ
 * @plugindesc Enhanced god mode system with F1 key access for stage teleportation
 * @author Assistant
 *
 * @help EnhancedGodMode.js
 *
 * This plugin provides a comprehensive god mode system that can be accessed
 * anywhere in the game by pressing F1.
 * 
 * Features:
 * - Press F1 to open the god mode menu anywhere
 * - Teleport to any stage with proper variable setup
 * - Automatically sets coins, score, quests, and stage progress
 * - Works across all maps
 * - Integrates with GodModeHelper.js and FilibusteroProgressBar plugins
 *
 * Requirements:
 * - GodModeHelper.js plugin must be installed
 * - FilibusteroProgressBar plugin must be installed
 * 
 * =============================================================================
 * Map Name Configuration:
 * =============================================================================
 * Make sure your map names in the editor match these exactly:
 * - San Diego Phase 1
 * - San Diego Phase 2
 * - Bahay ni Kapitan Tiago
 * - Karwahe background
 * - Mountain
 * - Lawa
 * - Ilog
 * - School before
 * - Bahay ni Ibarra
 * - Kulungan
 * - Inside Palengke
 * - Masukal na Bukirin
 * - Bahay ni Hermana Penchang
 * - Bahay ni Quiroga
 * - Silid aralan
 * - Kalsada Patungo sa Sto. Tomas
 * - Bahay ni Hermana Bali
 * - Laboratoryo ni Simoun
 * - Bahay ni Padre Florentino
 */

(() => {
    'use strict';

    const pluginName = "EnhancedGodMode";

    //=============================================================================
    // Map ID Configuration
    //=============================================================================
    
    const MAP_IDS = {
        "San Diego Phase 1": 7,
        "Bahay ni Kapitan Tiago": 16,
        "San Diego Phase 2": 27,
        "Karwahe background": 20,
        "Mountain": 6,
        "Lawa": 25,
        "Ilog": 9,
        "School before": 28,
        "Bahay ni Ibarra": 17,
        "Kulungan": 22,
        "Inside Palengke": 1,
        "Masukal na Bukirin": 50,
        "Bahay ni Hermana Penchang": 47,
        "Bahay ni Quiroga": 44,
        "Silid aralan": 45,
        "Kalsada Patungo sa Sto. Tomas": 60,
        "Bahay ni Hermana Bali": 34,
        "Laboratoryo ni Simoun": 58,
        "Bahay ni Padre Florentino": 33
    };

    //=============================================================================
    // Stage Data Configuration
    //=============================================================================
    
    const STAGE_DATA = {
        noli: {
            name: "Noli Me Tangere",
            stages: {
                1: {
                    name: "Stage 1",
                    map: "San Diego Phase 1",
                    x: 19, y: 33, d: 2,
                    variables: { 1: 1 },
                    score: 0, coins: 0, stageNum: 1, quests: 0,
                    currentStageVar: 1,  // Which stage variable to set (1-13 for Noli, 14+ for Elfili)
                    resetCoins: true, resetScore: true, resetQuests: true
                },
                2: {
                    name: "Stage 2",
                    map: "Bahay ni Kapitan Tiago",
                    x: 13, y: 21, d: 2,
                    variables: { 2: 1 },
                    score: 5, coins: 5, stageNum: 2, quests: 1,
                    currentStageVar: 2
                },
                3: {
                    name: "Stage 3",
                    map: "San Diego Phase 2",
                    x: 19, y: 28, d: 2,
                    variables: { 2: 15 },
                    score: 20, coins: 22, stageNum: 3, quests: 3,
                    currentStageVar: 3
                },
                4: {
                    name: "Stage 4",
                    map: "San Diego Phase 2",
                    x: 18, y: 20, d: 2,
                    variables: { 3: 10, 4: 0 },
                    score: 25, coins: 34, stageNum: 4, quests: 3,
                    currentStageVar: 4
                },
                5: {
                    name: "Stage 5",
                    map: "Karwahe background",
                    x: 16, y: 12, d: 2,
                    variables: { 5: 1 },
                    score: 30, coins: 46, stageNum: 6, quests: 6,
                    currentStageVar: 6
                },
                6: {
                    name: "Stage 6",
                    map: "Mountain",
                    x: 8, y: 26, d: 4,
                    variables: { 5: 9, 6: 0 },
                    score: 35, coins: 58, stageNum: 7, quests: 7,
                    currentStageVar: 7
                },
                7: {
                    name: "Stage 7",
                    map: "Lawa",
                    x: 12, y: 22, d: 2,
                    variables: { 6: 9, 7: 0 },
                    score: 40, coins: 63, stageNum: 8, quests: 9,
                    currentStageVar: 8
                },
                8: {
                    name: "Stage 8",
                    map: "Ilog",
                    x: 27, y: 15, d: 8,
                    variables: { 8: 1 },
                    score: 45, coins: 75, stageNum: 9, quests: 12,
                    currentStageVar: 9
                },
                9: {
                    name: "Stage 9",
                    map: "School before",
                    x: 4, y: 5, d: 6,
                    variables: { 8: 7, 9: 0 },
                    score: 45, coins: 75, stageNum: 10, quests: 13,
                    currentStageVar: 10
                },
                10: {
                    name: "Stage 10",
                    map: "San Diego Phase 2",
                    x: 8, y: 17, d: 4,
                    variables: { 9: 11, 10: 0 },
                    score: 50, coins: 75, stageNum: 11, quests: 14,
                    currentStageVar: 11
                },
                11: {
                    name: "Stage 11",
                    map: "Bahay ni Kapitan Tiago",
                    x: 14, y: 10, d: 8,
                    variables: { 11: 1, 10: 0 },
                    score: 50, coins: 87, stageNum: 12, quests: 16,
                    currentStageVar: 12
                },
                12: {
                    name: "Stage 12",
                    map: "Bahay ni Ibarra",
                    x: 15, y: 16, d: 4,
                    variables: { 11: 7, 12: 0 },
                    score: 50, coins: 94, stageNum: 13, quests: 18,
                    currentStageVar: 13
                },
                13: {
                    name: "Stage 13",
                    map: "Kulungan",
                    x: 9, y: 8, d: 2,
                    variables: { 12: 5, 13: 0 },
                    score: 50, coins: 101, stageNum: 14, quests: 20,
                    currentStageVar: 14
                }
            }
        },
        elfili: {
            name: "El Filibusterismo",
            stages: {
                1: {
                    name: "Stage 1",
                    map: "Inside Palengke",
                    x: 9, y: 9, d: 2,
                    variables: {},
                    score: 50, coins: 108, stageNum: 14, quests: 22,
                    currentStageVar: 14
                },
                2: {
                    name: "Stage 2",
                    map: "Masukal na Bukirin",
                    x: 8, y: 12, d: 8,
                    variables: { 35: 1 },
                    score: 58, coins: 25, stageNum: 16, quests: 23,
                    currentStageVar: 16
                },
                3: {
                    name: "Stage 3",
                    map: "Bahay ni Hermana Penchang",
                    x: 8, y: 14, d: 2,
                    variables: { 36: 0 },
                    score: 73, coins: 47, stageNum: 17, quests: 25,
                    currentStageVar: 17
                },
                4: {
                    name: "Stage 4",
                    map: "Bahay ni Quiroga",
                    x: 9, y: 8, d: 8,
                    variables: { 36: 16, 37: 0 },
                    score: 88, coins: 69, stageNum: 20, quests: 27,
                    currentStageVar: 20
                },
                5: {
                    name: "Stage 5",
                    map: "Silid aralan",
                    x: 7, y: 6, d: 2,
                    variables: { 37: 6, 38: 0 },
                    score: 103, coins: 84, stageNum: 23, quests: 28,
                    currentStageVar: 23
                },
                6: {
                    name: "Stage 6",
                    map: "Kalsada Patungo sa Sto. Tomas",
                    x: 24, y: 10, d: 8,
                    variables: { 39: 2 },
                    score: 112, coins: 94, stageNum: 26, quests: 31,
                    currentStageVar: 26
                },
                7: {
                    name: "Stage 7",
                    map: "Bahay ni Hermana Bali",
                    x: 5, y: 13, d: 8,
                    variables: { 39: 9, 40: 0 },
                    score: 120, coins: 111, stageNum: 27, quests: 33,
                    currentStageVar: 27
                },
                8: {
                    name: "Stage 8",
                    map: "Laboratoryo ni Simoun",
                    x: 9, y: 7, d: 2,
                    variables: { 40: 10, 41: 0 },
                    score: 126, coins: 134, stageNum: 28, quests: 35,
                    currentStageVar: 28
                },
                9: {
                    name: "Ending",
                    map: "Bahay ni Padre Florentino",
                    x: 10, y: 17, d: 2,
                    variables: { 41: 11 },
                    score: 149, coins: 148, stageNum: 29, quests: 38,
                    currentStageVar: 29
                }
            }
        }
    };

    //=============================================================================
    // Helper Functions
    //=============================================================================

    function findMapIdByName(mapName) {
        // First try our direct map ID lookup
        if (MAP_IDS[mapName]) {
            return MAP_IDS[mapName];
        }
        
        // Fallback to searching through dataMapInfos
        const dataMapInfos = $dataMapInfos;
        for (let i = 1; i < dataMapInfos.length; i++) {
            if (dataMapInfos[i] && dataMapInfos[i].name === mapName) {
                return i;
            }
        }
        console.warn(`[Enhanced God Mode] Map not found: ${mapName}`);
        return null;
    }

    function resetAllVariables() {
        // Reset variables 1-42 to 0
        for (let i = 1; i <= 42; i++) {
            $gameVariables.setValue(i, 0);
        }
    }

    function resetCurrentMapEvents() {
        const mapId = $gameMap.mapId();
        const keys = Object.keys($gameSelfSwitches._data);
        
        keys.forEach(key => {
            const keyParts = key.split(',');
            if (parseInt(keyParts[0]) === mapId) {
                delete $gameSelfSwitches._data[key];
            }
        });
        
        $gameMap.requestRefresh();
    }

    function callProgressBarPlugin(command, args) {
        try {
            // Use PluginManager to call the command properly
            PluginManager.callCommand($gameMap._interpreter, 'FilibusteroProgressBar', command, args);
        } catch (e) {
            console.warn('[Enhanced God Mode] Error calling FilibusteroProgressBar:', e);
        }
    }

    function teleportToStage(stageData) {
        console.log('[Enhanced God Mode] Teleporting to stage:', stageData);
        
        // Reset all variables first
        resetAllVariables();
        
        // Clear weather and screen effects
        $gameScreen.changeWeather('none', 0, 0);
        $gameScreen.startTint([0, 0, 0, 0], 0);
        AudioManager.fadeOutBgs(1);
        
        // Set stage-specific variables ONLY (not cumulative)
        for (const [varId, value] of Object.entries(stageData.variables)) {
            $gameVariables.setValue(parseInt(varId), value);
            console.log(`[Enhanced God Mode] Set Variable ${varId} = ${value}`);
        }
        
        // Set the system variables directly - NO PLUGIN CALLS
        $gameVariables.setValue(14, stageData.coins);     // Coins (Coin Variable ID)
        $gameVariables.setValue(15, stageData.score);     // Score (Score Variable ID)
        $gameVariables.setValue(16, stageData.stageNum);  // Current Stage (Current Stage Variable ID)
        $gameVariables.setValue(17, stageData.quests);    // Completed Quests
        $gameVariables.setValue(19, stageData.coins);     // Coin Count Variable ID (duplicate for display)
        
        console.log(`[Enhanced God Mode] Set Variables - Coins: ${stageData.coins}, Score: ${stageData.score}, Stage: ${stageData.stageNum}, Quests: ${stageData.quests}`);
        
        // Find map ID and transfer
        const mapId = findMapIdByName(stageData.map);
        console.log(`[Enhanced God Mode] Looking for map "${stageData.map}", found ID: ${mapId}`);
        
        if (mapId) {
            $gamePlayer.reserveTransfer(mapId, stageData.x, stageData.y, stageData.d, 0);
            SceneManager.goto(Scene_Map);
        } else {
            console.error(`[Enhanced God Mode] Cannot transfer to map: ${stageData.map}`);
            alert(`Map not found: "${stageData.map}"\nPlease check your map names in the editor.`);
        }
    }

    //=============================================================================
    // Scene_GodMode - Main Menu Scene
    //=============================================================================

    class Scene_GodMode extends Scene_MenuBase {
        create() {
            super.create();
            this.createCategoryWindow();
            this.createStageWindow();
        }

        createCategoryWindow() {
            const rect = this.categoryWindowRect();
            this._categoryWindow = new Window_GodModeCategory(rect);
            this._categoryWindow.setHandler('noli1', this.onCategoryOk.bind(this));
            this._categoryWindow.setHandler('noli2', this.onCategoryOk.bind(this));
            this._categoryWindow.setHandler('elfili1', this.onCategoryOk.bind(this));
            this._categoryWindow.setHandler('elfili2', this.onCategoryOk.bind(this));
            this._categoryWindow.setHandler('cancel', this.popScene.bind(this));
            this.addWindow(this._categoryWindow);
        }

        createStageWindow() {
            const rect = this.stageWindowRect();
            this._stageWindow = new Window_GodModeStage(rect);
            this._stageWindow.setHandler('ok', this.onStageOk.bind(this));
            this._stageWindow.setHandler('cancel', this.onStageCancel.bind(this));
            this._stageWindow.hide();
            this.addWindow(this._stageWindow);
        }

        categoryWindowRect() {
            const ww = Graphics.boxWidth;
            const wh = this.calcWindowHeight(4, true);
            const wx = 0;
            const wy = this.mainAreaTop();
            return new Rectangle(wx, wy, ww, wh);
        }

        stageWindowRect() {
            const ww = Graphics.boxWidth;
            const wh = this.mainAreaHeight() - this._categoryWindow.height;
            const wx = 0;
            const wy = this.mainAreaTop() + this._categoryWindow.height;
            return new Rectangle(wx, wy, ww, wh);
        }

        onCategoryOk() {
            const symbol = this._categoryWindow.currentSymbol();
            this._stageWindow.setCategory(symbol);
            this._stageWindow.show();
            this._stageWindow.activate();
            this._stageWindow.selectLast();
        }

        onStageOk() {
            const stageData = this._stageWindow.currentStageData();
            if (stageData) {
                teleportToStage(stageData);
            }
        }

        onStageCancel() {
            this._stageWindow.hide();
            this._stageWindow.deactivate();
            this._categoryWindow.activate();
        }
    }

    //=============================================================================
    // Window_GodModeCategory - Category Selection Window
    //=============================================================================

    class Window_GodModeCategory extends Window_HorzCommand {
        maxCols() {
            return 4;
        }

        makeCommandList() {
            this.addCommand("Noli Stage 1-7", 'noli1');
            this.addCommand("Noli Stage 8-13", 'noli2');
            this.addCommand("Elfili Stage 1-4", 'elfili1');
            this.addCommand("Elfili Stage 5-9", 'elfili2');
        }
    }

    //=============================================================================
    // Window_GodModeStage - Stage Selection Window
    //=============================================================================

    class Window_GodModeStage extends Window_Selectable {
        initialize(rect) {
            super.initialize(rect);
            this._category = null;
            this._data = [];
        }

        setCategory(category) {
            if (this._category !== category) {
                this._category = category;
                this.refresh();
                this.scrollTo(0, 0);
            }
        }

        maxCols() {
            return 1;
        }

        colSpacing() {
            return 16;
        }

        maxItems() {
            return this._data ? this._data.length : 0;
        }

        makeItemList() {
            this._data = [];
            
            if (!this._category) return;
            
            if (this._category === 'noli1') {
                for (let i = 1; i <= 7; i++) {
                    this._data.push({ book: 'noli', stage: i });
                }
            } else if (this._category === 'noli2') {
                for (let i = 8; i <= 13; i++) {
                    this._data.push({ book: 'noli', stage: i });
                }
            } else if (this._category === 'elfili1') {
                for (let i = 1; i <= 4; i++) {
                    this._data.push({ book: 'elfili', stage: i });
                }
            } else if (this._category === 'elfili2') {
                for (let i = 5; i <= 9; i++) {
                    this._data.push({ book: 'elfili', stage: i });
                }
            }
        }

        refresh() {
            this.makeItemList();
            super.refresh();
        }

        currentStageData() {
            const item = this._data[this.index()];
            if (!item) return null;
            return STAGE_DATA[item.book].stages[item.stage];
        }

        drawItem(index) {
            const item = this._data[index];
            if (!item) return;
            
            const stageData = STAGE_DATA[item.book].stages[item.stage];
            const rect = this.itemLineRect(index);
            
            const stageName = `${stageData.name}`;
            const mapInfo = `→ ${stageData.map} (${stageData.x}, ${stageData.y})`;
            const progressInfo = `Score: +${stageData.score} | Coins: +${stageData.coins} | Quests: +${stageData.quests}`;
            
            this.changePaintOpacity(true);
            this.drawText(stageName, rect.x, rect.y, rect.width - 8);
            
            this.changeTextColor(ColorManager.systemColor());
            this.drawText(mapInfo, rect.x + 150, rect.y, rect.width - 158, 'left');
            
            this.changeTextColor(ColorManager.textColor(3));
            this.drawText(progressInfo, rect.x, rect.y + this.lineHeight(), rect.width - 8, 'left');
            
            this.resetTextColor();
        }

        itemHeight() {
            return this.lineHeight() * 2 + 8;
        }

        selectLast() {
            this.select(0);
        }
    }

    //=============================================================================
    // Input Handling - F1 Key
    //=============================================================================

    const _Scene_Map_update = Scene_Map.prototype.update;
    Scene_Map.prototype.update = function() {
        _Scene_Map_update.call(this);
        
        if (Input.isTriggered('pageup')) { // F1 key
            SceneManager.push(Scene_GodMode);
        }
    };

    // Register F1 as pageup button
    Input.keyMapper[112] = 'pageup'; // F1 key code

    //=============================================================================
    // Scene Manager Integration
    //=============================================================================

    window.Scene_GodMode = Scene_GodMode;
    window.Window_GodModeCategory = Window_GodModeCategory;
    window.Window_GodModeStage = Window_GodModeStage;

})();