//=============================================================================
// Student Rankings Menu Plugin
// Version: 1.2.0
// Adds student rankings to the main menu system
//=============================================================================

/*:
 * @target MZ
 * @plugindesc [v1.2.0] Student Rankings Menu System
 * @author YourName
 * @url 
 * @help StudentRankingsMenu.js
 * 
 * @param serverUrl
 * @text Server URL
 * @desc Base URL for your server (e.g., https://filibustero-web.com/your-game/)
 * @type string
 * @default https://filibustero-web.com/filibustero/
 * 
 * @param menuText
 * @text Menu Text
 * @desc Text to display in the menu
 * @type string
 * @default Section Progress
 * 
 * @param maxRankingsDisplay
 * @text Max Rankings to Display
 * @desc Maximum number of rankings to show per page
 * @type number
 * @default 8
 * 
 * @param refreshInterval
 * @text Auto Refresh Interval (seconds)
 * @desc How often to refresh rankings automatically (0 = disabled)
 * @type number
 * @default 30
 * 
 * @command openRankings
 * @text Open Rankings Menu
 * @desc Open the rankings menu directly
 * 
 * This plugin adds student rankings to the main menu system.
 * Students can view their section rankings and other sections under the same teacher.
 */

(() => {
    'use strict';
    
    const pluginName = 'StudentRankingsMenu';
    const parameters = PluginManager.parameters(pluginName);
    
    const serverUrl = parameters['serverUrl'] || 'https://filibustero-web.com/php/';
    const menuText = parameters['menuText'] || 'Section Progress';
    const maxRankingsDisplay = Number(parameters['maxRankingsDisplay'] || 8);
    const refreshInterval = Number(parameters['refreshInterval'] || 30);
    
    let rankingsData = null;
    let refreshTimer = null;
    let currentUserInfo = null;
    
    //-----------------------------------------------------------------------------
    // Database Communication Functions
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
                    console.error('Rankings - Error parsing response:', e);
                    console.log('Raw response:', xhr.responseText);
                    callback({ success: false, error: 'Invalid server response' });
                }
            }
        };
        
        xhr.onerror = function() {
            console.error('Rankings - Network error occurred');
            callback({ success: false, error: 'Network error' });
        };
        
        const params = new URLSearchParams();
        for (const key in data) {
            params.append(key, data[key]);
        }
        
        xhr.send(params.toString());
    }
    
   function fetchRankingsData(userId, callback) {
    console.log('User ID being sent:', userId, 'Type:', typeof userId);
        if (!userId) {
            callback({ success: false, error: 'No user ID provided' });
            return;
        }
        
        console.log('Fetching rankings for user:', userId);
        const url = serverUrl + 'rankings.php';
        
        makeRequest(url, {
            action: 'get_rankings',
            user_id: userId
        }, function(response) {
            console.log('Rankings response received:', response);
            
            if (response.success) {
                // Store the rankings data directly from PHP response
                rankingsData = response.rankings;
                currentUserInfo = response.current_user;
                console.log('Response success:', response.success);
                console.log('My section data:', response.rankings ? response.rankings.mySection : 'No rankings');
                console.log('Other sections data:', response.rankings ? response.rankings.otherSections : 'No rankings');
                
                callback(response);
            } else {
                console.error('Rankings fetch failed:', response.error);
                callback(response);
            }
        });
    }
    
    //-----------------------------------------------------------------------------
    // Add Rankings to Main Menu
    //-----------------------------------------------------------------------------
    
    const _Window_MenuCommand_makeCommandList = Window_MenuCommand.prototype.makeCommandList;
    Window_MenuCommand.prototype.makeCommandList = function() {
        _Window_MenuCommand_makeCommandList.call(this);
        this.addRankingsCommand();
    };
    
    Window_MenuCommand.prototype.addRankingsCommand = function() {
        const enabled = this.areMainCommandsEnabled();
        this.addCommand(menuText, 'rankings', enabled);
    };
    
    const _Scene_Menu_createCommandWindow = Scene_Menu.prototype.createCommandWindow;
    Scene_Menu.prototype.createCommandWindow = function() {
        _Scene_Menu_createCommandWindow.call(this);
        this._commandWindow.setHandler('rankings', this.commandRankings.bind(this));
    };
    
    Scene_Menu.prototype.commandRankings = function() {
        SceneManager.push(Scene_Rankings);
    };
    
    //-----------------------------------------------------------------------------
    // Rankings Scene
    //-----------------------------------------------------------------------------
    
    class Scene_Rankings extends Scene_MenuBase {
        create() {
            super.create();
            this.createHelpWindow();
            this.createCategoryWindow();
            this.createRankingsWindow();
            this.refreshRankingsData();
        }
        
        createHelpWindow() {
            const rect = this.helpWindowRect();
            this._helpWindow = new Window_Help(rect);
            this._helpWindow.setText('View student rankings by section');
            this.addWindow(this._helpWindow);
        }
        
        createCategoryWindow() {
            const rect = this.categoryWindowRect();
            this._categoryWindow = new Window_RankingsCategory(rect);
            this._categoryWindow.setHandler('mySection', this.onCategoryOk.bind(this));
            this._categoryWindow.setHandler('otherSections', this.onCategoryOk.bind(this));
            this._categoryWindow.setHandler('cancel', this.popScene.bind(this));
            this.addWindow(this._categoryWindow);
        }
        
        createRankingsWindow() {
            const rect = this.rankingsWindowRect();
            this._rankingsWindow = new Window_RankingsList(rect);
            this._rankingsWindow.setHandler('cancel', this.onRankingsCancel.bind(this));
            this.addWindow(this._rankingsWindow);
            
            this._categoryWindow.setRankingsWindow(this._rankingsWindow);
        }
        
        helpWindowRect() {
            return new Rectangle(0, 0, Graphics.boxWidth, this.calcWindowHeight(1, false));
        }
        
        categoryWindowRect() {
            const wy = this.mainAreaTop();
            const wh = this.calcWindowHeight(2, true);
            return new Rectangle(0, wy, Graphics.boxWidth * 0.4, wh);
        }
        
        rankingsWindowRect() {
            const wx = Graphics.boxWidth * 0.4;
            const wy = this.mainAreaTop();
            const ww = Graphics.boxWidth * 0.6;
            const wh = this.mainAreaHeight();
            return new Rectangle(wx, wy, ww, wh);
        }
        
        onCategoryOk() {
            this._rankingsWindow.activate();
            this._rankingsWindow.select(0);
        }
        
        onRankingsCancel() {
            this._categoryWindow.activate();
            this._rankingsWindow.deselect();
        }
        
        refreshRankingsData() {
            const userId = window.getCurrentUserId ? window.getCurrentUserId() : null;
            
            if (!userId) {
                this._helpWindow.setText('Not connected to server - Please log in');
                console.log('No user ID found');
                return;
            }
            
            console.log('Refreshing rankings for user:', userId);
            this._helpWindow.setText('Loading rankings data...');
            
            fetchRankingsData(userId, (response) => {
                if (response.success) {
                    console.log('Rankings data loaded successfully');
                    this._categoryWindow.refresh();
                    this._rankingsWindow.refresh();
                    this._helpWindow.setText('Student Rankings - Use arrow keys to navigate');
                    
                    // Update section info in category window
                    if (currentUserInfo) {
                        const sectionText = `My Section (${currentUserInfo.section})`;
                        const otherText = `Other Sections (Teacher: ${currentUserInfo.teacher_id})`;
                        this._categoryWindow.updateCommandText(0, sectionText);
                        this._categoryWindow.updateCommandText(1, otherText);
                        console.log('Updated category text:', sectionText, otherText);
                    }
                } else {
                    this._helpWindow.setText('Failed to load rankings: ' + (response.error || 'Unknown error'));
                    console.error('Failed to fetch rankings:', response.error);
                }
            });
        }
        
        update() {
            super.update();
            if (Input.isTriggered('cancel')) {
                this.popScene();
            }
        }
    }
    
    //-----------------------------------------------------------------------------
    // Rankings Category Window
    //-----------------------------------------------------------------------------
    
    class Window_RankingsCategory extends Window_Command {
        constructor(rect) {
            super(rect);
            this._rankingsWindow = null;
            this.activate();
            this.select(0);
        }
        
        makeCommandList() {
            // Default text - will be updated once we have user data
            this.addCommand('My Section', 'mySection', true);
            this.addCommand('Other Sections', 'otherSections', true);
        }
        
        setRankingsWindow(rankingsWindow) {
            this._rankingsWindow = rankingsWindow;
        }
        
        updateCommandText(index, text) {
            if (this._list && this._list[index]) {
                this._list[index].name = text;
                this.refresh();
            }
        }
        
        update() {
            super.update();
            if (this._rankingsWindow) {
                const symbol = this.currentSymbol();
                this._rankingsWindow.setCategory(symbol);
            }
        }
    }
    
    //-----------------------------------------------------------------------------
    // Rankings List Window
    //-----------------------------------------------------------------------------
    
    class Window_RankingsList extends Window_Selectable {
        constructor(rect) {
            super(rect);
            this._category = 'mySection';
            this._data = [];
            this.refresh();
            this.deactivate();
            this.deselect();
        }
        
        setCategory(category) {
            if (this._category !== category) {
                this._category = category;
                this.refresh();
                this.scrollTo(0, 0);
            }
        }
        
        maxItems() {
            return this._data ? this._data.length : 0;
        }
        
        item() {
            return this._data && this.index() >= 0 ? this._data[this.index()] : null;
        }
        
        makeItemList() {
            this._data = [];
            if (rankingsData) {
                if (this._category === 'mySection' && rankingsData.mySection) {
                    this._data = rankingsData.mySection.slice(0, maxRankingsDisplay);
                    console.log('My section data loaded:', this._data.length, 'items');
                } else if (this._category === 'otherSections' && rankingsData.otherSections) {
                    this._data = rankingsData.otherSections.slice(0, maxRankingsDisplay);
                    console.log('Other sections data loaded:', this._data.length, 'items');
                }
            } else {
                console.log('No rankings data available');
            }
        }
        
        refresh() {
            this.makeItemList();
            super.refresh();
        }
        
        drawItem(index) {
            const student = this._data[index];
            if (!student) return;
            
            const rect = this.itemRect(index);
            const rank = index + 1;
            
            // Background for current user
            if (student.is_current_user) {
                this.contents.fillRect(rect.x, rect.y, rect.width, rect.height, 'rgba(0,255,0,0.1)');
            }
            
            // Rank
            this.changeTextColor(this.getRankColor(rank));
            this.contents.fontSize = 20;
            this.drawText(rank + '.', rect.x + 5, rect.y, 30);
            
            // Name
            this.changeTextColor(student.is_current_user ? 
                ColorManager.textColor(3) : ColorManager.normalColor());
            const nameWidth = 100; // Reduced from 120 to make room
            const displayName = student.player_name && student.player_name.length > 10 ? 
                student.player_name.substring(0, 10) + "..." : student.player_name || "Unknown";
            this.drawText(displayName, rect.x + 35, rect.y, nameWidth);
            
            // Section (show for other sections) or Progress (for same section)
          
                // Progress percentage for same section
                this.changeTextColor(ColorManager.textColor(14));
                this.drawText(student.progress_percentage + '%', rect.x + 140, rect.y, 85);

            
            // Score - adjusted position to accommodate larger section display
            this.changeTextColor(ColorManager.normalColor());
            this.drawText('S:' + (student.score || 0), rect.x + 230, rect.y, 70); // Adjusted position
            
            // Coins
            this.changeTextColor(ColorManager.textColor(6));
            this.drawText('C:' + (student.coins || 0), rect.x + 305, rect.y, 60);
            
            this.contents.fontSize = 28; // Reset font size
        }
        
        getRankColor(rank) {
            switch(rank) {
                case 1: return ColorManager.textColor(14); // Gold
                case 2: return ColorManager.textColor(8);  // Silver  
                case 3: return ColorManager.textColor(18); // Bronze
                default: return ColorManager.normalColor();
            }
        }
        
        drawAllItems() {
            const topIndex = this.topIndex();
            for (let i = 0; i < this.maxVisibleItems(); i++) {
                const index = topIndex + i;
                if (index < this.maxItems()) {
                    this.drawItem(index);
                }
            }
        }
        
        itemHeight() {
            return 36;
        }
    }
    
    //-----------------------------------------------------------------------------
    // Plugin Commands
    //-----------------------------------------------------------------------------

    PluginManager.registerCommand(pluginName, "openRankings", args => {
    // Add the authentication check here
    if (!window.getCurrentUserId || !window.getCurrentUserId()) {
        console.log('User not logged in - Cannot open rankings');
        // You might want to show a message to the player
        SceneManager._scene.addChild(new Window_Help(new Rectangle(100, 100, 400, 100)));
        SceneManager._scene._helpWindow.setText("Please log in first to view rankings");
        return;
    }
    SceneManager.push(Scene_Rankings);
});
    
    //-----------------------------------------------------------------------------
    // Auto Refresh System
    //-----------------------------------------------------------------------------
    
    function startAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        
        if (refreshInterval > 0) {
            refreshTimer = setInterval(() => {
                // Only refresh if we're in the rankings scene
                if (SceneManager._scene instanceof Scene_Rankings) {
                    console.log('Auto-refreshing rankings data');
                    SceneManager._scene.refreshRankingsData();
                }
            }, refreshInterval * 1000);
        }
    }
    
    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }
    
    // Start auto refresh when plugin loads
    startAutoRefresh();
    
    //-----------------------------------------------------------------------------
    // User Session Integration
    //-----------------------------------------------------------------------------
    
    const originalSetCurrentUserId = window.setCurrentUserId;
    if (originalSetCurrentUserId) {
        window.setCurrentUserId = function(userId) {
            originalSetCurrentUserId.call(this, userId);
            // Clear cached data when user changes
            rankingsData = null;
            currentUserInfo = null;
        };
    }
    
    const originalClearCurrentUserId = window.clearCurrentUserId;
    if (originalClearCurrentUserId) {
        window.clearCurrentUserId = function() {
            originalClearCurrentUserId.call(this);
            rankingsData = null;
            currentUserInfo = null;
            stopAutoRefresh();
        };
    }
    
    //-----------------------------------------------------------------------------
    // Global Interface
    //-----------------------------------------------------------------------------
    
    window.StudentRankings = {
        openMenu: () => SceneManager.push(Scene_Rankings),
        refreshData: () => {
            if (SceneManager._scene instanceof Scene_Rankings) {
                SceneManager._scene.refreshRankingsData();
            }
        }
    };
    window.setCurrentUserIdNumber = function(idNumber) {
        window.currentUserIdNumber = idNumber;
        console.log('Set current user ID number:', idNumber);
    };

    window.getCurrentUserIdNumber = function() {
        return window.currentUserIdNumber;
    };

    Scene_Rankings.prototype.refreshRankingsData = function() {
    let userId = null;
    
    // Priority 1: Use id_number from global function (for rankings)
    if (window.getCurrentUserIdNumber && window.getCurrentUserIdNumber()) {
        userId = window.getCurrentUserIdNumber();
        console.log('Using ID Number from global function:', userId);
    }
    // Priority 2: Use id_number from game variables
    else if ($gameVariables && $gameVariables.value(18)) {
        userId = $gameVariables.value(18);
        console.log('Using ID Number from game variables:', userId);
    }
    // Priority 3: Fallback to numeric ID (this might not work with leaderboard)
    else if (window.getCurrentUserId && window.getCurrentUserId()) {
        userId = window.getCurrentUserId();
        console.log('Fallback: Using numeric user ID:', userId);
    }
    // Priority 4: Fallback to numeric ID from game variables
    else if ($gameVariables && $gameVariables.value(10)) {
        userId = $gameVariables.value(10);
        console.log('Fallback: Using numeric user ID from game variables:', userId);
    }
    
    if (!userId) {
        this._helpWindow.setText('Not connected to server - Please log in');
        console.log('No user ID found');
        return;
    }
    
    console.log('=== RANKINGS REFRESH DEBUG ===');
    console.log('Final userId for rankings:', userId);
    console.log('userId type:', typeof userId);
    console.log('==============================');
    
    this._helpWindow.setText('Loading rankings data...');
    
    fetchRankingsData(userId, (response) => {
        if (response.success) {
            console.log('Rankings data loaded successfully');
            this._categoryWindow.refresh();
            this._rankingsWindow.refresh();
            this._helpWindow.setText('Student Rankings - Use arrow keys to navigate');
            
            // Update section info in category window
            if (currentUserInfo) {
                const sectionText = `My Section (${currentUserInfo.section})`;
                const otherText = `Other Sections (Teacher: ${currentUserInfo.teacher_id})`;
                this._categoryWindow.updateCommandText(0, sectionText);
                this._categoryWindow.updateCommandText(1, otherText);
                console.log('Updated category text:', sectionText, otherText);
            }
        } else {
            this._helpWindow.setText('Failed to load rankings: ' + (response.error || 'Unknown error'));
            console.error('Failed to fetch rankings:', response.error);
            
            // Add debug info to help message if debug data is available
            if (response.debug) {
                console.error('Debug info:', response.debug);
            }
        }
    });
};

// Update plugin command to use ID number
PluginManager.registerCommand(pluginName, "openRankings", args => {
    // Try to get ID number first, fallback to numeric ID
    let userId = null;
    
    if (window.getCurrentUserIdNumber && window.getCurrentUserIdNumber()) {
        userId = window.getCurrentUserIdNumber();
    } else if ($gameVariables && $gameVariables.value(18)) {
        userId = $gameVariables.value(18);
    } else if (window.getCurrentUserId && window.getCurrentUserId()) {
        userId = window.getCurrentUserId();
    } else if ($gameVariables && $gameVariables.value(10)) {
        userId = $gameVariables.value(10);
    }
    
    if (!userId) {
        console.log('User not logged in - Cannot open rankings');
        $gameMessage.add('\\C[2]Please log in first to view rankings\\C[0]');
        return;
    }
    
    console.log('Opening rankings for user:', userId);
    SceneManager.push(Scene_Rankings);
});


})();