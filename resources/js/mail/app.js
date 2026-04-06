// file app.js

import { initLeads, handleExtract, downloadNext, startDownload } from "./modules/leads.js";
import { openOneDrive, closeOneDrive } from "./modules/onedrive.js";
import { closeSettings, openSettings, loadRules, loadRulesToState, createRule, newRule, deleteRule, selectRule } from './modules/rules.js';
import { state } from "./core/state.js";
import { qs, qsa } from "./core/dom.js";
import { initRealtime } from './modules/realtime.js';

import { mountMailListScroll, loadMoreEmails, renderMailList } from "./modules/mailList.js";
import { addSearch, instantFilter, liveSearch, mountSearch } from "./modules/search.js";
import { mountFolderDrop } from './modules/folderAction.js';

import {
  openMail,
  markRead,
  markUnread,
  openThread,
  replyMail,
  replyAllMail,
  forwardMail,
} from "./modules/mailPreview.js";

import {
  openAttachmentViewer,
  nextAttachment,
  prevAttachment,
  closeAttachmentViewer,
  mountAttachmentPreviewHotkeys
} from "./modules/attachmentPreview.js";

import {
  createFolder,
  deleteFolder,
  menuCreate,
  menuDelete,
  menuRename
} from "./modules/folderCrud";

import {
  selectMail,
  selectItem,
  toggleItem,
  handleMailClick,
  updateBulkUI,
  clearSelection,
  getSelectedEmails,
  mountSelection,
  mountKeyboardSelection,
  mountDragAndDrop
} from "./modules/selection.js";

import {
  deleteMail,
  deleteSelected,
  archiveSelected,
  markReadSelected,
  recoverSelected,
  toggleFlag,
} from "./modules/mailActions.js";

import {
  composeMail,
  sendMail,
  removeAttachment,
  toggleCc,
  toggleBcc,
  initEditor,
  initRecipientChips,
  mountAttachmentInput,
  loadEditor,
  addRecipientDirect
} from "./modules/compose.js";

import { undoManager } from './core/undo.js';
import { loadFolder } from "./modules/folder.js";

/* ======================
EDITOR GLOBAL
====================== */
window.initEditor = initEditor;
window.initRecipientChips = initRecipientChips;
window.mountAttachmentInput = mountAttachmentInput;
window.loadEditor = loadEditor;

/* ======================
COMPOSE GLOBAL
====================== */
window.composeMail = composeMail;
window.sendMail = sendMail;
window.removeAttachment = removeAttachment;
window.toggleCc = toggleCc;
window.toggleBcc = toggleBcc;

/* ======================
FOLDER ICONS
====================== */
function initFolderIcons() {
  qsa(".folder").forEach((folder) => {

    const name = (folder.dataset.name || "").toLowerCase();
    const icon = folder.querySelector(".folder-icon");
    if (!icon) return;

    if (name.includes("inbox")) icon.className = "folder-icon fa-solid fa-inbox";
    else if (name.includes("draft")) icon.className = "folder-icon fa-solid fa-pen-to-square";
    else if (name.includes("sent")) icon.className = "folder-icon fa-solid fa-paper-plane";
    else if (name.includes("deleted")) icon.className = "folder-icon fa-solid fa-trash";
    else if (name.includes("archive")) icon.className = "folder-icon fa-solid fa-box-archive";
    else if (name.includes("junk") || name.includes("spam")) icon.className = "folder-icon fa-solid fa-shield-halved";
    else icon.className = "folder-icon fa-regular fa-folder";

    state.folderMap.set(folder.dataset.id, folder);

    if (name.includes("inbox")) {
      state.inboxFolderId = folder.dataset.id;
    }
  });
}

/* ======================
ACCOUNT MENU
====================== */
function bindAccountMenu() {
  document.addEventListener("click", (e) => {
    const menu = qs("#accountMenu");
    const box = qs(".account-box");
    if (!menu || !box) return;

    if (!box.contains(e.target)) {
      menu.style.display = "none";
    }
  });
}

