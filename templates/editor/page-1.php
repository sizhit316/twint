<div id="<?= MAME_TW_PREFIX ?>-setup-page-1" class="<?= MAME_TW_PREFIX ?>-setup-page <?= $active ? '' : 'hidden'; ?>">

    <h3><?= __( '1. Enter license key', 'mametwint' ) ?></h3>

    <div class="<?= MAME_TW_PREFIX ?>-editor-field required">
        <label for="<?= MAME_TW_PREFIX ?>-license_key"><?= sprintf( __( 'Please enter the plugin license key you received by email. You can always find your license keys in your account at %1$smamedev.ch%2$s.', 'mametwint' ), '<a href="https://www.mamedev.ch" target="_blank">', '</a>' ) ?></label><?php
        ?>
        <div class="<?= MAME_TW_PREFIX ?>-editor-field-inner">
            <input type="text" id="<?= MAME_TW_PREFIX ?>-license_key" name="license_key"
                   placeholder="<?= __( 'Enter the license key', 'mametwint' ) ?>" value="<?= $license ?>">
        </div>

    </div>

</div>