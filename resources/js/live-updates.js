/**
 * Live Updates System for Calcio
 * Provides real-time updates using intelligent polling
 */

class LiveUpdates {
    constructor() {
        this.lastUpdate = null;
        this.pollingInterval = null;
        this.baseInterval = 30000; // 30 seconds default
        this.fastInterval = 10000;  // 10 seconds for live matches
        this.isActive = true;
        this.retryCount = 0;
        this.maxRetries = 3;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.startPolling();
        this.addVisibilityHandling();
    }

    bindEvents() {
        // Listen for page focus/blur to optimize polling
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pausePolling();
            } else {
                this.resumePolling();
            }
        });

        // Add live indicator
        this.addLiveIndicator();
    }

    addLiveIndicator() {
        // Add a small live indicator to the navigation
        const nav = document.querySelector('nav .flex-shrink-0');
        if (nav) {
            const indicator = document.createElement('div');
            indicator.className = 'ml-2 flex items-center';
            indicator.innerHTML = `
                <div id="live-indicator" class="flex items-center">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-1"></div>
                    <span class="text-xs text-gray-500">En vivo</span>
                </div>
            `;
            nav.appendChild(indicator);
        }
    }

    startPolling() {
        this.updateData();
        this.pollingInterval = setInterval(() => {
            if (this.isActive) {
                this.updateData();
            }
        }, this.getCurrentInterval());
    }

    pausePolling() {
        this.isActive = false;
        this.updateIndicator('paused');
    }

    resumePolling() {
        this.isActive = true;
        this.updateIndicator('active');
        this.updateData(); // Immediate update when returning
    }

    getCurrentInterval() {
        // Use faster polling if there are live matches
        const liveMatches = document.querySelectorAll('[data-status="live"]');
        return liveMatches.length > 0 ? this.fastInterval : this.baseInterval;
    }

    async updateData() {
        try {
            const params = new URLSearchParams();
            if (this.lastUpdate) {
                params.append('last_update', this.lastUpdate);
            }

            const response = await fetch(`/api/live/matches?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            this.processUpdates(data);
            this.lastUpdate = data.timestamp;
            this.retryCount = 0;
            this.updateIndicator('active');

        } catch (error) {
            console.warn('Live update failed:', error);
            this.handleError();
        }
    }

    processUpdates(data) {
        if (data.matches && data.matches.length > 0) {
            data.matches.forEach(match => {
                this.updateMatchDisplay(match);
            });
            
            // Update the polling interval based on current matches
            this.adjustPollingInterval();
        }

        // Update statistics if we're on the statistics page
        if (window.location.pathname.includes('/statistics')) {
            this.updateStatistics();
        }
    }

    updateMatchDisplay(match) {
        // Find match elements by data-match-id
        const matchElements = document.querySelectorAll(`[data-match-id="${match.id}"]`);
        
        matchElements.forEach(element => {
            // Update score
            const homeScore = element.querySelector('.home-score');
            const awayScore = element.querySelector('.away-score');
            
            if (homeScore && match.home_goals !== null) {
                homeScore.textContent = match.home_goals;
            }
            
            if (awayScore && match.away_goals !== null) {
                awayScore.textContent = match.away_goals;
            }

            // Update status
            const statusElement = element.querySelector('.match-status');
            if (statusElement) {
                statusElement.textContent = this.formatStatus(match.status, match.minute);
                statusElement.className = `match-status ${this.getStatusClass(match.status)}`;
            }

            // Update data attributes
            element.setAttribute('data-status', match.status);
            
            // Add visual feedback for updates
            this.highlightUpdate(element);
        });
    }

    formatStatus(status, minute) {
        switch (status) {
            case 'live':
                return minute ? `${minute}'` : 'En vivo';
            case 'finished':
                return 'Finalizado';
            case 'scheduled':
                return 'Programado';
            default:
                return status;
        }
    }

    getStatusClass(status) {
        switch (status) {
            case 'live':
                return 'text-red-600 font-bold animate-pulse';
            case 'finished':
                return 'text-gray-600';
            case 'scheduled':
                return 'text-blue-600';
            default:
                return 'text-gray-500';
        }
    }

    highlightUpdate(element) {
        // Add a subtle highlight animation
        element.classList.add('bg-yellow-50');
        setTimeout(() => {
            element.classList.remove('bg-yellow-50');
        }, 2000);
    }

    adjustPollingInterval() {
        const currentInterval = this.getCurrentInterval();
        
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        
        this.pollingInterval = setInterval(() => {
            if (this.isActive) {
                this.updateData();
            }
        }, currentInterval);
    }

    async updateStatistics() {
        try {
            const response = await fetch('/api/live/statistics', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateStatisticsDisplay(data);
            }
        } catch (error) {
            console.warn('Statistics update failed:', error);
        }
    }

    updateStatisticsDisplay(data) {
        // Update accuracy display
        const accuracyElements = document.querySelectorAll('.accuracy-value');
        accuracyElements.forEach(element => {
            if (element.textContent !== `${data.accuracy}%`) {
                element.textContent = `${data.accuracy}%`;
                this.highlightUpdate(element.closest('.bg-white, .card'));
            }
        });

        // Update live matches count
        const liveCountElements = document.querySelectorAll('.live-matches-count');
        liveCountElements.forEach(element => {
            element.textContent = data.live_matches;
        });
    }

    handleError() {
        this.retryCount++;
        this.updateIndicator('error');
        
        if (this.retryCount >= this.maxRetries) {
            // Exponential backoff
            setTimeout(() => {
                this.retryCount = 0;
                this.updateData();
            }, this.baseInterval * 2);
        }
    }

    updateIndicator(status) {
        const indicator = document.getElementById('live-indicator');
        if (!indicator) return;

        const dot = indicator.querySelector('.w-2');
        const text = indicator.querySelector('span');
        
        switch (status) {
            case 'active':
                dot.className = 'w-2 h-2 bg-green-500 rounded-full animate-pulse mr-1';
                text.textContent = 'En vivo';
                break;
            case 'paused':
                dot.className = 'w-2 h-2 bg-yellow-500 rounded-full mr-1';
                text.textContent = 'Pausado';
                break;
            case 'error':
                dot.className = 'w-2 h-2 bg-red-500 rounded-full mr-1';
                text.textContent = 'Error';
                break;
        }
    }

    addVisibilityHandling() {
        // Reduce polling when page is not visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.baseInterval = 60000; // 1 minute when hidden
                this.fastInterval = 30000;  // 30 seconds when hidden
            } else {
                this.baseInterval = 30000; // 30 seconds when visible
                this.fastInterval = 10000;  // 10 seconds when visible
            }
            this.adjustPollingInterval();
        });
    }

    destroy() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        this.isActive = false;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on pages that need live updates
    const needsLiveUpdates = [
        '/', 
        '/matches', 
        '/statistics',
        '/teams'
    ].some(path => window.location.pathname.startsWith(path));

    if (needsLiveUpdates) {
        window.liveUpdates = new LiveUpdates();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.liveUpdates) {
        window.liveUpdates.destroy();
    }
});