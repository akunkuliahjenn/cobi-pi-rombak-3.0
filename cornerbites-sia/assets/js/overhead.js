// Global variables
let searchOverheadTimeout;
let searchLaborTimeout;
let currentOverheadPage = 1;
let currentLaborPage = 1;

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
    // Hapus semua karakter non-digit
    let value = element.value.replace(/[^0-9]/g, '');

    // Jika kosong, biarkan kosong
    if (value === '') {
        element.value = '';
        return;
    }

    // Format dengan titik sebagai pemisah ribuan
    let formatted = formatNumber(parseInt(value));
    element.value = formatted;
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

    // Scroll to form overhead
    document.getElementById('overhead_form_title').scrollIntoView({ behavior: 'smooth', block: 'center' });
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

    // Scroll to form labor
    document.getElementById('labor_form_title').scrollIntoView({ behavior: 'smooth', block: 'center' });
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
    
    // Simpan current page
    currentLaborPage = page;

    // Update URL
    updateURL({
        search_labor: searchValue,
        limit_labor: limitValue,
        page_labor: page
    });

    const params = new URLSearchParams({
        search_labor: searchValue,
        limit_labor: limitValue,
        page_labor: page,
        ajax: 'labor'
    });

    // Show loading
    container.innerHTML = '<div class="flex justify-center items-center py-12"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div><span class="ml-2 text-gray-600">Memuat...</span></div>';

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

