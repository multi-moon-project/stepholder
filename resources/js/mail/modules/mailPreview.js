import { state } from "../core/state";
import { qs } from "../core/dom";
import { safeFetch, safeText } from "../core/api";
import { setLimitedMap } from "../core/cache";
import { skeletonPreview } from "../ui/skeleton";
import { undoManager } from "../core/undo";

/* ===============================
   HELPERS
=============================== */

function ensureMarkUnreadAction(el, id) {
  const actions = el?.querySelector(".mail-actions");
  if (!actions || actions.querySelector(".mark-unread")) return;

  const btn = document.createElement("span");
  btn.className = "mail-action mark-unread";
  btn.innerText = "📩";
  btn.onclick = (e) => {
    e.stopPropagation();
    markUnread(id);
  };

  actions.prepend(btn);
}

function removeUnreadDot(item) {
  const dot = item?.querySelector(".unread-dot");
  if (!dot) return;

  dot.style.opacity = 0;
  setTimeout(() => dot.remove(), 250);
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

/* ===============================
   OPEN MAIL
=============================== */

export async function openMail(id, el) {

  console.log("📨 OPEN MAIL:", id);

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
      preview.innerHTML = `<div style="padding:40px">Unable to load email</div>`;
      return;
    }

    html = text;
    setLimitedMap(state.previewCache, id, html, 40);
  }

  if (requestId !== state.previewRequest) return;

  preview.innerHTML = `
    <iframe class="mail-frame"
      sandbox="allow-same-origin allow-scripts allow-popups allow-popups-to-escape-sandbox"
      style="width:100%;height:100%;border:none;background:white">
    </iframe>
  `;

  const iframe = preview.querySelector(".mail-frame");
  if (!iframe) return;

  const doc = iframe.contentDocument || iframe.contentWindow.document;

  doc.open();
  doc.write(html);
  doc.close();

  // CLICK BRIDGE
  doc.addEventListener("click", (e) => {
    let target = e.target;

    while (target && target !== doc) {

      if (target.dataset?.action) {
        const action = target.dataset.action;
        const mailId = target.dataset.id;

        console.log("⚡ ACTION:", action);

        e.preventDefault();
        e.stopPropagation();

        if (action === "reply") replyMail(mailId);
        if (action === "reply-all") replyAllMail(mailId);
        if (action === "forward") forwardMail(mailId);

        return;
      }

      target = target.parentNode;
    }
  });

  try {
    await safeFetch("/mail/read/" + id);
  } catch {}

  if (el) {
    el.classList.remove("unread");
    removeUnreadDot(el);
    ensureMarkUnreadAction(el, id);
  }
}

/* ===============================
   REPLY / FORWARD (🔥 FIX UTAMA)
=============================== */

export async function replyMail(id) {
  console.log("↩️ REPLY:", id);

  const preview = qs(".mail-preview");
  preview.innerHTML = skeletonPreview();

  const res = await fetch("/mail/reply/" + id);
  const data = await res.json();

  preview.innerHTML = data.html;

  // 🔥 SET BODY DULU
  window.composeBody = data.body;

  console.log("📦 BODY FROM SERVER:", data.body);

  // 🔥 WAJIB: load TinyMCE dulu
  if (window.loadEditor) {
    await window.loadEditor();
  }

  // 🔥 BARU INIT
  setTimeout(() => {
    console.log("🚀 INIT EDITOR FROM PREVIEW");

    window.initEditor?.();
    window.initRecipientChips?.();
    window.mountAttachmentInput?.();
  }, 50);
}

export async function replyAllMail(id) {
  console.log("👥 REPLY ALL:", id);

  const preview = qs(".mail-preview");
  preview.innerHTML = skeletonPreview();

  const res = await fetch("/mail/reply-all/" + id);
  const data = await res.json();

  preview.innerHTML = data.html;

  // 🔥 SET BODY
  window.composeBody = data.body;

  console.log("📦 BODY REPLY ALL:", data.body);

  // 🔥 load TinyMCE
  if (window.loadEditor) {
    await window.loadEditor();
  }

  setTimeout(() => {
    window.initEditor?.();
    window.initRecipientChips?.();
    window.mountAttachmentInput?.();
  }, 50);
}

export async function forwardMail(id) {
  console.log("📤 FORWARD:", id);

  const preview = qs(".mail-preview");
  preview.innerHTML = skeletonPreview();

  const res = await fetch("/mail/forward/" + id);
  const data = await res.json();

  preview.innerHTML = data.html;

  // 🔥 SET BODY
  window.composeBody = data.body;

  console.log("📦 BODY FORWARD:", data.body);

  if (window.loadEditor) {
    await window.loadEditor();
  }

  setTimeout(() => {
    window.initEditor?.();
    window.initRecipientChips?.();
    window.mountAttachmentInput?.();
  }, 50);
}

/* ===============================
   MARK READ / UNREAD
=============================== */

export async function markUnread(id) {
  await safeFetch("/mail/unread/" + id);

  const item = document.querySelector(`.mail-item[mail-id="${id}"]`);
  if (!item) return;

  item.classList.add("unread");
  addUnreadDot(item);

  undoManager.notify("Marked as unread");
}

export async function markRead(id) {
  await safeFetch("/mail/read/" + id);

  const item = document.querySelector(`.mail-item[mail-id="${id}"]`);
  if (!item) return;

  item.classList.remove("unread");
  removeUnreadDot(item);

  undoManager.notify("Marked as read");
}

/* ===============================
   THREAD
=============================== */

export function openThread(conversationId, messageId) {
  const threadId = conversationId || messageId;

  const preview = qs(".mail-preview");
  preview.innerHTML = "Loading...";

  safeText(`/mail/thread/${threadId}?message=${messageId}`)
    .then((html) => {
      if (html) preview.innerHTML = html;
    });
}