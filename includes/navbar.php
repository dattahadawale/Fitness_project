<?php
/**
 * Shared Top Navigation Bar Component
 * Gym Management System
 */

$user = current_user();
$title = $navTitle ?? $pageTitle ?? 'Dashboard';
?>
<header class="top-navbar">
    <div class="nav-left">
        <button type="button" class="menu-toggle-btn" id="menuToggle" aria-label="Toggle navigation">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <h1 class="page-title"><?= e($title) ?></h1>
    </div>

    <div class="nav-right">
        <div class="live-time-badge">
            <span style="color: var(--primary);">&#9679;</span>
            <span id="liveClock">--:--:--</span>
        </div>
        <a href="<?= base_url('index.php') ?>" class="btn btn-secondary btn-sm" target="_blank" title="View Public Landing Page">
            <span>Website</span> &nearr;
        </a>
    </div>
</header>
