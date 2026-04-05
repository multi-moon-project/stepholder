import { state } from "../core/state";
import { qs, qsa } from "../core/dom";
import { safeText } from "../core/api";
import { skeletonList } from "../ui/skeleton";
import { refreshFolderCrudBindings } from "./folderCrud";

/*
========================================
TRASH BUTTON TOGGLE
========================================
*/
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

/*
========================================
LOAD FOLDER (MULTI ACCOUNT SAFE)
========================================
*/
export async function loadFolder(id, name, el) {
  state.loadingMore = false;

  // 🔥 WAJIB: simpan ID untuk pagination
  state.currentFolderId = id;

  // 🔥 nama folder (UI only)
  state.currentFolder = name;

  // =========================
  // UI: highlight folder
  // =========================
  qsa(".folder").forEach((f) => f.classList.remove("active"));
  if (el) el.classList.add("active");

  toggleTrashButtons();

  // =========================
  // RESET PREVIEW
  // =========================
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
  // CACHE HIT
  // =========================
  if (!window.__dirtyFolders?.has(id) && state.folderCache?.has(id)) {
    const cache = state.folderCache.get(id);

    state.mailListEl.innerHTML = cache.html;
    state.nextPage = cache.nextPage;

    refreshFolderCrudBindings();
    return;
  }

  // =========================
  // LOADING STATE
  // =========================
  state.mailListEl.innerHTML = skeletonList();

  /*
  ========================================
  FETCH (🔥 MULTI ACCOUNT + SAFE URL)
  ========================================
  */
  const url =
    "/folder/" +
    encodeURIComponent(id) +
    "?token_id=" +
    encodeURIComponent(state.tokenId);

  const html = await safeText(url);

  if (!html) return;

  const parser = new DOMParser();
  const doc = parser.parseFromString(html, "text/html");

  const newList = doc.querySelector(".mail-list");

  // =========================
  // NEXT PAGE (IMPORTANT)
  // =========================
  const next = doc.querySelector("#nextPageLink");
  const nextPage = next ? next.dataset.next : null;

  if (newList) {
    state.mailListEl.innerHTML = newList.innerHTML;

    state.folderCache.set(id, {
      html: newList.innerHTML,
      nextPage: nextPage,
    });

    if (window.__dirtyFolders) {
      window.__dirtyFolders.delete(id);
    }
  }

  // =========================
  // SAVE PAGINATION STATE
  // =========================
  state.nextPage = nextPage;

  /*
  ========================================
  UPDATE URL (🔥 MULTI ACCOUNT SAFE)
  ========================================
  */
  history.pushState(
    {},
    "",
    "/folder/" +
      encodeURIComponent(id) +
      "?token_id=" +
      encodeURIComponent(state.tokenId)
  );

  refreshFolderCrudBindings();
}