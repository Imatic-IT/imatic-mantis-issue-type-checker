"use strict";
window.onload = function () {
  function getSettings() {
    const el = document.querySelector("#imaticIssueType");
    if (el == null) {
      return;
    }

    return JSON.parse(el.dataset.data);
  }

  let settings = getSettings();
  settings.issue_view_state = parseInt(settings.issue_view_state);
  const bugForm = document.querySelector("#bugnoteadd");
  const addButton = bugForm.querySelector('input[type="submit"]');
  const addPublicButton = document.querySelector("#add_public_bugnote");
  const checkBoxViewStatus = document.querySelector("#bugnote_add_view_status");
  const butnoteTextArea = document.querySelector("#bugnote_text");
  const loader = document.getElementById("loader");
  const confirmBugnote = document.getElementById("confirm_bugnote");
  const VIEW_STATE_PRIVATE = 50;
  const VIEW_STATE_PUBLIC = 10;

  setPublicIssueButtonAccess()
  sendBugnoteModal();
  setIssuePublic()

  addPublicButton.addEventListener("click", function (e) {
    checkBoxViewStatus.checked = false;
    butnoteTextArea.classList.remove("bugnote-private");

    switch (settings.issue_view_state) {
      case VIEW_STATE_PRIVATE:
        e.preventDefault();
        bugnoteConfirm(
          settings.warning_private_issue_public_bugnote.message
        );
        break;
      default:
        showLoader();
        break;
    }
  });

  // Left button, default mantis add bugnote button
  addButton.addEventListener("click", function (e) {
    switch (settings.issue_view_state) {
      case VIEW_STATE_PUBLIC:
        if (checkBoxViewStatus.checked) {
          if (settings.warning_public_issue_private_bugnote.allow) {
            e.preventDefault();
            bugnoteConfirm(

              settings.warning_public_issue_private_bugnote.message
            );
          } else {
            showLoader();
          }
        } else {
          showLoader();
        }
        break;
      case VIEW_STATE_PRIVATE:
        if (!checkBoxViewStatus.checked) {
          if (settings.warning_private_issue_public_bugnote.allow) {
            e.preventDefault();
            bugnoteConfirm(

              settings.warning_private_issue_public_bugnote.message
            );
          } else {
            showLoader();
          }
        } else {
          showLoader();
        }
        break;
      default:
        showLoader();
        break;
    }
  });

  addButton.insertAdjacentElement("afterend", addPublicButton);

  function setIssuePublic() {

    const publicIssueSendBugnote = document.getElementById(
      "public_issue_send_bugnote"
    );

    if (publicIssueSendBugnote) {
      publicIssueSendBugnote.addEventListener("click", function (e) {
        $.ajax({
          url: getUrl(),
          type: "POST",
          dataType: "json",
          success: function (data) {
            if (data.affected_row) {
              loader.style.display = "none";
              AddNoteClick();
            }
          },
          error: function (xhr, status, error) {
            console.error(error);
          },
        });
        showLoader();
      });
    }
  }

  function showLoader() {
    loader.style.display = "block";
  }

  function getUrl() {
    const url = settings.set_issue_public_url;
    return url;
  }

  function bugnoteConfirm(msg) {
    const publicIssueSendBugnote = document.getElementById(
      "public_issue_send_bugnote"
    );

    closeModal()
    showModal();

    const modalBody = document.getElementById("bugnote_modal_confirm_message");
    modalBody.textContent = msg;
  }

  // Remove set public button if user do not have permision to  change status or it is turn off
  function setPublicIssueButtonAccess() {

    const publicIssueSendBugnote = document.getElementById(
      "public_issue_send_bugnote"
    );

    if (!publicIssueSendBugnote)return ;

    if (!settings.allow_set_public_issue.allow) {
      publicIssueSendBugnote.remove();
    }
    if (!settings.allow_set_public_issue.change_status_access) {
      publicIssueSendBugnote.remove();
    }
    if (settings.issue_view_state == 10) {
      publicIssueSendBugnote.remove();
    }
  }

  function showModal() {
    confirmBugnote.classList.add("show");
    confirmBugnote.classList.remove("fade");
    document.addEventListener("keydown", confirmBugnoteKeyDownHandler);
  }

    function closeModal() {
        const closeButton = document.getElementById("close_bugnote_congirm_modal");
        closeButton.addEventListener("click", function () {
            hideModal();
        });
    }

    function hideModal() {
        confirmBugnote.classList.remove("show");
        confirmBugnote.classList.add("fade");
        document.removeEventListener("keydown", confirmBugnoteKeyDownHandler);
    }

  function confirmBugnoteKeyDownHandler(e) {
    let key = e.code;

      settings.submit_confirm_shortcut.send_bugnote.forEach(function(bugnote){

        if (key === bugnote) {
          e.preventDefault();
          const sendBugnote = document.getElementById("send_bugnote");
          sendBugnote.click()
        }
      });
  }

  //Listener on modal send issue
  function sendBugnoteModal(){
    const sendBugnote = document.getElementById("send_bugnote");

    sendBugnote.addEventListener("click", function (e) {
      AddNoteClick()
      showLoader();
    });
  }

  function AddNoteClick() {
    addButton.dispatchEvent(new MouseEvent("click"));
  }


  if(settings.assignee_cant_view_issue === true){
    // console.log(settings.assignee_cant_view_issue)
      // const modal = $('#assigneeWarningModal');
      // console.log(modal)
      //   modal.modal('show');
      // $('#assignee_warning').show();

      console.log(settings.assignee_cant_view_issue)

      const warning = document.getElementById('assignee_warning');
      const closeBtn = document.getElementById('assignee_warning_close');
      const confirmBtn = document.getElementById('assignee_warning_confirm');


      if (warning && settings.assignee_cant_view_issue === true) {
          console.log('aaa')
          warning.style.display = 'block';
      }

      if(closeBtn){
          closeBtn.addEventListener('click', function() {
              warning.style.display = 'none';
          });
      }

        if(confirmBtn){
            confirmBtn.addEventListener('click', function() {
                warning.style.display = 'none';
            });
        }
  }

};
