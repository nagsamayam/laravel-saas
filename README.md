# System Specification: Production-Grade Multi-Tenant Laravel Application (Schema-Isolated)

## 1. System Overview & Tech Stack
Act as an expert Software Architect and Principal Backend Engineer. **Generate a production-ready, highly secure, scalable, and maintainable blueprint** alongside implementation code for a multi-tenant backend system adhering to these exact modern standards:

*   **Runtime & Framework:** **PHP 8.4** (leveraging native property hooks, asymmetric visibility, typed constants, and strict typing) and **Laravel 13**.
*   **Database:** **PostgreSQL 18** configured with a Primary (Write) and Read Replica architecture.
*   **Caching & Queues:** **Redis (latest)** for caching and **RabbitMQ (latest)** as the asynchronous job/queue broker.
*   **Web Server:** **Nginx (latest)** configured initially as a web server, built to scale cleanly into a reverse proxy and load balancer.
*   **Environment & Testing:** Orchestrated via Multi-Stage Docker builds, with a test suite written entirely in **Pest PHP (latest)**.
*   **CI/CD Pipeline:** Fully automated validation, building, and deployment using **GitHub Actions**, publishing images to the **GitHub Container Registry (GHCR)**.
*   **PgBouncer:** All Laravel database configuration arrays must target the **PgBouncer** ports rather than the databases directly, utilizing **Transaction Pooling mode** for optimal performance.

---

## 2. Core Architectural & Database Design

### 2.1 Multi-Tenancy Architecture & Dynamic Routing
*   **Isolation Strategy:** **Schema-per-tenant** isolation within a single PostgreSQL database instance (No Row-Level Security / RLS).
*   **Dynamic Data Source Routing:** 
    *   Tenant schema context switching must use raw SQL execution: `SET LOCAL search_path TO <schema_name>, public;`.
    *   Routing **must** occur inside short-lived, explicit database transactions to guarantee strict data boundary protection.
    *   The `search_path` must be safely reverted using `RESET search_path;` or via transaction termination (`COMMIT`/`ROLLBACK`).

### 2.2 Advanced Database Replication & Replication Routing
The Laravel database configuration must explicitly handle separate connections for high-performance scale and data integrity:
1.  **Write Traffic Routing:** All structural modifications, updates, and insertions (INSERT, UPDATE, DELETE) must be routed directly to the Primary Database Instance.
2.  **Read-After-Write Consistency:** Implement an automated mechanism or specific middleware context mapping ensuring that if a record was mutated during the current request lifecycle, subsequent reads for that specific context during the same request bypass the replica and hit the **Primary Database Instance** to prevent reading stale, lag-delayed data.
3.   **Standard Read Traffic Routing:** Standard query operations (SELECT) without matching mutations during the execution thread must be cleanly distributed to the **Read Replica**.
4.   **PgBouncer Integration:** All Laravel database configuration arrays must target the **PgBouncer** ports rather than the databases directly, utilizing **Transaction Pooling** mode for optimal performance.

### 2.3 Extensible Migrations Pipeline
Two strictly segregated migration directories must exist. Both must be designed with forward-compatibility to allow new columns to be smoothly appended in future schema updates without breaking existing definitions:
1.  **Platform Migrations:** Run globally against the default `public` schema.
2.  **Tenant Migrations:** Isolated directory executed programmatically and sequentially across individual tenant schemas.
*   **Automation:** When a tenant's status transitions to `Approved`, the tenant-specific migrations must run automatically via the async provisioning pipeline.
*   **Timestamps:** All database timestamps across both layers must be **zoned/aware** (`timestampTz`).

### 2.4 Comprehensive Schema Structure
#### Platform Database Tables
*   **`tenants`:** `id` (UUID), `name`, `schema_name`, `status` (Enum: `Pending`, `Approved`, `Provisioning`, `Ready`, `ProvisioningFailed`, `Suspended`, `Deleted`), audit fields.
*   **`users`:** `id` (UUID), `email`, `password_hash`, audit fields.
*   **`tenant_memberships`:** Links `users` to `tenants` with assigned contextual roles.
*  **`outbox_messages`:**
*  **`refresh_tokens`:**
*   **Platform RBAC Tables:** `roles`, `permissions`, `role_permissions`, `membership_roles`, `platform_user_roles` for global system management.

#### Phase 1 Tenant Database Tables
*   **Tenant RBAC Tables:** Tenant-specific local `roles`, `permissions`, and assignment mappings to control granular per-tenant operations.
*   **Domain Data:** `categories` and `products` tables localized exclusively inside each tenant's schema.

