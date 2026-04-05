import { state } from "../core/state";
import { safeFetch } from "../core/api";
import { loadFolder } from "./folder";

export function mountFolderDrop() {

  document.querySelectorAll(".folder").forEach(folder => {

    if (folder.dataset.dropBound) return;
    folder.dataset.dropBound = "1";

    folder.addEventListener("dragover", function (e) {
      e.preventDefault();
      this.classList.add("drag-over");
    });

    folder.addEventListener("dragleave", function () {
      this.classList.remove("drag-over");
    });

    folder.addEventListener("drop", async function (e) {
      e.preventDefault();
      this.classList.remove("drag-over");

      if (state.loadingMove) return;
      state.loadingMove = true;

      try {

        const folderId = this.dataset.id;
        const ids = window.__draggedMails || [];

        if (!ids.length) return;

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

        if (!window.__dirtyFolders) {
          window.__dirtyFolders = new Set();
        }

        // target
        const key = state.tokenId + "_" + folderId;
        window.__dirtyFolders.add(key);

        // source
        const active = document.querySelector(".folder.active");
        if (active) {
          const activeKey = state.tokenId + "_" + active.dataset.id;
          window.__dirtyFolders.add(activeKey);

          loadFolder(active.dataset.id, "", active);
        }

        window.undoManager?.notify("Email moved");

      } catch (e) {
        console.error("Move error", e);
        window.undoManager?.notify("Move failed ❌");
      } finally {
        state.loadingMove = false;
      }
    });

  });

}