/**
 * Loads heavy vendor libraries (xlsx, jspdf, jspdf-autotable) on demand
 * instead of as blocking <script> tags on every page load. These are only
 * needed when a user actually clicks an Export/PDF button, not on initial
 * page render, so pages that used to eagerly ship ~1MB of vendor JS up
 * front now only pay that cost the first time the feature is used.
 *
 * Each ensure*() function is idempotent and safe to call from multiple
 * places (e.g. both an Export button and a Print button that both need
 * jsPDF) — concurrent calls share the same in-flight load instead of
 * injecting duplicate <script> tags.
 */
(function (global) {
    var pending = {};

    function loadScript(src) {
        if (pending[src]) {
            return pending[src];
        }

        pending[src] = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = function () { resolve(); };
            script.onerror = function () {
                delete pending[src];
                reject(new Error('Failed to load ' + src));
            };
            document.head.appendChild(script);
        });

        return pending[src];
    }

    global.LibLoader = {
        // Chart.js + Leaflet are lazy-loaded so the dashboard shell/icons
        // render without waiting for ~350 KB of visualization JS.
        ensureChart: function () {
            if (global.Chart) return Promise.resolve();
            return loadScript('/assets/vendor/chartjs/chart.min.js');
        },
        ensureLeaflet: function () {
            if (global.L) return Promise.resolve();
            return loadScript('/assets/vendor/leaflet/leaflet.js');
        },
        ensureXLSX: function () {
            if (global.XLSX) return Promise.resolve();
            return loadScript('/assets/vendor/xlsx/xlsx.full.min.js');
        },
        ensureJsPDF: function () {
            if (global.jspdf) return Promise.resolve();
            return loadScript('/assets/vendor/jspdf/jspdf.umd.min.js');
        },
        ensureJsPDFAutoTable: function () {
            return this.ensureJsPDF().then(function () {
                if (global.jspdf && global.jspdf.jsPDF && global.jspdf.jsPDF.API && global.jspdf.jsPDF.API.autoTable) {
                    return;
                }
                return loadScript('/assets/vendor/jspdf-autotable/jspdf.plugin.autotable.min.js');
            });
        },
    };
})(window);
