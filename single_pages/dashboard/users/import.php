<?php

defined('C5_EXECUTE') or die('Access Denied.');

/** @var \Concrete\Core\Application\Service\FileManager $html */
/** @var \Concrete\Core\Form\Service\Form $form */
/** @var \Concrete\Core\Validation\CSRF\Token $token */
/** @var \Concrete\Core\Page\View\PageView $view */
?>
<form method="post" action="<?=$view->action('select_mapping')?>">
    <?= $form->hidden('save', 1) ?>
    <fieldset>
        <legend><?=t('Select CSV File')?></legend>
        <div class="form-group">
            <?php
            echo $html->file('csv', 'csv', t('Choose File'));
            ?>
        </div>
    </fieldset>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <?= $form->submit('submit', t('Next'), ['class' => 'btn-primary float-end']) ?>
        </div>
    </div>
</form>
