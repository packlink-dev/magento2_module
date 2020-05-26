var Packlink = window.Packlink || {};

let createDraftEndpoint,
    checkDraftStatusEndpoint,
    draftInProgressMessage,
    draftFailedMessage,
    draftButtonTemplate,
    createDraftTemplate;

function plInitializeFields() {
    createDraftEndpoint = document.querySelector('#pl-create-endpoint');
    checkDraftStatusEndpoint = document.querySelector('#pl-check-status');
    draftInProgressMessage = document.querySelector('#pl-draft-in-progress');
    draftFailedMessage = document.querySelector('#pl-draft-failed');
    draftButtonTemplate = document.querySelector('#pl-draft-button-template');
    createDraftTemplate = document.querySelector('#pl-create-draft-template');
}

function plCreateDraftClick(event) {
    plInitializeFields();

    event.preventDefault();

    plCreateDraft(event.target);
}

function plDraftInProgressInit(orderId) {
    plInitializeFields();

    let element = document.querySelector('.pl-draft-in-progress[data-order-id="' + orderId + '"]'),
        parent = element.parentElement;

    plCheckDraftStatus(parent, orderId);
}

function plCreateDraft(createDraftButton) {
    let orderId = parseInt(createDraftButton.getAttribute('data-order-id')),
        buttonParent = createDraftButton.parentElement;

    buttonParent.removeChild(createDraftButton);
    buttonParent.innerText = draftInProgressMessage.value;

    Packlink.ajaxService.post(createDraftEndpoint.value, {orderId: orderId}, function () {
        plCheckDraftStatus(buttonParent, orderId);
    });
}

function plCheckDraftStatus(parent, orderId) {
    clearTimeout(function () {
        plCheckDraftStatus(parent, orderId);
    });

    Packlink.ajaxService.get(checkDraftStatusEndpoint.value + '?orderId=' + orderId, function (response) {
        if (response.status === 'created') {
            let viewDraftButton = draftButtonTemplate.cloneNode(true);

            viewDraftButton.id = '';
            viewDraftButton.href = response.shipment_url;
            viewDraftButton.classList.remove('hidden');

            parent.innerHTML = '';
            parent.appendChild(viewDraftButton);
        } else if (['failed', 'aborted'].includes(response.status)) {
            parent.innerText = draftFailedMessage.value;
            setTimeout(function () {
                plDisplayCreateDraftButton(parent, orderId)
            }, 5000)
        } else {
            setTimeout(function () {
                plCheckDraftStatus(parent, orderId)
            }, 1000);
        }
    });
}

function plDisplayCreateDraftButton(parent, orderId) {
    clearTimeout(function () {
        plDisplayCreateDraftButton(parent, orderId)
    });

    let createDraftButton = createDraftTemplate.cloneNode(true);

    createDraftButton.id = '';
    createDraftButton.classList.remove('hidden');
    createDraftButton.setAttribute('data-order-id', orderId);

    createDraftButton.addEventListener('click', function (event) {
        event.preventDefault();

        plCreateDraft(createDraftButton);
    });

    parent.innerHTML = '';
    parent.appendChild(createDraftButton);
}
