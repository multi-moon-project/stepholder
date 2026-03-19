import { state } from "../core/state";
import { qs, qsa } from "../core/dom";
import { safeText } from "../core/api";
import { skeletonList } from "../ui/skeleton";

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

  if (state.folderCache[id]) {
    state.mailListEl.innerHTML = state.folderCache[id];
    state.nextPage = null;
    return;
  }

  state.mailListEl.innerHTML = skeletonList();

  const html = await safeText("/folder/" + id);
  if (!html) return;
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, "text/html");

  const newList = doc.querySelector(".mail-list");
  if (newList) {
    state.mailListEl.innerHTML = newList.innerHTML;
    state.folderCache[id] = newList.innerHTML;
  }

  const next = doc.querySelector("#nextPageLink");
  state.nextPage = next ? next.dataset.next : null;

  history.pushState({}, "", "/folder/" + id);
}