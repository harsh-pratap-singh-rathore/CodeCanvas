/**
 * Admin Panel Interactions
 * Cleaned up: Removed redundant browser popups.
 */

(function () {
    'use strict';

    // Logout Handling: Allow the link to process directly 
    // This removes the browser confirm() that was causing the double popup.
    const logoutLinks = document.querySelectorAll('a[href$="logout.php"], a[href*="logout"]');
    
    // Header/Sidebar menu toggles would go here if needed.
    
    console.log('Admin interactions initialized.');
})();
