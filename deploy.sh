#!/bin/bash

version="$1"

cleanup() {
  rm -rf ./packlink-manual.zip
  rm -rf ./PacklinkPro.zip
  rm -rf ./packlink
}

createTempSource() {
  # Create deployment source
  echo -e "\e[32mSTEP 1:\e[39m Copying plugin source..."
  mkdir packlink
  cp -r ./PacklinkPro packlink
  cp -r ./Script packlink
  cp -r ./composer.json packlink
  rm -rf packlink/PacklinkPro/IntegrationCore
}

composerInstall() {
  # Ensure proper composer dependencies
  echo -e "\e[32mSTEP 2:\e[39m Installing composer dependencies..."
  cd packlink
  composer install -d "$PWD" --no-dev
  cd ..
}

removeUnnecessaryFiles() {
  # Remove unnecessary files from final release archive
  echo -e "\e[32mSTEP 3:\e[39m Removing unnecessary files from final release archive..."
  rm -rf packlink/Script
  rm -rf packlink/vendor
  rm -rf packlink/composer.json
  rm -rf packlink/composer.lock
  rm -rf packlink/PacklinkPro/Test
  rm -rf packlink/PacklinkPro/IntegrationCore/Tests
  rm -rf packlink/PacklinkPro/IntegrationCore/DemoUI
  rm -rf packlink/PacklinkPro/IntegrationCore/BusinessLogic/OAuth/Services/OAuthService.php
  rm -rf packlink/PacklinkPro/IntegrationCore/BusinessLogic/Resources/countries/translations.csv
  rm -rf packlink/PacklinkPro/IntegrationCore/BusinessLogic/Resources/countries/fromCSV.php
  rm -rf packlink/PacklinkPro/IntegrationCore/BusinessLogic/Resources/countries/toCSV.php
  rm -rf packlink/PacklinkPro/IntegrationCore/Infrastructure/Serializer/Concrete/NativeSerializer.php
  rm -rf packlink/PacklinkPro/view/adminhtml/web/packlink/countries/fromCSV.php
  rm -rf packlink/PacklinkPro/view/adminhtml/web/packlink/countries/toCSV.php
}

createZipArchives() {
  echo -e "\e[32mSTEP 4:\e[39m Creating new marketplace archive..."
  cd packlink
  zip -r -q PacklinkPro.zip ./PacklinkPro
  mv ./PacklinkPro.zip ../PacklinkPro.zip

  echo -e "\e[32mSTEP 5:\e[39m Creating new archive for manual install..."
  mkdir Packlink
  mv ./PacklinkPro ./Packlink/PacklinkPro
  zip -r -q packlink-manual.zip ./Packlink
  cd ..
  mv ./packlink/packlink-manual.zip ./packlink-manual.zip
}

readVersion() {
  if [[ "$version" == "" ]]; then
    version=$(php -r "echo json_decode(file_get_contents('PacklinkPro/composer.json'), true)['version'];")
    if [[ "$version" == "" ]]; then
      echo "Please enter new plugin version (leave empty to use root folder as destination) [ENTER]:"
      read version
    else
      echo -e "\e[35mVersion read from the composer.json file: $version\e[39m"
    fi
  fi
}

replaceMtRand() {
  echo -e "\e[32mSTEP X:\e[39m Replacing mt_rand() with random_int()..."
  find ./packlink -type f -name "*.php" -exec sed -i 's/mt_rand(/random_int(/g' {} +
}

fixNullTypeHints() {
  echo -e "\e[32mSTEP Z:\e[39m Removing nullable type hints..."

  find ./packlink -type f -name "*.php" -exec sed -i -E \
    "s/([, (])([A-Za-z0-9_\\]+)[[:space:]]+\$([a-zA-Z0-9_]+)[[:space:]]*=\s*null/\1\$\3 = null/g" {} +
}

moveArchivesToDirectory() {
  if [[ "$version" != "" ]]; then
    if [[ ! -d ./PluginInstallation/ ]]; then
      mkdir ./PluginInstallation/
    fi
    if [[ ! -d ./PluginInstallation/"$version"/ ]]; then
      mkdir ./PluginInstallation/"$version"/
    fi

    mv ./packlink-manual.zip ./PluginInstallation/${version}/
    mv ./PacklinkPro.zip ./PluginInstallation/${version}/
    if [[ ! -f "./PluginInstallation/$version/README.md" ]]; then
      touch "./PluginInstallation/$version/README.md"
      echo "This folder contains packages for version $version.

Please follow instructions from [Readme file](../../Packlink/PacklinkPro/README.md).

Changes are described in the [Change log](../../CHANGELOG.md)." >"./PluginInstallation/$version/README.md"
    fi
    echo -e "\e[32mDONE!\n\e[93mNew release created under: $PWD/PluginInstallation/$version"
  else
    echo -e "\e[32mDONE!\n\e[93mNew plugin archives created: $PWD/packlink-manual.zip, $PWD/PacklinkPro.zip"
  fi
}

cleanup
createTempSource
composerInstall
replaceMtRand
fixNullTypeHints
removeUnnecessaryFiles
createZipArchives
readVersion
moveArchivesToDirectory
cleanup
