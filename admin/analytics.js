/* ══════════════════════════════════════════════════
   analytics.js — Casa Gunita Analytics
   All placeholder data marked with TODO comments
   for backend replacement.
══════════════════════════════════════════════════ */

/* ── DATE PRESET LOGIC ── */
const dateFrom = document.getElementById('dateFrom');
const dateTo   = document.getElementById('dateTo');

function isoToday() {
    return new Date().toISOString().split('T')[0];
}
function isoOffset(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().split('T')[0];
}
function isoMonthStart() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
}

const presets = {
    today:     () => [isoToday(),      isoToday()],
    yesterday: () => [isoOffset(-1),   isoOffset(-1)],
    '7d':      () => [isoOffset(-6),   isoToday()],
    '30d':     () => [isoOffset(-29),  isoToday()],
    thismonth: () => [isoMonthStart(), isoToday()],
    custom:    () => null, // user picks manually
};

document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const range = presets[btn.dataset.preset]?.();
        if (range) {
            dateFrom.value = range[0];
            dateTo.value   = range[1];
            refreshData();
        }
    });
});

[dateFrom, dateTo].forEach(inp => {
    inp.addEventListener('change', () => {
        document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('[data-preset="custom"]').classList.add('active');
        refreshData();
    });
});

function refreshData() {
    /* TODO: fetch from PHP endpoint using dateFrom.value + dateTo.value */
    updateKPIs();
    buildHeatmap();
    renderPieChart();
    renderTopLineChart(activeTopTab);
    renderTopList(activeRankedTab);
}


/* ── KPIs ── */
function updateKPIs() {
    /* TODO: replace '—' with values from PHP JSON response */
    document.getElementById('kpiOrders').textContent  = '—';
    document.getElementById('kpiRevenue').textContent = '—';
    document.getElementById('kpiPeak').textContent    = '—';
    document.getElementById('kpiTop').textContent     = '—';
}
updateKPIs();


/* ── HEATMAP ── */
const heatDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/* TODO: replace with PHP-driven 7×24 array */
const heatData = [
    [0,0,0,0,0,1,2,3,4,6,8,9,10,8,6,5,4,5,7,6,4,3,1,0],
    [0,0,0,0,0,1,2,4,5,7,9,10, 9,8,6,5,4,5,6,5,3,2,1,0],
    [0,0,0,0,0,1,3,4,6,8,10,10,9,8,7,5,5,6,7,6,4,3,1,0],
    [0,0,0,0,0,2,3,5,6,8, 9,10,10,9,7,6,5,6,8,7,5,3,2,0],
    [0,0,0,0,0,2,4,6,7,9,10,10,10,9,8,7,6,7,9,8,6,4,2,0],
    [0,0,0,0,1,3,5,7,9,10,10,10,10,10,9,8,8,9,10,9,7,5,3,1],
    [0,0,0,0,1,2,4,6,8,10,10,10,10,10,8,7,7,8, 9,8,6,4,2,1],
];

function heatColor(v) {
    if (v === 0) return '#f5ede0';
    if (v <= 2)  return '#e8c99a';
    if (v <= 5)  return '#d4a55a';
    if (v <= 8)  return '#b87820';
    return '#210303';
}

function buildHeatmap() {
    const wrap = document.getElementById('heatmapWrap');
    let html = '<div class="heatmap-hour-row"><div class="heatmap-hour-lbl"></div>';
    for (let h = 0; h < 24; h++) {
        const lbl = h === 0 ? '12a' : h < 12 ? h + 'a' : h === 12 ? '12p' : (h - 12) + 'p';
        html += `<div class="heatmap-hour-lbl">${lbl}</div>`;
    }
    html += '</div>';

    heatDays.forEach((d, i) => {
        html += `<div class="heatmap-day-row"><div class="heatmap-day-lbl">${d}</div>`;
        heatData[i].forEach((v, h) => {
            const lbl = h === 0 ? '12a' : h < 12 ? h + 'am' : h === 12 ? '12pm' : (h - 12) + 'pm';
            html += `<div class="heatmap-cell" style="background:${heatColor(v)}" title="${d} ${lbl}: ${v === 0 ? 'No orders' : v + ' orders'}"></div>`;
        });
        html += '</div>';
    });

    wrap.innerHTML = html;
}
buildHeatmap();


