<div class="channelengine-container">
    <div class="channelengine-header">
        <div class="channelengine-logo"></div>
    </div>

    <div class="channelengine-sync-section">
        <div class="sync-status-container">
            <div class="sync-status-row">
                <span class="sync-status-label">Sync Status:</span>
                <span id="sync-status-value" class="status-done">Done</span>
            </div>
        </div>

        <button class="sync-button" onclick="ChannelEngine.handleSync()">Synchronize</button>

        <div class="sync-progress" style="display: none;"></div>
        <div class="sync-error-message" style="display: none;"></div>
    </div>
</div>