/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

var Packlink = window.Packlink || {};

/**
 * Creates order draft for the order with provided ID.
 *
 * @param {object} element
 */
function plCreateOrderDraft(element) {
    let ajaxService = Packlink.ajaxService,
        orderId = parseInt(element.dataset.order),
        controllerUrl = element.dataset.createDraftUrl,
        errorText = document.getElementById('pl-create-draft-error-text'),
        errorMsg = document.getElementById('pl-create-draft-error'),
        defaultErrorMsg = document.getElementById('pl-create-draft-default-msg');

    element.disabled = true;

    ajaxService.post(
        controllerUrl,
        {orderId: orderId},
        function () {
            window.location.reload();
        },
        function (response) {
            element.disabled = false;
            errorMsg.hidden = false;
            errorMsg.innerHTML = defaultErrorMsg.innerText;
            if (response && response.message) {
                errorMsg.innerHTML += ' ' + errorText.innerText + response.message;
            }
        }
    );
}