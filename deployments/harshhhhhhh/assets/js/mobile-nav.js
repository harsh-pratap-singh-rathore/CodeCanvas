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

// Global Real-Time Event Stream setup
document.addEventListener('DOMContentLoaded', () => {
    if (typeof BASE_URL !== 'undefined' && document.querySelector('.dashboard-header')) {
        setupRealTimeEvents();
    }
});

function setupRealTimeEvents() {
    const sse = new EventSource(`${BASE_URL}/app/api/sse-events.php`);
    
    sse.addEventListener('update', (event) => {
        try {
            const data = JSON.parse(event.data);
            
            // If new messages or notifications arrive, trigger a page reload
            // Or better yet, natively show a toast on-screen so they know immediately
            if (data.messages || data.notifications) {
                showGlobalToast("You have new updates! Refreshing list...", "success");
                
                // If they are on the specific page, we trigger the custom fetch if it exists
                // Otherwise, a simple fallback is to reload the window, but since they asked 
                // "without refreshing", we notify them and execute a dedicated list update if possible.
                
                if (data.messages && typeof updateMessagesList !== 'undefined') {
                    // Pull new list
                    fetch(`${BASE_URL}/app/api/get-messages.php`)
                        .then(r => r.json())
                        .then(json => { if (json.status === 'success') updateMessagesList(json.data); });
                }
                
                if (data.notifications && typeof updateNotificationsList !== 'undefined') {
                    fetch(`${BASE_URL}/app/api/get-notifications.php?mark_read=1`)
                        .then(r => r.json())
                        .then(json => { if (json.status === 'success') updateNotificationsList(json.data); });
                }
            }

        } catch (e) {
            console.error('SSE Error', e);
        }
    });

    sse.onerror = (e) => {
        console.warn("SSE stream disconnected. Reconnecting automatically...");
    };
}

// UI Toast helper to tell them immediately
function showGlobalToast(message, type = 'success') {
    let t = document.getElementById('global-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'global-toast';
        document.body.appendChild(t);
        const style = document.createElement('style');
        style.innerHTML = `
            #global-toast {
                position: fixed; top: 20px; right: 20px; z-index: 10000;
                background: #fff; border-left: 4px solid #000; padding: 16px 24px;
                border-radius: 6px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                font-size: 14px; font-weight: 600; color: #111;
                transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            #global-toast.show { transform: translateX(0); }
        `;
        document.head.appendChild(style);
    }
    t.innerHTML = `🔔 ${message}`;
    t.classList.add('show');
    
    setTimeout(() => {
        t.classList.remove('show');
    }, 4500);
}
