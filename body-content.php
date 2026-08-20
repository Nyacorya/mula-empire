<!-- body-content.php – minimal header and content for the landing page -->
<header id="header">
  <div class="container">
    <div class="logo">
      <a href="/"><img src="<?= htmlspecialchars($social_media_logo_url ?? '/img/logo.png') ?>" alt="<?= htmlspecialchars($brand_name ?? 'Logo') ?>" height="40"></a>
    </div>
    <nav class="desktop-nav">
      <a href="#">Home</a>
      <a href="#" id="liveChatLink">Live Chat</a>
      <a href="#" id="helpCenterLink">Help Center</a>
      <a href="#" id="installAppLink" class="install-link"><i class="fas fa-download"></i> Install App</a>
      <p id="appInstalledMsg" class="app-installed-msg" style="display:none;">App installed!</p>
    </nav>
    <button id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
  </div>
  <!-- Mobile navigation (hidden by default) -->
  <div id="mobileNavLinks" class="mobile-nav-links" style="display:none;">
    <a href="#">Home</a>
    <a href="#" id="liveChatLinkMobile">Live Chat</a>
    <a href="#" id="helpCenterLinkMobile">Help Center</a>
    <a href="#" id="installAppLinkMobile" class="install-link"><i class="fas fa-download"></i> Install App</a>
  </div>
</header>

<!-- Hero Section (example) -->
<section class="hero">
  <div class="container">
    <h1><?= htmlspecialchars($hero_title) ?></h1>
    <p><?= htmlspecialchars($hero_subtitle) ?></p>
    <a href="<?= htmlspecialchars($cta_button_url) ?>" class="btn"><?= htmlspecialchars($cta_button_text) ?></a>
    <a href="<?= htmlspecialchars($secondary_cta_url) ?>" class="btn btn-outline"><?= htmlspecialchars($secondary_cta_text) ?></a>
  </div>
</section>

<!-- The rest of your landing page content can go here -->
<p>Welcome to <?= htmlspecialchars($site_title) ?></p>