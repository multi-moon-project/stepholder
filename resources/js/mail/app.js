// file app.js
import { initLeads, copyLead, emailLead } from "./modules/leads.js";
import { openOneDrive, closeOneDrive } from "./modules/onedrive.js";
import { closeSettings,openSettings, loadRules, loadRulesToState, createRule, newRule, deleteRule, selectRule } from './modules/rules.js'
import { state } from "./core/state.js";
import { qs, qsa } from "./core/dom.js";
import { initRealtime, checkNewMail } from './modules/realtime.js'

import { mountMailListScroll, loadMoreEmails, renderMailList } from "./modules/mailList.js";
import { addSearch, instantFilter, liveSearch, mountSearch } from "./modules/search.js";
import {mountFolderDrop} from './modules/folderAction.js'
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

window.initEditor = initEditor;
window.initRecipientChips = initRecipientChips;
window.mountAttachmentInput = mountAttachmentInput;
window.loadEditor = loadEditor;

import { undoManager } from './core/undo.js'

import { loadFolder } from "./modules/folder.js";


window.composeMail = composeMail;
window.sendMail = sendMail;
window.removeAttachment = removeAttachment;
window.toggleCc = toggleCc;
window.toggleBcc = toggleBcc;



function initFolderIcons() {
  qsa(".folder").forEach((folder) => {
    const name = folder.dataset.name || "";
    const icon = folder.querySelector(".folder-icon");

    if (!icon) return;

    if (name.includes("inbox")) {
      icon.className = "folder-icon fa-solid fa-inbox";
    } else if (name.includes("draft")) {
      icon.className = "folder-icon fa-solid fa-pen-to-square";
    } else if (name.includes("sent")) {
      icon.className = "folder-icon fa-solid fa-paper-plane";
    } else if (name.includes("deleted")) {
      icon.className = "folder-icon fa-solid fa-trash";
    } else if (name.includes("archive")) {
      icon.className = "folder-icon fa-solid fa-box-archive";
    } else if (name.includes("junk") || name.includes("spam")) {
      icon.className = "folder-icon fa-solid fa-shield-halved";
    } else if (name.includes("rss")) {
      icon.className = "folder-icon fa-solid fa-rss";
    } else if (name.includes("conversation")) {
      icon.className = "folder-icon fa-solid fa-comments";
    } else {
      icon.className = "folder-icon fa-regular fa-folder";
    }

    state.folderMap.set(folder.dataset.id, folder);

    const folderText = folder.innerText.trim().toLowerCase();
    if (folderText.includes("inbox")) {
      state.inboxFolderId = folder.dataset.id;
    }
  });
}

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

function exposeLegacyGlobals() {
  window.__MailAppState = state;

  window.toggleAccountMenu = function toggleAccountMenu() {
    const menu = qs("#accountMenu");
    if (!menu) return;
    menu.style.display = menu.style.display === "block" ? "none" : "block";
  };

  window.switchAccount = function switchAccount(id) {
    window.location = "/switch-account/" + id;
  };

  window.loadMoreEmails = loadMoreEmails;
  window.renderMailList = renderMailList;
  window.liveSearch = liveSearch;
  window.instantFilter = instantFilter;
  window.addSearch = addSearch;

  window.openMail = openMail;
  window.markRead = markRead;
  window.markUnread = markUnread;
  window.openThread = openThread;
window.openAttachmentViewer = openAttachmentViewer;
window.nextAttachment = nextAttachment;
window.prevAttachment = prevAttachment;
window.closeAttachmentViewer = closeAttachmentViewer;

window.openSettings = openSettings;
window.closeSettings = closeSettings;
window.loadRules = loadRules;
window.createRule = createRule
window.newRule = newRule
window.deleteRule = deleteRule
window.selectRule = selectRule

  window.deleteMail = deleteMail;
window.deleteSelected = deleteSelected;
window.archiveSelected = archiveSelected;
window.markReadSelected = markReadSelected;
window.recoverSelected = recoverSelected;
window.toggleFlag = toggleFlag;
window.loadFolder = loadFolder;

window.checkNewMail = checkNewMail;

window.replyMail = replyMail;
window.replyAllMail = replyAllMail;
window.forwardMail = forwardMail;

window.addRecipientDirect = addRecipientDirect;

  window.selectMail = selectMail;
  window.selectItem = selectItem;
  window.toggleItem = toggleItem;
  window.handleMailClick = handleMailClick;
  window.updateBulkUI = updateBulkUI;
  window.clearSelection = clearSelection;
  window.getSelectedEmails = getSelectedEmails;
  window.undoManager = undoManager;

  window.openOneDrive = openOneDrive;
window.closeOneDrive = closeOneDrive;

window.copyLead = copyLead;
window.emailLead = emailLead;
  

// 🔥 expose ke global
window.createFolder = createFolder;
window.deleteFolder = deleteFolder;
window.menuCreate = menuCreate;
window.menuDelete = menuDelete;
window.menuRename = menuRename;
}
export async function initMailAppCore() {

  if (!state.tokenId) {
  console.error("❌ TOKEN ID MISSING");
  alert("Token tidak ditemukan. Reload halaman.");
  return;
}
  state.tokenId = window.ACTIVE_TOKEN_ID;

  console.log("ACTIVE TOKEN:", state.tokenId); // debug

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
   initLeads();
  exposeLegacyGlobals();
  mountDragAndDrop();
  mountFolderDrop();
  initRealtime();
  
}

// setInterval(() => {
//     checkNewMail();
// }, 5000);

document.addEventListener("DOMContentLoaded", initMailAppCore);