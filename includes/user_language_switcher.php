<?php $userLanguageSwitcherCompact = $userLanguageSwitcherCompact ?? false; ?>
<div class="user-language-switcher<?= $userLanguageSwitcherCompact ? ' user-language-switcher-compact' : '' ?>" role="group" aria-label="Language" data-i18n-aria-label="language_label">
    <span class="user-language-label" data-i18n="language_label">Language</span>
    <button class="lang-btn" data-lang="en" type="button" data-i18n="lang_en">EN</button>
    <button class="lang-btn" data-lang="ar" type="button" data-i18n="lang_ar">AR</button>
</div>
