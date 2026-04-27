// Theme Toggle
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

// Load saved theme (default to dark on first visit)
const storedTheme = localStorage.getItem('theme');
const savedTheme = storedTheme || 'dark';
if (!storedTheme) {
    localStorage.setItem('theme', savedTheme);
}
html.setAttribute('data-theme', savedTheme);
updateThemeIcon(savedTheme);

window.addEventListener('DOMContentLoaded', () => {
    html.classList.add('theme-ready');
});

themeToggle?.addEventListener('click', () => {
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
});

function updateThemeIcon(theme) {
    const icon = themeToggle?.querySelector('i');
    if (icon) {
        icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// Add scroll animation to elements
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe feature cards and product cards
document.querySelectorAll('.feature-card, .product-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(card);
});

// Universal notification function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '16px 24px',
        borderRadius: '12px',
        color: 'white',
        fontWeight: '600',
        fontSize: '15px',
        zIndex: '10000',
        boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15)',
        animation: 'slideInRight 0.3s ease',
        maxWidth: '400px',
        wordWrap: 'break-word'
    });
    
    // Set background color based on type
    const colors = {
        success: 'linear-gradient(135deg, #22c55e, #16a34a)',
        error: 'linear-gradient(135deg, #ef4444, #dc2626)',
        warning: 'linear-gradient(135deg, #f59e0b, #d97706)',
        info: 'linear-gradient(135deg, #3b82f6, #2563eb)'
    };
    notification.style.background = colors[type] || colors.info;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add keyframe animations if not already present
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

