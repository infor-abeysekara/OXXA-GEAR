/**
 * Export Utilities for Seller Dashboard
 */

// Format date for filename
function getFormattedDate() {
    return new Date().toISOString().split('T')[0];
}

/**
 * Export data to CSV format
 * @param {Array} data - Array of objects containing data
 * @param {String} filename - Name of the file
 */
function exportToCSV(data, filename) {
    if (!data || !data.length) {
        alert("No data available to export.");
        return;
    }

    const headers = Object.keys(data[0]);
    const csvRows = [];
    
    // Add headers
    csvRows.push(headers.map(h => `"${String(h).replace(/"/g, '""')}"`).join(','));
    
    // Add data rows
    data.forEach(row => {
        const values = headers.map(header => {
            const val = row[header] === null || row[header] === undefined ? '' : String(row[header]);
            return `"${val.replace(/"/g, '""')}"`;
        });
        csvRows.push(values.join(','));
    });

    const csvString = csvRows.join('\n');
    // Add UTF-8 BOM for Sinhala support
    const blob = new Blob(['\uFEFF' + csvString], { type: 'text/csv;charset=utf-8;' });
    
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}-${getFormattedDate()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Export data to PDF format using jsPDF & autoTable
 * @param {Array} data - Array of objects containing data
 * @param {String} filename - Name of the file
 */
function exportToPDF(data, filename, title = "OXXA GEAR Report") {
    if (typeof window.jspdf === 'undefined') {
        alert("PDF export library is not loaded. Please try again or refresh the page.");
        return;
    }
    
    if (!data || !data.length) {
        alert("No data available to export.");
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape'); // use landscape for tables with many columns
    
    doc.setFontSize(18);
    doc.text(title, 14, 22);
    doc.setFontSize(11);
    doc.setTextColor(100);
    doc.text(`Generated on: ${getFormattedDate()}`, 14, 30);
    
    const headers = Object.keys(data[0]);
    const body = data.map(row => headers.map(h => {
        const val = row[h];
        return val === null || val === undefined ? '' : String(val);
    }));

    doc.autoTable({
        startY: 36,
        head: [headers],
        body: body,
        theme: 'grid',
        headStyles: { fillColor: [0, 102, 255] },
        styles: { fontSize: 8 }
    });

    doc.save(`${filename}-${getFormattedDate()}.pdf`);
}

/**
 * Helper to fetch data and trigger export
 */
async function handleExport(type, format, endpointUrl, summaryData = {}, buttonElement = null) {
    try {
        if (buttonElement) {
            buttonElement.dataset.originalText = buttonElement.innerHTML;
            buttonElement.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Exporting...`;
            buttonElement.disabled = true;
        }

        const response = await fetch(endpointUrl);
        const result = await response.json();
        
        if (result.error) {
            alert(result.error);
            return;
        }

        const dataRows = result.data;
        const storeName = result.business_name_clean || 'store';
        
        if (!dataRows || !dataRows.length) {
            alert("No data found for the selected criteria.");
            return;
        }
        
        const filename = `oxxa-gear-${storeName}-${type}`;

        if (format === 'csv') {
            exportToCSV(dataRows, filename);
        } else if (format === 'pdf') {
            exportToPDF(dataRows, filename, `OXXA GEAR - ${type.toUpperCase()}`);
        }
        
        closeExportModal();

    } catch (e) {
        console.error("Export error:", e);
        alert("An error occurred during export.");
    } finally {
        if (buttonElement) {
            buttonElement.innerHTML = buttonElement.dataset.originalText;
            buttonElement.disabled = false;
        }
    }
}

/**
 * Export Modal Logic
 */
function createExportModal() {
    if (document.getElementById('exportModal')) return;

    const modalHTML = `
    <div id="exportModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-md mx-4 shadow-2xl transform transition-all">
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900" id="exportModalTitle">Export Data</h3>
                <button onclick="closeExportModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-5">
                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">From Date</label>
                        <input type="date" id="exportDateFrom" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-[#0066FF] focus:ring-2 focus:ring-[#0066FF]/20 transition-all outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">To Date</label>
                        <input type="date" id="exportDateTo" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-[#0066FF] focus:ring-2 focus:ring-[#0066FF]/20 transition-all outline-none">
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select id="exportStatus" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:border-[#0066FF] focus:ring-2 focus:ring-[#0066FF]/20 transition-all outline-none appearance-none bg-white">
                        <option value="">All Statuses</option>
                        <!-- Options injected dynamically -->
                    </select>
                </div>

                <!-- Format Selection -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Export Format</label>
                    <div class="flex gap-3">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="exportFormat" value="csv" class="peer sr-only" checked>
                            <div class="p-3 text-center rounded-xl border-2 border-gray-100 peer-checked:border-[#0066FF] peer-checked:bg-[#0066FF]/5 text-gray-600 peer-checked:text-[#0066FF] font-medium transition-all">
                                <i class="fas fa-file-csv mr-2"></i>CSV
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="exportFormat" value="pdf" class="peer sr-only">
                            <div class="p-3 text-center rounded-xl border-2 border-gray-100 peer-checked:border-[#ef4444] peer-checked:bg-[#ef4444]/5 text-gray-600 peer-checked:text-[#ef4444] font-medium transition-all">
                                <i class="fas fa-file-pdf mr-2"></i>PDF
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="p-6 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                <button onclick="closeExportModal()" class="px-5 py-2.5 text-gray-600 font-medium hover:bg-gray-100 rounded-xl transition-colors">Cancel</button>
                <button id="exportConfirmBtn" onclick="processExport()" class="px-6 py-2.5 bg-gray-900 text-white font-medium rounded-xl hover:bg-gray-800 transition-colors shadow-lg shadow-gray-900/20">
                    Download <i class="fas fa-download ml-2"></i>
                </button>
            </div>
        </div>
    </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

let currentExportType = '';

function openExportModal(type, title, defaultFormat = 'csv', statuses = []) {
    createExportModal();
    currentExportType = type;
    
    document.getElementById('exportModalTitle').innerText = `Export ${title}`;
    document.querySelector(`input[name="exportFormat"][value="${defaultFormat}"]`).checked = true;
    
    const statusSelect = document.getElementById('exportStatus');
    statusSelect.innerHTML = '<option value="">All Statuses</option>';
    statuses.forEach(s => {
        statusSelect.innerHTML += `<option value="${s.value}">${s.label}</option>`;
    });

    document.getElementById('exportModal').classList.remove('hidden');
    document.getElementById('exportModal').classList.add('flex');
}

function closeExportModal() {
    const modal = document.getElementById('exportModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function processExport() {
    const dateFrom = document.getElementById('exportDateFrom').value;
    const dateTo = document.getElementById('exportDateTo').value;
    const status = document.getElementById('exportStatus').value;
    const format = document.querySelector('input[name="exportFormat"]:checked').value;
    const btn = document.getElementById('exportConfirmBtn');

    // Build URL
    let url = `../Backend/seller-export.php?type=${currentExportType}`;
    if (dateFrom) url += `&date_from=${dateFrom}`;
    if (dateTo) url += `&date_to=${dateTo}`;
    if (status) url += `&status=${status}`;

    // Summary logic could be extended here based on currentExportType if needed
    const summaryData = {};

    handleExport(currentExportType, format, url, summaryData, btn);
}

