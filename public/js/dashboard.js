import { api } from './api.js';
import { store } from './store.js';

let pollingInterval = null;

export async function renderDashboard(container) {
    const { user } = store.state;
    if (!user) return;

    // Initial grid setup
    container.innerHTML = `
        <div class="dashboard-header">
            <div class="greeting">
                <h1>Hello, ${user.name}!</h1>
                <p>Ready to tackle the day? (${new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })})</p>
            </div>
            <button id="add-chore-btn" class="btn-primary">✨ Add Chore</button>
        </div>
        <div class="dashboard-grid">
            <section id="available">
                <h2>Available Chores</h2>
                <div class="chore-list"><div class="spinner"></div></div>
            </section>
            <section id="claimed-me">
                <h2>My Chores</h2>
                <div class="chore-list"><div class="spinner"></div></div>
            </section>
            <section id="claimed-others">
                <h2>Family Progress</h2>
                <div class="chore-list"><div class="spinner"></div></div>
            </section>
            <section id="completed">
                <h2>Recently Completed</h2>
                <div class="chore-list"><div class="spinner"></div></div>
            </section>
        </div>
    `;

    document.getElementById('add-chore-btn').onclick = showAddChoreModal;

    startPolling();
    await loadChores();
}

export function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

function startPolling() {
    stopPolling();
    pollingInterval = setInterval(loadChores, 10000); // Poll every 10 seconds
}

async function loadChores() {
    try {
        console.log('Fetching chores...');
        const response = await api.get('/chores');
        console.log('API Raw Response:', response);
        
        if (response.success && Array.isArray(response.data)) {
            console.log(`Loaded ${response.data.length} chores`);
            store.setState({ chores: response.data });
            updateChoreLists();
        } else {
            console.error('Invalid chores data format:', response);
        }
    } catch (error) {
        console.error('Failed to load chores:', error);
    }
}

function updateChoreLists() {
    const { user, chores } = store.state;
    if (!user) {
        console.warn('updateChoreLists: No user in store');
        return;
    }
    
    console.log('User ID for filtering:', user.id);
    if (chores.length > 0) {
        console.log('Sample chore claimed_by:', chores[0].claimed_by);
    }

    const sections = {
        'available': chores.filter(c => c.status === 'available' && !c.claimed_by),
        'claimed-me': chores.filter(c => c.status === 'claimed' && c.claimed_by == user.id),
        'claimed-others': chores.filter(c => c.status === 'claimed' && c.claimed_by != user.id && c.claimed_by),
        'completed': chores.filter(c => c.status === 'completed')
    };

    console.log('Sections count:', {
        available: sections.available.length,
        'claimed-me': sections['claimed-me'].length,
        'claimed-others': sections['claimed-others'].length,
        completed: sections.completed.length
    });

    Object.entries(sections).forEach(([id, list]) => {
        const listContainer = document.querySelector(`#${id} .chore-list`);
        if (!listContainer) {
            console.error(`List container not found for section: #${id}`);
            return;
        }
        
        if (list.length === 0) {
            const emptyMsgs = {
                'available': '✨ All caught up! The house is clean (for now).',
                'claimed-me': '☕ No active chores. Time for a break?',
                'claimed-others': '👥 Everyone is resting or chores are done.',
                'completed': '🧹 No recent activity to show.'
            };
            listContainer.innerHTML = `<div class="empty-state">${emptyMsgs[id] || 'No chores here.'}</div>`;
        } else {
            listContainer.innerHTML = list.map(chore => renderChoreCard(chore)).join('');
        }
    });

    attachCardListeners();
}

