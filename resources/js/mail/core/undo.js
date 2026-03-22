import { qs } from "./dom";

const stack = [];
let timer = null;

export const undoManager = {
  push(action) {
    stack.push(action);
    this.show(action);
  },

  async undo() {
    if (!stack.length) return;

    const action = stack.pop();

    if (action.undo) {
      await action.undo();
    }

    this.hide();
  },

  show(action) {
    const toast = qs("#toast");
    if (!toast) return;

    toast.innerHTML = `
      <i class="fa-solid fa-circle-check"></i>
      ${action.message}
      ${
        action.undo
          ? `<span class="toast-undo" data-role="toast-undo">Undo</span>`
          : ""
      }
    `;

    // bind undo click
    const undoEl = toast.querySelector('[data-role="toast-undo"]');
    if (undoEl) {
      undoEl.onclick = () => this.undo();
    }

    toast.classList.add("show");

    // clear previous timer
    clearTimeout(timer);

    timer = setTimeout(async () => {
      // kalau ada action → commit
      if (stack.length) {
        const latest = stack.pop();

        if (latest.commit) {
          await latest.commit();
        }
      }

      // 🔥 selalu hide (fix bug kamu)
      this.hide();
    }, 5000);
  },

  hide() {
    const toast = qs("#toast");
    if (!toast) return;

    toast.classList.remove("show");
  },

  // 🔥 versi ringan (tanpa undo / stack)
  notify(message) {
    const toast = qs("#toast");
    if (!toast) return;

    toast.innerHTML = `
      <i class="fa-solid fa-circle-check"></i>
      ${message}
    `;

    toast.classList.add("show");

    clearTimeout(timer);

    timer = setTimeout(() => {
      this.hide();
    }, 3000);
  },
};