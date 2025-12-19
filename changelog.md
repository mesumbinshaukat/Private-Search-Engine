# Changelog

## [2025-12-20 01:23:34] - System Stabilization & Refresh Cycle Implementation

### Fixed
- **Seeding Error**: Resolved `Call to undefined function Database\Factories\fake()` in production environments by removing the `fakerphp/faker` dependency from `DatabaseSeeder.php` and redirecting to the production-safe `CreateUserSeeder`.
- **Authentication**: Fixed "no such table: personal_access_tokens" error by publishing Sanctum migrations and ensuring they are run during setup.
- **Crawl Depth**: Fixed an issue where the crawler would stop after 25 jobs regardless of limits. The crawler now correctly reaches the `CRAWLER_MAX_CRAWLS_PER_CATEGORY` (e.g., 800) by allowing link discovery on previously known pages.

### Changed
- **Data Lifecycle**: Transformed the "Incremental Growth" model into a "True Refresh Cycle". `master:refresh` now resets daily counters, clears job logs, and updates existing records instead of skipping them.
- **Configurability**: Externalized all hardcoded system limits into environment variables. You can now tune Crawler depth, Indexer thresholds, Search scoring (BM25/Fuzziness), and System timeouts directly via `.env`.
- **Master Refresh Job**: Refined `MasterRefreshJob` to stop execution upon step failure, protecting the integrity of the search index and cache.

### Added
- **Sanctum Integration**: Published Sanctum configuration and migrations to enable full token-based API authentication.
- **Documentation**: Updated `README.md`, `DEPLOYMENT.md`, and `API.md` to reflect the new authentication setup, refresh commands, and environment variables.
- **Changelog**: Introduced this `changelog.md` to track project evolution.
