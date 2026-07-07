//=============================================================================
// Minimap Plugin - Enhanced Version
// Version: 2.0.0
//=============================================================================

/*:
 * @target MZ
 * @plugindesc Displays a square minimap with character portraits (Enhanced for indoor/water maps)
 * @author Your Name
 * 
 * @param Minimap Width
 * @desc Width of the minimap in pixels
 * @type number
 * @default 180
 * 
 * @param Minimap Height
 * @desc Height of the minimap in pixels
 * @type number
 * @default 180
 * 
 * @param Minimap X Offset
 * @desc X position offset from the right edge
 * @type number
 * @default 10
 * 
 * @param Minimap Y Offset
 * @desc Y position offset from the top
 * @type number
 * @default 10
 * 
 * @param Minimized Width
 * @desc Width of minimized minimap in pixels
 * @type number
 * @default 50
 * 
 * @param Minimized Height
 * @desc Height of minimized minimap in pixels
 * @type number
 * @default 50
 * 
 * @param Map Opacity
 * @desc Opacity of the minimap (0-255)
 * @type number
 * @default 230
 * 
 * @param Player Portrait Size
 * @desc Size of the player portrait in pixels
 * @type number
 * @default 20
 * 
 * @param NPC Portrait Size
 * @desc Size of the NPC portrait in pixels
 * @type number
 * @default 14
 * 
 * @param Zoom Level
 * @desc How many tiles to show from center (higher = more area visible)
 * @type number
 * @default 8
 * 
 * @param Tile Scale
 * @desc Scale of tiles on minimap (smaller = more area visible)
 * @type number
 * @decimals 1
 * @default 3.0
 * 
 * @param Max Tileset Load Attempts
 * @desc Maximum attempts to load tilesets before showing simplified map
 * @type number
 * @default 60
 * 
 * @param Use Fallback Colors
 * @desc Use simplified colors if tilesets fail to load
 * @type boolean
 * @default true
 * 
 * @param Minimized Opacity
 * @desc Opacity when minimized (0-255)
 * @type number
 * @default 180
 * 
 * @help
 * ============================================================================
 * Minimap Plugin - Enhanced Edition
 * ============================================================================
 * 
 * This enhanced version includes:
 * - Improved tileset loading for indoor/complex maps
 * - Better water tile detection and rendering
 * - Automatic fallback to color-based minimap if tilesets don't load
 * - Support for all 68+ maps
 * - Optimized layer rendering
 * - Minimize/Maximize functionality (click on the minimap to toggle)
 * 
 * The minimap automatically works on all maps.
 * 
 * ============================================================================
 */

