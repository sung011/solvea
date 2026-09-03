/**
 * 홈 검색 폼
 */
(function () {
    'use strict';

    function init() {
        const form = document.forms['ask'];
        if (!form) return;
        form.addEventListener('submit', handleSubmit);

        document.querySelectorAll('.prompt-card').forEach((btn) => {
            btn.addEventListener('click', () => {
                const q = (btn.dataset.q || '').trim();
                if (!q) return;
                form.q.value = q;
                goResults(q);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    async function handleSubmit(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const form = e.currentTarget;
        const q = (form.q?.value || '').trim();

        if (!q || q.length < 2) {
            utils.showToast('두자리 이상 입력해주세요.', () => form.q.focus());
            return false;
        }

        goResults(q);
        return false;
    }

    function goResults(q) {
        location.href = '/results?q=' + encodeURIComponent(q);
    }
})();
