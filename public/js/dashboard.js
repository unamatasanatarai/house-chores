import { api } from './api.js';
import { store } from './store.js';

export async function renderDashboard(container) {
    container.innerHTML = `
        <div class="dashboard-header">
            <h1>Household Chores</h1>
            <button id="add-chore-btn" class="btn-primary">✨ Add Chore</button>
        </div>
        <div class="dashboard-grid">
            <section id="claimed-me">
                <h2>Claimed by Me</h2>
                <div class="chore-list"></div>
            </section>
            <section id="available">
                <h2>Available</h2>
                <div class="chore-list"></div>
            </section>
            <section id="claimed-others">
                <h2>Claimed by Others</h2>
                <div class="chore-list"></div>
            </section>
            <section id="completed">
                <h2>Recently Completed</h2>
                <div class="chore-list"></div>
            </section>
        </div>
    `;

    document.getElementById('add-chore-btn').onclick = showAddChoreModal;

    startPolling();
    loadChores();
}

let pollingInterval = null;

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(loadChores, 10000); // Poll every 10 seconds
}

async function loadChores() {
    try {
        const response = await api.get('/chores');
        store.setState({ chores: response.data });
        updateChoreLists();
    } catch (error) {
        console.error('Failed to load chores');
    }
}

function updateChoreLists() {
    const { user, chores } = store.state;
    
    const sections = {
        'claimed-me': chores.filter(c => c.status === 'claimed' && c.claimed_by === user.id),
        'available': chores.filter(c => c.status === 'available' && !c.claimed_by),
        'claimed-others': chores.filter(c => c.status === 'claimed' && c.claimed_by !== user.id),
        'completed': chores.filter(c => c.status === 'completed')
    };

    Object.entries(sections).forEach(([id, list]) => {
        const listContainer = document.querySelector(`#${id} .chore-list`);
        if (list.length === 0) {
            listContainer.innerHTML = `<div class="empty-state">No chores here.</div>`;
        } else {
            listContainer.innerHTML = list.map(chore => renderChoreCard(chore)).join('');
        }
    });

    attachCardListeners();
}

function renderChoreCard(chore) {
    const { user } = store.state;
    const isOwner = chore.claimed_by === user.id;
    const isOverdue = chore.is_overdue === "1";

    let actions = '';
    if (chore.status === 'available') {
        actions = `<button class="action-btn claim" data-id="${chore.id}">✋ Claim</button>`;
    } else if (chore.status === 'claimed') {
        if (isOwner) {
            actions = `
                <button class="action-btn done" data-id="${chore.id}">✅ Done</button>
                <button class="action-btn-text unclaim" data-id="${chore.id}">↩️ Unclaim</button>
            `;
        } else {
            actions = `<button class="action-btn-text take-over" data-id="${chore.id}">🔄 Take Over</button>`;
        }
    }

    return `
        <div class="chore-card ${isOverdue ? 'overdue' : ''}" data-id="${chore.id}">
            <div class="chore-card-content">
                <div class="chore-card-top">
                    <h3>${chore.title}</h3>
                    <button class="icon-btn archive" data-id="${chore.id}" title="Archive">🗑️</button>
                </div>
                ${chore.description ? `<p>${chore.description}</p>` : ''}
                <div class="chore-meta">
                    ${isOverdue ? '<span class="badge danger">⚠️ Overdue</span>' : ''}
                    ${chore.due_date ? `<span>📅 ${new Date(chore.due_date).toLocaleDateString()}</span>` : ''}
                </div>
            </div>
            <div class="chore-actions">
                ${actions}
            </div>
        </div>
    `;
}

import { ui } from './utils.js';

async function handleAction(id, action) {
    try {
        if (action === 'take-over') {
            if (!confirm('This chore is claimed by someone else. Take over?')) return;
            await api.put(`/chores/${id}/take-over`);
        } else if (action === 'claim') {
            await api.put(`/chores/${id}/claim`);
        } else if (action === 'unclaim') {
            await api.put(`/chores/${id}/unclaim`);
        } else if (action === 'done') {
            await api.put(`/chores/${id}/done`);
        } else if (action === 'archive') {
            await api.put(`/chores/${id}/archive`);
            ui.snackbar('Chore archived', {
                label: 'Undo',
                callback: async () => {
                    await api.put(`/chores/${id}/unarchive`);
                    loadChores();
                }
            });
        }
        loadChores();
    } catch (error) {
        ui.snackbar(error.message || 'Action failed');
    }
}

function attachCardListeners() {
    document.querySelectorAll('.action-btn, .action-btn-text, .icon-btn').forEach(btn => {
        btn.onclick = (e) => {
            const id = btn.dataset.id;
            const action = [...btn.classList].find(c => ['claim', 'unclaim', 'done', 'archive', 'take-over'].includes(c));
            handleAction(id, action);
        };
    });
}

function showAddChoreModal() {
    // Basic implementation for now
    const title = prompt('Chore Title:');
    if (!title) return;
    const description = prompt('Description (optional):');
    const due_date = prompt('Due Date (YYYY-MM-DD, optional):');

    api.post('/chores/add', { title, description, due_date }).then(loadChores);
}
