# Action Plan: ChoreLoop API Implementation (PHP)

This document serves as a detailed checklist for an AI agent to build the **ChoreLoop RESTful API**. It is based on the Product Requirements, High-Level Design, and Implementation roadmap.

## 🏗️ Phase 1: Docker & Environment Setup
- [x] **Spin up Docker Services**
    - Run `docker-compose up -d` to start the PHP `app` and `mysql` containers.
    - Verify connectivity: `docker-compose exec app php -r "new PDO('mysql:host=db;dbname=family_chores', 'root', 'root_password');"`.
- [x] **Database Migration (MySQL in Docker)**
    - Implement the `migrations/` logic.
    - Run migrations inside the container: `docker-compose exec app php migrations/migrate.php`.
    - Create `users`, `chores`, and `activity_logs` tables as defined in HLD Section 8.
- [x] **Standardized Response Utility**
    - Create a wrapper class/function to ensure all responses match the HLD schema:
      ```json
      { "success": true, "data": {}, "meta": {} }
      { "success": false, "error": { "code": "...", "message": "..." } }
      ```

## 🔐 Phase 2: Infrastructure & Security
- [x] **API Routing & Entry Point**
    - Configure `public/index.php` as the entry point.
    - Implement routing for `/api/users/*`, `/api/chores/*`, and `/api/logs/*`.
- [x] **Identity Middleware (Bearer Token)**
    - Implement middleware to intercept all requests to `/api/chores` and `/api/logs`.
    - Extract `Bearer [TOKEN]` (which is a User UUID).
    - Validate that the UUID exists in the `users` table.
    - Return `401_UNAUTHORIZED` if missing or invalid.
    - Inject the `authenticated_user_id` into the request context for downstream use.

## 👤 Phase 3: Identity & User Endpoints
- [x] **POST `/api/users/add`**
    - Validate `name` is present.
    - Generate a new UUID.
    - Return the new User object + 201 Created.
- [x] **GET `/api/users`**
    - Fetch and return a list of all users (for the initial identity selection screen).

## 📋 Phase 4: Chore Management (CRUD & Lifecycle)
- [x] **POST `/api/chores/add`**
    - Required: `title`. Optional: `description`, `due_date` (ISO8601).
    - Auto-fill `created_by` (from token) and `status` ('available').
- [x] **GET `/api/chores`**
    - Implement status filtering (`?status=available`).
    - Implement sorting logic: **Overdue first**, then **Due Date ASC**.
    - Implement pagination (HLD 7.7).
- [x] **PUT `/api/chores/{id}/claim` (Critical: Conflict Check)**
    - **Logic:** Update `status` to 'claimed' and set `claimed_by`.
    - **Safety:** Must verify `claimed_by IS NULL` in the same transaction or check before update.
    - Return `409_CONFLICT_ALREADY_CLAIMED` if already taken.
- [x] **PUT `/api/chores/{id}/unclaim`**
    - **Logic:** Revert status to 'available', clear `claimed_by`.
    - **Constraint:** Only the current owner can unclaim.
- [x] **PUT `/api/chores/{id}/done`**
    - **Logic:** Update status to 'completed', set `completed_by`.
    - **Constraint:** Only the current owner can mark as done.
- [x] **PUT `/api/chores/{id}/archive`**
    - **Logic:** Update status to 'archived', set `archived_by`.
    - **Safety:** Open to any user (Flat Trust Model).
- [x] **PUT `/api/chores/{id}/unarchive`**
    - **Logic:** Revert status to 'available' (or previous state). Supports the "Undo" window.

## 📜 Phase 5: Activity Logging
- [x] **Log Observer / Hook**
    - Implement a central mechanism to log every state transition.
    - Actions to track: `created`, `claimed`, `unclaimed`, `completed`, `archived`.
- [x] **GET `/api/logs`**
    - Allow filtering by `chore_id` or `user_id`.
    - Return a chronological list of actions for the audit trail.

## ✅ Phase 6: Dockerized Testing & Validation
- [x] **Run Automated Tests in Container**
    - Execute PHPUnit: `docker-compose exec app ./vendor/bin/phpunit`.
- [x] **Identity Check:** `curl -H "Authorization: Bearer invalid" http://localhost:8080/api/chores` → Expect `401`.
- [x] **Validation Check:** `curl -X POST -d '{"description":"test"}' http://localhost:8080/api/chores/add` → Expect `422`.
- [x] **Concurrency Test:** Use a script to fire two `PUT /claim` requests at once → Expect one `200` and one `409`.
- [x] **State Machine Check:** Try to mark an unclaimed chore as "Done" → Expect `409_CONFLICT_INVALID_STATE`.
- [x] **Sorting Check:** Create an overdue chore and a future chore → Verify overdue appears first in `GET /api/chores`.

---

> [!IMPORTANT]
> **Docker Note:** Ensure all file paths in PHP (like migration files) are relative to `/var/www/html` or the container's working directory.
> **Implementation Note:** Always use the server's current time for timestamps. Do not trust client-provided timestamps for lifecycle events (except for the `due_date` metadata).



# Action Plan: ChoreLoop Web Frontend (Vanilla JS)

This plan outlines the steps to build a premium, responsive web application for ChoreLoop. We will use a "No-Framework" approach with ES6 modules and Vanilla CSS.

## 🎨 Phase 1: Design System & Scaffolding
- [x] **Define Design Tokens (Vanilla CSS)**
    - Create `public/css/variables.css` with a sleek, modern color palette (deep purples, vibrant accents, glassmorphism tokens).
    - Setup typography (Google Fonts: Inter/Outfit).
- [x] **Project Structure**
    - `public/js/` for modules.
    - `public/js/api.js` (API Service).
    - `public/js/store.js` (State Management).
    - `public/js/app.js` (Main Entry).
    - `public/components/` for UI elements.

## 🔌 Phase 2: Core Infrastructure
- [x] **API Service Module (`api.js`)**
    - Implement a `fetch` wrapper that handles global error states and automatically attaches the `Authorization` header.
- [x] **State Management (`store.js`)**
    - Create a simple reactive store to hold chores, users, and the current active identity.

## 👤 Phase 3: Identity & Onboarding
- [x] **Identity Selection UI**
    - Build a high-fidelity onboarding screen for selecting an existing user or creating a new one.
    - Implement `localStorage` persistence to skip this screen on subsequent visits.
- [x] **Active User Header**
    - Create the TopAppBar showing "Acting as: [Name]" with a "Switch User" option.

## 📋 Phase 4: The Chore Dashboard
- [x] **Multi-Section Grid**
    - Implement the 4 sections: *Available, Claimed by Me, Claimed by Others, Completed*.
- [x] **Chore Card Component**
    - Design a premium card with micro-animations on hover.
    - Implement conditional action buttons (Claim, Done, Take Over).
- [x] **Real-time Refresh Logic**
    - Implement background polling to keep the dashboard in sync with other family members.

## 🛡️ Phase 5: UX & Safety
- [x] **The "Undo" Snackbar**
    - Build a custom snackbar with a countdown timer for archiving/unclaiming actions.
- [x] **Confirmation Dialogs**
    - Implement accessible modals for "Take Over" and other high-impact actions.
- [x] **Offline Overlay**
    - Add a global interceptor to show a "Connection Lost" screen if the API is unreachable.

---

> [!TIP]
> **Premium Feel:** Use CSS transitions for all list reorderings and status changes to make the "Claim → Own → Complete" loop feel satisfying.
