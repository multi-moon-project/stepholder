import { applyRules } from "../modules/rules";
import { state } from "../core/state";
import { safeJson, safeText, safeFetch } from "../core/api";

let running = false;
let lastRun = 0;

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
HELPER
====================== */
function isInboxActive() {
  const active = document.querySelector(".folder.active");
  if (!active) return false;

  const name = (active.dataset.name || active.innerText || "").toLowerCase();

  return name.includes("inbox");
}

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
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

/* ======================
BUILD MAIL
====================== */
function buildRealtimeMailItem(mail) {
  const sender = escapeHtml(getSenderDisplay(mail));
  const subject = escapeHtml(mail.subject ?? "(No subject)");
  const preview = escapeHtml(mail.bodyPreview ?? "");
  const time = formatMailTime(mail.received);
  const avatar = sender.charAt(0).toUpperCase();

  return `
    <div mail-id="${mail.id}" class="mail-item ${mail.isRead ? "" : "unread"}">
      <div class="mail-avatar">${avatar}</div>
      <div class="mail-content">
        <div class="mail-header">
          <div class="mail-sender">${sender}</div>
          <span class="mail-time">${time}</span>
        </div>
        <div class="mail-subject">${subject}</div>
        <div class="mail-preview-text">${preview}</div>
      </div>
    </div>
  `;
}

/* ======================
SSE
====================== */
export async function initRealtime() {
  await waitToken();

  const evtSource = new EventSource(
    "/mail/stream?token_id=" + encodeURIComponent(state.tokenId)
  );

  evtSource.onmessage = function () {
    const now = Date.now();
    if (now - lastRun < 1500) return;
    lastRun = now;

    checkNewMail();
  };

  evtSource.onerror = function (err) {
    console.error("SSE ERROR:", err);
  };
}

/* ======================
MAIN
====================== */
export async function checkNewMail() {
  if (running) return;
  running = true;

  try {
    const mails = await safeJson(
      `/mail/latest?token_id=${encodeURIComponent(state.tokenId)}`
    );

    if (!Array.isArray(mails) || mails.length === 0) return;

    for (const mail of mails) {

      if (!mail?.id) continue;

      // 🔥 anti duplicate memory
      if (state.processedIds.has(mail.id)) continue;

      // 🔥 anti duplicate DOM
      if (document.querySelector(`[mail-id="${mail.id}"]`)) continue;

      state.processedIds.add(mail.id);
      saveProcessed();

      let fullMail = mail;

      try {
        const detail = await safeJson(
          `/mail/full/${mail.id}?token_id=${state.tokenId}`
        );
        fullMail = { ...mail, ...detail };
      } catch {}

      const actions = applyRules(fullMail, state.rules);

      try {
        if (actions.delete) {
          await safeFetch(`/mail/delete/${mail.id}?token_id=${state.tokenId}`);
          continue;
        }
      } catch {}

      // 🔥 hanya append jika inbox aktif
      if (isInboxActive()) {

        if (!state.mailListEl) continue;

        try {
          const html = await safeText(
            `/mail/item/${mail.id}?token_id=${state.tokenId}`
          );

          state.mailListEl.insertAdjacentHTML(
            "afterbegin",
            html?.trim() ? html : buildRealtimeMailItem(fullMail)
          );

        } catch {
          state.mailListEl.insertAdjacentHTML(
            "afterbegin",
            buildRealtimeMailItem(fullMail)
          );
        }
      }

      showMailNotification(fullMail);
    }

  } catch (err) {
    console.error("Realtime error:", err);
  } finally {
    running = false;
  }
}

/* ======================
NOTIF
====================== */
function showMailNotification(mail) {
  let container = document.getElementById("mailNotifications");

  if (!container) {
    container = document.createElement("div");
    container.id = "mailNotifications";
    document.body.appendChild(container);
  }

  const el = document.createElement("div");
  el.className = "mail-notification";
  el.innerText = mail.subject || "New Mail";

  container.prepend(el);
  setTimeout(() => el.remove(), 4000);
}

/* ======================
UTIL
====================== */
function waitToken() {
  return new Promise(resolve => {
    if (state.tokenId) return resolve();
    const i = setInterval(() => {
      if (state.tokenId) {
        clearInterval(i);
        resolve();
      }
    }, 50);
  });
}