![Packlink logo](https://pro.packlink.es/public-assets/common/images/icons/packlink.svg)

# Packlink Magento 2 plugin

Please read this document thoroughly in order to prepare for the correct installation.
 
## Installation instructions
Plugin is still not on Magento Marketplace so it has to be installed manually.

### Manual installation
To manually install the extension, you will need to have direct access to the server through the terminal.

**Step 1:** 
Extract the content of the `packlink-manual.zip` file and 
upload `Packlink` folder to your Magento shop `app/code/` directory 
(copy the whole Packlink folder). After you do that, make sure that you have folders `app/code/Packlink/PacklinkPro`.

**Step 2:** Now module has to be enabled and Magento recompiled in order to include the module.

Login to Magento server via terminal (SSH) and go to the root directory of the Magento installation. 
All the commands should be executed as the Magento _server console_ user. 
This is the same console user that is used to install the Magento. 

**Note:** If you previously uninstalled the module, then you need to enable the module first:
```bash
php bin/magento module:enable Packlink_PacklinkPro

```

Run the following commands:
```bash
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```

**Step 3:** In case of Magento version less than 2.3, static content needs to be deployed with this command:
```bash
php bin/magento setup:static-content:deploy
```

**Step 4:** Optionally you might need to fix permissions on your Magento files if
the previous steps were ran as a `root` or other non-magento console user. 

After installation is over, Packlink configuration can be accessed with _Sales > Packlink PRO_ menu.

## Uninstall instructions
### Marketplace installation
If module is installed through the Magento Marketplace, module can be uninstalled
from the _System > Web Setup Wizard > Extension manager_.

### Uninstall manually
In a case where module is installed manually, some manual actions are also required to remove the module.

Login to Magento server via terminal (SSH) and go to the root directory of the Magento installation. 
All the commands should be executed as the Magento _server console_ user. 
This is the same console user that is used to install the Magento. 

**Step 1:** Disable module by running this command from the Magento root folder and as a magento console user:
```bash
php bin/magento module:disable Packlink_PacklinkPro
```

**Step 2:** Remove module files
```bash
rm -rf app/code/Packlink
```

**Step 3:** Delete module data from database (you will need the access to the database):
```sql
DELETE FROM `setup_module` WHERE `module` = 'Packlink_PacklinkPro';
DROP TABLE `packlink_entity`;
```

**Step 4:** Lastly, rebuild Magento's code:
```bash
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```

In case of Magento version less than 2.3, static content needs to be deployed with this command:
```bash
php bin/magento setup:static-content:deploy
```

## Version
1.0.1

## Compatibility
Magento 2.1.x, 2.2.x and 2.3.x versions

## Prerequisites
- PHP 5.6 or newer
- MySQL 5.6 or newer