window.addEventListener('DOMContentLoaded', () => {
    const blockedOverlay = document.querySelector('.blocked-self-overlay');
    if (blockedOverlay) {
        document.body.classList.add('blocked-overlay-open');
    }

    const userMenuButton = document.getElementById('userMenuButton');
    const userDropdown = document.getElementById('userDropdown');
    const notificationsButton = document.getElementById('notificationsButton');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const notificationFilters = document.getElementById('notificationFilters');
    const notificationFiltersToggle = document.getElementById('notificationFiltersToggle');
    const notificationFiltersReset = document.getElementById('notificationFiltersReset');
    const notificationFiltersEmpty = document.getElementById('notificationFiltersEmpty');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileNav = document.getElementById('mobileNav');
    const notificationItems = Array.from(document.querySelectorAll('.header-notification-item'));
    const notificationSubtitle = notificationsDropdown?.querySelector('.notification-subtitle');

    const notificationFiltersState = {
        status: true,
        support: true,
        system: true,
        showRead: true
    };

    function syncPanelState(button, panel, isOpen) {
        if (button) {
            button.classList.toggle('active', isOpen);
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        if (panel) {
            panel.classList.toggle('active', isOpen);
        }
    }

    function closeNotificationFilters() {
        if (notificationFilters) {
            notificationFilters.classList.remove('show');
        }

        if (notificationFiltersToggle) {
            notificationFiltersToggle.classList.remove('active');
            notificationFiltersToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function setMobileMenuState(isOpen) {
        if (!mobileNav || !mobileMenuToggle) {
            return;
        }

        mobileNav.classList.toggle('active', isOpen);
        mobileMenuToggle.classList.toggle('active', isOpen);
        mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('no-scroll', isOpen);
    }

    function closeHeaderPanels(options = {}) {
        const {
            keepUser = false,
            keepNotifications = false,
            keepMobile = false
        } = options;

        if (!keepUser) {
            syncPanelState(userMenuButton, userDropdown, false);
        }

        if (!keepNotifications) {
            syncPanelState(notificationsButton, notificationsDropdown, false);
            closeNotificationFilters();
        }

        if (!keepMobile) {
            setMobileMenuState(false);
        }
    }

    function updateNotificationSubtitle() {
        if (!notificationSubtitle) {
            return;
        }

        const unreadCount = notificationItems.filter((item) => item.dataset.read !== '1').length;
        notificationSubtitle.textContent = unreadCount > 0
            ? `Непрочитанных: ${unreadCount}`
            : 'Все уведомления прочитаны';
    }

    function applyNotificationFilters() {
        if (!notificationItems.length) {
            return;
        }

        let visibleCount = 0;

        notificationItems.forEach((item) => {
            const type = item.dataset.type || 'system';
            const isRead = item.dataset.read === '1';
            const typeEnabled = notificationFiltersState[type] ?? true;
            const showRead = notificationFiltersState.showRead || !isRead;
            const shouldShow = typeEnabled && showRead;

            item.style.display = shouldShow ? '' : 'none';
            if (shouldShow) {
                visibleCount += 1;
            }
        });

        if (notificationFiltersEmpty) {
            notificationFiltersEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    function markNotificationsAsRead() {
        if (!notificationsButton || !notificationsDropdown) {
            return;
        }

        const unreadItems = notificationItems.filter((item) => item.dataset.read !== '1');
        if (!unreadItems.length) {
            updateNotificationSubtitle();
            return;
        }

        fetch('api/mark_notifications_read.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => response.ok ? response.json() : Promise.reject(new Error('request_failed')))
            .then((data) => {
                if (!data.success) {
                    return;
                }

                unreadItems.forEach((item) => {
                    item.dataset.read = '1';
                    item.classList.remove('unread');
                });

                notificationsButton.classList.remove('has-unread');
                notificationsButton.querySelector('.notification-dot')?.remove();
                updateNotificationSubtitle();
                applyNotificationFilters();
            })
            .catch(() => {});
    }

    function openNotificationDestination(item) {
        const orderId = parseInt(item.dataset.orderId || '0', 10);
        const type = item.dataset.notificationType || item.dataset.type || 'status';

        if (orderId > 0) {
            const hash = type === 'support' ? `#chat-${orderId}` : `#order-${orderId}`;
            window.location.href = `orders.php${hash}`;
            return;
        }

        window.location.href = 'orders.php';
    }

    document.querySelectorAll('.notification-filter-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const filterName = checkbox.dataset.filter;
            if (!filterName) {
                return;
            }

            notificationFiltersState[filterName] = checkbox.checked;
            applyNotificationFilters();
        });
    });

    notificationFiltersReset?.addEventListener('click', () => {
        Object.keys(notificationFiltersState).forEach((key) => {
            notificationFiltersState[key] = true;
        });

        document.querySelectorAll('.notification-filter-checkbox').forEach((checkbox) => {
            checkbox.checked = true;
        });

        applyNotificationFilters();
    });

    notificationsButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = !notificationsDropdown?.classList.contains('active');
        closeHeaderPanels({ keepNotifications: true });
        syncPanelState(notificationsButton, notificationsDropdown, isOpen);
        if (!isOpen) {
            closeNotificationFilters();
            return;
        }

        markNotificationsAsRead();
    });

    notificationFiltersToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = !notificationFilters?.classList.contains('show');
        notificationFilters?.classList.toggle('show', isOpen);
        notificationFiltersToggle.classList.toggle('active', isOpen);
        notificationFiltersToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    userMenuButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = !userDropdown?.classList.contains('active');
        closeHeaderPanels({ keepUser: true });
        syncPanelState(userMenuButton, userDropdown, isOpen);
    });

    mobileMenuToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = !mobileNav?.classList.contains('active');
        closeHeaderPanels({ keepMobile: true });
        setMobileMenuState(isOpen);
    });

    notificationItems.forEach((item) => {
        item.addEventListener('click', () => openNotificationDestination(item));
    });

    mobileNav?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setMobileMenuState(false));
    });

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!target.closest('.notifications-menu')) {
            syncPanelState(notificationsButton, notificationsDropdown, false);
            closeNotificationFilters();
        } else if (!target.closest('.notification-filters') && !target.closest('#notificationFiltersToggle')) {
            closeNotificationFilters();
        }

        if (!target.closest('.user-menu')) {
            syncPanelState(userMenuButton, userDropdown, false);
        }

        if (!target.closest('.site-header')) {
            setMobileMenuState(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeHeaderPanels();
        }
    });

    applyNotificationFilters();
    updateNotificationSubtitle();
});
