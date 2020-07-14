[![Codacy Badge](https://app.codacy.com/project/badge/Grade/0bafe9b01abc40cda98e6564b6816246)](https://www.codacy.com/gh/packlink-dev/magento2_module?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=packlink-dev/magento2_module&amp;utm_campaign=Badge_Grade)

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

### Preparing code for commit
Always run the validator script before committing the code.

```
./prepare-validate.sh

```
This will validate and automatically fix all common problems. 
If there is no output, all is fine. Otherwise, correct the reported errors. 
Ignore errors in `Tests` folder and in file `/IntegrationCore/Infrastructure/Serializer/Concrete/NativeSerializer.php` 
since these will be removed during the deployment/packaging process.

## Release new version

### Prepare module
Make sure version in `./PacklinkPro/composer.json` file is set to a new version number.
Make sure DB "setup_version" in `./PacklinkPro/etc/module.xml` file is set to a new version number.

Add change log - release notes in `./PluginInstalations/CHANGELOG.md` file.

### Create module package
Run 
```
./deploy.sh
```