/* ══════════════════════════════════════════════════
   PIE / DOUGHNUT CHART — Order Trends
   Group options: status | category | time
══════════════════════════════════════════════════ */
const PIE_PALETTES = {
    status:   ['#2a7a3b', '#b03030'],
    category: ['#210303', '#b87820', '#5a1a1a', '#d4a55a', '#8d6e37', '#c9a84c'],
    time:     ['#210303', '#b87820', '#d4a55a', '#e8c99a'],
    ordertype: ['#210303', '#b87820'],
};

const PIE_DATA = {
    status: {
        labels: ['Completed', 'Cancelled'],
        data:   [480, 48],
    },
    category: {
        labels: ['Main Course', 'Soups', 'Pulutan', 'Desserts', 'Beverages', 'Specials'],
        data:   [312, 198, 174, 89, 143, 55],
    },
    time: {
        labels: ['Breakfast (6:00 AM–10:00 AM)', 'Lunch (10:00 AM–1:00 PM)', 'Merienda (3:00 PM–5:00 PM)', 'Dinner (5:00 PM–10:00 PM)'],
        data:   [88, 310, 145, 228],
    },
    ordertype: {
        labels: ['Delivery', 'Pickup'],
        data:   [385, 215],
    },
};

let pieChart = null;

function renderPieChart(group) {
    group = group || document.getElementById('pieGroupBy').value;
    const d   = PIE_DATA[group];
    const pal = PIE_PALETTES[group];
    const total = d.data.reduce((a, b) => a + b, 0);

    if (pieChart) pieChart.destroy();

    const ctx = document.getElementById('pieChart').getContext('2d');
    pieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: d.labels,
            datasets: [{
                data: d.data,
                backgroundColor: pal,
                borderColor: '#faf6ef',
                borderWidth: 3,
                hoverOffset: 8,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '62%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.raw} (${Math.round(ctx.raw / total * 100)}%)`,
                    },
                },
            },
        },
    });

    document.getElementById('pieLegend').innerHTML = d.labels.map((lbl, i) => `
        <div class="pie-legend-item">
            <div class="pie-legend-dot" style="background:${pal[i % pal.length]}"></div>
            <span class="pie-legend-name">${lbl}</span>
            <span class="pie-legend-pct">${Math.round(d.data[i] / total * 100)}%</span>
        </div>`).join('');
}
renderPieChart('status');

document.getElementById('pieGroupBy').addEventListener('change', function () {
    renderPieChart(this.value);
});


/* ══════════════════════════════════════════════════
   TOP PERFORMING — LINE CHART
   Shows top 3 per tab (item | category | area)
   over last 7 days
══════════════════════════════════════════════════ */

/* TODO: replace with PHP-driven JSON per tab */
const TOP_LINE_DATA = {
    item: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        series: [
            { name: 'Sinigang na Baboy', data: [28,34,30,38,42,58,50], color: '#210303', category: 'Soups'       },
            { name: 'Crispy Pata',       data: [20,22,26,30,35,48,42], color: '#b87820', category: 'Main Course' },
            { name: 'Kare-Kare',         data: [18,20,22,24,28,40,36], color: '#d4a55a', category: 'Main Course' },
            { name: 'Lechon Kawali',     data: [14,16,18,20,24,34,30], color: '#5a1a1a', category: 'Main Course' },
            { name: 'Bulalo',            data: [10,12,14,16,20,28,24], color: '#8d6e37', category: 'Soups'       },
        ],
    },
    category: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        series: [
            { name: 'Main Course', data: [80,90,88,100,115,148,130], color: '#210303' },
            { name: 'Soups',       data: [50,60,55,65,72,98,88],     color: '#b87820' },
            { name: 'Pulutan',     data: [30,35,40,38,48,70,60],     color: '#d4a55a' },
            { name: 'Desserts',    data: [20,22,25,24,30,45,38],     color: '#5a1a1a' },
            { name: 'Beverages',   data: [15,18,20,22,26,38,32],     color: '#8d6e37' },
        ],
    },
    area: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        series: [
            { name: 'Kapitolyo', data: [38,44,40,50,58,75,68], color: '#210303', district: 'District I'  },
            { name: 'Oranbo',    data: [28,32,35,40,45,62,55], color: '#b87820', district: 'District I'  },
            { name: 'Ugong',     data: [22,26,28,32,38,50,45], color: '#d4a55a', district: 'District I'  },
            { name: 'Kalawaan',  data: [16,18,22,26,30,42,36], color: '#5a1a1a', district: 'District I'  },
            { name: 'Santolan',  data: [10,14,16,20,24,34,28], color: '#8d6e37', district: 'District II' },
        ],
    },
};

