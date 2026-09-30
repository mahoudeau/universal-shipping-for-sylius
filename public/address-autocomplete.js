// Address suggestions under the street fields of the checkout's address step, and
// under the pickup point search of the shipping step, as progressive enhancement.
//
// Without this script the form is the same plain form. With it, typing in a
// street field lists matching addresses; choosing one fills the street,
// postcode and city. In the pickup point search, choosing one fills the field with
// the whole address and searches around it. The browser only asks the shop
// (/universal-shipping/address/suggest), never the address provider itself.
//
// It follows the ARIA combobox pattern: the street field is the combobox, the
// suggestions a listbox, the active one announced with aria-activedescendant.
// Up and down move, Enter chooses, Escape closes, and a status line tells
// screen readers how many addresses were found.
//
// Sylius renders the address step as a live component. Filling a field sends
// input and change events, as typing would, so the live form picks the values
// up. Its re-renders can reset the field's attributes or replace the field: a
// MutationObserver puts the combobox back. The list itself lives at the end of
// <body>, outside the form, so re-renders never touch it.

const DELAY = 200;

const config = readConfig();
const states = new Map();
const unsupported = new Set();
let status = null;
let counter = 0;

function readConfig() {
    const element = document.querySelector('script[data-us-address-config]');
    if (!element) {
        return null;
    }
    try {
        return JSON.parse(element.textContent);
    } catch {
        return null;
    }
}

// The field next to the street one in the same address: [street] -> [postcode].
function sibling(input, field) {
    const name = input.name.replace(/\[street\]$/, '[' + field + ']');
    const element = input.form?.elements.namedItem(name);
    return element instanceof HTMLInputElement || element instanceof HTMLSelectElement ? element : null;
}

function announce(message) {
    if (!status) {
        status = document.createElement('div');
        status.className = 'us-address-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        document.body.append(status);
    }
    status.textContent = message;
}

function setAttributes(input, state) {
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', state.list.id);
    input.setAttribute('aria-expanded', String(state.open));
    // The field says what it is (WCAG 1.3.5), so a browser can fill a saved address in one
    // go. "off" used to keep the browser's list from covering ours, and cost that.
    // The pickup point search is no address of the customer's: nothing for the browser to fill.
    input.setAttribute('autocomplete', input.matches('[data-us-search-field]') ? 'off' : 'address-line1');
    if (state.open && state.active >= 0) {
        input.setAttribute('aria-activedescendant', state.list.id + '-' + state.active);
    } else {
        input.removeAttribute('aria-activedescendant');
    }
}

function place(input, list) {
    const rect = input.getBoundingClientRect();
    list.style.top = rect.bottom + window.scrollY + 'px';
    list.style.left = rect.left + window.scrollX + 'px';
    list.style.width = rect.width + 'px';
}

function open(input, state) {
    state.open = true;
    state.list.hidden = false;
    place(input, state.list);
    setAttributes(input, state);
}

function close(input, state) {
    state.open = false;
    state.active = -1;
    state.list.hidden = true;
    setAttributes(input, state);
}

function highlight(input, state, index) {
    state.active = index;
    [...state.list.children].forEach((option, position) => {
        option.setAttribute('aria-selected', String(position === index));
    });
    state.list.children[index]?.scrollIntoView({ block: 'nearest' });
    setAttributes(input, state);
}

