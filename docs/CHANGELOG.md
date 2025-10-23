# Changelog
All notable changes to this project will be documented in this file.

This format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
-

### Changed
-

### Fixed
-

### Deprecated
-

### Removed
-

### Security
-

### Breaking Changes
-

---

## [v0.4.3] - 2025-10-22
### Added
- Stable Woo export/sync baseline (parents + variations working).

### Changed
- Parent/variation HTTP pipeline consolidated in `ProductUpsertService` (`updateExisting()`/`createNew()` with normalized responses).

### Fixed
- Name resolver uses DB `products.product_name` (no placeholder titles).
- Only-changed guard uses `products.last_synced_at` timestamp.
- Selection whitelist + `refresh()` prevent unintended updates.

### Security
- None.

### Breaking Changes
- None.

---

## [v0.4.2] - 2025-10-17
### Changed
- Documentation cleanup (removed obsolete docs).

---

## [v0.4.1] - 2025-10-??
- Minor improvements.

## [v0.4.0] - 2025-10-??
- Initial 0.4 release.

[Unreleased]: https://github.com/jaderbass/wc-ga-api/compare/v0.4.3...HEAD
[v0.4.3]: https://github.com/jaderbass/wc-ga-api/compare/v0.4.2...v0.4.3
[v0.4.2]: https://github.com/jaderbass/wc-ga-api/compare/v0.4.1...v0.4.2
[v0.4.1]: https://github.com/jaderbass/wc-ga-api/compare/v0.4.0...v0.4.1
[v0.4.0]: https://github.com/jaderbass/wc-ga-api/releases/tag/v0.4.0
