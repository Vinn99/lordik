// public/assets/js/app.js

// Auto-dismiss flash alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach(function (el) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        });
    }, 5000);

    // Confirm delete on forms with data-confirm
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Character counter for textareas with maxlength
    document.querySelectorAll('textarea[maxlength]').forEach(function (ta) {
        const counter = document.createElement('div');
        counter.classList.add('form-text', 'text-end');
        ta.parentNode.appendChild(counter);
        const update = () => {
            const remaining = ta.maxLength - ta.value.length;
            counter.textContent = remaining + ' karakter tersisa';
            counter.style.color = remaining < 20 ? '#ef4444' : '#94a3b8';
        };
        ta.addEventListener('input', update);
        update();
    });

    // Tooltips initialization
    document.querySelectorAll('[title]').forEach(function (el) {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });
});
