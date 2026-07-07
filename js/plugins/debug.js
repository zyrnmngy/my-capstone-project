// =============================================
// COMPREHENSIVE DEBUG SOLUTION
// Add this to your game to debug the progress sync issue
// =============================================

// Step 1: Check if all systems are loaded
function debugSystemStatus() {
    console.log("=== SYSTEM STATUS DEBUG ===");
    console.log("1. FilibusteroAuth exists:", !!window.FilibusteroAuth);
    console.log("2. Current User:", window.FilibusteroAuth?.currentUser);
    console.log("3. ProgressTracker exists:", !!window.ProgressTracker);
    console.log("4. ProgressTracker initialized:", window.ProgressTracker?.isInitialized);
    console.log("5. FilibusteroProgress exists:", !!window.FilibusteroProgress);
    console.log("=============================");
}

// Step 2: Check variable values
function debugVariableValues() {
    console.log("=== VARIABLE VALUES DEBUG ===");
    console.log("Variable 8 (old score):", $gameVariables.value(8));
    console.log("Variable 14 (coins):", $gameVariables.value(14));
    console.log("Variable 15 (might be score):", $gameVariables.value(15));
    console.log("Variable 16 (stage):", $gameVariables.value(16));
    console.log("Variable 17 (quests):", $gameVariables.value(17));
    console.log("Variable 18 (map changes):", $gameVariables.value(18));
    
    if (window.FilibusteroProgress) {
        console.log("Plugin score:", window.FilibusteroProgress.getScore());
        console.log("Plugin coins:", window.FilibusteroProgress.getCoins());
        console.log("Plugin stage:", window.FilibusteroProgress.getCurrentStage());
        console.log("Plugin progress:", window.FilibusteroProgress.getProgress());
    }
    console.log("===============================");
}

// Step 3: Test database connection
async function testDatabaseConnection() {
    console.log("=== TESTING DATABASE CONNECTION ===");
    
    const testData = {
        action: 'save_progress',
        user_id: 1, // Test user ID
        player_name: 'Test Player',
        score: 999,
        coin_count: 888,
        current_stage: 7,
        completed_quests: 6,
        map_changes: 5
    };
    
    try {
        const formData = new FormData();
        Object.keys(testData).forEach(key => {
            formData.append(key, testData[key]);
        });
        
        const response = await fetch('https://filibustero-web.com/php/save_progress.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        console.log("Database test result:", result);
        
        if (result.success) {
            console.log("✅ Database connection working!");
        } else {
            console.log("❌ Database error:", result.error);
        }
    } catch (error) {
        console.log("❌ Connection failed:", error);
    }
    console.log("=====================================");
}

// Step 4: Force initialize Progress Tracker
function forceInitializeTracker() {
    console.log("=== FORCE INITIALIZING TRACKER ===");
    
    // Create a fake user if none exists
    if (!window.FilibusteroAuth || !window.FilibusteroAuth.currentUser) {
        console.log("Creating test user...");
        window.FilibusteroAuth = {
            currentUser: {
                id: 1,
                full_name: "Test Player"
            }
        };
    }
    
    // Try to initialize
    if (window.ProgressTracker) {
        console.log("Initializing ProgressTracker...");
        const result = window.ProgressTracker.initialize();
        console.log("Initialization result:", result);
        
        // Check status after initialization
        setTimeout(() => {
            console.log("Status after init:", window.ProgressTracker.getSyncStatus());
        }, 1000);
    } else {
        console.log("❌ ProgressTracker not found!");
    }
    console.log("===================================");
}

// Step 5: Manually trigger a save
function manualProgressSave() {
    console.log("=== MANUAL PROGRESS SAVE ===");
    
    if (window.ProgressTracker && window.ProgressTracker.isInitialized) {
        console.log("Triggering manual save...");
        window.ProgressTracker.manualSave();
    } else {
        console.log("❌ ProgressTracker not initialized!");
        console.log("Trying direct save...");
        
        // Try direct save
        const progressData = {
            action: 'save_progress',
            user_id: 1,
            player_name: 'Manual Test',
            score: $gameVariables.value(8) || $gameVariables.value(15) || 0,
            coin_count: $gameVariables.value(14) || 0,
            current_stage: $gameVariables.value(16) || 0,
            completed_quests: $gameVariables.value(17) || 0,
            map_changes: $gameVariables.value(18) || 0,
            playtime_seconds: $gameSystem ? $gameSystem.playtimeSeconds() : 0
        };
        
        console.log("Sending data:", progressData);
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'https://filibustero-web.com/php/save_progress.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log("Response status:", xhr.status);
                console.log("Response text:", xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        const result = JSON.parse(xhr.responseText);
                        console.log("Save result:", result);
                    } catch (e) {
                        console.log("Could not parse response as JSON");
                    }
                }
            }
        };
        
        const params = Object.keys(progressData)
            .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(progressData[key])}`)
            .join('&');
        
        xhr.send(params);
    }
    console.log("=============================");
}

// Step 6: Check if save hooks are working
function testSaveHooks() {
    console.log("=== TESTING SAVE HOOKS ===");
    
    // Override save functions temporarily to see if they're being called
    const originalExecuteSave = Scene_Save.prototype.executeSave;
    Scene_Save.prototype.executeSave = function() {
        console.log("🎯 Save button was clicked!");
        return originalExecuteSave.call(this);
    };
    
    const originalMakeSaveContents = DataManager.makeSaveContents;
    DataManager.makeSaveContents = function() {
        console.log("🎯 Save contents being created!");
        return originalMakeSaveContents.call(this);
    };
    
    console.log("Save hooks installed. Try saving your game now.");
    console.log("===========================");
}

// Step 7: Run complete diagnostic
async function runCompleteDiagnostic() {
    console.log("🔍 RUNNING COMPLETE DIAGNOSTIC...");
    console.log("==================================");
    
    debugSystemStatus();
    await new Promise(r => setTimeout(r, 1000));
    
    debugVariableValues();
    await new Promise(r => setTimeout(r, 1000));
    
    await testDatabaseConnection();
    await new Promise(r => setTimeout(r, 1000));
    
    forceInitializeTracker();
    await new Promise(r => setTimeout(r, 2000));
    
    testSaveHooks();
    await new Promise(r => setTimeout(r, 1000));
    
    console.log("🔍 DIAGNOSTIC COMPLETE!");
    console.log("Now try: manualProgressSave()");
}

// Make functions available globally
window.debugProgress = {
    ...window.debugProgress, // Keep existing debug functions
    systemStatus: debugSystemStatus,
    variableValues: debugVariableValues,
    testDB: testDatabaseConnection,
    forceInit: forceInitializeTracker,
    manualSave: manualProgressSave,
    testHooks: testSaveHooks,
    fullDiagnostic: runCompleteDiagnostic
};

// Auto-run basic diagnostic when this script loads
console.log("🎮 Progress Debug System Loaded!");
console.log("Available commands:");
console.log("- debugProgress.fullDiagnostic() - Run complete diagnostic");
console.log("- debugProgress.systemStatus() - Check if systems are loaded");  
console.log("- debugProgress.variableValues() - Check variable values");
console.log("- debugProgress.testDB() - Test database connection");
console.log("- debugProgress.forceInit() - Force initialize tracker");
console.log("- debugProgress.manualSave() - Force save progress");
console.log("- debugProgress.testHooks() - Test if save hooks work");