/*
 * Filter options per tab for Top Performing.
 * Same pattern as RANKED_FILTER_CONFIG.
 * field = property on each series object to match.
 * 'category' tab has no sub-filter.
 * TODO: auto-generate options from DB when replacing placeholder data.
 */
const TOP_LINE_FILTER_CONFIG = {
    item: {
        placeholder: 'All Categories',
        field: 'category',
        options: ['Main Course', 'Soups', 'Pulutan', 'Desserts', 'Beverages', 'Specials'],
    },
    category: null,
    area: {
        placeholder: 'All Districts',
        field: 'district',
        options: ['District I', 'District II'],
    },
};

const TOP_LINE_SERIES_PER_PAGE = 5;
let topPage = 1;

/* Populate / show / hide the Top Performing filter dropdown */
function updateTopFilter() {
    const sel    = document.getElementById('topFilterSelect');
    const config = TOP_LINE_FILTER_CONFIG[activeTopTab];

    if (!config) {
        sel.style.display = 'none';
        sel.value = '';
        return;
    }

    let html = `<option value="">${config.placeholder}</option>`;
    config.options.forEach(opt => {
        html += `<option value="${opt}">${opt}</option>`;
    });
    sel.innerHTML = html;
    sel.value = '';
    sel.style.display = '';
}

/* Return ALL filtered series (unsliced) for the active top tab */
function getAllFilteredTopSeries() {
    const sel    = document.getElementById('topFilterSelect');
    const config = TOP_LINE_FILTER_CONFIG[activeTopTab];
    const series = TOP_LINE_DATA[activeTopTab].series;

    if (config && sel.value) {
        return series.filter(s => s[config.field] === sel.value);
    }
    return series;
}

/* Return the current page slice of filtered series */
function getPagedTopSeries() {
    const all      = getAllFilteredTopSeries();
    const maxPages = Math.max(1, Math.ceil(all.length / TOP_LINE_SERIES_PER_PAGE));
    if (topPage > maxPages) topPage = maxPages;
    const start = (topPage - 1) * TOP_LINE_SERIES_PER_PAGE;
    return all.slice(start, start + TOP_LINE_SERIES_PER_PAGE);
}

function updateTopPagination() {
    const total    = getAllFilteredTopSeries().length;
    const maxPages = Math.max(1, Math.ceil(total / TOP_LINE_SERIES_PER_PAGE));
    document.getElementById('topPrev').innerHTML     = '&lt;';
    document.getElementById('topNext').innerHTML     = '&gt;';
    document.getElementById('topPrev').disabled      = topPage <= 1;
    document.getElementById('topNext').disabled      = topPage >= maxPages;
    document.getElementById('topPageInfo').textContent = `${topPage}/${maxPages}`;
}

let topLineChart = null;

function renderTopLineChart(tab) {
    const d      = TOP_LINE_DATA[tab];
    const series = getPagedTopSeries();

    if (topLineChart) topLineChart.destroy();

    const ctx = document.getElementById('topLineChart').getContext('2d');
    topLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: d.labels,
            datasets: series.map(s => ({
                label: s.name,
                data: s.data,
                borderColor: s.color,
                backgroundColor: s.color + '12',
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: s.color,
                fill: false,
                tension: 0.35,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10, family: "'DM Sans',sans-serif" }, color: '#8a7060' },
                },
                y: {
                    grid: { color: 'rgba(33,3,3,0.05)' },
                    ticks: { font: { size: 10, family: "'DM Sans',sans-serif" }, color: '#8a7060', precision: 0 },
                },
            },
        },
    });

    document.getElementById('topLineLegend').innerHTML = series.length
        ? series.map(s => `
            <div class="legend-item">
                <div class="legend-dot" style="background:${s.color}"></div>
                ${s.name}
            </div>`).join('')
        : '<div style="font-size:12px;color:#8a7060;">No results for this filter.</div>';

    updateTopPagination();
}

/* Pagination controls for Top Performing */
document.getElementById('topPrev').addEventListener('click', () => {
    if (topPage > 1) { topPage--; renderTopLineChart(activeTopTab); }
});
document.getElementById('topNext').addEventListener('click', () => {
    const maxPages = Math.max(1, Math.ceil(getAllFilteredTopSeries().length / TOP_LINE_SERIES_PER_PAGE));
    if (topPage < maxPages) { topPage++; renderTopLineChart(activeTopTab); }
});


