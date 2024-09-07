<div class="<?= MAME_TW_PREFIX ?>-multi-steps-container">
    <ul class="<?= MAME_TW_PREFIX ?>-multi-steps">
        <li id="<?= MAME_TW_PREFIX ?>-multi-step-1"
            class="<?= $page == 1 ? 'is-active' : '' ?> step"><?= __( 'License', 'mametwint' ) ?></li>
        <li id="<?= MAME_TW_PREFIX ?>-multi-step-2"
            class="<?= $page == 2 ? 'is-active' : '' ?> step"><?= __( 'TWINT credentials', 'mametwint' ) ?></li>
        <li id="<?= MAME_TW_PREFIX ?>-multi-step-3"
            class="<?= $page == 3 ? 'is-active' : '' ?> step"><?= __( 'Convert certificate', 'mametwint' ) ?></li>
        <li id="<?= MAME_TW_PREFIX ?>-multi-step-4"
            class="<?= $page == 4 ? 'is-active' : '' ?> step"><?= __( 'Enroll cash register', 'mametwint' ) ?></li>
    </ul>
</div>