<?php

class ImaticIssueTypeCheckerPlugin extends MantisPlugin
{
    public function register(): void
    {
        $this->name = 'Imatic issue checker';
        $this->description = 'Warning when private issue has public note, button to send public note, private issues cannot be assigned to users who cannot view them';
        $this->version = '0.1.1';
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
            ],
            // Grey out users who cannot view private issues in the assignee select of a
            // private issue and refuse such an assignment on the server side
            'restrict_private_issue_handlers' => true,
        ];
    }

    public function errors(): array
    {
        return [
            'HandlerCannotViewPrivateIssue' => lang_get('plugin_error_handler_cannot_view_private_issue'),
        ];
    }

    public function hooks(): array
    {
        return [
            'EVENT_LAYOUT_BODY_END' => 'layout_body_end_hook',
            'EVENT_BUGNOTE_ADD_FORM' => 'event_bugnote_add_form',
            'EVENT_REPORT_BUG_DATA' => 'event_report_bug_data',
            'EVENT_UPDATE_BUG_DATA' => 'event_update_bug_data',
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
        $this->printHandlerRestriction();

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

    /**
     * New issue: refuse a private issue assigned to a user who could not view it.
     * Covers the report form and the REST API.
     */
    public function event_report_bug_data($p_event, BugData $p_bug): BugData
    {
        $this->ensureHandlerCanViewIssue($p_bug);

        return $p_bug;
    }

    /**
     * Issue update: refuse when the handler or the visibility changes into a
     * combination the handler cannot view. Covers the update page, the change
     * status page and the quick "Assign to" form on the issue detail.
     *
     * Only the changed combination is checked so an already inconsistent issue
     * (assigned before this rule existed) stays editable.
     */
    public function event_update_bug_data($p_event, BugData $p_updated_bug, BugData $p_existing_bug): BugData
    {
        if ((int)$p_updated_bug->handler_id !== (int)$p_existing_bug->handler_id
            || (int)$p_updated_bug->view_state !== (int)$p_existing_bug->view_state
            || (int)$p_updated_bug->project_id !== (int)$p_existing_bug->project_id
        ) {
            $this->ensureHandlerCanViewIssue($p_updated_bug);
        }

        return $p_updated_bug;
    }

    private function ensureHandlerCanViewIssue(BugData $p_bug): void
    {
        if (!plugin_config_get('restrict_private_issue_handlers')) {
            return;
        }

        $handler_id = (int)$p_bug->handler_id;

        if ((int)$p_bug->view_state !== VS_PRIVATE || $handler_id === NO_USER) {
            return;
        }

        // Mantis always lets the reporter view their own issue
        if ($handler_id === (int)$p_bug->reporter_id) {
            return;
        }

        if (!$this->userCanViewPrivateIssues($handler_id, (int)$p_bug->project_id)) {
            plugin_error('HandlerCannotViewPrivateIssue');
        }
    }

    private function userCanViewPrivateIssues(int $user_id, int $project_id): bool
    {
        $threshold = config_get('private_bug_threshold', null, $user_id, $project_id);

        return access_has_project_level($threshold, $project_id, $user_id);
    }

    /**
     * Users who may handle issues in the project but cannot view private ones.
     * The reporter (if known) is never restricted, see ensureHandlerCanViewIssue().
     *
     * @return int[]
     */
    private function getRestrictedHandlerIds(int $project_id, int $reporter_id = NO_USER): array
    {
        $handle_threshold = config_get('handle_bug_threshold', null, null, $project_id);
        $restricted = [];

        foreach (project_get_all_user_rows($project_id, $handle_threshold) as $user_id => $row) {
            $user_id = (int)$user_id;

            if ($user_id === $reporter_id || $this->userCanViewPrivateIssues($user_id, $project_id)) {
                continue;
            }

            $restricted[] = $user_id;
        }

        return $restricted;
    }

    /**
     * Emit the script that greys out restricted users in the assignee select on
     * pages that have one. The script follows the view_state control on the page,
     * so the options get disabled as soon as the user marks the issue private.
     */
    private function printHandlerRestriction(): void
    {
        if (!plugin_config_get('restrict_private_issue_handlers') || !auth_is_user_authenticated()) {
            return;
        }

        $context = $this->getHandlerRestrictionContext();

        if ($context === null || empty($context['restricted_ids'])) {
            return;
        }

        $context['restricted_label'] = lang_get('plugin_handler_cannot_view_private_issue_label');
        $t_data = htmlspecialchars(json_encode($context));

        echo '<script id="imaticHandlerRestriction" data-data="' . $t_data . '" src="' . plugin_file('handler-restriction.js') . '&v=' . $this->version . '"></script>';
    }

    /**
     * @return array|null ['is_private' => bool, 'restricted_ids' => int[]], null when the page has no assignee select
     */
    private function getHandlerRestrictionContext(): ?array
    {
        if (is_page_name('bug_report_page.php')) {
            return $this->getReportPageContext();
        }

        if (is_page_name('bug_actiongroup_page.php')) {
            return $this->getActionGroupPageContext();
        }

        $issue_id = 0;

        if (is_page_name('bug_update_page.php')) {
            $issue_id = gpc_get_int('bug_id', 0);
        } elseif (is_page_name('bug_view_page.php') || is_page_name('bug_view_advanced_page.php')
            || is_page_name('view.php') || is_page_name('bug_change_status_page.php')
        ) {
            $issue_id = gpc_get_int('id', 0);
        }

        if ($issue_id <= 0 || !bug_exists($issue_id)) {
            return null;
        }

        $issue = bug_get_row($issue_id);

        return [
            'is_private' => (int)$issue['view_state'] === VS_PRIVATE,
            'restricted_ids' => $this->getRestrictedHandlerIds((int)$issue['project_id'], (int)$issue['reporter_id']),
        ];
    }

    private function getReportPageContext(): ?array
    {
        $copy_from_id = gpc_get_int('m_id', 0);

        if ($copy_from_id > 0 && bug_exists($copy_from_id)) {
            // A clone may be redirected into another project (clone_issue_into_another_project.patch)
            $project_id = gpc_get_int('project_id', (int)bug_get_field($copy_from_id, 'project_id'));
        } else {
            // Same resolution as bug_report_page.php
            $project_id = gpc_get_int('project_id', helper_get_current_project());
            $default_project = user_pref_get_pref(auth_get_current_user_id(), 'default_project');

            if (ALL_PROJECTS == $project_id && ALL_PROJECTS != $default_project) {
                $project_id = (int)$default_project;
            }
        }

        if (ALL_PROJECTS == $project_id) {
            // The page asks the user to pick a project first, no form is rendered
            return null;
        }

        return [
            'is_private' => (int)config_get('default_bug_view_status', null, null, $project_id) === VS_PRIVATE,
            'restricted_ids' => $this->getRestrictedHandlerIds($project_id, auth_get_current_user_id()),
        ];
    }

    /**
     * Group action "Assign": the select serves several issues at once, so a user
     * is hidden when they could not view any of the selected private issues.
     */
    private function getActionGroupPageContext(): ?array
    {
        if (gpc_get_string('action', '') !== 'ASSIGN') {
            return null;
        }

        $restricted = [];
        $is_private = false;

        foreach (gpc_get_int_array('bug_arr', []) as $issue_id) {
            if (!bug_exists($issue_id)) {
                continue;
            }

            $issue = bug_get_row($issue_id);

            if ((int)$issue['view_state'] !== VS_PRIVATE) {
                continue;
            }

            $is_private = true;
            $restricted += array_flip($this->getRestrictedHandlerIds((int)$issue['project_id'], (int)$issue['reporter_id']));
        }

        return [
            'is_private' => $is_private,
            'restricted_ids' => array_keys($restricted),
        ];
    }
}