/* ══════════════════════════════════════════════════
   RANKED BREAKDOWN — independent tabs, filter dropdown,
   pagination, and sort.
   NOTE: completely separate from Top Performing tabs.
══════════════════════════════════════════════════ */

/* TODO: replace with PHP-driven JSON per tab */
const TOP_LIST_DATA = {
    item: [
        { name: 'Sinigang na Baboy', count: 238, category: 'Soups' },
        { name: 'Crispy Pata',       count: 195, category: 'Main Course' },
        { name: 'Kare-Kare',         count: 172, category: 'Main Course' },
        { name: 'Lechon Kawali',     count: 148, category: 'Main Course' },
        { name: 'Bulalo',            count: 134, category: 'Soups' },
        { name: 'Sisig',             count: 120, category: 'Pulutan' },
        { name: 'Adobo',             count: 115, category: 'Main Course' },
        { name: 'Tinola',            count: 98,  category: 'Soups' },
        { name: 'Pinakbet',          count: 87,  category: 'Main Course' },
        { name: 'Nilaga',            count: 74,  category: 'Soups' },
        { name: 'Caldereta',         count: 68,  category: 'Main Course' },
        { name: 'Menudo',            count: 55,  category: 'Main Course' },
        { name: 'Pancit Canton',     count: 44,  category: 'Main Course' },
        { name: 'Lomi',              count: 38,  category: 'Soups' },
        { name: 'Batchoy',           count: 29,  category: 'Soups' },
        { name: 'Arroz Caldo',       count: 21,  category: 'Soups' },
        { name: 'Champorado',        count: 18,  category: 'Desserts' },
        { name: 'Goto',              count: 14,  category: 'Soups' },
        { name: 'Puto Bumbong',      count: 10,  category: 'Desserts' },
        { name: 'Bibingka',          count:  7,  category: 'Desserts' },
    ],
    category: [
        { name: 'Main Course', count: 612 },
        { name: 'Soups',       count: 398 },
        { name: 'Pulutan',     count: 274 },
        { name: 'Desserts',    count: 189 },
        { name: 'Beverages',   count: 143 },
        { name: 'Specials',    count:  55 },
    ],
    area: [
        { name: 'Brgy. Kapitolyo',    count: 312, district: 'District I' },
        { name: 'Brgy. Oranbo',       count: 248, district: 'District I' },
        { name: 'Brgy. Ugong',        count: 203, district: 'District I' },
        { name: 'Brgy. Kalawaan',     count: 178, district: 'District I' },
        { name: 'Brgy. Santolan',     count: 145, district: 'District I' },
        { name: 'Brgy. Maybunga',     count: 132, district: 'District I' },
        { name: 'Brgy. Rosario',      count: 118, district: 'District I' },
        { name: 'Brgy. San Antonio',  count:  99, district: 'District I' },
        { name: 'Brgy. Manggahan',    count:  88, district: 'District II' },
        { name: 'Brgy. Dela Paz',     count:  74, district: 'District II' },
        { name: 'Brgy. Pinagbuhatan', count:  61, district: 'District II' },
        { name: 'Brgy. Caniogan',     count:  53, district: 'District II' },
        { name: 'Brgy. Bambang',      count:  45, district: 'District II' },
        { name: 'Brgy. Buting',       count:  38, district: 'District II' },
        { name: 'Brgy. Sagad',        count:  29, district: 'District II' },
        { name: 'Brgy. Sumilang',     count:  22, district: 'District II' },
        { name: 'Brgy. Palatiw',      count:  18, district: 'District II' },
        { name: 'Brgy. Pineda',       count:  14, district: 'District II' },
        { name: 'Brgy. Malinao',      count:  10, district: 'District II' },
        { name: 'Brgy. San Nicolas',  count:   7, district: 'District II' },
    ],
};

/*
 * Filter options per tab.
 * Each entry: { label, field, value }
 * field = the property on each row object to match against value.
 * value = '' means "All" (no filtering).
 * For 'category' tab, no filter is shown (empty array).
 * TODO: when replacing with PHP data, generate these dynamically
 *       from the unique values in the dataset.
 */
const RANKED_FILTER_CONFIG = {
    item: {
        placeholder: 'All Categories',
        field: 'category',
        /* TODO: auto-generate from DB: SELECT DISTINCT category FROM menu_items */
        options: ['Main Course', 'Soups', 'Pulutan', 'Desserts', 'Beverages', 'Specials'],
    },
    category: null, /* No sub-filter for categories */
    area: {
        placeholder: 'All Districts',
        field: 'district',
        /* TODO: auto-generate from DB: SELECT DISTINCT district FROM barangays */
        options: ['District I', 'District II'],
    },
};

