<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.signal.php';
require_once INCLUDE_DIR . 'class.topic.php';
require_once INCLUDE_DIR . 'class.user.php';
require_once 'config.php';

if (!defined('USER_TOPIC_ACCESS_TABLE'))
    define('USER_TOPIC_ACCESS_TABLE', TABLE_PREFIX . 'user_topic_access');

class UserTopicAccess {

    static $topicIdError;

    static function tableExists() {
        static $exists = null;
        if ($exists !== null)
            return $exists;
        $exists = false;
        if (!function_exists('db_query'))
            return $exists;
        if (($res = db_query('SHOW TABLES LIKE \'' . USER_TOPIC_ACCESS_TABLE . '\'')))
            $exists = (db_num_rows($res) > 0);
        return $exists;
    }

    static function createTable() {
        if (!function_exists('db_query'))
            return false;
        $sql = 'CREATE TABLE IF NOT EXISTS `' . USER_TOPIC_ACCESS_TABLE . '` (
            `user_id` int(10) unsigned NOT NULL,
            `topic_id` int(10) unsigned NOT NULL,
            PRIMARY KEY (`user_id`, `topic_id`),
            KEY `topic_id` (`topic_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        return db_query($sql);
    }

    static function dropTable() {
        if (!function_exists('db_query'))
            return false;
        return db_query('DROP TABLE IF EXISTS `' . USER_TOPIC_ACCESS_TABLE . '`');
    }

    static function getAllowedTopicIds($user_id) {
        $user_id = (int) $user_id;
        if (!$user_id || !self::tableExists())
            return null;

        $ids = array();
        $sql = 'SELECT topic_id FROM `' . USER_TOPIC_ACCESS_TABLE
            . '` WHERE user_id=' . db_input($user_id);
        if (($res = db_query($sql))) {
            while ($row = db_fetch_array($res))
                $ids[] = (int) $row['topic_id'];
        }
        if (!count($ids))
            return null;

        return $ids;
    }

    static function isTopicAllowed($user_id, $topic_id) {
        $allowed = self::getAllowedTopicIds($user_id);
        if ($allowed === null)
            return true;
        return in_array((int) $topic_id, $allowed, true);
    }

    static function setAllowedTopics($user_id, array $topic_ids) {
        $user_id = (int) $user_id;
        if (!$user_id || !self::tableExists())
            return false;

        db_query('DELETE FROM `' . USER_TOPIC_ACCESS_TABLE
            . '` WHERE user_id=' . db_input($user_id));

        $topic_ids = array_unique(array_filter(array_map('intval', $topic_ids)));
        foreach ($topic_ids as $tid) {
            if ($tid <= 0)
                continue;
            if (!Topic::lookup($tid))
                continue;
            db_query('INSERT INTO `' . USER_TOPIC_ACCESS_TABLE
                . '` (user_id, topic_id) VALUES ('
                . db_input($user_id) . ', ' . db_input($tid) . ')');
        }
        return true;
    }

    static function validateTicketTopic($user_id, $topic_id) {
        if (!$user_id || !$topic_id)
            return true;
        if (self::isTopicAllowed($user_id, $topic_id))
            return true;

        self::$topicIdError = __('You are not allowed to select this Help Topic');
        return false;
    }

    /**
     * Only enforce on end-user web portal (open.php), not staff/API/email.
     */
    static function isClientWebTicketCreate(array $vars) {
        global $thisstaff;

        if ($thisstaff)
            return false;

        if (!empty($vars['response']) || array_key_exists('assignId', $vars))
            return false;

        if (isset($vars['source']) && strcasecmp($vars['source'], 'Web') !== 0)
            return false;

        if (isset($vars['a']) && $vars['a'] !== 'open')
            return false;

        return true;
    }

    static function onTicketCreateBefore($object, &$vars) {
        if (!self::isClientWebTicketCreate($vars))
            return;

        if (empty($vars['uid']) || empty($vars['topicId']))
            return;

        if (!self::validateTicketTopic($vars['uid'], $vars['topicId']))
            $vars['topicId'] = 0;
    }
}

class UserTopicsPlugin extends Plugin {
    var $config_class = 'UserTopicsConfig';

    static $hooksRegistered = false;

    /** @var bool */
    static $outputBufferStarted = false;

    function isMultiInstance() {
        return false;
    }

    function init() {
        if (!$this->isActive())
            return;
        if (!UserTopicAccess::tableExists())
            UserTopicAccess::createTable();
        self::registerHooks($this);
    }

    function bootstrap() {
        self::registerHooks($this);
    }

