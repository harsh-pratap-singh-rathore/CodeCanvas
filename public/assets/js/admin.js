/**
 * Admin Panel Interactions
 */

(function () {
    'use strict';

    // Logout Handling
    const logoutLinks = document.querySelectorAll('a[href$="logout.php"], a[href*="logout"]');

    logoutLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const href = link.getAttribute('href');

            if (confirm('Are you certain you want to log out?')) {
                window.location.href = href;
            }
        });
    });

    console.log('Admin interactions initialized.');
})();
