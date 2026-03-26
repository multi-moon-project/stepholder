import { applyRules } from "../modules/rules";
import { state } from "../core/state";
import { safeJson, safeText } from "../core/api";

/* ======================
HELPER: NORMALIZE SENDER
====================== */
function getSenderDisplay(mail) {
  if (!mail?.from) return "Unknown";

  // Microsoft Graph object
  if (typeof mail.from === "object") {
    return (
      mail.from.emailAddress?.name ||
      mail.from.emailAddress?.address ||
      "Unknown"
    );
  }

  // string fallback
  return mail.from;
}

/* ======================
UPDATE UNREAD COUNTER
====================== */
export function updateFolderUnread(folderId, delta) {
  const folder = state.folderMap.get(folderId);
  if (!folder) return;

  let counter = folder.querySelector(".folder-count");

  if (!counter) {
    counter = document.createElement("span");
    counter.className = "folder-count";
    counter.innerText = "0";
    folder.appendChild(counter);
  }

  let count = parseInt(counter.innerText || "0");
  count += delta;
  if (count < 0) count = 0;

  counter.innerText = count;
}

/* ======================
NOTIFICATION
====================== */
export function showMailNotification(mail) {
  const container = document.getElementById("mailNotifications");
  if (!container) return;

  const sender = getSenderDisplay(mail);

  const avatarLetter = sender?.[0]?.toUpperCase() || "U";

  const el = document.createElement("div");
  el.className = "mail-notification";

  el.innerHTML = `
    <div class="mail-notification-avatar">
      ${avatarLetter}
    </div>
    <div class="mail-notification-content">
      <div class="mail-notification-from">${sender}</div>
      <div class="mail-notification-subject">${mail.subject ?? "(No subject)"}</div>
      <div class="mail-notification-preview">${mail.bodyPreview ?? ""}</div>
    </div>
    <div class="mail-notification-close">✕</div>
  `;

  el.querySelector(".mail-notification-close").onclick = (e) => {
    e.stopPropagation();
    el.remove();
  };

  el.onclick = async () => {
    if (!state.currentFolder.toLowerCase().includes("inbox")) {
      await window.loadFolder(state.inboxFolderId, "Inbox");

      setTimeout(() => {
        const item = document.querySelector(`[mail-id="${mail.id}"]`);
        if (item) window.openMail(mail.id, item);
      }, 400);
    }
  };

  container.prepend(el);

  setTimeout(() => el.remove(), 5000);
}

/* ======================
MAIN DELTA CHECK
====================== */
export async function checkNewMail() {
  const mails = await safeJson("/mail/delta");
  if (!Array.isArray(mails)) return;

  if (state.firstDelta) {
    mails.forEach(m => state.processedIds.add(m.id));
    state.firstDelta = false;
    return;
  }

  for (const mail of mails) {

    if (state.processedIds.has(mail.id)) continue;
    state.processedIds.add(mail.id);

    let fullMail = mail;

    const needBody = (state.rules || []).some(
      r => r.conditionType === "bodyContains"
    );

    const needFullData =
      needBody ||
      !mail.from ||
      typeof mail.from !== "object" ||
      !mail.from.emailAddress ||
      !mail.from.emailAddress.address;

    /* ======================
    FETCH FULL DATA
    ====================== */
    if (needFullData) {
      try {
        const detail = await safeJson(`/mail/full/${mail.id}`);

        let sender = null;

        // PRIORITY CHAIN
        if (detail?.from?.emailAddress?.address) {
          sender = detail.from;
        }
        else if (detail?.sender?.emailAddress?.address) {
          sender = detail.sender;
        }
        else if (detail?.replyTo?.[0]?.emailAddress?.address) {
          sender = detail.replyTo[0];
        }
        else {
          // fallback
          sender = {
            emailAddress: {
              name: getSenderDisplay(mail),
              address: ""
            }
          };
        }

        fullMail = {
          ...mail,
          from: sender,
          subject: detail?.subject ?? mail.subject,
          fullBody: stripHtml(
            detail?.body?.content || detail?.bodyPreview || ""
          )
        };

        console.group("REALTIME FIX");
        console.log("ORIGINAL FROM:", mail.from);
        console.log("DETAIL FROM:", detail.from);
        console.log("DETAIL SENDER:", detail.sender);
        console.log("FINAL NORMALIZED FROM:", fullMail.from);
        console.groupEnd();

      } catch (e) {
        console.error("Failed load full data:", e);
      }
    }

    /* ======================
    APPLY RULES
    ====================== */
    const actions = applyRules(fullMail, state.rules);

    try {

      if (actions.delete) {
        await fetch(`/mail/delete/${mail.id}`);
        continue;
      }

      if (actions.read) {
        await fetch(`/mail/read/${mail.id}`);
      }

      if (actions.moveTo) {
        await fetch(`/mail/move`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document
              .querySelector('meta[name="csrf-token"]')
              .getAttribute("content")
          },
          body: JSON.stringify({
            ids: [mail.id],
            folder: actions.moveTo
          })
        });

        continue;
      }

    } catch (e) {
      console.error("Rule action failed:", e);
    }

    updateFolderUnread(mail.parentFolderId, 1);

    if (state.currentFolder.toLowerCase().includes("inbox")) {
      try {
        const html = await safeText(`/mail/item/${mail.id}`);
        state.mailListEl.insertAdjacentHTML("afterbegin", html);
      } catch (e) {
        console.error("Render mail failed:", e);
      }
    }

    showMailNotification(mail);
  }
}

/* ======================
START / STOP DELTA
====================== */
export function startDelta() {
  if (state.deltaTimer) return;
  state.deltaTimer = setInterval(checkNewMail, 7000);
}

export function stopDelta() {
  clearInterval(state.deltaTimer);
  state.deltaTimer = null;
}

/* ======================
AUTO MOUNT
====================== */
export function mountRealtime() {
  document.addEventListener("visibilitychange", () => {
    document.hidden ? stopDelta() : startDelta();
  });

  startDelta();
}

/* ======================
STRIP HTML
====================== */
function stripHtml(html) {
  const div = document.createElement("div");
  div.innerHTML = html;
  return div.textContent || div.innerText || "";
}