# ChoreLoop: Collaborative Household Orchestration

ChoreLoop is a shared task-management system designed to eliminate household friction through a **Flat Trust Model**. It replaces restrictive, hierarchical permissions with **Soft Accountability**, leveraging high visibility and activity logging to coordinate responsibilities in a single-household environment.

---

## 1. Problem & Context

Traditional task managers are often built for corporate environments, utilizing rigid Role-Based Access Control (RBAC) that creates unnecessary friction in a domestic setting. Conversely, simple "to-do" lists lack the attribution necessary to prevent the "nag factor" or "stolen credit."

**Constraints & Assumptions:**
* **The "Nag" Factor:** The system must identify who is responsible for what without requiring a "manager" role.
* **Zero-Friction Identity:** In a trusted household, complex authentication (passwords/social login) is a barrier to entry for children and busy adults.
* **Single Source of Truth (SSOT):** To prevent desynchronization between roommates or family members, the system prioritizes real-time state over offline availability.

---

## 2. Solution Overview

ChoreLoop implements a **Claim → Own → Complete** lifecycle. The core innovation is its **Flat Identity Model**, where every user has equal power to create, claim, or even "Take Over" tasks. Accountability is enforced socially via a persistent, immutable **Activity Log** rather than programmatically via restrictions.

---

## 3. System Design & Architecture

The system follows a **Stateless REST Architecture** with the server acting as the absolute SSOT.

* **Backend:** PHP-based RESTful API managing the state machine and activity streams.
* **Clients:** A "Thin Client" approach using Vanilla JS (Web) and Jetpack Compose (Android), ensuring business logic remains centralized on the server to prevent drift.
* **Data Flow:** Unidirectional state flow where clients poll/refetch after every mutation to ensure the UI reflects the most recent server-validated state.

### **Architectural Trade-offs**
* **Consistency over Availability (CP):** By mandating an **Always-Online** state, we eliminated the overhead of conflict resolution (OT/CRDT) and eventual consistency logic. This trade-off ensures that two users can never claim the same chore simultaneously without one receiving an immediate error.
* **Statelessness:** The API uses a simple `Bearer [TOKEN]` (Name-based UUID) to maintain session context without the complexity of JWT refresh cycles or OAuth, matching the MVP’s security-trust profile.

---

## 4. Technical Decisions & Trade-offs

### **First-Write-Wins Conflict Resolution**
To handle race conditions in a collaborative environment (e.g., two users clicking "Claim" at the same time), I implemented a strict server-side validation layer. If a `PUT /claim` request reaches the server for a chore already assigned, the server returns a `409 Conflict`. The client is designed to intercept this, display the current owner, and force a dashboard refresh.

### **Soft Accountability vs. Hard Permissions**
The decision to allow any user to "Take Over" or "Archive" any chore was intentional. 
* **Decision:** Optimize for "Real-world behavior" (e.g., a parent finishing a child's chore).
* **Risk:** Trolling or accidental deletion.
* **Mitigation:** Every action triggers an entry in the `activity_logs` table. By making actions public and attributable, social pressure replaces the need for complex permission logic.

### **Safety via "Undo" State**
Since the system lacks a "Trash" bin (favoring an Archive-only model), I implemented a **5-second Snackbar Undo loop**. This allows for high-velocity interaction while providing a safety net for accidental taps, without the database complexity of soft-deletes or versioning.

---

## 5. Implementation Highlights

* **Chore State Machine:** Implemented a robust backend transition logic (Available ↔ Claimed ↔ Completed ↔ Archived) that prevents invalid transitions (e.g., completing an unassigned task).
* **Cross-Platform Identity Bridge:** Designed a consistent local identity persistence layer using **Jetpack DataStore** (Android) and **LocalStorage** (Web) to ensure a "Login-once" experience that survives app kills and cache clears.
* **Urgency-Based Sorting:** Developed a client-side sorting algorithm that prioritizes `Overdue` status (computed against `due_date`) regardless of claim status, ensuring that the most critical tasks are always at the top of the viewport.

---

## 6. Tech Stack

* **Backend:** PHP (REST API), MySQL (Persistence)
* **Android:** Kotlin, Jetpack Compose, MVVM, Room (Cache-only)
* **Web:** Vanilla JavaScript (ES6 Modules), CSS3 (Grid/Flexbox)
* **Communication:** JSON-over-HTTP (Standardized Success/Error Schemas)

---

## 7. Limitations & Future Work

* **Security:** The current Bearer Token model is optimized for trust. For a multi-household "SaaS" version, this would be replaced with OIDC/Auth0.
* **Connectivity:** The "Always-Online" requirement is a limitation in low-signal areas (basements/garages). Future iterations would involve a local-first sync engine using Room/SQLite.
* **Proactive Engagement:** The lack of push notifications requires users to "habit-check" the app. Integration of WebPush or Firebase Cloud Messaging is the logical next step for increasing "Claim Velocity."

---

## 8. Key Takeaways

* **Designed a high-trust collaborative system** that minimizes technical friction by leveraging social accountability.
* **Implemented a strict SSOT architecture** to solve concurrency and state-drift issues across heterogeneous clients (Web/Android).
* **Balanced UX and Data Integrity** by utilizing optimistic UI patterns coupled with server-side conflict validation (409 logic).
* **Translated Product Requirements** into a lean, implementation-ready technical specification focused on MVP velocity and core value delivery.