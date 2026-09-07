/**
 * Hide users who cannot view private issues from the assignee select while the
 * issue is (or is being marked) private. Rendered by ImaticIssueTypeCheckerPlugin::printHandlerRestriction().
 *
 * The server side (EVENT_REPORT_BUG_DATA / EVENT_UPDATE_BUG_DATA) refuses such an
 * assignment anyway, this only keeps the choice out of the UI.
 */
(function () {
    'use strict';

    var el = document.getElementById('imaticHandlerRestriction');
    if (!el) {
        return;
    }

    var settings = JSON.parse(el.dataset.data);
    var VS_PRIVATE = '50';

    var restricted = {};
    (settings.restricted_ids || []).forEach(function (id) {
        restricted[String(id)] = true;
    });

    var selects = document.querySelectorAll('select[name="handler_id"], select[name="assign"]');
    var viewStateControls = document.querySelectorAll('[name="view_state"]');

    if (!selects.length) {
        return;
    }

    function isPrivate() {
        var checked = document.querySelector('input[name="view_state"]:checked');
        if (checked) {
            return checked.value === VS_PRIVATE;
        }

        var select = document.querySelector('select[name="view_state"]');
        if (select) {
            return select.value === VS_PRIVATE;
        }

        return settings.is_private === true;
    }

    function firstSelectable(select) {
        for (var i = 0; i < select.options.length; i++) {
            if (!select.options[i].disabled) {
                return i;
            }
        }
        return -1;
    }

    function apply() {
        var hide = isPrivate();

        Array.prototype.forEach.call(selects, function (select) {
            var resetSelection = false;

            Array.prototype.forEach.call(select.options, function (option) {
                if (!restricted[option.value]) {
                    return;
                }

                // The handler stored on the issue stays visible: the select must show
                // the real state and the "assignee cannot view" warning already covers it.
                if (option.defaultSelected) {
                    return;
                }

                option.disabled = hide;
                option.hidden = hide;

                if (hide && option.selected) {
                    resetSelection = true;
                }
            });

            if (resetSelection) {
                select.selectedIndex = firstSelectable(select);
            }
        });
    }

    Array.prototype.forEach.call(viewStateControls, function (control) {
        control.addEventListener('change', apply);
    });

    apply();
})();
