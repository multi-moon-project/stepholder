import { state } from "../core/state";
import { safeJson, safeText } from "../core/api";
import { undoManager } from "../core/undo";

export function updateFolderUnread(folderId, delta) {
  const folder = state.folderMap.get(folderId) || document.querySelector(`.folder[data-id="${folderId}"]`);
  if (!folder) return;

  let counter = folder.querySelector(".folder-count");

  if (!counter) {
    counter = document.createElement("span");
    counter.className = "folder-count";
    counter.innerText = "0";
    folder.appendChild(counter);
  }

  let count = parseInt(counter.innerText || 0, 10);
  count += delta;
  if (count < 0) count = 0;
  counter.innerText = count;
}
export function showMailNotification(mail) {
  const container = document.getElementById("mailNotifications");
  if (!container) return;

  const letter = (mail.from ?? "U")[0].toUpperCase();
  const el = document.createElement("div");

  el.className = "mail-notification";
  el.innerHTML = `
    <div class="mail-notification-avatar">${letter}</div>
    <div class="mail-notification-content">
      <div class="mail-notification-from">${mail.from ?? "Unknown"}</div>
      <div class="mail-notification-subject">${mail.subject ?? "(No subject)"}</div>
      <div class="mail-notification-preview">${mail.bodyPreview ?? ""}</div>
    </div>
    <div class="mail-notification-close">✕</div>
  `;

  const closeBtn = el.querySelector(".mail-notification-close");
  if (closeBtn) {
    closeBtn.onclick = function onNotificationClose(e) {
      e.stopPropagation();
      el.style.opacity = 0;
      el.style.transform = "translateX(60px)";
      setTimeout(() => el.remove(), 250);
    };
  }
el.onclick = async function onNotificationClick() {
    if (!(state.currentFolder || "").toLowerCase().includes("inbox")) {
      await window.loadFolder?.(state.inboxFolderId, "Inbox");

      setTimeout(() => {
        const mailItem = document.querySelector(`[mail-id="${mail.id}"]`);
        if (mailItem) {
          window.openMail?.(mail.id, mailItem);
        }
      }, 400);
    } else {
      const mailItem = document.querySelector(`[mail-id="${mail.id}"]`);
      if (mailItem) {
        window.openMail?.(mail.id, mailItem);
      }
    }

    el.remove();
  };
container.prepend(el);

  setTimeout(() => {
    if (!el.isConnected) return;
    el.style.opacity = 0;
    el.style.transform = "translateX(60px)";
    setTimeout(() => el.remove(), 300);
  }, 5000);
}

export async function checkNewMail() {
  const mails = await safeJson("/mail/delta");
  if (!Array.isArray(mails) || !mails.length) return;

  if (state.firstDelta) {
    mails.forEach((mail) => {
      if (mail?.id) state.processedIds.add(mail.id);
    });
    state.firstDelta = false;
    return;
  }

  mails.forEach((mail) => {
    if (mail["@removed"]) {
      const folderId = mail.parentFolderId || state.inboxFolderId;
      if (folderId) updateFolderUnread(folderId, -1);
      return;
    }