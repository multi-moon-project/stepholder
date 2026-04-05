import { state } from "../core/state";
import { safeFetch } from "../core/api";

let oneDriveHistory = [];
let oneDriveCache = {};

export function openOneDrive() {
  const panel = document.getElementById("onedrivePanel");
  if (!panel) return;

  panel.style.display = "flex";

  oneDriveHistory = [];

  loadOneDriveFiles();
}

export function closeOneDrive() {
  const panel = document.getElementById("onedrivePanel");
  if (panel) panel.style.display = "none";
}

/*
========================================
LOAD ROOT
========================================
*/
async function loadOneDriveFiles() {

  if (oneDriveCache["root"]) {
    renderOneDriveFiles(oneDriveCache["root"]);
    return;
  }

  const res = await safeFetch(
    "/onedrive/files?token_id=" + encodeURIComponent(state.tokenId)
  );

  const files = await res.json();

  oneDriveCache["root"] = files;

  renderOneDriveFiles(files);
}

/*
========================================
LOAD FOLDER
========================================
*/
async function loadOneDriveFolder(id, pushHistory = true) {

  const container = document.getElementById("onedriveFiles");
  if (!container) return;

  if (pushHistory) oneDriveHistory.push(id);

  if (oneDriveCache[id]) {
    renderOneDriveFiles(oneDriveCache[id]);
    return;
  }

  container.innerHTML = "Loading...";

  const res = await safeFetch(
    "/onedrive/folder/" +
    encodeURIComponent(id) +
    "?token_id=" +
    encodeURIComponent(state.tokenId)
  );

  const files = await res.json();

  oneDriveCache[id] = files;

  renderOneDriveFiles(files);
}

/*
========================================
BACK
========================================
*/
function goOneDriveBack() {

  oneDriveHistory.pop();

  if (!oneDriveHistory.length) {
    renderOneDriveFiles(oneDriveCache["root"]);
    return;
  }

  const last = oneDriveHistory[oneDriveHistory.length - 1];
  loadOneDriveFolder(last, false);
}

/*
========================================
RENDER
========================================
*/
function renderOneDriveFiles(files) {

  const container = document.getElementById("onedriveFiles");
  if (!container) return;

  container.innerHTML = "";

  /*
  =========================
  BACK BUTTON
  =========================
  */
  const back = document.createElement("div");
  back.className = "onedrive-file";

  back.innerHTML = `
    <i class="fa-solid fa-arrow-left"></i>
    <div>${oneDriveHistory.length ? "Back" : "Close"}</div>
  `;

  back.onclick = () => {
    if (oneDriveHistory.length) {
      goOneDriveBack();
    } else {
      closeOneDrive();
    }
  };

  container.appendChild(back);

  /*
  =========================
  FILE LIST
  =========================
  */
  const list = files?.value ?? files ?? [];

  if (!list.length) {
    const empty = document.createElement("div");
    empty.style.padding = "30px";
    empty.style.textAlign = "center";
    empty.innerText = "Nothing here";

    container.appendChild(empty);
    return;
  }

  /*
  =========================
  RENDER FILES
  =========================
  */
  list.forEach(file => {

    let icon = "fa-file";

    if (file.folder) {
      icon = "fa-folder";
    } else if (file.file?.mimeType?.includes("image")) {
      icon = "fa-file-image";
    } else if (file.file?.mimeType?.includes("pdf")) {
      icon = "fa-file-pdf";
    }

    const el = document.createElement("div");
    el.className = "onedrive-file";

    el.innerHTML = `
      <i class="fa-solid ${icon}"></i>
      <div>${file.name}</div>
    `;

    el.onclick = () => openOneDriveFile(file);

    container.appendChild(el);
  });
}

/*
========================================
OPEN FILE
========================================
*/
function openOneDriveFile(file) {

  if (file.folder) {
    loadOneDriveFolder(file.id);
    return;
  }

  const url = file["@microsoft.graph.downloadUrl"];
  if (url) {
    window.open(url);
  }
}