# 🔄 ChoreLoop

> **Shared household coordination, simplified.**

ChoreLoop is a production-grade, shared-device task management system designed for families and shared households. Unlike traditional task managers, ChoreLoop operates on a **Flat Trust Model**, optimizing for speed, transparency, and the "Always-Online" nature of a tablet mounted on a fridge.

![ChoreLoop Dashboard Mockup](public/mockup.png)

## ✨ Key Features

- **Flat Trust Architecture:** No passwords, no hierarchy. Anyone in the household can join, claim chores, and help out.
- **Atomic State Machine:** Robust backend logic prevents race conditions (409 Conflict) when two family members try to claim the same task simultaneously.
- **Real-time Accountability:** Automated activity logging tracks every state transition (Created → Claimed → Done → Archived).
- **Premium Experience:** A high-fidelity, responsive Vanilla JS frontend with glassmorphic aesthetics and micro-animations.
- **Resilient UX:** 
    - **Undo Mechanism:** 5-second safety window for destructive actions.
    - **Offline Guard:** Automatic reconnection logic and connection-lost overlays.
    - **Background Sync:** Real-time dashboard polling to stay in sync with other family members.

## 🛠️ Tech Stack

### Backend
- **Core:** PHP 8.2 (Pure RESTful API)
- **Database:** MySQL 8.0 (InnoDB)
- **Infrastructure:** Docker & Docker-Compose
- **Testing:** PHPUnit 10.x (Integration & Concurrency Suites)
- **Utilities:** Custom PDO Singleton & Standardized JSON Response Engine

### Frontend
- **Logic:** Vanilla JavaScript (ES6 Modules)
- **State Management:** Simple Reactive Store (No-Framework)
- **Styling:** Modern Vanilla CSS (Design Tokens & Glassmorphism)
- **Design:** Typography by Google Fonts (Outfit & Inter)

## 🚀 Quick Start

The project is fully containerized and controlled via a one-stop-shop `Makefile`.

### 1. Requirements
- Docker & Docker Compose
- Make

### 2. Setup & Launch
```bash
make setup
```
*This will build the images, spin up containers, install dependencies, and run migrations.*

### 3. Usage
- **Web App:** [http://localhost:8080](http://localhost:8080)
- **API Root:** [http://localhost:8080/api/](http://localhost:8080/api/)

### 4. Other Commands
| Command | Description |
|---------|-------------|
| `make test` | Run all automated PHPUnit tests |
| `make status` | Check container health |
| `make logs` | Stream logs for debugging |
| `make destroy` | Wipe the environment completely |

## 🧪 Testing Strategy

ChoreLoop prioritizes data integrity. The test suite includes:
- **Integration Tests:** Verifying full request-response cycles and middleware auth.
- **State Transition Tests:** Ensuring chores cannot bypass status logic (e.g., claiming a completed chore).
- **Conflict Tests:** Simulating concurrent writes to verify the Atomic State Machine logic.

---

**Built with a 🧛 for better household harmony.**
