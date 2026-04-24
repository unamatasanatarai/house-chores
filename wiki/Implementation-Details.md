# **Implementation Details: ChoreLoop**

**Status:** Ready \
**Date:** April 24, 2026

## **1. Core System Architecture**
Based on the HLD, the implementation must adhere to a **Single Source of Truth (SSOT)** model.

* **Backend:** PHP RESTful API (Stateless).
* **Android:** MVVM + Jetpack Compose + Room (Cache only) + DataStore (Identity).
* **Web:** ES6 Modules + Vanilla JS + `localStorage` (Identity).
* **State Policy:** Always-online. No offline write support. Conflict resolution follows "First Write Wins" via Server Validation.

---

## **2. Technical Build Sequence**

### **Phase 1: The Foundation (Infrastructure & Identity)**
* **T1: Database Schema Deployment**
    * Implement `users`, `chores`, and `activity_logs` tables.
* **T2: Identity API & Client Persistence**
    * **Backend:** `POST /api/users/add` and `GET /api/users`.
    * **Android:** Implement `IdentityRepository` using Jetpack DataStore.
    * **Web:** Implement `auth.js` module using `localStorage`.
* **T3: API Contract Scaffolding**
    * Standardized JSON response wrappers.
    * `Bearer [TOKEN]` header validation.

### **Phase 2: The Core Loop (Chore Lifecycle)**
* **T4: CRUD & State Machine**
    * Backend logic for transitions: `available` ↔ `claimed` ↔ `completed` ↔ `archived`.
    * **Guardrails:** Enforce that only the current claimant can execute `unclaim` or `done`.
* **T5: Conflict Handling (The 409 Logic)**
    * Server-side checks for `PUT /claim`. If `claimed_by` is set, return `409_CONFLICT_ALREADY_CLAIMED`.
* **T6: Multi-Section Dashboard**
    * Build sections: Available, Claimed by Me, Claimed by Others, Completed.
    * Sorting: Overdue at top, then Due Date ASC.

### **Phase 3: Soft Accountability & UX Safety**
* **T7: Activity Logging**
    * Hook into chore actions to populate `activity_logs`.
    * Build `GET /api/logs?chore_id=UUID`.
* **T8: Safety Mechanisms**
    * **Undo:** 5-second Snackbar logic on the client.
    * **Take Over:** flow with confirmation dialog for chores owned by others.

---

## **3. Detailed Task Breakdown**

### **Data Models (SQL)**
```sql
CREATE TABLE chores (
    id UUID PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    status VARCHAR(30),
    due_date TIMESTAMP,
    created_by UUID,
    claimed_by UUID,
    completed_by UUID,
    archived_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    claimed_at TIMESTAMP,
    completed_at TIMESTAMP,
    archived_at TIMESTAMP
);
```

### **API Implementation (PHP)**
* **Middleware:** Check for `Authorization: Bearer [TOKEN]`.
* **Validation:** Use `422_VALIDATION_ERROR` for invalid inputs.

### **Android (Compose)**
* **MVVM ViewModels:** `ChoreViewModel` handles polling logic.
* **UI Components:** `ChoreCard` with conditional action buttons.

---

## **4. Risks & Technical Mitigations**

| Risk | Implementation Action |
| :--- | :--- |
| **Race Conditions** | Server validates `claimed_by IS NULL` before updating. |
| **Stale UI** | Clients trigger `GET /api/chores` after any `PUT`. |
| **Identity Loss** | Atomic DataStore updates (Android); localStorage checks (Web). |
| **Connectivity** | Global "Offline" overlay if status code is unreachable. |
