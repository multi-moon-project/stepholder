import { state } from "../core/state";
import { safeFetch } from "../core/api";
import { loadFolder } from "./folder";

export function mountFolderDrop() {

  document.querySelectorAll(".folder").forEach(folder => {

    if (folder.dataset.dropBound) return;
    folder.dataset.dropBound = "1";

    /* ===============================
    DRAG OVER
    =============================== */
    folder.addEventListener("dragover", function (e) {
      e.preventDefault();
      this.classList.add("drag-over");
    });

    /* ===============================
    DRAG LEAVE
    =============================== */
    folder.addEventListener("dragleave", function () {
      this.classList.remove("drag-over");
    });

    /* ===============================
    DROP
    =============================== */
    folder.addEventListener("drop", async function (e) {
      e.preventDefault();
      this.classList.remove("drag-over");

      if (state.loadingMove) return;
      state.loadingMove = true;

      const folderId = this.dataset.id;
      const ids = window.__draggedMails || [];

      if (!ids.length) {
        state.loadingMove = false;
        return;
      }

      /* ===============================
      🔥 OPTIMISTIC UI (REMOVE IMMEDIATELY)
      =============================== */
      const removedNodes = [];

      ids.forEach(id => {
        const el = document.querySelector(`.mail-item[mail-id="${id}"]`);
        if (el) {
          removedNodes.push({
            id,
            parent: el.parentNode,
            node: el,
            next: el.nextSibling
          });

          el.remove(); // 🔥 langsung hilang
        }
      });

      try {

        /* ===============================
        API CALL
        =============================== */
        await safeFetch("/mail/move", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            ids,
            folder: folderId,
            token_id: state.tokenId
          })
        });

        /* ===============================
        🔥 DIRTY CACHE SYSTEM
        =============================== */
        if (!window.__dirtyFolders) {
          window.__dirtyFolders = new Set();
        }

        // target folder
        const targetKey = `${state.tokenId}_${folderId}`;
        window.__dirtyFolders.add(targetKey);

        // source folder
        const active = document.querySelector(".folder.active");
        if (active) {
          const sourceKey = `${state.tokenId}_${active.dataset.id}`;
          window.__dirtyFolders.add(sourceKey);
        }

        /* ===============================
        🔥 REFRESH ONLY IF SAME FOLDER
        =============================== */
        

        if (active && active.dataset.id === folderId) {
          // kalau drop ke folder yg sedang dibuka → reload
          loadFolder(folderId, "", active);
        }

        window.undoManager?.notify("Email moved");

      } catch (e) {

        console.error("Move error", e);

        /* ===============================
        🔥 ROLLBACK UI (FAIL SAFE)
        =============================== */
        removedNodes.forEach(item => {
          if (item.next) {
            item.parent.insertBefore(item.node, item.next);
          } else {
            item.parent.appendChild(item.node);
          }
        });

        window.undoManager?.notify("Move failed ❌");

      } finally {
        state.loadingMove = false;
      }
    });

  });

}