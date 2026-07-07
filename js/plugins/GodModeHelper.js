//=============================================================================
// RPG Maker MZ - God Mode Helper Plugin
//=============================================================================

/*:
 * @target MZ
 * @plugindesc Helps reset variables and self-switches for god mode testing
 * @author Assistant
 *
 * @help GodModeHelper.js
 *
 * This plugin provides simple commands to help with your god mode system.
 * 
 * =============================================================================
 * Plugin Commands:
 * =============================================================================
 *
 * @command resetAllStageVariables
 * @text Reset All Stage Variables
 * @desc Resets variables 1-42 to 0 (use before setting specific stage values)
 *
 * @command resetCurrentMapEvents
 * @text Reset Current Map Events
 * @desc Clears all self-switches on the current map
 *
 * @command resetSpecificVariables
 * @text Reset Specific Variables
 * @desc Reset a range of variables to 0
 * 
 * @arg startVar
 * @type number
 * @min 1
 * @text Start Variable ID
 * @desc First variable to reset
 * @default 1
 * 
 * @arg endVar
 * @type number
 * @min 1
 * @text End Variable ID
 * @desc Last variable to reset
 * @default 42
 *
 * @command setMultipleVariables
 * @text Set Multiple Variables
 * @desc Quickly set multiple variables at once (format: ID:VALUE,ID:VALUE)
 * 
 * @arg variableList
 * @type string
 * @text Variable List
 * @desc Format: 1:0,2:15,5:4 (sets Var1=0, Var2=15, Var5=4)
 * @default 1:0,2:0
 *
 * @command resetWeather
 * @text Reset Weather
 * @desc Clears all weather effects (rain, storm, snow)
 */

(() => {
    'use strict';

    const pluginName = "GodModeHelper";

    //-----------------------------------------------------------------------------
    // Plugin Commands
    //-----------------------------------------------------------------------------

    PluginManager.registerCommand(pluginName, "resetAllStageVariables", args => {
        // Reset variables 1-42 to 0
        for (let i = 1; i <= 42; i++) {
            $gameVariables.setValue(i, 0);
        }
        console.log('[God Mode] Reset all stage variables (1-42) to 0');
    });

    PluginManager.registerCommand(pluginName, "resetCurrentMapEvents", args => {
        const mapId = $gameMap.mapId();
        const keys = Object.keys($gameSelfSwitches._data);
        let count = 0;
        
        keys.forEach(key => {
            const keyParts = key.split(',');
            if (parseInt(keyParts[0]) === mapId) {
                delete $gameSelfSwitches._data[key];
                count++;
            }
        });
        
        $gameMap.requestRefresh();
        console.log(`[God Mode] Reset ${count} self-switches on Map ${mapId}`);
    });

    PluginManager.registerCommand(pluginName, "resetSpecificVariables", args => {
        const startVar = parseInt(args.startVar) || 1;
        const endVar = parseInt(args.endVar) || 42;
        
        for (let i = startVar; i <= endVar; i++) {
            $gameVariables.setValue(i, 0);
        }
        
        console.log(`[God Mode] Reset variables ${startVar}-${endVar} to 0`);
    });

    PluginManager.registerCommand(pluginName, "setMultipleVariables", args => {
        const variableList = args.variableList || "";
        const pairs = variableList.split(',');
        let count = 0;
        
        pairs.forEach(pair => {
            const [varId, value] = pair.split(':').map(s => parseInt(s.trim()));
            if (!isNaN(varId) && !isNaN(value)) {
                $gameVariables.setValue(varId, value);
                count++;
            }
        });
        
        console.log(`[God Mode] Set ${count} variables`);
    });

    PluginManager.registerCommand(pluginName, "resetWeather", args => {
        // Clear weather effect
        $gameScreen.changeWeather('none', 0, 0);
        console.log('[God Mode] Weather reset to none');
    });

})();