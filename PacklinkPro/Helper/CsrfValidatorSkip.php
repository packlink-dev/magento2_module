<?php

namespace Packlink\PacklinkPro\Helper;

/**
 * Class CsrfValidatorSkip
 *
 * @package Packlink\PacklinkPro\Helper
 */
class CsrfValidatorSkip
{
    /**
     * Validates csrf request.
     *
     * @param \Magento\Framework\App\Request\CsrfValidator $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\App\ActionInterface $action
     *
     * @noinspection PhpUnusedParameterInspection*/
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        if ($request->getModuleName() === 'packlink') {
            return; // Skips CSRF check for Packlink POST routes.
        }

        $proceed($request, $action);
    }
}
