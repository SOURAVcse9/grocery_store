<?php
/**
 * ==========================================================================
 * public/components/search-bar.php
 * ==========================================================================
 * Reusable header/banner search bar component.
 * Supports autocomplete bindings and GET redirections.
 * ==========================================================================
 */

declare(strict_types=1);

$searchQueryVal = $searchQuery ?? '';
?>
<form action="<?= url_for('search.php') ?>" method="get" class="header-search-form">
    <div class="search-input-group">
        <input type="text" 
               name="q" 
               id="smartSearch" 
               placeholder="Search for groceries, brands, categories..." 
               value="<?= e($searchQueryVal) ?>" 
               autocomplete="off" 
               required 
               minlength="2"
               aria-label="Search Store">
        <button type="submit" class="header-search-btn" aria-label="Submit Search">
            <i class="fas fa-magnifying-glass"></i>
        </button>
        <!-- Dynamic Autocomplete Box Drawer -->
        <div class="search-suggestions" id="searchSuggestions"></div>
    </div>
</form>
