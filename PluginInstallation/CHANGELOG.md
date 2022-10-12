# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.3.4](https://github.com/packlink-dev/magento2_module/compare/v1.3.3...1.3.4)
### Changed
- Fixed Zip Archive opening for PHP8.1 and updated to the latest Core version regarding new webhook event

## [1.3.3](https://github.com/packlink-dev/magento2_module/compare/v1.3.2...1.3.3)
### Added
- Added carrier logos for Colis Prive and Shop2Shop shipping services.

## [1.3.2](https://github.com/packlink-dev/magento2_module/compare/v1.3.1...1.3.2)
### Changed
- Updated to the latest Core changes regarding changing the value of the marketing calls flag.

## [1.3.1](https://github.com/packlink-dev/magento2_module/compare/v1.3.0...1.3.1)
### Changed
- Updated to the new shipping statuses and sending custom shipment reference to the Packlink API.

## [1.3.0](https://github.com/packlink-dev/magento2_module/compare/v1.2.0...1.3.0)
### Changed
- Updated to the module white-label changes.
- Updated to the multi-currency changes.

## [1.2.0](https://github.com/packlink-dev/magento2_module/compare/v1.1.7...1.2.0)
### Changed
- Applied the new module design.
- Changed pricing policies management.

## [1.1.7](https://github.com/packlink-dev/magento2_module/compare/v1.1.6...v1.1.7)
### Changed
- Fix translations in admin section and storefront.

## [1.1.6](https://github.com/packlink-dev/magento2_module/compare/v1.1.5...v1.1.6)
### Added
- Compatibility with Magento 2.4.

## [1.1.5](https://github.com/packlink-dev/magento2_module/compare/v1.1.4...v1.1.5)
### Added
- Add task runner wakeup on the order overview page.
- Add third address line when creating order shipment draft.

## [1.1.4](https://github.com/packlink-dev/magento2_module/compare/v1.1.3...v1.1.4)
### Added
- Added Hungary to the list of supported countries.

## [1.1.3](https://github.com/packlink-dev/magento2_module/compare/v1.1.2...v1.1.3)
### Added
- Added "Send with Packlink" button on order overview page. 

### Changed
- Fix event name for sales order event. 

## [1.1.2](https://github.com/packlink-dev/magento2_module/compare/v1.1.1...v1.1.2)
### Changed
- Fix issue with parcel price calculation.

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
