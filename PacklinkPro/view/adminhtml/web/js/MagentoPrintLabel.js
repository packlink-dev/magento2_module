/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

var Packlink = window.Packlink || {};

/**
 * Prints shipment label for the given order.
 *
 * @param {object} element
 */
function plPrintShipmentLabel(element) {
    let printedLabel = document.getElementsByClassName('pl-printed-label')[0],
        labelLink = element.dataset.link,
        controllerUrl = element.dataset.controllerUrl,
        orderId = parseInt(element.dataset.orderId),
        ajaxService = Packlink.ajaxService;

    if (element.classList.contains('primary')) {
        element.disabled = true;

        ajaxService.post(
            controllerUrl,
            {
                orderId: orderId,
                link: labelLink
            },
            function () {
                element.classList.remove('primary');
                element.innerText = printedLabel.innerText;
                element.disabled = false;
            },
            function () {
                element.disabled = false;
            }
        )
    }

    window.open(labelLink, '_blank');
}
