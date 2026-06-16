// assets/js/admin.js

document.addEventListener('DOMContentLoaded', () => {
    
    // --- Scraper Integration ---
    const btnScrape = document.getElementById('btn-scrape');
    const scrapeUrlInput = document.getElementById('scrape-url');
    const scrapeLoading = document.getElementById('scrape-loading');
    
    const addTitleInput = document.getElementById('add-title');
    const addUrlInput = document.getElementById('add-url');
    const addImageInput = document.getElementById('add-image');
    const addImagePreview = document.getElementById('add-image-preview');
    const addImagePreviewContainer = document.getElementById('add-image-preview-container');

    if (btnScrape) {
        btnScrape.addEventListener('click', () => {
            const url = scrapeUrlInput.value.trim();
            if (!url) {
                alert('Please paste a valid URL first.');
                return;
            }
            
            // Basic client-side URL validation
            try {
                new URL(url);
            } catch (_) {
                alert('The URL entered appears to be invalid.');
                return;
            }

            // Show indicator
            scrapeLoading.style.display = 'flex';
            btnScrape.disabled = true;

            // Fetch details from API
            fetch('api/fetch-metadata.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ url: url })
            })
            .then(response => response.json())
            .then(data => {
                scrapeLoading.style.display = 'none';
                btnScrape.disabled = false;

                if (data.success) {
                    // Populate Form Fields
                    addTitleInput.value = data.title || '';
                    addUrlInput.value = data.url || url;
                    
                    if (data.image) {
                        addImageInput.value = data.image;
                        addImagePreview.src = data.image;
                        addImagePreviewContainer.style.display = 'block';
                    } else {
                        addImageInput.value = '';
                        addImagePreviewContainer.style.display = 'none';
                    }
                } else {
                    alert('Could not automatically gather product details: ' + data.message + '\n\nYou can still add the item details manually below.');
                    // Populate url anyway for convenience
                    addUrlInput.value = url;
                }
            })
            .catch(error => {
                scrapeLoading.style.display = 'none';
                btnScrape.disabled = false;
                alert('An error occurred while connecting to the scraping server. Please fill details manually.');
                addUrlInput.value = url;
            });
        });
    }

    // --- Live Image Previews ---
    const setupLivePreview = (inputEl, previewEl, containerEl) => {
        if (inputEl && previewEl && containerEl) {
            inputEl.addEventListener('input', () => {
                const val = inputEl.value.trim();
                if (val) {
                    previewEl.src = val;
                    containerEl.style.display = 'block';
                } else {
                    containerEl.style.display = 'none';
                }
            });
        }
    };
    setupLivePreview(addImageInput, addImagePreview, addImagePreviewContainer);

    const editImageInput = document.getElementById('edit-image');
    const editImagePreview = document.getElementById('edit-image-preview');
    const editImagePreviewContainer = document.getElementById('edit-image-preview-container');
    setupLivePreview(editImageInput, editImagePreview, editImagePreviewContainer);


    // --- Edit Modal Handling ---
    const editModal = document.getElementById('edit-modal');
    const modalClose = document.getElementById('modal-close');
    const editIdInput = document.getElementById('edit-id');
    const editTitleInput = document.getElementById('edit-title');
    const editUrlInput = document.getElementById('edit-url');
    const editNotesInput = document.getElementById('edit-notes');

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const title = btn.dataset.title;
            const url = btn.dataset.url;
            const image = btn.dataset.image;
            const notes = btn.dataset.notes;

            editIdInput.value = id;
            editTitleInput.value = title;
            editUrlInput.value = url;
            editImageInput.value = image;
            editNotesInput.value = notes;

            if (image) {
                editImagePreview.src = image;
                editImagePreviewContainer.style.display = 'block';
            } else {
                editImagePreviewContainer.style.display = 'none';
            }

            editModal.classList.add('active');
        });
    });

    const closeModal = () => {
        editModal.classList.remove('active');
    };

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    if (editModal) {
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) {
                closeModal();
            }
        });
    }


    // --- Drag and Drop Sorting ---
    const list = document.getElementById('sortable-list');
    
    if (list) {
        let draggingElement = null;

        list.addEventListener('dragstart', (e) => {
            const item = e.target.closest('.sortable-item');
            if (!item) return;
            draggingElement = item;
            item.classList.add('dragging');
            // Required for Firefox
            e.dataTransfer.setData('text/plain', '');
        });

        list.addEventListener('dragend', (e) => {
            const item = e.target.closest('.sortable-item');
            if (!item) return;
            item.classList.remove('dragging');
            draggingElement = null;
            saveNewOrder();
        });

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            const dragging = document.querySelector('.dragging');
            if (!dragging) return;

            const afterElement = getDragAfterElement(list, e.clientY);
            if (afterElement == null) {
                list.appendChild(dragging);
            } else {
                list.insertBefore(dragging, afterElement);
            }
        });

        // Function to find insertion placement
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.sortable-item:not(.dragging)')];
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
    }

    // --- Arrow Buttons Sorting (Mobile / Fallback) ---
    document.querySelectorAll('.btn-move-up').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const item = btn.closest('.sortable-item');
            const prev = item.previousElementSibling;
            if (prev && prev.classList.contains('sortable-item')) {
                list.insertBefore(item, prev);
                saveNewOrder();
            }
        });
    });

    document.querySelectorAll('.btn-move-down').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const item = btn.closest('.sortable-item');
            const next = item.nextElementSibling;
            if (next && next.classList.contains('sortable-item')) {
                list.insertBefore(next, item);
                saveNewOrder();
            }
        });
    });

    // Send API update request
    function saveNewOrder() {
        const items = [...list.querySelectorAll('.sortable-item')];
        const ids = items.map(item => item.dataset.id);
        
        fetch('api/update-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                console.error('Error saving order:', data.message);
            }
        })
        .catch(err => {
            console.error('Failed to communicate with sorting server:', err);
        });
    }

    // --- Tab Switching ---
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.dataset.tab;

                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                btn.classList.add('active');
                const contentEl = document.getElementById('tab-' + targetTab);
                if (contentEl) {
                    contentEl.classList.add('active');
                }

                localStorage.setItem('active_admin_tab', targetTab);
            });
        });

        // Restore active tab (prefer location hash, then localStorage)
        let activeTab = 'items';
        if (window.location.hash && document.getElementById('tab-' + window.location.hash.substring(1).replace('tab-', ''))) {
            activeTab = window.location.hash.substring(1).replace('tab-', '');
        } else {
            activeTab = localStorage.getItem('active_admin_tab') || 'items';
        }
        const activeBtn = document.querySelector(`.tab-btn[data-tab="${activeTab}"]`);
        if (activeBtn) {
            activeBtn.click();
        } else if (tabBtns[0]) {
            tabBtns[0].click();
        }
    }

    // --- Translation Filter ---
    const trSearch = document.getElementById('translation-search');
    const trRows = document.querySelectorAll('.translation-row');

    if (trSearch) {
        trSearch.addEventListener('input', () => {
            const query = trSearch.value.toLowerCase().trim();

            trRows.forEach(row => {
                const key = row.dataset.key ? row.dataset.key.toLowerCase() : '';
                const defaultValue = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const currentValue = row.querySelector('textarea').value.toLowerCase();

                if (key.includes(query) || defaultValue.includes(query) || currentValue.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // --- Live Translation Validation & Warnings ---
    const textareas = document.querySelectorAll('.translation-textarea');
    const warningBanner = document.getElementById('empty-translations-warning');
    const warningCountEl = document.getElementById('empty-translations-count');

    function updateEmptyWarnings() {
        let emptyCount = 0;
        textareas.forEach(textarea => {
            const val = textarea.value.trim();
            const badge = textarea.nextElementSibling; // the .empty-fallback-badge span
            if (val === '') {
                emptyCount++;
                textarea.classList.add('empty-warning');
                if (badge) {
                    badge.style.display = 'inline-block';
                }
            } else {
                textarea.classList.remove('empty-warning');
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        });

        if (warningBanner && warningCountEl) {
            if (emptyCount > 0) {
                warningCountEl.textContent = emptyCount;
                warningBanner.style.display = 'flex';
            } else {
                warningBanner.style.display = 'none';
            }
        }
    }

    if (textareas.length > 0) {
        textareas.forEach(textarea => {
            textarea.addEventListener('input', updateEmptyWarnings);
        });
        // Run once on load to sync
        updateEmptyWarnings();
    }

    // --- Theme Live Customizer & Presets ---
    const presets = {
        default: {
            primary: '#ff003c',
            accent: '#00e5ff',
            background: '#050a0e',
            card: '#0b161d',
            'text-primary': '#ffffff',
            'text-secondary': '#a0a0a0'
        },
        emerald: {
            primary: '#10b981',
            accent: '#34d399',
            background: '#064e3b',
            card: '#065f46',
            'text-primary': '#ecfdf5',
            'text-secondary': '#a7f3d0'
        },
        sunset: {
            primary: '#f59e0b',
            accent: '#ec4899',
            background: '#180f03',
            card: '#2a1b08',
            'text-primary': '#fffbeb',
            'text-secondary': '#fde68a'
        },
        ocean: {
            primary: '#06b6d4',
            accent: '#3b82f6',
            background: '#030712',
            card: '#0b1329',
            'text-primary': '#f0f9ff',
            'text-secondary': '#bae6fd'
        },
        sakura: {
            primary: '#f43f5e',
            accent: '#a855f7',
            background: '#1c0d12',
            card: '#2d1520',
            'text-primary': '#fff1f2',
            'text-secondary': '#fecdd3'
        },
        dracula: {
            primary: '#bd93f9',
            accent: '#ff79c6',
            background: '#1e1f29',
            card: '#282a36',
            'text-primary': '#f8f8f2',
            'text-secondary': '#6272a4'
        }
    };

    function applyPreset(name) {
        const preset = presets[name];
        if (!preset) return;
        
        Object.entries(preset).forEach(([key, val]) => {
            const el = document.getElementById(`theme-${key}`);
            if (el) {
                el.value = val;
                // Dispatch both input and change events to notify preview listeners
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    // Bind event listeners to preset buttons
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            applyPreset(btn.dataset.preset);
        });
    });

    function hexToRgb(hex) {
        hex = hex.replace('#', '');
        let r, g, b;
        if (hex.length === 3) {
            r = parseInt(hex[0] + hex[0], 16);
            g = parseInt(hex[1] + hex[1], 16);
            b = parseInt(hex[2] + hex[2], 16);
        } else {
            r = parseInt(hex.substring(0, 2), 16);
            g = parseInt(hex.substring(2, 4), 16);
            b = parseInt(hex.substring(4, 6), 16);
        }
        return `${r}, ${g}, ${b}`;
    }

    function updateColor(varName, value) {
        document.documentElement.style.setProperty(varName, value);
        if (varName === '--primary') {
            const rgb = hexToRgb(value);
            document.documentElement.style.setProperty('--primary-glow', `rgba(${rgb}, 0.15)`);
            document.documentElement.style.setProperty('--border-color-focus', `rgba(${rgb}, 0.5)`);
        }
    }

    function updateCardColor(value) {
        const rgb = hexToRgb(value);
        document.documentElement.style.setProperty('--bg-card', `rgba(${rgb}, 0.6)`);
        document.documentElement.style.setProperty('--bg-card-hover', `rgba(${rgb}, 0.8)`);
    }

    function updateBodyGradient() {
        const primaryEl = document.getElementById('theme-primary');
        const accentEl = document.getElementById('theme-accent');
        if (!primaryEl || !accentEl) return;
        const rgbPrimary = hexToRgb(primaryEl.value);
        const rgbAccent = hexToRgb(accentEl.value);
        document.body.style.backgroundImage = `
            radial-gradient(at 0% 0%, rgba(${rgbPrimary}, 0.08) 0px, transparent 50%),
            radial-gradient(at 100% 100%, rgba(${rgbAccent}, 0.08) 0px, transparent 50%)
        `;
    }

    document.querySelectorAll('.theme-color-picker').forEach(picker => {
        picker.addEventListener('input', (e) => {
            const varName = e.target.dataset.var;
            const value = e.target.value;
            if (varName === '--bg-card') {
                updateCardColor(value);
            } else {
                updateColor(varName, value);
            }
            if (varName === '--primary' || varName === '--accent') {
                updateBodyGradient();
            }
        });
    });
});
