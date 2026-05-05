// ============================================================
// Alicia CPRS — Main JavaScript
// ============================================================

// Auto-calculate age from DOB
function calculateAge(dob) {
    if (!dob) return '';
    const birth = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age;
}

// Hook up DOB → Age auto-fill
document.addEventListener('DOMContentLoaded', function () {
    const dobInput = document.getElementById('dob');
    const ageInput = document.getElementById('age');

    if (dobInput && ageInput) {
        dobInput.addEventListener('change', function () {
            ageInput.value = calculateAge(this.value);
        });
        // Trigger on page load if DOB already filled
        if (dobInput.value) ageInput.value = calculateAge(dobInput.value);
    }

    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.4s'; setTimeout(() => el.remove(), 400); }, 5000);
    });

    // Confirm delete buttons
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });
});

// Live search filter for tables
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll(`#${tableId} tbody tr`).forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// Print page
function printPage() { window.print(); }
