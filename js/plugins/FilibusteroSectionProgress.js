//=============================================================================
// Filibustero Section Progress Menu System
// Version: 2.0.0
// Author: Your Name
//=============================================================================

/*:
 * @target MZ
 * @plugindesc [v2.0.0] Filibustero Section Progress Menu System
 * @author YourName
 * @url 
 * @help FilibusteroSectionProgressMenu.js
 * 
 * @param apiEndpoint
 * @text API Endpoint
 * @desc Base URL for your API endpoint
 * @type string
 * @default https://filibustero-web.com/php/get_section_progress.php
 * 
 * @param currentUserVariable
 * @text Current User ID Variable
 * @desc Variable ID that stores the current user's ID
 * @type variable
 * @default 20
 * 
 * @param refreshInterval
 * @text Refresh Interval (seconds)
 * @desc How often to refresh section data from database
 * @type number
 * @default 30
 * 
 * @param menuText
 * @text Menu Command Text
 * @desc Text to display in the main menu
 * @type string
 * @default Section Progress
 * 
 * @param enableInGame
 * @text Enable In-Game Access
 * @desc Allow access to section progress from in-game menu
 * @type boolean
 * @default true
 * 
 * @param menuKey
 * @text Menu Hotkey
 * @desc Key to open section progress menu directly (optional)
 * @type string
 * @default 
 * 
 * This plugin creates a section progress menu system for the Filibustero game.
 * It integrates into RPG Maker MZ's menu system with a slide-out interface.
 * 
 * Features:
 * - Proper menu integration (accessible from main menu and in-game)
 * - Two-panel sliding interface (Your Section vs Other Sections)
 * - Real-time data refresh
 * - Rankings and detailed comparisons
 * 
 * Required Setup:
 * 1. Set up your API endpoints for fetching section data
 * 2. Configure the current user ID variable
 * 3. Ensure your database has the proper tables
 */

