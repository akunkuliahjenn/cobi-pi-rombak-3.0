
// bahan_baku.js
// JavaScript functions for bahan baku management with AJAX search

const unitOptions = ['kg', 'gram', 'liter', 'ml', 'pcs', 'buah', 'roll', 'meter', 'box', 'botol', 'lembar'];
const typeOptions = ['bahan', 'kemasan'];
const validLimits = [5, 10, 15, 20, 25];
const defaultLimit = 5;

// Variables untuk menyimpan posisi scroll
let currentScrollPosition = 0;

// Function to scroll to form
function scrollToForm() {
    const formElement = document.querySelector('form[action="../process/simpan_bahan_baku.php"]');
    if (formElement) {
        formElement.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
}

// Currency formatting for price input
document.addEventListener('DOMContentLoaded', function() {
    // Restore limit states on page load
    restoreLimitStates();
    
    const priceInput = document.getElementById('purchase_price_per_unit');

    if (priceInput) {
        // Format input saat user mengetik
        priceInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                e.target.value = formatNumber(value);
            }
        });

        // Convert ke number saat submit
        const form = priceInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Save current limit states before form submission
                saveLimitStates();
                
                // Update hidden fields with current limit values
                const bahanLimit = document.getElementById('bahan_limit');
                const kemasanLimit = document.getElementById('kemasan_limit');
                const hiddenBahanLimit = document.getElementById('hidden_bahan_limit');
                const hiddenKemasanLimit = document.getElementById('hidden_kemasan_limit');
                
                if (bahanLimit && hiddenBahanLimit) {
                    hiddenBahanLimit.value = bahanLimit.value;
                }
                if (kemasanLimit && hiddenKemasanLimit) {
                    hiddenKemasanLimit.value = kemasanLimit.value;
                }
                
                // Convert formatted price back to number
                const currentValue = priceInput.value.replace(/[^\d]/g, '');
                priceInput.value = currentValue;

                // Let the form submit normally
                return true;
            });
        }
    }

    // Dynamic label and button update based on type selection
    const typeSelect = document.getElementById('type');
    const purchaseSizeLabel = document.getElementById('purchase_size_label');
    const purchasePriceLabel = document.getElementById('purchase_price_label');
    const purchaseSizeHelp = document.getElementById('purchase_size_help');
    const purchasePriceHelp = document.getElementById('purchase_price_help');
    const submitButton = document.getElementById('submit_button');

    function updateLabelsBasedOnType(type) {
        if (type === 'bahan') {
            purchaseSizeLabel.textContent = 'Ukuran Beli Kemasan Bahan';
            purchasePriceLabel.textContent = 'Harga Beli Per Kemasan Bahan';
            purchaseSizeHelp.textContent = 'Isi per kemasan bahan yang Anda beli (sesuai satuan yang tertera di plastik kemasan yang anda beli)';
            purchasePriceHelp.textContent = 'Harga per kemasan bahan saat pembelian';
            submitButton.innerHTML = `
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Tambah Bahan
            `;
        } else {
            purchaseSizeLabel.textContent = 'Ukuran Beli Kemasan';
            purchasePriceLabel.textContent = 'Harga Beli Per Kemasan';
            purchaseSizeHelp.textContent = 'Isi per kemasan yang Anda beli (sesuai satuan yang tertera di kemasan yang anda beli)';
            purchasePriceHelp.textContent = 'Harga per kemasan saat pembelian';
            submitButton.innerHTML = `
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Tambah Kemasan
            `;
        }
    }

    if (typeSelect && purchaseSizeLabel && submitButton) {
        typeSelect.addEventListener('change', function() {
            updateLabelsBasedOnType(this.value);
        });

        // Set initial labels
        updateLabelsBasedOnType(typeSelect.value);
    }

    // Cancel edit button event
    const cancelButton = document.getElementById('cancel_edit_button');
    if (cancelButton) {
        cancelButton.addEventListener('click', resetForm);
    }

    // AJAX search implementation
    setupAjaxSearch();

    // Tambahkan event listener untuk menyimpan posisi scroll saat user berinteraksi
    const searchInputs = document.querySelectorAll('#search_raw, #search_kemasan');
    const limitSelects = document.querySelectorAll('#bahan_limit, #kemasan_limit');

    searchInputs.forEach(input => {
        input.addEventListener('focus', saveScrollPosition);
        input.addEventListener('input', saveScrollPosition);
    });

    limitSelects.forEach(select => {
        select.addEventListener('change', function() {
            saveScrollPosition();
            // Langsung trigger pencarian dengan limit baru
            const searchType = this.id === 'bahan_limit' ? 'raw' : 'kemasan';
            const searchInput = document.getElementById(searchType === 'raw' ? 'search_raw' : 'search_kemasan');
            const searchTerm = searchInput ? searchInput.value : '';

            // Clear current results immediately to show loading
            const containerId = searchType === 'raw' ? 'raw-materials-container' : 'packaging-materials-container';
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `
                    <div class="col-span-full flex justify-center items-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="ml-2 text-gray-600">Memperbarui tampilan...</span>
                    </div>
                `;
            }

            performAjaxSearch(searchType, searchTerm, this.value);
        });
    });
});

