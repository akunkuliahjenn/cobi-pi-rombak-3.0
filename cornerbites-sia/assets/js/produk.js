// Produk JavaScript Functions

// Format Rupiah function
function formatRupiah(element, hiddenInputId) {
    let value = element.value.replace(/[^0-9]/g, '');

    if (value === '') {
        element.value = '';
        document.getElementById(hiddenInputId).value = '';
        return;
    }

    // Format dengan titik sebagai pemisah ribuan
    let formatted = parseInt(value).toLocaleString('id-ID');
    element.value = formatted;
    document.getElementById(hiddenInputId).value = value;
}

// Helper function untuk format number tanpa mengubah input
function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

// JavaScript untuk mengisi form saat tombol edit diklik
function editProduct(product) {
    console.log('Edit product called:', product);
    
    // Set values ke form
    document.getElementById('product_id_to_edit').value = product.id;
    document.getElementById('product_name').value = product.name;

    // Handle unit dropdown
    const unitSelect = document.getElementById('unit');
    const customInput = document.getElementById('unit_custom');
    const unitOptions = ['pcs', 'porsi', 'bungkus', 'cup', 'botol', 'gelas', 'slice', 'pack', 'box', 'kg', 'gram', 'liter', 'ml'];

    if (unitOptions.includes(product.unit)) {
        unitSelect.value = product.unit;
        customInput.classList.add('hidden');
        customInput.required = false;
    } else {
        unitSelect.value = 'custom';
        customInput.value = product.unit;
        customInput.classList.remove('hidden');
        customInput.required = true;
    }

    // Format dan set harga jual - menampilkan harga lama dengan format yang benar
    const salePrice = parseFloat(product.sale_price);
    const formattedPrice = formatNumber(salePrice);
    document.getElementById('sale_price_display').value = formattedPrice;
    document.getElementById('sale_price').value = salePrice;

    // Update judul form
    const formTitle = document.getElementById('form_title');
    const formDescription = document.getElementById('form_description');

    if (formTitle) {
        formTitle.textContent = 'Edit Produk';
    }
    if (formDescription) {
        formDescription.textContent = 'Ubah detail produk yang sudah ada sesuai kebutuhan Anda.';
    }

    // Update tombol submit menjadi "Edit Produk"
    const submitButton = document.getElementById('submit_button');
    submitButton.innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
        Edit Produk
    `;
    
    // Ubah warna tombol submit ke biru untuk mode edit
    submitButton.classList.remove('bg-green-600', 'hover:bg-green-700', 'focus:ring-green-500');
    submitButton.classList.add('bg-blue-600', 'hover:bg-blue-700', 'focus:ring-blue-500');

    // Tampilkan tombol "Batal Edit"
    const cancelButton = document.getElementById('cancel_edit_button');
    if (cancelButton) {
        cancelButton.classList.remove('hidden');
        cancelButton.style.display = 'inline-flex';
        cancelButton.style.visibility = 'visible';
    }

    // Scroll ke form dan focus pada field pertama
    const form = document.querySelector('form[action="/cornerbites-sia/process/simpan_produk.php"]');
    if (form) {
        form.scrollIntoView({ behavior: 'smooth' });
        setTimeout(() => {
            document.getElementById('product_name').focus();
        }, 500);
    }
}

// JavaScript untuk mereset form ke mode tambah
function resetForm() {
    // Reset semua field
    document.getElementById('product_id_to_edit').value = '';
    document.getElementById('product_name').value = '';

    // Reset unit dropdown
    const unitSelect = document.getElementById('unit');
    const customInput = document.getElementById('unit_custom');
    unitSelect.value = '';
    customInput.value = '';
    customInput.classList.add('hidden');
    customInput.required = false;

    // Reset display dan hidden inputs untuk harga
    document.getElementById('sale_price_display').value = '';
    document.getElementById('sale_price').value = '';

    // Reset judul form ke mode tambah
    const formTitle = document.getElementById('form_title');
    const formDescription = document.getElementById('form_description');

    if (formTitle) {
        formTitle.textContent = 'Tambah Produk Baru';
    }
    if (formDescription) {
        formDescription.textContent = 'Isi detail produk baru Anda atau gunakan form ini untuk mengedit produk yang sudah ada.';
    }

    // Reset tombol submit ke mode tambah
    const submitButton = document.getElementById('submit_button');
    submitButton.innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Tambah Produk
    `;
    
    // Ubah warna tombol submit kembali ke hijau untuk mode tambah
    submitButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'focus:ring-blue-500');
    submitButton.classList.add('bg-green-600', 'hover:bg-green-700', 'focus:ring-green-500');

    // Sembunyikan tombol "Batal Edit"
    const cancelButton = document.getElementById('cancel_edit_button');
    if (cancelButton) {
        cancelButton.classList.add('hidden');
        cancelButton.style.display = 'none';
        cancelButton.style.visibility = 'hidden';
    }

    // Scroll ke form dan focus pada field pertama
    const form = document.querySelector('form[action="/cornerbites-sia/process/simpan_produk.php"]');
    if (form) {
        form.scrollIntoView({ behavior: 'smooth' });
        setTimeout(() => {
            document.getElementById('product_name').focus();
        }, 500);
    }
}

// Toggle custom unit input
function toggleCustomUnit() {
    const unitSelect = document.getElementById('unit');
    const customInput = document.getElementById('unit_custom');

    if (unitSelect.value === 'custom') {
        customInput.classList.remove('hidden');
        customInput.required = true;
        customInput.focus();
    } else {
        customInput.classList.add('hidden');
        customInput.required = false;
        customInput.value = '';
    }
}

// Validate form before submit
function validateForm() {
    const unitSelect = document.getElementById('unit');
    const customInput = document.getElementById('unit_custom');

    if (unitSelect.value === 'custom' && customInput.value.trim() === '') {
        alert('Silakan masukkan satuan custom');
        customInput.focus();
        return false;
    }

    return true;
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add form validation
    const form = document.querySelector('form[action="/cornerbites-sia/process/simpan_produk.php"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }

    console.log('Produk page loaded and initialized');
});