(() => {
    const pluginName = "MinimapPlugin";
    const parameters = PluginManager.parameters(pluginName);
    
    const minimapWidth = Number(parameters['Minimap Width'] || 180);
    const minimapHeight = Number(parameters['Minimap Height'] || 180);
    const minimapXOffset = Number(parameters['Minimap X Offset'] || 10);
    const minimapYOffset = Number(parameters['Minimap Y Offset'] || 10);
    const minimizedWidth = Number(parameters['Minimized Width'] || 50);
    const minimizedHeight = Number(parameters['Minimized Height'] || 50);
    const mapOpacity = Number(parameters['Map Opacity'] || 230);
    const playerPortraitSize = Number(parameters['Player Portrait Size'] || 20);
    const npcPortraitSize = Number(parameters['NPC Portrait Size'] || 14);
    const zoomLevel = Number(parameters['Zoom Level'] || 8);
    const tileScale = Number(parameters['Tile Scale'] || 3.0);
    const maxTilesetLoadAttempts = Number(parameters['Max Tileset Load Attempts'] || 60);
    const useFallbackColors = String(parameters['Use Fallback Colors'] !== 'false');
    const minimizedOpacity = Number(parameters['Minimized Opacity'] || 180);

    //-----------------------------------------------------------------------------
    // Sprite_Minimap
    //-----------------------------------------------------------------------------

    class Sprite_Minimap extends Sprite {
        initialize() {
            super.initialize();
            this._mapBitmap = null;
            this._lastMapId = 0;
            this._tilesetReady = false;
            this._refreshCounter = 0;
            this._loadAttempts = 0;
            this._useFallbackMap = false;
            this._fallbackColorMap = null;
            this._isMinimized = false; // Start with normal size
            this._minimizedBitmap = null;
            this._lastMinimizedRefresh = 0;
            this._clickCooldown = 0;
            this.createBitmap();
            this.createBorder();
            this.updateOpacity();
        }

        update() {
            super.update();
            
            // Handle click cooldown
            if (this._clickCooldown > 0) {
                this._clickCooldown--;
            }
            
            // Check for click/touch input
            if (this.isClickTriggered()) {
                this.onClick();
            }
            
            if ($gameMap && $gamePlayer) {
                if (this._lastMapId !== $gameMap.mapId()) {
                    this._lastMapId = $gameMap.mapId();
                    this._mapBitmap = null;
                    this._fallbackColorMap = null;
                    this._tilesetReady = false;
                    this._useFallbackMap = false;
                    this._refreshCounter = 0;
                    this._loadAttempts = 0;
                    this._minimizedBitmap = null;
                }
                
                if (!this._tilesetReady && !this._useFallbackMap) {
                    this._refreshCounter++;
                    if (this._refreshCounter % 5 === 0) {
                        this._loadAttempts++;
                        this.generateMapBitmap();
                        
                        // Use fallback after max attempts
                        if (this._loadAttempts >= maxTilesetLoadAttempts && useFallbackColors) {
                            this._useFallbackMap = true;
                            this.generateFallbackMap();
                        }
                    }
                }
                
                this.refresh();
            }
        }

        isClickTriggered() {
            // Check for mouse click or touch
            if (this._clickCooldown > 0) {
                return false;
            }
            
            if (TouchInput.isTriggered() || Input.isTriggered('ok')) {
                const x = this.canvasToLocalX(TouchInput.x);
                const y = this.canvasToLocalY(TouchInput.y);
                
                // Check if click is within minimap bounds
                if (x >= 0 && x < this.width && y >= 0 && y < this.height) {
                    this._clickCooldown = 10; // 10 frame cooldown to prevent double-clicks
                    return true;
                }
            }
            return false;
        }

        canvasToLocalX(x) {
            return x - this.x;
        }

        canvasToLocalY(y) {
            return y - this.y;
        }

        onClick() {
            this._isMinimized = !this._isMinimized;
            this.resizeMinimap();
            this.updateOpacity();
            if (this._border) {
                this._border.visible = !this._isMinimized;
            }
        }

        resizeMinimap() {
            if (this._isMinimized) {
                // Create new bitmap for minimized state
                const oldBitmap = this.bitmap;
                this.bitmap = new Bitmap(minimizedWidth, minimizedHeight);
                this.x = Graphics.boxWidth - minimizedWidth - minimapXOffset;
                this.y = minimapYOffset;
                
                if (this._border) {
                    this._border.bitmap = new Bitmap(minimizedWidth + 4, minimizedHeight + 4);
                    const context = this._border.bitmap.context;
                    context.save();
                    context.strokeStyle = '#000000';
                    context.lineWidth = 4;
                    context.strokeRect(2, 2, minimizedWidth, minimizedHeight);
                    context.restore();
                    this._border.bitmap._baseTexture.update();
                    this._border.x = this.x - 2;
                    this._border.y = this.y - 2;
                    this._border.visible = false; // Hide border when minimized
                }
            } else {
                // Restore to original size
                this.bitmap = new Bitmap(minimapWidth, minimapHeight);
                this.x = Graphics.boxWidth - minimapWidth - minimapXOffset;
                this.y = 50 + minimapYOffset;
                
                if (this._border) {
                    this._border.bitmap = new Bitmap(minimapWidth + 4, minimapHeight + 4);
                    const context = this._border.bitmap.context;
                    context.save();
                    context.strokeStyle = '#000000';
                    context.lineWidth = 4;
                    context.strokeRect(2, 2, minimapWidth, minimapHeight);
                    context.restore();
                    this._border.bitmap._baseTexture.update();
                    this._border.x = this.x - 2;
                    this._border.y = this.y - 2;
                    this._border.visible = true; // Show border when normal size
                }
            }
        }

        updateOpacity() {
            this.opacity = this._isMinimized ? minimizedOpacity : mapOpacity;
        }

        createBitmap() {
            // Always start with normal size
            this.bitmap = new Bitmap(minimapWidth, minimapHeight);
            this.x = Graphics.boxWidth - minimapWidth - minimapXOffset;
            this.y = 50 + minimapYOffset;
        }

        createBorder() {
            this._border = new Sprite();
            const width = minimapWidth + 4;
            const height = minimapHeight + 4;
            this._border.bitmap = new Bitmap(width, height);
            const context = this._border.bitmap.context;
            
            context.save();
            context.strokeStyle = '#000000';
            context.lineWidth = 4;
            context.strokeRect(2, 2, minimapWidth, minimapHeight);
            context.restore();
            this._border.bitmap._baseTexture.update();
            
            this._border.x = this.x - 2;
            this._border.y = this.y - 2;
        }

        generateMapBitmap() {
            const tileset = $gameMap.tileset();
            if (!tileset) return;
            
            let allTilesetsReady = true;
            for (let i = 0; i < tileset.tilesetNames.length; i++) {
                if (tileset.tilesetNames[i]) {
                    const bitmap = ImageManager.loadTileset(tileset.tilesetNames[i]);
                    if (!bitmap || !bitmap.isReady()) {
                        allTilesetsReady = false;
                        break;
                    }
                }
            }
            
            if (!allTilesetsReady) return;
            
            const mapWidth = $gameMap.width();
            const mapHeight = $gameMap.height();
            const scaledTileSize = Math.floor(48 / tileScale);
            
            this._mapBitmap = new Bitmap(mapWidth * scaledTileSize, mapHeight * scaledTileSize);
            this._tilesetReady = true;
            
            // Render all layers
            for (let z = 0; z < 6; z++) {
                for (let x = 0; x < mapWidth; x++) {
                    for (let y = 0; y < mapHeight; y++) {
                        this.drawTileAt(x, y, z, scaledTileSize);
                    }
                }
            }
        }

        drawTileAt(x, y, z, scaledTileSize) {
            const tileId = $gameMap.tileId(x, y, z);
            if (tileId > 0) {
                this.drawSingleTile(tileId, x * scaledTileSize, y * scaledTileSize, scaledTileSize);
            }
        }

        drawSingleTile(tileId, dx, dy, scaledTileSize) {
            const tileset = $gameMap.tileset();
            if (!tileset) return;
            
            let bitmapName = '';
            
            if (tileId < Tilemap.TILE_ID_A1) {
                return;
            } else if (tileId < Tilemap.TILE_ID_A2) {
                bitmapName = tileset.tilesetNames[0];
            } else if (tileId < Tilemap.TILE_ID_A3) {
                bitmapName = tileset.tilesetNames[1];
            } else if (tileId < Tilemap.TILE_ID_A4) {
                bitmapName = tileset.tilesetNames[2];
            } else if (tileId < Tilemap.TILE_ID_A5) {
                bitmapName = tileset.tilesetNames[3];
            } else if (tileId < Tilemap.TILE_ID_B) {
                bitmapName = tileset.tilesetNames[4];
            } else if (tileId < Tilemap.TILE_ID_C) {
                bitmapName = tileset.tilesetNames[5];
            } else if (tileId < Tilemap.TILE_ID_D) {
                bitmapName = tileset.tilesetNames[6];
            } else if (tileId < Tilemap.TILE_ID_E) {
                bitmapName = tileset.tilesetNames[7];
            } else {
                bitmapName = tileset.tilesetNames[8];
            }
            
            if (!bitmapName) return;
            
            const bitmap = ImageManager.loadTileset(bitmapName);
            if (!bitmap || !bitmap.isReady()) return;
            
            let sx, sy;
            
            if (tileId < Tilemap.TILE_ID_B) {
                const autotileType = Math.floor((tileId - Tilemap.TILE_ID_A1) / 48);
                const autotileShape = (tileId - Tilemap.TILE_ID_A1) % 48;
                sx = (autotileShape % 8) * 48;
                sy = Math.floor(autotileShape / 8) * 48 + (autotileType * 6 * 48);
            } else {
                const localId = tileId % 256;
                sx = (localId % 8) * 48;
                sy = Math.floor(localId / 8) * 48;
            }
            
            this._mapBitmap.blt(bitmap, sx, sy, 48, 48, dx, dy, scaledTileSize, scaledTileSize);
        }

        generateFallbackMap() {
            const mapWidth = $gameMap.width();
            const mapHeight = $gameMap.height();
            const scaledTileSize = Math.floor(48 / tileScale);
            
            this._fallbackColorMap = new Bitmap(mapWidth * scaledTileSize, mapHeight * scaledTileSize);
            
            for (let x = 0; x < mapWidth; x++) {
                for (let y = 0; y < mapHeight; y++) {
                    const color = this.getTileColor(x, y);
                    const rectX = x * scaledTileSize;
                    const rectY = y * scaledTileSize;
                    this._fallbackColorMap.fillRect(rectX, rectY, scaledTileSize, scaledTileSize, color);
                }
            }
        }

        getTileColor(x, y) {
            // Check all layers for tiles
            for (let z = 0; z < 6; z++) {
                const tileId = $gameMap.tileId(x, y, z);
                if (tileId > 0) {
                    // Water tiles (A2 autotiles 0-5)
                    if (tileId >= Tilemap.TILE_ID_A2 && tileId < Tilemap.TILE_ID_A3) {
                        return '#3366CC';
                    }
                    // Grass/ground (A1 autotiles)
                    if (tileId >= Tilemap.TILE_ID_A1 && tileId < Tilemap.TILE_ID_A2) {
                        return '#66AA44';
                    }
                    // Walls/obstacles (B tiles)
                    if (tileId >= Tilemap.TILE_ID_B && tileId < Tilemap.TILE_ID_C) {
                        return '#555555';
                    }
                    // Shadows/darkness (C tiles)
                    if (tileId >= Tilemap.TILE_ID_C && tileId < Tilemap.TILE_ID_D) {
                        return '#333333';
                    }
                    // Upper tiles (D-E)
                    if (tileId >= Tilemap.TILE_ID_D) {
                        return '#888888';
                    }
                    // Default for A3-A5
                    return '#AA8844';
                }
            }
            // Empty tile
            return '#1a1a1a';
        }

        refresh() {
            if (this._isMinimized) {
                this.refreshMinimized();
            } else {
                this.refreshNormal();
            }
        }

        refreshNormal() {
            this.bitmap.clear();
            
            if (this._tilesetReady && this._mapBitmap) {
                this.drawMapSection();
            } else if (this._useFallbackMap && this._fallbackColorMap) {
                this.drawFallbackMapSection();
            } else {
                this.bitmap.fillRect(0, 0, minimapWidth, minimapHeight, 'rgba(50, 50, 50, 0.8)');
                this.bitmap.drawText('Loading...', 0, minimapHeight / 2 - 12, minimapWidth, 24, 'center');
            }
            
            this.drawNPCs();
            this.drawPlayer();
        }

        refreshMinimized() {
            // Only refresh minimized view occasionally to save performance
            if (Graphics.frameCount - this._lastMinimizedRefresh < 30) {
                return;
            }
            this._lastMinimizedRefresh = Graphics.frameCount;
            
            this.bitmap.clear();
            
            // Draw a simplified version of the map
            this.bitmap.fillRect(0, 0, minimizedWidth, minimizedHeight, 'rgba(50, 50, 50, 0.8)');
            
            // Draw player indicator in center
            const centerX = minimizedWidth / 2;
            const centerY = minimizedHeight / 2;
            const indicatorSize = 6;
            
            // Draw a small compass-like indicator
            this.bitmap.fillRect(centerX - indicatorSize/2, centerY - indicatorSize/2, 
                               indicatorSize, indicatorSize, '#FF6666');
            
            // Draw direction indicator based on player direction
            const direction = $gamePlayer.direction();
            let dirX = 0, dirY = 0;
            switch (direction) {
                case 2: dirY = 1; break; // Down
                case 4: dirX = -1; break; // Left
                case 6: dirX = 1; break; // Right
                case 8: dirY = -1; break; // Up
            }
            
            if (dirX !== 0 || dirY !== 0) {
                this.bitmap.fillRect(centerX + dirX * 8 - 2, centerY + dirY * 8 - 2, 
                                   4, 4, '#FFFF66');
            }
            
            // Draw a simple border using fillRect for each side
            const borderColor = '#FFFFFF';
            const borderWidth = 1;
            
            // Top border
            this.bitmap.fillRect(0, 0, minimizedWidth, borderWidth, borderColor);
            // Bottom border
            this.bitmap.fillRect(0, minimizedHeight - borderWidth, minimizedWidth, borderWidth, borderColor);
            // Left border
            this.bitmap.fillRect(0, 0, borderWidth, minimizedHeight, borderColor);
            // Right border
            this.bitmap.fillRect(minimizedWidth - borderWidth, 0, borderWidth, minimizedHeight, borderColor);
            
            // Add a "+" sign to indicate it can be expanded
            this.bitmap.drawText('+', 0, 0, minimizedWidth, minimizedHeight, 'center');
        }

        drawMapSection() {
            if (!this._mapBitmap) return;
            
            const mapWidth = $gameMap.width();
            const mapHeight = $gameMap.height();
            const scaledTileSize = Math.floor(48 / tileScale);
            
            const centerX = $gamePlayer.x;
            const centerY = $gamePlayer.y;
            
            const startX = Math.max(0, centerX - zoomLevel);
            const startY = Math.max(0, centerY - zoomLevel);
            const endX = Math.min(mapWidth, centerX + zoomLevel);
            const endY = Math.min(mapHeight, centerY + zoomLevel);
            
            const sourceWidth = (endX - startX) * scaledTileSize;
            const sourceHeight = (endY - startY) * scaledTileSize;
            
            this.bitmap.blt(
                this._mapBitmap,
                startX * scaledTileSize,
                startY * scaledTileSize,
                sourceWidth,
                sourceHeight,
                0,
                0,
                minimapWidth,
                minimapHeight
            );
        }

        drawFallbackMapSection() {
            if (!this._fallbackColorMap) return;
            
            const mapWidth = $gameMap.width();
            const mapHeight = $gameMap.height();
            const scaledTileSize = Math.floor(48 / tileScale);
            
            const centerX = $gamePlayer.x;
            const centerY = $gamePlayer.y;
            
            const startX = Math.max(0, centerX - zoomLevel);
            const startY = Math.max(0, centerY - zoomLevel);
            const endX = Math.min(mapWidth, centerX + zoomLevel);
            const endY = Math.min(mapHeight, centerY + zoomLevel);
            
            const sourceWidth = (endX - startX) * scaledTileSize;
            const sourceHeight = (endY - startY) * scaledTileSize;
            
            this.bitmap.blt(
                this._fallbackColorMap,
                startX * scaledTileSize,
                startY * scaledTileSize,
                sourceWidth,
                sourceHeight,
                0,
                0,
                minimapWidth,
                minimapHeight
            );
        }

        drawPlayer() {
            const x = minimapWidth / 2;
            const y = minimapHeight / 2;
            
            const characterName = $gamePlayer.characterName();
            const characterIndex = $gamePlayer.characterIndex();
            
            this.drawCharacterPortrait(characterName, characterIndex, x, y, playerPortraitSize, [255, 255, 255]);
        }

        drawNPCs() {
            if (!this._tilesetReady && !this._useFallbackMap) return;
            
            const centerX = $gamePlayer.x;
            const centerY = $gamePlayer.y;
            const mapWidth = $gameMap.width();
            const mapHeight = $gameMap.height();
            
            const startX = Math.max(0, centerX - zoomLevel);
            const startY = Math.max(0, centerY - zoomLevel);
            const endX = Math.min(mapWidth, centerX + zoomLevel);
            const endY = Math.min(mapHeight, centerY + zoomLevel);
            
            $gameMap.events().forEach(event => {
                if (event && event.x >= startX && event.x < endX && 
                    event.y >= startY && event.y < endY) {
                    
                    const relX = ((event.x - startX) / (endX - startX)) * minimapWidth;
                    const relY = ((event.y - startY) / (endY - startY)) * minimapHeight;
                    
                    const characterName = event.characterName();
                    const characterIndex = event.characterIndex();
                    
                    this.drawCharacterPortrait(characterName, characterIndex, relX, relY, npcPortraitSize, [255, 255, 100]);
                }
            });
        }

        drawCharacterPortrait(characterName, characterIndex, x, y, size, tint) {
            if (!characterName) return;
            
            const bitmap = ImageManager.loadCharacter(characterName);
            if (!bitmap || !bitmap.isReady()) {
                const color = tint[2] === 255 ? '#FF6666' : '#FFFF66';
                this.drawCircle(x, y, size / 2, color);
                return;
            }
            
            const big = ImageManager.isBigCharacter(characterName);
            const pw = bitmap.width / (big ? 3 : 12);
            const ph = bitmap.height / (big ? 4 : 8);
            const n = characterIndex;
            const sx = ((n % 4) * 3 + 1) * pw;
            const sy = (Math.floor(n / 4) * 4) * ph;
            
            const tempBitmap = new Bitmap(size, size);
            tempBitmap.blt(bitmap, sx, sy, pw, ph, 0, 0, size, size);
            
            if (tint[0] !== 255 || tint[1] !== 255 || tint[2] !== 255) {
                const context = tempBitmap.context;
                context.save();
                context.globalCompositeOperation = 'multiply';
                context.fillStyle = `rgb(${tint[0]}, ${tint[1]}, ${tint[2]})`;
                context.fillRect(0, 0, size, size);
                context.globalCompositeOperation = 'destination-in';
                context.drawImage(tempBitmap.canvas, 0, 0);
                context.restore();
                tempBitmap._baseTexture.update();
            }
            
            const context = this.bitmap.context;
            context.drawImage(tempBitmap.canvas, x - size / 2, y - size / 2, size, size);
            this.bitmap._baseTexture.update();
        }

        drawCircle(x, y, radius, color) {
            const context = this.bitmap.context;
            context.save();
            context.fillStyle = color;
            context.beginPath();
            context.arc(x, y, radius, 0, Math.PI * 2);
            context.fill();
            context.restore();
            this.bitmap._baseTexture.update();
        }
    }

    //-----------------------------------------------------------------------------
    // Scene_Map
    //-----------------------------------------------------------------------------

    const _Scene_Map_createDisplayObjects = Scene_Map.prototype.createDisplayObjects;
    Scene_Map.prototype.createDisplayObjects = function() {
        _Scene_Map_createDisplayObjects.call(this);
        this.createMinimap();
    };

    Scene_Map.prototype.createMinimap = function() {
        this._minimapSprite = new Sprite_Minimap();
        this.addChild(this._minimapSprite);
        if (this._minimapSprite._border) {
            this.addChild(this._minimapSprite._border);
        }
    };

})();