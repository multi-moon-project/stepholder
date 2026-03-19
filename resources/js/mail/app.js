import { state } from "./core/state";
import { qs, qsa } from "./core/dom";
import { mountMailListScroll, loadMoreEmails, renderMailList } from "./modules/mailList";
import { addSearch, instantFilter, liveSearch, mountSearch } from "./modules/search";
import {
  openMail,
  markRead,
  markUnread,
  openThread,
} from "./modules/mailPreview";
import {
  previewAttachment,
  closeAttachmentPreview,
  mountAttachmentPreviewHotkeys,
} from "./modules/attachmentPreview";
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
} from "./modules/selection";

import {
  deleteMail,
  deleteSelected,
  archiveSelected,
  markReadSelected,
  recoverSelected,
  toggleFlag,
} from "./modules/mailActions";

import {
  composeMail,
  sendMail,
  removeAttachment,
} from "./modules/compose";

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
  window.previewAttachment = previewAttachment;
  window.closeAttachmentPreview = closeAttachmentPreview;

  window.deleteMail = deleteMail;
window.deleteSelected = deleteSelected;
window.archiveSelected = archiveSelected;
window.markReadSelected = markReadSelected;
window.recoverSelected = recoverSelected;
window.toggleFlag = toggleFlag;
window.loadFolder = loadFolder;

window.composeMail = composeMail;
window.sendMail = sendMail;
window.removeAttachment = removeAttachment;

  window.selectMail = selectMail;
  window.selectItem = selectItem;
  window.toggleItem = toggleItem;
  window.handleMailClick = handleMailClick;
  window.updateBulkUI = updateBulkUI;
  window.clearSelection = clearSelection;
  window.getSelectedEmails = getSelectedEmails;
}
export function initMailAppCore() {
  state.mailListEl = qs(".mail-list");
  initFolderIcons();
  bindAccountMenu();
  mountMailListScroll();
  mountSearch();
  mountAttachmentPreviewHotkeys();
  mountSelection();
  mountKeyboardSelection();
  exposeLegacyGlobals();
}

document.addEventListener("DOMContentLoaded", initMailAppCore);