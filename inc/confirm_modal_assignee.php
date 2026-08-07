<!-- Centered Assignee Warning -->
<div id="assignee_warning" class="alert alert-warning"
     style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); z-index:999999; max-width:500px; text-align:center;">
    <strong><?php echo lang_get('plugin_warning') ?>:</strong><br>
    <?php echo lang_get('plugin_assignee_warning_message'); ?>
    <br><br>
    <!--             TODO: MAYBE FEATURE IN THE FEATURE   -->
<!--    <button id="assignee_warning_confirm"-->
<!--            class="btn btn-warning btn-sm">--><?php //echo lang_get('plugin_assignee_warning_button_fix') ?><!--</button>-->
    <button id="assignee_warning_close"
            class="btn btn-default btn-sm"><?php echo lang_get('plugin_assignee_warning_button_close') ?></button>
</div>