function fill(input, suggestion) {
    // The pickup point search: the whole address in the one field, then the search runs
    // (pickup-point-search.js makes the button send the new value).
    if (input.matches('[data-us-search-field]')) {
        input.value = suggestion.label;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.closest('[data-us-picker]')?.querySelector('[data-us-search]')?.click();
        return;
    }

    const values = [
        [input, suggestion.streetLine],
        [sibling(input, 'postcode'), suggestion.postcode],
        [sibling(input, 'city'), suggestion.city],
    ].filter(([field]) => field);

    // Every value first, then the events: the live form sends them in one update.
    values.forEach(([field, value]) => {
        field.value = value;
    });
    values.forEach(([field]) => {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function choose(input, state, index) {
    const suggestion = state.suggestions[index];
    if (!suggestion) {
        return;
    }
    close(input, state);
    fill(input, suggestion);
    announce(suggestion.label);
}

function render(input, state, suggestions) {
    state.suggestions = suggestions;
    state.active = -1;
    state.list.replaceChildren(
        ...suggestions.map((suggestion, index) => {
            const option = document.createElement('li');
            option.id = state.list.id + '-' + index;
            option.className = 'us-address-option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.textContent = suggestion.label;
            option.addEventListener('mousemove', () => {
                if (state.active !== index) {
                    highlight(input, state, index);
                }
            });
            option.addEventListener('click', () => choose(input, state, index));
            return option;
        }),
    );

    if (suggestions.length === 0) {
        close(input, state);
        announce(config.messages.none);
        return;
    }
    open(input, state);
    announce(config.messages.count.replace('%count%', String(suggestions.length)));
}

async function suggest(input, state) {
    const query = input.value.trim();
    // The pickup point search has no country field beside it: the template gives it the order's.
    const country = (input.dataset.usCountry || sibling(input, 'countryCode')?.value || '').toUpperCase();

    state.request?.abort();
    if (query.length < config.minLength || country === '' || unsupported.has(country)) {
        state.suggestions = [];
        close(input, state);
        return;
    }

    const url = new URL(config.url, window.location.href);
    url.searchParams.set('q', query);
    url.searchParams.set('country', country);

    state.request = new AbortController();
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: state.request.signal });
        if (!response.ok) {
            close(input, state);
            return;
        }
        const answer = await response.json();
        if (answer.supported === false) {
            unsupported.add(country);
            close(input, state);
            return;
        }
        // The customer may have gone on typing, or left, while the answer travelled.
        if (document.activeElement === input && input.value.trim() === query) {
            render(input, state, Array.isArray(answer.suggestions) ? answer.suggestions : []);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            close(input, state);
        }
    }
}

function onKeydown(input, state, event) {
    const count = state.suggestions.length;
    switch (event.key) {
        case 'ArrowDown':
        case 'ArrowUp': {
            if (count === 0) {
                return;
            }
            event.preventDefault();
            if (!state.open) {
                open(input, state);
            }
            const step = event.key === 'ArrowDown' ? 1 : -1;
            const from = state.active === -1 && step === -1 ? count : state.active;
            highlight(input, state, (from + step + count) % count);
            break;
        }
        case 'Enter':
            // Without an active suggestion, Enter submits the form as usual.
            if (state.open && state.active >= 0) {
                event.preventDefault();
                choose(input, state, state.active);
            }
            break;
        case 'Escape':
            if (state.open) {
                event.preventDefault();
                close(input, state);
            }
            break;
        case 'Tab':
            close(input, state);
            break;
    }
}

function enhance(input) {
    const known = states.get(input);
    if (known) {
        // A live re-render resets attributes to what the server sent.
        if (input.getAttribute('role') !== 'combobox') {
            setAttributes(input, known);
        }
        return;
    }

    const list = document.createElement('ul');
    list.id = 'us-address-list-' + ++counter;
    list.className = 'us-address-list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', config.messages.list);
    list.hidden = true;
    // Keep the focus in the field when an option is clicked.
    list.addEventListener('mousedown', (event) => event.preventDefault());
    document.body.append(list);

    const state = { list, open: false, active: -1, suggestions: [], request: null, timer: null };
    states.set(input, state);
    setAttributes(input, state);

    input.addEventListener('input', (event) => {
        // Our own events, sent when a suggestion fills the field, are not typing.
        if (!event.isTrusted) {
            return;
        }
        clearTimeout(state.timer);
        state.timer = setTimeout(() => suggest(input, state), DELAY);
    });
    input.addEventListener('keydown', (event) => onKeydown(input, state, event));
    input.addEventListener('blur', () => close(input, state));
}

// Fields a re-render replaced leave their list behind.
function forget() {
    states.forEach((state, input) => {
        if (!input.isConnected) {
            state.request?.abort();
            state.list.remove();
            states.delete(input);
        }
    });
}

function scan() {
    forget();
    document.querySelectorAll('form input[name$="[street]"], form input[data-us-search-field]').forEach(enhance);
}

function reposition() {
    states.forEach((state, input) => {
        if (state.open) {
            place(input, state.list);
        }
    });
}

if (config) {
    let scheduled = false;
    new MutationObserver(() => {
        if (!scheduled) {
            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                scan();
            });
        }
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['role'] });

    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, { passive: true, capture: true });

    scan();
}
