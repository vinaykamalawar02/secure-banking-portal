// Optimized ATM System JavaScript

// Tab switching functionality
function switchTab(tab) {
    // Hide all forms
    const forms = ['customer', 'manager', 'admin'];
    forms.forEach(formType => {
        const form = document.getElementById('form-' + formType);
        if (form) form.style.display = 'none';
        
        const tabButton = document.getElementById('tab-' + formType);
        if (tabButton) tabButton.classList.remove('active');
    });
    
    // Show selected form and activate tab
    const selectedForm = document.getElementById('form-' + tab);
    const selectedTab = document.getElementById('tab-' + tab);
    
    if (selectedForm) selectedForm.style.display = 'block';
    if (selectedTab) selectedTab.classList.add('active');
    
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
}

// Password toggle functionality
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId.replace('-password', '-eye-icon'));
    
    if (!input || !icon) return;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// DateTime update functionality
function updateDateTime() {
    const now = new Date();
    const timeElement = document.getElementById('current-time');
    const dateElement = document.getElementById('current-date');
    
    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString();
    }
    
    if (dateElement) {
        dateElement.textContent = now.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }
}

// Initialize common functionality
document.addEventListener('DOMContentLoaded', function() {
    // Set initial tab based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        switchTab(tab);
    }
    
    // Start time updates if elements exist
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        updateDateTime();
        setInterval(updateDateTime, 1000);
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Confirm before logout
    document.querySelectorAll('.logout-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    });
    
    // ATM keypad functionality
    document.querySelectorAll('.atm-key').forEach(function(key) {
        key.addEventListener('click', function() {
            const display = document.getElementById('atm-display');
            const value = this.getAttribute('data-value');
            
            if (value === 'clear') {
                display.value = '';
            } else if (value === 'enter') {
                // Submit form
                document.getElementById('atm-form').submit();
            } else {
                display.value += value;
            }
        });
    });
    
    // Form validation
    document.querySelectorAll('form.needs-validation').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Auto-format currency inputs
    document.querySelectorAll('.currency-input').forEach(function(input) {
        input.addEventListener('blur', function() {
            const value = parseFloat(this.value) || 0;
            this.value = value.toFixed(2);
        });
    });
});

// Error popup functionality
function showErrorPopup(message) {
    const popup = document.createElement('div');
    popup.innerHTML = `
        <div class="flex items-center space-x-3">
            <i class="fas fa-exclamation-triangle text-yellow-500"></i>
            <span class="font-medium">${message}</span>
        </div>
    `;
    popup.className = 'fixed top-6 left-1/2 transform -translate-x-1/2 px-6 py-4 bg-white text-gray-800 font-medium rounded-xl shadow-lg z-50 border-l-4 border-yellow-500';
    document.body.appendChild(popup);
    setTimeout(() => popup.remove(), 4000);
}

// Modal functionality
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'block';
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-backdrop')) {
        event.target.style.display = 'none';
    }
});