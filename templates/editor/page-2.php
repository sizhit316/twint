<div id="<?= MAME_TW_PREFIX ?>-setup-page-2" class="<?= MAME_TW_PREFIX ?>-setup-page <?= $active ? '' : 'hidden'; ?>">

    <h3><?= __( '2. Enter TWINT credentials', 'mametwint' ) ?></h3>

    <p><?= sprintf( __( 'Enter the information below after you created a new store in the %1$sTWINT%2$s portal.', 'mametwint' ), '<a href="https://portal.twint.ch/partner/gui/?login" target="_blank">', '</a>' ) ?></p>
    <br>

    <!-- Store UUID -->
    <div class="<?= MAME_TW_PREFIX ?>-editor-field required">

        <label for="<?= MAME_TW_PREFIX ?>-store_uuid"><?= __( 'Store UUID', 'mametwint' ) ?></label><?php
        ?>
        <p class="<?= MAME_TW_PREFIX ?>-editor-field-description"><?= __( 'The UUID of your store can be found in the TWINT account by selecting your shop on the page <strong>STORES > [edit store button]</strong>. If the list of stores is empty you will first have to create a store.', 'mametwint' ) ?></p>
        <div class="<?= MAME_TW_PREFIX ?>-editor-field-inner">
            <?php $store_uuid = get_option( 'mametw_settings_uuid' ); ?>
            <input type="text" id="<?= MAME_TW_PREFIX ?>-store_uuid" name="store_uuid"
                   placeholder="<?= __( 'Enter the store UUID', 'mametwint' ) ?>" value="<?= $store_uuid ?: '' ?>">
        </div>

    </div>

    <!-- Certificate password -->
    <div class="<?= MAME_TW_PREFIX ?>-editor-field required">

        <label for="<?= MAME_TW_PREFIX ?>-certificate_password"><?= __( 'Certificate passphrase', 'mametwint' ) ?></label><?php
        ?>
        <p class="<?= MAME_TW_PREFIX ?>-editor-field-description"><?= __( 'The password for the certificate which was set in the TWINT account when the certificate was created. This is not the password for the TWINT account. Please only use the following special characters in your password: ~!@#%^*_+-={}[]:,./<br>If you forgot the password for your certificate or if you used any special character which is not allowed, you can contact TWINT to create a new certificate: <a href="mailto:support@twint.ch">support@twint.ch</a>', 'mametwint' ) ?></p>
        <div class="<?= MAME_TW_PREFIX ?>-editor-field-inner">
            <?php $pass = get_option( 'mametw_settings_certpw' ); ?>
            <input type="password" id="<?= MAME_TW_PREFIX ?>-certificate_password" name="certificate_password"
                   placeholder="<?= __( 'Enter the certificate password', 'mametwint' ) ?>"
                   value="<?= $pass ? stripcslashes( $pass ) : '' ?>">
        </div>

    </div>

    <!-- Register ID -->
    <div class="<?= MAME_TW_PREFIX ?>-editor-field required">

        <label for="<?= MAME_TW_PREFIX ?>-register_id"><?= __( 'Cash Register ID', 'mametwint' ) ?></label><?php
        ?>
        <p class="<?= MAME_TW_PREFIX ?>-editor-field-description"><?= __( 'Choose any ID to identify transactions from your sales point in the TWINT backend. You can choose any name but names have to be different for shops which use the same TWINT account.', 'mametwint' ) ?></p>
        <div class="<?= MAME_TW_PREFIX ?>-editor-field-inner">
            <?php $register_id = get_option( 'mametw_settings_registerid' ); ?>
            <input type="text" id="<?= MAME_TW_PREFIX ?>-register_id" name="register_id"
                   placeholder="<?= __( 'Enter a cash register ID', 'mametwint' ) ?>" value="<?= $register_id ?: '' ?>">
        </div>

    </div>

</div>

