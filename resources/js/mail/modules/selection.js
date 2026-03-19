import { state } from "../core/state";
import { qs, qsa } from "../core/dom";
import { openMail } from "./mailPreview";

function getMailItems() {
  return qsa(".mail-item");
}

export function getSelectedEmails() {
  const ids = [];

  qsa(".mail-item").forEach((item) => {
    const cb = item.querySelector(".mail-checkbox");
    if (cb && cb.checked) {
      ids.push(item.getAttribute("mail-id"));
    }
  });

  return ids;
}
export function selectItem(el, stateValue) {
  if (!el) return;

  const id = el.getAttribute("mail-id");
  if (!id) return;

  if (stateValue) {
    el.classList.add("selected");

    const cb = el.querySelector(".mail-checkbox");
    if (cb) cb.checked = true;

    state.selectedMails.add(id);
  } else {
    el.classList.remove("selected");

    const cb = el.querySelector(".mail-checkbox");
    if (cb) cb.checked = false;

    state.selectedMails.delete(id);
  }

  updateBulkUI();
}
export function toggleItem(el) {
  if (!el) return;

  const selected = el.classList.contains("selected");
  selectItem(el, !selected);
  updateBulkUI();
}

export function selectMail(event, el) {
  const items = getMailItems();
  const index = items.indexOf(el);

  if (event.shiftKey && state.lastSelectedIndex !== null) {
    const start = Math.min(index, state.lastSelectedIndex);
    const end = Math.max(index, state.lastSelectedIndex);

    items.forEach((item) => selectItem(item, false));

    for (let i = start; i <= end; i++) {
      selectItem(items[i], true);
    }
  } else if (event.ctrlKey || event.metaKey) {
    toggleItem(el);
  } else {
    items.forEach((item) => selectItem(item, false));
    selectItem(el, true);
  }

  state.lastSelectedIndex = index;
}
export function updateBulkUI() {
  const selected = qsa(".mail-item.selected");
  const preview = qs(".mail-preview");
  if (!preview) return;

  if (selected.length > 1) {
    state.previewRequest++;

    let restoreBtn = "";
    if ((state.currentFolder || "").toLowerCase().includes("deleted")) {
      restoreBtn = `<button onclick="recoverSelected()">♻ Restore</button>`;
    }

    preview.innerHTML = `
      <div style="text-align:center;margin-top:80px">
        <div style="font-size:60px">📨</div>
        <h2>${selected.length} items selected</h2>
        <div style="margin-top:30px;display:flex;flex-direction:column;gap:10px;align-items:center">
          <button onclick="deleteSelected()">🗑 Delete</button>
          <button onclick="archiveSelected()">📦 Archive</button>
          <button onclick="markReadSelected()">✔ Mark as read</button>
          ${restoreBtn}
          <button onclick="clearSelection()">✖ Cancel</button>
        </div>
      </div>
    `;
  }
}
export function clearSelection() {
  state.selectedMails.clear();

  qsa(".mail-item").forEach((el) => {
    selectItem(el, false);
  });

  const preview = qs(".mail-preview");
  if (!preview) return;

  preview.innerHTML = `
    <div class="empty-preview">
      📧
      <br>
      Select an email to read
    </div>
  `;
}
export function handleMailClick(event, el, id) {
  selectMail(event, el);

  const selected = qsa(".mail-item.selected");

  if (selected.length > 1) {
    updateBulkUI();
    return;
  }

  if (event.ctrlKey || event.shiftKey || event.metaKey) {
    return;
  }

  openMail(id, el);
}

export function mountSelection() {
  document.addEventListener("click", (e) => {
    if (!e.target.classList.contains("mail-checkbox")) return;

    e.stopPropagation();

    const item = e.target.closest(".mail-item");
    if (!item) return;

    const id = item.getAttribute("mail-id");

    if (e.target.checked) {
      item.classList.add("selected");
      state.selectedMails.add(id);
    } else {
      item.classList.remove("selected");
      state.selectedMails.delete(id);
    }

    updateBulkUI();
  });

  if (state.mailListEl) {
    state.mailListEl.addEventListener("click", (e) => {
      if (e.target.classList.contains("mail-checkbox")) {
        e.stopPropagation();
      }

      const item = e.target.closest(".mail-item");
      if (!item) return;

      const id = item.getAttribute("mail-id");
      handleMailClick(e, item, id);
    });
  }
}
export function mountKeyboardSelection() {
  document.addEventListener("keydown", (e) => {
    const tag = document.activeElement?.tagName?.toLowerCase() || "";
    if (tag === "input" || tag === "textarea" || document.activeElement?.isContentEditable) {
      return;
    }

    if ((e.ctrlKey || e.metaKey) && e.key === "a") {
      e.preventDefault();
      qsa(".mail-item").forEach((el) => selectItem(el, true));
      return;
    }

    const items = qsa(".mail-item");
    if (!items.length) return;

    if (e.key === "ArrowDown") {
      e.preventDefault();

      state.currentIndex++;
      if (state.currentIndex >= items.length) {
        state.currentIndex = items.length - 1;
      }

      const el = items[state.currentIndex];
      const id = el.getAttribute("mail-id");

      if (id) {
        openMail(id, el);
        el.scrollIntoView({ block: "nearest" });
      }

      return;
    }

    if (e.key === "ArrowUp") {
      e.preventDefault();

      state.currentIndex--;
      if (state.currentIndex < 0) {
        state.currentIndex = 0;
      }

      const el = items[state.currentIndex];
      const id = el.getAttribute("mail-id");

      if (id) {
        openMail(id, el);
        el.scrollIntoView({ block: "nearest" });
      }

      return;
    }
     const active = qs(".mail-item.active");
    if (!active) return;

    const id = active.getAttribute("mail-id");
    if (!id) return;

    switch (String(e.key).toLowerCase()) {
      case "delete":
        if (typeof window.deleteMail === "function") {
          window.deleteMail(id);
        }
        break;
      default:
        break;
    }
  });
}