    static function registerHooks($plugin) {
        if (self::$hooksRegistered)
            return;
        self::$hooksRegistered = true;

        Signal::connect('ticket.create.before',
            array('UserTopicAccess', 'onTicketCreateBefore'));

        Signal::connect('usertab.audit', function($user, &$extras) {
            global $thisstaff;
            if (!$thisstaff || !$thisstaff->hasPerm(User::PERM_DIRECTORY))
                return;
            echo sprintf(
                '<li><a href="#user-topics"><i class="icon-tags"></i>&nbsp;%s</a></li>',
                __('Help Topics')
            );
        }, null, function($user) {
            return $user instanceof User;
        });

        Signal::connect('user.audit', function($user, &$extras) {
            global $thisstaff;
            if (!$thisstaff || !$thisstaff->hasPerm(User::PERM_DIRECTORY))
                return;

            $canEdit = $thisstaff->hasPerm(User::PERM_EDIT);
            $allowed = UserTopicAccess::getAllowedTopicIds($user->getId());
            $topics = Topic::getPublicHelpTopics();

            echo '<div class="info-banner" style="margin:12px 0;padding:10px 14px;background:#eef5ff;border:1px solid #bcd;">';
            echo '<strong>' . __('Help Topics (plugin)') . '</strong> — ';
            echo __('Open the «Help Topics» tab next to «Tickets» and «Notes» to choose which topics this user may select in the client portal.');
            echo '</div>';

            echo '<div class="hidden tab_content" id="user-topics">';
            include dirname(__FILE__) . '/templates/user-topics-tab.tmpl.php';
            echo '</div>';
        }, null, function($user) {
            return $user instanceof User;
        });

        Signal::connect('ajax.scp', function($dispatcher) {
            $dispatcher->append(
                url_post('^/user-topics/users/(?P<uid>\d+)$', function($uid) {
                    global $thisstaff;

                    if (!$thisstaff || !$thisstaff->hasPerm(User::PERM_EDIT))
                        Http::response(403, __('Permission denied'));

                    if (!($user = User::lookup((int) $uid)))
                        Http::response(404, __('Unknown user'));

                    $topicIds = isset($_POST['topic_ids']) && is_array($_POST['topic_ids'])
                        ? $_POST['topic_ids'] : array();

                    if (!UserTopicAccess::setAllowedTopics($user->getId(), $topicIds))
                        Http::response(500, __('Unable to save'));

                    Http::response(201, __('Successfully updated'));
                })
            );
        });

        if ($plugin->shouldBufferOpenTicketPage())
            $plugin->startOpenTicketOutputBuffer();
    }

    /**
     * Buffer only the GET form — never POST (avoids breaking redirects / 500s).
     */
    function shouldBufferOpenTicketPage() {
        if (self::$outputBufferStarted)
            return false;
        if (osTicket::is_cli())
            return false;
        if (!isset($_SERVER['SCRIPT_NAME'])
                || basename($_SERVER['SCRIPT_NAME']) !== 'open.php')
            return false;
        if (isset($_SERVER['REQUEST_METHOD'])
                && strcasecmp($_SERVER['REQUEST_METHOD'], 'GET') !== 0)
            return false;
        return true;
    }

    function startOpenTicketOutputBuffer() {
        self::$outputBufferStarted = true;
        ob_start(array($this, 'filterOpenTicketPage'));
    }

    function filterOpenTicketPage($html) {
        global $thisclient;

        if (!$html || !$thisclient || !$thisclient->isValid())
            return $html;

        try {
            $userId = (int) $thisclient->getId();
            $allowed = UserTopicAccess::getAllowedTopicIds($userId);
            if ($allowed === null)
                return $this->injectTopicAccessError($html);

            $allowed = array_flip($allowed);

            $html = preg_replace_callback(
                '/<select id="topicId" name="topicId"[^>]*>(.*?)<\/select>/s',
                function ($m) use ($allowed) {
                    $inner = preg_replace_callback(
                        '/<option value="(\d+)"[^>]*>.*?<\/option>/s',
                        function ($opt) use ($allowed) {
                            $id = (int) $opt[1];
                            return isset($allowed[$id]) ? $opt[0] : '';
                        },
                        $m[1]
                    );
                    return str_replace($m[1], $inner, $m[0]);
                },
                $html
            );

            $jsAllowed = json_encode(array_values(array_map('intval', array_keys($allowed))));
            $script = <<<JS
<script type="text/javascript">
(function($) {
  $(function() {
    var allowed = {$jsAllowed};
    if (!allowed || !allowed.length) return;
    $('#topicId option').each(function() {
      var v = $(this).val();
      if (!v) return;
      if ($.inArray(parseInt(v, 10), allowed) === -1)
        $(this).remove();
    });
    var sel = $('#topicId').val();
    if (sel && $.inArray(parseInt(sel, 10), allowed) === -1)
      $('#topicId').val('');
  });
})(jQuery);
</script>
JS;
            if (stripos($html, '</body>') !== false)
                $html = str_ireplace('</body>', $script . "\n</body>", $html);
            else
                $html .= $script;

            return $this->injectTopicAccessError($html);
        } catch (Throwable $e) {
            return $html;
        }
    }

    function injectTopicAccessError($html) {
        if (!UserTopicAccess::$topicIdError)
            return $html;

        $msg = Format::htmlchars(UserTopicAccess::$topicIdError);
        $replacement = '<font class="error">*&nbsp;' . $msg . '</font>';

        if (preg_match('/(<select id="topicId"[^>]*>.*?<\/select>\s*)(<font class="error">.*?<\/font>)?/s', $html)) {
            $html = preg_replace(
                '/(<select id="topicId"[^>]*>.*?<\/select>\s*)(<font class="error">.*?<\/font>)?/s',
                '$1' . $replacement,
                $html,
                1
            );
        }
        UserTopicAccess::$topicIdError = null;
        return $html;
    }

    function enable() {
        UserTopicAccess::createTable();
        return parent::enable();
    }

    function pre_uninstall(&$errors) {
        UserTopicAccess::dropTable();
        return parent::pre_uninstall($errors);
    }
}
