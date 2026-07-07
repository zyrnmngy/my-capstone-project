/*:
 * @target MZ
 * @plugindesc Cloud Save System v1.6.4 - Cross-device cache fix
 * @author Your Name
 * @url https://filibustero-web.com/filibustero/php/auth.php
 *
 * @param apiUrl
 * @text API URL
 * @desc URL to your auth.php endpoint
 * @default https://filibustero-web.com/filibustero/php/auth.php
 *
 * @param enableDebugLog
 * @text Enable Debug Logging
 * @desc Show detailed logs in console
 * @type boolean
 * @default true
 *
 * @help
 * ============================================================================
 * Cloud Save System v1.6.4 - Cross-Device Cache Fix
 * ============================================================================
 * 
 * FIXED: Cache now works properly across different devices
 * - Persistent cache storage in localStorage
 * - User-specific cache keys
 * - Automatic cache invalidation on user change
 * 
 * IMPORTANT: This plugin must be loaded LAST in your plugin list!
 * 
 * ============================================================================
 */

(() => {
    const pluginName = "CloudSaveSystem";
    const parameters = PluginManager.parameters(pluginName);
    const apiUrl = String(parameters['apiUrl'] || 'https://filibustero-web.com/php/auth.php');
    const enableDebugLog = String(parameters['enableDebugLog'] || 'true') === 'true';

    function debugLog(...args) {
        if (enableDebugLog) {
            console.log('[CloudSave]', ...args);
        }
    }

    // Enhanced cache system with localStorage persistence
    window.CloudSaveCache = {
        _currentUserId: null,
        
        // Generate cache key with user ID to ensure isolation
        _getCacheKey: function(key) {
            const userId = getUserId();
            return `cloudsave_${userId}_${key}`;
        },
        
        // Get from cache with expiration check (1 minute cache)
        get: function(key) {
            const cacheKey = this._getCacheKey(key);
            try {
                const cached = localStorage.getItem(cacheKey);
                if (cached) {
                    const parsed = JSON.parse(cached);
                    // Check if cache is still valid (1 minute)
                    if (Date.now() - parsed.timestamp < 60000) {
                        debugLog('Cache HIT for:', key);
                        return parsed.data;
                    } else {
                        debugLog('Cache EXPIRED for:', key);
                        localStorage.removeItem(cacheKey);
                    }
                }
            } catch (e) {
                debugLog('Cache read error:', e);
            }
            debugLog('Cache MISS for:', key);
            return null;
        },
        
        // Set cache with timestamp
        set: function(key, value) {
            const cacheKey = this._getCacheKey(key);
            try {
                const cacheData = {
                    data: value,
                    timestamp: Date.now(),
                    userId: getUserId()
                };
                localStorage.setItem(cacheKey, JSON.stringify(cacheData));
                debugLog('Cache SET for:', key);
            } catch (e) {
                debugLog('Cache write error:', e);
                // If localStorage is full, clear old caches
                this.clearOldCaches();
            }
        },
        
        // Clear specific cache or all for current user
        clear: function(key) {
            if (key) {
                const cacheKey = this._getCacheKey(key);
                localStorage.removeItem(cacheKey);
                debugLog('Cache CLEARED for:', key);
            } else {
                // Clear all caches for current user
                const userId = getUserId();
                this.clearUserCaches(userId);
            }
        },
        
        // Clear all caches for a specific user
        clearUserCaches: function(userId) {
            const keysToRemove = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(`cloudsave_${userId}_`)) {
                    keysToRemove.push(key);
                }
            }
            keysToRemove.forEach(key => {
                localStorage.removeItem(key);
                debugLog('Removed user cache:', key);
            });
            debugLog(`Cleared ${keysToRemove.length} caches for user:`, userId);
        },
        
        // Clear old caches to free up space
        clearOldCaches: function() {
            const now = Date.now();
            const keysToRemove = [];
            
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('cloudsave_')) {
                    try {
                        const cached = localStorage.getItem(key);
                        const parsed = JSON.parse(cached);
                        // Remove caches older than 1 hour
                        if (now - parsed.timestamp > 3600000) {
                            keysToRemove.push(key);
                        }
                    } catch (e) {
                        // Remove invalid cache entries
                        keysToRemove.push(key);
                    }
                }
            }
            
            keysToRemove.forEach(key => {
                localStorage.removeItem(key);
            });
            
            if (keysToRemove.length > 0) {
                debugLog(`Cleared ${keysToRemove.length} old caches`);
            }
        },
        
        // Check if user changed and clear old user caches if needed
        checkUserChange: function() {
            const currentUserId = getUserId();
            if (this._currentUserId && this._currentUserId !== currentUserId) {
                debugLog('User changed, clearing old user caches');
                this.clearUserCaches(this._currentUserId);
            }
            this._currentUserId = currentUserId;
        }
    };

    function addCacheBuster(url) {
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + '_cb=' + Date.now();
    }

    function getUserId() {
        if (typeof window.gameUserId !== 'undefined' && window.gameUserId) {
            return window.gameUserId;
        }
        if (window.FilibusteroAuth && window.FilibusteroAuth.currentUser && window.FilibusteroAuth.currentUser.id) {
            return window.FilibusteroAuth.currentUser.id;
        }
        if (typeof $gameVariables !== 'undefined' && $gameVariables && $gameVariables.value(10)) {
            return $gameVariables.value(10);
        }
        try {
            const storedId = localStorage.getItem('gameUserId');
            if (storedId) return storedId;
        } catch (e) {}
        return null;
    }

    //=============================================================================
    // Cloud Save Manager
    //=============================================================================

    window.CloudSaveManager = {
        saveToCloud: function(savefileId) {
            const userId = getUserId();
            if (!userId) {
                debugLog('Cannot save to cloud: No user logged in');
                return;
            }

            debugLog('=== CLOUD SAVE START ===');
            debugLog('User ID:', userId);
            debugLog('Save Slot:', savefileId);

            const saveData = DataManager.makeSaveContents();
            const json = JsonEx.stringify(saveData);
            
            const saveInfo = DataManager.makeSavefileInfo();
            const infoJson = JsonEx.stringify(saveInfo);
            
            debugLog('Save data size:', json.length, 'bytes');
            debugLog('Save info:', saveInfo);

            const formData = new FormData();
            formData.append('action', 'save_game');
            formData.append('user_id', userId);
            formData.append('save_slot', savefileId);
            formData.append('save_data', json);
            formData.append('save_info', infoJson);
            formData.append('save_title', saveInfo.title || 'Save ' + savefileId);
            formData.append('playtime', Math.floor($gameSystem.playtime()));
            formData.append('timestamp', Date.now());

            debugLog('Sending to:', apiUrl);

            fetch(apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(response => {
                debugLog('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                debugLog('Response data:', data);
                if (data.success) {
                    debugLog('✓ Cloud save successful for slot', savefileId);
                    // Clear relevant caches
                    window.CloudSaveCache.clear('saves_list_' + userId);
                    window.CloudSaveCache.clear(userId + '_' + savefileId);
                    DataManager._globalInfo[savefileId] = saveInfo;
                } else {
                    console.error('✗ Cloud save failed:', data.error);
                }
            })
            .catch(error => {
                console.error('✗ Cloud save error:', error);
            });
        },

        // Load from cloud - this is the primary load method
        loadFromCloud: async function(savefileId, userId) {
            debugLog('=== CLOUD LOAD START ===');
            debugLog('User ID:', userId);
            debugLog('Save Slot:', savefileId);
        
            // Check cache first
            const cacheKey = userId + '_' + savefileId;
            const cached = window.CloudSaveCache.get(cacheKey);
            if (cached) {
                debugLog('Using cached cloud data');
                return cached;
            }
        
            try {
                const url = `${apiUrl}?action=load_game&user_id=${userId}&save_slot=${savefileId}`;
                const response = await fetch(addCacheBuster(url), {
                    method: 'GET',
                    credentials: 'include',
                    cache: 'no-store'
                });
        
                debugLog('Response status:', response.status);
        
                if (response.ok) {
                    const data = await response.json();
                    
                    if (data.success && data.save_data) {
                        debugLog('✓ Cloud save data retrieved');
                        debugLog('Cloud timestamp:', data.timestamp);
                        const parsedData = JsonEx.parse(data.save_data);
                        debugLog('✓ Data parsed successfully');
                        
                        const result = {
                            data: parsedData,
                            timestamp: data.timestamp,
                            saveInfo: data.save_info ? JsonEx.parse(data.save_info) : null
                        };
                        
                        window.CloudSaveCache.set(cacheKey, result);
                        return result;
                    } else {
                        debugLog('✗ No save_data in response');
                    }
                } else {
                    debugLog('✗ HTTP error response:', response.status);
                }
            } catch (e) {
                debugLog('✗ Error fetching cloud save:', e);
            }
            
            return null;
        },

        // Fetch list of saves - CRITICAL for showing accurate UI
        getCloudSavesList: async function(userId) {
            debugLog('=== FETCHING CLOUD SAVES LIST ===');
            debugLog('User ID:', userId);
        
            // Check cache first
            const cacheKey = 'saves_list_' + userId;
            const cached = window.CloudSaveCache.get(cacheKey);
            if (cached) {
                debugLog('Using cached saves list');
                return cached;
            }
        
            try {
                const url = `${apiUrl}?action=list_saves&user_id=${userId}`;
                const response = await fetch(addCacheBuster(url), {
                    method: 'GET',
                    credentials: 'include',
                    cache: 'no-store'
                });
        
                if (response.ok) {
                    const data = await response.json();
                    debugLog('Response data:', data);
                    
                    if (data.success && data.saves) {
                        debugLog('✓ Cloud saves list retrieved:', data.saves.length, 'saves found');
                        
                        const processedSaves = [];
                        for (const save of data.saves) {
                            try {
                                let saveInfo = null;
                                if (save.save_info) {
                                    saveInfo = JsonEx.parse(save.save_info);
                                } else {
                                    saveInfo = {
                                        title: save.save_title || 'Save ' + save.save_slot,
                                        characters: [],
                                        faces: [],
                                        playtime: this.formatPlaytime(save.playtime),
                                        timestamp: save.timestamp
                                    };
                                }
                                
                                processedSaves.push({
                                    save_slot: save.save_slot,
                                    save_info: saveInfo,
                                    timestamp: save.timestamp
                                });
                                
                                debugLog(`  - Slot ${save.save_slot}: "${saveInfo.title}" (playtime: ${saveInfo.playtime}, timestamp: ${save.timestamp})`);
                            } catch (e) {
                                debugLog(`  ✗ Error parsing save info for slot ${save.save_slot}:`, e);
                            }
                        }
                        
                        window.CloudSaveCache.set(cacheKey, processedSaves);
                        return processedSaves;
                    } else {
                        debugLog('✗ No saves found or error:', data.error);
                    }
                } else {
                    debugLog('✗ HTTP error response:', response.status);
                }
            } catch (e) {
                debugLog('✗ Error fetching saves list:', e);
            }
            
            return [];
        },
                
        formatPlaytime: function(seconds) {
            if (!seconds) return '00:00:00';
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = Math.floor(seconds % 60);
            return String(hours).padStart(2, '0') + ':' + 
                   String(minutes).padStart(2, '0') + ':' + 
                   String(secs).padStart(2, '0');
        }
    };

    //=============================================================================
    // DataManager Integration - CLOUD FIRST APPROACH
    //=============================================================================

    const _DataManager_saveGame = DataManager.saveGame;
    DataManager.saveGame = function(savefileId) {
        debugLog('=== SAVE GAME CALLED ===');
        debugLog('Slot:', savefileId);
        
        const result = _DataManager_saveGame.call(this, savefileId);
        
        result.then(() => {
            debugLog('✓ Local save successful, uploading to cloud...');
            CloudSaveManager.saveToCloud(savefileId);
        }).catch(() => {
            debugLog('✗ Local save failed, skipping cloud save');
        });
        
        return result;
    };

    // CRITICAL: Override loadGame to ALWAYS check cloud first
    const _DataManager_loadGame = DataManager.loadGame;
    DataManager.loadGame = function(savefileId) {
        debugLog('=== LOAD GAME CALLED (Cloud Priority) ===');
        debugLog('Slot:', savefileId);
        
        const userId = getUserId();
        
        if (!userId) {
            debugLog('No user ID, using local load only');
            return _DataManager_loadGame.call(this, savefileId);
        }
        
        // CLOUD FIRST: Always try cloud before local
        debugLog('Attempting cloud load for user:', userId);
        return CloudSaveManager.loadFromCloud(savefileId, userId).then(cloudResult => {
            if (cloudResult && cloudResult.data) {
                debugLog('✓ Cloud data found, using cloud save');
                this.createGameObjects();
                this.extractSaveContents(cloudResult.data);
                this.correctDataErrors();
                debugLog('✓ Cloud load successful');
                return 0;
            } else {
                debugLog('No cloud save found, falling back to local');
                // Try local as fallback
                return _DataManager_loadGame.call(this, savefileId).catch(err => {
                    debugLog('Local load also failed:', err);
                    throw err;
                });
            }
        }).catch(err => {
            debugLog('Cloud load error, trying local fallback:', err);
            return _DataManager_loadGame.call(this, savefileId);
        });
    };

    // CRITICAL FIX: savefileExists should check cloud too
    const _DataManager_savefileExists = DataManager.savefileExists;
    DataManager.savefileExists = function(savefileId) {
        const userId = getUserId();
        
        // Check local first (fast)
        const localExists = _DataManager_savefileExists.call(this, savefileId);
        if (localExists) {
            debugLog(`Slot ${savefileId}: exists locally`);
            return true;
        }
        
        // Check if we have this in global info (from cloud)
        if (this._globalInfo && this._globalInfo[savefileId]) {
            debugLog(`Slot ${savefileId}: exists in cloud (from global info)`);
            return true;
        }
        
        debugLog(`Slot ${savefileId}: does not exist`);
        return false;
    };

    // CRITICAL: Load global info from cloud and use it as source of truth
    const _DataManager_loadGlobalInfo = DataManager.loadGlobalInfo;
    DataManager.loadGlobalInfo = function() {
        debugLog('=== LOADING GLOBAL INFO ===');
        
        // Check for user change and clear appropriate caches
        window.CloudSaveCache.checkUserChange();
        
        // First load local info
        _DataManager_loadGlobalInfo.call(this);
        
        // Then OVERRIDE with cloud info (cloud is authoritative)
        const userId = getUserId();
        if (userId) {
            debugLog('Fetching cloud saves to override local info...');
            return CloudSaveManager.getCloudSavesList(userId).then(cloudSaves => {
                if (!this._globalInfo) {
                    this._globalInfo = [];
                }
                
                // CRITICAL: Clear all slots first to remove old user data
                this._globalInfo = [];
                
                // Then populate with current user's cloud data
                for (const cloudSave of cloudSaves) {
                    const slot = cloudSave.save_slot;
                    const cloudInfo = cloudSave.save_info;
                    
                    // Always use cloud info (it's the source of truth)
                    this._globalInfo[slot] = cloudInfo;
                    debugLog(`Slot ${slot}: Using cloud info (playtime: ${cloudInfo.playtime})`);
                }
                
                debugLog('✓ Global info loaded from cloud');
                debugLog('Final global info:', this._globalInfo);
                return true;
            }).catch(err => {
                debugLog('✗ Error loading cloud saves, using local only:', err);
                return false;
            });
        } else {
            debugLog('No user ID, using local info only');
            return Promise.resolve(true);
        }
    };

    //=============================================================================
    // Scene_File - Always refresh from cloud
    //=============================================================================

    const _Scene_File_start = Scene_File.prototype.start;
    Scene_File.prototype.start = function() {
        _Scene_File_start.call(this);
        
        const userId = getUserId();
        if (userId) {
            debugLog('Scene_File: Refreshing cloud saves on scene start...');
            // Clear cache to force fresh data
            window.CloudSaveCache.clear('saves_list_' + userId);
            
            CloudSaveManager.getCloudSavesList(userId).then(cloudSaves => {
                if (!DataManager._globalInfo) {
                    DataManager._globalInfo = [];
                }
                
                // Clear existing data first to prevent mixing users
                DataManager._globalInfo = [];
                
                // Override all slot info with cloud data
                for (const cloudSave of cloudSaves) {
                    const slot = cloudSave.save_slot;
                    const cloudInfo = cloudSave.save_info;
                    DataManager._globalInfo[slot] = cloudInfo;
                    debugLog(`Scene_File: Slot ${slot} updated (playtime: ${cloudInfo.playtime})`);
                }
                
                if (this._listWindow) {
                    this._listWindow.refresh();
                    debugLog('✓ File list refreshed with cloud data');
                }
            }).catch(err => {
                debugLog('✗ Error refreshing cloud saves:', err);
            });
        }
    };

    //=============================================================================
    // Scene_File - Add periodic refresh while file scene is open
    //=============================================================================

    const _Scene_File_update = Scene_File.prototype.update;
    Scene_File.prototype.update = function() {
        _Scene_File_update.call(this);
        
        // Refresh cloud saves every 3 seconds (180 frames at 60fps)
        if (!this._cloudRefreshCounter) {
            this._cloudRefreshCounter = 0;
        }
        
        this._cloudRefreshCounter++;
        if (this._cloudRefreshCounter >= 180) {
            this._cloudRefreshCounter = 0;
            
            const userId = getUserId();
            if (userId) {
                // Clear cache to get fresh data
                window.CloudSaveCache.clear('saves_list_' + userId);
                
                CloudSaveManager.getCloudSavesList(userId).then(cloudSaves => {
                    if (!DataManager._globalInfo) {
                        DataManager._globalInfo = [];
                    }
                    
                    for (const cloudSave of cloudSaves) {
                        const slot = cloudSave.save_slot;
                        DataManager._globalInfo[slot] = cloudSave.save_info;
                    }
                    
                    if (this._listWindow) {
                        this._listWindow.refresh();
                    }
                }).catch(err => {
                    debugLog('Error in periodic refresh:', err);
                });
            }
        }
    };

    //=============================================================================
    // Window_SavefileList - Cloud indicators
    //=============================================================================

    Window_SavefileList.prototype.drawPlaytime = function(info, x, y, width) {
        // Draw playtime
        if (info) {
            let playtimeText = info.playtime || '00:00:00';
            this.drawText(playtimeText, x, y, width, "right");
        }
        
        // Draw cloud icon if this is a cloud save (to the left of playtime)
        const savefileId = this.indexToSavefileId(this._index);
        const userId = getUserId();
        
        if (userId && DataManager._globalInfo && DataManager._globalInfo[savefileId]) {
            this.changeTextColor(ColorManager.textColor(3)); // Green
            this.drawText("☁", x + width - 150, y, 40, "right");
            this.resetTextColor();
        }
    };

    Window_SavefileList.prototype.drawTimestamp = function(info, x, y, width) {
        // Don't draw timestamp
    };

    //=============================================================================
    // Plugin Commands
    //=============================================================================

    PluginManager.registerCommand(pluginName, "clearSaveCache", args => {
        debugLog('=== CLEARING SAVE CACHE ===');
        const userId = getUserId();
        if (userId) {
            window.CloudSaveCache.clear();
            debugLog('✓ Save cache cleared for user:', userId);
        }
    });

    PluginManager.registerCommand(pluginName, "testCloudSave", args => {
        debugLog('=== TEST CLOUD SAVE ===');
        const userId = getUserId();
        debugLog('Current User ID:', userId);
        if (userId) {
            CloudSaveManager.saveToCloud(1);
        } else {
            console.error('Cannot test: No user logged in');
        }
    });

    PluginManager.registerCommand(pluginName, "debugCache", args => {
        debugLog('=== DEBUG CACHE ===');
        const userId = getUserId();
        debugLog('Current User ID:', userId);
        debugLog('LocalStorage keys:');
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('cloudsave_')) {
                debugLog('  ', key);
            }
        }
    });

    //=============================================================================
    // Initialization
    //=============================================================================

    debugLog('🎮 Cloud Save System v1.6.4 initialized (Cross-Device Cache)');
    debugLog('API URL:', apiUrl);

    window.testCloudSave = function() {
        debugLog('Running cloud save test...');
        CloudSaveManager.saveToCloud(1);
    };

    window.debugCloudSave = function() {
        debugLog('=== CLOUD SAVE DEBUG INFO ===');
        const userId = getUserId();
        debugLog('User ID:', userId);
        debugLog('Global Info:', DataManager._globalInfo);
    };

})();