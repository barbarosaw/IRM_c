/**
 * AbroadWorks Management System - FontAwesome Icon Picker
 * 
 * @author ikinciadam@gmail.com
 */

// Popular FontAwesome icons categorized
const fontAwesomeIcons = {
    'General': [
        'fa-home', 'fa-user', 'fa-users', 'fa-cog', 'fa-settings', 'fa-gear',
        'fa-search', 'fa-plus', 'fa-minus', 'fa-edit', 'fa-trash', 'fa-save',
        'fa-download', 'fa-upload', 'fa-print', 'fa-copy', 'fa-cut', 'fa-paste',
        'fa-heart', 'fa-star', 'fa-bookmark', 'fa-thumbs-up', 'fa-thumbs-down',
        'fa-share', 'fa-reply', 'fa-forward', 'fa-refresh', 'fa-undo', 'fa-redo',
        'fa-calendar', 'fa-clock', 'fa-history', 'fa-map', 'fa-location-dot',
        'fa-filter', 'fa-sort', 'fa-list', 'fa-th', 'fa-th-list', 'fa-th-large'
    ],
    'Business': [
        'fa-building', 'fa-briefcase', 'fa-chart-bar', 'fa-chart-line', 'fa-chart-pie',
        'fa-money-bill', 'fa-dollar-sign', 'fa-credit-card', 'fa-calculator',
        'fa-handshake', 'fa-truck', 'fa-shipping-fast', 'fa-warehouse', 'fa-store',
        'fa-industry', 'fa-factory', 'fa-chart-area', 'fa-chart-column', 'fa-trending-up',
        'fa-trending-down', 'fa-balance-scale', 'fa-coins', 'fa-wallet', 'fa-receipt',
        'fa-cash-register', 'fa-piggy-bank', 'fa-bank', 'fa-landmark', 'fa-percentage',
        'fa-tag', 'fa-tags', 'fa-barcode', 'fa-qrcode', 'fa-shopping-basket'
    ],
    'Communication': [
        'fa-envelope', 'fa-phone', 'fa-comments', 'fa-comment', 'fa-bell',
        'fa-bullhorn', 'fa-rss', 'fa-wifi', 'fa-signal', 'fa-satellite-dish',
        'fa-microphone', 'fa-volume-up', 'fa-volume-down', 'fa-volume-mute',
        'fa-headphones', 'fa-video', 'fa-camera', 'fa-broadcast-tower',
        'fa-at', 'fa-hashtag', 'fa-envelope-open', 'fa-paper-plane',
        'fa-inbox', 'fa-outbox', 'fa-phone-alt', 'fa-mobile-alt', 'fa-fax'
    ],
    'Files & Documents': [
        'fa-file', 'fa-file-text', 'fa-file-pdf', 'fa-file-word', 'fa-file-excel',
        'fa-folder', 'fa-folder-open', 'fa-archive', 'fa-clipboard', 'fa-newspaper',
        'fa-file-image', 'fa-file-video', 'fa-file-audio', 'fa-file-code', 'fa-file-csv',
        'fa-file-powerpoint', 'fa-file-zipper', 'fa-file-arrow-down', 'fa-file-arrow-up',
        'fa-file-contract', 'fa-file-invoice', 'fa-file-signature', 'fa-sticky-note',
        'fa-note-sticky', 'fa-book', 'fa-bookmark', 'fa-journal-whills'
    ],
    'Technology': [
        'fa-laptop', 'fa-desktop', 'fa-mobile', 'fa-tablet', 'fa-server',
        'fa-database', 'fa-cloud', 'fa-code', 'fa-bug', 'fa-shield-alt',
        'fa-keyboard', 'fa-mouse', 'fa-monitor', 'fa-hard-drive', 'fa-memory',
        'fa-microchip', 'fa-usb', 'fa-ethernet', 'fa-router', 'fa-network-wired',
        'fa-globe', 'fa-sitemap', 'fa-project-diagram', 'fa-terminal', 'fa-command',
        'fa-window-maximize', 'fa-window-minimize', 'fa-window-restore'
    ],
    'Navigation': [
        'fa-arrow-left', 'fa-arrow-right', 'fa-arrow-up', 'fa-arrow-down',
        'fa-chevron-left', 'fa-chevron-right', 'fa-bars', 'fa-ellipsis-v',
        'fa-external-link-alt', 'fa-link', 'fa-angle-left', 'fa-angle-right',
        'fa-angle-up', 'fa-angle-down', 'fa-caret-left', 'fa-caret-right',
        'fa-caret-up', 'fa-caret-down', 'fa-long-arrow-alt-left', 'fa-long-arrow-alt-right',
        'fa-step-backward', 'fa-step-forward', 'fa-fast-backward', 'fa-fast-forward',
        'fa-play', 'fa-pause', 'fa-stop', 'fa-eject'
    ],
    'Status & Actions': [
        'fa-check', 'fa-times', 'fa-exclamation-triangle', 'fa-info-circle',
        'fa-question-circle', 'fa-ban', 'fa-lock', 'fa-unlock', 'fa-eye', 'fa-eye-slash',
        'fa-check-circle', 'fa-times-circle', 'fa-exclamation-circle', 'fa-minus-circle',
        'fa-plus-circle', 'fa-warning', 'fa-error', 'fa-success', 'fa-info',
        'fa-lightbulb', 'fa-flag', 'fa-bookmark', 'fa-bell-slash', 'fa-power-off',
        'fa-sync', 'fa-sync-alt', 'fa-spinner', 'fa-circle-notch'
    ],
    'E-commerce': [
        'fa-shopping-cart', 'fa-shopping-bag', 'fa-credit-card', 'fa-paypal',
        'fa-stripe', 'fa-amazon', 'fa-ebay', 'fa-product-hunt', 'fa-gift',
        'fa-ticket-alt', 'fa-crown', 'fa-gem', 'fa-award', 'fa-medal',
        'fa-trophy', 'fa-certificate', 'fa-ribbon', 'fa-percent', 'fa-discount',
        'fa-sale', 'fa-price-tag', 'fa-delivery', 'fa-package'
    ],
    'Social & Media': [
        'fa-facebook', 'fa-twitter', 'fa-instagram', 'fa-linkedin', 'fa-youtube',
        'fa-tiktok', 'fa-whatsapp', 'fa-telegram', 'fa-discord', 'fa-slack',
        'fa-skype', 'fa-zoom', 'fa-google', 'fa-apple', 'fa-microsoft',
        'fa-github', 'fa-gitlab', 'fa-bitbucket', 'fa-stackoverflow', 'fa-reddit',
        'fa-pinterest', 'fa-tumblr', 'fa-snapchat', 'fa-twitch'
    ],
    'Health & Medical': [
        'fa-heartbeat', 'fa-stethoscope', 'fa-pills', 'fa-syringe', 'fa-thermometer',
        'fa-band-aid', 'fa-hospital', 'fa-ambulance', 'fa-first-aid', 'fa-medical-kit',
        'fa-tooth', 'fa-brain', 'fa-lungs', 'fa-eye', 'fa-hand-holding-heart',
        'fa-dna', 'fa-microscope', 'fa-x-ray', 'fa-wheelchair'
    ],
    'Transportation': [
        'fa-car', 'fa-bus', 'fa-train', 'fa-plane', 'fa-ship', 'fa-bicycle',
        'fa-motorcycle', 'fa-taxi', 'fa-subway', 'fa-helicopter', 'fa-rocket',
        'fa-truck-pickup', 'fa-truck-monster', 'fa-caravan', 'fa-gas-pump',
        'fa-parking', 'fa-traffic-light', 'fa-road', 'fa-map-signs'
    ],
    'Sports & Recreation': [
        'fa-football', 'fa-basketball', 'fa-baseball', 'fa-tennis-ball', 'fa-golf-ball',
        'fa-swimming-pool', 'fa-running', 'fa-walking', 'fa-hiking', 'fa-cycling',
        'fa-skiing', 'fa-snowboarding', 'fa-chess', 'fa-dice', 'fa-gamepad',
        'fa-puzzle-piece', 'fa-trophy', 'fa-medal', 'fa-target'
    ]
};

