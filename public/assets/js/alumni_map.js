/**
 * Alumni Location map (Leaflet) for the admin/superadmin Dashboard.
 *
 * What is drawn: one pin per (city, province) location that has a cached coordinate - never one pin per alumnus - so
 * the pin count is bounded by geography, not by how many alumni exist. Pins are grouped by Leaflet.markercluster.
 *
 * How data arrives: the map asks /admin_dashboard?action=alumniMap for the locations inside the visible bounds
 * (padded), debounced on moveend. Panning/zooming inside an area that was already fetched does not hit the server
 * again; leaving it fetches the new area and drops the pins that scrolled far away. If a very wide view would contain
 * more points than should be drawn, the server sends grid cells ("aggregate" mode) instead, which split apart as the
 * user zooms in.
 *
 * Popup content is built lazily (only when a pin is opened) from DOM nodes with textContent, so location / program
 * names coming from imported alumni data can never inject markup.
 *
 * Usage (admin_dashboard.js):
 *   const ctl = await AlumniMap.create({ container: 'alumniMap', signal, onView(view) {...}, onStatus(s) {...} });
 *   ctl.destroy();
 */
(function (global) {
    'use strict';

    const ENDPOINT = '/admin_dashboard?action=alumniMap';
    const DEFAULT_CENTER = [14.1667, 121.2167];
    const DEFAULT_ZOOM = 10;
    const MOVE_DEBOUNCE_MS = 250;
    const FETCH_PAD = 0.5; // fetch the visible area plus 50% on every side

    function h(tag, className, text) {
        let node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function label(loc) {
        return loc.city + ', ' + loc.province;
    }

    function popupContent(loc, onView) {
        let root = h('div', 'amp');
        root.appendChild(h('b', 'amp-title', label(loc)));

        let stats = h('div', 'amp-stats');
        stats.appendChild(document.createTextNode('Alumni: '));
        stats.appendChild(h('b', null, String(loc.count)));
        stats.appendChild(document.createTextNode('  |  Employed: '));
        stats.appendChild(h('b', null, String(loc.employed)));
        root.appendChild(stats);

        let courses = loc.top_courses || [];
        if (courses.length) {
            let list = h('div', 'amp-courses');
            courses.forEach(function (c) {
                let btn = h('button', 'amp-course', c.course + ' (' + c.count + ')');
                btn.type = 'button';
                btn.title = 'Browse only the alumni of this program';
                btn.addEventListener('click', function () {
                    onView({ city: loc.city, province: loc.province, course: c.course, total: c.count, label: label(loc) });
                });
                list.appendChild(btn);
            });
            root.appendChild(list);
        }

        let view = h('button', 'amp-view', 'View alumni (' + loc.count + ')');
        view.type = 'button';
        view.addEventListener('click', function () {
            onView({ city: loc.city, province: loc.province, course: null, total: loc.count, label: label(loc) });
        });
        root.appendChild(view);

        return root;
    }

    async function create(opts) {
        if (global.LibLoader) await global.LibLoader.ensureMarkerCluster();
        let L = global.L;
        let onView = opts.onView || function () {};
        let onStatus = opts.onStatus || function () {};

        let map = L.map(opts.container).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // chunkedLoading keeps the page responsive while a large batch of pins is added.
        let cluster = L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false, maxClusterRadius: 50 });
        let aggLayer = L.layerGroup();
        map.addLayer(cluster);
        map.addLayer(aggLayer);

        let markers = new Map();   // "city|province" -> L.Marker currently on the map (points mode)
        let coverage = null;       // { bounds, mode } of the last successful fetch
        let fetchCtl = null;
        let timer = null;
        let destroyed = false;

        // One listener for the whole life of the map (not one per request): leaving the page aborts whatever is in flight.
        if (opts.signal) opts.signal.addEventListener('abort', function () { if (fetchCtl) fetchCtl.abort(); }, { once: true });

        function makeMarker(loc) {
            let m = L.marker([loc.lat, loc.lng], { title: label(loc), alt: label(loc) });
            m.__loc = loc;
            m.bindPopup(function () { return popupContent(m.__loc, onView); }, { minWidth: 220, maxWidth: 320 });
            return m;
        }

        function applyPoints(locations) {
            aggLayer.clearLayers();
            let seen = new Set();
            let add = [];
            locations.forEach(function (loc) {
                let key = loc.city + '|' + loc.province;
                seen.add(key);
                let existing = markers.get(key);
                if (existing) {
                    existing.__loc = loc;
                } else {
                    let m = makeMarker(loc);
                    markers.set(key, m);
                    add.push(m);
                }
            });
            let remove = [];
            markers.forEach(function (m, key) {
                if (!seen.has(key)) { remove.push(m); markers.delete(key); }
            });
            if (remove.length) cluster.removeLayers(remove);
            if (add.length) cluster.addLayers(add);
        }

        function applyAggregate(cells) {
            cluster.clearLayers();
            markers.clear();
            aggLayer.clearLayers();
            cells.forEach(function (c) {
                let icon = L.divIcon({
                    className: 'alumni-agg',
                    html: '<div class="alumni-agg-bubble">' + c.count + '</div>',
                    iconSize: [44, 44]
                });
                let m = L.marker([c.lat, c.lng], { icon: icon, title: c.locations + ' locations, ' + c.count + ' alumni' });
                m.on('click', function () { map.setView([c.lat, c.lng], Math.min(map.getZoom() + 2, map.getMaxZoom())); });
                aggLayer.addLayer(m);
            });
        }

        async function refresh(force) {
            if (destroyed) return;
            let view = map.getBounds();
            // Points mode: the pins already loaded cover this view, and clustering re-groups them locally - no request.
            if (!force && coverage && coverage.mode === 'points' && coverage.bounds.contains(view)) return;

            let padded = view.pad(FETCH_PAD);
            if (fetchCtl) fetchCtl.abort();
            let ctl = fetchCtl = new AbortController();

            let url = ENDPOINT
                + '&north=' + padded.getNorth() + '&south=' + padded.getSouth()
                + '&east=' + padded.getEast() + '&west=' + padded.getWest()
                + '&zoom=' + map.getZoom();
            try {
                let res = await fetch(url, { signal: ctl.signal });
                let data = await res.json();
                if (destroyed || ctl !== fetchCtl || !data.success) return;
                coverage = { bounds: padded, mode: data.mode };
                if (data.mode === 'aggregate') applyAggregate(data.locations);
                else applyPoints(data.locations);
                onStatus({ ok: true, mode: data.mode, mapped: data.total_mapped, inView: data.in_view, unmapped: data.unmapped });
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                console.error('Alumni map request failed:', e);
                onStatus({ ok: false });
            }
        }

        map.on('moveend', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { refresh(false); }, MOVE_DEBOUNCE_MS);
        });

        refresh(true);

        return {
            map: map,
            refresh: function () { return refresh(true); },
            destroy: function () {
                destroyed = true;
                clearTimeout(timer);
                if (fetchCtl) fetchCtl.abort();
                map.remove();
            }
        };
    }

    global.AlumniMap = { create: create };
})(window);
