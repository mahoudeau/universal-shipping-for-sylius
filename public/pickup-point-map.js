// A map next to the pickup point list, as progressive enhancement.
//
// The list stays the real control: it works without this script, it is what
// keyboards and screen readers use, and it is what the form submits. The map
// only mirrors it. Clicking a pin checks the matching radio, which triggers
// Sylius's live form like any other change.
//
// The live form re-renders the picker on every change. The map container is
// marked data-live-ignore so MapLibre keeps its state; the points travel in a
// JSON script tag that is re-rendered, and this module watches for it.
//
// MapLibre (about 600 KB) is only fetched once a map is actually on the page.

const maps = new WeakMap();
let library = null;

function load() {
    library ??= import('./vendor/maplibre/maplibre-gl.mjs').then((maplibre) => {
        // Styles pointing at a self-hosted Protomaps file use pmtiles:// sources.
        if (window.pmtiles) {
            maplibre.addProtocol('pmtiles', new window.pmtiles.Protocol().tile);
        }
        return maplibre;
    });
    return library;
}

// Which paint property carries the colour, per layer type.
const PAINT = { background: 'background-color', fill: 'fill-color', line: 'line-color', 'fill-extrusion': 'fill-extrusion-color' };

// OpenMapTiles source layers (OpenFreeMap and most free styles) to theme colours.
function colorFor(layer, colors) {
    if (layer.type === 'background') {
        return colors.background;
    }
    if (layer.type === 'symbol') {
        return null;
    }
    const source = layer['source-layer'];
    if (source === 'water' || source === 'waterway') {
        return colors.water;
    }
    if (source === 'park' || source === 'landcover') {
        return colors.parks;
    }
    if (source === 'landuse') {
        return colors.background;
    }
    if (source === 'building') {
        return colors.buildings;
    }
    // Road casings keep the style's darker edge, so roads stay readable on a pale background.
    if (source === 'transportation' && layer.type === 'line' && !layer.id.includes('casing')) {
        return colors.roads;
    }
    return null;
}

function applyTheme(map, colors) {
    if (Object.keys(colors).length === 0) {
        return;
    }
    for (const layer of map.getStyle().layers) {
        if (layer.type === 'symbol') {
            if (colors.labels && map.getPaintProperty(layer.id, 'text-color') !== undefined) {
                map.setPaintProperty(layer.id, 'text-color', colors.labels);
                if (colors.background) {
                    map.setPaintProperty(layer.id, 'text-halo-color', colors.background);
                }
            }
            continue;
        }
        const color = colorFor(layer, colors);
        if (color && PAINT[layer.type]) {
            map.setPaintProperty(layer.id, PAINT[layer.type], color);
        }
    }
}

function colors(container) {
    try {
        return JSON.parse(container.dataset.colors || '{}');
    } catch {
        return {};
    }
}

function points(picker) {
    const data = picker.querySelector('script[data-us-points]');
    try {
        return data ? JSON.parse(data.textContent) : [];
    } catch {
        return [];
    }
}

function choose(picker, id) {
    const radio = [...picker.querySelectorAll('input[type="radio"]')].find((input) => input.value === id);
    if (!radio || radio.checked) {
        return;
    }
    radio.checked = true;
    radio.dispatchEvent(new Event('change', { bubbles: true }));
    radio.closest('label')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

// The map is aria-hidden: it mirrors the list, which is the control screen readers and
// keyboards use. MapLibre makes its canvas, zoom buttons, attribution links and markers
// focusable, which would put keyboard users on controls a screen reader doesn't announce
// (WCAG 4.1.2, axe "aria-hidden-focus"). Everything inside it leaves the tab order; mouse
// and touch work as before, and the attribution stays on screen.
function keepOutOfTabOrder(container) {
    const untab = (root) => {
        // summary: the compact attribution is a <details>, focusable without a tabindex.
        root.querySelectorAll('a, button, canvas, input, summary, [tabindex]').forEach((element) => {
            if (element.getAttribute('tabindex') !== '-1') {
                element.setAttribute('tabindex', '-1');
            }
        });
    };
    untab(container);
    new MutationObserver(() => untab(container)).observe(container, { childList: true, subtree: true });
}

function pin(index, selected, onClick) {
    const element = document.createElement('div');
    element.className = 'us-map-pin' + (selected ? ' us-map-pin--selected' : '');
    element.innerHTML = '<span><b>' + (index + 1) + '</b></span>';
    element.addEventListener('click', (event) => {
        event.stopPropagation();
        onClick();
    });
    return element;
}

async function render(container) {
    const picker = container.closest('[data-us-picker]');
    if (!picker) {
        return;
    }

    const list = points(picker).filter((point) => point.lat !== null && point.lng !== null);
    const data = JSON.stringify(list);

    let state = maps.get(container);
    if (state?.data === data) {
        return;
    }

    const maplibre = await load();

    state = maps.get(container);
    if (!state) {
        const map = new maplibre.Map({
            container,
            style: container.dataset.style,
            cooperativeGestures: true,
            attributionControl: { compact: true },
            center: list[0] ? [list[0].lng, list[0].lat] : [2.35, 46.6],
            zoom: list[0] ? 14 : 5,
        });
        map.addControl(new maplibre.NavigationControl({ showCompass: false }), 'top-left');
        map.once('style.load', () => applyTheme(map, colors(container)));
        keepOutOfTabOrder(container);
        state = { map, markers: [], data: '', ids: '' };
        maps.set(container, state);
    }
    if (state.data === data) {
        return;
    }
    state.data = data;

    state.markers.forEach((marker) => marker.remove());
    state.markers = list.map((point, index) =>
        new maplibre.Marker({ element: pin(index, point.selected, () => choose(picker, point.id)), anchor: 'bottom' })
            .setLngLat([point.lng, point.lat])
            .addTo(state.map),
    );

    // Refit only when the set of points changes, not when the selection does.
    const ids = list.map((point) => point.id).join(',');
    if (ids !== state.ids && list.length > 0) {
        state.ids = ids;
        const bounds = new maplibre.LngLatBounds();
        list.forEach((point) => bounds.extend([point.lng, point.lat]));
        state.map.resize();
        state.map.fitBounds(bounds, { padding: 40, maxZoom: 16, duration: 0 });
    }
}

function renderAll() {
    document.querySelectorAll('[data-us-map]').forEach((container) => {
        render(container).catch((error) => console.error('Universal Shipping map:', error));
    });
}

let scheduled = false;
new MutationObserver(() => {
    if (!scheduled) {
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            renderAll();
        });
    }
}).observe(document.body, { childList: true, subtree: true, characterData: true });

renderAll();
