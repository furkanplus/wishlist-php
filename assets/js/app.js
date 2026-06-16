// assets/js/app.js

document.addEventListener('DOMContentLoaded', () => {

    // --- Shipping Address Countdown Timer ---
    const timerElement = document.getElementById('address-timer');
    const countdownSpan = document.getElementById('timer-countdown');

    if (timerElement && countdownSpan) {
        const expiresTimestamp = parseInt(timerElement.dataset.expires, 10);

        const updateTimer = () => {
            const now = Math.floor(Date.now() / 1000);
            const diff = expiresTimestamp - now;

            if (diff <= 0) {
                clearInterval(timerInterval);
                countdownSpan.textContent = "Expired";
                timerElement.classList.add('expired');
                
                // Hide the shipping address container card after a small delay (or trigger page reload for backend security check)
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                const hours = Math.floor(diff / 3600);
                const minutes = Math.floor((diff % 3600) / 60);
                const seconds = diff % 60;

                // Format string
                const hDisplay = hours.toString().padStart(2, '0');
                const mDisplay = minutes.toString().padStart(2, '0');
                const sDisplay = seconds.toString().padStart(2, '0');

                countdownSpan.textContent = `${hDisplay}h ${mDisplay}m ${sDisplay}s`;
            }
        };

        // Run immediately and start interval
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
    }

    // --- "I've Bought This" Modal Handling ---
    const buyModal = document.getElementById('buy-modal');
    const modalCloseBuy = document.getElementById('modal-close-buy');
    const modalItemTitle = document.getElementById('modal-item-title');
    
    const formContent = document.getElementById('modal-form-content');
    const successContent = document.getElementById('modal-success-content');
    const formMarkBought = document.getElementById('form-mark-bought');
    const modalError = document.getElementById('modal-error');
    
    const buyItemIdInput = document.getElementById('buy-item-id');
    const buyerNameInput = document.getElementById('buyer-name');
    const buyerProofInput = document.getElementById('buyer-proof');
    const messagePublicToggle = document.getElementById('message-public-toggle');
    const messageGroup = document.getElementById('message-group');
    const buyerMessageInput = document.getElementById('buyer-message');
    const messageVisibilityHint = document.getElementById('message-visibility-hint');

    if (messagePublicToggle && messageVisibilityHint) {
        const t = window.translations || {};
        messagePublicToggle.addEventListener('change', () => {
            messageVisibilityHint.textContent = messagePublicToggle.checked 
                ? (t.message_visibility_public || 'This message will be visible to everyone on the public wishlist.')
                : (t.message_visibility_private || 'This message will only be visible to the wishlist owner (admin).');
        });
    }

    document.querySelectorAll('.btn-mark-bought').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const title = btn.dataset.title;

            // Reset modal state
            buyItemIdInput.value = id;
            modalItemTitle.textContent = title;
            buyerNameInput.value = '';
            buyerProofInput.value = '';
            if (messagePublicToggle) messagePublicToggle.checked = false;
            if (buyerMessageInput) buyerMessageInput.value = '';
            if (messageVisibilityHint) messageVisibilityHint.textContent = 'This message will only be visible to the wishlist owner (admin).';
            modalError.style.display = 'none';
            modalError.textContent = '';
            
            formContent.style.display = 'block';
            successContent.style.display = 'none';

            buyModal.classList.add('active');
        });
    });

    const closeBuyModal = () => {
        buyModal.classList.remove('active');
    };

    if (modalCloseBuy) {
        modalCloseBuy.addEventListener('click', closeBuyModal);
    }

    if (buyModal) {
        buyModal.addEventListener('click', (e) => {
            if (e.target === buyModal) {
                closeBuyModal();
            }
        });
    }

    // --- Submit Purchase Mark ---
    if (formMarkBought) {
        formMarkBought.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const itemId = buyItemIdInput.value;
            const buyerName = buyerNameInput.value.trim();
            const buyerProof = buyerProofInput.value.trim();

            const t = window.translations || {};

            if (!buyerProof) {
                modalError.textContent = t.verification_required || 'Verification proof (Tracking Link or Order ID) is required.';
                modalError.style.display = 'block';
                return;
            }

            modalError.style.display = 'none';
            const submitBtn = formMarkBought.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = t.verifying || 'Verifying...';

            const buyerMessage = buyerMessageInput ? buyerMessageInput.value.trim() : '';
            const messagePublic = messagePublicToggle ? messagePublicToggle.checked : false;

            fetch('api/mark-bought.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: itemId,
                    buyer_name: buyerName,
                    buyer_proof: buyerProof,
                    buyer_message: buyerMessage,
                    message_public: messagePublic
                })
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = t.confirm_purchase || 'Confirm Purchase';

                if (data.success) {
                    // Show success checkmark content
                    formContent.style.display = 'none';
                    successContent.style.display = 'block';
                    
                    // Reload page after success animation delay to show updated list
                    setTimeout(() => {
                        closeBuyModal();
                        location.reload();
                    }, 2500);
                } else {
                    modalError.textContent = data.message || 'An error occurred. Please try again.';
                    modalError.style.display = 'block';
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.textContent = t.confirm_purchase || 'Confirm Purchase';
                modalError.textContent = t.network_error || 'Unable to contact the server. Please check your network connection.';
                modalError.style.display = 'block';
                console.error('Mark bought failure:', err);
            });
        });
    }
});