function formatNumber(num) {
    return parseInt(num).toLocaleString('id-ID');
}

function saveScrollPosition() {
    currentScrollPosition = window.pageYOffset;
}

function restoreScrollPosition() {
    window.scrollTo(0, currentScrollPosition);
}

function editBahanBaku(material) {
    // Save current limit states before editing
    saveLimitStates();
    
    // Scroll to form first
    const formTitle = document.getElementById('form-title');
    if (formTitle) {
        formTitle.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }

    // Fill form data
    document.getElementById('bahan_baku_id').value = material.id;
    document.getElementById('name').value = material.name;
    document.getElementById('brand').value = material.brand || '';
    document.getElementById('type').value = material.type;
    document.getElementById('unit').value = material.unit;

    // Format numbers without .00 for display - clean integer display
    const purchaseSize = parseFloat(material.default_package_quantity);
    document.getElementById('purchase_size').value = purchaseSize % 1 === 0 ? Math.round(purchaseSize).toString() : purchaseSize.toString();

    // Format price as integer and remove any decimal places for display
    const priceAsInt = Math.round(parseFloat(material.purchase_price_per_unit));
    document.getElementById('purchase_price_per_unit').value = priceAsInt.toLocaleString('id-ID');

    // Update labels and button based on type
    const purchaseSizeLabel = document.getElementById('purchase_size_label');
    const purchasePriceLabel = document.getElementById('purchase_price_label');
    const purchaseSizeHelp = document.getElementById('purchase_size_help');
    const purchasePriceHelp = document.getElementById('purchase_price_help');
    const submitButton = document.getElementById('submit_button');
    const cancelButton = document.getElementById('cancel_edit_button');

    if (material.type === 'bahan') {
        purchaseSizeLabel.textContent = 'Ukuran Beli Kemasan Bahan';
        purchasePriceLabel.textContent = 'Harga Beli Per Kemasan Bahan';
        purchaseSizeHelp.textContent = 'Isi per kemasan bahan yang Anda beli (sesuai satuan yang tertera di plastik kemasan yang anda beli)';
        purchasePriceHelp.textContent = 'Harga per kemasan bahan saat pembelian';
        document.getElementById('form-title').textContent = 'Edit Bahan';
        submitButton.innerHTML = `
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Update Bahan
        `;
    } else {
        purchaseSizeLabel.textContent = 'Ukuran Beli Kemasan';
        purchasePriceLabel.textContent = 'Harga Beli Per Kemasan';
        purchaseSizeHelp.textContent = 'Isi per kemasan yang Anda beli (sesuai satuan yang tertera di kemasan yang anda beli)';
        purchasePriceHelp.textContent = 'Harga per kemasan saat pembelian';
        document.getElementById('form-title').textContent = 'Edit Kemasan';
        submitButton.innerHTML = `
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Update Kemasan
        `;
    }

    submitButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
    submitButton.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
    cancelButton.classList.remove('hidden');

    // Focus pada nama field dengan delay minimal
    setTimeout(() => {
        const nameField = document.getElementById('name');
        if (nameField) {
            nameField.focus();
            nameField.select(); // Select text untuk editing yang lebih mudah
        }
    }, 300);
}

