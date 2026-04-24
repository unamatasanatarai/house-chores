# **High-Level Design (HLD): ChoreLoop**

**Status:** Implementation-Ready \
**Date:** April 24, 2026 \
**Platform:** Web (Vanilla JS) + Android (Jetpack Compose) \
**Backend:** PHP (RESTful)

---

## 1. Product Overview

**ChoreLoop** is a streamlined, collaborative task-management application designed for a single household. It operates on a **Flat Trust Model**, where all users have equal permissions, combined with **Soft Accountability Mechanisms** to ensure transparency and responsibility without introducing hierarchy.

### **Core Principles**

* No roles, no permissions, no barriers
* Accountability through visibility, not restriction
* Designed for real-world behavior, not idealized cooperation

### **Value Proposition**

Effortless coordination via a **Claim → Own → Complete loop**, reinforced by transparency.

### **Target Users**

Members of a shared household (adults, children, mixed).

---

## 2. Scope

### **In-Scope (MVP)**

#### Core Features

* User creation (name-based)
* Persistent identity per device
* Full shared CRUD access to chores
* Claim / Unclaim / Take Over
* Chore lifecycle tracking
* Soft accountability (attribution + activity log)
* Archive instead of delete
* Structured dashboard
* Always-online (server as SSOT)

#### UX & Safety

* Confirmation dialogs
* Undo (snackbar)
* Active user visibility

---

## 3. Users & Identity

### **Flat Identity Model**

* Single role: User
* No admin privileges

### **Identity Handling**

* Selected on first launch
* Stored locally (Web: localStorage, Android: DataStore)
* Required before usage

### **Session Visibility**

Persistent UI indicator:

```
Acting as: [User Name]
```

---

## 4. Functional Requirements

### **4.1 Chore Lifecycle**

#### **States**

* Available
* Claimed
* Completed
* Overdue
* Archived

#### **Core Actions**

##### Create
* Fields: title, description, due_date
* Auto: `created_by`, `created_at`

##### Claim
* Assigns user
* Sets: `claimed_by`, `claimed_at`

##### Take Over
* Confirmation required
* Reassigns ownership

##### Unclaim
* Only current owner

##### Complete
* Only current owner
* Sets: `completed_by`, `completed_at`

##### Archive
* Any user
* Undo supported
* Sets: `archived_by`, `archived_at`

### **4.2 Soft Accountability**

Each chore shows:
* Created by
* Claimed by
* Completed by
* Archived by

#### **Activity Log (Per Chore)**
* Last N events (default: 10)
* Actions: created, claimed, unclaimed, completed, archived

### **4.3 Dashboard**

Sections:
1. Available
2. Claimed by Me
3. Claimed by Others
4. Completed (Recent)

#### Sorting
1. Status == 'Claimed' AND Overdue == True
2. Status == 'Available' AND Overdue == True
3. Status == 'Claimed'
4. Status == 'Available'
* sorted by due_date ASC

---

## 5. Web App Architecture

* **Approach:** Component-based state rendering, ES6 modules
* **Stack:** HTML5, CSS (Grid/Flexbox), Vanilla JS, Fetch API
* **State:** Central Store, localStorage for identity
* **Data Strategy:** Always-online, Polling / refetch

---

## 6. Android App Architecture

* **Pattern:** MVVM + Clean Architecture, Jetpack Compose
* **Data Strategy:** Server = Single Source of Truth, Always-online required, No offline writes, No eventual consistency
* **Local Storage:** Room DB = cache only (non-authoritative)
* **Sync:** On app start, On user action, Manual refresh

---

## 7. Backend & API Design

* **Principles:** RESTful, Stateless, JSON-based, Server = SSOT
* **Authentication (MVP):** `Authorization: Bearer [TOKEN]`

### **API Endpoints**

#### Users
* `POST /api/users/add`
* `GET /api/users`

#### Chores
* `GET /api/chores`
* `POST /api/chores/add`
* `PUT /api/chores/{id}/claim`
* `PUT /api/chores/{id}/unclaim`
* `PUT /api/chores/{id}/done`
* `PUT /api/chores/{id}/archive`
* `PUT /api/chores/{id}/unarchive`

#### Activity Logs
* `GET /api/logs?chore_id=UUID`
* `GET /api/logs?user_id=UUID`

---

## 8. Data Model

### Users
* id (UUID)
* name

### Chores
* id, title, description, status, due_date
* created_by, claimed_by, completed_by, archived_by
* created_at, claimed_at, completed_at, archived_at

### Activity Logs
* id, chore_id, user_id, action, metadata, created_at

---

## 9. Data Management

* **Connectivity:** Always-online required
* **Conflict Resolution:** First write wins, Server validates state
* **Client Handling:** Conflict → error message, No retry queue

---

## 10. Key Flows

### Identity
Select → Persist → Load app

### Chore Loop
Create → Claim → Complete → Archive

---

## 11. Risks & Mitigations

| Risk | Mitigation |
| :--- | :--- |
| Token exposure | Accepted (MVP) |
| Misuse | Visibility |
| Accidental actions | Undo |
| Stale data | Refetch |

---

## 12. MVP Release Plan

1. Backend Infrastructure
2. Identity Selection
3. Dashboard UI
4. Core lifecycle actions
5. Ownership logic
6. Activity logs
7. Archive/Undo
8. Polish & Testing
