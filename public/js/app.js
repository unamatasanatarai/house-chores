import { api } from './api.js';
import { store } from './store.js';

const appContainer = document.getElementById('app');

async function initApp() {
    window.addEventListener('unauthorized', () => renderIdentitySelection());

    const storedUserId = localStorage.getItem('choreloop_user_id');
    
    if (!storedUserId) {
        return renderIdentitySelection();
    }

    try {
        // Verify identity and load initial data
        const usersResponse = await api.get('/users');
        const currentUser = usersResponse.data.find(u => u.id === storedUserId);
        
        if (!currentUser) {
            localStorage.removeItem('choreloop_user_id');
            return renderIdentitySelection();
        }

        store.setState({ user: currentUser, users: usersResponse.data });
        renderDashboardView();
    } catch (error) {
        store.setState({ error: 'Failed to connect to the household server.' });
        renderError();
    }
}

function renderIdentitySelection() {
    appContainer.innerHTML = `
        <div class="identity-screen">
            <h1 class="logo">ChoreLoop</h1>
            <p>Who is helping out today?</p>
            <div id="user-list" class="user-grid">
                <div class="spinner"></div>
            </div>
            <div class="divider"><span>or</span></div>
            <form id="add-user-form">
                <input type="text" placeholder="Add a new name..." required>
                <button type="submit">Join</button>
            </form>
        </div>
    `;

    loadUsersForSelection();
}

async function loadUsersForSelection() {
    try {
        const response = await api.get('/users');
        const userList = document.getElementById('user-list');
        
        if (response.data.length === 0) {
            userList.innerHTML = '<p class="empty-msg">No profiles found yet. Create the first one!</p>';
        } else {
            userList.innerHTML = response.data.map(user => `
                <button class="user-card" data-id="${user.id}">
                    <div class="user-avatar">${user.name[0]}</div>
                    <span>${user.name}</span>
                </button>
            `).join('');
        }

        document.querySelectorAll('.user-card').forEach(btn => {
            btn.onclick = () => selectUser(btn.dataset.id);
        });

        document.getElementById('add-user-form').onsubmit = async (e) => {
            e.preventDefault();
            const name = e.target.querySelector('input').value;
            const res = await api.post('/users/add', { name });
            selectUser(res.data.id);
        };
    } catch (error) {
        console.error('Failed to load users');
    }
}

function selectUser(userId) {
    localStorage.setItem('choreloop_user_id', userId);
    initApp();
}

import { renderDashboard, stopPolling } from './dashboard.js';

async function renderDashboardView() {
    const { user } = store.state;
    appContainer.innerHTML = `
        <div class="top-bar">
            <div class="user-info">
                <span>Acting as: <strong>${user.name}</strong></span>
                <button id="switch-user" class="btn-text">Switch User</button>
            </div>
        </div>
        <div id="dashboard-content">
            <div class="spinner"></div>
        </div>
    `;

    document.getElementById('switch-user').onclick = () => {
        localStorage.removeItem('choreloop_user_id');
        store.setState({ user: null }); // Clear user in store!
        stopPolling();
        renderIdentitySelection();
    };

    await renderDashboard(document.getElementById('dashboard-content'));
}

// Global Network Listeners
window.addEventListener('offline', () => ui.showOfflineOverlay());
window.addEventListener('online', () => ui.hideOfflineOverlay());

function renderError() {
    appContainer.innerHTML = `
        <div class="error-screen">
            <h2>Oops!</h2>
            <p>${store.state.error}</p>
            <button onclick="location.reload()">Try Again</button>
        </div>
    `;
}

initApp();
