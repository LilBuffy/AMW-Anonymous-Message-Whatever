document.addEventListener('DOMContentLoaded', function () {
    function showConfirmModal(message) {
        return new Promise(function (resolve) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop';
            backdrop.innerHTML = `
                <div class="modal-card" role="dialog" aria-modal="true" aria-label="Confirm deletion">
                    <p>${message}</p>
                    <div class="modal-actions">
                        <button type="button" class="cancel-btn">Cancel</button>
                        <button type="button" class="confirm-btn">Delete</button>
                    </div>
                </div>
            `;
            document.body.appendChild(backdrop);
            requestAnimationFrame(function () { backdrop.classList.add('visible'); });

            function close(result) {
                backdrop.classList.remove('visible');
                setTimeout(function () { backdrop.remove(); }, 180);
                resolve(result);
            }

            backdrop.querySelector('.cancel-btn').addEventListener('click', function () { close(false); });
            backdrop.querySelector('.confirm-btn').addEventListener('click', function () { close(true); });
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) close(false);
            });
        });
    }

    document.querySelectorAll('.delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.id;

            showConfirmModal('Delete this message permanently?').then(function (confirmed) {
                if (!confirmed) return;

                btn.disabled = true;
                btn.textContent = 'Deleting...';

                fetch('delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': window.CSRF_TOKEN
                    },
                    body: new URLSearchParams({ id: id, csrf_token: window.CSRF_TOKEN })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        const card = btn.closest('.message-card');
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(-6px)';
                        setTimeout(function () { card.remove(); }, 220);
                    } else {
                        alert(data.error || 'Failed to delete message.');
                        btn.disabled = false;
                        btn.textContent = 'Delete';
                    }
                })
                .catch(function () {
                    alert('Something went wrong. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Delete';
                });
            });
        });
    });
});
