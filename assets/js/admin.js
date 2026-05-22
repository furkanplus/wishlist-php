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
});
