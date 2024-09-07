<div id="<?= MAME_TW_PREFIX ?>-setup-page-4" class="<?= MAME_TW_PREFIX ?>-setup-page <?= $active ? '' : 'hidden'; ?>">

    <h3><?= __( '4. Enroll cash register', 'mametwint' ) ?></h3>

    <div class="<?= MAME_TW_PREFIX ?>-editor-field required">
        <p class="<?= MAME_TW_PREFIX ?>-editor-field-description"><?= __( 'Click the button below to enroll the cash register and complete the setup.<br><br>The (virtual) cash register has to be enrolled once before you can use TWINT on the checkout page. If you change the register ID you will have to enroll the cash register again. If enrolment fails either store UUID or certificate password is not correct or the certificate has not been correctly converted.', 'mametwint' ) ?></p><?php
        ?>
        <div class="<?= MAME_TW_PREFIX ?>-editor-field-inner">

            <input id="<?= MAME_TW_PREFIX ?>-enroll_register" class="button button-loader" type="submit" name=enroll_register"
                   value="<?php echo __( 'Enroll', 'mametwint' ) ?>"/>


        </div>

    </div>

</div>