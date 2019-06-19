/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

if (!window['Packlink']) {
    window.Packlink = {};
}

(function () {
    function CheckoutShippingMethodsConstructor() {
        let mainContainer,
            quote,
            quoteItemsWeight,
            locationControllerBaseUrl,
            submitButtonsSelector,
            isMultiShipping,
            methodLogos,
            language,
            internalMutation,
            displayingDropOffInProgress = false,
            updateShippingInfoPaymentStepTimer = null,
            dropOffLocation = null;

        this.init = init;

        function init(container, quoteObj, itemsWeight, locationControllerUrl, nextSelector, multiShipping, logos, languageCode) {
            mainContainer = container;
            quote = quoteObj;
            quoteItemsWeight = itemsWeight;
            locationControllerBaseUrl = locationControllerUrl;
            submitButtonsSelector = nextSelector;
            isMultiShipping = multiShipping;
            methodLogos = logos;
            language = languageCode;

            hookInputClick();
            selectShippingMethod();
            if (!isMultiShipping) {
                renderCarrierDetails();
                createDOMListener();
            } else {
                renderCarrierDetailsMultiShipping();
            }
        }

        /**
         * Hook a click event handler on all shipping method selector elements.
         */
        function hookInputClick() {
            let buttons = mainContainer.querySelectorAll('input[type=radio]'),
                clickHandler = displayDropOff,
                i;

            for (i = 0; i < buttons.length; i++) {
                buttons[i].parentElement.parentElement.addEventListener(
                    'click',
                    function () {
                        clickHandler(this.querySelector('input[type=radio]').value.substring(9));
                    }
                );
            }
        }

        /**
         * Listens to changes on shipping methods element in order to render proper elements.
         */
        function createDOMListener() {
            let timer,
                mutationObserver = new MutationObserver(
                    function () {
                        if (!timer && !internalMutation) {
                            timer = setTimeout(
                                function () {
                                    renderCarrierDetails();
                                    hookInputClick();
                                    timer = false;
                                },
                                100
                            );
                        }
                    }
                );

            mutationObserver.observe(
                mainContainer,
                {
                    childList: true,
                    subtree: true,
                }
            );
        }

        /**
         * Invokes the click event on the selected shipping method.
         */
        function selectShippingMethod() {
            let checkedInput = mainContainer.querySelector('input:checked');
            if (checkedInput) {
                // page loaded with pre-selected packlink shipping method so we need to
                // make sure we render drop-off locations if needed.
                checkedInput.click();
            }
        }

        /**
         * Displays carrier logos for all Packlink carriers on checkout page.
         */
        function renderCarrierDetails() {
            let shippingMethodRows = mainContainer.getElementsByClassName('row');

            internalMutation = true;
            for (let i = 0; i < shippingMethodRows.length; i++) {
                let methodCell = shippingMethodRows[i].children[2],
                    shippingMethodElements = methodCell.id.split('_'),
                    carrier = shippingMethodElements[3];

                // if our carrier and logo is not added yet
                if (carrier === 'packlink' && methodCell.children.length === 0) {
                    let shippingMethodId = shippingMethodElements[2],
                        logoUrl = methodLogos.hasOwnProperty(shippingMethodId)
                            ? methodLogos[shippingMethodId] : '';

                    if (logoUrl) {
                        methodCell.innerHTML = '<div id="pl-logo-' + shippingMethodId + '" class="pl-carrier-logo">'
                            + '<img alt="" src="' + logoUrl + '" title="' + methodCell.innerHTML + '">'
                            + methodCell.innerHTML
                            + '</div>';
                    }
                }
            }

            let dropOffButtonContainer = getDropOffButtonContainer();
            if (dropOffButtonContainer && !dropOffButtonContainer.querySelector('button')) {
                selectShippingMethod();
            }

            internalMutation = false;
        }

        /**
         * Displays carrier logos for all Packlink carriers on multi-shipping checkout page.
         */
        function renderCarrierDetailsMultiShipping() {
            let shippingMethodRows = mainContainer.querySelectorAll('.choice [type=radio]');

            internalMutation = true;
            for (let i = 0; i < shippingMethodRows.length; i++) {
                let methodCell = shippingMethodRows[i].parentElement.parentElement.querySelector('label'),
                    shippingMethodElements = shippingMethodRows[i].id.split('_'),
                    carrier = shippingMethodElements[3];

                // if our carrier and logo is not added yet
                if (carrier === 'packlink' && methodCell.children.length === 1) {
                    let shippingMethodId = shippingMethodElements[4],
                        logoUrl = methodLogos.hasOwnProperty(shippingMethodId)
                            ? methodLogos[shippingMethodId] : '';

                    if (logoUrl) {
                        methodCell.innerHTML = '<div id="pl-logo-' + shippingMethodId + '" class="pl-carrier-logo">'
                            + '<img alt="" src="' + logoUrl + '" title="' + methodCell.innerText + '">'
                            + methodCell.innerHTML
                            + '</div>';
                    }
                }
            }

            let dropOffButtonContainer = getDropOffButtonContainer();
            if (dropOffButtonContainer && !dropOffButtonContainer.querySelector('button')) {
                selectShippingMethod();
            }

            internalMutation = false;
        }

        /**
         * Displays drop-off button if provided shipping method supports pick-up delivery.
         *
         * @param {string} methodId
         */
        function displayDropOff(methodId) {
            dropOffLocation = null;
            clearInterval(updateShippingInfoPaymentStepTimer);
            updateShippingInfoPaymentStepTimer = setInterval(updateShippingInfoPaymentStep, 1000);

            if (!methodId) {
                removeDropOffInCurrentGroup();
            } else {
                handlePacklinkShippingMethodSelection(parseInt(methodId));
            }
        }

        /**
         * Gets drop-off locations if applicable.
         *
         * @param shippingMethodId Shipping method ID.
         */
        function handlePacklinkShippingMethodSelection(shippingMethodId) {
            if (displayingDropOffInProgress || !shippingMethodId) {
                return;
            }

            let url = locationControllerBaseUrl + '?action=getLocations'
                + '&methodId=' + shippingMethodId
                + '&country=' + quote.shippingAddress().countryId
                + '&zip=' + quote.shippingAddress().postcode
                + '&totalWeight=' + quoteItemsWeight;

            displayingDropOffInProgress = true;
            if (quote.shippingAddress().id) {
                url += '&addressId=' + quote.shippingAddress().id;
            }

            toggleLoader(true);
            Packlink.ajaxService.get(
                url,
                function (response) {
                    if (response.success === true) {
                        displayLocations(response.locations, shippingMethodId)
                    } else {
                        displayErrorGettingLocations(response.message);
                    }

                    displayingDropOffInProgress = false;
                },
                function (response) {
                    displayErrorGettingLocations(response.message);
                    displayingDropOffInProgress = false;
                }
            );
        }

        /**
         * Displays button for selecting drop-off locations if shipping method supports pick up on delivery.
         *
         * @param locations Array of drop-off locations.
         * @param methodCode Shipping method code.
         */
        function displayLocations(locations, methodCode) {

            toggleNextButton(true);
            removeDropOffInCurrentGroup();

            if (locations.length > 0) {
                let dropOffButton = document.getElementById('pl-drop-off-button').cloneNode(true),
                    dropOffDetails = document.getElementById('pl-drop-off-details').cloneNode(true);

                dropOffDetails.style.display = 'none';

                let selectDropOffText = document.getElementById('pl-select-drop-off-text'),
                    dropOffButtonContainer = getDropOffButtonContainer(),
                    selectedLocation;

                locations.forEach(function (location) {
                    if (location['selected']) {
                        selectedLocation = location;
                    }
                });

                dropOffButton.addEventListener('click', function () {
                    displayLocationPicker(methodCode, locations);
                });

                if (selectedLocation) {
                    setDropOffDetails(dropOffButton, dropOffDetails, selectedLocation);
                } else {
                    dropOffButton.innerText = selectDropOffText.innerText;
                    dropOffButton.dataset.dropOffSelected = '0';
                    toggleNextButton(false);
                }

                dropOffButtonContainer.appendChild(dropOffButton);
                dropOffButtonContainer.appendChild(dropOffDetails);

                dropOffButton.style.display = 'inline-flex';
            }

            toggleLoader(false);
        }

        /**
         * Enables or disables the order submit buttons.
         *
         * @param {boolean} enable
         */
        function toggleNextButton(enable) {
            let buttons = document.querySelectorAll(submitButtonsSelector);

            if (isMultiShipping && enable) {
                // check all methods
                let selectedMethods = document.querySelectorAll('.box-shipping-method input[type=radio]:checked');
                for (let i = 0; i < selectedMethods.length; i++) {
                    let detailsElem = selectedMethods[i].parentElement.parentElement.querySelector('#pl-drop-off-details');
                    if (selectedMethods[i].id.split('_')[3] === 'packlink'
                        && detailsElem && detailsElem.style.display === 'none'
                    ) {
                        enable = false;
                        break;
                    }
                }

            }

            for (let i = 0; i < buttons.length; i++) {
                buttons[i].disabled = !enable;
            }
        }

        /**
         * Hides or shows loader.
         *
         * @param {boolean} show
         */
        function toggleLoader(show) {
            if (jQuery('body').loader) {
                jQuery('body').loader(show ? 'show' : 'hide');
            }
        }

        /**
         * Gets the container of the drop-off button inside the shipping method container.
         *
         * @returns {null|Element}
         */
        function getDropOffButtonContainer() {
            let element = mainContainer.querySelector('input:checked');
            if (element) {
                if (isMultiShipping) {
                    return element.parentElement.parentElement.querySelector('label');
                }

                return element.parentElement.parentElement.getElementsByClassName('col-method')[1];
            }

            return null;
        }

        /**
         * Removes drop-off button from the current shipping methods group.
         */
        function removeDropOffInCurrentGroup() {
            let dropOffButton = mainContainer.querySelector('#pl-drop-off-button'),
                dropOffDetails = mainContainer.querySelector('#pl-drop-off-details');

            if (dropOffButton !== null) {
                dropOffButton.parentElement.removeChild(dropOffButton);
                dropOffDetails.parentElement.removeChild(dropOffDetails);
            }
        }

        /**
         * Displays error that occurred while getting locations.
         *
         * @param error Error information.
         */
        function displayErrorGettingLocations(error) {
            toggleLoader(false);
            console.log(error);
        }

        /**
         * Displays location picker.
         *
         * @param id Shipping method ID.
         * @param locations Array of drop-off locations.
         */
        function displayLocationPicker(id, locations) {
            let mapModal = new Packlink.MapModalController(
                id,
                locations,
                locationControllerBaseUrl,
                isMultiShipping,
                quote.shippingAddress().id,
                language,
                dropOffLocation ? dropOffLocation['id'] : null,
                onDropOffSelected
            );

            mapModal.display();
        }

        /**
         * Handles selection of drop-off location from location picker.
         *
         * @param {array} location Selected drop-off location.
         */
        function onDropOffSelected(location) {
            let dropOffElement = getDropOffButtonContainer(),
                dropOffButton = dropOffElement.querySelector('#pl-drop-off-button'),
                dropOffDetails = dropOffElement.querySelector('#pl-drop-off-details');

            setDropOffDetails(dropOffButton, dropOffDetails, location);
        }

        /**
         * Sets drop-off location button and details based on selected location.
         *
         * @param {HTMLElement|Element} dropOffButton
         * @param {HTMLElement|Element} dropOffDetails
         * @param {array} location
         */
        function setDropOffDetails(dropOffButton, dropOffDetails, location) {
            dropOffLocation = location;
            dropOffButton.innerText = document.getElementById('pl-change-drop-off-text').innerText;
            dropOffButton.dataset.dropOffSelected = '1';
            dropOffDetails.style.display = 'block';
            dropOffDetails.querySelector('#pl-drop-off-address').innerText = location['name'] + ', '
                + location['address'] + ', '
                + location['zip'] + ', ' + location['city'];

            toggleNextButton(true);
            clearInterval(updateShippingInfoPaymentStepTimer);
            updateShippingInfoPaymentStepTimer = setInterval(updateShippingInfoPaymentStep, 1000);
        }

        /**
         * Updates shipping info with drop-off location on payment step.
         */
        function updateShippingInfoPaymentStep() {
            let infoContainer = document.querySelector('.opc-block-shipping-information .ship-via'),
                dropOffContainer = infoContainer ? infoContainer.querySelector('.pl-drop-off-container') : null;

            if (infoContainer && dropOffContainer) {
                infoContainer.removeChild(dropOffContainer);
            }

            if (infoContainer && dropOffLocation) {
                clearInterval(updateShippingInfoPaymentStepTimer);
                let link = 'https://www.google.com/maps/search/?api=1&query=' + dropOffLocation['lat']
                    + ',' + dropOffLocation['long'];

                dropOffContainer = document.createElement('div');
                dropOffContainer.innerHTML = '<div class="pl-drop-off-container">'
                    + document.querySelector('#pl-drop-off-details > span').innerText
                    + '<br><a href="' + link + '" target="_blank" style="cursor: pointer;">'
                    + dropOffLocation['name']
                    + '<br>'
                    + dropOffLocation['address']
                    + '<br>'
                    + dropOffLocation['zip'] + ', ' + dropOffLocation['city']
                    + '</a></div>';

                infoContainer.appendChild(dropOffContainer.firstChild);
            }
        }
    }

    Packlink.CheckoutShippingMethods = CheckoutShippingMethodsConstructor;
})();
