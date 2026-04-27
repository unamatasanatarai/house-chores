export const store = {
    state: {
        user: null, // Current active user {id, name}
        users: [],  // All users for selection
        chores: [], // Current dashboard chores
        isLoading: true,
        error: null
    },

    listeners: [],

    subscribe(callback) {
        this.listeners.push(callback);
        return () => {
            this.listeners = this.listeners.filter(l => l !== callback);
        };
    },

    notify() {
        this.listeners.forEach(callback => callback(this.state));
    },

    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.notify();
    },

    async init() {
        const storedUserId = localStorage.getItem('choreloop_user_id');
        if (storedUserId) {
            // We'll set the user later after verification in app.js
        }
        this.setState({ isLoading: false });
    }
};
