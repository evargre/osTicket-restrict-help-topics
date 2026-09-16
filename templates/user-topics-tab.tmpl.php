<?php
if (!defined('OSTSCPINC')) die('Access denied');

$allowed = isset($allowed) ? $allowed : null;
$topics = isset($topics) ? $topics : array();
$user = isset($user) ? $user : null;
if (!$user)
    return;

$allowedSet = $allowed ? array_flip($allowed) : array();
$hasRestriction = ($allowed !== null);
$canEdit = isset($canEdit) ? $canEdit : false;
?>
<form id="user-topics-form" method="post" action="#">
<?php if (!$canEdit) { ?>
    <p class="error"><?php echo __('You do not have permission to edit users. Contact an administrator.'); ?></p>
<?php } ?>
    <p><?php echo __(
        'Select the help topics this user may choose when opening a ticket. Leave all unchecked with «No restriction» to allow every public help topic.'
    ); ?></p>
    <p>
        <label>
            <input type="checkbox" id="user-topics-restrict-all" name="restrict"
                <?php if (!$hasRestriction) echo 'checked="checked"'; ?>
                <?php if (!$canEdit) echo 'disabled="disabled"'; ?>>
            <?php echo __('No restriction (all public help topics)'); ?>
        </label>
    </p>
    <div id="user-topics-list" <?php if (!$hasRestriction) echo 'style="display:none"'; ?>>
        <table class="list" width="100%">
            <thead>
                <tr>
                    <th width="28">&nbsp;</th>
                    <th><?php echo __('Help Topic'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($topics) {
                foreach ($topics as $id => $name) {
                    $checked = isset($allowedSet[$id]) ? 'checked="checked"' : '';
                    echo sprintf(
                        '<tr><td><input type="checkbox" name="topic_ids[]" value="%d" %s %s /></td><td>%s</td></tr>',
                        (int) $id,
                        $checked,
                        $canEdit ? '' : 'disabled="disabled"',
                        Format::htmlchars($name)
                    );
                }
            } else {
                echo '<tr><td colspan="2"><em>' . __('No public help topics.') . '</em></td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php if ($canEdit) { ?>
    <p class="full-width">
        <span class="buttons pull-right">
            <input type="button" class="save-user-topics" value="<?php echo __('Save'); ?>">
        </span>
    </p>
    <?php } ?>
</form>
<?php if ($canEdit) { ?>
<script type="text/javascript">
$(function() {
    $('#user-topics-restrict-all').on('change', function() {
        $('#user-topics-list').toggle(!this.checked);
        if (this.checked)
            $('#user-topics-list input[type=checkbox]').prop('checked', false);
    });
    $('.save-user-topics').on('click', function() {
        var data = { topic_ids: [] };
        if (!$('#user-topics-restrict-all').prop('checked')) {
            $('#user-topics-list input[name="topic_ids[]"]:checked').each(function() {
                data.topic_ids.push($(this).val());
            });
        }
        $.ajax('ajax.php/user-topics/users/<?php echo (int) $user->getId(); ?>', {
            type: 'POST',
            data: data,
            dataType: 'json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-CSRFToken', $('meta[name=csrf_token]').attr('content'));
            },
            success: function() {
                alert('<?php echo __('Saved successfully'); ?>');
            },
            error: function(xhr) {
                alert(xhr.responseText || '<?php echo __('Unable to save'); ?>');
            }
        });
    });
});
</script>
<?php } ?>
