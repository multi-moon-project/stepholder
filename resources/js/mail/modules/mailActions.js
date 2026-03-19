import { state } from "../core/state";
import { qs } from "../core/dom";
import { safeFetch, safeText } from "../core/api";
import { skeletonList, skeletonPreview } from "../ui/skeleton";
import { undoManager } from "../core/undo";

export async function deleteMail(id) {
  const item = document.querySelector(`.mail-item[mail-id="${id}"]`);
  if (!item) return;

  const html = item.outerHTML;

  item.classList.add("removing");
  setTimeout(() => item.remove(), 300);

  undoManager.push({
    message: "Message moved to Deleted Items",

    undo: async () => {
      state.mailListEl.insertAdjacentHTML("afterbegin", html);

      const el = state.mailListEl.querySelector(`[mail-id="${id}"]`);
      el.classList.add("restoring");

      await safeFetch("/mail/recover/" + id);
    },

    commit: async () => {
      let url = "/mail/delete/" + id;

      if ((state.currentFolder || "").toLowerCase().includes("deleted")) {
        url = "/mail/delete-permanent/" + id;
      }

      await safeFetch(url);
    },
  });
}
export async function deleteSelected() {
  const ids = window.getSelectedEmails?.() || [];

  if (!ids.length) {
    alert("Select email first");
    return;
  }

  let unreadRemoved = 0;

  ids.forEach((id) => {
    const el = document.querySelector(`.mail-item[mail-id="${id}"]`);
    if (el && el.classList.contains("unread")) unreadRemoved++;
  });

  const folder = document.querySelector(".folder.active");
  if (folder && unreadRemoved > 0 && window.updateFolderUnread) {
    window.updateFolderUnread(folder.dataset.id, -unreadRemoved);
  }
  ids.forEach((id) => {
    const el = document.querySelector(`.mail-item[mail-id="${id}"]`);
    if (!el) return;

    el.classList.add("removing");
    setTimeout(() => el.remove(), 300);
  });

  const requests = ids.map((id) => {
    let url = "/mail/delete/" + id;

    if ((state.currentFolder || "").toLowerCase().includes("deleted")) {
      url = "/mail/delete-permanent/" + id;
    }

    return safeFetch(url);
  });

  await Promise.all(requests);

  if ((state.currentFolder || "").toLowerCase().includes("deleted")) {
    undoManager.notify("Message permanently deleted");
  } else {
    undoManager.notify(ids.length + " messages deleted");
  }

  state.folderCache = {};
  window.clearSelection?.();
}
export async function archiveSelected() {
  const ids = window.getSelectedEmails?.() || [];

  if (!ids.length) {
    alert("Select email first");
    return;
  }

  ids.forEach((id) => {
    const item = document.querySelector(`.mail-item[mail-id="${id}"]`);
    if (!item) return;

    const html = item.outerHTML;

    item.classList.add("removing");
    setTimeout(() => item.remove(), 300);

    undoManager.push({
      message: "Message archived",

      undo: async () => {
        state.mailListEl.insertAdjacentHTML("afterbegin", html);

        const el = state.mailListEl.querySelector(`[mail-id="${id}"]`);
        el.classList.add("restoring");

        await safeFetch("/mail/recover/" + id);
      },
 commit: async () => {
        await safeFetch("/mail/archive/" + id);
      },
    });
  });

  window.clearSelection?.();
  state.folderCache = {};
}
export async function markReadSelected() {
  const ids = window.getSelectedEmails?.() || [];

  if (!ids.length) {
    alert("Select email first");
    return;
  }

  for (const id of ids) {
    await safeFetch("/mail/read/" + id);

    const item = document.querySelector(`.mail-item[mail-id="${id}"]`);
    if (!item) continue;

    if (item.classList.contains("unread")) {
      item.classList.remove("unread");

      const folder = document.querySelector(".folder.active");
      if (folder && window.updateFolderUnread) {
        window.updateFolderUnread(folder.dataset.id, -1);
      }
    }

    const dot = item.querySelector(".unread-dot");
    if (dot) {
      dot.style.opacity = 0;
      setTimeout(() => dot.remove(), 250);
    }

    const cb = item.querySelector(".mail-checkbox");
    if (cb) cb.checked = false;
  }

  undoManager.notify("Marked as read");
}
export async function recoverSelected() {
  const ids = window.getSelectedEmails?.() || [];

  if (!ids.length) {
    alert("Select email first");
    return;
  }

  for (const id of ids) {
    await safeFetch("/mail/recover/" + id);

    const el = document.querySelector(`.mail-item[mail-id="${id}"]`);
    if (el) el.remove();
  }

  undoManager.notify("Message restored");
  state.folderCache = {};
}
export async function toggleFlag(id) {
  await safeFetch("/mail/flag/" + id);

  if (typeof window.loadFolder === "function") {
    window.loadFolder(state.inboxFolderId, "Inbox");
  }

  state.folderCache = {};
}