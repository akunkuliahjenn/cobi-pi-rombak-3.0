// Global variables
let searchOverheadTimeout;
let searchLaborTimeout;

// Reset form overhead
function resetOverheadForm() {
    const form = document.querySelector('form[action*="simpan_overhead"]');
    if (form) {
        form.reset(); // Reset semua input dalam form
    }
    
    document.getElementById('overhead_id_to_edit').value = '';
    document.getElementById('overhead_name').value = '';
    document.getElementById('overhead_amount').value = '';
    document.getElementById('overhead_description').value = '';
    document.getElementById('overhead_form_title').textContent = 'Tambah Biaya Overhead Baru';
    document.getElementById('overhead_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Tambah Overhead
    `;
    document.getElementById('overhead_cancel_edit_button').classList.add('hidden');
}

// Reset form labor
function resetLaborForm() {
    const form = document.querySelector('form[action*="simpan_overhead"][method="POST"]');
    if (form) {
        // Hanya reset input labor, bukan semua form
        document.getElementById('labor_id_to_edit').value = '';
        document.getElementById('labor_position_name').value = '';
        document.getElementById('labor_hourly_rate').value = '';
    }
    
    document.getElementById('labor_form_title').textContent = 'Tambah Posisi Tenaga Kerja Baru';
    document.getElementById('labor_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Tambah Posisi
    `;
    document.getElementById('labor_cancel_edit_button').classList.add('hidden');
}

// Format number function (konsisten dengan produk.js) - tanpa desimal
function formatNumber(num) {
    return new Intl.NumberFormat('id-ID', { 
        minimumFractionDigits: 0, 
        maximumFractionDigits: 0 
    }).format(num);
}

// Format input dengan pemisah ribuan otomatis
function formatRupiahInput(element) {
    // Simpan posisi cursor
    let cursorPosition = element.selectionStart;
    let oldValue = element.value;
    
    // Hapus semua karakter non-digit
    let value = element.value.replace(/[^0-9]/g, '');

    if (value === '') {
        element.value = '';
        return;
    }

    // Format dengan titik sebagai pemisah ribuan
    let formatted = formatNumber(parseInt(value));
    element.value = formatted;
    
    // Restore cursor position dengan adjustment untuk pemisah yang ditambahkan
    let newCursorPosition = cursorPosition;
    if (formatted.length > oldValue.length) {
        newCursorPosition = cursorPosition + (formatted.length - oldValue.length);
    }
    
    // Set cursor position
    setTimeout(() => {
        element.setSelectionRange(newCursorPosition, newCursorPosition);
    }, 0);
}

// Edit overhead
function editOverhead(overhead) {
    document.getElementById('overhead_id_to_edit').value = overhead.id;
    document.getElementById('overhead_name').value = overhead.name;

    // Format amount value untuk editing - langsung set dengan format yang benar
    const amountInput = document.getElementById('overhead_amount');
    const amountValue = parseInt(overhead.amount);
    const formattedAmount = formatNumber(amountValue);
    amountInput.value = formattedAmount;

    document.getElementById('overhead_description').value = overhead.description || '';
    document.getElementById('overhead_form_title').textContent = 'Edit Biaya Overhead';
    document.getElementById('overhead_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Update Overhead
    `;
    document.getElementById('overhead_cancel_edit_button').classList.remove('hidden');

    // Scroll to form
    document.getElementById('overhead_form_title').scrollIntoView({ behavior: 'smooth' });
}

// Make edit functions global
window.editOverhead = editOverhead;

// Edit labor
function editLabor(labor) {
    document.getElementById('labor_id_to_edit').value = labor.id;
    document.getElementById('labor_position_name').value = labor.position_name;

    // Format hourly rate value untuk editing - langsung set dengan format yang benar
    const rateInput = document.getElementById('labor_hourly_rate');
    const rateValue = parseInt(labor.hourly_rate);
    const formattedRate = formatNumber(rateValue);
    rateInput.value = formattedRate;

    document.getElementById('labor_form_title').textContent = 'Edit Posisi Tenaga Kerja';
    document.getElementById('labor_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Update Posisi
    `;
    document.getElementById('labor_cancel_edit_button').classList.remove('hidden');

    // Scroll to form
    document.getElementById('labor_form_title').scrollIntoView({ behavior: 'smooth' });
}

// Make edit and delete functions global
window.editLabor = editLabor;
window.deleteOverhead = deleteOverhead;
window.deleteLabor = deleteLabor;

// Delete overhead
function deleteOverhead(id, name) {
    if (confirm(`Apakah Anda yakin ingin menghapus biaya overhead "${name}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/cornerbites-sia/process/hapus_overhead.php';

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'type';
        typeInput.value = 'overhead';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'overhead_id';
        idInput.value = id;

        form.appendChild(typeInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Delete labor
function deleteLabor(id, name) {
    if (confirm(`Apakah Anda yakin ingin menghapus posisi "${name}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/cornerbites-sia/process/hapus_overhead.php';

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'type';
        typeInput.value = 'labor';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'labor_id';
        idInput.value = id;

        form.appendChild(typeInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Function untuk load overhead data dengan AJAX
function loadOverheadData(page = 1) {
    const searchInput = document.getElementById('search-overhead-input');
    const limitSelect = document.getElementById('limit-overhead-select');
    const container = document.getElementById('overhead-container');

    if (!searchInput || !limitSelect || !container) {
        console.error('Element tidak ditemukan untuk overhead');
        return;
    }

    const searchValue = searchInput.value;
    const limitValue = limitSelect.value;

    const params = new URLSearchParams({
        search_overhead: searchValue,
        limit_overhead: limitValue,
        page_overhead: page,
        ajax: 'overhead'
    });

    // Show loading
    container.innerHTML = '<div class="flex justify-center items-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div><span class="ml-2 text-gray-600">Memuat...</span></div>';

    fetch(`/cornerbites-sia/pages/overhead_management.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading overhead data:', error);
            container.innerHTML = '<div class="text-center py-12 text-red-600">Terjadi kesalahan saat memuat data overhead.</div>';
        });
}

// Function untuk load labor data dengan AJAX
function loadLaborData(page = 1) {
    const searchInput = document.getElementById('search-labor-input');
    const limitSelect = document.getElementById('limit-labor-select');
    const container = document.getElementById('labor-container');

    if (!searchInput || !limitSelect || !container) {
        console.error('Element tidak ditemukan untuk labor');
        return;
    }

    const searchValue = searchInput.value;
    const limitValue = limitSelect.value;

    const params = new URLSearchParams({
        search_labor: searchValue,
        limit_labor: limitValue,
        page_labor: page,
        ajax: 'labor'
    });

    // Show loading
    container.innerHTML = '<div class="flex justify-center items-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div><span class="ml-2 text-gray-600">Memuat...</span></div>';

    fetch(`/cornerbites-sia/pages/overhead_management.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading labor data:', error);
            container.innerHTML = '<div class="text-center py-12 text-red-600">Terjadi kesalahan saat memuat data tenaga kerja.</div>';
        });
}

// Make functions global for pagination links
window.loadOverheadData = loadOverheadData;
window.loadLaborData = loadLaborData;

// Format currency input with automatic thousand separators
document.addEventListener('DOMContentLoaded', function() {
    // Only load data via AJAX if URL contains reload parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('reload') === '1') {
        // Load initial data automatically after form submission
        loadOverheadData(1);
        loadLaborData(1);
    }

    // Setup format input untuk overhead amount
    const amountInput = document.getElementById('overhead_amount');
    if (amountInput) {
        // Format input saat user mengetik
        amountInput.addEventListener('input', function(e) {
            formatRupiahInput(e.target);
        });

        // Format juga saat blur dan keyup untuk memastikan format selalu terapply
        amountInput.addEventListener('blur', function(e) {
            if (e.target.value && !isNaN(e.target.value.replace(/[^\d]/g, ''))) {
                formatRupiahInput(e.target);
            }
        });

        amountInput.addEventListener('keyup', function(e) {
            if (e.target.value && !isNaN(e.target.value.replace(/[^\d]/g, ''))) {
                formatRupiahInput(e.target);
            }
        });

        // Convert ke number saat submit
        const overheadForm = amountInput.closest('form');
        if (overheadForm) {
            overheadForm.addEventListener('submit', function(e) {
                // Convert formatted number back to raw number for submission
                const rawValue = amountInput.value.replace(/[^\d]/g, '');
                if (rawValue === '' || rawValue === '0') {
                    e.preventDefault();
                    alert('Jumlah biaya harus diisi dan lebih dari 0!');
                    return false;
                }
                amountInput.value = rawValue;
            });
        }
    }

    // Setup format input untuk labor hourly rate
    const hourlyRateInput = document.getElementById('labor_hourly_rate');
    if (hourlyRateInput) {
        // Format input saat user mengetik
        hourlyRateInput.addEventListener('input', function(e) {
            formatRupiahInput(e.target);
        });

        // Format juga saat blur dan keyup untuk memastikan format selalu terapply
        hourlyRateInput.addEventListener('blur', function(e) {
            if (e.target.value && !isNaN(e.target.value.replace(/[^\d]/g, ''))) {
                formatRupiahInput(e.target);
            }
        });

        hourlyRateInput.addEventListener('keyup', function(e) {
            if (e.target.value && !isNaN(e.target.value.replace(/[^\d]/g, ''))) {
                formatRupiahInput(e.target);
            }
        });

        // Convert ke number saat submit
        const laborForm = hourlyRateInput.closest('form');
        if (laborForm) {
            laborForm.addEventListener('submit', function(e) {
                // Convert formatted number back to raw number for submission
                const rawValue = hourlyRateInput.value.replace(/[^\d]/g, '');
                if (rawValue === '' || rawValue === '0') {
                    e.preventDefault();
                    alert('Upah per jam harus diisi dan lebih dari 0!');
                    return false;
                }
                hourlyRateInput.value = rawValue;
            });
        }
    }

    // Setup tombol batal edit untuk overhead
    const overheadCancelBtn = document.getElementById('overhead_cancel_edit_button');
    if (overheadCancelBtn) {
        overheadCancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            resetOverheadForm();
        });
    }

    // Setup tombol batal edit untuk labor
    const laborCancelBtn = document.getElementById('labor_cancel_edit_button');
    if (laborCancelBtn) {
        laborCancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            resetLaborForm();
        });
    }

    // Setup event listeners untuk overhead search
    const searchOverheadInput = document.getElementById('search-overhead-input');
    const limitOverheadSelect = document.getElementById('limit-overhead-select');
    const filterOverheadBtn = document.getElementById('filter-overhead-btn');
    const resetOverheadBtn = document.getElementById('reset-overhead-btn');

    // Setup event listeners untuk labor search
    const searchLaborInput = document.getElementById('search-labor-input');
    const limitLaborSelect = document.getElementById('limit-labor-select');
    const filterLaborBtn = document.getElementById('filter-labor-btn');
    const resetLaborBtn = document.getElementById('reset-labor-btn');

    // Real-time search untuk overhead dengan debouncing
    if (searchOverheadInput) {
        searchOverheadInput.addEventListener('input', function() {
            clearTimeout(searchOverheadTimeout);
            searchOverheadTimeout = setTimeout(() => {
                // Only search if user actually typed something
                const currentValue = this.value.trim();
                const initialValue = this.getAttribute('value') || '';
                if (currentValue !== initialValue) {
                    loadOverheadData(1);
                }
            }, 500);
        });
    }

    // Real-time search untuk labor dengan debouncing
    if (searchLaborInput) {
        searchLaborInput.addEventListener('input', function() {
            clearTimeout(searchLaborTimeout);
            searchLaborTimeout = setTimeout(() => {
                // Only search if user actually typed something
                const currentValue = this.value.trim();
                const initialValue = this.getAttribute('value') || '';
                if (currentValue !== initialValue) {
                    loadLaborData(1);
                }
            }, 500);
        });
    }

    // Event listeners untuk overhead
    if (filterOverheadBtn) {
        filterOverheadBtn.addEventListener('click', function() {
            loadOverheadData(1);
        });
    }

    if (limitOverheadSelect) {
        limitOverheadSelect.addEventListener('change', function() {
            loadOverheadData(1);
        });
    }

    if (resetOverheadBtn) {
        resetOverheadBtn.addEventListener('click', function() {
            searchOverheadInput.value = '';
            limitOverheadSelect.value = '10';
            // Reload page to show all data
            window.location.href = '/cornerbites-sia/pages/overhead_management.php';
        });
    }

    // Event listeners untuk labor
    if (filterLaborBtn) {
        filterLaborBtn.addEventListener('click', function() {
            loadLaborData(1);
        });
    }

    if (limitLaborSelect) {
        limitLaborSelect.addEventListener('change', function() {
            loadLaborData(1);
        });
    }

    if (resetLaborBtn) {
        resetLaborBtn.addEventListener('click', function() {
            searchLaborInput.value = '';
            limitLaborSelect.value = '10';
            // Reload page to show all data
            window.location.href = '/cornerbites-sia/pages/overhead_management.php';
        });
    }
});