/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

if (!window['Packlink']) {
    window.Packlink = {};
}

(function () {
    /**
     * @param {string} id Method ID.
     * @param {array} locations Array of locations
     * @param {string} locationControllerUrl Url for fetching locations.
     * @param {boolean} multiShipping
     * @param {string} address Id of the user address.
     * @param {string} languageCode Two letter language code.
     * @param {string} selectedLocationId Id of the selected drop-off point.
     * @param {function} onSelectionCallback A callback function when drop-off point is selected.
     * @constructor
     */
    function MapModalControllerConstructor(
        id,
        locations,
        locationControllerUrl,
        multiShipping,
        address,
        languageCode,
        selectedLocationId,
        onSelectionCallback
    ) {
        this.display = display;

        let modal = null,
            closeButton = null,
            ajaxService = Packlink.ajaxService,
            locationControllerBaseUrl = locationControllerUrl,
            isMultiShipping = multiShipping,
            addressId = address,
            language = languageCode,

            selectedId = selectedLocationId,
            methodId = id,
            locationList = locations;

        function display() {
            modal = document.getElementById('pl-map-modal');
            modal.classList.remove('hidden');

            closeButton = document.getElementById('pl-close-modal-btn');
            closeButton.addEventListener('click', hideModal);

            Packlink.locationPicker.display(locationList, onDropOffSelected, selectedId, language);
        }

        /**
         * Selects drop-off location.
         *
         * @param id Selected location id.
         */
        function onDropOffSelected(id) {
            selectedId = id;

            toggleSpinner(true);
            let url = locationControllerBaseUrl + '?action=setDropOff'
                + '&methodId=' + methodId
                + '&dropOff=' + JSON.stringify(getSelectedDropOff());

            if (isMultiShipping) {
                url += '&addressId=' + addressId;
            }

            ajaxService.get(url, selectDropOffSuccessHandler);
        }

        function hideModal() {
            toggleSpinner(false);
            modal.classList.add('hidden');
        }

        function toggleSpinner(show) {
            jQuery('body').loader(show ? 'show' : 'hide');
        }

        /**
         * Select drop-off location success callback.
         */
        function selectDropOffSuccessHandler() {
            hideModal();
            if (onSelectionCallback) {
                onSelectionCallback(getSelectedDropOff());
            }
        }

        /**
         * Retrieves selected drop-off.
         *
         * @return {object}
         */
        function getSelectedDropOff() {
            let dropOff = {};

            for (let loc of locations) {
                if (loc.id === selectedId) {
                    dropOff = loc;
                    break;
                }
            }

            return dropOff;
        }
    }

    Packlink.MapModalController = MapModalControllerConstructor;
})();
