import { applyRules } from "../modules/rules";
import { state } from "../core/state";
import { safeJson, safeText } from "../core/api";

/* ======================
ANTI DOUBLE REQUEST
====================== */
let running = false;

/* ======================
ANTI SPAM TRIGGER (SSE)
====================== */
let lastEventId = null;
let lastRun = 0;

/* ======================
HELPER: NORMALIZE SENDER
====================== */
function getSenderDisplay(mail) {
  if (!mail?.from) return "Unknown";

  if (typeof mail.from === "object") {
    return (
      mail.from.emailAddress?.name ||
      mail.from.emailAddress?.address ||
      "Unknown"
    );
  }

  return mail.from;
}

/* ======================
UPDATE UNREAD COUNTER
====================== */
export function updateFolderUnread(folderId, delta) {
  if (!folderId) return;

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
    <div class="mail-notification-avatar">${avatarLetter}</div>
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
    if (!state.currentFolder?.toLowerCase().includes("inbox")) {
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
SSE LISTENER (FIXED 🔥)
====================== */
export function initRealtime() {

  const evtSource = new EventSource("/mail/stream");

  evtSource.onmessage = function (event) {

    const data = event.data;

    console.log("SSE DATA:", data);

    /* ======================
    IGNORE DUPLICATE EVENT
    ====================== */
    if (data === lastEventId) return;

    lastEventId = data;

    /* ======================
    IGNORE EMPTY / ZERO
    ====================== */
    if (!data || data === "0") return;

    /* ======================
    THROTTLE (ANTI SPAM)
    ====================== */
    const now = Date.now();
    if (now - lastRun < 2000) return;
    lastRun = now;

    console.log("🔥 REALTIME TRIGGER", data);

    checkNewMail();
  };

  evtSource.onerror = function (err) {
    console.error("SSE ERROR:", err);
  };
}

/* ======================
MAIN DELTA CHECK
====================== */
export async function checkNewMail() {

  if (running) return;
  running = true;

  try {

    const mails = await safeJson("/mail/delta");
    console.log("DELTA RESULT:", mails);

    if (!Array.isArray(mails) || mails.length === 0) {
      running = false;
      return;
    }

    const now = Date.now();
    const newMails = [];

    for (const mail of mails) {

      if (!mail?.id) continue;

      /* ======================
      FILTER 1: DUPLICATE GUARD
      ====================== */
      if (state.processedIds.has(mail.id)) continue;

      /* ======================
      FILTER 2: ONLY NEW EMAIL
      ====================== */
      if (mail.received) {
        const ts = new Date(mail.received).getTime();

        if (ts < now - 120000) {
          state.processedIds.add(mail.id);
          continue;
        }
      }

      state.processedIds.add(mail.id);

      newMails.push(mail);
    }

    if (newMails.length === 0) {
      running = false;
      return;
    }

    /* ======================
    SORT NEWEST FIRST
    ====================== */
    newMails.sort((a, b) => {
      return new Date(b.received) - new Date(a.received);
    });

    /* ======================
    PROCESS MAILS
    ====================== */
    for (const mail of newMails) {

      let fullMail = mail;

      const needBody = (state.rules || []).some(
        r => r.conditionType === "bodyContains"
      );

      const needFullData =
        needBody ||
        !mail.from ||
        typeof mail.from !== "object" ||
        !mail.from.emailAddress?.address;

      /* ======================
      FETCH DETAIL (IF NEEDED)
      ====================== */
      if (needFullData) {
        try {
          const detail = await safeJson(`/mail/full/${mail.id}`);

          let sender =
            detail?.from ||
            detail?.sender ||
            detail?.replyTo?.[0] || {
              emailAddress: {
                name: getSenderDisplay(mail),
                address: ""
              }
            };

          fullMail = {
            ...mail,
            from: sender,
            subject: detail?.subject ?? mail.subject,
            fullBody: stripHtml(
              detail?.body?.content || detail?.bodyPreview || ""
            )
          };

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
                ?.getAttribute("content")
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
      UPDATE UI
      ====================== */
      updateFolderUnread(mail.parentFolderId, 1);

      if (state.currentFolder?.toLowerCase().includes("inbox")) {
        try {
          const html = await safeText(`/mail/item/${mail.id}`);
          state.mailListEl?.insertAdjacentHTML("afterbegin", html);
        } catch (e) {
          console.error("Render mail failed:", e);
        }
      }

      showMailNotification(mail);
    }

    /* ======================
    UPDATE CHECKPOINT
    ====================== */
    state.lastCheckTime = now;

  } catch (err) {
    console.error("Delta error:", err);
  }

  running = false;
}

/* ======================
STRIP HTML
====================== */
function stripHtml(html) {
  if (!html) return "";

  const div = document.createElement("div");
  div.innerHTML = html;

  return div.textContent || div.innerText || "";
}