function resetForm() {
    // Save current limit states before reset
    saveLimitStates();
    
    document.getElementById('bahan_baku_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('brand').value = '';
    document.getElementById('type').value = typeOptions[0];
    document.getElementById('unit').value = unitOptions[0];
    document.getElementById('purchase_size').value = '';
    document.getElementById('purchase_price_per_unit').value = '';

    // Reset labels to default (bahan)
    const purchaseSizeLabel = document.getElementById('purchase_size_label');
    const purchasePriceLabel = document.getElementById('purchase_price_label');
    const purchaseSizeHelp = document.getElementById('purchase_size_help');
    const purchasePriceHelp = document.getElementById('purchase_price_help');

    purchaseSizeLabel.textContent = 'Ukuran Beli Kemasan Bahan';
    purchasePriceLabel.textContent = 'Harga Beli Per Kemasan Bahan';
    purchaseSizeHelp.textContent = 'Isi per kemasan bahan yang Anda beli (sesuai satuan yang tertera di plastik kemasan yang anda beli)';
    purchasePriceHelp.textContent = 'Harga per kemasan bahan saat pembelian';

    document.getElementById('form-title').textContent = 'Tambah Bahan Baku/Kemasan Baru';

    const submitButton = document.getElementById('submit_button');
    const cancelButton = document.getElementById('cancel_edit_button');

    submitButton.innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Tambah Bahan
    `;
    submitButton.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
    submitButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
    cancelButton.classList.add('hidden');
}

// AJAX search setup
function setupAjaxSearch() {
    let searchTimeoutRaw;
    let searchTimeoutKemasan;

    // Search for raw materials
    const searchRaw = document.getElementById('search_raw');
    const bahanLimit = document.getElementById('bahan_limit');

    if (searchRaw) {
        searchRaw.addEventListener('input', function() {
            const searchTerm = this.value;
            clearTimeout(searchTimeoutRaw);
            searchTimeoutRaw = setTimeout(() => {
                performAjaxSearch('raw', searchTerm, bahanLimit ? bahanLimit.value : defaultLimit);
            }, 300); // Reduced timeout for faster response
        });
    }

    // Search for packaging materials
    const searchKemasan = document.getElementById('search_kemasan');
    const kemasanLimit = document.getElementById('kemasan_limit');

    if (searchKemasan) {
        searchKemasan.addEventListener('input', function() {
            const searchTerm = this.value;
            clearTimeout(searchTimeoutKemasan);
            searchTimeoutKemasan = setTimeout(() => {
                performAjaxSearch('kemasan', searchTerm, kemasanLimit ? kemasanLimit.value : defaultLimit);
            }, 300); // Reduced timeout for faster response
        });
    }
}

function performAjaxSearch(type, searchTerm, limit) {
    const containerId = type === 'raw' ? 'raw-materials-container' : 'packaging-materials-container';
    const container = document.getElementById(containerId);

    if (!container) return;

    // Simpan posisi scroll sebelum AJAX
    saveScrollPosition();

    // Show loading dengan pesan yang lebih spesifik
    const loadingMessage = searchTerm ? 'Mencari...' : 'Memuat data...';
    if (container.innerHTML.indexOf('animate-spin') === -1) {
        container.innerHTML = `
            <div class="flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span class="ml-2 text-gray-600">${loadingMessage}</span>
            </div>
        `;
    }

    // Build URL parameters
    const params = new URLSearchParams();
    if (type === 'raw') {
        params.set('search_raw', searchTerm);
        params.set('bahan_limit', limit);
        params.set('ajax_type', 'raw');
    } else {
        params.set('search_kemasan', searchTerm);
        params.set('kemasan_limit', limit);
        params.set('ajax_type', 'kemasan');
    }
    params.set('ajax', '1');

    // Perform AJAX request
    fetch(`/cornerbites-sia/pages/bahan_baku.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            container.innerHTML = data;

            // Wait a bit for DOM to update, then check pagination
            setTimeout(() => {
                checkAndHidePagination(type, limit);
            }, 100);

            // Restore scroll position
            restoreScrollPosition();
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            container.innerHTML = `
                <div class="text-center py-12 text-red-600">
                    <svg class="w-16 h-16 mx-auto mb-4 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <p class="text-lg font-medium">Terjadi kesalahan saat memuat data</p>
                    <p class="text-sm">Silakan refresh halaman atau coba lagi nanti</p>
                </div>
            `;
        });
}

