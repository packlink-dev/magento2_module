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
        controllerUrl = element.dataset.controllerUrl,
        orderId = parseInt(element.dataset.orderId),
        ajaxService = Packlink.ajaxService;

    element.disabled = true;

    ajaxService.post(
        controllerUrl,
        {
            orderId: orderId
        },
        function (response) {
            if (response.labelLink) {
                window.open(response.labelLink, '_blank');
                element.innerText = printedLabel.innerText;
            }
            element.classList.remove('primary');
            element.disabled = false;
        },
        function () {
            element.disabled = false;
        }
    )
}
