'use strict';

const checkout = document.querySelector('#checkout');
const csrf = document.querySelector('meta[name="storelift-csrf"]').content;
const status = document.querySelector('#payment-status');
const retry = document.querySelector('#verify-payment');
let orderId = checkout.dataset.order;
let attempt = checkout.dataset.attempt;

async function request(path, payload) {
    const response = await fetch(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-StoreLift-CSRF': csrf },
        body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok) {
        const error = new Error(data.message || 'StoreLift could not complete the request.');
        error.code = data.error;
        throw error;
    }
    return data;
}

function showStatus(message) {
    status.textContent = message;
}

function resetAttempt() {
    attempt = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
}

async function capture(id, actions) {
    orderId = id;
    retry.disabled = true;
    showStatus('Verifying your sandbox payment…');
    try {
        const receipt = await request('api/capture', { orderId });
        if (receipt.state === 'paid') {
            document.querySelector('#payment-actions').hidden = true;
            document.querySelector('#receipt').hidden = false;
            document.querySelector('#capture-id').textContent = `Capture ${receipt.captureId}`;
            const link = document.querySelector('#download');
            link.href = receipt.downloadUrl;
            showStatus('Payment verified. Your kit is ready.');
            link.focus();
        } else if (receipt.state === 'revoked') {
            retry.hidden = true;
            showStatus('This payment was refunded or reversed. The download is unavailable.');
        } else {
            retry.hidden = false;
            showStatus('PayPal is still processing this payment. Check again in a moment.');
        }
    } catch (error) {
        if (error.code === 'instrument_declined' && typeof actions?.restart === 'function') {
            return actions.restart();
        }
        retry.hidden = false;
        showStatus(error.message || 'Could not verify payment. Try Check payment.');
    } finally {
        retry.disabled = false;
    }
}

retry?.addEventListener('click', () => capture(orderId));

if (retry && !document.querySelector('#payment-actions').hidden) {
    if (!window.paypal) {
        showStatus('PayPal checkout could not load. Refresh the page and try again.');
    } else {
        window.paypal.Buttons({
            fundingSource: window.paypal.FUNDING.PAYPAL,
            style: { color: 'blue', shape: 'pill', layout: 'horizontal', label: 'paypal', height: 44, tagline: false },
            async createOrder() {
                try {
                    showStatus('Opening PayPal Sandbox…');
                    const result = await request('api/orders', { sku: checkout.dataset.sku, attempt });
                    orderId = result.id;
                    return result.id;
                } catch (error) {
                    showStatus(error.message);
                    throw error;
                }
            },
            onApprove: (data, actions) => capture(data.orderID, actions),
            onCancel() {
                resetAttempt();
                retry.hidden = true;
                showStatus('Checkout cancelled. You can try again when you’re ready.');
            },
            onError() {
                if (status.textContent === '' || status.textContent === 'Opening PayPal Sandbox…') {
                    showStatus('PayPal checkout could not finish. Try again.');
                }
            },
        }).render('#paypal-buttons').catch(() => showStatus('PayPal checkout could not load. Refresh and try again.'));
    }
}

const importForm = document.querySelector('#catalog-import');
const csvForm = document.querySelector('#catalog-csv');
const reviewForm = document.querySelector('#catalog-review');
const catalogStatus = document.querySelector('#catalog-status');

async function saveCatalog(payload, pending, destination) {
    const controls = document.querySelectorAll('#catalog button');
    controls.forEach(control => { control.disabled = true; });
    catalogStatus.textContent = pending;
    try {
        await request('api/catalog', payload);
        window.location.hash = destination;
        window.location.reload();
    } catch (error) {
        catalogStatus.textContent = error.message;
        catalogStatus.scrollIntoView({ block: 'nearest' });
        controls.forEach(control => { control.disabled = false; });
    }
}

importForm.addEventListener('submit', event => {
    event.preventDefault();
    saveCatalog({ action: 'import', url: document.querySelector('#source-url').value.trim() },
        'Reading the JenniNexus catalog…', 'catalog-review');
});

csvForm.addEventListener('submit', async event => {
    event.preventDefault();
    const file = document.querySelector('#catalog-file').files[0];
    if (!file || file.size > Number(csvForm.dataset.maxBytes)) {
        catalogStatus.textContent = 'Choose a UTF-8 CSV file under 24 KB.';
        return;
    }
    let csv;
    try {
        csv = new TextDecoder('utf-8', { fatal: true }).decode(await file.arrayBuffer());
    } catch {
        catalogStatus.textContent = 'The CSV could not be read. Save it as UTF-8 and try again.';
        return;
    }
    saveCatalog({ action: 'import_csv', csv }, 'Reading your CSV catalog…', 'catalog-review');
});

document.querySelector('#catalog-suggest')?.addEventListener('click', () => {
    saveCatalog({ action: 'suggest', draftId: reviewForm.dataset.draftId },
        'Generating suggestions from the source catalog…', 'catalog-review');
});

document.querySelectorAll('.use-suggestion').forEach(button => {
    button.addEventListener('click', () => {
        const listing = button.closest('.listing');
        const description = listing.querySelector('textarea');
        description.value = listing.querySelector('[data-suggestion]').textContent;
        description.focus();
    });
});

reviewForm?.addEventListener('submit', event => {
    event.preventDefault();
    const edits = Object.fromEntries(Array.from(reviewForm.querySelectorAll('textarea[data-sku]'),
        field => [field.dataset.sku, field.value.trim()]));
    saveCatalog({ action: 'approve', draftId: reviewForm.dataset.draftId, edits },
        'Saving the approved revision…', 'approved-catalog');
});

const embed = document.querySelector('#catalog-embed');
if (embed) {
    const card = document.createElement('article');
    const title = document.createElement('h2');
    const summary = document.createElement('p');
    const link = document.createElement('a');
    title.textContent = 'Creator Demo Kit';
    summary.textContent = 'A starter pack for creator catalogs and digital delivery.';
    link.href = new URL('#checkout', window.location.href).href;
    link.textContent = 'Open StoreLift Sandbox checkout';
    card.append(title, summary, link);
    embed.value = card.outerHTML;
}