function renderChoreCard(chore) {
    const { user } = store.state;
    const isOwner = chore.claimed_by == user.id;
    const isOverdue = chore.is_overdue == 1 || chore.is_overdue === "1";

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
            actions = `<button class="action-btn-text take-over" data-id="${chore.id}" data-owner="${chore.claimer_name || 'someone else'}">🔄 Take Over</button>`;
        }
    }

    return `
        <div class="chore-card ${isOverdue ? 'overdue' : ''}" data-id="${chore.id}">
            <div class="chore-card-content">
                <div class="chore-card-top">
                    <h3>${chore.title}</h3>
                    <div class="chore-card-icons">
                        <button class="icon-btn view-log" data-id="${chore.id}" title="History">🕒</button>
                        <button class="icon-btn archive" data-id="${chore.id}" title="Archive">🗑️</button>
                    </div>
                </div>
                ${chore.description ? `<p>${chore.description}</p>` : ''}
                <div class="chore-meta">
                    ${isOverdue ? '<span class="badge danger">⚠️ Overdue</span>' : ''}
                    ${chore.due_date ? `<span>📅 ${new Date(chore.due_date).toLocaleDateString()}</span>` : ''}
                    <span class="meta-owner">👤 Created by: ${chore.creator_name || 'Unknown'}</span>
                    ${chore.status === 'completed' ? `<span class="meta-owner">✅ Done by: ${chore.completer_name || 'Unknown'}</span>` : ''}
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
            const btn = document.querySelector(`.take-over[data-id="${id}"]`);
            const owner = btn ? btn.dataset.owner : 'someone else';
            if (!confirm(`This chore is claimed by ${owner}. Take over?`)) return;
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
            if (btn.classList.contains('view-log')) {
                showChoreLogModal(id);
                return;
            }
            const action = [...btn.classList].find(c => ['claim', 'unclaim', 'done', 'archive', 'take-over'].includes(c));
            handleAction(id, action);
        };
    });
}

async function showChoreLogModal(choreId) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>🕒 Activity Log</h2>
                <button class="btn-text close-modal">Close</button>
            </div>
            <div class="log-list">
                <div class="spinner"></div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    const listContainer = modal.querySelector('.log-list');
    const closeBtn = modal.querySelector('.close-modal');

    closeBtn.onclick = () => modal.remove();
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };

    try {
        const response = await api.get(`/logs?chore_id=${choreId}`);
        const logs = response.data;

        if (logs.length === 0) {
            listContainer.innerHTML = '<p class="empty-state">No history recorded yet.</p>';
        } else {
            listContainer.innerHTML = logs.map(log => `
                <div class="log-item">
                    <div class="log-dot ${log.action}"></div>
                    <div class="log-info">
                        <strong>${log.user_name}</strong> ${formatAction(log.action)}
                        <span class="log-time">${ui.formatTimeAgo(log.created_at)}</span>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        listContainer.innerHTML = '<p class="error">Failed to load history.</p>';
    }
}

function formatAction(action) {
    const map = {
        'created': 'created this task',
        'claimed': 'claimed this task',
        'unclaimed': 'returned this task to available',
        'completed': 'marked this task as done',
        'archived': 'archived this task',
        'unarchived': 'restored this task',
        'taken_over': 'took over this task'
    };
    return map[action] || action;
}

function showAddChoreModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <h2>✨ New Chore</h2>
            <form id="add-chore-form">
                <div class="form-group">
                    <label>What needs to be done?</label>
                    <input type="text" name="title" placeholder="e.g., Wash the dishes" required>
                </div>
                <div class="form-group">
                    <label>Details (Optional)</label>
                    <textarea name="description" placeholder="Any special instructions?"></textarea>
                </div>
                <div class="form-group">
                    <label>When is it due? *</label>
                    <input type="date" name="due_date" required min="${new Date().toISOString().split('T')[0]}">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-text cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Add Chore</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(modal);

    const form = modal.querySelector('form');
    const cancelBtn = modal.querySelector('.cancel');

    const closeModal = () => {
        modal.classList.add('fade-out');
        setTimeout(() => modal.remove(), 300);
    };

    cancelBtn.onclick = closeModal;
    modal.onclick = (e) => { if (e.target === modal) closeModal(); };

    form.onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            await api.post('/chores/add', data);
            closeModal();
            loadChores();
            ui.snackbar('Chore added successfully! 🚀');
        } catch (error) {
            ui.snackbar(error.message || 'Failed to add chore');
        }
    };
}