function hidePaginationIfNotNeeded(tabType, limit) {
    const totalCountElement = document.getElementById(`total-${tabType}-count`);
    if (!totalCountElement) return;

    const totalCount = parseInt(totalCountElement.textContent) || 0;
    const currentTab = document.getElementById(`content-${tabType}`);
    if (!currentTab) return;

    // Find pagination container in current tab
    const paginationContainer = currentTab.querySelector('.flex.items-center.justify-between.border-t');

    if (paginationContainer) {
        if (totalCount <= limit) {
            paginationContainer.style.display = 'none';
        } else {
            paginationContainer.style.display = 'flex';
        }
    }
}

function checkAndHidePagination(type, limit) {
    // Map type to the correct element ID
    const tabType = type === 'raw' ? 'raw' : 'kemasan';
    const totalCountElement = document.getElementById(`total-${tabType}-count`);
    
    if (!totalCountElement) {
        console.log('Total count element not found for:', tabType);
        return;
    }

    const totalCount = parseInt(totalCountElement.textContent) || 0;
    const limitInt = parseInt(limit);
    
    console.log(`Checking pagination for ${tabType}: total=${totalCount}, limit=${limitInt}`);
    
    // Find the correct content area
    const contentArea = document.getElementById(`content-${tabType === 'raw' ? 'bahan' : 'kemasan'}`);
    if (!contentArea) {
        console.log('Content area not found for:', tabType);
        return;
    }

    // Find pagination container
    const paginationContainer = contentArea.querySelector('.flex.items-center.justify-between.border-t');
    
    if (paginationContainer) {
        if (totalCount <= limitInt) {
            console.log(`Hiding pagination: ${totalCount} <= ${limitInt}`);
            paginationContainer.style.display = 'none';
        } else {
            console.log(`Showing pagination: ${totalCount} > ${limitInt}`);
            paginationContainer.style.display = 'flex';
        }
    } else {
        console.log('Pagination container not found');
    }
}

// Update pagination info for raw materials
function updateRawPaginationInfo(totalCount, limit) {
    // Use the centralized function to check and hide pagination
    setTimeout(() => {
        checkAndHidePagination('raw', limit);
    }, 50);
    console.log('Raw materials pagination updated:', { totalCount, limit });
}

// Update pagination info for kemasan
function updateKemasanPaginationInfo(totalCount, limit) {
    // Use the centralized function to check and hide pagination
    setTimeout(() => {
        checkAndHidePagination('kemasan', limit);
    }, 50);
    console.log('Kemasan pagination updated:', { totalCount, limit });
}

// Function to update total count display
function updateTotalCount(elementId, count) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = count;
    }
}

