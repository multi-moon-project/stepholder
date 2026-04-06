import { state } from "../core/state";
import { qs, qsa } from "../core/dom";
import { safeText } from "../core/api";
import { skeletonList } from "../ui/skeleton";
import { refreshFolderCrudBindings } from "./folderCrud";

/*
========================================
HELPER
========================================
*/
function getFolderCacheKey(folderId) {
  return `${state.tokenId}_${folderId}`;
}

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
export async function loadFolder(id, name = "", el = null, options = {}) {
  const { force = false } = options;

  if (!id) return;

  state.loadingMore = false;
  state.searchMode = false;
  state.searchQuery = "";
  state.currentFolderId = id;
  state.currentFolder = name || state.currentFolder || "";

  const cacheKey = getFolderCacheKey(id);

  if (!window.__dirtyFolders) {
    window.__dirtyFolders = new Set();
  }

  /*
  =========================
  UI: highlight folder
  =========================
  */
  qsa(".folder").forEach((f) => f.classList.remove("active"));
  if (el) {
    el.classList.add("active");
  } else {
    const activeEl = document.querySelector(`.folder[data-id="${id}"]`);
    if (activeEl) activeEl.classList.add("active");
  }

  toggleTrashButtons();

  /*
  =========================
  RESET PREVIEW
  =========================
  */
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

  /*
  =========================
  CACHE HIT
  =========================
  */
  const isDirty = window.__dirtyFolders.has(cacheKey);

  if (!force && !isDirty && state.folderCache?.has(cacheKey)) {
    const cache = state.folderCache.get(cacheKey);

    state.mailListEl.innerHTML = cache.html;
    state.nextPage = cache.nextPage ?? null;

    refreshFolderCrudBindings();
    return;
  }

  /*
  =========================
  FORCE / DIRTY => DROP CACHE
  =========================
  */
  if (state.folderCache?.has(cacheKey)) {
    state.folderCache.delete(cacheKey);
  }

  /*
  =========================
  LOADING STATE
  =========================
  */
  if (state.mailListEl) {
    state.mailListEl.innerHTML = skeletonList();
  }

  /*
  ========================================
  FETCH (MULTI ACCOUNT + SAFE URL)
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
  const next = doc.querySelector("#nextPageLink");
  const nextPage = next ? next.dataset.next : null;

  if (newList && state.mailListEl) {
    state.mailListEl.innerHTML = newList.innerHTML;

    state.folderCache.set(cacheKey, {
      html: newList.innerHTML,
      nextPage
    });

    window.__dirtyFolders.delete(cacheKey);
  }

  /*
  =========================
  SAVE PAGINATION STATE
  =========================
  */
  state.nextPage = nextPage;

  /*
  ========================================
  UPDATE URL (MULTI ACCOUNT SAFE)
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