<?php
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../system/config.php';
}
/**
 * Reusable Search Widget
 * Usage: require_once __DIR__ . '/includes/search_widget.php'; render_search_bar();
 * Optional: render_search_bar($actionPath = '/search/search_working.php')
 */

if (!function_exists('render_search_bar')) {
    function render_search_bar($actionPath = '/search/search_working.php') {
        // Guard to only print JS/CSS once per request
        static $SVS_SEARCH_WIDGET_LOADED = false;

        // HTML (IDs are fixed so keyboard navigation works everywhere)
        $formHtml = <<<'HTML'
<div class="card" style="margin-bottom: 20px; position: relative;">
    <form id="searchForm" method="GET" action="HTML_ACTION_PATH" style="display: flex; gap: 10px;">
        <input
            type="text"
            id="searchInput"
            name="q"
            class="form-input"
            placeholder="Search posts, categories, subcategories..."
            style="flex: 1; margin: 0;"
            autocomplete="off"
        >
        <button type="submit" class="btn btn-primary" style="margin: 0;">🔍 Search</button>
    </form>

    <!-- Autocomplete Dropdown -->
    <div id="autocompleteDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; max-height: 300px; overflow-y: auto;">
        <!-- Results injected by JS -->
    </div>
</div>
HTML;

        // Swap in the desired action path (kept simple and safe)
        $formHtml = str_replace('HTML_ACTION_PATH', htmlspecialchars($actionPath, ENT_QUOTES, 'UTF-8'), $formHtml);
        echo $formHtml;

        // JS/CSS only once
        if ($SVS_SEARCH_WIDGET_LOADED) {
            return;
        }
        $SVS_SEARCH_WIDGET_LOADED = true;

        // JS + CSS
        echo <<<'ASSET'
<script>
let searchTimeout;
let currentAutocompleteResults = [];

document.addEventListener('DOMContentLoaded', function () {
    const inputEl = document.getElementById('searchInput');
    const formEl = document.getElementById('searchForm');
    const dropdown = document.getElementById('autocompleteDropdown');

    if (!inputEl || !formEl || !dropdown) return;

    // Debounced input
    inputEl.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        clearTimeout(searchTimeout);

        if (query.length < 2) {
            dropdown.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            performAutocompleteSearch(query);
        }, 300);
    });

    // Outside click to close
    document.addEventListener('click', function(e) {
        const card = dropdown.parentElement; // card has position: relative
        if (card && !card.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Keyboard nav
    inputEl.addEventListener('keydown', function(e) {
        if (dropdown.style.display === 'none' || currentAutocompleteResults.length === 0) return;

        let selectedIndex = -1;
        const items = dropdown.querySelectorAll('.autocomplete-item');

        for (let i = 0; i < items.length; i++) {
            if (items[i].classList.contains('selected')) {
                selectedIndex = i;
                break;
            }
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateSelectedAutocompleteItem(items, selectedIndex);
                break;
            case 'ArrowUp':
                e.preventDefault();
                selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
                updateSelectedAutocompleteItem(items, selectedIndex);
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedIndex >= 0 && items[selectedIndex]) {
                    items[selectedIndex].click();
                } else {
                    formEl.submit();
                }
                break;
            case 'Escape':
                dropdown.style.display = 'none';
                break;
        }
    });
});

function updateSelectedAutocompleteItem(items, selectedIndex) {
    items.forEach(item => item.classList.remove('selected'));
    if (items[selectedIndex]) {
        items[selectedIndex].classList.add('selected');
        const titleElement = items[selectedIndex].querySelector('.autocomplete-title');
        if (titleElement) {
            const inputEl = document.getElementById('searchInput');
            if (inputEl) inputEl.value = titleElement.textContent;
        }
    }
}

function performAutocompleteSearch(query) {
    fetch('/search/search_autocomplete.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            currentAutocompleteResults = (data && Array.isArray(data.results)) ? data.results : [];
            displayAutocompleteResults(currentAutocompleteResults);
        })
        .catch(error => {
            console.error('Autocomplete search error:', error);
            const dropdown = document.getElementById('autocompleteDropdown');
            if (dropdown) dropdown.style.display = 'none';
        });
}

function displayAutocompleteResults(results) {
    const dropdown = document.getElementById('autocompleteDropdown');
    if (!dropdown) return;

    if (!results || results.length === 0) {
        dropdown.style.display = 'none';
        return;
    }

    let html = '';
    results.forEach(result => {
        const typeIcon = getTypeIcon(result.type);
        const typeColor = getTypeColor(result.type);
        const safeUrl = String(result.url || '#').replace(/"/g, '&quot;');

        html += `
            <div class="autocomplete-item" onclick="selectAutocompleteResult('${safeUrl}')" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; transition: background-color 0.2s;">
                <div style="font-size: 18px; color: ${typeColor};">${typeIcon}</div>
                <div style="flex: 1;">
                    <div class="autocomplete-title" style="font-weight: 500; color: #2d3748; margin-bottom: 2px;">${escapeHtml(result.title || '')}</div>
                    <div style="font-size: 12px; color: #718096;">${escapeHtml(result.subtitle || '')}</div>
                </div>
                <div style="font-size: 12px; color: #a0aec0; text-transform: uppercase; font-weight: 500;">${escapeHtml(result.type || '')}</div>
            </div>
        `;
    });

    dropdown.innerHTML = html;
    dropdown.style.display = 'block';

    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('mouseenter', function() { this.style.backgroundColor = '#f7fafc'; });
        item.addEventListener('mouseleave', function() { this.style.backgroundColor = 'transparent'; });
    });
}

function getTypeIcon(type) {
    const icons = { 'category': '📁', 'subcategory': '📂', 'post': '📄' };
    return icons[type] || '📄';
}

function getTypeColor(type) {
    const colors = { 'category': '#667eea', 'subcategory': '#4299e1', 'post': '#48bb78' };
    return colors[type] || '#718096';
}

function selectAutocompleteResult(url) { window.location.href = url; }

// Simple HTML escaper to avoid XSS in titles/subtitles
function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>

<style>
.autocomplete-item.selected { background-color: #e6f3ff !important; }
.autocomplete-item:hover { background-color: #f7fafc; }
#autocompleteDropdown { animation: slideDown 0.2s ease-out; }
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
ASSET;
    }
}
