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
🔥 PERSIST PROCESSED IDS
====================== */
state.processedIds = new Set(
  JSON.parse(localStorage.getItem("processedIds") || "[]")
);

function saveProcessed() {
  localStorage.setItem(
    "processedIds",
    JSON.stringify([...state.processedIds])
  );
}

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
HELPER: FORMAT MAIL HTML
====================== */
function buildRealtimeMailItem(mail) {
  const sender = escapeHtml(getSenderDisplay(mail));
  const subject = escapeHtml(mail.subject ?? "(No subject)");
  const preview = escapeHtml(mail.bodyPreview ?? "");
  const time = formatMailTime(mail.received);
  const avatar = sender.charAt(0).toUpperCase();

  return `
    <div draggable="true"
         mail-id="${mail.id}"
         class="mail-item ${mail.isRead ? "" : "unread"}"
         onclick="handleMailClick(event,this,'${mail.id}')">

      <input type="checkbox" class="mail-checkbox" onclick="event.stopPropagation()">

      <div class="mail-avatar">${avatar}</div>

      <div class="mail-content">
        <div class="mail-header">
          <div class="mail-sender">${sender}</div>

          <div class="mail-right">
            <span class="mail-icon"
                  onclick="event.stopPropagation(); toggleFlag('${mail.id}')">
              <i class="fa-regular fa-flag"></i>
            </span>

            <span class="mail-action"
                  onclick="event.stopPropagation(); markRead('${mail.id}')">
              <i class="fa-solid fa-envelope-open"></i>
            </span>

            <span class="mail-action delete"
                  onclick="event.stopPropagation(); deleteMail('${mail.id}')">
              <i class="fa-regular fa-trash-can"></i>
            </span>

            <span class="mail-time">${time}</span>
          </div>
        </div>

        <div class="mail-subject">${subject}</div>
        <div class="mail-preview-text">${preview}</div>
      </div>
    </div>
  `;
}

function escapeHtml(str = "") {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function formatMailTime(dateString) {
  if (!dateString) return "";

  const d = new Date(dateString);
  if (Number.isNaN(d.getTime())) return "";

  return d.toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit"
  });
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
  let container = document.getElementById("mailNotifications");

  if (!container) {
    container = document.createElement("div");
    container.id = "mailNotifications";
    document.body.appendChild(container);
  }

  const sender = getSenderDisplay(mail);
  const avatarLetter = sender?.[0]?.toUpperCase() || "U";

  const el = document.createElement("div");
  el.className = "mail-notification";

  el.innerHTML = `
    <div class="mail-notification-avatar">${avatarLetter}</div>
    <div class="mail-notification-content">
      <div class="mail-notification-from">${escapeHtml(sender)}</div>
      <div class="mail-notification-subject">${escapeHtml(mail.subject ?? "(No subject)")}</div>
      <div class="mail-notification-preview">${escapeHtml(mail.bodyPreview ?? "")}</div>
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
    } else {
      const item = document.querySelector(`[mail-id="${mail.id}"]`);
      if (item) window.openMail(mail.id, item);
    }
  };

  container.prepend(el);

  setTimeout(() => el.remove(), 5000);
}

/* ======================
SSE LISTENER
====================== */
export function initRealtime() {
  const evtSource = new EventSource("/mail/stream");

  evtSource.onmessage = function (event) {
    const data = event.data;

    console.log("SSE DATA:", data);

    if (data === lastEventId) return;
    lastEventId = data;

    if (!data || data === "0") return;

    const now = Date.now();
    if (now - lastRun < 1500) return;
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
    const tokenId = window.ACTIVE_TOKEN_ID;

const mails = await safeJson(`/mail/latest?token_id=${tokenId}`);
    console.log("DELTA RESULT:", mails);

    if (!Array.isArray(mails) || mails.length === 0) {
      return;
    }

    const newMails = [];

    for (const mail of mails) {
      if (!mail?.id) continue;

      if (state.processedIds.has(mail.id)) continue;

      state.processedIds.add(mail.id);
      saveProcessed();

      newMails.push(mail);
    }

    if (newMails.length === 0) {
      return;
    }

    newMails.sort((a, b) => {
      return new Date(b.received || 0) - new Date(a.received || 0);
    });

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

      if (needFullData) {
        try {
          const detail = await safeJson(`/mail/full/${mail.id}`);

          const sender =
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
            bodyPreview: detail?.bodyPreview ?? mail.bodyPreview,
            fullBody: stripHtml(
              detail?.body?.content || detail?.bodyPreview || ""
            )
          };
        } catch (e) {
          console.error("Failed load full data:", e);
        }
      }

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

      updateFolderUnread(mail.parentFolderId, 1);

      if (state.currentFolder?.toLowerCase().includes("inbox")) {
        try {
          const html = await safeText(`/mail/item/${mail.id}`);

          if (html && html.trim()) {
            state.mailListEl?.insertAdjacentHTML("afterbegin", html);
          } else {
            state.mailListEl?.insertAdjacentHTML(
              "afterbegin",
              buildRealtimeMailItem(fullMail)
            );
          }
        } catch (e) {
          console.error("Render mail failed:", e);

          state.mailListEl?.insertAdjacentHTML(
            "afterbegin",
            buildRealtimeMailItem(fullMail)
          );
        }
      }

      showMailNotification(fullMail);
    }

    state.lastCheckTime = Date.now();
  } catch (err) {
    console.error("Delta error:", err);
  } finally {
    running = false;
  }
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