// Tab navigation functionality
function switchTab(tabType) {
    // Update tab buttons
    const tabBahan = document.getElementById('tab-bahan');
    const tabKemasan = document.getElementById('tab-kemasan');
    const contentBahan = document.getElementById('content-bahan');
    const contentKemasan = document.getElementById('content-kemasan');
    const badgeBahan = document.getElementById('badge-bahan');
    const badgeKemasan = document.getElementById('badge-kemasan');

    if (tabType === 'bahan') {
        // Activate bahan tab
        tabBahan.classList.add('bg-white', 'text-blue-600', 'shadow-sm');
        tabBahan.classList.remove('text-gray-500', 'hover:text-gray-700');
        tabBahan.setAttribute('aria-selected', 'true');

        // Deactivate kemasan tab
        tabKemasan.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        tabKemasan.classList.add('text-gray-500', 'hover:text-gray-700');
        tabKemasan.setAttribute('aria-selected', 'false');

        // Show/hide content
        contentBahan.classList.remove('hidden');
        contentKemasan.classList.add('hidden');

        // Update badge styles
        badgeBahan.classList.remove('bg-gray-100', 'text-gray-600');
        badgeBahan.classList.add('bg-blue-100', 'text-blue-800');
        badgeKemasan.classList.remove('bg-green-100', 'text-green-800');
        badgeKemasan.classList.add('bg-gray-100', 'text-gray-600');

    } else if (tabType === 'kemasan') {
        // Activate kemasan tab
        tabKemasan.classList.add('bg-white', 'text-green-600', 'shadow-sm');
        tabKemasan.classList.remove('text-gray-500', 'hover:text-gray-700');
        tabKemasan.setAttribute('aria-selected', 'true');

        // Deactivate bahan tab
        tabBahan.classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        tabBahan.classList.add('text-gray-500', 'hover:text-gray-700');
        tabBahan.setAttribute('aria-selected', 'false');

        // Show/hide content
        contentKemasan.classList.remove('hidden');
        contentBahan.classList.add('hidden');

        // Update badge styles
        badgeKemasan.classList.remove('bg-gray-100', 'text-gray-600');
        badgeKemasan.classList.add('bg-green-100', 'text-green-800');
        badgeBahan.classList.remove('bg-blue-100', 'text-blue-800');
        badgeBahan.classList.add('bg-gray-100', 'text-gray-600');
    }
}

// Update badge counts for tabs
function updateTabBadges(rawCount, kemasanCount) {
    const badgeBahan = document.getElementById('badge-bahan');
    const badgeKemasan = document.getElementById('badge-kemasan');

    if (badgeBahan) {
        badgeBahan.textContent = rawCount;
    }
    if (badgeKemasan) {
        badgeKemasan.textContent = kemasanCount;
    }
}

// Functions to save and restore limit states
function saveLimitStates() {
    const bahanLimit = document.getElementById('bahan_limit');
    const kemasanLimit = document.getElementById('kemasan_limit');
    
    if (bahanLimit) {
        localStorage.setItem('bahan_limit_state', bahanLimit.value);
    }
    if (kemasanLimit) {
        localStorage.setItem('kemasan_limit_state', kemasanLimit.value);
    }
    
    // Also save current active tab
    const activeTab = document.querySelector('[aria-selected="true"]');
    if (activeTab) {
        const tabType = activeTab.id.includes('bahan') ? 'bahan' : 'kemasan';
        localStorage.setItem('active_tab_state', tabType);
    }
}

function restoreLimitStates() {
    const bahanLimit = document.getElementById('bahan_limit');
    const kemasanLimit = document.getElementById('kemasan_limit');
    
    if (bahanLimit && localStorage.getItem('bahan_limit_state')) {
        bahanLimit.value = localStorage.getItem('bahan_limit_state');
    }
    if (kemasanLimit && localStorage.getItem('kemasan_limit_state')) {
        kemasanLimit.value = localStorage.getItem('kemasan_limit_state');
    }
    
    // Restore active tab
    const activeTabState = localStorage.getItem('active_tab_state');
    if (activeTabState) {
        switchTab(activeTabState);
    }
}

function clearLimitStates() {
    localStorage.removeItem('bahan_limit_state');
    localStorage.removeItem('kemasan_limit_state');
    localStorage.removeItem('active_tab_state');
}

// Make functions global
window.editBahanBaku = editBahanBaku;
window.resetForm = resetForm;
window.updateTotalCount = updateTotalCount;
window.switchTab = switchTab;
window.updateTabBadges = updateTabBadges;
window.saveLimitStates = saveLimitStates;
window.restoreLimitStates = restoreLimitStates;
window.clearLimitStates = clearLimitStates;
