/* ══════════════════════════════════════════════════
   analytics.js — Casa Gunita Analytics
   All placeholder data marked with TODO comments
   for backend replacement.
══════════════════════════════════════════════════ */

let TOP_LINE_DATA = {
    item: { labels: [], series: [] },
    category: { labels: [], series: [] },
    area: { labels: [], series: [] }
};

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

<<<<<<< Updated upstream
async function refreshData() {
    /* TODO: fetch from PHP endpoint using dateFrom.value + dateTo.value */
    updateKPIs();
    await buildHeatmap();   /* now async — fetches data for selected period */
    renderPieChart();
    renderTopLineChart(activeTopTab);
    renderTopList(activeRankedTab);
=======
function refreshData() {
    const fromVal = dateFrom.value;
    const toVal = dateTo.value;
    
    fetch(`get_analytics_data.php?date_from=${fromVal}&date_to=${toVal}`)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            analyticsData = data;
            
            // Recompute heatmap variables
            heatData = analyticsData?.heatmap?.grid || Array(7).fill(null).map(() => Array(24).fill(0));
            displayDays = analyticsData?.heatmap?.days || heatDays;
            
            // Recompute TOP_LINE_DATA cache
            TOP_LINE_DATA.item = buildTopLineData('item');
            TOP_LINE_DATA.category = buildTopLineData('category');
            TOP_LINE_DATA.area = buildTopLineData('area');
            
            // Re-render components
            updateKPIs();
            buildHeatmap();
            renderPieChart();
            updateTopFilter();
            renderTopLineChart(activeTopTab);
            updateRankedFilter();
            renderTopList(activeRankedTab);
        })
        .catch(err => {
            console.error('Error fetching analytics data:', err);
        });
>>>>>>> Stashed changes
}


