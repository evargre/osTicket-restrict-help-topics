<?php
require_once INCLUDE_DIR . 'class.forms.php';

class UserTopicsConfig extends PluginConfig {

    function getOptions() {
        return array(
            'info' => new SectionBreakField(array(
                'label' => __('Help topics per user'),
                'hint' => __('Control Panel → Users → open a user (click their name) → «Help Topics» tab (next to Tickets and Notes). Check the allowed topics and click Save. If nothing is checked and «No restriction» is enabled, the user can use all public help topics when opening a ticket. The plugin must be Active under Admin → Plugins.'),
            )),
        );
    }
}