// Edit Overhead function
function editOverhead(overhead) {
    document.getElementById('overhead_id_to_edit').value = overhead.id;
    document.getElementById('overhead_name').value = overhead.name;
    document.getElementById('overhead_amount').value = numberFormat(overhead.amount);
    document.getElementById('overhead_description').value = overhead.description || '';

    // Update form title and button
    document.getElementById('overhead_form_title').textContent = 'Edit Biaya Overhead';
    document.getElementById('overhead_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
        </svg>
        Update Overhead
    `;
    document.getElementById('overhead_submit_button').classList.remove('bg-blue-600', 'hover:bg-blue-700');
    document.getElementById('overhead_submit_button').classList.add('bg-orange-600', 'hover:bg-orange-700');
    document.getElementById('overhead_cancel_edit_button').classList.remove('hidden');
}

// Reset Overhead form
function resetOverheadForm() {
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
    document.getElementById('overhead_submit_button').classList.remove('bg-orange-600', 'hover:bg-orange-700');
    document.getElementById('overhead_submit_button').classList.add('bg-blue-600', 'hover:bg-blue-700');
    document.getElementById('overhead_cancel_edit_button').classList.add('hidden');
}

// Edit Labor function
function editLabor(labor) {
    document.getElementById('labor_id_to_edit').value = labor.id;
    document.getElementById('labor_position_name').value = labor.position_name;
    document.getElementById('labor_hourly_rate').value = numberFormat(labor.hourly_rate);

    // Update form title and button
    document.getElementById('labor_form_title').textContent = 'Edit Posisi Tenaga Kerja';
    document.getElementById('labor_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
        </svg>
        Update Posisi
    `;
    document.getElementById('labor_submit_button').classList.remove('bg-green-600', 'hover:bg-green-700');
    document.getElementById('labor_submit_button').classList.add('bg-orange-600', 'hover:bg-orange-700');
    document.getElementById('labor_cancel_edit_button').classList.remove('hidden');
}

// Reset Labor form
function resetLaborForm() {
    document.getElementById('labor_id_to_edit').value = '';
    document.getElementById('labor_position_name').value = '';
    document.getElementById('labor_hourly_rate').value = '';

    document.getElementById('labor_form_title').textContent = 'Tambah Posisi Tenaga Kerja Baru';
    document.getElementById('labor_submit_button').innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Tambah Posisi
    `;
    document.getElementById('labor_submit_button').classList.remove('bg-orange-600', 'hover:bg-orange-700');
    document.getElementById('labor_submit_button').classList.add('bg-green-600', 'hover:bg-green-700');
    document.getElementById('labor_cancel_edit_button').classList.add('hidden');
}

// Delete functions
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

// Number formatting function
function numberFormat(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

// Auto format input numbers
document.addEventListener('DOMContentLoaded', function() {
    // Format overhead amount input
    const overheadAmountInput = document.getElementById('overhead_amount');
    if (overheadAmountInput) {
        // Format input saat user mengetik
        overheadAmountInput.addEventListener('input', function(e) {
            formatRupiahInput(e.target);
        });
    }

    // Format labor hourly rate input
    const laborRateInput = document.getElementById('labor_hourly_rate');
    if (laborRateInput) {
        // Format input saat user mengetik
        laborRateInput.addEventListener('input', function(e) {
            formatRupiahInput(e.target);
        });
    }

    // Setup search functionality
    const searchOverheadInput = document.getElementById('search-overhead-input');
    const limitOverheadSelect = document.getElementById('limit-overhead-select');
    const searchLaborInput = document.getElementById('search-labor-input');
    const limitLaborSelect = document.getElementById('limit-labor-select');

    if (searchOverheadInput) {
        let searchTimeout;
        searchOverheadInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadOverheadData(1);
            }, 500);
        });
    }

    if (limitOverheadSelect) {
        limitOverheadSelect.addEventListener('change', function() {
            loadOverheadData(1);
        });
    }

    if (searchLaborInput) {
        let searchTimeout;
        searchLaborInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadLaborData(1);
            }, 500);
        });
    }

    if (limitLaborSelect) {
        limitLaborSelect.addEventListener('change', function() {
            loadLaborData(1);
        });
    }

    // Setup cancel buttons
    const overheadCancelButton = document.getElementById('overhead_cancel_edit_button');
    if (overheadCancelButton) {
        overheadCancelButton.addEventListener('click', resetOverheadForm);
    }

    const laborCancelButton = document.getElementById('labor_cancel_edit_button');
    if (laborCancelButton) {
        laborCancelButton.addEventListener('click', resetLaborForm);
    }
});

// Tab Navigation Functions
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active styles from all tabs
    document.querySelectorAll('[id^="tab-"]').forEach(tab => {
        tab.classList.remove('border-blue-500', 'text-blue-600');
        tab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    });

    // Show selected tab content
    document.getElementById(`content-${tabName}`).classList.remove('hidden');

    // Add active styles to selected tab
    const activeTab = document.getElementById(`tab-${tabName}`);
    activeTab.classList.add('border-blue-500', 'text-blue-600');
    activeTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
}

// Allocation method handling
document.addEventListener('DOMContentLoaded', function() {
    const allocationRadios = document.querySelectorAll('input[name="allocation_method"]');
    const productionInput = document.getElementById('production-input');
    const laborInput = document.getElementById('labor-input');

    if (allocationRadios.length > 0) {
        allocationRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (productionInput && laborInput) {
                    productionInput.classList.add('hidden');
                    laborInput.classList.add('hidden');

                    if (this.value === 'production') {
                        productionInput.classList.remove('hidden');
                    } else if (this.value === 'labor_hours') {
                        laborInput.classList.remove('hidden');
                    }
                }
            });
        });
    }
});

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
    
    // Simpan current page
    currentOverheadPage = page;

    // Update URL
    updateURL({
        search_overhead: searchValue,
        limit_overhead: limitValue,
        page_overhead: page
    });

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

// Function untuk scroll ke form
function scrollToForm() {
    const formsSection = document.querySelector('.grid.grid-cols-1.lg\\:grid-cols-2');
    if (formsSection) {
        formsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Update URL tanpa reload halaman
function updateURL(params) {
    const url = new URL(window.location);
    Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            url.searchParams.set(key, params[key]);
        } else {
            url.searchParams.delete(key);
        }
    });
    window.history.replaceState({}, '', url);
}

// Make functions global for pagination links
window.loadOverheadData = loadOverheadData;
window.loadLaborData = loadLaborData;
window.scrollToForm = scrollToForm;

// Format currency input with automatic thousand separators
document.addEventListener('DOMContentLoaded', function() {
    // Setup format input untuk overhead amount
    const amountInput = document.getElementById('overhead_amount');
    if (amountInput) {
        // Format input saat user mengetik
        amountInput.addEventListener('input', function(e) {
            formatRupiahInput(e.target);
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
                
                // Simpan state pagination sebelum submit
                localStorage.setItem('overheadPage', currentOverheadPage);
                localStorage.setItem('overheadLimit', document.getElementById('limit-overhead-select').value);
                localStorage.setItem('overheadSearch', document.getElementById('search-overhead-input').value);
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
                
                // Simpan state pagination sebelum submit
                localStorage.setItem('laborPage', currentLaborPage);
                localStorage.setItem('laborLimit', document.getElementById('limit-labor-select').value);
                localStorage.setItem('laborSearch', document.getElementById('search-labor-input').value);
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

    // Setup event listeners untuk labor search
    const searchLaborInput = document.getElementById('search-labor-input');
    const limitLaborSelect = document.getElementById('limit-labor-select');

    // Real-time search untuk overhead dengan debouncing
    if (searchOverheadInput) {
        searchOverheadInput.addEventListener('input', function() {
            clearTimeout(searchOverheadTimeout);
            searchOverheadTimeout = setTimeout(() => {
                currentOverheadPage = 1; // Reset ke page 1 saat search
                loadOverheadData(1);
            }, 500);
        });
    }

    // Real-time search untuk labor dengan debouncing
    if (searchLaborInput) {
        searchLaborInput.addEventListener('input', function() {
            clearTimeout(searchLaborTimeout);
            searchLaborTimeout = setTimeout(() => {
                currentLaborPage = 1; // Reset ke page 1 saat search
                loadLaborData(1);
            }, 500);
        });
    }

    // Event listeners untuk limit select
    if (limitOverheadSelect) {
        limitOverheadSelect.addEventListener('change', function() {
            currentOverheadPage = 1; // Reset ke page 1 saat ubah limit
            loadOverheadData(1);
        });
    }

    if (limitLaborSelect) {
        limitLaborSelect.addEventListener('change', function() {
            currentLaborPage = 1; // Reset ke page 1 saat ubah limit
            loadLaborData(1);
        });
    }
    
    // Restore pagination state setelah reload (misalnya setelah edit)
    if (localStorage.getItem('overheadPage')) {
        const savedPage = parseInt(localStorage.getItem('overheadPage'));
        const savedLimit = localStorage.getItem('overheadLimit');
        const savedSearch = localStorage.getItem('overheadSearch');
        
        if (savedLimit && limitOverheadSelect) {
            limitOverheadSelect.value = savedLimit;
        }
        if (savedSearch && searchOverheadInput) {
            searchOverheadInput.value = savedSearch;
        }
        
        setTimeout(() => {
            loadOverheadData(savedPage);
        }, 100);
        
        // Clear dari localStorage
        localStorage.removeItem('overheadPage');
        localStorage.removeItem('overheadLimit');
        localStorage.removeItem('overheadSearch');
    }
    
    if (localStorage.getItem('laborPage')) {
        const savedPage = parseInt(localStorage.getItem('laborPage'));
        const savedLimit = localStorage.getItem('laborLimit');
        const savedSearch = localStorage.getItem('laborSearch');
        
        if (savedLimit && limitLaborSelect) {
            limitLaborSelect.value = savedLimit;
        }
        if (savedSearch && searchLaborInput) {
            searchLaborInput.value = savedSearch;
        }
        
        setTimeout(() => {
            loadLaborData(savedPage);
        }, 100);
        
        // Clear dari localStorage
        localStorage.removeItem('laborPage');
        localStorage.removeItem('laborLimit');
        localStorage.removeItem('laborSearch');
    }
});