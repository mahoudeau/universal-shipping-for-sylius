// The pickup point search, made dependable. The picker works without it: this only makes
// sure the button searches what the field holds.
//
// The "Search" button re-renders the live form. The field's new value only reaches the
// live form on its change event, which a browser fires when the field loses the focus,
// and on a phone the tap on the button may not take it away first: the re-render went out
// with the old value and "nothing happened". So when the field holds something the last
// render did not see, the button sends the field's change instead, which re-renders with
// it: one request, the right one. Otherwise its own re-render runs, a plain "search again".
// Enter in the field searches too, instead of submitting the whole step.

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-us-search]');
    const field = button?.closest('[data-us-picker]')?.querySelector('[data-us-search-field]');
    // defaultValue is the value attribute, what the last render put there.
    if (!field || field.value === field.defaultValue) {
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    field.dispatchEvent(new Event('change', { bubbles: true }));
}, true);

document.addEventListener('keydown', (event) => {
    // A suggestion chosen with Enter (address-autocomplete.js) already handled it.
    if (event.key !== 'Enter' || event.defaultPrevented || !event.target.matches?.('[data-us-search-field]')) {
        return;
    }
    event.preventDefault();
    event.target.closest('[data-us-picker]')?.querySelector('[data-us-search]')?.click();
});
