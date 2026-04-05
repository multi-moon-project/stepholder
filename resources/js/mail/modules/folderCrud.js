import { state } from "../core/state";
import { qs, qsa } from "../core/dom";
import { safeFetch } from "../core/api";
import { getCsrfToken } from "../core/utils";
import { undoManager } from "../core/undo";
import { loadFolder } from "./folder";

function reloadInboxAndClearFolderCache() {
  if (state.folderCache?.clear) {
    state.folderCache.clear();
  }

  if (typeof loadFolder === "function") {
    loadFolder(state.inboxFolderId, "Inbox");
  }
}

export async function createFolder() {
  const name = prompt("Folder name");
  if (!name) return;

  await safeFetch("/folder/create", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": getCsrfToken(),
    },
    body: JSON.stringify({
      name,
      token_id: state.tokenId
    }),
  });

  await reloadSidebar();
  reloadInboxAndClearFolderCache();

  undoManager.notify("Folder created");
}

export async function deleteFolder(id) {
  if (!confirm("Delete this folder?\nEmails will move to Deleted Items.")) {
    return;
  }

  await safeFetch(`/folder/delete/${id}?token_id=${state.tokenId}`, {
    method: "DELETE",
    headers: {
      "X-CSRF-TOKEN": getCsrfToken(),
    },
  });

  await reloadSidebar();
  reloadInboxAndClearFolderCache();

  undoManager.notify("Folder deleted");
}

export async function saveRename(id, input) {
  const name = input?.value?.trim() || "";

  if (!name) {
    reloadInboxAndClearFolderCache();
    return;
  }

  await safeFetch(`/folder/rename/${id}`, {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": getCsrfToken(),
    },
    body: JSON.stringify({
      name,
      token_id: state.tokenId
    }),
  });

  await reloadSidebar();
  reloadInboxAndClearFolderCache();

  undoManager.notify("Folder renamed");
}

function bindFolderRenameElement(el) {
  if (!el || el.dataset.renameBound === "1") return;

  el.dataset.renameBound = "1";

  el.addEventListener("dblclick", function onFolderNameDblClick(e) {
    e.stopPropagation();

    const folder = el.closest(".folder");
    if (!folder) return;

    const id = folder.dataset.id;
    const current = el.innerText;

    const input = document.createElement("input");
    input.value = current;
    input.style.width = "100%";
    input.className = "folder-rename-input";

    el.replaceWith(input);
    input.focus();
    input.select();

    let saved = false;

    const commit = async () => {
      if (saved) return;
      saved = true;
      await saveRename(id, input);
    };

    input.addEventListener("blur", commit);

    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        commit();
      }
      if (event.key === "Escape") {
        saved = true;
        reloadInboxAndClearFolderCache();
      }
    });
  });
}

export function mountFolderRename() {
  qsa(".folder-name").forEach(bindFolderRenameElement);
}

function bindFolderContextMenuElement(folder) {
  if (!folder || folder.dataset.contextBound === "1") return;

  folder.dataset.contextBound = "1";

  folder.addEventListener("contextmenu", function onFolderContextMenu(e) {
    e.preventDefault();

    state.currentFolderMenu = folder.dataset.id;

    const menu = qs("#folderMenu");
    if (!menu) return;

    menu.style.left = e.pageX + "px";
    menu.style.top = e.pageY + "px";
    menu.style.display = "block";
  });
}

export function mountFolderContextMenu() {
  qsa(".folder").forEach(bindFolderContextMenuElement);

  document.addEventListener("click", () => {
    const menu = qs("#folderMenu");
    if (menu) {
      menu.style.display = "none";
    }

    // 🔥 FIX: reset state
    state.currentFolderMenu = null;
  });
}

export function menuRename() {
  const folder = qs(`.folder[data-id="${state.currentFolderMenu}"]`);
  const name = folder?.querySelector(".folder-name");

  if (name) {
    name.dispatchEvent(new Event("dblclick"));
  }
}

export function menuDelete() {
  if (!state.currentFolderMenu) return;
  deleteFolder(state.currentFolderMenu);
}

export function menuCreate() {
  createFolder();
}

export function refreshFolderCrudBindings() {
  mountFolderRename();
  mountFolderContextMenu();
}

async function reloadSidebar() {
  const res = await safeFetch(`/folders?token_id=${state.tokenId}`);
  const html = await res.text();

  const parser = new DOMParser();
  const doc = parser.parseFromString(html, "text/html");

  const newSidebar = doc.querySelector(".sidebar");
  const sidebar = document.querySelector(".sidebar");

  if (!newSidebar || !sidebar) return;

  sidebar.innerHTML = newSidebar.innerHTML;

  refreshFolderCrudBindings();

  if (window.mountFolderDrop) {
    window.mountFolderDrop();
  }
}