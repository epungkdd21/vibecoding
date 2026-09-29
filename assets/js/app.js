document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.querySelector('.nav-toggle');
    const topNav = document.querySelector('.topnav');
    const themeToggle = document.querySelector('.theme-toggle');

    if (navToggle && topNav) {
        navToggle.addEventListener('click', () => {
            topNav.classList.toggle('is-open');
        });
    }

    const savedTheme = localStorage.getItem('educode-theme');
    const initialTheme = savedTheme || 'dark';
    document.body.setAttribute('data-theme', initialTheme);

    if (themeToggle) {
        themeToggle.textContent = initialTheme === 'dark' ? 'Light mode' : 'Dark mode';
        themeToggle.addEventListener('click', () => {
            const nextTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', nextTheme);
            localStorage.setItem('educode-theme', nextTheme);
            themeToggle.textContent = nextTheme === 'dark' ? 'Light mode' : 'Dark mode';
        });
    }
});
