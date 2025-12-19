# RULES.md

## Non Negotiable System Rules

This document defines the architectural, data integrity, ethical, and operational rules that govern the private search engine system. These rules are absolute and cannot be violated.

## Architectural Rules

### AR1: Laravel as Orchestration Layer Only
Laravel serves exclusively as the orchestration and API layer. Business logic resides in dedicated service classes, not controllers or routes.

### AR2: APIs are the Primary Product
All functionality must be exposed through versioned REST APIs. The UI is a thin client that consumes these APIs only.

### AR3: Google Drive JSON is Source of Truth
The canonical data store is JSON files stored in Google Drive. Local caches are ephemeral and must be rebuildable from Google Drive at any time.

### AR4: No External Services Beyond Google Drive
The system may not depend on any external service except Google Drive for storage. All other functionality must be self contained.

### AR5: Service Account Authentication Only
Google Drive integration must use Service Account authentication exclusively. OAuth refresh tokens and client credentials are forbidden. This ensures fully automated operation without browser based authentication flows.

### AR6: Stateless Search
Search operations must not maintain state between requests. Each API call is independent and deterministic.

### AR7: Weighted Search Relevance
Search results must be scored based on relevance (Title weight > Description weight) and sorted by score. A match score (1-10) must be exposed via API and UI.

## Data Integrity Rules

### DI1: Five Categories Only
The system supports exactly five categories: Technology, Business, AI, Sports, Politics. This list is immutable.

### DI2: Five Day Maximum Age
Data older than 5 days must be automatically purged. No exceptions.

### DI3: Minimum Record Count Per Category
Each daily index must contain at least a minimum number of valid, live records per category (default: 5). If this threshold cannot be met, the system must log failure and not upload incomplete data.

### DI4: Canonical URL Deduplication
Duplicate detection is based on canonical URLs and content hashes. The same content must never appear twice in a single category index.

### DI5: Deterministic JSON Output
JSON files must be generated deterministically. Given the same input data, the output JSON must be byte identical.

### DI6: Metadata Headers Required
Every JSON file must include a metadata header with: generation timestamp, category, record count, schema version, and data validity period.

## Ethical Crawling Rules

### EC1: Respect robots.txt
All crawlers must parse and obey robots.txt directives for every domain. Disallowed paths must never be crawled.

### EC2: Enforce Crawl Delays
If robots.txt specifies a crawl delay, it must be honored. If no delay is specified, a minimum of 1 second between requests to the same domain is mandatory.

### EC3: Rate Limiting Per Domain
No more than 1 request per second per domain. Domains returning 429 status codes must be backed off exponentially.

### EC4: Identify as Bot
All HTTP requests must include a User Agent string that clearly identifies the system as a bot and provides contact information.

### EC5: Handle 429 and 503 Gracefully
Rate limit and service unavailable responses must trigger exponential backoff, not retries.

### EC6: Validate Content Type
Only HTML content types are valid. PDFs, images, videos, and other media must be rejected without processing.

## Performance Ceilings

### PC1: Maximum Concurrent Crawls
No more than 10 concurrent crawl jobs may run simultaneously to prevent resource exhaustion.

### PC2: Request Timeout
All HTTP requests must timeout after 10 seconds. Slow servers must be abandoned.

### PC3: Maximum Page Size
Pages larger than 5MB must be rejected without parsing.

### PC4: Queue Depth Limit
The crawl queue must not exceed 10,000 pending jobs. New jobs must be rejected if the queue is full.

### PC5: API Rate Limiting
Search API endpoints must enforce rate limiting at 60 requests per minute per IP address.

## Forbidden Shortcuts

### FS1: No Silent Failures
Failures must be logged explicitly. Silent error swallowing is forbidden.

### FS2: No Hardcoded URLs
Seed URLs and domain lists must be stored in configuration files or database, never hardcoded in source code.

### FS3: No Premature Optimization
Code must prioritize correctness and clarity over performance until profiling identifies actual bottlenecks.

### FS4: No Magic Numbers
All numeric constants must be defined as named constants with clear documentation.

### FS5: No Assumptions About External Data
All external data (HTML, JSON, API responses) must be validated before use. Never assume structure or presence of fields.

## Documentation Obligations

### DO1: Update PHASES.md on State Change
Every significant state transition (phase start, phase completion, failure) must be logged in PHASES.md immediately.

### DO2: Document All Edge Cases
Every handled edge case must be documented in code comments and relevant documentation files.

### DO3: API Changes Require API.md Update
Any change to API request/response schemas, endpoints, or behavior must be reflected in API.md before deployment.

### DO4: Deployment Changes Require DEPLOYMENT.md Update
Changes to environment variables, dependencies, or deployment procedures must be documented in DEPLOYMENT.md.

### DO5: README.md Reflects Current State
README.md must accurately describe the system as it exists, not as it is planned to be.

## Violation Response

If any rule is violated:
1. The violation must be logged with severity CRITICAL
2. The violating operation must be aborted
3. PHASES.md must be updated with failure details
4. The system must enter a safe state
5. Manual intervention is required to resume

## Rule Amendment Process

Rules may only be amended through explicit documentation updates approved by the system architect. Amendments must include:
1. Rule identifier
2. Original text
3. New text
4. Rationale for change
5. Impact assessment
