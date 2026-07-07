/*:
 * @target MZ
 * @plugindesc Customizes the menu by removing unwanted commands
 * @author YourName
 */

(function() {
    // Override the addMainCommands method to exclude unwanted commands
    const _Window_MenuCommand_addMainCommands = Window_MenuCommand.prototype.addMainCommands;
    Window_MenuCommand.prototype.addMainCommands = function() {
        _Window_MenuCommand_addMainCommands.call(this);
        
        // Remove unwanted commands
        this.removeCommand('item');
        this.removeCommand('skill');
        this.removeCommand('equip');
        this.removeCommand('status');
        this.removeCommand('formation');
    };

    // Helper function to remove commands by symbol
    Window_MenuCommand.prototype.removeCommand = function(symbol) {
        for (let i = 0; i < this._list.length; i++) {
            if (this._list[i].symbol === symbol) {
                this._list.splice(i, 1);
                break;
            }
        }
    };

    // Also need to handle the formation command in the status window
    const _Window_MenuStatus_initialize = Window_MenuStatus.prototype.initialize;
    Window_MenuStatus.prototype.initialize = function(rect) {
        _Window_MenuStatus_initialize.call(this, rect);
        this._formationMode = false; // Disable formation mode
    };

    // Prevent formation mode from being activated
    Window_MenuStatus.prototype.setFormationMode = function(formationMode) {
        // Do nothing - formation is disabled
    };
})();