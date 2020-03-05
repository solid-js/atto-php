<!DOCTYPE html>
<html lang="<?= _::html('locales.language', 1) ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">

    <!--
      @version     : <?= _::html('config.version', 0, true, "\n") ?>
      @base        : <?= _::html('config.base', 0, true, "\n") ?>
      @host        : <?= _::html('config.host', 0, true, "\n") ?>
    -->

    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1, minimum-scale=1.0, maximum-scale=1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />

    <!-- PAGE META -->
    <title><?= _::html('meta.title') ?></title>
    <meta name="description" content="<?= _::html('meta.description') ?>">

    <!-- FACEBOOK -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= _::html('meta.url') ?>">
    <meta property="og:title" content="<?= _::html('meta.title') ?>">
    <meta property="og:description" content="<?= _::html('meta.description') ?>">
    <meta property="og:image" content="<?= _::html('meta.image') ?>">

    <!-- TWITTER -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:url" content="<?= _::html('meta.url') ?>">
    <meta name="twitter:title" content="<?= _::html('meta.title') ?>">
    <meta name="twitter:description" content="<?= _::html('meta.description') ?>">
    <meta name="twitter:image" content="<?= _::html('meta.image') ?>">

    <!-- SCRIPT LOADING -->
    <script nomodule>self.legacy=1;console.warn('Using legacy scripts.')</script>
    <script>function $loadScript(e,d,c){c=document.createElement('script');c.src=(self.legacy?d:e);document.head.appendChild(c)}</script>
</head>
<body>

<!-- PRELOADER -->
<style>
    #Preloader {}
</style>
<div id="Preloader"></div>

<!-- APP DATA -->
<script>
    var __appData = <?= _::html('.', 3) ?>
</script>

<!-- APP CONTAINER -->
<div id="AppContainer"></div>

<!-- APP RESOURCES -->
<link rel="stylesheet" href="<?= _::href('static/index.css', false, true) ?>" />
<script defer>
    $loadScript(
        "<?= _::href('static/index.js', false, true) ?>",
        "<?= _::href('static/index.legacy.js', false, true) ?>"
    );
</script>
</body>
</html>