const rankedSubLabels = {
    item:     'Menu items by order count',
    category: 'Categories by order count',
    area:     'Barangays by order count',
};

const ROWS_PER_PAGE = 5;
let activeRankedTab = 'item';
let rankedPage      = 1;
let rankedSortAsc   = false; /* false = highest first (default) */

/* Populate / show / hide the filter dropdown based on the active ranked tab */
function updateRankedFilter() {
    const sel    = document.getElementById('rankedFilterSelect');
    const config = RANKED_FILTER_CONFIG[activeRankedTab];

    if (!config) {
        sel.style.display = 'none';
        sel.value = '';
        return;
    }

    /* Rebuild options */
    let html = `<option value="">${config.placeholder}</option>`;
    config.options.forEach(opt => {
        html += `<option value="${opt}">${opt}</option>`;
    });
    sel.innerHTML = html;
    sel.value = '';
    sel.style.display = '';
}

/* Filter + sort + paginate the ranked data for the active tab */
function getFilteredRankedData() {
    const sel    = document.getElementById('rankedFilterSelect');
    const config = RANKED_FILTER_CONFIG[activeRankedTab];
    let data     = [...TOP_LIST_DATA[activeRankedTab]];

    /* Apply dropdown filter if a value is selected */
    if (config && sel.value) {
        data = data.filter(row => row[config.field] === sel.value);
    }

    /* Apply sort */
    return rankedSortAsc
        ? data.sort((a, b) => a.count - b.count)
        : data.sort((a, b) => b.count - a.count);
}

function renderTopList(tab) {
    /* tab param kept for API consistency; activeRankedTab is the source of truth */
    const sorted   = getFilteredRankedData();
    const total    = sorted.length;
    const maxPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
    if (rankedPage > maxPages) rankedPage = maxPages;

    const startIdx  = (rankedPage - 1) * ROWS_PER_PAGE;
    const pageData  = sorted.slice(startIdx, startIdx + ROWS_PER_PAGE);

    /* globalMax is always the highest count regardless of sort direction
       so the bar widths are always relative to the true #1 item */
    const globalMax = Math.max(...sorted.map(r => r.count)) || 1;

    document.getElementById('rankedSub').textContent = rankedSubLabels[activeRankedTab];

    /* Rank number logic:
       - Highest first: 1, 2, 3 ... (startIdx + i + 1)
       - Lowest first:  the item shown first is actually rank #total,
         then #total-1, etc. so rank = total - startIdx - i            */
    function getRank(i) {
        return rankedSortAsc
            ? total - startIdx - i
            : startIdx + i + 1;
    }

    /* Rows */
    document.getElementById('topList').innerHTML = pageData.length
        ? pageData.map((r, i) => `
            <div class="top-row">
                <div class="top-rank">${getRank(i)}</div>
                <div class="top-name">${r.name}</div>
                <div class="top-bar-wrap">
                    <div class="top-bar-fill" style="width:${Math.round(r.count / globalMax * 100)}%"></div>
                </div>
                <div class="top-count">${r.count}</div>
            </div>`).join('')
        : '<div style="padding:18px 0;text-align:center;color:#8a7060;font-size:13px;">No results for this filter.</div>';

/* Pagination */
document.getElementById('rankedPrev').innerHTML = '&lt;';
document.getElementById('rankedNext').innerHTML = '&gt;';
document.getElementById('rankedPrev').disabled     = rankedPage <= 1;
document.getElementById('rankedNext').disabled     = rankedPage >= maxPages;
document.getElementById('rankedPageInfo').textContent = `${rankedPage}/${maxPages}`;

    /* Sort button label */
    document.getElementById('rankedSortBtn').textContent = rankedSortAsc ? '↑ Lowest First' : '↓ Highest First';
}

/* Pagination controls */
document.getElementById('rankedPrev').addEventListener('click', () => {
    if (rankedPage > 1) { rankedPage--; renderTopList(activeRankedTab); }
});
document.getElementById('rankedNext').addEventListener('click', () => {
    const maxPages = Math.max(1, Math.ceil(getFilteredRankedData().length / ROWS_PER_PAGE));
    if (rankedPage < maxPages) { rankedPage++; renderTopList(activeRankedTab); }
});

/* Sort toggle */
document.getElementById('rankedSortBtn').addEventListener('click', () => {
    rankedSortAsc = !rankedSortAsc;
    rankedPage = 1;
    renderTopList(activeRankedTab);
});

