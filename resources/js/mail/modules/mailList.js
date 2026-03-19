import { state } from "../core/state";
import { qs } from "../core/dom";
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

  const htmlAll = (mails || []).map((mail) => buildMailItemHtml(mail, keyword)).join("");

  if (append) {
    state.mailListEl.insertAdjacentHTML("beforeend", htmlAll);
  } else {
    state.mailListEl.innerHTML = htmlAll;
  }
}

export async function loadMoreEmails() {
  if (!state.nextPage || state.loadingMore) return;

  state.loadingMore = true;

  try {
    if (state.searchMode) {
      const data = await safeJson(
        "/api/search?q=" + encodeURIComponent(state.searchQuery) +
        "&next=" + encodeURIComponent(state.nextPage)
      );

      if (data) {
        renderMailList(data.emails, state.searchQuery, true);
        state.nextPage = data.next;
      }

      return;
    }

    const html = await safeText("/inbox?next=" + encodeURIComponent(state.nextPage));
    if (!html) return;

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");
    const newItems = doc.querySelectorAll(".mail-item");

    newItems.forEach((item) => {
      state.mailListEl.appendChild(item);
    });

    const next = doc.querySelector("#nextPageLink");
    state.nextPage = next ? next.dataset.next : null;
  } finally {
    state.loadingMore = false;
  }
}
export function mountMailListScroll() {
  if (!state.mailListEl) return;

  state.mailListEl.addEventListener("scroll", function onMailListScroll() {
    clearTimeout(state.scrollTimer);

    state.scrollTimer = setTimeout(() => {
      if (this.scrollTop + this.clientHeight >= this.scrollHeight - 100) {
        if (!state.loadingMore) {
          loadMoreEmails();
        }
      }
    }, 120);
  });
}

export function showMailListSkeleton() {
  if (!state.mailListEl) return;
  state.mailListEl.innerHTML = skeletonList();
}