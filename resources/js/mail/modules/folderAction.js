

export function mountFolderDrop() {

  document.querySelectorAll(".folder").forEach(folder => {

    // prevent double bind
    if (folder.dataset.dropBound) return;
    folder.dataset.dropBound = "1";

    // ===============================
    // DRAG OVER (WAJIB)
    // ===============================
    folder.addEventListener("dragover", function (e) {
      e.preventDefault(); // 🔥 INI KUNCI
      this.classList.add("drag-over");
    });

    // ===============================
    // DRAG LEAVE
    // ===============================
    folder.addEventListener("dragleave", function () {
      this.classList.remove("drag-over");
    });

    // ===============================
    // DROP
    // ===============================
   folder.addEventListener("drop", async function (e) {
  e.preventDefault();
  this.classList.remove("drag-over");

  const folderId = this.dataset.id;
  const ids = window.__draggedMails || [];

  if (!ids.length) return;

  // ===============================
  // CALL API
  // ===============================
  await fetch("/mail/move", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": document
        .querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
      ids,
      folder: folderId
    })
  });

  // ===============================
  // 🔥 MARK DIRTY FOLDERS
  // ===============================
  if (!window.__dirtyFolders) {
    window.__dirtyFolders = new Set();
  }

  // target folder
  window.__dirtyFolders.add(folderId);

  // source folder (active)
  const active = document.querySelector(".folder.active");
  if (active) {
    window.__dirtyFolders.add(active.dataset.id);
  }

  // ===============================
  // REFRESH CURRENT FOLDER
  // ===============================
  if (active) {
    window.loadFolder(active.dataset.id, "", active);
  }

  // ===============================
  // TOAST
  // ===============================
  window.undoManager?.notify("Email moved");
});

  });

}