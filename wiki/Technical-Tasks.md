# **Technical Tasks: ChoreLoop**

## **1. Backend & RESTful API Tasks (PHP)**

### **Phase 1: Infrastructure & Data**
* **T1.1: Database Schema Migration** – Create `users`, `chores`, and `activity_logs` tables.
* **T1.2: Base API Controller & Routing** – Set up `/api/*` entry points.
* **T1.3: Response Wrapper Implementation** – Build utility for standardized JSON responses.
* **T1.4: Middleware: Identity Validation** – Implement Bearer Token check.

### **Phase 2: Identity & User Endpoints**
* **T2.1: POST /api/users/add** – Register name, return UUID.
* **T2.2: GET /api/users** – Fetch user list for onboarding.

### **Phase 3: Chore Lifecycle & Business Logic**
* **T3.1: GET /api/chores** – Fetch chores with status filters and sorting.
* **T3.2: POST /api/chores/add** – Add new chore with validation.
* **T3.3: PUT /api/chores/{id}/claim** – Claim logic with 409 check.
* **T3.4: PUT /api/chores/{id}/done** – Completion logic with ownership guard.
* **T3.5: PUT /api/chores/{id}/archive** – Soft-delete/Archive.
* **T3.6: PUT /api/chores/{id}/unclaim & /unarchive** – Support "Undo" mechanism.

### **Phase 4: Accountability Logging**
* **T4.1: Activity Log Observer** – Auto-log status changes.
* **T4.2: GET /api/logs** – Retrieve history for a chore.

---

## **2. Web App Tasks (Vanilla JS)**

### **Phase 1: Core Architecture**
* **W1.1: Project Scaffolding** – HTML5/CSS grid and entry points.
* **W1.2: API Service Module** – `fetch` wrapper with Bearer token.
* **W1.3: Central State Store** – Manage chore list and identity.

### **Phase 2: Identity Selection**
* **W2.1: Identity Selection UI** – Modal for onboarding.
* **W2.2: LocalStorage Persistence** – Save UUID locally.

### **Phase 3: Dashboard & Components**
* **W3.1: Multi-Section Dashboard Layout** – Build 4-section grid.
* **W3.2: Chore Card Component** – Conditional rendering of actions.
* **W3.3: Refetch/Polling Logic** – Keep UI fresh.

### **Phase 4: Safety & UX**
* **W4.1: Undo Snackbar** – 5-second safety net.
* **W4.2: Confirmation Dialogs** – For "Take Over" flow.
* **W4.3: Connection Interceptor** – Global offline overlay.
