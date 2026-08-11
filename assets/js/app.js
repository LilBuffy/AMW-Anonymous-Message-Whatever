(function () {
    'use strict';

    const CFG = window.CONFIG || {};
    const INTRO_DELAY = CFG.INTRO_DELAY_MS || 7000;
    const MAX_MESSAGE_LENGTH = CFG.MAX_MESSAGE_LENGTH || 1000;
    const MAX_IMAGE_SIZE = CFG.MAX_IMAGE_SIZE_BYTES || 5 * 1024 * 1024;
    const MAX_VIDEO_SIZE = CFG.MAX_VIDEO_SIZE_BYTES || 20 * 1024 * 1024;
    const HOLD_DURATION = 2400;
    const FADE_DURATION = 420;
    const TYPE_SPEED = 42;

    const stage = document.getElementById('stage');
    let selectedAttachment = null;

    const WHY_CHOICES = [
        'I have no idea', 'Someone sent it to me', 'I was bored',
        'I wanted to see what this was', 'I was looking for something', 'Other'
    ];
    const WHERE_CHOICES = [
        'Instagram', 'Facebook', 'LinkedIn', 'Discord', 'Messenger',
        'Someone sent it to me', 'Somewhere else', 'Other'
    ];

    const answers = {
        why_clicked: null,
        why_clicked_other: '',
        where_found: null,
        where_found_other: ''
    };

    function clearStage() {
        stage.innerHTML = '';
    }

    function mount(el) {
        clearStage();
        stage.appendChild(el);
        requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('visible')));
    }

    function transitionOut(el, callback) {
        el.classList.remove('visible');
        el.classList.add('leaving');
        setTimeout(callback, FADE_DURATION);
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    // -------------------------------------------------------------
    // Stage 0: blank screen with a faint breathing cursor
    // -------------------------------------------------------------
    function showBlank() {
        clearStage();
        const cursor = document.createElement('div');
        cursor.className = 'ambient-cursor';
        cursor.setAttribute('aria-hidden', 'true');
        stage.appendChild(cursor);

        setTimeout(showIntroMessage, INTRO_DELAY);
    }

    // -------------------------------------------------------------
    // Stage 1: "Wow... you clicked my link." typed onto the screen
    // -------------------------------------------------------------
    function showIntroMessage() {
        const block = document.createElement('div');
        block.className = 'stage-block';

        const p = document.createElement('p');
        p.className = 'intro-text';
        p.setAttribute('aria-label', 'Wow... you clicked my link.');
        block.appendChild(p);
        mount(block);

        const fullText = 'Wow... you clicked my link.';

        if (prefersReducedMotion()) {
            p.textContent = fullText;
            setTimeout(() => transitionOut(block, showQuestion1), HOLD_DURATION);
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
                setTimeout(() => transitionOut(block, showQuestion1), HOLD_DURATION);
            }
        })();
    }

    // -------------------------------------------------------------
    // Stage 2: Question 1
    // -------------------------------------------------------------
    function showQuestion1() {
        renderQuestion({
            eyebrow: '01',
            title: 'Why did you click my link?',
            choices: WHY_CHOICES,
            onContinue: (choice, otherText) => {
                answers.why_clicked = choice;
                answers.why_clicked_other = otherText;
                const current = stage.querySelector('.stage-block');
                transitionOut(current, showQuestion2);
            }
        });
    }

    // -------------------------------------------------------------
    // Stage 3: Question 2
    // -------------------------------------------------------------
    function showQuestion2() {
        renderQuestion({
            eyebrow: '02',
            title: 'Where did you find my link?',
            choices: WHERE_CHOICES,
            onContinue: (choice, otherText) => {
                answers.where_found = choice;
                answers.where_found_other = otherText;
                const current = stage.querySelector('.stage-block');
                transitionOut(current, showMessageForm);
            }
        });
    }

    /**
     * Shared renderer for both questions.
     */
    function renderQuestion({ eyebrow, title, choices, onContinue }) {
        const block = document.createElement('div');
        block.className = 'stage-block';

        const eyebrowEl = document.createElement('span');
        eyebrowEl.className = 'eyebrow';
        eyebrowEl.textContent = eyebrow;
        block.appendChild(eyebrowEl);

        const heading = document.createElement('h2');
        heading.className = 'question-title';
        heading.textContent = title;
        block.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'choice-list';
        list.setAttribute('role', 'radiogroup');
        list.setAttribute('aria-label', title);

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

        choices.forEach((choice) => {
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

        continueBtn.addEventListener('click', () => {
            const otherText = selected === 'Other' ? otherInput.value.trim() : '';
            onContinue(selected, otherText);
        });

        block.appendChild(list);
        block.appendChild(otherWrap);
        block.appendChild(continueBtn);

        mount(block);
    }

    // -------------------------------------------------------------
    // Stage 4: Main message form
    // -------------------------------------------------------------
    function showMessageForm() {
        const block = document.createElement('div');
        block.className = 'stage-block';

        block.innerHTML = `
            <h2 class="form-title">Alright my nigga, say something.</h2>
            <div class="message-form-card">
                <span class="corner tl" aria-hidden="true"></span>
                <span class="corner br" aria-hidden="true"></span>
                <textarea class="message-textarea" maxlength="${MAX_MESSAGE_LENGTH}"
                    placeholder="Write whatever you want... I'll read it soon:)" aria-label="Your anonymous message"></textarea>
            </div>
            <div class="char-counter">0 / ${MAX_MESSAGE_LENGTH}</div>
            <div class="form-controls-row">
                <button type="button" class="attach-btn">Attach image or video</button>
                <button type="button" class="submit-btn" disabled>Send</button>
            </div>
            <input type="file" class="attach-input" accept="image/*,video/*" style="display:none">
            <div class="attach-preview" aria-live="polite"></div>
            <div class="form-error-container" aria-live="assertive"></div>
        `;

        const textarea = block.querySelector('.message-textarea');
        const counter = block.querySelector('.char-counter');
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

        attachBtn.addEventListener('click', () => attachInput.click());

        attachInput.addEventListener('change', () => {
            const file = attachInput.files[0];
            errorContainer.textContent = '';

            if (!file) {
                selectedAttachment = null;
                attachPreview.textContent = '';
                attachBtn.classList.remove('has-file');
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
                showError(`File is too large (max ${mb}MB).`);
                attachInput.value = '';
                return;
            }

            selectedAttachment = file;
            attachPreview.textContent = `Attached: ${file.name}`;
            attachBtn.classList.add('has-file');
            attachBtn.textContent = 'Change attachment';
        });

        function showError(msg) {
            errorContainer.innerHTML = `<p class="form-error">${escapeHtml(msg)}</p>`;
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        submitBtn.addEventListener('click', () => {
            submitMessage(textarea.value, submitBtn, errorContainer, showError);
        });

        mount(block);
        textarea.focus();
    }

    // -------------------------------------------------------------
    // Stage 5: Submission
    // -------------------------------------------------------------
    function submitMessage(messageText, submitBtn, errorContainer, showError) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="sending-dot"></span><span class="sending-dot"></span><span class="sending-dot"></span>';
        errorContainer.textContent = '';

        const formData = new FormData();
        formData.append('message', messageText);
        formData.append('why_clicked', answers.why_clicked || '');
        formData.append('why_clicked_other', answers.why_clicked_other || '');
        formData.append('where_found', answers.where_found || '');
        formData.append('where_found_other', answers.where_found_other || '');
        formData.append('csrf_token', window.CSRF_TOKEN);

        if (selectedAttachment) {
            formData.append('attachment', selectedAttachment);
        }

        fetch('send.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
            body: formData
        })
        .then((res) => res.json())
        .then((data) => {
            if (data.success) {
                const currentBlock = stage.querySelector('.stage-block');
                transitionOut(currentBlock, () => showConfirmation(data.confirmation));
            } else {
                showError(data.error || 'Something went wrong. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send';
            }
        })
        .catch(() => {
            showError('Something went wrong. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send';
        });
    }

    // -------------------------------------------------------------
    // Stage 6: Confirmation
    // -------------------------------------------------------------
    function showConfirmation(text) {
        const block = document.createElement('div');
        block.className = 'stage-block';
        block.innerHTML = `
            <div class="confirmation-wrap">
                <div class="confirmation-ring r1" aria-hidden="true"></div>
                <div class="confirmation-ring r2" aria-hidden="true"></div>
                <div class="confirmation-ring r3" aria-hidden="true"></div>
                <p class="confirmation-text">${text}</p>
            </div>
            <button type="button" class="restart-link">Send another</button>
        `;
        mount(block);

        block.querySelector('.restart-link').addEventListener('click', () => {
            transitionOut(block, () => {
                answers.why_clicked = null;
                answers.where_found = null;
                selectedAttachment = null;
                showMessageForm();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', showBlank);
})();
