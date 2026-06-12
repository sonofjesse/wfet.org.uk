/**
 * Mobile Menu Module
 * Sliding submenus from the right with explicit toggle controls
 */

const CHEVRON_RIGHT_SVG = `<svg class="mobile-submenu-chevron" width="13" height="24" viewBox="0 0 13 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M12.5508 12.5508L1.92578 23.1758C1.52734 23.5742 0.796875 23.5742 0.398437 23.1758C-1.68357e-07 22.7773 -2.00287e-07 22.0469 0.398437 21.6484L10.293 11.7539L0.398436 1.92578C-1.09722e-06 1.52734 -1.12915e-06 0.796875 0.398436 0.398437C0.796874 -4.60848e-07 1.52734 -4.92778e-07 1.92578 0.398437L12.5508 11.0234C12.9492 11.4219 12.9492 12.1523 12.5508 12.5508Z" fill="currentColor"/></svg>`;

const SUBMENU_BASE_Z = 1003;

class MobileMenu {
    constructor() {
        this.isActive = false;
        this.toggleButton = document.querySelector('.mobile-menu-toggle');
        this.overlay = document.querySelector('.mobile-menu-overlay');
        this.menu = document.querySelector('#mobile-menu');

        this.navigationStack = [];
        this.submenus = [];

        this.init();
    }

    init() {
        if (!this.toggleButton || !this.overlay || !this.menu) {
            if (this.isDebugEnabled()) {
                console.warn('[MOBILE MENU] Mobile menu elements not found');
            }
            return;
        }

        this.setupSubmenus();
        this.bindEvents();
        if (this.isDebugEnabled()) {
            console.log('[MOBILE MENU] Mobile menu initialized');
        }
    }

    isDebugEnabled() {
        return typeof sojTheme !== 'undefined' && sojTheme.debug;
    }

    setupSubmenus() {
        if (!this.menu) {
            return;
        }

        this.submenus = this.menu.querySelectorAll('.sub-menu');

        if (this.isDebugEnabled()) {
            console.log(`[MOBILE MENU] Found ${this.submenus.length} submenus`);
        }

        if (this.submenus.length === 0) {
            return;
        }

        this.submenus.forEach((submenu, index) => {
            if (!submenu.id) {
                submenu.id = `submenu-${index}`;
            }

            submenu.style.display = 'none';
            submenu.style.left = '100vw';
            submenu.style.visibility = 'hidden';
            submenu.style.zIndex = '';

            this.createBackButton(submenu);
        });

        this.addChevronIcons();
    }

    addChevronIcons() {
        const menuItemsWithChildren = this.menu.querySelectorAll('.menu-item-has-children');

        menuItemsWithChildren.forEach((menuItem) => {
            if (menuItem.querySelector(':scope > .mobile-submenu-toggle')) {
                return;
            }

            const submenu = menuItem.querySelector(':scope > .sub-menu');
            if (!submenu) {
                return;
            }

            const link = menuItem.querySelector(':scope > a');
            const label = link ? link.textContent.trim() : '';

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'mobile-submenu-toggle';
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute(
                'aria-label',
                label ? `${label} — open submenu` : 'Open submenu'
            );
            toggleBtn.innerHTML = CHEVRON_RIGHT_SVG;

            menuItem.insertBefore(toggleBtn, submenu);
        });
    }

    createBackButton(submenu) {
        const backButton = document.createElement('button');
        backButton.type = 'button';
        backButton.className = 'back-button';
        backButton.setAttribute('aria-label', 'Back');
        backButton.innerHTML = '<span class="back-button-label">BACK</span>';

        const backLi = document.createElement('li');
        backLi.className = 'mobile-submenu-back-item';
        backLi.appendChild(backButton);

        submenu.insertBefore(backLi, submenu.firstChild);

        backButton.addEventListener('click', (e) => {
            e.preventDefault();
            this.goBack();
        });
    }

