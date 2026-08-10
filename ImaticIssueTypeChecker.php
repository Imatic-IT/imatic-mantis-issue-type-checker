<?php

class ImaticIssueTypeCheckerPlugin extends MantisPlugin
{
    public function register(): void
    {
        $this->name = 'Imatic issue checker';
        $this->description = 'Warning when private issue has public note && added button send public note';
        $this->version = '0.0.5';
        $this->requires = [
            'MantisCore' => '2.0.0',
        ];

        $this->author = 'Imatic Software s.r.o.';
        $this->contact = 'info@imatic.cz';
        $this->url = 'https://www.imatic.cz/';
    }

    public function config(): array
    {
        return [
            'show_issue_status' => true,
            'allow_set_public_issue' => [
                'allow' => true,
            ],
            'warning_public_issue_private_bugnote' => [
                'allow' => true,
            ],
            'warning_private_issue_public_bugnote' => [
                'allow' => true,
            ],
            'submit_confirm_shortcut' => [
                'send_bugnote' => ['Enter', 'NumpadEnter']
            ]
        ];
    }

    public function hooks(): array
    {
        return [
            'EVENT_LAYOUT_BODY_END' => 'layout_body_end_hook',
            'EVENT_BUGNOTE_ADD_FORM' => 'event_bugnote_add_form',
        ];
    }


    public function event_bugnote_add_form($p_event)
    {
        if (isset($_GET['id'])) {
            $issue_id = $_GET['id'];
            $issue = bug_get_row($issue_id);

            // Just UI loader
            echo '<div style="display:none;" id="loader"></div>';

            include 'inc/confirm_modal.php';

            echo '<button class="btn btn-primary btn-white btn-round"  id="add_public_bugnote" type="submit">'.lang_get('plugin_add_public_bugnote_button').'</button>';

            if (plugin_config_get('show_issue_status')) {
                $this->imaticShowViewStateIssueMessage($issue);
            }
        }
    }


    public function  imaticShowViewStateIssueMessage($issue)
    {

        $view_state_issue =  ucfirst($issue['view_state'] == 50 ? lang_get('plugin_private_issue') : lang_get('plugin_public_issue'));
        $class = $issue['view_state'] == 50 ? 'bg-warning' : 'bg-success';

        echo '<p style="margin: 5px;padding: 5px;" class=" ' . $class . '">' . $view_state_issue . '</p>';
    }

    public function ImaticChangeStatusAccess()
    {

        require_api('authentication_api.php');

        if (!auth_is_user_authenticated()) {
            return;
        }

        if (!empty($_GET['imaticProject'])) {
            $project_id = gpc_get_int('imaticProject');
        } else {
            $project_id = helper_get_current_project();
        }

        $access =  user_get_access_level(auth_get_current_user_id(), $project_id) >= config_get('change_view_status_threshold') ? true : false;
        return $access;
    }


    public function layout_body_end_hook($p_event)
    {

        $issue_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        // The hook runs on every page, and `id` there is not always a bug id
        // (manage_user_edit_page.php, plugin pages, ...) — bug_get_row() would
        // throw ERROR_BUG_NOT_FOUND on those.
        if ($issue_id > 0 && bug_exists($issue_id)) {
            $issue = bug_get_row($issue_id);

            // NO_USER (0) has no row in the user table and neither has a deleted
            // account, so the access check must not be reached for either
            $handler_id = (int)$issue['handler_id'];
            $assignee_cant_view_issue = false;

            if ($issue['view_state'] == VS_PRIVATE && $handler_id != NO_USER && user_exists($handler_id)) {
                // First argument is the required threshold, not the handler's own level
                $t_view_threshold = config_get('view_bug_threshold', null, $handler_id, $issue['project_id']);
                $assignee_cant_view_issue = !access_has_bug_level($t_view_threshold, $issue_id, $handler_id);
            }

            include 'inc/confirm_modal_assignee.php';

            $allow_set_public_issue = plugin_config_get('allow_set_public_issue');
            $allow_set_public_issue['change_status_access'] = $this->ImaticChangeStatusAccess();

            $warning_public_issue_private_bugnote =  plugin_config_get('warning_public_issue_private_bugnote');
            $warning_public_issue_private_bugnote['message'] =  lang_get('plugin_public_issue_private_note'); // Lang message

            $warning_private_issue_public_bugnote = plugin_config_get('warning_private_issue_public_bugnote');
            $warning_private_issue_public_bugnote['message'] = lang_get('plugin_private_issue_public_note'); // Lang message

            $t_data = htmlspecialchars(json_encode([
                'issue_view_state' => $issue['view_state'],
                'set_issue_public_url' => plugin_page('set_issue_public') . '&issue_id=' . $issue_id,
                'allow_set_public_issue' => $allow_set_public_issue,
                'warning_public_issue_private_bugnote' => $warning_public_issue_private_bugnote,
                'warning_private_issue_public_bugnote' => $warning_private_issue_public_bugnote,
                'submit_confirm_shortcut' =>  plugin_config_get('submit_confirm_shortcut'),
                'assignee_cant_view_issue' => $assignee_cant_view_issue,
            ]));
            echo '<script id="imaticIssueType" data-data="' . $t_data . '" src="' . plugin_file('issue-checker.js') . '&v=' . $this->version . '"></script>
            <link rel="stylesheet" type="text/css" href="' . plugin_file('style.css') . '&v=' . $this->version . '" />';
        }
    }
}
