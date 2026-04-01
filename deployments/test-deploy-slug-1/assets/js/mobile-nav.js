/**
 * CodeCanvas Global Mobile Navigation System
 * Handles slide-in menu for public pages and collapsible sidebar for dashboard.
 */
document.addEventListener('DOMContentLoaded', () => {
    setupMobileNav();
});

function setupMobileNav() {
    const headerContent = document.querySelector('.header-content');
    const dashboardHeader = document.querySelector('.dashboard-header-content');
    
    // Create the hamburger button
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'mobile-menu-btn';
    toggleBtn.setAttribute('aria-label', 'Toggle menu');
    toggleBtn.innerHTML = '<span></span><span></span><span></span>';

    // Create backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'mobile-nav-backdrop';

    if (headerContent) {
        // --- Public / Auth Pages ---
        // Insert after everything in header content or append
        headerContent.appendChild(toggleBtn);
        document.body.appendChild(backdrop);

        // We build the mobile menu by cloning the desktop nav elements
        const mobileMenu = document.createElement('div');
        mobileMenu.className = 'mobile-menu';

        const nav = document.querySelector('.nav');
        const actions = document.querySelector('.header-actions');
        
        if (nav) mobileMenu.appendChild(nav.cloneNode(true));
        if (actions) mobileMenu.appendChild(actions.cloneNode(true));
        
        document.body.appendChild(mobileMenu);

        // Submenu toggling logic for cloned nav
        const mobileNavItems = mobileMenu.querySelectorAll('.nav-item');
        mobileNavItems.forEach(item => {
            const link = item.querySelector('.nav-link');
            const dropdown = item.querySelector('.dropdown');
            if (dropdown && link) {
                link.addEventListener('click', (e) => {
                    // if it's not strictly a link, prevent default and toggle
                    if (!link.getAttribute('href') || link.getAttribute('href') === '#') {
                        e.preventDefault();
                    }
                    item.classList.toggle('active');
                });
            }
        });

        // Bind toggle events
        toggleBtn.addEventListener('click', () => {
            const isActive = toggleBtn.classList.contains('is-active');
            toggleMenu(!isActive, toggleBtn, mobileMenu, backdrop);
        });

        backdrop.addEventListener('click', () => {
            toggleMenu(false, toggleBtn, mobileMenu, backdrop);
        });
    } else if (dashboardHeader) {
        // --- Dashboard / Admin Pages ---
        // Dashboard uses a sidebar, so hamburger toggles sidebar instead of a new sliding menu
        dashboardHeader.insertBefore(toggleBtn, dashboardHeader.firstChild);
        document.body.appendChild(backdrop);

        const sidebar = document.querySelector('.dashboard-sidebar');
        if (sidebar) {
            toggleBtn.addEventListener('click', () => {
                const isOpen = sidebar.classList.contains('is-open');
                sidebar.classList.toggle('is-open', !isOpen);
                toggleBtn.classList.toggle('is-active', !isOpen);
                backdrop.classList.toggle('is-active', !isOpen);
                document.body.style.overflow = !isOpen ? 'hidden' : '';
            });

            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('is-open');
                toggleBtn.classList.remove('is-active');
                backdrop.classList.remove('is-active');
                document.body.style.overflow = '';
            });
        }
    }
}

function toggleMenu(show, toggleBtn, mobileMenu, backdrop) {
    if (show) {
        toggleBtn.classList.add('is-active');
        mobileMenu.classList.add('is-active');
        backdrop.classList.add('is-active');
        document.body.style.overflow = 'hidden'; // Prevent body scroll
    } else {
        toggleBtn.classList.remove('is-active');
        mobileMenu.classList.remove('is-active');
        backdrop.classList.remove('is-active');
        document.body.style.overflow = '';
    }
}
