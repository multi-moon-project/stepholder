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

  if (requestId !== state.previewRequest) {
    return;
  }

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

  doc.addEventListener("click", function onIframeClick(e) {
    const attachmentEl = e.target.closest(".mail-attachment");
    if (!attachmentEl) return;

    const messageId = attachmentEl.dataset.message;
    const index = parseInt(attachmentEl.dataset.index, 10);
    const attachments = JSON.parse(attachmentEl.dataset.attachments || "[]");

    if (typeof window.openAttachmentViewer === "function") {
      window.openAttachmentViewer(messageId, attachments, index);
    }
  });

  try {
    await safeFetch("/mail/read/" + id);
  } catch (e) {
    // noop for parity with old behavior
  }

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