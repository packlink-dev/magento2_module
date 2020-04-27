# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.1.1](https://github.com/packlink-dev/magento2_module/compare/v1.1.0...v1.1.1)
### Changed
- Enable multi-store support in such a way that the same carriers are displayed across all stores, not just in default one.
- Fix package cost calculation
- Sever-side validation when drop-off is selected before placing the order.

## [1.1.0](https://github.com/packlink-dev/magento2_module/compare/v1.0.1...v1.1.0)
### Changed
- Updated to the latest Packlink Integration Core v2.0.0. The most important changes:
  * Update interval for shipment details is optimized. Now, the data for active shipments will be updated once a day.
  * Optimized code
  * Added a background task for cleaning up the task runner queue for completed tasks.
- Fixed problem with bulk label print on some servers
- Display better progress status for creating a draft shipment

### Added
- Added CSRF checks for webhooks endpoints
- Added more supported countries for Packlink accounts and shipments.

## [1.0.1](https://github.com/packlink-dev/magento2_module/compare/v1.0.0...v1.0.1)
### Changed
- Shipment data update interval.
- Fixed bugs in base repository discovered by the new test suite. 

### Added
- Option to select destination countries for shipping service.
- Lower bound greater than zero for price and weight range pricing policy.

## [1.0.0](https://github.com/packlink-dev/magento2_module/compare/v1.0.0...v1.0.0-BETA-1)
### Added
- JSON serializer

### Changed
- Updated to latest CORE

## [1.0.0-BETA-1](https://github.com/packlink-dev/magento2_module/compare/v1.0.0-BETA-1...v1.0.0-BETA)
### Added
- Implemented CR-12: Auto-test and auto-configuration
- Updated to latest core with some bugfixes

## [1.0.0-BETA](https://github.com/packlink-dev/magento2_module/compare/v1.0.0-BETA...dev)
- First release of module.
