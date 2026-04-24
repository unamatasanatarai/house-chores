# **Product Requirements Document (PRD): ChoreLoop**

**Status:** Implementation-Ready \
**Date:** April 24, 2026 \
**Platform:** Web (Vanilla JS) + Android (Jetpack Compose) \
**Backend:** PHP (RESTful)

---

## 1. Product Summary
ChoreLoop is a shared household task-management system built on a **Flat Trust Model**. Unlike traditional project management tools with rigid hierarchies, ChoreLoop assumes all users are equal. It replaces restrictive permissions with **Soft Accountability**, using high visibility and activity logging to encourage responsible coordination within a single household.

* **Core Value Proposition:** Simplify household coordination through a transparent "Claim → Own → Complete" loop.
* **Problem Statement:** Existing household apps are either too complex (over-engineered permissions) or too simple (no tracking of who did what), leading to domestic friction.
* **Target Users:** Household members (adults and children) sharing responsibilities.

---

## 2. Goals & Non-Goals
### **Goals**
* Provide a Single Source of Truth (SSOT) for household chores.
* Minimize friction for claiming and completing tasks.
* Foster accountability through a public activity log and clear attribution.
* Ensure a consistent experience across Web and Android devices.

### **Non-Goals**
* **Role Management:** No "Parent" vs. "Child" permissions.
* **Offline Functionality:** The app will not support offline writes or eventual consistency for the MVP.
* **Gamification:** No points, rewards, or leveling systems.
* **External Comms:** No email or push notifications.

---

## 3. Personas
| Persona | Behaviors | Motivations | Pain Points |
| :--- | :--- | :--- | :--- |
| **The Organizer** | Creates most chores; monitors progress. | Wants a clean house without nag-cycles. | Forgetting if someone promised to do a task. |
| **The Contributor** | Claims tasks as they have time. | Wants to show they are helping. | Unclear expectations or "stolen" credit. |
| **The Dependent** | Uses a shared tablet or older phone. | Wants to finish tasks to get back to play. | Complex UI; losing track of their "owned" chores. |

---

## 4. User Problems & Jobs-To-Be-Done (JTBD)
* **Problem:** "I don't know what needs to be done right now."
    * **JTBD:** When I am free, I want to see a list of available chores so I can contribute to the house.
* **Problem:** "I did the dishes, but everyone thinks they are still dirty."
    * **JTBD:** When I finish a task, I want to mark it as complete so the household knows it's done.
* **Problem:** "Someone else said they'd do the trash, but it's still there."
    * **JTBD:** When a task is overdue, I want to see who claimed it so I can follow up or take it over.

---

## 5. Core User Flows
### **5.1 Identity Selection**
1.  **Entry:** First-time app launch.
2.  **Action:** User selects their name from a list or adds a new name.
3.  **Outcome:** UUID is stored in `localStorage` (Web) or `DataStore` (Android). User is redirected to Dashboard.

### **5.2 The Chore Loop (Claim → Complete)**
1.  **Claim:** User views "Available" chores, clicks "Claim."
2.  **Ownership:** Chore moves to "Claimed by Me." `claimed_by` is updated.
3.  **Completion:** User finishes real-world task, clicks "Complete."
4.  **Outcome:** Chore moves to "Completed (Recent)."

### **5.3 Taking Over a Chore**
1.  **Entry:** User sees a chore claimed by someone else that is overdue or stalled.
2.  **Action:** User clicks "Take Over."
3.  **Decision Point:** System shows a confirmation dialog: *"This chore is currently claimed by [Name]. Take over?"*
4.  **Outcome:** On confirm, `claimed_by` updates to current user. Activity log records the swap.

---

## 6. Functional Requirements

### **FR.1: Identity & Persistence**
* **Requirement:** Users must be identified by a name-based profile stored locally on the device.
* **Acceptance Criteria:**
    * App remains "locked" until a user profile is selected.
    * The active user's name is visible in the TopAppBar at all times.
* **Edge Case:** If the local ID is deleted (clear cache), the app must force a re-selection.

### **FR.2: Chore Management (CRUD)**
* **Requirement:** Any user can create, edit, or archive any chore.
* **Acceptance Criteria:**
    * **Create:** Fields required: Title. Optional: Description, Due Date.
    * **Archive:** Chores are never deleted, only moved to `archived` status.
    * **Undo:** Archiving a chore triggers a 5-second Snackbar with an "Undo" action (unarchive endpoint).

### **FR.3: Accountability Tracking**
* **Requirement:** Every chore must display its lifecycle history.
* **Acceptance Criteria:**
    * Chore detail view shows: "Created by [X]", "Claimed by [Y]", "Completed by [Z]".
    * Activity log displays the last 10 actions (e.g., "John claimed this 2 hours ago").

### **FR.4: Dashboard**
* **Requirement:** The dashboard should display all chores in a clear and organized manner.
* **Acceptance Criteria:**
    * **Available:** Chores that are not claimed.
    * **Claimed by Me:** Chores that are claimed by the current user.
    * **Claimed by Others:** Chores that are claimed by other users.
    * **Completed:** Completed chores.
    * **Overdue:** Overdue chores are displayed at the top of the list.

---

## 7. UX & Interaction Requirements
* **State Transitions:** When a chore is claimed, it must disappear from the "Available" list and appear in "Claimed by [User]" across all devices (via refetch).
* **Empty States:** If no chores are available, display: *"All caught up! The house is clean (for now)."*
* **Visibility:** Overdue chores must be visually highlighted and sorted to the top of the list.

---

## 8. Data & System Constraints
* **Always-Online:** Because the server is the SSOT, the app must show a "Connection Lost" overlay or error if the API is unreachable.
* **Flat Model Risk:** Any user can "Unclaim" or "Archive" someone else's chore. This is a choice, not a bug—rely on the Activity Log to discourage "trolling."
* **Conflict Handling:** If two users click "Claim" at the same time, the second user receives a `409 Conflict` error and their UI refreshes to show the chore is already taken.

---

## 9. Success Metrics
| Metric | Definition | Target |
| :--- | :--- | :--- |
| **Completion Rate** | % of created chores that reach "Completed" status. | > 80% |
| **Claim Velocity** | Average time between "Created" and "Claimed." | < 12 Hours |
| **Stickiness** | % of household members opening the app 4+ days/week. | 70% |

---

## 10. Edge Cases & Failure Scenarios
* **Double Claim:** User A and User B claim the same chore simultaneously. Server rejects User B.
* **User Misuse:** A user completes a chore they didn't do. Activity log shows "Completed by [User]." Others can "Unarchive" if necessary.
* **Network Timeout:** During a "Complete" action, the network drops. Show an error snackbar.

---

## 11. Open Questions & Assumptions
* **Assumption:** The "Household" is defined by everyone pointing their app to the same API instance. 
* **Question:** Should there be a "Recently Completed" auto-archive timer? Answer: No, we want to keep them visible.
* **Risk:** Without push notifications, users might forget to check the app.

---

## 12. Milestone Roadmap

### **Milestone 1: The "Identity"**
* **Goal:** Establish who is using the app.
* **Key Features:** User creation, persistent local storage of Identity.

### **Milestone 2: The "Chore Loop"**
* **Goal:** Complete the primary value proposition.
* **Key Features:** Dashboard (Available/Claimed/Done), Claim/Complete actions.

### **Milestone 3: Accountability & Polish**
* **Goal:** Reduce friction and increase transparency.
* **Key Features:** Activity Logs, Undo (Snackbar), Archive functionality.