    bindEvents() {
        this.toggleButton.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggle();
        });

        this.overlay.addEventListener('click', (e) => {
            e.preventDefault();
            this.close();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isActive) {
                if (this.navigationStack.length > 0) {
                    this.goBack();
                } else {
                    this.close();
                }
            }
        });

        window.addEventListener('resize', () => {
            if (this.isActive && window.innerWidth > 768) {
                this.close();
            }
        });

        this.bindSubmenuEvents();
        this.bindCloseSearchEvents();
    }

    bindSubmenuEvents() {
        this.menu.addEventListener('click', (e) => {
            const toggle = e.target.closest('.mobile-submenu-toggle');
            if (!toggle || !this.menu.contains(toggle)) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const menuItem = toggle.closest('.menu-item-has-children');
            if (!menuItem) {
                return;
            }

            const submenu = menuItem.querySelector(':scope > .sub-menu');
            if (!submenu) {
                return;
            }

            toggle.setAttribute('aria-expanded', 'true');
            this.navigateToSubmenu(submenu);
        });
    }

    bindCloseSearchEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('close-search') || e.target.closest('.close-search')) {
                e.preventDefault();

                const mobileSearch = document.querySelector('.mobile-search');
                if (mobileSearch) {
                    mobileSearch.classList.remove('active');

                    if (this.isDebugEnabled()) {
                        console.log('[MOBILE MENU] Removed active class from mobile-search');
                    }
                }
            }
        });
    }

    navigateToSubmenu(submenu) {
        const level = this.navigationStack.length;
        this.navigationStack.push(submenu);

        submenu.style.zIndex = String(SUBMENU_BASE_Z + level);
        submenu.style.display = 'flex';
        submenu.style.flexDirection = 'column';
        submenu.style.left = '100vw';
        submenu.style.visibility = 'visible';

        submenu.offsetHeight;

        submenu.style.left = '0';

        if (this.isDebugEnabled()) {
            console.log('[MOBILE MENU] Navigated to submenu, stack length:', this.navigationStack.length);
        }
    }

    goBack() {
        if (this.navigationStack.length === 0) {
            return;
        }

        const currentSubmenu = this.navigationStack.pop();

        const parentMenuItem = currentSubmenu.closest('.menu-item-has-children');
        const toggle = parentMenuItem ? parentMenuItem.querySelector(':scope > .mobile-submenu-toggle') : null;
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }

        currentSubmenu.style.left = '100vw';

        const slideMs = 400;

        setTimeout(() => {
            currentSubmenu.style.display = 'none';
            currentSubmenu.style.left = '100vw';
            currentSubmenu.style.visibility = 'hidden';
            currentSubmenu.style.zIndex = '';
        }, slideMs);

        if (this.isDebugEnabled()) {
            console.log('[MOBILE MENU] Went back, stack length:', this.navigationStack.length);
        }
    }

    toggle() {
        if (this.isActive) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        document.body.classList.add('mobile-menu-active');
        this.isActive = true;

        document.dispatchEvent(new CustomEvent('mobileMenuOpen'));

        if (this.isDebugEnabled()) {
            console.log('[MOBILE MENU] Mobile menu opened');
        }
    }

    close() {
        this.resetNavigationState();

        document.body.classList.remove('mobile-menu-active');
        this.isActive = false;

        document.dispatchEvent(new CustomEvent('mobileMenuClose'));

        if (this.isDebugEnabled()) {
            console.log('[MOBILE MENU] Mobile menu closed');
        }
    }

    resetNavigationState() {
        this.submenus.forEach((submenu) => {
            submenu.style.display = 'none';
            submenu.style.left = '100vw';
            submenu.style.visibility = 'hidden';
            submenu.style.zIndex = '';
        });

        this.menu.querySelectorAll('.mobile-submenu-toggle').forEach((btn) => {
            btn.setAttribute('aria-expanded', 'false');
        });

        this.navigationStack = [];

        if (this.isDebugEnabled()) {
            console.log('[MOBILE MENU] Navigation state reset');
        }
    }
}

export default MobileMenu;
