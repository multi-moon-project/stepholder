import { state } from "../core/state";
import { safeJson, safeText } from "../core/api";
import { escapeHtml, formatMailDate, highlight } from "../core/utils";
import { skeletonList } from "../ui/skeleton";

export let cachedEmails = [];
export let allEmails = [];

export function setAllEmails(emails) {
  allEmails = Array.isArray(emails) ? emails : [];
}

export function setCachedEmails(emails) {
  cachedEmails = Array.isArray(emails) ? emails : [];
}

export function buildMailItemHtml(mail, keyword = "") {
  const time = formatMailDate(mail.receivedDateTime);
  const sender = escapeHtml(mail.from ?? "Unknown");
  const subject = highlight(escapeHtml(mail.subject ?? ""), keyword);

  let previewText = escapeHtml(mail.bodyPreview ?? "");
  if (previewText.length > 120) {
    previewText = previewText.substring(0, 120) + "...";
  }

  const preview = highlight(previewText, keyword);
  const letter = sender.charAt(0).toUpperCase();

  return `
    <div draggable="true"
         mail-id="${mail.id}"
         class="mail-item ${!mail.isRead ? "unread" : ""}"
         onclick="handleMailClick(event,this,'${mail.id}')">

      <input type="checkbox" class="mail-checkbox" onclick="event.stopPropagation()">

      <div class="mail-avatar">${letter}</div>

      <div class="mail-content">
        <div class="mail-header">
          <div class="mail-sender">${sender}</div>

          <div class="mail-right">
            ${mail.hasAttachments ? `
              <span class="mail-icon">
                <i class="fa-solid fa-paperclip"></i>
              </span>` : ""}

            <span class="mail-icon"
                  onclick="event.stopPropagation(); toggleFlag('${mail.id}')">
              <i class="${mail.flagged ? "fa-solid fa-flag" : "fa-regular fa-flag"}"></i>
            </span>

            ${mail.isRead ? `
              <span class="mail-action"
                    onclick="event.stopPropagation(); markUnread('${mail.id}')">
                <i class="fa-regular fa-envelope"></i>
              </span>
            ` : `
              <span class="mail-action"
                    onclick="event.stopPropagation(); markRead('${mail.id}')">
                <i class="fa-solid fa-envelope-open"></i>
              </span>
            `}

            <span class="mail-action delete"
                  onclick="event.stopPropagation(); deleteMail('${mail.id}')">
              <i class="fa-regular fa-trash-can"></i>
            </span>

            <span class="mail-time">${time}</span>
          </div>
        </div>

        <div class="mail-subject">${subject}</div>
        <div class="mail-preview-text">${preview}</div>
      </div>
    </div>
  `;
}

export function renderMailList(mails, keyword = "", append = false) {
  if (!state.mailListEl) return;

  const htmlAll = (mails || [])
    .map((mail) => buildMailItemHtml(mail, keyword))
    .join("");

  if (append) {
    state.mailListEl.insertAdjacentHTML("beforeend", htmlAll);
  } else {
    state.mailListEl.innerHTML = htmlAll;
  }
}

export async function loadMoreEmails() {
  console.log("🔥 LOAD MORE TRIGGERED");
  console.log("NEXT PAGE:", state.nextPage);

  if (!state.nextPage || state.loadingMore) return;

  state.loadingMore = true;

  // 🔥 SHOW LOADER
  showBottomLoader();

  try {
    if (state.searchMode) {
      const data = await safeJson(
        "/api/search?q=" +
          encodeURIComponent(state.searchQuery) +
          "&next=" +
          encodeURIComponent(state.nextPage)
      );

      if (data) {
        renderMailList(data.emails, state.searchQuery, true);
        state.nextPage = data.next;
      }

      return;
    }

    let url;

   if (state.currentFolder?.toLowerCase().includes("inbox")) {
  url = "/inbox?next=" + encodeURIComponent(state.nextPage);
} else {
  url =
    "/folder/" +
    state.currentFolderId + // ✅ FIX
    "?next=" +
    encodeURIComponent(state.nextPage);
}
    const html = await safeText(url);
    if (!html) return;

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");

    const newItems = doc.querySelectorAll(".mail-item");

    newItems.forEach((item) => {
      state.mailListEl.appendChild(item);
    });

    const next = doc.querySelector("#nextPageLink");
    state.nextPage = next ? next.dataset.next : null;
  } catch (e) {
    console.error("Load more error:", e);
  } finally {
    state.loadingMore = false;

    // 🔥 HIDE LOADER
    hideBottomLoader();
  }
}

export function mountMailListScroll() {
  if (!state.mailListEl) return;

  state.mailListEl.addEventListener("scroll", function () {
    clearTimeout(state.scrollTimer);

    state.scrollTimer = setTimeout(() => {
      const nearBottom =
        this.scrollTop + this.clientHeight >= this.scrollHeight - 100;

      if (nearBottom && !state.loadingMore) {
        loadMoreEmails();
      }
    }, 120);
  });
}

export function showMailListSkeleton() {
  if (!state.mailListEl) return;
  state.mailListEl.innerHTML = skeletonList();
}

function showBottomLoader() {
  if (!state.mailListEl) return;

  // cegah duplicate
  if (state.mailListEl.querySelector(".mail-loader")) return;

  state.mailListEl.insertAdjacentHTML(
    "beforeend",
    `
    <div class="mail-loader">
      <div class="spinner"></div>
      <div class="loader-text">Loading more emails...</div>
    </div>
    `
  );
}

function hideBottomLoader() {
  const loader = state.mailListEl?.querySelector(".mail-loader");
  if (loader) loader.remove();
}