import { applyRules } from "../modules/rules";
import { state } from "../core/state";
import { safeJson, safeText, safeFetch } from "../core/api";

/* ======================
CONFIG (🔥 IMPORTANT)
====================== */
const FETCH_INTERVAL = 5000; // 5 detik minimum
const MAX_BATCH = 3; // max email per cycle
const REQUEST_DELAY = 300; // delay antar request

/* ======================
STATE CONTROL
====================== */
let running = false;
let lastRun = 0;
let queue = [];

/* ======================
ANTI DUPLICATE
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
HELPER
====================== */
function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

function getSenderDisplay(mail) {
  return mail?.from?.emailAddress?.name ||
         mail?.from?.emailAddress?.address ||
         "Unknown";
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
  const d = new Date(dateString);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

/* ======================
UI BUILDER
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
        <div class="mail-sender">${sender}</div>
        <div class="mail-subject">${subject}</div>
        <div class="mail-preview-text">${preview}</div>
        <div class="mail-time">${time}</div>
      </div>
    </div>
  `;
}

/* ======================
SSE LISTENER (SAFE)
====================== */
export async function initRealtime() {
  await waitToken();

  const evtSource = new EventSource(
    "/mail/stream?token_id=" + encodeURIComponent(state.tokenId)
  );

  evtSource.onmessage = () => {
    triggerFetch();
  };

  evtSource.onerror = (err) => {
    console.warn("SSE reconnect...", err);
  };
}

/* ======================
TRIGGER (ANTI SPAM)
====================== */
function triggerFetch() {
  const now = Date.now();

  if (running) return;
  if (now - lastRun < FETCH_INTERVAL) return;

  lastRun = now;

  fetchLatestToQueue();
}

/* ======================
FETCH → QUEUE
====================== */
async function fetchLatestToQueue() {
  try {
    const mails = await safeJson(
      `/mail/latest?token_id=${encodeURIComponent(state.tokenId)}`
    );

    if (!Array.isArray(mails)) return;

    for (const mail of mails) {
      if (!mail?.id) continue;
      if (state.processedIds.has(mail.id)) continue;

      state.processedIds.add(mail.id);
      queue.push(mail);
    }

    saveProcessed();

    processQueue();

  } catch (err) {
    console.error("Fetch latest error:", err);
  }
}

/* ======================
PROCESS QUEUE (🔥 CORE FIX)
====================== */
async function processQueue() {
  if (running) return;
  running = true;

  try {
    let count = 0;

    while (queue.length > 0 && count < MAX_BATCH) {
      const mail = queue.shift();
      await processSingleMail(mail);
      count++;

      await sleep(REQUEST_DELAY); // 🔥 anti spam API
    }

  } finally {
    running = false;

    // kalau masih ada queue → lanjut lagi (slow mode)
    if (queue.length > 0) {
      setTimeout(processQueue, 1000);
    }
  }
}

/* ======================
PROCESS SINGLE MAIL
====================== */
async function processSingleMail(mail) {
  let fullMail = mail;

  try {
    const needBody = (state.rules || []).some(
      r => r.conditionType === "bodyContains"
    );

    if (needBody) {
      try {
        const detail = await safeJson(
          `/mail/full/${mail.id}?token_id=${state.tokenId}`
        );
        fullMail = { ...mail, ...detail };
      } catch {}
    }

    const actions = applyRules(fullMail, state.rules);

    // 🔥 EXECUTE RULE (SAFE)
    if (actions.delete) {
      await safeFetch(`/mail/delete/${mail.id}?token_id=${state.tokenId}`);
      return;
    }

    if (actions.read) {
      await safeFetch(`/mail/read/${mail.id}?token_id=${state.tokenId}`);
    }

    if (actions.moveTo) {
      await safeFetch("/mail/move", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ids: [mail.id],
          folder: actions.moveTo,
          token_id: state.tokenId
        })
      });
      return;
    }

    // 🔥 UI UPDATE
    if (state.currentFolderId === "inbox") {
      try {
        const html = await safeText(
          `/mail/item/${mail.id}?token_id=${state.tokenId}`
        );

        state.mailListEl?.insertAdjacentHTML(
          "afterbegin",
          html || buildRealtimeMailItem(fullMail)
        );
      } catch {
        state.mailListEl?.insertAdjacentHTML(
          "afterbegin",
          buildRealtimeMailItem(fullMail)
        );
      }
    }

    showMailNotification(fullMail);

  } catch (err) {
    console.error("Process mail error:", err);
  }
}

/* ======================
NOTIFICATION
====================== */
function showMailNotification(mail) {
  let container = document.getElementById("mailNotifications");

  if (!container) {
    container = document.createElement("div");
    container.id = "mailNotifications";
    document.body.appendChild(container);
  }

  const sender = getSenderDisplay(mail);

  const el = document.createElement("div");
  el.className = "mail-notification";
  el.innerText = `${sender}: ${mail.subject}`;

  container.prepend(el);
  setTimeout(() => el.remove(), 4000);
}

/* ======================
WAIT TOKEN
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