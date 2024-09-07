<div id="<?= MAME_TW_PREFIX ?>-setup-assistant-wrapper" data-page="<?= $page ?>">

    <div id="<?= MAME_TW_PREFIX ?>-setup-assistant-loader"></div>

    <button id="<?= MAME_TW_PREFIX ?>-cancel-setup-button" class="button"><?= __( 'Cancel setup', 'mametwint' ) ?></button>

    <div id="<?= MAME_TW_PREFIX ?>-navigation" class="<?= MAME_TW_PREFIX ?>-setup-assistant-section">
        <?php mame_twint_get_template( 'editor/navigation.php', [ 'page' => $page ] ); ?>
    </div>

    <div id="<?= MAME_TW_PREFIX ?>-editor-header" class="<?= MAME_TW_PREFIX ?>-setup-assistant-section">
        <?php mame_twint_get_template( 'editor/header.php' ); ?>
    </div>

    <div id="<?= MAME_TW_PREFIX ?>-pages" class="<?= MAME_TW_PREFIX ?>-setup-assistant-section">
        <?php mame_twint_get_template( 'editor/page-1.php', [ 'active' => $page == 1, 'license' => $license ] ); ?>
        <?php mame_twint_get_template( 'editor/page-2.php', [ 'active' => $page == 2 ] ); ?>
        <?php mame_twint_get_template( 'editor/page-3.php', [ 'active' => $page == 3 ] ); ?>
        <?php mame_twint_get_template( 'editor/page-4.php', [ 'active' => $page == 4 ] ); ?>
        <?php mame_twint_get_template( 'editor/page-5.php', [ 'active' => $page == 5, 'gateway_enabled' => $gateway_enabled ] ); ?>
    </div>

    <div id="<?= MAME_TW_PREFIX ?>-footer" class="<?= MAME_TW_PREFIX ?>-setup-assistant-section">
        <?php mame_twint_get_template( 'editor/footer.php', [ 'page' => $page ] ); ?>
    </div>

</div>