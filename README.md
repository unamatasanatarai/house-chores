# Family Chores

A lightweight, self-hosted task coordination system for households. Built on a **Flat Trust Model** — no accounts, no roles, no friction. Any family member can create, claim, and complete chores from a shared device or their own phone.

---

## Overview

Family Chores is a full-stack project comprising a **RESTful PHP/MySQL backend** and a companion **Android mobile app** (Kotlin + Jetpack Compose). The backend acts as the Single Source of Truth (SSOT), enforcing a *First to the Server Wins* conflict resolution strategy so that chore ownership is always consistent across devices.

The project was designed with real-world engineering concerns in mind:

- Token-based API authentication on every request
- Atomic conflict detection for concurrent claim attempts (HTTP 409)
- Soft deletes for auditability and data recovery
- Input validation with structured error responses at every endpoint
- Isolated, DB-verifying integration tests per domain

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend API | PHP 8.2 (Vanilla, no framework) |
| Database | MySQL 8.0 |
| Containerisation | Docker + Docker Compose |
| Android client | Kotlin, Jetpack Compose, MVVM, DataStore |
| Testing | Bash integration tests with live DB assertions |

---

## Architecture

```
┌─────────────────────────────────────────┐
│         Android App / Web App           │
└────────────────────┬────────────────────┘
                     │  HTTPS  X-CHORES-TOKEN
                     ▼
┌─────────────────────────────────────────┐
│             PHP REST API                │
│  family.php · users.php                 │
│  chores.php · action.php                │
└────────────────────┬────────────────────┘
                     │  PDO
                     ▼
┌─────────────────────────────────────────┐
│           MySQL 8.0 Database            │
│  families · users · chores              │
└─────────────────────────────────────────┘
```

---

## API Reference

All endpoints require the `X-CHORES-TOKEN` header. All request and response bodies are JSON.

### Family

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/family.php` | Fetch the existing family record |
| `POST` | `/api/family.php` | Create the family (single-family deployment) |

### Users

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/users.php?family_id=<uuid>` | List all members of a family |
| `POST` | `/api/users.php` | Add a new member (name-only, no password) |
| `DELETE` | `/api/users.php` | Remove a member (blocked if they have active claimed chores) |

### Chores

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/chores.php` | List all active chores (`deleted_at IS NULL`) |
| `POST` | `/api/chores.php` | Create a chore (`family_id`, `title`, `due_date` required) |

### Actions

| Method | Endpoint | Body | Description |
|---|---|---|---|
| `POST` | `/api/action.php` | `{"action":"claim","chore_id":"…","user_id":"…"}` | Claim an available chore |
| `POST` | `/api/action.php` | `{"action":"complete","chore_id":"…"}` | Mark a claimed chore as done |
| `POST` | `/api/action.php` | `{"action":"delete","chore_id":"…"}` | Soft-delete a chore |

**Conflict handling:** Claiming an already-claimed chore returns `409 Conflict`:
```json
{ "error": "Oops! Someone claimed this chore just before you." }
```

---

## Database Schema

```sql
families  (id PK, family_name, created_at)
users     (id PK, family_id FK, name, updated_at)
chores    (id PK, family_id FK, title, description,
           status, assigned_to FK, due_date,
           deleted_at, updated_at)
```

- UUIDs for all primary keys
- `deleted_at` enables soft deletes and future recovery UI
- All foreign keys enforced at the DB level (InnoDB)

---

## Getting Started

**Prerequisites:** Docker and Docker Compose installed.

```bash
# 1. Clone the repository
git clone <repo-url>
cd chores

# 2. Build images, start containers, and run migrations
make setup

# 3. Verify everything is running
make status
```

The API is now available at **`http://localhost:8080/api/`**.

### Essential commands

```bash
make help       # List all available commands with descriptions
make setup      # Build images, start containers, run migrations — full bootstrap
make destroy    # Tear down containers, networks, volumes, and data — clean slate
```

