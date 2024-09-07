<div id="<?= MAME_TW_PREFIX ?>-setup-page-3" class="<?= MAME_TW_PREFIX ?>-setup-page <?= $active ? '' : 'hidden'; ?>">

    <h3><?= __( '3. Convert the TWINT certificate', 'mametwint' ) ?></h3>

    <div class="<?= MAME_TW_PREFIX ?>-editor-field required">
        <p class="<?= MAME_TW_PREFIX ?>-editor-field-description"><?= sprintf( __( 'Upload the certificate you downloaded from your TWINT account in p12 format to automatically convert it.<br><br>If automatic conversion fails you will have to manually convert the certificate to PEM format and either rename it twint.txt and use the upload button or directly upload the renamed file twint.pem to the directory wp-content/uploads/mame_twint.<br><br>Read the %1$s documentation %2$s for more information.', 'mametwint' ), '<a href="https://documentation.mame-webdesign.ch/?p=273&lang=en#1-2%c2%a0certificate-creation" target="_blank">', '</a>' ) ?></p><?php
        ?>
        <div class="<?= MAME_TW_PREFIX ?>-editor-field-inner">

            <?php wp_enqueue_media();

            $options = get_option( MAME_TW_PREFIX . '_options_group' );
            $value   = isset( $options[ 'certificate' ] ) ? $options[ 'certificate' ] : '';

            ?>

            <input type="hidden" name="certificate_upload"
                   id="certificate_upload"
                   value="<?= $value ?>"/>
            <div id="<?= MAME_TW_PREFIX ?>-certificate_upload-wrapper">
                <button type="submit"
                        class="<?= MAME_TW_PREFIX ?>-certificate_upload_button button"
                        data-environment="<?= esc_html( MAME_TW_ENVIRONMENT ) ?>"><?= __( 'Upload file', 'mametwint' ) ?>
                </button>
            </div>
            <div id="<?= MAME_TW_PREFIX ?>-certificate_upload-loader"
                 class="mame-loader <?= MAME_TW_ENVIRONMENT ?>"><img
                        src="<?= plugins_url() . '/' . MAME_TW_PLUGIN_DIRNAME . '/assets/images/loader_round.gif' ?>">
            </div>
        </div>

    </div>

    <?php if ( file_exists( \Mame_Twint\Twint_Helper::get_certificate_file_path() ) ) { ?>

        <div class="<?= MAME_TW_PREFIX ?>-editor-field required">

            <p class="<?= MAME_TW_PREFIX ?>-editor-field-description"><?= __( 'Certificate file exists. Upload a new certificate if the current certificate has expired.', 'mametwint' ) ?></p><?php
            ?>
            <div class="<?= MAME_TW_PREFIX ?>-editor-field-inner">
                <button class="<?= MAME_TW_PREFIX ?>-editor-skip-button button"><?= __( 'Use existing certificate file', 'mametwint' ) ?></button>
            </div>

        </div>

    <?php } ?>

</div>