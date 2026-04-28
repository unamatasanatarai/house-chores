export const ui = {
    modalStack: [],

    pushModal(element, closeCallback) {
        const trigger = document.activeElement;
        this.modalStack.push({ element, closeCallback, trigger });
    },

    popModal() {
        return this.modalStack.pop();
    },

    get activeModal() {
        return this.modalStack[this.modalStack.length - 1];
    },

    ICON_ENTER: '<span class="key-cap">↵</span>',
    ICON_ESC: '<span class="key-cap">Esc</span>',

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
    },

    formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}d ago`;
        
        return date.toLocaleDateString();
    },

    confetti() {
        const canvas = document.createElement('canvas');
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '3000';
        document.body.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const particles = [];
        const colors = ['#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444'];

        function createBurst(x, y, angleRange) {
            for (let i = 0; i < 60; i++) {
                const angle = angleRange[0] + Math.random() * (angleRange[1] - angleRange[0]);
                const speed = Math.random() * 50 + 20;
                particles.push({
                    x: x,
                    y: y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    size: Math.random() * 8 + 4,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    opacity: 1,
                    rotation: Math.random() * Math.PI * 2,
                    vRotation: (Math.random() - 0.5) * 0.4
                });
            }
        }

        // Left corner (aiming up-right: -45 to -75 degrees)
        createBurst(0, canvas.height, [-Math.PI / 2.5, -Math.PI / 6]);
        // Right corner (aiming up-left: -105 to -135 degrees)
        createBurst(canvas.width, canvas.height, [-Math.PI + Math.PI / 6, -Math.PI + Math.PI / 2.5]);

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let finished = true;

            particles.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 1.2; // Gravity (doubled)
                p.vx *= 0.98; // Air resistance (slight increase)
                p.opacity -= 0.016; // Fade speed (doubled)
                p.rotation += p.vRotation;

                if (p.opacity > 0 && p.y < canvas.height + 100) {
                    finished = false;
                    ctx.save();
                    ctx.globalAlpha = p.opacity;
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rotation);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                    ctx.restore();
                }
            });

            if (!finished) {
                requestAnimationFrame(animate);
            } else {
                canvas.remove();
            }
        }

        animate();
    }
};
