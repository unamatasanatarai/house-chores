# **Testing Strategy: ChoreLoop**

**Status:** Implementation-Ready \
**Focus:** State Machine Integrity & Concurrency Validation

## **1. Testing Philosophy**
ChoreLoop relies on a **Flat Trust Model** and **Always-Online** connectivity. Testing is prioritized around the **Chore Lifecycle** to ensure that state drift never occurs.

---

## **2. Test Levels & Tooling**

### **2.1 Backend Unit & Integration (PHPUnit)**
* **State Machine Validation:** Ensure transitions follow HLD logic.
* **Identity Middleware:** Verify Bearer token requirement.

### **2.2 Android UI & Logic (JUnit / Espresso)**
* **ViewModel Polling:** Test refetch triggers.
* **DataStore Persistence:** Verify identity survives restarts.

### **2.3 Web Component Testing (Vitest / Cypress)**
* **Undo Logic:** Verify the 5-second Snackbar window.

---

## **3. Critical "High-Signal" Test Cases**

| ID | Scenario | Expected Outcome |
| :--- | :--- | :--- |
| **TC-01** | **Race Condition** | Server rejects second simultaneous claim with `409`. |
| **TC-02** | **Unauthorized Takeover** | Reject ownership changes from non-owners where required. |
| **TC-03** | **Identity Loss** | Force re-entry to selection screen if local storage is clear. |
| **TC-04** | **Polling Sync** | Web updates reflect on Android within one cycle. |

---

## **4. Performance & Reliability Testing**
* **Connectivity Stress:** Verify offline overlay appears via throttling.
* **Activity Log Load:** Ensure performant history retrieval.
