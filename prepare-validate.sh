#!/bin/bash

echo -e "\e[32mSTEP 1:\e[39m Installing coding standards..."

if [[ -d ./magento-coding-standard/ ]]; then
  rm -rf magento-coding-standard/
fi

composer create-project magento/magento-coding-standard --stability=dev magento-coding-standard

echo -e "\e[32mSTEP 2:\e[39m Running code fixer..."
magento-coding-standard/vendor/bin/phpcbf ./PacklinkPro/ --standard=Magento2

echo -e "\e[32mSTEP 3:\e[39m Running code sniffer..."
magento-coding-standard/vendor/bin/phpcs ./PacklinkPro/ --standard=Magento2 --severity=10

echo -e "\e[32mDONE!\n\n\e[93mIf the last step produced any output, review it before making a package!"
