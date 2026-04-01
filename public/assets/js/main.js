/**
 * CodeCanvas - Minimal JavaScript
 */

(function () {
    'use strict';

    // Dropdown menu functionality
    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach(item => {
        const link = item.querySelector('.nav-link');
        const dropdown = item.querySelector('.dropdown');

        if (link && dropdown) {
            // Toggle dropdown on click
            link.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Close other dropdowns
                navItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });

                // Toggle current dropdown
                item.classList.toggle('active');
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.nav-item')) {
            navItems.forEach(item => {
                item.classList.remove('active');
            });
        }
    });

    // Close dropdowns on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            navItems.forEach(item => {
                item.classList.remove('active');
            });

            // Close user menu too
            const userAvatar = document.querySelector('.user-avatar');
            if (userAvatar) {
                userAvatar.classList.remove('active');
            }
        }
    });

    // User avatar dropdown (for dashboard)
    const userAvatar = document.querySelector('.user-avatar');
    if (userAvatar) {
        userAvatar.addEventListener('click', (e) => {
            e.stopPropagation();
            userAvatar.classList.toggle('active');
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-avatar')) {
                userAvatar.classList.remove('active');
            }
        });
    }


    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');

            if (href === '#' || href.length <= 1) return;

            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                const headerOffset = 72;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // --- Dynamic Header Logic ---
    async function checkAuthAndPopulateHeader() {
        const headerActions = document.querySelector('.header-actions');
        if (!headerActions) return;

        try {
            let path = 'api/check-auth.php';
            let baseUrl = '';

            // Determine relative path to public/api/check-auth.php based on location
            if (window.location.pathname.includes('/app/')) {
                path = '../public/api/check-auth.php';
                baseUrl = '../public/';
            } else if (!window.location.pathname.includes('/public/')) {
                path = 'public/api/check-auth.php';
                baseUrl = 'public/';
            }

            const res = await fetch(path);
            if (res && res.ok) {
                const authData = await res.json();
                if (authData.isLoggedIn) {
                    headerActions.innerHTML = `
                        <a href="${baseUrl}../dashboard.php" class="btn btn-secondary">Dashboard</a>
                        <div class="user-avatar" style="margin-left: 10px; cursor: pointer; display: inline-flex; align-items: center;" onclick="window.location.href='${baseUrl}../profile.php'">
                            <span class="avatar-circle" style="width: 40px; height: 40px; border-radius: 50%; background: #000; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500;">
                                ${authData.user.initials}
                            </span>
                        </div>
                    `;
                }
            }
        } catch (e) { }
    }

    checkAuthAndPopulateHeader();

})();
