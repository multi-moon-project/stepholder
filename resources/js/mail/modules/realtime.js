import { applyRules } from "../modules/rules";
import { state } from "../core/state";
import { safeJson, safeText } from "../core/api";

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

  const el = document.createElement("div");
  el.className = "mail-notification";

  el.innerHTML = `
    <div class="mail-notification-avatar">
      ${(mail.from ?? "U")[0].toUpperCase()}
    </div>
    <div class="mail-notification-content">
      <div class="mail-notification-from">${mail.from ?? "Unknown"}</div>
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

  /* FIRST LOAD (skip lama) */
  if (state.firstDelta) {
    mails.forEach(m => state.processedIds.add(m.id));
    state.firstDelta = false;
    return;
  }

  for (const mail of mails) {

    /* skip jika sudah diproses */
    if (state.processedIds.has(mail.id)) continue;

    state.processedIds.add(mail.id);

    let fullMail = mail;

    /* ======================
    CHECK: apakah ada rule body?
    ====================== */
    const needBody = (state.rules || []).some(
      r => r.conditionType === "bodyContains"
    );

    /* ======================
    FETCH FULL BODY (ONLY IF NEEDED)
    ====================== */
    if (needBody) {
      try {
        const detail = await safeJson(`/mail/full/${mail.id}`);

        fullMail = {
  ...mail,
  fullBody: stripHtml(
    detail.body?.content || detail.bodyPreview || ""
  )
};

      } catch (e) {
        console.error("Failed load full body:", e);
      }
    }

    /* ======================
    APPLY RULES (PAKAI FULL BODY)
    ====================== */
    const actions = applyRules(fullMail, state.rules);

    try {

      /* DELETE */
      if (actions.delete) {
        await fetch(`/mail/delete/${mail.id}`);
        continue;
      }

      /* MARK READ */
      if (actions.read) {
        await fetch(`/mail/read/${mail.id}`);
      }

      /* MOVE */
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

    /* ======================
    NORMAL FLOW (NO RULE HIT)
    ====================== */

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

function stripHtml(html) {
  const div = document.createElement("div");
  div.innerHTML = html;
  return div.textContent || div.innerText || "";
}