/* ── KPIs ── */
function updateKPIs() {
    const kpis = analyticsData?.kpis;
    if (!kpis) return;
    
    document.getElementById('kpiOrders').textContent = kpis.total_orders || 0;
    
    const revenueFormatted = '₱' + Number(kpis.total_revenue || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    document.getElementById('kpiRevenue').textContent = revenueFormatted;
    
    let peakHourStr = '—';
    if (kpis.peak_hour && kpis.peak_hour !== '—') {
        const hour = parseInt(kpis.peak_hour.split(':')[0], 10);
        if (!isNaN(hour)) {
            const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
            const ampm = hour >= 12 ? 'PM' : 'AM';
            peakHourStr = `${displayHour} ${ampm}`;
        } else {
            peakHourStr = kpis.peak_hour;
        }
    }
    document.getElementById('kpiPeak').textContent = peakHourStr;
    
    document.getElementById('kpiTop').textContent = kpis.top_item || '—';
    if (kpis.top_item && kpis.top_item !== '—') {
        document.getElementById('kpiTopSub').textContent = `${kpis.top_item_count || 0} order${kpis.top_item_count === 1 ? '' : 's'}`;
    } else {
        document.getElementById('kpiTopSub').textContent = 'Most ordered';
    }
}
updateKPIs();


/* ── HEATMAP ── */
const heatDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

<<<<<<< Updated upstream
/* TODO: replace with PHP-driven 7×24 array fetched via AJAX using dateFrom/dateTo */
let heatData = [
    [0,0,0,0,0,1,2,3,4,6,8,9,10,8,6,5,4,5,7,6,4,3,1,0],
    [0,0,0,0,0,1,2,4,5,7,9,10, 9,8,6,5,4,5,6,5,3,2,1,0],
    [0,0,0,0,0,1,3,4,6,8,10,10,9,8,7,5,5,6,7,6,4,3,1,0],
    [0,0,0,0,0,2,3,5,6,8, 9,10,10,9,7,6,5,6,8,7,5,3,2,0],
    [0,0,0,0,0,2,4,6,7,9,10,10,10,9,8,7,6,7,9,8,6,4,2,0],
    [0,0,0,0,1,3,5,7,9,10,10,10,10,10,9,8,8,9,10,9,7,5,3,1],
    [0,0,0,0,1,2,4,6,8,10,10,10,10,10,8,7,7,8, 9,8,6,4,2,1],
];
=======
// Use data from PHP (analyticsData.heatmap)
let heatData = analyticsData?.heatmap?.grid || Array(7).fill(null).map(() => Array(24).fill(0));
let displayDays = analyticsData?.heatmap?.days || heatDays;
>>>>>>> Stashed changes

function heatColor(v) {
    if (v === 0) return '#f5ede0';
    if (v <= 2)  return '#e8c99a';
    if (v <= 5)  return '#d4a55a';
    if (v <= 8)  return '#b87820';
    return '#210303';
}

/* Fetch heatmap data from backend based on selected date range */
async function fetchHeatmapData(from, to) {
    /* TODO: Replace with actual fetch call:
       const res = await fetch(`api/heatmap.php?from=${from}&to=${to}`);
       const json = await res.json();
       return json.data; // 7x24 array
    */
    // Return placeholder data for now (simulating filtered results)
    // In production, this should query the DB with the date range.
    return heatData;
}

async function buildHeatmap() {
    const wrap = document.getElementById('heatmapWrap');
    const from = dateFrom.value;
    const to   = dateTo.value;

    /* Fetch data for the selected period */
    const data = await fetchHeatmapData(from, to);

    let html = '<div class="heatmap-hour-row"><div class="heatmap-hour-lbl"></div>';
    for (let h = 0; h < 24; h++) {
        const lbl = h === 0 ? '12a' : h < 12 ? h + 'a' : h === 12 ? '12p' : (h - 12) + 'p';
        html += `<div class="heatmap-hour-lbl">${lbl}</div>`;
    }
    html += '</div>';

<<<<<<< Updated upstream
    heatDays.forEach((d, i) => {
        html += `<div class="heatmap-day-row"><div class="heatmap-day-lbl">${d}</div>`;
        data[i].forEach((v, h) => {
=======
    displayDays.forEach((d, i) => {
        if (!heatData[i]) return;
        html += `<div class="heatmap-day-row"><div class="heatmap-day-lbl">${d.substring(0, 3)}</div>`;
        heatData[i].forEach((v, h) => {
>>>>>>> Stashed changes
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
    status:   ['#2a7a3b', '#b03030', '#8d6e37', '#5a1a1a'],
    category: ['#210303', '#b87820', '#5a1a1a', '#d4a55a', '#8d6e37', '#c9a84c'],
    time:     ['#210303', '#b87820', '#d4a55a', '#e8c99a'],
    ordertype: ['#210303', '#b87820'],
};

// Convert PHP pie data to chart.js format
function getPieData(groupType) {
    const data = analyticsData?.pie?.[groupType] || [];
    return {
        labels: data.map(d => d.label),
        data: data.map(d => d.count),
        percentages: data.map(d => d.percentage)
    };
}

let pieChart = null;

function renderPieChart(group) {
    group = group || document.getElementById('pieGroupBy').value;
    const pieData = getPieData(group);
    const pal = PIE_PALETTES[group];
    const total = pieData.data.reduce((a, b) => a + b, 0);

    if (pieChart) pieChart.destroy();

    const ctx = document.getElementById('pieChart').getContext('2d');
    pieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: pieData.labels,
            datasets: [{
                data: pieData.data,
                backgroundColor: pal.slice(0, pieData.data.length),
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

    document.getElementById('pieLegend').innerHTML = pieData.labels.map((lbl, i) => `
        <div class="pie-legend-item">
            <div class="pie-legend-dot" style="background:${pal[i % pal.length]}"></div>
            <span class="pie-legend-name">${lbl}</span>
            <span class="pie-legend-pct">${pieData.percentages[i] || 0}%</span>
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

// Convert PHP top performing data to chart format
function buildTopLineData(tabType) {
    const rawData = analyticsData?.topPerforming?.[tabType] || [];
    
    // Group by date and build series
    const colors = ['#210303', '#b87820', '#d4a55a', '#5a1a1a', '#8d6e37'];
    const uniqueDates = [...new Set(rawData.map(d => d.date))].sort();
    
    // Get unique items/categories/areas
    const uniqueItems = [...new Set(rawData.map(d => d.id))];
    
    // Build series data
    const series = uniqueItems.map((id, idx) => {
        const itemData = rawData.filter(d => d.id === id);
        const item = itemData[0];
        return {
            name: item?.label || 'Unknown',
            data: uniqueDates.map(date => {
                const entry = itemData.find(d => d.date === date);
                return tabType === 'area' ? (entry?.order_count || 0) : (entry?.quantity_sold || 0);
            }),
            color: colors[idx % colors.length],
            [tabType === 'item' ? 'category' : 'district']: item?.category || item?.district || '',
        };
    });

    // Format dates as day names
    const labels = uniqueDates.length > 0 
        ? uniqueDates.map(d => new Date(d).toLocaleDateString('en-US', { weekday: 'short' }))
        : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    return { labels, series };
}

const TOP_LINE_FILTER_CONFIG = {
    item: {
        placeholder: 'All Categories',
        field: 'category',
        options: [],
    },
    category: null,
    area: {
        placeholder: 'All Districts',
        field: 'district',
        options: [],
    },
};

const TOP_LINE_SERIES_PER_PAGE = 5 ;
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

    if (activeTopTab === 'item') {
        const series = TOP_LINE_DATA.item?.series || [];
        const cats = [...new Set(series.map(s => s.category).filter(c => c))].sort();
        config.options = cats;
    } else if (activeTopTab === 'area') {
        const series = TOP_LINE_DATA.area?.series || [];
        const dists = [...new Set(series.map(s => s.district).filter(d => d))].sort();
        config.options = dists;
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
    if (!TOP_LINE_DATA[tab]) {
        TOP_LINE_DATA[tab] = buildTopLineData(tab);
    }
    const lineData = TOP_LINE_DATA[tab];
    const series = getPagedTopSeries();
    updateTopPagination();

    if (topLineChart) topLineChart.destroy();

    const ctx = document.getElementById('topLineChart').getContext('2d');
    topLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: lineData.labels,
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
        : '<div style="font-size:12px;color:#8a7060;">No data available.</div>';
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

// Get ranked data from PHP
function getRankedData(tabType) {
    const data = analyticsData?.ranked?.[tabType] || [];
    return data.map(item => ({
        name: item.label,
        count: item.order_count,
        category: item.category || '',
        district: item.district || '',
    }));
}

const RANKED_FILTER_CONFIG = {
    item: {
        placeholder: 'All Categories',
        field: 'category',
        options: [],
    },
    category: null,
    area: {
        placeholder: 'All Districts',
        field: 'district',
        options: [],
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

    if (activeRankedTab === 'item') {
        const data = getRankedData('item');
        const cats = [...new Set(data.map(r => r.category).filter(c => c))].sort();
        config.options = cats;
    } else if (activeRankedTab === 'area') {
        const data = getRankedData('area');
        const dists = [...new Set(data.map(r => r.district).filter(d => d))].sort();
        config.options = dists;
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
    let data     = [...getRankedData(activeRankedTab)];

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
TOP_LINE_DATA.item = buildTopLineData('item');
TOP_LINE_DATA.category = buildTopLineData('category');
TOP_LINE_DATA.area = buildTopLineData('area');

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

/* ── LOGOUT ── */
document.getElementById('logoutBtn').addEventListener('click', function (e) {
    e.preventDefault();
    window.location.href = '../logout.php';
});