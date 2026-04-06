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
   🔥 FIX CID + TOKEN INJECTION
=============================== */

function patchIframeContent(doc) {
  if (!doc) return;

  const imgs = doc.querySelectorAll("img");

  imgs.forEach((img) => {
    try {
      let src = img.getAttribute("src");
      if (!src) return;

      // skip cid yg belum di replace backend
      if (src.startsWith("cid:")) return;

      const url = new URL(src, window.location.origin);

      if (!url.searchParams.get("token_id")) {
        url.searchParams.set("token_id", state.tokenId);
        img.src = url.toString();
      }

    } catch (e) {
      console.warn("IMG PATCH FAIL", e);
    }
  });

  // 🔥 FIX semua <a> link juga
  const links = doc.querySelectorAll("a");

  links.forEach((a) => {
    try {
      const url = new URL(a.href, window.location.origin);

      if (!url.searchParams.get("token_id") && url.origin === window.location.origin) {
        url.searchParams.set("token_id", state.tokenId);
        a.href = url.toString();
      }

    } catch (e) {}
  });
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
    const text = await safeText(
      "/mail/preview/" +
      encodeURIComponent(id) +
      "?token_id=" +
      encodeURIComponent(state.tokenId)
    );

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

  // 🔥 BASE URL FIX (IMPORTANT)
  const baseUrl = window.location.origin;

  doc.write(`
    <base href="${baseUrl}">
    ${html}
  `);

  doc.close();

  // 🔥 PATCH TOKEN KE SEMUA IMG + LINK
  setTimeout(() => {
    patchIframeContent(doc);
  }, 10);

  // CLICK BRIDGE
  doc.addEventListener("click", (e) => {
    let target = e.target;

    while (target && target !== doc) {

      if (target.dataset?.action) {
        const action = target.dataset.action;
        const mailId = target.dataset.id;

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
    await safeFetch(
      `/mail/read/${encodeURIComponent(id)}?token_id=${state.tokenId}`
    );
  } catch {}

  if (el) {
    el.classList.remove("unread");
    removeUnreadDot(el);
    ensureMarkUnreadAction(el, id);
  }
}

/* ===============================
   REPLY / FORWARD
=============================== */

export async function replyMail(id) {

  const preview = qs(".mail-preview");
  if (!preview) return;

  preview.innerHTML = skeletonPreview();

  const res = await safeFetch(
    `/mail/reply/${encodeURIComponent(id)}?token_id=${state.tokenId}`
  );

  const data = await res.json();

  preview.innerHTML = data.html;

  window.composeBody = data.body;

  await window.loadEditor?.();

  setTimeout(() => {
    window.initEditor?.();
    window.initRecipientChips?.();
    window.mountAttachmentInput?.();
  }, 50);
}

export async function replyAllMail(id) {

  const preview = qs(".mail-preview");
  if (!preview) return;

  preview.innerHTML = skeletonPreview();

  const res = await safeFetch(
    `/mail/reply-all/${encodeURIComponent(id)}?token_id=${state.tokenId}`
  );

  const data = await res.json();

  preview.innerHTML = data.html;

  window.composeBody = data.body;

  await window.loadEditor?.();

  setTimeout(() => {
    window.initEditor?.();
    window.initRecipientChips?.();
    window.mountAttachmentInput?.();
  }, 50);
}

export async function forwardMail(id) {

  const preview = qs(".mail-preview");
  if (!preview) return;

  preview.innerHTML = skeletonPreview();

  const res = await safeFetch(
    `/mail/forward/${encodeURIComponent(id)}?token_id=${state.tokenId}`
  );

  const data = await res.json();

  preview.innerHTML = data.html;

  window.composeBody = data.body;

  await window.loadEditor?.();

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
  await safeFetch(`/mail/unread/${id}?token_id=${state.tokenId}`);

  const item = document.querySelector(`.mail-item[mail-id="${id}"]`);
  if (!item) return;

  item.classList.add("unread");
  addUnreadDot(item);

  undoManager.notify("Marked as unread");
}

export async function markRead(id) {
  await safeFetch(`/mail/read/${id}?token_id=${state.tokenId}`);

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
  const preview = qs(".mail-preview");
  if (!preview) return;

  preview.innerHTML = "Loading...";

  safeText(
    `/mail/thread/${encodeURIComponent(conversationId)}?message=${encodeURIComponent(messageId)}&token_id=${state.tokenId}`
  ).then((html) => {
    if (html) preview.innerHTML = html;
  });
}