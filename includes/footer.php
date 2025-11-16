<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../system/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- Standard footer content -->
    <div class="footer-inner" style="text-align: center; padding: 10px 0;">
        <p style="margin: 0;">
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
        </p>
        <p style="font-size: 11px; color: #666; margin: 4px 0 0;">
            Version 2.5.3 - Training Role System - Update 3🎓
        </p>

        <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
            <div style="margin-top: 10px;">
                <a href="/bugs/bug_report.php" class="btn btn-primary btn-small" style="font-size: 12px; padding: 8px 16px;">
                    🐛 Report a Bug
                </a>
            </div>
        <?php endif; ?>
    </div>
</footer>

<?php
    // Only show the Latest Updates widget on specific pages.
    // Edit this array to control where it appears.
    $LATEST_UPDATES_PAGES = [
        'index.php',   // example page – change/add as needed
        // 'training_dashboard.php',
        // 'some_other_page.php',
    ];

    $current_script = basename($_SERVER['PHP_SELF'] ?? '');
    $show_latest_updates_widget = in_array($current_script, $LATEST_UPDATES_PAGES, true);
    ?>

    <?php if ($show_latest_updates_widget): ?>
        <!-- Latest Updates Widget -->
    <div id="latestUpdatesWidget" class="latest-updates-widget" style="display:none;">
        <!-- Toggle Button -->
        <div id="updatesToggleBtn" class="updates-toggle-btn" onclick="toggleLatestUpdates()" title="Latest Updates">
            <span class="updates-icon">📢</span>
            <span class="updates-text">Latest Updates</span>
            <span id="updatesArrow" class="updates-arrow">▲</span>
        </div>

        <!-- Content Panel -->
        <div id="updatesContent" class="updates-content collapsed">
            <div class="updates-header">
                <h4 id="updatesHeaderTitle">📢 Latest Updates</h4>
                <button class="updates-close-btn" onclick="toggleLatestUpdates()" title="Close">×</button>
            </div>

            <div class="updates-body">
                <!-- Latest Updates Panel -->
                <div id="latestUpdatesPanel" class="updates-panel active">
                    <div class="updates-list">
                        <!-- Version 2.5.3 -->
                        <div class="update-item">
                            <div class="update-version">v2.5.3</div>
                            <div class="update-title">Training Role System - Update 3</div>
                            <div class="update-features">
                                <div class="feature-item">⚠️ Most issues fixed. Report bugs if found ⚠️</div>
                                <div class="feature-item">- Ability to have images in quiz questions</div>
                                <div class="feature-item">- Updated the update widget to have roadmap and made only visible on home page</div>
                                <br>
                                <div class="feature-item">Still to do:</div>
                                <div class="feature-item">- Ability for admins to do training</div>
                                <div class="feature-item">- Test training course management improvements</div>
                            </div>
                            <div class="update-date">2025-11-13</div>
                        </div>
                        <!-- Version 2.5.2 -->
                        <div class="update-item">
                            <div class="update-version">v2.5.2</div>
                            <div class="update-title">Training Role System - Update 2 ⚠️</div>
                            <div class="update-features">
                                <div class="feature-item">⚠️ Update is still in progress so expect bugs and less functionality ⚠️</div>
                                <div class="feature-item">- Fixed search functionality</div>
                                <div class="feature-item">- Made the visuals and links more informative for training dashboard</div>
                                <div class="feature-item">- Increased the visibility of what quizzes need to be done</div>
                                <br>
                                <div class="feature-item">Still to do:</div>
                                <div class="feature-item">- Ability for admins to do training</div>
                                <div class="feature-item">- Test training course management improvements</div>
                                <div class="feature-item">- Ability to have images in quiz questions</div>
                            </div>
                            <div class="update-date">2025-11-12</div>
                        </div>
                        
                        <!-- Version 2.5.1 -->
                        <div class="update-item">
                            <div class="update-version">v2.5.1</div>
                            <div class="update-title">Training Role System - Update 1 ⚠️</div>
                            <div class="update-features">
                                <div class="feature-item">⚠️ Update is still in progress so expect bugs and less functionality ⚠️</div>
                                <div class="feature-item">- Allowed courses to be made, assigned to staff.</div>
                                <div class="feature-item">- Quizzes can be made and assigned to course content</div>
                                <div class="feature-item">- Training users are promoted to user after finishing training</div>
                                <div class="feature-item">- Users are changed to training user if assigned a course</div>
                                <br>
                                <div class="feature-item">Still to do:</div>
                                <div class="feature-item">- Ability for admins to do training</div>
                                <div class="feature-item">- Make the training dashboard links better</div>
                                <div class="feature-item">- Increase visibility of what quizzes need to be done as a training user</div>
                            </div>
                            <div class="update-date">2025-11-12</div>
                        </div>
                        
                        <!-- Version 2.5.0 -->
                        <div class="update-item">
                            <div class="update-version">v2.5.0</div>
                            <div class="update-title">Training Role System - WIP ⚠️</div>
                            <div class="update-features">
                                <div class="feature-item">⚠️ Update is still in progress so expect bugs and less functionality</div>
                                <div class="feature-item">- Adding comprehensive training role system for new staff</div>
                                <div class="feature-item">- Adding training progress bar in header for training users</div>
                                <div class="feature-item">- Adding Admin interface for creating and managing training courses</div>
                            </div>
                            <div class="update-date">2025-11-10</div>
                        </div>

                        <!-- Version 2.4.3 -->
                        <div class="update-item">
                            <div class="update-version">v2.4.3</div>
                            <div class="update-title">Latest Updates Widget</div>
                            <div class="update-features">
                                <div class="feature-item">- Added expandable Latest Updates widget in bottom-right corner</div>
                                <div class="feature-item">- Shows version history with features and fixes</div>
                            </div>
                            <div class="update-date">2025-11-05</div>
                        </div>
                    </div>

                    
                </div>
                

                <!-- Roadmap Panel (edit this content as you like) -->
                <div id="roadmapPanel" class="updates-panel">
                    <div class="roadmap-list">
                        <div class="roadmap-section">
                            <div class="roadmap-label roadmap-label-now">Now</div>
                            <div class="update-version">v2.5.3</div>
                            <ul>
                                <li>Quiz deadlines with admin notifications</li>
                                <li>Training Role System polish and bug fixes</li>
                                <li>Improve quiz UX and reporting</li>
                                <li>Admin page for training statistics</li>
                                <li>Manage quizzes page. Add per course selection instead of being shown all posts and all questions</li>
                            </ul>
                        </div>

                        <div class="roadmap-section">
                            <div class="roadmap-label roadmap-label-next">Next</div>
                            <div class="update-version">v2.6.0</div>
                            <ul>
                                <li>Departments – department-based visibility</li>
                                <li>Admin-friendly training management improvements</li>
                            </ul>
                        </div>

                        <div class="roadmap-section">
                            <div class="roadmap-label roadmap-label-later">Later</div>
                            <div class="update-version">v2.7.0+</div>
                            <ul>
                                <li>2.7.0 Retest periods - For knowledge rentention</li>
                                <li>2.8.0 Email functionality - Notifying managers / users about training</li>
                                <li>2.9.0 File clean-up</li>
                                <li>3.0.0 Landing page overhaul - Menu for different programs</li>
                                <li>4.0.0 🤫</li> <!-- H&S program -->
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Side toggle button (rotated, on the right side) -->
                <button
                    id="updatesSideToggle"
                    class="updates-side-toggle"
                    onclick="toggleUpdatesMode()"
                    title="Show roadmap"
                    type="button"
                >
                    <span class="side-text">Roadmap</span>
                </button>
            </div>
        </div>

    <style>
        /* Latest Updates Widget Styles */
        .latest-updates-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .updates-toggle-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            font-weight: 500;
            font-size: 14px;
        }

        .updates-toggle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }

        .updates-icon {
            font-size: 16px;
        }

        .updates-text {
            flex: 1;
        }

        .updates-arrow {
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .updates-content {
            position: absolute;
            bottom: 100%;
            right: 0;
            width: 380px;
            max-height: 560px; /* a bit taller overall */
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform-origin: bottom right;
            /* no transition until we explicitly mark it as ready (prevents load flicker) */
            transition: none;
            border: 1px solid rgba(0, 0, 0, 0.1);

            /* layout: header on top, body fills rest */
            display: flex;
            flex-direction: column;

            /* Hidden by default */
            opacity: 0;
            visibility: hidden;
            transform: scale(0.8) translateY(20px);
        }

        /* Once DOM is ready, we add .ready so toggles animate, but initial paint does not */
        .updates-content.ready {
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .updates-body {
            position: relative;
            flex: 1 1 auto;      /* take remaining space under header */
            min-height: 380px;   /* a bit taller content area */
        }

        .updates-panel {
            position: absolute;
            inset: 0; /* top:0; right:0; bottom:0; left:0; */
            display: flex;
            flex-direction: column;
            opacity: 0;
            transform: translateX(10px);
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }

        .updates-panel.active {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }

        .roadmap-list {
            max-height: 420px;
            overflow-y: auto;
            padding: 12px 20px 16px 32px;
        }

        .roadmap-section {
            margin-bottom: 14px;
        }

        .roadmap-label {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 3px 8px;
            border-radius: 999px;
            margin-bottom: 6px;
        }

        .roadmap-label-now {
            background: rgba(102, 126, 234, 0.13);
            color: #4c51bf;
        }

        .roadmap-label-next {
            background: rgba(72, 187, 120, 0.13);
            color: #2f855a;
        }

        .roadmap-label-later {
            background: rgba(236, 201, 75, 0.18);
            color: #975a16;
        }

        .roadmap-section ul {
            list-style: disc;
            padding-left: 18px;
            margin: 0;
        }

        .roadmap-section li {
            font-size: 12px;
            color: #555;
            margin-bottom: 3px;
        }

        .updates-side-toggle {
            position: absolute;
            top: 50%;
            transform-origin: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 999px;
            padding: 10px 18px;      /* bigger click target */
            font-size: 13px;         /* slightly larger text */
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            min-width: 90px;         /* ensure label fits nicely */
            z-index: 1001;           /* above panel edges */
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        /* On the right side (for switching to Roadmap) */
        .updates-side-toggle.side-right {
            right: -38px; /* pull it further in so it's fully visible */
            transform: translateY(-50%) rotate(-90deg);
        }

        .updates-side-toggle.side-right:hover {
            transform: translateY(-50%) rotate(-90deg) translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.55);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }

        /* On the left side (for switching back to Latest Updates) */
        .updates-side-toggle.side-left {
            left: -55px; /* symmetric to right side */
            transform: translateY(-50%) rotate(90deg);
        }

        .updates-side-toggle.side-left:hover {
            transform: translateY(-50%) rotate(90deg) translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.55);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }

        .updates-side-toggle .side-text {
            pointer-events: none;
        }

        .updates-content.collapsed {
            opacity: 0;
            visibility: hidden;
            transform: scale(0.8) translateY(20px);
        }

        .updates-content.expanded {
            opacity: 1;
            visibility: visible;
            transform: scale(1) translateY(0);
        }

        .updates-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .updates-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .updates-close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background 0.2s ease;
        }

        .updates-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .updates-list {
            max-height: 420px;
            overflow-y: auto;
            padding: 0;
        }

        .update-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .update-item:hover {
            background-color: #f8f9fa;
        }

        .update-item:last-child {
            border-bottom: none;
        }

        .update-version {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .update-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .update-features {
            margin-bottom: 6px;
        }

        .feature-item {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 3px;
        }

        .update-date {
            font-size: 11px;
            color: #999;
            font-style: italic;
        }

        .updates-footer {
            padding: 12px 20px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #f0f0f0;
        }

        /* Custom scrollbar for updates list */
        .updates-list::-webkit-scrollbar {
            width: 6px;
        }

        .updates-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .updates-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .updates-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Mobile responsiveness */
        @media (max-width: 480px) {
            .latest-updates-widget {
                bottom: 10px;
                right: 10px;
                left: 10px;
            }

            .updates-content {
                width: 100%;
                max-width: 350px;
                right: auto;
                left: 0;
            }

            .updates-toggle-btn {
                padding: 10px 14px;
                font-size: 13px;
            }

            .updates-side-toggle {
                font-size: 11px;
                padding: 6px 10px;
            }

            .updates-side-toggle.side-right {
                right: -34px;
            }

            .updates-side-toggle.side-left {
                left: -34px;
            }
        }
    </style>

    <script>
        // Latest Updates functionality
        let isUpdatesExpanded = false;
        let currentUpdatesMode = 'latest'; // 'latest' or 'roadmap'

        function setUpdatesMode(mode) {
            const latestPanel = document.getElementById('latestUpdatesPanel');
            const roadmapPanel = document.getElementById('roadmapPanel');
            const sideToggle = document.getElementById('updatesSideToggle');
            const headerTitle = document.getElementById('updatesHeaderTitle');

            if (!latestPanel || !roadmapPanel || !sideToggle || !headerTitle) {
                return;
            }

            // Reset side classes each time
            sideToggle.classList.remove('side-left', 'side-right');

            if (mode === 'roadmap') {
                latestPanel.classList.remove('active');
                roadmapPanel.classList.add('active');
                currentUpdatesMode = 'roadmap';

                headerTitle.textContent = '🗺️ Roadmap';
                sideToggle.querySelector('.side-text').textContent = 'Latest Updates';
                sideToggle.setAttribute('title', 'Show latest updates');

                // When viewing roadmap, show "Latest Updates" on the LEFT
                sideToggle.classList.add('side-left');
            } else {
                roadmapPanel.classList.remove('active');
                latestPanel.classList.add('active');
                currentUpdatesMode = 'latest';

                headerTitle.textContent = '📢 Latest Updates';
                sideToggle.querySelector('.side-text').textContent = 'Roadmap';
                sideToggle.setAttribute('title', 'Show roadmap');

                // When viewing latest updates, show "Roadmap" on the RIGHT
                sideToggle.classList.add('side-right');
            }
        }

        function toggleUpdatesMode() {
            if (currentUpdatesMode === 'latest') {
                setUpdatesMode('roadmap');
            } else {
                setUpdatesMode('latest');
            }
        }
        
        function toggleLatestUpdates() {
            const content = document.getElementById('updatesContent');
            const arrow = document.getElementById('updatesArrow');
            const toggleBtn = document.getElementById('updatesToggleBtn');

            isUpdatesExpanded = !isUpdatesExpanded;

            if (isUpdatesExpanded) {
                // show
                content.classList.add('expanded');
                content.classList.remove('collapsed');
                arrow.textContent = '▼';
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
            } else {
                // hide
                content.classList.remove('expanded');
                content.classList.add('collapsed');
                arrow.textContent = '▲';
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function () {
    // Unhide widget only after DOM is ready (prevents plain-text flicker)
    const widgetRoot = document.getElementById('latestUpdatesWidget');
    if (widgetRoot) {
        widgetRoot.style.display = 'block';
    }

    // Mark content as ready for animations AFTER first paint
    const content = document.getElementById('updatesContent');
    if (content) {
        content.classList.add('ready');
    }

    // Keep it closed by default via CSS
    const updatesToggleBtn = document.getElementById('updatesToggleBtn');
    if (updatesToggleBtn) {
        updatesToggleBtn.setAttribute('aria-expanded', 'false');
    }

    // Initialize mode once DOM is ready
    try {
        setUpdatesMode('latest');
    } catch (e) {
        // fail silently if widget isn't on this page
    }
});

        // Close updates when clicking outside
        document.addEventListener('click', function(event) {
            const widget = document.getElementById('latestUpdatesWidget');
            const toggleBtn = document.getElementById('updatesToggleBtn');

            if (widget && isUpdatesExpanded && !widget.contains(event.target)) {
                toggleLatestUpdates();
            }
        });
    </script>
    <?php endif; ?>

    <!-- Admin Menu JavaScript (Admin Only) -->
    <?php
    require_once 'user_helpers.php';
    if (is_admin()):
    ?>
    <script>
        function toggleDevMenu() {
            const overlay = document.getElementById('devDropdownOverlay');
            const menu = document.getElementById('devDropdownMenu');

            if (overlay.classList.contains('show')) {
                closeDevMenu();
            } else {
                openDevMenu();
            }
        }

        function openDevMenu() {
            const overlay = document.getElementById('devDropdownOverlay');
            const menu = document.getElementById('devDropdownMenu');

            // Show overlay and menu
            overlay.classList.add('show');
            menu.classList.add('show');

            // Prevent body scroll
            document.body.style.overflow = 'hidden';

            // Add escape key listener
            document.addEventListener('keydown', handleEscapeKey);
        }

        function closeDevMenu() {
            const overlay = document.getElementById('devDropdownOverlay');
            const menu = document.getElementById('devDropdownMenu');

            overlay.classList.remove('show');
            menu.classList.remove('show');

            // Restore body scroll
            document.body.style.overflow = '';

            // Remove escape key listener
            document.removeEventListener('keydown', handleEscapeKey);
        }

        function handleEscapeKey(event) {
            if (event.key === 'Escape') {
                closeDevMenu();
            }
        }

        // Set up overlay click handler
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('devDropdownOverlay');
            if (overlay) {
                overlay.addEventListener('click', closeDevMenu);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>