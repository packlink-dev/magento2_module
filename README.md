![Packlink logo](https://pro.packlink.es/public-assets/common/images/icons/packlink.svg)

# Packlink Magento 2 plugin - developer instructions

## Commit procedure
**The following steps must be completed before each commit.**

### Run unit tests
Configuration for phpunit is in the `./phpunit.xml` file.

If you haven't already done so, you can setup unit tests in PHPStorm.
To do so, first go to `File > Settings > Languages & Frameworks > PHP > Test Frameworks` and 
add new PHPUnit Local configuration. Select `Use composer autoloader` and in the field below navigate to your Magento 
installation folder and select `/vendor/autoload.php` file.

Go to `Run > Edit configuration` menu and add new PHPUnit configuration. 
For Test Runner options select `Defined in configuration file` and add specific phpunit configuration 
file path to the `./phpunit.xml` file in module's root directory.

Create new file `./PacklinkPro/Test/autoload.php` by copying the file
`./PacklinkPro/Test/autoload-sample.php`. In the newly created file change path to the magento's root folder,
for example `/var/www/html/magento/app/bootstrap.php`.

Now test configuration is set and you can run tests by activating run command from the 
top right toolbar. 

**All tests must pass.**

*Note*: Tests will fail if you already have installed the module in Magento, because Magento will load both your local 
module and the module from Magento. In such a configuration, you need to rename the module folder in Magento,
run tests, and rename it back.

### Install coding standards tool
If you haven't done so, install Magento Code Sniffer.
```
composer create-project magento/magento-coding-standard --stability=dev magento-coding-standard
```

### Run code fixer
Run code fixer on base code.
```
magento-coding-standard/vendor/bin/phpcbf ./PacklinkPro/ --standard=Magento2

```
This will fix all common problems. 

### Run code sniffer
Run code sniffer.
```
magento-coding-standard/vendor/bin/phpcs ./PacklinkPro/ --standard=Magento2 --severity=10
```
If there is no output, all is fine. Otherwise, correct the reported errors. Ignore errors in `Tests` folder 
since it will not be a part of the final release.
Ignore errors in file `/IntegrationCore/Infrastructure/Serializer/Concrete/NativeSerializer.php` since this file
is removed during the deployment process.

## Release new version

### Prepare module
Make sure that version in `./PacklinkPro/composer.json` file is set to a new version number.
Make sure DB "setup_version" in `./PacklinkPro/etc/module.xml` file is set to a new version number.

Add change log - release notes in `./PluginInstalations/CHANGELOG.md` file.

### Create module package
Run 
```
./deploy.sh
```
### Validate package for release
If you haven't done so, install Magento Marketplace Tools:
```
git clone https://github.com/magento/marketplace-tools.git
```

This will make a proper packages in the `PluginInstallation` folder based on the current module version.

Run checker to validate package:
```
php marketplace-tools/validate_m2_package.php -d ./PluginInstallation/[version]/PacklinkPro.zip
```