### Also useful

```bash
make test       # Run the full integration test suite
make migrate    # Re-run DB migrations inside the running container
make logs       # Stream live container logs
make db-shell   # Open an interactive MySQL monitor session
```

---

## Testing

Tests are split into one file per domain. Each suite makes real HTTP calls and then queries the database directly to verify the data was persisted correctly.

```
tests/
├── lib.sh           # Shared helpers: pass/fail, HTTP parsing, DB assertions
├── test_family.sh   # Family creation, duplicate guard, fetch
├── test_users.sh    # Add, list, duplicate guard, delete (with DB checks)
├── test_chores.sh   # Create, list, validation (with DB checks)
├── test_action.sh   # Claim, conflict (409), complete, soft-delete (with DB checks)
└── api.sh           # Orchestrator — runs all suites and aggregates results
```

```bash
make test
```

Each suite can also be run in isolation:

```bash
bash tests/test_family.sh
bash tests/test_users.sh
```

---

## Design Decisions

**Flat Trust Model** — No roles or permissions. Every household member has full CRUD access. Security is physical: the device is in the home.

**Server as SSOT** — The app does not cache or queue writes offline. Every action is an immediate API call. This eliminates sync conflicts by design.

**Soft Deletes** — Chores are never hard-deleted via the API. `deleted_at` is stamped instead, keeping a full audit trail and enabling future recovery features.

**Validation before the DB** — Every endpoint validates and sanitises inputs (required fields, UUID format, length limits, date format) and returns a structured `400` before any SQL is executed, preventing cryptic 500 errors from surfacing to clients.

**Conflict Resolution** — Claim actions check `assigned_to` inside a single round-trip. The first writer wins; subsequent attempts receive `409 Conflict` with a user-friendly message.

---

## Project Structure

```
chores/
├── public/
│   ├── api/
│   │   ├── config.php      # DB connection + token auth middleware
│   │   ├── family.php      # Family management endpoint
│   │   ├── users.php       # User management endpoint
│   │   ├── chores.php      # Chore CRUD endpoint
│   │   ├── action.php      # Chore action endpoint (claim/complete/delete)
│   │   └── migrate.php     # Schema migration runner
│   └── index.html          # Web client (Vanilla JS SPA)
├── scripts/
│   └── 001-init.sql        # Initial schema
├── tests/
│   ├── lib.sh
│   ├── test_family.sh
│   ├── test_users.sh
│   ├── test_chores.sh
│   ├── test_action.sh
│   └── api.sh
├── product-design-documentation/
│   ├── 1. HLD.md
│   ├── 2. PM.md
│   ├── 3. TPM.md
│   └── 4. tasks.md
├── Dockerfile
├── docker-compose.yml
└── Makefile
```

---

## Configuration

The API token and database credentials are set in `public/api/config.php`:

```php
define('API_TOKEN', 'YOUR_HARDCODED_TOKEN_HERE');  // shared secret for all clients
define('DB_HOST',   'db');
define('DB_NAME',   'family_chores');
define('DB_USER',   'root');
define('DB_PASS',   'root_password');
```

> **Note:** For a private household deployment, the hardcoded token is an accepted trade-off. The primary security boundary is physical access to the home network.

---

## Local Development

The full environment runs in Docker — no local PHP or MySQL installation needed.

```bash
make setup      # First-time setup: build → start → migrate

# Edit files under public/api/ — changes are live immediately via volume mount.

make test       # Verify nothing is broken after changes
make db-shell   # Inspect DB state directly if a test behaves unexpectedly
make logs       # Watch PHP/Apache errors in real time
```

To reset the database to a clean state without rebuilding images:

```bash
make destroy && make setup
```

---

## License

This project is licensed under the **GNU General Public License v2.0**.
See the [LICENSE](LICENSE) file for the full text.

---
<div align="center">
  <p>Built with a 🧛.</p>
</div>
