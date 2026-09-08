## Imatic Issue type Checker

## Mantis plugin

[![N|Solid](https://www.imatic.cz/img/logo.png)](https://www.imatic.cz/)

## Features

Warns while adding notes, depends on view state

- Added button send public note

- If the issue is private, a confirmation window will pop up asking if you want to set the issue to public, if yes, the issue will be set to public and a public note will be inserted, otherwise only a public note  will be added, this can by turn off in plugin config `allow_set_public_issue`

- If issue is public and you will want to add private bugnote, confirmation window will pop up asking, if you want to send private bugnote

- Showing message view state of issue on the top of bugnote form, can by turn off on plugin config ('show_issue_status' => true)

- Private issues cannot be assigned to users who cannot view them (their access level is below `private_bug_threshold`). Such users stay listed in the "Assigned To" select of a private issue but are greyed out, cannot be selected and carry a short label (report page, update page, change status page, quick assign on the issue detail, group action "Assign") and the assignment is refused on the server side as well (covers the REST API for new issues). The reporter of the issue is never restricted, Mantis always lets the reporter view their own issue. Can be turned off in plugin config (`restrict_private_issue_handlers` => false).



## Requirements

- [mantis bug tracker](https://www.mantisbt.org/)
- [imatic mantis issue type checker ](https://github.com/Imatic-IT/imatic-mantis-issue-type-checker)

## Installation

- Copy all files from [imatic mantis issue type checker ](https://github.com/Imatic-IT/imatic-mantis-issue-type-checker) into plugins/ImaticIssueTypeChecker.

- In mantis plugins, install [imatic mantis issue type checker ](https://github.com/Imatic-IT/imatic-mantis-issue-type-checker)


## Config

- `show_issue_status` (boolean): Whether to show the issue status.

- `allow_set_public_issue` (array): Options for allowing to set an issue as public by request button.

- `allow` (boolean): Whether to allow setting an issue as public.

- `warning_public_issue_private_bugnote` (array): Options for warning when adding a private bugnote to a public issue.

- `allow` (boolean): Whether to allow show confirm adding a private bugnote to a public issue.

- `message` (string): The warning message to display.

- `warning_private_issue_public_bugnote` (array): Options for warning when adding a public bugnote to a private issue.

- `allow` (boolean): Whether to allow allow show confirm adding a public bugnote to a private issue.

- `message` (string): The warning message to display.

- `submit_confirm_shortcut => send_bugnote` (array): Shortcut for send bugnote confirm modal (send as default button).

- `restrict_private_issue_handlers` (boolean): Grey out (disable) users who cannot view private issues in the assignee select of a private issue and refuse such an assignment when saving.

### Config code

```php
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
            'restrict_private_issue_handlers' => true,
        ];
    }