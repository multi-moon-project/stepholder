import { state } from "../core/state";
import { qs } from "../core/dom";

export function previewAttachment(messageId, attachmentId, type = "") {
  const url = `/mail/attachment/${messageId}/${attachmentId}`;
  const preview = qs(".mail-preview");
  if (!preview) return;

  if (!state.lastEmailPreview) {
    state.lastEmailPreview = preview.innerHTML;
  }

  preview.innerHTML = `
    <div style="height:100%;display:flex;flex-direction:column">
      <div style="
        padding:10px;
        border-bottom:1px solid #eee;
        display:flex;
        gap:10px;
        align-items:center;
        background:#f8f8f8
      ">
        <button onclick="closeAttachmentPreview()">← Back</button>
        <button onclick="window.open('${url}')">Open</button>
        <button onclick="window.location='${url}'">Download</button>
        <div style="margin-left:auto;font-size:20px;cursor:pointer"
             onclick="closeAttachmentPreview()">✕</div>
      </div>

      <div style="flex:1">
        <iframe src="${url}" style="width:100%;height:100%;border:none"></iframe>
      </div>
    </div>
  `;
}
export function closeAttachmentPreview() {
  const preview = qs(".mail-preview");
  if (!preview) return;

  if (state.lastEmailPreview) {
    preview.innerHTML = state.lastEmailPreview;
    state.lastEmailPreview = null;
  }
}

export function mountAttachmentPreviewHotkeys() {
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeAttachmentPreview();
    }
  });
}