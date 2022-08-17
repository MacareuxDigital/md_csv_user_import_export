<?php

defined('C5_EXECUTE') or die('Access Denied.');

$selected_headers = $selected_headers ?? [];
$headers = $headers ?? [];
$save = $save ?? true;
/** @var \Concrete\Core\Form\Service\Form $form */
/** @var \Concrete\Core\Validation\CSRF\Token $token */
/** @var \Concrete\Core\Page\View\PageView $view */
?>
<form method="post" action="<?= $view->action('do_export') ?>">
    <?php $token->output('export_user_csv'); ?>
    <div class="form-group">
        <h3><?= t('Select export columns') ?></h3>
        <?php
        foreach ($headers as $handle => $name) {
            $isChecked = !$selected_headers || in_array($handle, $selected_headers);
            ?>
            <div class="form-check">
                <label class="form-check-label">
                    <?= $form->checkbox('headers[]', $handle, $isChecked) ?>
                    <?= h($name) ?>
                </label>
            </div>
            <?php
        }
        ?>
    </div>
    <div class="form-group">
        <h3><?= t('Options') ?></h3>
        <div class="form-check">
            <label class="form-check-label">
                <?= $form->checkbox('save', 1, $save) ?>
                <?= t('Keep selected headers') ?>
            </label>
        </div>
    </div>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <?= $form->submit('submit', t('Export'), ['class' => 'btn-primary float-end']) ?>
        </div>
    </div>
</form>
