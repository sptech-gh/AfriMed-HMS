document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('theme-toggle');
    if (toggleButton) {
        toggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            const body = document.body;
            if (body.classList.contains('theme-dark')) {
                body.classList.remove('theme-dark');
                body.classList.add('theme-light');
                localStorage.setItem('hms_ui_theme', 'light');
            } else {
                body.classList.remove('theme-light');
                body.classList.add('theme-dark');
                localStorage.setItem('hms_ui_theme', 'dark');
            }
        });
    }
});