### 2.5 Idempotent Onboarding (Outbox Pattern)
*   **Idempotency:** The tenant provisioning pipeline must be entirely idempotent and retryable.
*   **Transactional Outbox:** When a tenant moves to `Approved`, an onboarding entry is written to a `transactional_outboxes` table within the same platform DB transaction.
*   **Worker processing:** A background worker processes the outbox entry, dispatches a RabbitMQ job to handle asynchronous provisioning (schema creation, running tenant migrations), and transitions the status to `Ready` or `ProvisioningFailed`.

---

## 3. Global System Features & Cross-Cutting Concerns

### 3.1 REST API Architecture, Versioning & Rate Limiting
*   **Structure:** Implement a strictly versioned REST API pattern using URL-based versioning segments (e.g., `/api/v1/...`).
*   **Separation of Concerns:** Design clean separations between routes, controllers, form requests, and data transformers (API resources) under versioned namespaces (e.g., `App\Http\Controllers\Api\V1\Tenant\ProductController`).
*   **Global Response Pattern:** Enforce a uniform JSON structure for all API outputs (success, error validation collection, and pagination metadata).
*   **API Rate Limiting:** Protect all endpoints using Redis-backed rate-limiting throttlers configured dynamically by traffic categorization (e.g., standard public lookups, sensitive authentication/token-refresh targets, tenant administrative mutation endpoints). Limit responses must attach proper `X-RateLimit-*` headers and drop traffic cleanly upon limit exhaustion.

### 3.2 Standard Error Response Payload Structure
The application's global exception handler must format all client errors into a rigid, reliable JSON object container. Standard responses must omit internal backend trace vectors in production environments and include a custom internal machine code string (`code` property):

#### Structure A: Collection/Validation Field Mappings
```json
{
    "message": "Validation failed.",
    "code": "VALIDATION_ERROR",
    "errors": {
        "name": [
            "The name field is required."
        ]
    }
}
```

#### Structure B: Generic/Domain Error Messages
```json
{
    "message": "Resource was not found.",
    "code": "RESOURCE_NOT_FOUND"
}
```

### 3.3 HTTP Status Code Mapping Matrix
The application must strictly map business domain scenarios to standard HTTP status codes:

| Scenario | HTTP Status Code | Response Code String Template |
| :--- | :---: | :--- |
| **Missing / Invalid JWT Context** | `401` | `UNAUTHORIZED` |
| **Missing Tenant Headers / Extraction Context** | `400` | `MISSING_TENANT_CONTEXT` |
| **Tenant Reference Not Found** | `404` | `TENANT_NOT_FOUND` |
| **Tenant Suspended / Inactive State** | `403` | `TENANT_INACTIVE` |
| **User lacks active membership within requested Tenant** | `403` | `FORBIDDEN_MEMBERSHIP` |
| **Domain Resource Not Found** (Product, Category, etc.) | `404` | `RESOURCE_NOT_FOUND` |
| **Validation Constraint Failure** | `422` | `VALIDATION_ERROR` |
| **Duplicate Active Slug Conflict** | `409` | `DUPLICATE_SLUG` |
| **Optimistic Locking Version Conflict (OCC)** | `409` | `CONCURRENCY_CONFLICT` |
| **Business Constraint Protection** (e.g., Category contains active items) | `409` | `DEPENDENCY_CONFLICT` |
| **Resource Successfully Created** | `201` | *(Standard Data Payload)* |
| **Resource Successfully Mutated / Removed** | `204` | *(No Content Payload)* |

### 3.4 Security & Advanced JWT Architecture
*   **Authentication Mechanism:** **Asymmetric JWT** (RS256 or EdDSA) utilizing Public/Private key pairs.
*   **Claims Validation:** The JWT must include and strictly enforce the `nbr` (**Not Before, Immutable**) claim.
*   **Immutable Claims Validation:**
    *   The JWT payload must include and strictly enforce the nbr **(Not Before, Immutable)** claim.
    *   The JWT payload **must include the tenant_id as an immutable claim** once authenticated to lock the tenant context into the cryptographically signed token.
*   **Token Refresh Lifecycle:**
    *   Requires a rotating refresh token strategy.
    *   Refresh tokens must be **opaque strings** generated securely, but stored in the database exclusively as cryptographic **hashes** (e.g., SHA-256).
    *   Using a previously used/compromised refresh token must trigger an immediate revocation cascade of all active tokens for that user session (Reuse Detection).

### 3.5 Granular RBAC (Role-Based Access Control)
*   **Platform Roles:** `SUPER_ADMIN`, `PLATFORM_ADMIN`.
*   **Tenant Roles:** `TENANT_OWNER`, `TENANT_ADMIN`, `TENANT_MEMBER`.
*   The authorization middleware must evaluate both global platform permissions and contextual tenant-level permissions dynamically based on the active, routed schema.

