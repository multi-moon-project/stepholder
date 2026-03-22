import { state } from "../core/state";
import { qs } from "../core/dom";
import { safeJson } from "../core/api";
import { renderMailList, allEmails } from "./mailList";

/*
=====================================
SERVER SEARCH (ANTI RACE CONDITION)
=====================================
*/
export async function liveSearch(q) {
  if (q.length < 3) return;

  const requestId = ++state.searchRequestId;

  state.searchMode = true;
  state.searchQuery = q;
  state.nextPage = null;

  const data = await safeJson("/api/search?q=" + encodeURIComponent(q));
  if (!data) return;

  // 🔥 cegah response lama overwrite
  if (requestId !== state.searchRequestId) return;

  state.nextPage = data.next;

  if (!data.emails || !data.emails.length) {
    renderMailList([], q);
    return;
  }

  renderMailList(data.emails, q);
}

/*
=====================================
INSTANT LOCAL FILTER (FAST UX)
=====================================
*/
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

/*
=====================================
ADD SEARCH TOKEN (from:, subject:, dll)
=====================================
*/
export function addSearch(token) {
  const box = qs("#mailSearch");
  if (!box) return;

  box.value = (box.value + " " + token).trim() + " ";
  box.focus();
}

/*
=====================================
MOUNT SEARCH (MAIN LOGIC)
=====================================
*/
export function mountSearch() {
  const searchBox = qs("#mailSearch");
  if (!searchBox) return;

  // init
  state.searchRequestId = 0;

  searchBox.addEventListener("input", function () {
    clearTimeout(state.searchTimer);

    const q = this.value.trim();

    // 🔥 instant UI feedback
    instantFilter(q);

    state.searchTimer = setTimeout(() => {

      // reset ke inbox kalau kosong
      if (q.length < 2) {
        state.searchMode = false;
        state.searchQuery = "";

        if (typeof window.loadFolder === "function") {
          window.loadFolder(state.inboxFolderId, "Inbox");
        }

        return;
      }

      // 🔥 server search
      liveSearch(q);

    }, 400);
  });
}