import { state } from "../core/state";
import { safeJson, safeText } from "../core/api";

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
export async function checkNewMail() {
  const mails = await safeJson("/mail/delta");
  if (!Array.isArray(mails)) return;

  if (state.firstDelta) {
    mails.forEach(m => state.processedIds.add(m.id));
    state.firstDelta = false;
    return;
  }

  mails.forEach(mail => {
    if (!state.processedIds.has(mail.id)) {
      state.processedIds.add(mail.id);

      updateFolderUnread(mail.parentFolderId, 1);

      if (state.currentFolder.toLowerCase().includes("inbox")) {
        safeText(`/mail/item/${mail.id}`).then(html => {
          state.mailListEl.insertAdjacentHTML("afterbegin", html);
        });
      }

      showMailNotification(mail);
    }
  });
}
export function startDelta(){
  if(state.deltaTimer) return
  state.deltaTimer = setInterval(checkNewMail,7000)
}

export function stopDelta(){
  clearInterval(state.deltaTimer)
  state.deltaTimer = null
}

export function mountRealtime(){
  document.addEventListener("visibilitychange",()=>{
    document.hidden ? stopDelta() : startDelta()
  })

  startDelta()
}