### 3.6 Concurrency, Auditing, & Health Monitoring
*   **Optimistic Concurrency Control (OCC):** Every domain table must include an integer `row_version` column initialized at `0`. Update operations must explicitly verify `WHERE row_version = ?` and increment it by `1`. Throw a `ConcurrencyException` on version mismatch.
*   **Advanced Soft Deletes:** Standard tables must track complete deletion lifecycle state and user ownership:
    *   `deleted_at`, `deleted_by` (UUID)
    *   `restored_at`, `restored_by` (UUID)
*   **Asynchronous & System Blame Handling (Virtual Actor Pattern):**
    *   Audit fields must decouple direct dependencies from HTTP sessions (Auth::id()) by resolving via a centralized **Blame Context Manager**.
    *   **Asynchronous Background Jobs (RabbitMQ):** When a job is triggered on behalf of a human user, the originating user's UUID must be passed in the queue payload to seamlessly rehydrate their audit identity within the worker execution context.
    *   **Purely System-Driven Tasks:** For fully automated hooks, scheduled tasks, or infrastructure triggers lacking human interaction, audit tracking must automatically fall back to attributing changes to the hardcoded **System/Virtual Actor UUID** (00000000-0000-0000-0000-000000000000).

*   **Audit Logging:** Global database hooks/events must track and log mutations to a structured audit logging repository.
*   **System Health Endpoint:** A dedicated public/private `/api/health` route that monitors and provides real-time status of:
    *   Application Runtime (PHP-FPM/Laravel status)
    *   Database connection (Primary & Read Replica responsiveness)
    *   Redis connection availability
    *   RabbitMQ connection and queue readiness

---

## 4. Containerization & Pipeline Strategy

### 4.1 Multi-Stage Docker & Nginx Deployment Configuration
*   **GitHub Container Registry (GHCR):** Prepare multi-stage production Dockerfiles to build, tag, and push images (`app` and `nginx`) directly to GHCR.
*   **Application Image:** Multi-stage image cutting out development dependencies (`composer install --no-dev`), containing optimized PHP 8.4 OPcache configuration.
*   **Docker Compose Configurations:**
    *   `docker-compose.yml` (Development): Uses local source code bind mounts, exposed debugging tools (Xdebug pre-configured), local service setups for Redis, RabbitMQ, and Postgres 18 with an overlaying PgBouncer pooling container.
    *   `docker-compose.prod.yml` (Production): Pulls pre-built immutable images directly from GHCR, locks down write privileges on containers where possible, isolates services via secure private networks, routes through PgBouncer pools, and strips out debugging bridges.

### 4.2 Decoupled Integration & Deployment Workflow (GitHub Actions)
The automation lifecycle must be strictly divided into **three separate, sequential, and dependent GitHub Actions jobs**:

#### Task 1: Continuous Integration (CI Verification)
*   **Triggers:** Runs on all pull requests and commits to development/main branches.
*   **Services Matrix:** Spins up isolated parallel runner service containers: PostgreSQL 18, PgBouncer, Redis, and RabbitMQ.
*   **Code Quality Gate:** Executes static analysis via PHPStan/Larastan (Level 8+), code style checks, and security audits.
*   **Test Suite execution:** Runs the entire **Pest PHP** suite against the active services matrix. Must pass 100% to greenlight subsequent tasks.

#### Task 2: Production Image Build & Publish
*   **Triggers:** Executes **only** after Task 1 passes successfully, restricted to merges into target release branches (e.g., `main`, `staging`) or release tags.
*   **Image Compiling:** Utilizes multi-stage Docker builds using aggressive layer caching optimization (`type=gha`). Strips away development packages, setups optimized production extensions (OPcache, JIT), and compiles production-ready Nginx definitions.
*   **Registry Upload:** Authenticates and pushes the final immutable, production-tagged `app` and `nginx` images into the **GitHub Container Registry (GHCR)**.

#### Task 3: Continuous Deployment (CD Execution)
*   **Triggers:** Executes **only** after Task 2 completes successfully.
*   **Target Infrastructure:** Connects via a secure, audited pipeline (e.g., SSH with OpenID Connect / OIDC or secure runner agents) to the host infrastructure.
*   **Blue/Green Zero-Downtime Rollout:** Pulls the newly updated immutable images directly from GHCR into the production environment using `docker-compose.prod.yml`, switches execution environments with zero request drop, clears performance caches, and programmatically handles platform data migrations through PgBouncer connection routers.