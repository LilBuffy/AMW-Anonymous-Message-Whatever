(function () {
    'use strict';

    function showModal(message, onConfirm) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        backdrop.innerHTML = `
            <div class="modal-card">
                <p>${message}</p>
                <div class="modal-actions">
                    <button type="button" class="cancel-btn">Cancel</button>
                    <button type="button" class="confirm-btn">Delete</button>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);
        requestAnimationFrame(() => backdrop.classList.add('visible'));

        function close() {
            backdrop.classList.remove('visible');
            setTimeout(() => backdrop.remove(), 200);
        }

        backdrop.querySelector('.cancel-btn').addEventListener('click', close);
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) close();
        });
        backdrop.querySelector('.confirm-btn').addEventListener('click', () => {
            close();
            onConfirm();
        });
    }

    document.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.delete-btn');
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            showModal('Delete this message? This cannot be undone.', () => {
                deleteBtn.disabled = true;
                fetch('delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${encodeURIComponent(id)}&csrf_token=${encodeURIComponent(window.CSRF_TOKEN)}`
                })
                .then((res) => res.json())
                .then((data) => {
                    if (data.success) {
                        const card = deleteBtn.closest('.message-card');
                        card.style.transition = 'opacity 220ms ease, transform 220ms ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-12px)';
                        setTimeout(() => card.remove(), 220);
                    } else {
                        deleteBtn.disabled = false;
                    }
                })
                .catch(() => { deleteBtn.disabled = false; });
            });
            return;
        }

        const readBtn = e.target.closest('.read-toggle-btn');
        if (readBtn) {
            const id = readBtn.dataset.id;
            const currentlyRead = readBtn.dataset.read === '1';
            const nextState = currentlyRead ? 0 : 1;
            readBtn.disabled = true;

            fetch('mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(id)}&is_read=${nextState}&csrf_token=${encodeURIComponent(window.CSRF_TOKEN)}`
            })
            .then((res) => res.json())
            .then((data) => {
                readBtn.disabled = false;
                if (!data.success) return;

                readBtn.dataset.read = String(nextState);
                readBtn.textContent = nextState ? 'Mark as unread' : 'Mark as read';

                const card = readBtn.closest('.message-card');
                card.classList.toggle('is-unread', !nextState);
                const dot = card.querySelector('.unread-dot');
                if (nextState && dot) {
                    dot.remove();
                } else if (!nextState && !dot) {
                    const newDot = document.createElement('span');
                    newDot.className = 'unread-dot';
                    newDot.setAttribute('aria-label', 'Unread');
                    card.querySelector('.message-card-top').appendChild(newDot);
                }
            })
            .catch(() => { readBtn.disabled = false; });
        }
    });

    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        const details = filterForm.querySelector('.filter-more');
        const hasActiveFilter = ['from', 'to', 'read', 'attachment', 'link', 'reason', 'found', 'familiarity']
            .some((name) => {
                const field = filterForm.elements[name];
                return field && field.value !== '';
            });
        if (hasActiveFilter && details) {
            details.open = true;
        }
    }
})();
