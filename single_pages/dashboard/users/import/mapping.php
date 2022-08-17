<?php

defined('C5_EXECUTE') or die('Access Denied.');

/** @var \Concrete\Core\Form\Service\Form $form */
/** @var \Concrete\Core\Validation\CSRF\Token $token */
/** @var \Concrete\Core\Page\View\PageView $view */

$fID = $fID ?? 0;
$canDelete = $canDelete ?? false;
$header = $header ?? [];
$headers = $headers ?? [];
$selected_headers = $selected_headers ?? [];
$save = $save ?? true;
$delete = $delete ?? false;
$options = ['' => t('** Ignore')];
foreach ($header as $column) {
    $options[$column] = $column;
}
?>
<form id="ccm-user-csv-import" method="post" action="<?= $view->action('do_import') ?>">
    <h3><?= t('Importable properties') ?></h3>
    <p><?= t('Please select CSV columns to import.') ?>
        <?php
        $token->output('import_user_csv');
        echo $form->hidden('csv', $fID);
        foreach ($headers
        as $handle => $label) {
        $selected = $label;
        if (isset($selected_headers[$handle])) {
            $selected = $selected_headers[$handle];
        }
        ?>
    <div class="form-group">
        <?= $form->label($handle, $label) ?>
        <?= $form->select($handle, $options, $selected) ?>
    </div>
    <?php
    }
    ?>
    <div class="form-group">
        <h3><?= t('Options') ?></h3>
        <div class="form-check">
            <label class="form-check-label">
                <?= $form->checkbox('save', 1, $save) ?>
                <?= t('Keep mapping') ?>
            </label>
        </div>
        <?php if ($canDelete) { ?>
            <div class="form-check">
                <label class="form-check-label">
                    <?= $form->checkbox('delete', 1, $delete) ?>
                    <?= t('Delete CSV file after imported') ?>
                </label>
            </div>
        <?php } ?>
    </div>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <?= $form->submit('submit', t('Import'), ['class' => 'btn-primary float-end']) ?>
        </div>
    </div>
</form>
<script type="text/javascript">
    $(function () {
        $("#ccm-user-csv-import").on('submit', function () {
            new ConcreteProgressiveOperation({
                url: $(this).attr('action'),
                data: $(this).serializeArray(),
                title: <?= json_encode(t('Import User CSV')) ?>,
                onComplete: function () {
                    window.location.href = <?=json_encode((string) $this->action('import_completed')) ?>;
                }
            });
            return false;
        });
    });
</script>