(function () {
    'use strict';

    var config = window.AabhushanTourConfig || {};
    if (!config.stateUrl || !config.adminId) {
        return;
    }

    var steps = [
        {
            key: 'welcome',
            target: 'brand',
            title: 'Welcome to Aabhushan ERP',
            description: 'This guided tour introduces your role-specific workspace. It only shows modules your login can access, so every user gets a relevant tour.'
        },
        {
            key: 'dashboard-overview',
            target: 'dashboard-overview',
            title: 'Your operational dashboard',
            description: 'Start here for today’s customer, order and inventory picture. Quick actions let authorised users create an order or customer.'
        },
        {
            key: 'dashboard-kpis',
            target: 'dashboard-kpis',
            title: 'Live order indicators',
            description: 'These cards show assignment needs, active customers, active orders and today’s dispatches. Select a card to open its working list.'
        },
        {
            key: 'inventory-summary',
            target: 'inventory-summary',
            title: 'Metal and requirement summary',
            description: 'Review fine-gold stock, pending gold requirement and weighted pure-gold purchase price without opening individual ledgers.'
        },
        {
            key: 'dashboard-activity',
            target: 'dashboard-activity',
            title: 'Action queue and recent work',
            description: 'The lower dashboard highlights orders needing assignment and the latest order activity, with direct links to the relevant record.'
        },
        {
            key: 'page-content',
            target: 'page-content',
            title: 'Page workspace',
            description: 'Every module opens in this workspace. Use its filters, searchable tables, status badges and action buttons to review or update records allowed by your permissions.'
        },
        {
            key: 'navigation',
            target: 'navigation',
            title: 'Role-aware navigation',
            description: 'The sidebar is your module map. Sections and actions are automatically limited by your assigned roles and direct permission overrides.'
        },
        {
            key: 'dashboard',
            module: 'dashboard',
            title: 'Dashboard',
            description: 'Return to the operational overview, assignment queue and recent order activity from here.'
        },
        {
            key: 'customers',
            module: 'customers',
            title: 'Customers and portal access',
            description: 'View customer details, contact and GST information, sales people and customer login accounts. Authorised users can create portal users and securely reset their passwords.'
        },
        {
            key: 'orders',
            module: 'orders',
            title: 'Complete order workflow',
            description: 'Track all, ready and repair orders; monitor delayed and repeat designs; add follow-ups with images; view timelines, finished jewellery details and order documents.'
        },
        {
            key: 'production',
            module: 'production',
            title: 'Production management',
            description: 'Manage karigar profiles, independent gold/diamond/stone issuements and the design master. Karigar and material ledgers remain connected to every posted transaction.'
        },
        {
            key: 'gold-inventory',
            module: 'gold-inventory',
            title: 'Gold inventory',
            description: 'Maintain purities and products, record purchases and returns, make controlled adjustments, and review stock plus the running gold ledger.'
        },
        {
            key: 'diamond-inventory',
            module: 'diamond-inventory',
            title: 'Diamond inventory',
            description: 'Maintain diamond classifications and follow purchases, returns, adjustments, pieces, carat balance, average cost and warehouse stock.'
        },
        {
            key: 'stone-inventory',
            module: 'stone-inventory',
            title: 'Stone inventory',
            description: 'Maintain stone items and rates, then track purchases, returns, adjustments, quantities and current stock value.'
        },
        {
            key: 'inventory-setup',
            module: 'inventory-setup',
            title: 'Inventory setup',
            description: 'Configure physical warehouses and bins used to locate gold, diamonds, stones and finished jewellery.'
        },
        {
            key: 'showroom',
            module: 'showroom',
            title: 'Retail showroom',
            description: 'Control finished jewellery, showroom and counter stock, reservations and transfers, staff assignments, sales and downloadable invoices.'
        },
        {
            key: 'accounts',
            module: 'accounts',
            title: 'Accounts and ledgers',
            description: 'Review payables and receivables, journal vouchers, party balances, unified ledgers, issue/receive movements, payments, bills, notes, outstanding balances and GST.'
        },
        {
            key: 'reports',
            module: 'reports',
            title: 'Reports and analysis',
            description: 'Filter the combined transaction register and material ledgers, then analyse inventory, karigar performance and the staff directory by date and party.'
        },
        {
            key: 'administration',
            module: 'administration',
            title: 'Administration',
            description: 'Depending on access, this section contains vendors, organisation hierarchy, KPI management, company settings, database updates, roles, permissions and user access.'
        },
        {
            key: 'profile',
            target: 'profile',
            title: 'Help is always available',
            description: 'Open your profile menu and choose “Application Tour” whenever you want to replay this guide. Finish now, skip for this session, or permanently choose “Never show again”.'
        }
    ];

    var state = null;
    var activeSteps = [];
    var currentIndex = 0;
    var active = false;
    var busy = false;
    var wasMiniSidebar = false;
    var mobileSidebarOpenedByTour = false;
    var nodes = {};
    var snoozeKey = 'aabhushan-admin-tour-snoozed-' + String(config.adminId);

    function allForStep(step) {
        var selector = step.module
            ? '[data-app-tour-module="' + step.module + '"]'
            : '[data-app-tour="' + step.target + '"]';
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function existingStep(step) {
        return allForStep(step).length > 0;
    }

    function visibleElement(elements) {
        for (var i = 0; i < elements.length; i += 1) {
            var rect = elements[i].getBoundingClientRect();
            var style = window.getComputedStyle(elements[i]);
            if (rect.width > 1 && rect.height > 1 && style.display !== 'none' && style.visibility !== 'hidden') {
                return elements[i];
            }
        }
        return elements[0] || null;
    }

    function isNavigationStep(step) {
        return Boolean(step.module) || step.target === 'navigation';
    }

    function prepareNavigation(step) {
        if (!isNavigationStep(step)) {
            if (mobileSidebarOpenedByTour) {
                var openWrapper = document.querySelector('.main-wrapper');
                if (openWrapper) {
                    openWrapper.classList.remove('slide-nav');
                }
                document.documentElement.classList.remove('menu-opened');
                var openOverlay = document.querySelector('.sidebar-overlay');
                if (openOverlay) {
                    openOverlay.classList.remove('opened');
                }
                mobileSidebarOpenedByTour = false;
            }
            return;
        }

        var wrapper = document.querySelector('.main-wrapper');
        if (window.innerWidth < 992 && wrapper && !wrapper.classList.contains('slide-nav')) {
            wrapper.classList.add('slide-nav');
            document.documentElement.classList.add('menu-opened');
            var sidebarOverlay = document.querySelector('.sidebar-overlay');
            if (sidebarOverlay) {
                sidebarOverlay.classList.add('opened');
            }
            mobileSidebarOpenedByTour = true;
        }

        if (document.body.classList.contains('mini-sidebar')) {
            document.body.classList.remove('mini-sidebar');
        }
    }

    function restoreNavigation() {
        if (wasMiniSidebar) {
            document.body.classList.add('mini-sidebar');
        }
        if (mobileSidebarOpenedByTour) {
            var wrapper = document.querySelector('.main-wrapper');
            if (wrapper) {
                wrapper.classList.remove('slide-nav');
            }
            document.documentElement.classList.remove('menu-opened');
            var sidebarOverlay = document.querySelector('.sidebar-overlay');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('opened');
            }
        }
        mobileSidebarOpenedByTour = false;
    }

    function createNodes() {
        if (nodes.card) {
            return;
        }

        nodes.shades = ['top', 'left', 'right', 'bottom'].map(function (name) {
            var shade = document.createElement('div');
            shade.className = 'app-tour-shade app-tour-shade-' + name;
            shade.setAttribute('aria-hidden', 'true');
            document.body.appendChild(shade);
            return shade;
        });

        nodes.highlight = document.createElement('div');
        nodes.highlight.className = 'app-tour-highlight';
        nodes.highlight.setAttribute('aria-hidden', 'true');
        document.body.appendChild(nodes.highlight);

        nodes.card = document.createElement('section');
        nodes.card.className = 'app-tour-card';
        nodes.card.setAttribute('role', 'dialog');
        nodes.card.setAttribute('aria-modal', 'true');
        nodes.card.setAttribute('aria-labelledby', 'appTourTitle');
        nodes.card.innerHTML = '' +
            '<div class="app-tour-card-header">' +
                '<div class="app-tour-kicker"><span>Application tour</span><span class="app-tour-counter"></span></div>' +
                '<h2 class="app-tour-title" id="appTourTitle"></h2>' +
            '</div>' +
            '<div class="app-tour-card-body">' +
                '<p class="app-tour-description"></p>' +
                '<div class="app-tour-message" role="alert"></div>' +
            '</div>' +
            '<div class="app-tour-progress" aria-hidden="true"><div class="app-tour-progress-bar"></div></div>' +
            '<div class="app-tour-card-footer">' +
                '<div class="app-tour-secondary-actions">' +
                    '<button type="button" class="app-tour-btn app-tour-btn-muted" data-tour-action="skip">Skip for now</button>' +
                    '<button type="button" class="app-tour-btn app-tour-btn-never" data-tour-action="never">Never show again</button>' +
                '</div>' +
                '<div class="app-tour-primary-actions">' +
                    '<button type="button" class="app-tour-btn app-tour-btn-back" data-tour-action="back">Back</button>' +
                    '<button type="button" class="app-tour-btn app-tour-btn-next" data-tour-action="next">Next</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(nodes.card);

        nodes.counter = nodes.card.querySelector('.app-tour-counter');
        nodes.title = nodes.card.querySelector('.app-tour-title');
        nodes.description = nodes.card.querySelector('.app-tour-description');
        nodes.message = nodes.card.querySelector('.app-tour-message');
        nodes.progress = nodes.card.querySelector('.app-tour-progress-bar');
        nodes.back = nodes.card.querySelector('[data-tour-action="back"]');
        nodes.next = nodes.card.querySelector('[data-tour-action="next"]');
        nodes.skip = nodes.card.querySelector('[data-tour-action="skip"]');
        nodes.never = nodes.card.querySelector('[data-tour-action="never"]');

        nodes.back.addEventListener('click', function () { move(-1); });
        nodes.next.addEventListener('click', function () { move(1); });
        nodes.skip.addEventListener('click', skipForNow);
        nodes.never.addEventListener('click', neverShowAgain);
    }

    function setRect(element, left, top, width, height) {
        element.style.left = Math.max(0, left) + 'px';
        element.style.top = Math.max(0, top) + 'px';
        element.style.width = Math.max(0, width) + 'px';
        element.style.height = Math.max(0, height) + 'px';
    }

    function position(target) {
        if (!active || !target || !nodes.card) {
            return;
        }

        var raw = target.getBoundingClientRect();
        var padding = window.innerWidth < 641 ? 5 : 8;
        var left = Math.max(0, raw.left - padding);
        var top = Math.max(0, raw.top - padding);
        var right = Math.min(window.innerWidth, raw.right + padding);
        var bottom = Math.min(window.innerHeight, raw.bottom + padding);
        var width = Math.max(1, right - left);
        var height = Math.max(1, bottom - top);

        setRect(nodes.shades[0], 0, 0, window.innerWidth, top);
        setRect(nodes.shades[1], 0, top, left, height);
        setRect(nodes.shades[2], right, top, window.innerWidth - right, height);
        setRect(nodes.shades[3], 0, bottom, window.innerWidth, window.innerHeight - bottom);
        setRect(nodes.highlight, left, top, width, height);

        nodes.card.classList.toggle('app-tour-card-mobile', window.innerWidth <= 640);
        if (window.innerWidth <= 640) {
            nodes.card.style.left = '12px';
            nodes.card.style.right = '12px';
            nodes.card.style.top = 'auto';
            nodes.card.style.bottom = '12px';
            return;
        }

        nodes.card.style.right = 'auto';
        nodes.card.style.bottom = 'auto';
        var cardRect = nodes.card.getBoundingClientRect();
        var gap = 18;
        var cardLeft;
        var cardTop;
        if (window.innerWidth - right >= cardRect.width + gap) {
            cardLeft = right + gap;
            cardTop = top;
        } else if (left >= cardRect.width + gap) {
            cardLeft = left - cardRect.width - gap;
            cardTop = top;
        } else if (window.innerHeight - bottom >= cardRect.height + gap) {
            cardLeft = Math.min(Math.max(12, left), window.innerWidth - cardRect.width - 12);
            cardTop = bottom + gap;
        } else {
            cardLeft = Math.min(Math.max(12, left), window.innerWidth - cardRect.width - 12);
            cardTop = Math.max(12, top - cardRect.height - gap);
        }
        cardTop = Math.min(Math.max(12, cardTop), window.innerHeight - cardRect.height - 12);
        nodes.card.style.left = cardLeft + 'px';
        nodes.card.style.top = cardTop + 'px';
    }

    function currentTarget() {
        var step = activeSteps[currentIndex];
        return step ? visibleElement(allForStep(step)) : null;
    }

    function showStep() {
        var step = activeSteps[currentIndex];
        if (!step) {
            closeTour();
            return;
        }

        prepareNavigation(step);
        var target = currentTarget();
        if (!target) {
            move(1, true);
            return;
        }

        target.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
        nodes.counter.textContent = String(currentIndex + 1) + ' of ' + String(activeSteps.length);
        nodes.title.textContent = step.title;
        nodes.description.textContent = step.description;
        nodes.message.textContent = '';
        nodes.message.classList.remove('is-visible');
        nodes.progress.style.width = String(((currentIndex + 1) / activeSteps.length) * 100) + '%';
        nodes.back.hidden = currentIndex === 0;
        nodes.next.textContent = currentIndex === activeSteps.length - 1 ? 'Finish tour' : 'Next';
        nodes.card.classList.remove('is-ready');

        window.setTimeout(function () {
            position(target);
            nodes.card.classList.add('is-ready');
            nodes.next.focus({ preventScroll: true });
        }, isNavigationStep(step) ? 180 : 20);
    }

    function setBusy(value) {
        busy = value;
        [nodes.back, nodes.next, nodes.skip, nodes.never].forEach(function (button) {
            if (button) {
                button.disabled = value;
            }
        });
    }

    function showMessage(message) {
        nodes.message.textContent = message;
        nodes.message.classList.add('is-visible');
    }

    async function move(direction, selectorWasMissing) {
        if (!active || busy) {
            return;
        }

        var nextIndex = currentIndex + direction;
        if (direction > 0 && currentIndex === activeSteps.length - 1) {
            setBusy(true);
            var completed = await saveState('completed', null);
            setBusy(false);
            if (!completed) {
                showMessage('The tour could not be marked complete. Please check your connection and try again.');
                return;
            }
            closeTour();
            return;
        }

        if (nextIndex < 0 || nextIndex >= activeSteps.length) {
            return;
        }
        currentIndex = nextIndex;
        if (!selectorWasMissing) {
            setBusy(true);
            await saveState('progress', activeSteps[currentIndex].key);
            setBusy(false);
        }
        showStep();
    }

    function skipForNow() {
        try {
            window.sessionStorage.setItem(snoozeKey, '1');
        } catch (error) {
            // Session storage can be unavailable in strict privacy modes.
        }
        closeTour();
    }

    async function neverShowAgain() {
        if (busy) {
            return;
        }
        setBusy(true);
        var saved = await saveState('dismissed', null);
        setBusy(false);
        if (!saved) {
            showMessage('Your preference could not be saved. Run Database Update and check the connection before trying again.');
            return;
        }
        closeTour();
    }

    function closeTour() {
        if (!active) {
            return;
        }
        active = false;
        document.body.classList.remove('app-tour-active');
        restoreNavigation();
        window.removeEventListener('resize', reposition);
        window.removeEventListener('orientationchange', reposition);
        window.removeEventListener('scroll', reposition, true);
        document.removeEventListener('keydown', keyHandler);
        (nodes.shades || []).forEach(function (node) { node.remove(); });
        if (nodes.highlight) {
            nodes.highlight.remove();
        }
        if (nodes.card) {
            nodes.card.remove();
        }
        nodes = {};
    }

    function reposition() {
        window.requestAnimationFrame(function () {
            position(currentTarget());
        });
    }

    function keyHandler(event) {
        if (!active || busy) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            skipForNow();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            move(1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            move(-1);
        }
    }

    async function saveState(action, stepKey) {
        try {
            var response = await window.fetch(config.stateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': config.csrfHash
                },
                body: JSON.stringify({ action: action, stepKey: stepKey })
            });
            var data = await response.json();
            if (data.csrfHash) {
                config.csrfHash = data.csrfHash;
            }
            return response.ok && data.success === true;
        } catch (error) {
            return false;
        }
    }

    async function loadState() {
        try {
            var response = await window.fetch(config.stateUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            var data = await response.json();
            if (data.csrfHash) {
                config.csrfHash = data.csrfHash;
            }
            return response.ok && data.success === true ? data.tour : null;
        } catch (error) {
            return null;
        }
    }

    async function startTour(options) {
        options = options || {};
        if (active) {
            return;
        }
        activeSteps = steps.filter(existingStep);
        if (!activeSteps.length) {
            return;
        }

        currentIndex = 0;
        if (!options.manual && state && state.currentStepKey) {
            var resumeIndex = activeSteps.findIndex(function (step) {
                return step.key === state.currentStepKey;
            });
            if (resumeIndex >= 0) {
                currentIndex = resumeIndex;
            }
        }

        wasMiniSidebar = document.body.classList.contains('mini-sidebar');
        active = true;
        document.body.classList.add('app-tour-active');
        createNodes();
        window.addEventListener('resize', reposition);
        window.addEventListener('orientationchange', reposition);
        window.addEventListener('scroll', reposition, true);
        document.addEventListener('keydown', keyHandler);
        await saveState('started', activeSteps[currentIndex].key);
        showStep();
    }

    function loaderFinished() {
        var loader = document.getElementById('globalLoaderOverlay');
        return !loader || !loader.classList.contains('active') || loader.getAttribute('aria-hidden') === 'true';
    }

    function waitForPageReady(callback) {
        var startedAt = Date.now();
        var timer = window.setInterval(function () {
            if (loaderFinished() || Date.now() - startedAt > 8000) {
                window.clearInterval(timer);
                window.requestAnimationFrame(function () {
                    window.setTimeout(callback, 350);
                });
            }
        }, 100);
    }

    function bindReplay() {
        document.addEventListener('click', async function (event) {
            var trigger = event.target.closest('[data-app-tour-replay]');
            if (!trigger) {
                return;
            }
            event.preventDefault();
            state = await loadState();
            startTour({ manual: true });
        });
    }

    window.addEventListener('load', function () {
        bindReplay();
        waitForPageReady(async function () {
            state = await loadState();
            var snoozed = false;
            try {
                snoozed = window.sessionStorage.getItem(snoozeKey) === '1';
            } catch (error) {
                snoozed = false;
            }
            if (state && state.available && state.shouldAutoStart && !snoozed) {
                startTour({ manual: false });
            }
        });
    });
})();