class IconPicker {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        
        // Check if already initialized
        if (this.input.iconPickerInstance) {
            return this.input.iconPickerInstance;
        }
        
        this.options = {
            placeholder: 'Select an icon...',
            searchPlaceholder: 'Search icons...',
            modalTitle: 'Choose Icon',
            showSearch: true,
            showCategories: true,
            iconPrefix: 'fa-',
            ...options
        };
        
        this.currentCategory = 'General';
        this.filteredIcons = [];
        this.selectedIcon = this.input.value || '';
        this.isInitialized = false;
        
        // Store reference to prevent double initialization
        this.input.iconPickerInstance = this;
        
        this.init();
    }
    
    init() {
        if (this.isInitialized) {
            return;
        }
        
        this.createTrigger();
        this.createModal();
        this.bindEvents();
        this.updateInputDisplay();
        this.isInitialized = true;
    }
    
    createTrigger() {
        // Check if wrapper already exists
        if (this.input.parentNode.classList.contains('icon-picker-wrapper')) {
            this.displayButton = this.input.parentNode.querySelector('.icon-picker-trigger');
            return;
        }
        
        const wrapper = document.createElement('div');
        wrapper.className = 'icon-picker-wrapper position-relative';
        
        this.input.parentNode.insertBefore(wrapper, this.input);
        wrapper.appendChild(this.input);
        
        // Hide original input
        this.input.style.display = 'none';
        
        // Create display button
        this.displayButton = document.createElement('button');
        this.displayButton.type = 'button';
        this.displayButton.className = 'btn btn-outline-secondary w-100 text-start icon-picker-trigger';
        this.displayButton.innerHTML = `
            <span class="icon-preview me-2"></span>
            <span class="icon-text">${this.options.placeholder}</span>
            <i class="fas fa-chevron-down float-end mt-1"></i>
        `;
        
        wrapper.appendChild(this.displayButton);
    }
    
    createModal() {
        // Check if modal already exists for this input
        const existingModalId = this.input.dataset.iconPickerModal;
        if (existingModalId && document.getElementById(existingModalId)) {
            this.modal = document.getElementById(existingModalId);
            this.modalInstance = bootstrap.Modal.getInstance(this.modal) || new bootstrap.Modal(this.modal);
            return;
        }
        
        const modalId = 'iconPickerModal_' + Math.random().toString(36).substr(2, 9);
        this.input.dataset.iconPickerModal = modalId;
        
        const modalHTML = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${this.options.modalTitle}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${this.options.showSearch ? `
                                <div class="mb-3">
                                    <input type="text" class="form-control icon-search" placeholder="${this.options.searchPlaceholder}">
                                </div>
                            ` : ''}
                            
                            ${this.options.showCategories ? `
                                <div class="mb-3">
                                    <div class="btn-group category-buttons" role="group">
                                        ${Object.keys(fontAwesomeIcons).map(cat => 
                                            `<button type="button" class="btn btn-outline-primary category-btn ${cat === this.currentCategory ? 'active' : ''}" data-category="${cat}">${cat}</button>`
                                        ).join('')}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <div class="icon-grid" style="max-height: 400px; overflow-y: auto;">
                                <!-- Icons will be populated here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary icon-select-btn" disabled>Select Icon</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById(modalId);
        this.modalInstance = new bootstrap.Modal(this.modal);
    }
    
    bindEvents() {
        // Trigger button click
        this.displayButton.addEventListener('click', () => {
            this.showModal();
        });
        
        // Search functionality
        const searchInput = this.modal.querySelector('.icon-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterIcons(e.target.value);
            });
        }
        
        // Category buttons
        this.modal.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchCategory(e.target.dataset.category);
            });
        });
        
        // Select button
        this.modal.querySelector('.icon-select-btn').addEventListener('click', () => {
            this.selectIcon();
        });
        
        // Modal close event
        this.modal.addEventListener('hidden.bs.modal', () => {
            this.resetModal();
        });
    }
    
    showModal() {
        this.populateIcons();
        this.modalInstance.show();
    }
    
    switchCategory(category) {
        this.currentCategory = category;
        
        // Update active category button
        this.modal.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.category === category);
        });
        
        // Clear search
        const searchInput = this.modal.querySelector('.icon-search');
        if (searchInput) {
            searchInput.value = '';
        }
        
        this.populateIcons();
    }
    
    filterIcons(searchTerm) {
        if (!searchTerm.trim()) {
            this.populateIcons();
            return;
        }
        
        // Search across all categories
        this.filteredIcons = [];
        Object.values(fontAwesomeIcons).forEach(categoryIcons => {
            categoryIcons.forEach(icon => {
                if (icon.toLowerCase().includes(searchTerm.toLowerCase())) {
                    this.filteredIcons.push(icon);
                }
            });
        });
        
        this.populateIconGrid(this.filteredIcons);
    }
    
    populateIcons() {
        const icons = this.modal.querySelector('.icon-search')?.value.trim() 
            ? this.filteredIcons 
            : fontAwesomeIcons[this.currentCategory] || [];
        
        this.populateIconGrid(icons);
    }
    
    populateIconGrid(icons) {
        const grid = this.modal.querySelector('.icon-grid');
        
        if (icons.length === 0) {
            grid.innerHTML = '<div class="text-center text-muted py-4">No icons found</div>';
            return;
        }
        
        grid.innerHTML = `
            <div class="row g-2">
                ${icons.map(icon => `
                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                        <button type="button" class="btn btn-outline-secondary w-100 icon-option ${this.selectedIcon === icon ? 'active' : ''}" 
                                data-icon="${icon}" title="${icon}">
                            <i class="fas ${icon} mb-1 d-block"></i>
                            <small class="d-block text-truncate">${icon}</small>
                        </button>
                    </div>
                `).join('')}
            </div>
        `;
        
        // Bind icon selection events
        grid.querySelectorAll('.icon-option').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.selectIconOption(e.currentTarget);
            });
        });
    }
    
    selectIconOption(button) {
        // Remove previous selection
        this.modal.querySelectorAll('.icon-option.active').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add new selection
        button.classList.add('active');
        this.selectedIcon = button.dataset.icon;
        
        // Enable select button
        this.modal.querySelector('.icon-select-btn').disabled = false;
    }
    
    selectIcon() {
        if (this.selectedIcon) {
            this.input.value = this.selectedIcon;
            this.updateInputDisplay();
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            this.input.dispatchEvent(event);
        }
        
        this.modalInstance.hide();
    }
    
    resetModal() {
        const searchInput = this.modal.querySelector('.icon-search');
        if (searchInput) {
            searchInput.value = '';
        }
        
        this.modal.querySelector('.icon-select-btn').disabled = true;
        this.filteredIcons = [];
    }
    
    updateInputDisplay() {
        const iconPreview = this.displayButton.querySelector('.icon-preview');
        const iconText = this.displayButton.querySelector('.icon-text');
        
        if (this.selectedIcon) {
            iconPreview.innerHTML = `<i class="fas ${this.selectedIcon}"></i>`;
            iconText.textContent = this.selectedIcon;
        } else {
            iconPreview.innerHTML = '';
            iconText.textContent = this.options.placeholder;
        }
    }
    
    setValue(value) {
        this.selectedIcon = value;
        this.input.value = value;
        this.updateInputDisplay();
    }
    
    getValue() {
        return this.input.value;
    }
}

// Auto-initialize icon pickers
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-icon-picker]').forEach(input => {
        // Check if already initialized
        if (!input.iconPickerInstance) {
            new IconPicker(input);
        }
    });
});

// Also initialize when shown in modals
document.addEventListener('shown.bs.modal', function() {
    document.querySelectorAll('[data-icon-picker]').forEach(input => {
        // Check if already initialized
        if (!input.iconPickerInstance) {
            new IconPicker(input);
        }
    });
});

// Make IconPicker globally available
window.IconPicker = IconPicker;