/* ======================
GLOBAL EXPOSURE
====================== */
function exposeLegacyGlobals() {

  window.__MailAppState = state;

  window.toggleAccountMenu = function () {
    const menu = qs("#accountMenu");
    if (!menu) return;
    menu.style.display = menu.style.display === "block" ? "none" : "block";
  };

  window.switchAccount = function (id) {
    window.location = "/switch-account/" + id;
  };

  /* ===== MAIL ===== */
  window.openMail = openMail;
  window.markRead = markRead;
  window.markUnread = markUnread;
  window.openThread = openThread;
  window.replyMail = replyMail;
  window.replyAllMail = replyAllMail;
  window.forwardMail = forwardMail;

  /* ===== SEARCH ===== */
  window.liveSearch = liveSearch;
  window.instantFilter = instantFilter;
  window.addSearch = addSearch;

  /* ===== LIST ===== */
  window.loadMoreEmails = loadMoreEmails;
  window.renderMailList = renderMailList;

  /* ===== ACTIONS ===== */
  window.deleteMail = deleteMail;
  window.deleteSelected = deleteSelected;
  window.archiveSelected = archiveSelected;
  window.markReadSelected = markReadSelected;
  window.recoverSelected = recoverSelected;
  window.toggleFlag = toggleFlag;

  /* ===== SELECTION ===== */
  window.selectMail = selectMail;
  window.selectItem = selectItem;
  window.toggleItem = toggleItem;
  window.handleMailClick = handleMailClick;
  window.updateBulkUI = updateBulkUI;
  window.clearSelection = clearSelection;
  window.getSelectedEmails = getSelectedEmails;

  /* ===== SETTINGS ===== */
  window.openSettings = openSettings;
  window.closeSettings = closeSettings;
  window.loadRules = loadRules;
  window.createRule = createRule;
  window.newRule = newRule;
  window.deleteRule = deleteRule;
  window.selectRule = selectRule;

  /* ===== FOLDER ===== */
  window.loadFolder = loadFolder;
  window.createFolder = createFolder;
  window.deleteFolder = deleteFolder;
  window.menuCreate = menuCreate;
  window.menuDelete = menuDelete;
  window.menuRename = menuRename;

  /* ===== ATTACHMENT ===== */
  window.openAttachmentViewer = openAttachmentViewer;
  window.nextAttachment = nextAttachment;
  window.prevAttachment = prevAttachment;
  window.closeAttachmentViewer = closeAttachmentViewer;

  /* ===== ONEDRIVE ===== */
  window.openOneDrive = openOneDrive;
  window.closeOneDrive = closeOneDrive;

  /* ===== LEADS ===== */
window.handleExtract = handleExtract;
window.startDownload = startDownload;
window.downloadNext = downloadNext;

  /* ===== UTIL ===== */
  window.undoManager = undoManager;
  // window.checkNewMail = checkNewMail;
  window.addRecipientDirect = addRecipientDirect;

  /* ======================
  🔥 FIXED REFRESH (FINAL)
  ====================== */
  window.refreshCurrentFolder = function () {

    const active = document.querySelector(".folder.active");
    if (!active) {
      console.warn("❌ No active folder");
      return;
    }

    const folderId = active.dataset.id;
    const name = active.innerText?.trim() || "";

    console.log("🔄 FORCE REFRESH:", folderId);

    // 🔥 mark dirty
    if (!window.__dirtyFolders) {
      window.__dirtyFolders = new Set();
    }

    const key = `${state.tokenId}_${folderId}`;
    window.__dirtyFolders.add(key);

    // 🔥 reset pagination
    state.nextPage = null;
    window.__MAIL_NEXT_PAGE__ = null;

    // 🔥 loading UI
    if (state.mailListEl) {
      state.mailListEl.innerHTML = `
        <div style="padding:20px;text-align:center">
          🔄 Refreshing...
        </div>
      `;
    }

    // 🔥 force load
    window.loadFolder(folderId, name, active, { force: true });
  };
}

/* ======================
INIT CORE
====================== */
export async function initMailAppCore() {

  state.tokenId = window.ACTIVE_TOKEN_ID;

  if (!state.tokenId) {
    console.error("❌ TOKEN ID MISSING");
    alert("Token Not Found!. Back to Panel and Open Box Again!");
    return;
  }

  console.log("ACTIVE TOKEN:", state.tokenId);

  state.nextPage = window.__MAIL_NEXT_PAGE__ ?? state.nextPage;
  state.mailListEl = qs(".mail-list");

  initFolderIcons();
  bindAccountMenu();

  await loadRulesToState();

  mountMailListScroll();
  mountSearch();
  mountAttachmentPreviewHotkeys();
  mountSelection();
  mountKeyboardSelection();
  mountDragAndDrop();
  mountFolderDrop();
  initLeads();
  initRealtime();

  exposeLegacyGlobals();
}

/* ======================
BOOT
====================== */
document.addEventListener("DOMContentLoaded", initMailAppCore);