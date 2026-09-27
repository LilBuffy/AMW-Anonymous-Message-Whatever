(function () {
    'use strict';

    const CFG = window.CONFIG || {};
    const INTRO_DELAY = CFG.INTRO_DELAY_MS || 2200;
    const MAX_MESSAGE_LENGTH = CFG.MAX_MESSAGE_LENGTH || 2000;
    const MAX_IMAGE_SIZE = CFG.MAX_IMAGE_SIZE_BYTES || 5 * 1024 * 1024;
    const MAX_VIDEO_SIZE = CFG.MAX_VIDEO_SIZE_BYTES || 20 * 1024 * 1024;
    const QUESTIONS = CFG.QUESTIONS || {};
    const QUESTION_ORDER = ['reason', 'found', 'familiarity'];

    const FADE_DURATION = 380;
    const TRANSITION_HOLD = 700;
    const TYPE_SPEED = 34;

    const stage = document.getElementById('stage');

    let selectedAttachment = null;
    let attachmentPreviewUrl = null;
    let lastPromptIndex = -1;

    const answers = {
        reason: null, reason_other: '',
        found: null, found_other: '',
        familiarity: null, familiarity_other: ''
    };

    const PROMPTS = [
        "Tell me something you have never said before.",
        "What is something you genuinely want me to know?",
        "What is your honest opinion of me?",
        "Say whatever you have been keeping to yourself.",
        "Ask me something you have always wondered.",
        "Tell me something random.",
        "What do you wish I understood better?",
        "Say the thing you keep almost sending."
    ];

    function reducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function clearStage() {
        stage.innerHTML = '';
    }

    function mount(el, focusTarget) {
        clearStage();
        stage.appendChild(el);
        requestAnimationFrame(() => requestAnimationFrame(() => {
            el.classList.add('visible');
            const target = focusTarget || el.querySelector('[data-autofocus]');
            if (target) {
                target.setAttribute('tabindex', '-1');
                target.focus({ preventScroll: true });
            }
        }));
    }

    function transitionOut(el, callback) {
        if (reducedMotion()) {
            callback();
            return;
        }
        el.classList.remove('visible');
        el.classList.add('leaving');
        setTimeout(callback, FADE_DURATION);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatClock(totalSeconds) {
        const s = Math.max(0, Math.ceil(totalSeconds));
        const m = Math.floor(s / 60);
        const r = s % 60;
        return String(m).padStart(2, '0') + ':' + String(r).padStart(2, '0');
    }

    function showBlank() {
        if (CFG.COOLDOWN && CFG.COOLDOWN.active) {
            showCooldown(CFG.COOLDOWN.remaining_seconds, { justSent: false });
            return;
        }

        clearStage();
        const cursor = document.createElement('div');
        cursor.className = 'ambient-cursor';
        cursor.setAttribute('aria-hidden', 'true');
        stage.appendChild(cursor);

        setTimeout(showIntro, reducedMotion() ? 400 : INTRO_DELAY);
    }

    function showIntro() {
        const block = document.createElement('div');
        block.className = 'stage-block';

        const p = document.createElement('p');
        p.className = 'intro-text';
        block.appendChild(p);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'intro-btn';
        btn.textContent = 'Come in.';
        btn.style.opacity = '0';
        block.appendChild(btn);

        mount(block, p);

        const fullText = 'You found your way in.';

        function revealButton() {
            btn.style.opacity = '';
            btn.classList.add('ready');
            btn.addEventListener('click', () => {
                transitionOut(block, () => showQuestionByIndex(0));
            });
        }

        if (reducedMotion()) {
            p.textContent = fullText;
            revealButton();
            return;
        }

        const textSpan = document.createElement('span');
        const cursor = document.createElement('span');
        cursor.className = 'type-cursor';
        cursor.textContent = '\u00A0';
        cursor.setAttribute('aria-hidden', 'true');
        p.appendChild(textSpan);
        p.appendChild(cursor);

        let i = 0;
        (function typeNext() {
            if (i < fullText.length) {
                textSpan.textContent += fullText.charAt(i);
                i++;
                setTimeout(typeNext, TYPE_SPEED);
            } else {
                cursor.remove();
                revealButton();
            }
        })();
    }

    function showQuestionByIndex(index) {
        if (index >= QUESTION_ORDER.length) {
            showComposer();
            return;
        }

        const key = QUESTION_ORDER[index];
        const q = QUESTIONS[key];
        if (!q) {
            showQuestionByIndex(index + 1);
            return;
        }

        renderQuestion(index, q, (choice, otherText) => {
            answers[key] = choice;
            answers[key + '_other'] = otherText;
            const current = stage.querySelector('.stage-block');
            const isLast = index === QUESTION_ORDER.length - 1;
            const transitionText = isLast ? 'Alright.' : q.transition;
            transitionOut(current, () => {
                showTransition(transitionText, () => showQuestionByIndex(index + 1));
            });
        });
    }

    function renderQuestion(index, q, onContinue) {
        const block = document.createElement('div');
        block.className = 'stage-block';

        const dots = document.createElement('div');
        dots.className = 'progress-dots';
        dots.setAttribute('aria-hidden', 'true');
        for (let i = 0; i < QUESTION_ORDER.length; i++) {
            const dot = document.createElement('span');
            dot.className = 'progress-dot';
            if (i < index) dot.classList.add('done');
            if (i === index) dot.classList.add('current');
            dots.appendChild(dot);
        }
        block.appendChild(dots);

        const heading = document.createElement('h2');
        heading.className = 'question-title';
        heading.textContent = q.question;
        block.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'choice-list';
        list.setAttribute('role', 'radiogroup');
        list.setAttribute('aria-label', q.question);

        let selected = null;

        const otherWrap = document.createElement('div');
        otherWrap.className = 'other-input-wrap';
        const otherInput = document.createElement('input');
        otherInput.type = 'text';
        otherInput.className = 'other-input';
        otherInput.maxLength = 255;
        otherInput.placeholder = 'Say more...';
        otherInput.setAttribute('aria-label', 'Custom answer');
        otherWrap.appendChild(otherInput);

        const continueBtn = document.createElement('button');
        continueBtn.type = 'button';
        continueBtn.className = 'continue-btn';
        continueBtn.textContent = 'Continue';
        continueBtn.disabled = true;

        function updateContinueState() {
            if (!selected) {
                continueBtn.disabled = true;
                return;
            }
            if (selected === 'Other') {
                continueBtn.disabled = otherInput.value.trim().length === 0;
            } else {
                continueBtn.disabled = false;
            }
        }

        q.choices.forEach((choice) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'choice-btn';
            btn.textContent = choice;
            btn.setAttribute('role', 'radio');
            btn.setAttribute('aria-checked', 'false');

            btn.addEventListener('click', () => {
                selected = choice;
                list.querySelectorAll('.choice-btn').forEach((b) => {
                    b.classList.remove('selected');
                    b.setAttribute('aria-checked', 'false');
                });
                btn.classList.add('selected');
                btn.setAttribute('aria-checked', 'true');

                otherWrap.classList.toggle('visible', choice === 'Other');
                if (choice === 'Other') {
                    otherInput.focus();
                }
                updateContinueState();
            });

            list.appendChild(btn);
        });

        otherInput.addEventListener('input', updateContinueState);
        otherInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !continueBtn.disabled) {
                continueBtn.click();
            }
        });

        continueBtn.addEventListener('click', () => {
            const otherText = selected === 'Other' ? otherInput.value.trim() : '';
            onContinue(selected, otherText);
        });

        block.appendChild(list);
        block.appendChild(otherWrap);
        block.appendChild(continueBtn);

        mount(block, heading);
    }

    function showTransition(text, next) {
        const block = document.createElement('div');
        block.className = 'stage-block transition-block';
        const p = document.createElement('p');
        p.className = 'transition-text';
        p.textContent = text;
        block.appendChild(p);
        mount(block, p);

        const hold = reducedMotion() ? 260 : TRANSITION_HOLD;
        setTimeout(() => transitionOut(block, next), hold);
    }

    function showComposer() {
        const block = document.createElement('div');
        block.className = 'stage-block composer-block';

        block.innerHTML = `
            <h2 class="form-title" data-autofocus>Now say whatever you came here to say.</h2>
            <button type="button" class="prompt-btn">Give me a prompt</button>
            <div class="prompt-chip" hidden></div>
            <div class="message-form-card">
                <span class="corner tl" aria-hidden="true"></span>
                <span class="corner br" aria-hidden="true"></span>
                <textarea class="message-textarea" maxlength="${MAX_MESSAGE_LENGTH}"
                    placeholder="Write whatever you want. I will read it." aria-label="Your anonymous message"></textarea>
            </div>
            <div class="char-counter">0 / ${MAX_MESSAGE_LENGTH}</div>
            <p class="composer-hint">Links you paste in your message come along with it.</p>
            <div class="form-controls-row">
                <button type="button" class="attach-btn">Add a photo or video</button>
                <button type="button" class="submit-btn" disabled>Send</button>
            </div>
            <input type="file" class="attach-input" accept="image/*,video/*" style="display:none">
            <div class="attach-preview" aria-live="polite"></div>
            <div class="form-error-container" aria-live="assertive"></div>
        `;

        const textarea = block.querySelector('.message-textarea');
        const counter = block.querySelector('.char-counter');
        const promptBtn = block.querySelector('.prompt-btn');
        const promptChip = block.querySelector('.prompt-chip');
        const attachBtn = block.querySelector('.attach-btn');
        const attachInput = block.querySelector('.attach-input');
        const attachPreview = block.querySelector('.attach-preview');
        const submitBtn = block.querySelector('.submit-btn');
        const errorContainer = block.querySelector('.form-error-container');

        function updateCounterAndButton() {
            const len = textarea.value.length;
            counter.textContent = `${len} / ${MAX_MESSAGE_LENGTH}`;
            counter.classList.toggle('near-limit', len > MAX_MESSAGE_LENGTH * 0.9);
            submitBtn.disabled = len === 0 || len > MAX_MESSAGE_LENGTH;
        }

        textarea.addEventListener('input', updateCounterAndButton);

        promptBtn.addEventListener('click', () => {
            let idx = Math.floor(Math.random() * PROMPTS.length);
            if (PROMPTS.length > 1 && idx === lastPromptIndex) {
                idx = (idx + 1) % PROMPTS.length;
            }
            lastPromptIndex = idx;
            const text = PROMPTS[idx];

            promptChip.hidden = false;
            promptChip.innerHTML = `
                <span class="prompt-chip-text">${escapeHtml(text)}</span>
                <button type="button" class="prompt-use-btn">Use this</button>
            `;
            promptChip.querySelector('.prompt-use-btn').addEventListener('click', () => {
                if (textarea.value.trim().length === 0) {
                    textarea.value = text;
                } else {
                    textarea.value = textarea.value.replace(/\s+$/, '') + '\n' + text;
                }
                updateCounterAndButton();
                promptChip.hidden = true;
                textarea.focus();
            });
        });

        attachBtn.addEventListener('click', () => attachInput.click());

        function clearAttachment() {
            selectedAttachment = null;
            if (attachmentPreviewUrl) {
                URL.revokeObjectURL(attachmentPreviewUrl);
                attachmentPreviewUrl = null;
            }
            attachPreview.innerHTML = '';
            attachBtn.classList.remove('has-file');
            attachBtn.textContent = 'Add a photo or video';
            attachInput.value = '';
        }

        attachInput.addEventListener('change', () => {
            const file = attachInput.files[0];
            errorContainer.textContent = '';

            if (!file) {
                clearAttachment();
                return;
            }

            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');
            const limit = isImage ? MAX_IMAGE_SIZE : MAX_VIDEO_SIZE;

            if (!isImage && !isVideo) {
                showError('Please choose an image or video file.');
                attachInput.value = '';
                return;
            }
            if (file.size > limit) {
                const mb = Math.round(limit / 1024 / 1024);
                showError(`That file is too large (max ${mb}MB).`);
                attachInput.value = '';
                return;
            }

            selectedAttachment = file;
            if (attachmentPreviewUrl) URL.revokeObjectURL(attachmentPreviewUrl);
            attachmentPreviewUrl = URL.createObjectURL(file);

            attachPreview.innerHTML = '';
            const thumb = document.createElement(isImage ? 'img' : 'video');
            thumb.className = 'attach-thumb';
            thumb.src = attachmentPreviewUrl;
            if (!isImage) { thumb.muted = true; thumb.playsInline = true; }
            attachPreview.appendChild(thumb);

            const meta = document.createElement('div');
            meta.className = 'attach-meta';
            meta.innerHTML = `
                <span class="attach-name">${escapeHtml(file.name)}</span>
                <button type="button" class="attach-remove" aria-label="Remove attachment">Remove</button>
            `;
            attachPreview.appendChild(meta);
            meta.querySelector('.attach-remove').addEventListener('click', clearAttachment);

            attachBtn.classList.add('has-file');
            attachBtn.textContent = 'Change attachment';
        });

        function showError(msg) {
            errorContainer.innerHTML = `<p class="form-error">${escapeHtml(msg)}</p>`;
        }

        submitBtn.addEventListener('click', () => {
            submitMessage(textarea.value, block, submitBtn, errorContainer, showError);
        });

        mount(block, textarea);
    }

    function submitMessage(messageText, block, submitBtn, errorContainer, showError) {
        submitBtn.disabled = true;
        submitBtn.classList.add('sending');
        submitBtn.innerHTML = '<span class="sending-dot"></span><span class="sending-dot"></span><span class="sending-dot"></span>';
        errorContainer.textContent = '';

        const formData = new FormData();
        formData.append('message', messageText);
        formData.append('reason', answers.reason || '');
        formData.append('reason_other', answers.reason_other || '');
        formData.append('found', answers.found || '');
        formData.append('found_other', answers.found_other || '');
        formData.append('familiarity', answers.familiarity || '');
        formData.append('familiarity_other', answers.familiarity_other || '');
        formData.append('csrf_token', window.CSRF_TOKEN);

        if (selectedAttachment) {
            formData.append('attachment', selectedAttachment);
        }

        fetch('send.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
            body: formData
        })
        .then((res) => res.json().then((data) => ({ status: res.status, data })))
        .then(({ status, data }) => {
            if (data.success) {
                block.classList.add('sent-away');
                setTimeout(() => {
                    showSuccess(data.confirmation, data.remaining_seconds);
                }, reducedMotion() ? 0 : 520);
                return;
            }

            if (data.cooldown_active) {
                transitionOut(block, () => showCooldown(data.remaining_seconds, { justSent: false }));
                return;
            }

            showError(data.error || 'Something went wrong. Please try again.');
            submitBtn.disabled = false;
            submitBtn.classList.remove('sending');
            submitBtn.textContent = 'Send';
        })
        .catch(() => {
            showError('Could not reach the server. Check your connection and try again.');
            submitBtn.disabled = false;
            submitBtn.classList.remove('sending');
            submitBtn.textContent = 'Send';
        });
    }

    function showSuccess(confirmationText, remainingSeconds) {
        const block = document.createElement('div');
        block.className = 'stage-block';
        block.innerHTML = `
            <div class="confirmation-wrap">
                <div class="confirmation-ring r1" aria-hidden="true"></div>
                <div class="confirmation-ring r2" aria-hidden="true"></div>
                <div class="confirmation-ring r3" aria-hidden="true"></div>
                <p class="confirmation-text" data-autofocus>Message sent.</p>
                <p class="confirmation-subtext">${escapeHtml(confirmationText)}</p>
            </div>
        `;
        mount(block, block.querySelector('.confirmation-text'));

        selectedAttachment = null;
        if (attachmentPreviewUrl) {
            URL.revokeObjectURL(attachmentPreviewUrl);
            attachmentPreviewUrl = null;
        }

        setTimeout(() => {
            transitionOut(block, () => showCooldown(remainingSeconds, { justSent: true }));
        }, reducedMotion() ? 700 : 1900);
    }

    function showCooldown(remainingSeconds, opts) {
        const justSent = !!(opts && opts.justSent);
        const block = document.createElement('div');
        block.className = 'stage-block cooldown-block';
        block.innerHTML = `
            <p class="cooldown-headline" data-autofocus>${justSent ? 'Message sent.' : 'You already sent one.'}</p>
            <p class="cooldown-subtext">${justSent ? 'It has been delivered.' : 'One at a time.'}</p>
            <p class="cooldown-question">Want to send another?</p>
            <p class="cooldown-label">Available again in</p>
            <p class="cooldown-clock">${formatClock(remainingSeconds)}</p>
        `;
        mount(block, block.querySelector('.cooldown-headline'));

        let remaining = remainingSeconds;
        const clockEl = block.querySelector('.cooldown-clock');

        const tick = setInterval(() => {
            remaining -= 1;
            if (remaining > 0) {
                clockEl.textContent = formatClock(remaining);
                return;
            }
            clearInterval(tick);
            verifyCooldownCleared(block);
        }, 1000);
    }

    function verifyCooldownCleared(block) {
        fetch('cooldown.php', { method: 'GET' })
            .then((res) => res.json())
            .then((data) => {
                if (data.success && !data.active) {
                    showBackAgain(block);
                } else {
                    const remaining = (data.success && data.remaining_seconds) ? data.remaining_seconds : 5;
                    showCooldown(remaining, { justSent: false });
                }
            })
            .catch(() => {
                setTimeout(() => verifyCooldownCleared(block), 2000);
            });
    }

    function showBackAgain(block) {
        const done = document.createElement('div');
        done.className = 'stage-block';
        done.innerHTML = `
            <p class="cooldown-headline" data-autofocus>You're back.</p>
            <button type="button" class="continue-btn">Send another message</button>
        `;
        transitionOut(block, () => {
            mount(done, done.querySelector('.cooldown-headline'));
            done.querySelector('.continue-btn').addEventListener('click', () => {
                transitionOut(done, showComposer);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', showBlank);

    const privacyToggle = document.getElementById('privacy-toggle');
    const privacyPanel = document.getElementById('privacy-panel');
    if (privacyToggle && privacyPanel) {
        privacyToggle.addEventListener('click', () => {
            const open = privacyPanel.hidden;
            privacyPanel.hidden = !open;
            privacyToggle.setAttribute('aria-expanded', String(open));
        });
    }
})();
