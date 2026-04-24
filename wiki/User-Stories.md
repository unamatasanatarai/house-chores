# **User Stories: ChoreLoop**

## 1. Overview of Epics
* **Epic 1: Identity & Persistence:** Establishing who the user is and ensuring the device "remembers" them.
* **Epic 2: Chore Lifecycle Management:** The core CRUD and state transition engine (Create, Claim, Complete).
* **Epic 3: Dashboard & Visibility:** Organizing tasks by priority and ownership.
* **Epic 4: Soft Accountability & History:** Tracking "who did what".
* **Epic 5: Safety & Reliability:** Error handling and recovery.

---

## 2. User Stories

### **Epic 1: Identity & Persistence**

#### **Story 1.1: Initial Identity Selection**
As a new user, I want to select or create my name upon first launch, so that my actions are correctly attributed to me.
* **Acceptance Criteria:** Onboarding screen forces selection; UUID stored locally; Redirect to Dashboard.

#### **Story 1.2: Persistent Identity Visibility**
As a user, I want to see my name in the app header at all times, so I know which profile I am currently using.
* **Acceptance Criteria:** TopAppBar displays "Acting as: [User Name]".

---

### **Epic 2: Chore Lifecycle Management**

#### **Story 2.1: Create a New Chore**
As a household member, I want to create a chore with a title and due date, so that others know what needs to be done.
* **Acceptance Criteria:** Input Title (Required); Auto-assign `created_by`; Status defaults to `available`.

#### **Story 2.2: Claim an Available Chore**
As a contributor, I want to claim an available chore, so that the household knows I am taking responsibility for it.
* **Acceptance Criteria:** Click "Claim" on `available` chore; Move to "Claimed by Me" section.

#### **Story 2.3: Mark Chore as Complete**
As the chore owner, I want to mark my claimed chore as complete, so that the household knows the task is finished.
* **Acceptance Criteria:** Only claimant can click "Complete"; Move to "Completed" section.

---

### **Epic 3: Dashboard & Visibility**

#### **Story 3.1: Intelligent Task Sorting**
As a user, I want chores to be sorted by urgency, so I can prioritize the most important tasks.
* **Acceptance Criteria:** Overdue chores at top; Sorted by `due_date` ASC; 4 sections displayed.

---

### **Epic 4: Soft Accountability**

#### **Story 4.1: Take Over a Chore**
As a user, I want to take over a chore claimed by someone else, so I can finish it if they are unable to.
* **Acceptance Criteria:** Confirmation dialog appears; Reassign ownership; Log action.

#### **Story 4.2: View Chore Activity Log**
As a user, I want to see the history of a chore, so I can understand its progress.
* **Acceptance Criteria:** Detail view shows last 10 actions with user name and relative timestamp.

---

### **Epic 5: Safety & Reliability**

#### **Story 5.1: Real-time Consistency**
As a user, I want my dashboard to stay updated, so I don't try to claim a chore someone else just took.
* **Acceptance Criteria:** Refetch after local action; Periodic polling.

#### **Story 5.2: Graceful Conflict Resolution**
As a user, I want to be notified if my action fails due to a conflict.
* **Acceptance Criteria:** Display `409 Conflict` error; Refresh UI automatically.
