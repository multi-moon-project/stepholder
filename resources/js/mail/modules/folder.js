import { state } from "../core/state";
import { qs, qsa } from "../core/dom";
import { safeText } from "../core/api";
import { skeletonList } from "../ui/skeleton";
import { refreshFolderCrudBindings } from "./folderCrud";

export function toggleTrashButtons() {
  const trashBtn = qs("#emptyTrashBtn");
  const recoverBtn = qs("#recoverBtn");

  if (!trashBtn || !recoverBtn) return;

  const folder = (state.currentFolder || "").toLowerCase();

  if (folder.includes("deleted")) {
    trashBtn.style.display = "inline-block";
    recoverBtn.style.display = "inline-block";
  } else {
    trashBtn.style.display = "none";
    recoverBtn.style.display = "none";
  }
}

export async function loadFolder(id, name, el) {

   
    
  state.currentFolder = name;

  // active folder highlight
  qsa(".folder").forEach((f) => f.classList.remove("active"));
  if (el) el.classList.add("active");

  toggleTrashButtons();

  const preview = qs(".mail-preview");

  if (preview) {
    preview.innerHTML = `
      <div class="empty-preview">
        📧
        <br>
        Select an email to read
      </div>
    `;
  }

  // =========================
  // =========================
// CACHE HIT
// =========================
if (!window.__dirtyFolders?.has(id) && state.folderCache?.has(id)) {
    state.mailListEl.innerHTML = state.folderCache.get(id);
    state.nextPage = null;

    // 🔥 REBIND setelah render dari cache
    refreshFolderCrudBindings();

    return;
  }

  // =========================
  // LOADING STATE
  // =========================
  state.mailListEl.innerHTML = skeletonList();

  // =========================
  // FETCH FOLDER
  // =========================
  const html = await safeText("/folder/" + id);
  if (!html) return;

  const parser = new DOMParser();
  const doc = parser.parseFromString(html, "text/html");

  const newList = doc.querySelector(".mail-list");

  if (newList) {
    state.mailListEl.innerHTML = newList.innerHTML;

    if (!state.folderCache) {
      state.folderCache = new Map();
    }

  state.folderCache.set(id, newList.innerHTML);

// 🔥 CLEAN folder ini saja
if (window.__dirtyFolders) {
  window.__dirtyFolders.delete(id);
}
  }

  // =========================
  // PAGINATION
  // =========================
  const next = doc.querySelector("#nextPageLink");
  state.nextPage = next ? next.dataset.next : null;

  // =========================
  // HISTORY PUSH
  // =========================
  history.pushState({}, "", "/folder/" + id);

  // 🔥 CRITICAL: REBIND EVENTS
  refreshFolderCrudBindings();
}