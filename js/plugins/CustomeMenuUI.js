/*:
 * @target MZ
 * @plugindesc Customizes the menu by removing unwanted elements and commands, centers the menu
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

    // Override Scene_Menu create method to skip creating status window
    const _Scene_Menu_create = Scene_Menu.prototype.create;
    Scene_Menu.prototype.create = function() {
        // Call the original create but skip status window creation
        Scene_MenuBase.prototype.create.call(this);
        this.createCommandWindow();
        this.createGoldWindow(); // We'll handle this separately
    };

    // Completely skip creating the status window
    Scene_Menu.prototype.createStatusWindow = function() {
        this._statusWindow = null; // Set to null to prevent errors
    };

    // Skip creating gold window or create an empty one
    Scene_Menu.prototype.createGoldWindow = function() {
        // Create a dummy window that doesn't display anything
        const rect = this.goldWindowRect();
        this._goldWindow = new Window_Base(rect);
        this._goldWindow.openness = 0; // Make it completely transparent
        this._goldWindow.visible = false; // Make it invisible
        this.addWindow(this._goldWindow);
    };

    // Adjust the command window position to center it
    Scene_Menu.prototype.commandWindowRect = function() {
        const ww = this.mainCommandWidth();
        const wh = this.calcWindowHeight(4, true); // Height for 4 commands
        const wx = (Graphics.boxWidth - ww) / 2; // Center horizontally
        const wy = (Graphics.boxHeight - wh) / 2; // Center vertically
        return new Rectangle(wx, wy, ww, wh);
    };

    // Override the start method to avoid refreshing non-existent status window
    const _Scene_Menu_start = Scene_Menu.prototype.start;
    Scene_Menu.prototype.start = function() {
        Scene_MenuBase.prototype.start.call(this);
        // Skip status window refresh since it doesn't exist
    };

    // Handle any methods that might try to access the status window
    Scene_Menu.prototype.onPersonalOk = function() {
        // Do nothing since we've removed these features
    };

    Scene_Menu.prototype.onPersonalCancel = function() {
        this._commandWindow.activate();
    };

    Scene_Menu.prototype.onFormationOk = function() {
        // Do nothing - formation is disabled
    };

    Scene_Menu.prototype.onFormationCancel = function() {
        this._commandWindow.activate();
    };

    // Disable actor cycling since we removed the status window
    Window_MenuCommand.prototype.needsPageButtons = function() {
        return false;
    };

    // Disable formation mode completely
    Window_MenuStatus.prototype.setFormationMode = function(formationMode) {
        // Do nothing - formation is disabled
    };

    // Also center the gold window (even though it's hidden)
    Scene_Menu.prototype.goldWindowRect = function() {
        const ww = this.mainCommandWidth();
        const wh = this.calcWindowHeight(1, true);
        const wx = (Graphics.boxWidth - ww) / 2; // Center horizontally
        const wy = Graphics.boxHeight - wh - 10; // Position at bottom with some margin
        return new Rectangle(wx, wy, ww, wh);
    };
})();