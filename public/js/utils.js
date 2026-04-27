export const ui = {
    snackbar(message, action = null, duration = 5000) {
        const existing = document.querySelector('.snackbar');
        if (existing) existing.remove();

        const bar = document.createElement('div');
        bar.className = 'snackbar';
        bar.innerHTML = `
            <span>${message}</span>
            ${action ? `<button id="snackbar-action">${action.label}</button>` : ''}
            <div class="progress-bar"></div>
        `;

        document.body.appendChild(bar);

        if (action) {
            document.getElementById('snackbar-action').onclick = () => {
                action.callback();
                bar.remove();
            };
        }

        setTimeout(() => {
            bar.classList.add('fade-out');
            setTimeout(() => bar.remove(), 500);
        }, duration);
    },

    showOfflineOverlay() {
        if (document.querySelector('.offline-overlay')) return;
        const overlay = document.createElement('div');
        overlay.className = 'offline-overlay';
        overlay.innerHTML = `
            <div class="overlay-content">
                <div class="logo">ChoreLoop</div>
                <h2>Connection Lost</h2>
                <p>We're trying to reconnect to your household server...</p>
                <div class="spinner"></div>
            </div>
        `;
        document.body.appendChild(overlay);
    },

    hideOfflineOverlay() {
        const overlay = document.querySelector('.offline-overlay');
        if (overlay) overlay.remove();
    }
};
