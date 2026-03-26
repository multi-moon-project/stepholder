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
  state.loadingMore = false;
  state.currentFolder = name;

  // highlight
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
  // LOADING
  // =========================
  state.mailListEl.innerHTML = skeletonList();

  // =========================
  // FETCH
  // =========================
  const html = await safeText("/folder/" + id);
  if (!html) return;

  const parser = new DOMParser();
  const doc = parser.parseFromString(html, "text/html");

  const newList = doc.querySelector(".mail-list");

  // 🔥 ambil nextPage dulu
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

  // update pagination
  state.nextPage = nextPage;

  history.pushState({}, "", "/folder/" + id);

  refreshFolderCrudBindings();
}