/* Filter dropdown change */
document.getElementById('rankedFilterSelect').addEventListener('change', () => {
    rankedPage = 1;
    renderTopList(activeRankedTab);
});

/* ── TAB SWITCHER — TOP PERFORMING (independent) ── */
let activeTopTab = 'item';

document.querySelectorAll('#topTabBar .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#topTabBar .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeTopTab = btn.dataset.tab;
        topPage = 1;
        updateTopFilter();
        renderTopLineChart(activeTopTab);
    });
});

/* Top Performing filter dropdown change */
document.getElementById('topFilterSelect').addEventListener('change', () => {
    topPage = 1;
    renderTopLineChart(activeTopTab);
});

/* ── TAB SWITCHER — RANKED BREAKDOWN (independent) ── */
document.querySelectorAll('#rankedTabBar .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#rankedTabBar .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeRankedTab = btn.dataset.tab;
        rankedPage      = 1;
        rankedSortAsc   = false;
        updateRankedFilter();
        renderTopList(activeRankedTab);
    });
});

/* ── INITIAL RENDERS ── */
updateTopFilter();
renderTopLineChart('item');
updateRankedFilter();
renderTopList('item');


/* ── PDF DOWNLOAD ── */
document.getElementById('downloadPdfBtn').addEventListener('click', async function () {
    const btn = this;
    btn.textContent = 'Generating…';
    btn.disabled = true;

    try {
        const { jsPDF } = window.jspdf;
        const pdf     = new jsPDF('p', 'mm', 'a4');
        const content = document.getElementById('analyticsContent');

        const canvas = await html2canvas(content, {
            scale: 1.5,
            useCORS: true,
            backgroundColor: '#f0ebe0',
        });

        const imgData = canvas.toDataURL('image/jpeg', 0.92);
        const pageW   = pdf.internal.pageSize.getWidth();
        const pageH   = pdf.internal.pageSize.getHeight();
        const imgW    = pageW;
        const imgH    = (canvas.height * imgW) / canvas.width;

        let yPos = 0;
        while (yPos < imgH) {
            if (yPos > 0) pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, -yPos, imgW, imgH);
            yPos += pageH;
        }

        pdf.save('casa-gunita-analytics.pdf');
    } catch (e) {
        alert('PDF generation failed. Please try again.');
        console.error(e);
    } finally {
        btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
        </svg> Download PDF`;
        btn.disabled = false;
    }
});


/* ── HAMBURGER / SIDEBAR TOGGLE ── */
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebar        = document.getElementById('sidebar');
const mainEl         = document.getElementById('main');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const isMobile = () => window.innerWidth <= 768;

function openSidebar() {
    hamburgerBtn.classList.add('open');
    if (isMobile()) {
        sidebar.classList.add('open');
        sidebar.classList.remove('collapsed');
        sidebarOverlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
    }
    localStorage.setItem('sidebarOpen', '1');
}

function closeSidebar() {
    hamburgerBtn.classList.remove('open');
    if (isMobile()) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('visible');
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('collapsed');
        mainEl.classList.add('expanded');
    }
    localStorage.setItem('sidebarOpen', '0');
}

function toggleSidebar() {
    const open = isMobile()
        ? sidebar.classList.contains('open')
        : !sidebar.classList.contains('collapsed');
    open ? closeSidebar() : openSidebar();
}

(function init() {
    const saved = localStorage.getItem('sidebarOpen');
    if (isMobile()) {
        sidebar.classList.remove('open');
        mainEl.classList.remove('expanded');
    } else if (saved === '0') {
        sidebar.classList.add('collapsed');
        mainEl.classList.add('expanded');
        hamburgerBtn.classList.remove('open');
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
        hamburgerBtn.classList.add('open');
    }
})();

hamburgerBtn.addEventListener('click', toggleSidebar);
sidebarOverlay.addEventListener('click', closeSidebar);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

window.addEventListener('resize', () => {
    if (!isMobile()) {
        sidebarOverlay.classList.remove('visible');
        sidebar.classList.remove('open');
        document.body.style.overflow = '';
        const saved = localStorage.getItem('sidebarOpen');
        if (saved === '0') {
            sidebar.classList.add('collapsed');
            mainEl.classList.add('expanded');
            hamburgerBtn.classList.remove('open');
        } else {
            sidebar.classList.remove('collapsed');
            mainEl.classList.remove('expanded');
            hamburgerBtn.classList.add('open');
        }
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
    }
});