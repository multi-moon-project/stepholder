import { state } from "../core/state";
import { qs } from "../core/dom";
import { safeFetch, safeText } from "../core/api";
import { setLimitedMap } from "../core/cache";
import { skeletonPreview } from "../ui/skeleton";
import { undoManager } from "../core/undo";

function ensureMarkUnreadAction(el, id) {
  const actions = el?.querySelector(".mail-actions");
  if (!actions || actions.querySelector(".mark-unread")) return;

  const btn = document.createElement("span");
  btn.className = "mail-action mark-unread";
  btn.innerText = "📩";
  btn.onclick = function onMarkUnreadClick(e) {
    e.stopPropagation();
    markUnread(id);
  };

  actions.prepend(btn);
}

function removeUnreadDot(item) {
  const dot = item?.querySelector(".unread-dot");
  if (!dot) return;

  dot.style.transition = "opacity .25s ease";
  dot.style.opacity = 0;

  setTimeout(() => {
    dot.remove();
  }, 250);
}

function addUnreadDot(item) {
  if (!item || item.querySelector(".unread-dot")) return;

  const dot = document.createElement("div");
  dot.className = "unread-dot";

  const avatar = item.querySelector(".mail-avatar");
  if (avatar) {
    avatar.insertAdjacentElement("afterend", dot);
  }
}


export async function openMail(id, el) {
  const preview = qs(".mail-preview");
  if (!preview) return;

  const requestId = ++state.previewRequest;

  preview.innerHTML = skeletonPreview();

  let html = null;

  if (state.previewCache.has(id)) {
    html = state.previewCache.get(id);
  } else {
    const text = await safeText("/mail/preview/" + id);

    if (!text) {
      preview.innerHTML = `
        <div style="padding:40px;color:#605e5c">
          Unable to load email
        </div>
      `;
      return;
    }

    html = text;
    setLimitedMap(state.previewCache, id, html, 40);
  }

  if (requestId !== state.previewRequest) return;

  // ===============================
  // RENDER IFRAME
  // ===============================
  preview.innerHTML = `
    <iframe class="mail-frame"
      sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
      style="width:100%;height:100%;border:none;background:white">
    </iframe>
  `;

  const iframe = preview.querySelector(".mail-frame");
  if (!iframe) return;

  const doc = iframe.contentDocument || iframe.contentWindow.document;

  doc.open();
  doc.write(html);
  doc.close();

  // ===============================
  // ATTACHMENT CLICK BRIDGE (FIX FINAL)
  // ===============================
  doc.addEventListener("click", function (e) {
    let el = e.target;

    while (el && el !== doc) {
      if (el.classList && el.classList.contains("mail-attachment")) {
        break;
      }
      el = el.parentNode;
    }

    if (!el || el === doc) return;

    const messageId = el.dataset.message;
    const index = parseInt(el.dataset.index, 10);

 let attachments = [];

try {
  attachments = JSON.parse(el.dataset.attachments || "[]");
} catch (err) {
  console.error("❌ JSON parse error", err);
  return;
}
    console.log("🔥 CLICK TRIGGERED");
    console.log("✅ CALLING VIEWER", { messageId, index, attachments });

    if (typeof window.openAttachmentViewer === "function") {
      window.openAttachmentViewer(messageId, attachments, index);
    }
  });

  // ===============================
  // AUTO MARK READ
  // ===============================
  try {
    await safeFetch("/mail/read/" + id);
  } catch (e) {}

  if (el) {
    el.classList.remove("unread");
    removeUnreadDot(el);
    ensureMarkUnreadAction(el, id);
  }
}

export async function markUnread(id) {
  await safeFetch("/mail/unread/" + id);

  const item = document.querySelector('.mail-item[mail-id="' + id + '"]');
  if (!item) return;

  item.classList.add("unread");

  const folder = document.querySelector(".folder.active");
  if (folder && typeof window.updateFolderUnread === "function") {
    window.updateFolderUnread(folder.dataset.id, 1);
  }

  addUnreadDot(item);
  ensureMarkUnreadAction(item, id);

  undoManager.notify("Marked as unread");
}
export async function markRead(id) {
  await safeFetch("/mail/read/" + id);

  const item = document.querySelector('.mail-item[mail-id="' + id + '"]');
  if (!item) return;

  if (item.classList.contains("unread")) {
    item.classList.remove("unread");

    const folder = document.querySelector(".folder.active");
    if (folder && typeof window.updateFolderUnread === "function") {
      window.updateFolderUnread(folder.dataset.id, -1);
    }
  }

  removeUnreadDot(item);

  const actions = item.querySelector(".mail-right");
  if (actions) {
    const btn = actions.querySelector(".mark-read");
    if (btn) btn.remove();
  }

  undoManager.notify("Marked as read");
}

export function openThread(conversationId, messageId) {
  let threadId = conversationId;
  if (!threadId) {
    threadId = messageId;
  }

  const preview = qs(".mail-preview");
  if (!preview) return;

  preview.innerHTML = "Loading...";

  safeText("/mail/thread/" + threadId + "?message=" + messageId)
    .then((html) => {
      if (!html) return;
      preview.innerHTML = html;
    });
}