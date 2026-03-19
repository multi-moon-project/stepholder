import { state } from "../core/state";
import { qs } from "../core/dom";
import { safeJson } from "../core/api";
import { renderMailList, allEmails } from "./mailList";

export async function liveSearch(q) {
  if (q.length < 3) return;

  state.searchMode = true;
  state.searchQuery = q;
  state.nextPage = null;

  const data = await safeJson("/api/search?q=" + encodeURIComponent(q));
  if (!data) return;

  state.nextPage = data.next;
  renderMailList(data.emails, q);
}

export function instantFilter(q) {
  if (!q) {
    renderMailList(allEmails, "");
    return;
  }

  const normalized = q.toLowerCase();

  const filtered = allEmails.filter((mail) =>
    (mail.subject ?? "").toLowerCase().includes(normalized) ||
    (mail.bodyPreview ?? "").toLowerCase().includes(normalized) ||
    (mail.from ?? "").toLowerCase().includes(normalized)
  );

  renderMailList(filtered, q);
}

export function addSearch(token) {
  const box = qs("#mailSearch");
  if (!box) return;

  box.value = box.value + " " + token;
  box.focus();
}

export function mountSearch() {
  const searchBox = qs("#mailSearch");
  if (!searchBox) return;

  searchBox.addEventListener("keyup", function onMailSearchKeyup() {
    clearTimeout(state.searchTimer);

    const q = this.value.trim();

    state.searchTimer = setTimeout(() => {
      if (q.length < 2) {
        state.searchMode = false;
        state.searchQuery = "";

        if (typeof window.loadFolder === "function") {
          window.loadFolder(state.inboxFolderId, "Inbox");
        }
        return;
      }

      liveSearch(q);
    }, 300);
  });
}