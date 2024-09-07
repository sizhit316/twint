<div id="<?= MAME_TW_PREFIX ?>-setup-page-5" class="<?= MAME_TW_PREFIX ?>-setup-page <?= $active ? '' : 'hidden'; ?>">

    <h3><?= __( 'Setup complete', 'mametwint' ) ?></h3>

    <p><?= __( 'The setup for the TWINT plugin is complete. You can now activate the payment method on the checkout and make a test payment.', 'mametwint' ) ?></p>

    <div class="<?= MAME_TW_PREFIX ?>-editor-field">
        <span id="<?= MAME_TW_PREFIX ?>-enable-gateway-toggle"
              class="woocommerce-input-toggle woocommerce-input-toggle--<?= $gateway_enabled ? 'enabled' : 'disabled' ?>"><?= __( 'Enable gateway', 'mametwint' ); ?></span>
        <label for="<?= MAME_TW_PREFIX ?>-enable-gateway-toggle"><?= __( 'Enable TWINT on the checkout', 'mametwint' ); ?></label>
    </div>
</div>