(() => {
    'use strict';
    
    const pluginName = 'FilibusteroSectionProgressMenu';
    const parameters = PluginManager.parameters(pluginName);
    
    const apiEndpoint = parameters['apiEndpoint'] || 'https://filibustero-web.com/php/';
    const currentUserVariable = Number(parameters['currentUserVariable'] || 10);
    const refreshInterval = Number(parameters['refreshInterval'] || 30) * 1000;
    const menuText = parameters['menuText'] || 'Section Progress';
    const enableInGame = parameters['enableInGame'] === 'true';
    const menuKey = parameters['menuKey'] || '';
    
    let sectionData = {
        currentSection: null,
        otherSections: [],
        lastUpdated: 0
    };

    //-----------------------------------------------------------------------------
    // Section Progress Scene
    //-----------------------------------------------------------------------------
    
    class Scene_SectionProgress extends Scene_MenuBase {
        constructor() {
            super();
            this._currentPanel = 0; // 0 = Your Section, 1 = Other Sections
            this._slideAnimation = false;
            this._slideDirection = 0;
        }
        
        create() {
            super.create();
            this.createBackground();
            this.createWindowLayer();
            this.createAllWindows();
            this.refreshSectionData();
        }
        
        createBackground() {
            this._backgroundFilter = new PIXI.filters.BlurFilter();
            this._backgroundSprite = new Sprite();
            this._backgroundSprite.bitmap = SceneManager.backgroundBitmap();
            this._backgroundSprite.filters = [this._backgroundFilter];
            this.addChild(this._backgroundSprite);
            this.setBackgroundOpacity(192);
        }
        
        createAllWindows() {
            this.createHelpWindow();
            this.createCommandWindow();
            this.createYourSectionWindow();
            this.createOtherSectionsWindow();
            this.createStatusWindow();
        }
        
        createHelpWindow() {
            const rect = this.helpWindowRect();
            this._helpWindow = new Window_Help(rect);
            this.addWindow(this._helpWindow);
        }
        
        createCommandWindow() {
            const rect = this.commandWindowRect();
            this._commandWindow = new Window_SectionCommand(rect);
            this._commandWindow.setHandler('yourSection', this.commandYourSection.bind(this));
            this._commandWindow.setHandler('otherSections', this.commandOtherSections.bind(this));
            this._commandWindow.setHandler('refresh', this.commandRefresh.bind(this));
            this._commandWindow.setHandler('cancel', this.popScene.bind(this));
            this.addWindow(this._commandWindow);
            
            // Connect help window
            this._commandWindow._helpWindow = this._helpWindow;
        }
        
        createYourSectionWindow() {
            const rect = this.mainWindowRect();
            this._yourSectionWindow = new Window_YourSection(rect);
            this.addWindow(this._yourSectionWindow);
        }
        
        createOtherSectionsWindow() {
            const rect = this.mainWindowRect();
            this._otherSectionsWindow = new Window_OtherSections(rect);
            this._otherSectionsWindow.x = Graphics.boxWidth; // Start off-screen
            this.addWindow(this._otherSectionsWindow);
        }
        
        createStatusWindow() {
            const rect = this.statusWindowRect();
            this._statusWindow = new Window_SectionStatus(rect);
            this.addWindow(this._statusWindow);
        }
        
        helpWindowRect() {
            const wx = 0;
            const wy = 0;
            const ww = Graphics.boxWidth;
            const wh = this.calcWindowHeight(2, false);
            return new Rectangle(wx, wy, ww, wh);
        }
        
        commandWindowRect() {
            const wx = 0;
            const wy = this._helpWindow.y + this._helpWindow.height;
            const ww = Graphics.boxWidth / 3;
            const wh = this.calcWindowHeight(4, true);
            return new Rectangle(wx, wy, ww, wh);
        }
        
        mainWindowRect() {
            const wx = this._commandWindow.width;
            const wy = this._helpWindow.y + this._helpWindow.height;
            const ww = Graphics.boxWidth - this._commandWindow.width;
            const wh = Graphics.boxHeight - wy - this.calcWindowHeight(2, false);
            return new Rectangle(wx, wy, ww, wh);
        }
        
        statusWindowRect() {
            const wx = this._commandWindow.width;
            const wy = Graphics.boxHeight - this.calcWindowHeight(2, false);
            const ww = Graphics.boxWidth - this._commandWindow.width;
            const wh = this.calcWindowHeight(2, false);
            return new Rectangle(wx, wy, ww, wh);
        }
        
        commandYourSection() {
            if (this._currentPanel !== 0) {
                this.slideToPanel(0);
            }
            this._commandWindow.activate();
        }
        
        commandOtherSections() {
            if (this._currentPanel !== 1) {
                this.slideToPanel(1);
            }
            this._commandWindow.activate();
        }
        
        commandRefresh() {
            this.refreshSectionData();
            this._commandWindow.activate();
        }
        
        slideToPanel(panelIndex) {
            if (this._slideAnimation) return;
            
            this._slideAnimation = true;
            this._slideDirection = panelIndex === 0 ? -1 : 1;
            this._currentPanel = panelIndex;
            
            const targetX1 = panelIndex === 0 ? this._commandWindow.width : -Graphics.boxWidth;
            const targetX2 = panelIndex === 1 ? this._commandWindow.width : Graphics.boxWidth;
            
            this.slideWindow(this._yourSectionWindow, targetX1);
            this.slideWindow(this._otherSectionsWindow, targetX2);
        }
        
        slideWindow(window, targetX) {
            const startX = window.x;
            const distance = targetX - startX;
            const duration = 20; // frames
            let frame = 0;
            
            const animate = () => {
                frame++;
                const progress = frame / duration;
                const easeProgress = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                
                window.x = startX + (distance * easeProgress);
                
                if (frame < duration) {
                    requestAnimationFrame(animate);
                } else {
                    window.x = targetX;
                    this._slideAnimation = false;
                }
            };
            
            animate();
        }
        
        update() {
            super.update();
            this.updateInput();
        }
        
        updateInput() {
            if (!this._slideAnimation) {
                if (Input.isTriggered('left') && this._currentPanel === 1) {
                    this.commandYourSection();
                } else if (Input.isTriggered('right') && this._currentPanel === 0) {
                    this.commandOtherSections();
                }
            }
        }
        
        async refreshSectionData() {
            try {
                const currentUserId = $gameVariables.value(currentUserVariable);
                console.log('Current user variable ID:', currentUserVariable);
                console.log('Current user ID from variable:', currentUserId);
                console.log('All relevant variables:', {
                    var10: $gameVariables.value(10),
                    var11: $gameVariables.value(11)
                });
                
                if (!currentUserId) {
                    this._statusWindow.showMessage('Current user ID not set');
                    return;
                }
                        
                this._statusWindow.showMessage('Refreshing data...');
                const response = await this.fetchSectionData(currentUserId);
                
                if (response) {
                    sectionData = response;
                    sectionData.lastUpdated = Date.now();
                    
                    this._yourSectionWindow.refresh();
                    this._otherSectionsWindow.refresh();
                    this._statusWindow.refresh();
                    this._statusWindow.showMessage('Data updated successfully', 2000);
                }
            } catch (error) {
                console.error('Error refreshing section data:', error);
                this._statusWindow.showMessage('Failed to refresh data');
            }
        }
        
        async fetchSectionData(userId) {
            try {
                const response = await fetch(`${apiEndpoint}get_section_progress.php?user_id=${userId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Check if the API returned an error
                if (data.error) {
                    throw new Error(data.error);
                }
                
                return data;
                
            } catch (error) {
                console.error('Failed to fetch section data:', error);
                this._statusWindow.showMessage('Failed to fetch data: ' + error.message);
                return null;
            }
        }
        
    }

    //-----------------------------------------------------------------------------
    // Command Window
    //-----------------------------------------------------------------------------
    
    class Window_SectionCommand extends Window_Command {
        makeCommandList() {
            this.addCommand("Your Section", 'yourSection', true);
            this.addCommand("Other Sections", 'otherSections', true);
            this.addCommand("Refresh Data", 'refresh', true);
            this.addCommand("Close", 'cancel', true);
        }
        
        updateHelp() {
            const symbol = this.currentSymbol();
            let helpText = "";
            switch (symbol) {
                case 'yourSection':
                    helpText = "View your section's progress and statistics";
                    break;
                case 'otherSections':
                    helpText = "Compare with other sections and view rankings";
                    break;
                case 'refresh':
                    helpText = "Refresh data from the database";
                    break;
                case 'cancel':
                    helpText = "Return to the previous menu";
                    break;
                default:
                    helpText = "";
            }
            if (this._helpWindow) {
                this._helpWindow.setText(helpText);
            }
        }
    }

    //-----------------------------------------------------------------------------
    // Your Section Window
    //-----------------------------------------------------------------------------
    
    class Window_YourSection extends Window_Base {
        constructor(rect) {
            super(rect);
            this.refresh();
        }
        
        refresh() {
            this.contents.clear();
            this.drawYourSectionInfo();
        }
        
        drawYourSectionInfo() {
            if (!sectionData.currentSection) {
                this.changeTextColor(ColorManager.deathColor());
                this.drawText("No section data available", 0, 0, this.contentsWidth(), 'center');
                return;
            }
            
            const section = sectionData.currentSection;
            let y = 0;
            const lineHeight = this.lineHeight();
            
            // Section Header
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("YOUR SECTION", 0, y, this.contentsWidth(), 'center');
            y += lineHeight * 1.5;
            
            // Section Name
            this.changeTextColor(ColorManager.powerUpColor());
            this.drawText(section.section, 20, y, this.contentsWidth() - 40, 'center');
            y += lineHeight;
            
            // Teacher
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(`Teacher: ${section.teacher}`, 20, y, this.contentsWidth() - 40, 'center');
            y += lineHeight * 2;
            
            // Progress Section
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("OVERALL PROGRESS", 20, y, this.contentsWidth() - 40);
            y += lineHeight;
            
            // Progress Bar
            const progressBarWidth = this.contentsWidth() - 100;
            this.drawProgressBar(20, y, progressBarWidth, 24, section.avgProgress, ColorManager.hpGaugeColor1());
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(`${section.avgProgress.toFixed(1)}%`, this.contentsWidth() - 70, y, 60, 'right');
            y += lineHeight * 2;
            
            // Statistics Grid
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("SECTION STATISTICS", 20, y, this.contentsWidth() - 40);
            y += lineHeight;
            
            const colWidth = (this.contentsWidth() - 40) / 2;
            
            // Left Column
            this.changeTextColor(ColorManager.normalColor());
            this.drawText(`Average Score: ${section.avgScore.toFixed(1)}`, 20, y, colWidth);
            this.drawText(`Average Stage: ${section.avgStage.toFixed(1)}`, 20, y + lineHeight, colWidth);
            
            // Right Column
            this.drawText(`Total Students: ${section.studentCount}`, 20 + colWidth, y, colWidth);
            this.drawText(`Completed: ${section.completedCount}`, 20 + colWidth, y + lineHeight, colWidth);
            y += lineHeight * 3;
            
            // Performance Indicators
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("PERFORMANCE INDICATORS", 20, y, this.contentsWidth() - 40);
            y += lineHeight;
            
            // Completion Rate
            const completionRate = (section.completedCount / section.studentCount) * 100;
            this.changeTextColor(ColorManager.normalColor());
            this.drawText("Completion Rate:", 20, y, colWidth);
            this.changeTextColor(this.getPerformanceColor(completionRate));
            this.drawText(`${completionRate.toFixed(1)}%`, 20 + colWidth, y, colWidth);
            y += lineHeight;
            
            // Progress Rating
            this.changeTextColor(ColorManager.normalColor());
            this.drawText("Progress Rating:", 20, y, colWidth);
            this.changeTextColor(this.getPerformanceColor(section.avgProgress));
            this.drawText(this.getProgressRating(section.avgProgress), 20 + colWidth, y, colWidth);
        }
        
        drawProgressBar(x, y, width, height, percentage, color) {
            // Background
            this.contents.fillRect(x, y, width, height, ColorManager.gaugeBackColor());
            
            // Progress fill
            const fillWidth = Math.floor((width * percentage) / 100);
            this.contents.fillRect(x, y, fillWidth, height, color);
            
            // Border
            this.contents.strokeRect(x, y, width, height, ColorManager.outlineColor());
        }
        
        getPerformanceColor(percentage) {
            if (percentage >= 80) return ColorManager.powerUpColor();
            if (percentage >= 60) return ColorManager.normalColor();
            if (percentage >= 40) return ColorManager.textColor(14);
            return ColorManager.deathColor();
        }
        
        getProgressRating(percentage) {
            if (percentage >= 90) return "Excellent";
            if (percentage >= 80) return "Very Good";
            if (percentage >= 70) return "Good";
            if (percentage >= 60) return "Satisfactory";
            if (percentage >= 50) return "Needs Improvement";
            return "Poor";
        }
    }

    //-----------------------------------------------------------------------------
    // Other Sections Window
    //-----------------------------------------------------------------------------
    
    class Window_OtherSections extends Window_Selectable {
        constructor(rect) {
            super(rect);
            this.refresh();
        }
        
        maxItems() {
            return sectionData.otherSections ? sectionData.otherSections.length : 0;
        }
        
        itemHeight() {
            return this.lineHeight() * 3;
        }
        
        refresh() {
            this.contents.clear();
            this.drawAllItems();
        }
        
        drawAllItems() {
            if (!sectionData.otherSections || sectionData.otherSections.length === 0) {
                this.changeTextColor(ColorManager.deathColor());
                this.drawText("No other sections found", 0, 0, this.contentsWidth(), 'center');
                return;
            }
            
            // Header
            this.changeTextColor(ColorManager.systemColor());
            this.drawText("OTHER SECTIONS RANKING", 0, 0, this.contentsWidth(), 'center');
            
            // Sort sections by progress
            const sortedSections = [...sectionData.otherSections].sort((a, b) => b.avgProgress - a.avgProgress);
            
            for (let i = 0; i < sortedSections.length; i++) {
                this.drawItem(i, sortedSections[i]);
            }
        }
        
        drawItem(index, section) {
            const rect = this.itemRect(index);
            rect.y += this.lineHeight() * 1.5; // Account for header
            
            const rank = index + 1;
            const rankColor = this.getRankColor(index);
            
            // Rank badge
            this.changeTextColor(rankColor);
            this.drawText(`#${rank}`, rect.x, rect.y, 40);
            
            // Section name
            this.changeTextColor(ColorManager.normalColor());
            const sectionName = section.section.length > 25 ? 
                section.section.substring(0, 22) + "..." : section.section;
            this.drawText(sectionName, rect.x + 45, rect.y, rect.width - 120);
            
            // Progress percentage
            this.changeTextColor(this.getPerformanceColor(section.avgProgress));
            this.drawText(`${section.avgProgress.toFixed(1)}%`, rect.x + rect.width - 70, rect.y, 60, 'right');
            
            // Progress bar
            const barY = rect.y + this.lineHeight();
            const barWidth = rect.width - 50;
            this.drawProgressBar(rect.x + 45, barY, barWidth, 12, section.avgProgress, this.getRankProgressColor(index));
            
            // Additional stats
            const statsY = rect.y + this.lineHeight() * 2;
            this.changeTextColor(ColorManager.dimColor1());
            this.drawText(`Avg Score: ${section.avgScore.toFixed(1)}`, rect.x + 45, statsY, (rect.width - 50) / 2);
            this.drawText(`Students: ${section.studentCount}`, rect.x + 45 + (rect.width - 50) / 2, statsY, (rect.width - 50) / 2);
        }
        
        itemRect(index) {
            const rect = super.itemRect(index);
            rect.height = this.itemHeight();
            return rect;
        }
        
        drawProgressBar(x, y, width, height, percentage, color) {
            // Background
            this.contents.fillRect(x, y, width, height, ColorManager.gaugeBackColor());
            
            // Progress fill
            const fillWidth = Math.floor((width * percentage) / 100);
            this.contents.fillRect(x, y, fillWidth, height, color);
            
            // Border
            this.contents.strokeRect(x, y, width, height, ColorManager.outlineColor());
        }
        
        getRankColor(rank) {
            switch (rank) {
                case 0: return ColorManager.textColor(14); // Gold
                case 1: return ColorManager.textColor(3);  // Blue
                case 2: return ColorManager.textColor(2);  // Red
                default: return ColorManager.normalColor();
            }
        }
        
        getRankProgressColor(rank) {
            switch (rank) {
                case 0: return ColorManager.textColor(14); // Gold
                case 1: return ColorManager.textColor(23); // Light blue
                case 2: return ColorManager.textColor(18); // Light red
                default: return ColorManager.hpGaugeColor2();
            }
        }
        
        getPerformanceColor(percentage) {
            if (percentage >= 80) return ColorManager.powerUpColor();
            if (percentage >= 60) return ColorManager.normalColor();
            if (percentage >= 40) return ColorManager.textColor(14);
            return ColorManager.deathColor();
        }
    }

    //-----------------------------------------------------------------------------
    // Status Window
    //-----------------------------------------------------------------------------
    
    class Window_SectionStatus extends Window_Base {
        constructor(rect) {
            super(rect);
            this._message = "";
            this._messageTimer = 0;
            this.refresh();
        }
        
        update() {
            super.update();
            if (this._messageTimer > 0) {
                this._messageTimer--;
                if (this._messageTimer === 0) {
                    this.refresh();
                }
            }
        }
        
        refresh() {
            this.contents.clear();
            
            if (this._message && this._messageTimer > 0) {
                this.changeTextColor(ColorManager.systemColor());
                this.drawText(this._message, 10, 0, this.contentsWidth() - 20);
            } else {
                // Show last updated info
                if (sectionData.lastUpdated > 0) {
                    const timeDiff = Math.floor((Date.now() - sectionData.lastUpdated) / 1000);
                    this.changeTextColor(ColorManager.dimColor1());
                    this.drawText(`Last updated: ${timeDiff}s ago`, 10, 0, this.contentsWidth() - 20);
                    
                    // Show navigation hint
                    this.drawText("Use ← → or command menu to switch panels", 10, this.lineHeight(), this.contentsWidth() - 20);
                } else {
                    this.changeTextColor(ColorManager.normalColor());
                    this.drawText("Ready to load section data", 10, 0, this.contentsWidth() - 20);
                }
            }
        }
        
        showMessage(message, duration = 3000) {
            this._message = message;
            this._messageTimer = Math.floor(duration / 16.67); // Convert ms to frames (60fps)
            this.refresh();
        }
    }

    //-----------------------------------------------------------------------------
    // Menu Integration
    //-----------------------------------------------------------------------------
    
    // Add to main menu
    const _Window_MenuCommand_makeCommandList = Window_MenuCommand.prototype.makeCommandList;
    Window_MenuCommand.prototype.makeCommandList = function() {
        _Window_MenuCommand_makeCommandList.call(this);
        this.addCommand(menuText, "sectionProgress", true);
    };
    
    const _Scene_Menu_createCommandWindow = Scene_Menu.prototype.createCommandWindow;
    Scene_Menu.prototype.createCommandWindow = function() {
        _Scene_Menu_createCommandWindow.call(this);
        this._commandWindow.setHandler("sectionProgress", this.commandSectionProgress.bind(this));
    };
    
    Scene_Menu.prototype.commandSectionProgress = function() {
        SceneManager.push(Scene_SectionProgress);
    };
    
    // Add to in-game menu (if enabled)
    if (enableInGame) {
        const _Scene_Map_createMenuButton = Scene_Map.prototype.createMenuButton;
        Scene_Map.prototype.createMenuButton = function() {
            _Scene_Map_createMenuButton.call(this);
            // Additional setup for in-game access can be added here
        };
        
        // Hotkey support
        if (menuKey) {
            const _Scene_Map_update = Scene_Map.prototype.update;
            Scene_Map.prototype.update = function() {
                _Scene_Map_update.call(this);
                if (Input.isTriggered(menuKey) && !$gameMap.isEventRunning()) {
                    SceneManager.push(Scene_SectionProgress);
                }
            };
        }
    }

    //-----------------------------------------------------------------------------
    // Plugin Commands
    //-----------------------------------------------------------------------------
    
    PluginManager.registerCommand(pluginName, "openSectionProgress", args => {
        if (!$gameParty.inBattle()) {
            SceneManager.push(Scene_SectionProgress);
        }
    });
    
    PluginManager.registerCommand(pluginName, "setCurrentUser", args => {
        const userId = Number(args.userId);
        $gameVariables.setValue(currentUserVariable, userId);
    });

    // Global access
    window.FilibusteroSectionProgress = {
        openMenu: function() {
            if (!$gameParty.inBattle()) {
                SceneManager.push(Scene_SectionProgress);
            }
        },
        
        setCurrentUser: function(userId) {
            $gameVariables.setValue(currentUserVariable, userId);
        },
        
        getSectionData: function() {
            return sectionData;
        }
    };

})();