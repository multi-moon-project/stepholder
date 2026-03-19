import { qs } from "../core/dom";
import { safeFetch, safeText } from "../core/api";
import { skeletonPreview } from "../ui/skeleton";
import { undoManager } from "../core/undo";

let editorInitialized = false;
let attachments = [];
let recipients = [];

export async function loadEditor() {
  if (window.tinymce) return;

  const script = document.createElement("script");
  script.src = "https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js";

  document.body.appendChild(script);

  await new Promise((resolve) => {
    script.onload = resolve;
  });
}

export function initEditor() {
  const existing = window.tinymce?.get("mailBody");

  if (editorInitialized && existing) {
    existing.setContent("");
    return;
  }

  window.tinymce.init({
    selector: "#mailBody",
    height: 350,
    menubar: true,
    plugins: ["link","image","table","lists","code","fullscreen","paste"],
    toolbar:
      "undo redo | fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | code fullscreen",
    content_style: "body{font-family:Segoe UI;font-size:14px}",
    setup: (editor) => {
      editor.on("init", () => {
        editorInitialized = true;
      });
    },
  });
}

export function renderAttachments() {
  const list = qs("#attachmentList");
  if (!list) return;

  list.innerHTML = "";

  attachments.forEach((file, index) => {
    const item = document.createElement("div");
    item.className = "attachment-item";

    if (file.type.startsWith("image")) {
      item.innerHTML = `
        <img src="${URL.createObjectURL(file)}" style="width:40px;height:40px;border-radius:4px">
        ${file.name}
        <span class="attachment-remove" onclick="removeAttachment(${index})">✕</span>
      `;
    } else {
      item.innerHTML = `
        📎 ${file.name}
        <span class="attachment-remove" onclick="removeAttachment(${index})">✕</span>
      `;
    }

    list.appendChild(item);
  });
}

export function removeAttachment(i) {
  attachments.splice(i, 1);
  renderAttachments();
}

export function mountAttachmentInput() {
  document.addEventListener("change", (e) => {
    if (e.target.id !== "fileInput") return;

    const file = e.target.files[0];
    if (!file) return;

    attachments.push(file);
    renderAttachments();
  });

  document.addEventListener("paste", (e) => {
    const items = e.clipboardData?.items || [];

    for (let i = 0; i < items.length; i++) {
      const item = items[i];

      if (item.type.includes("image")) {
        const file = item.getAsFile();
        attachments.push(file);
        renderAttachments();
      }
    }
  });
}

export function initDragAttachment() {
  const box = qs(".compose-box");
  if (!box) return;

  box.addEventListener("dragover", (e) => {
    e.preventDefault();
    box.classList.add("dragging");
  });

  box.addEventListener("dragleave", () => {
    box.classList.remove("dragging");
  });

  box.addEventListener("drop", (e) => {
    e.preventDefault();
    box.classList.remove("dragging");

    const files = e.dataTransfer.files;
    for (let i = 0; i < files.length; i++) {
      attachments.push(files[i]);
    }

    renderAttachments();
    undoManager.notify(files.length + " file attached");
  });
}

export function addRecipient(email) {
  if (recipients.includes(email)) return;

  recipients.push(email);

  const chip = document.createElement("div");
  chip.className = "recipient-chip";
  chip.innerHTML = `${email}<span class="recipient-remove">×</span>`;

  chip.querySelector(".recipient-remove").onclick = () => {
    chip.remove();
    recipients = recipients.filter((e) => e !== email);
  };

  qs("#toContainer")?.insertBefore(chip, qs("#mailToInput"));
}
export function initRecipientChips() {
  const input = qs("#mailToInput");
  if (!input) return;

  input.addEventListener("keydown", (e) => {
    if (["Enter", ",", "Tab"].includes(e.key)) {
      e.preventDefault();

      const email = input.value.trim().replace(",", "");
      if (email) addRecipient(email);

      input.value = "";
    }
  });
}
export function preloadRecipients() {
  const input = qs("#mailToInput");
  if (!input || !input.value) return;

  const emails = input.value;
  input.value = "";

  emails.split(",").forEach((e) => addRecipient(e.trim()));
}

export async function composeMail() {
  const preview = qs(".mail-preview");
  preview.innerHTML = skeletonPreview();

  await loadEditor();

  const html = await safeText("/mail/compose");
  preview.innerHTML = html;

  initEditor();
  initDragAttachment();
  initRecipientChips();
  preloadRecipients();
}
export async function sendMail() {
  const subject = qs("#mailSubject")?.value || "";
  const body = window.tinymce.get("mailBody").getContent();

  const form = new FormData();

  form.append("to", recipients.join(","));
  form.append("subject", subject);
  form.append("body", body);

  attachments.forEach((file) => {
    form.append("attachments[]", file);
  });

  await safeFetch("/mail/send", { method: "POST", body: form });

  undoManager.notify("Email sent");

  attachments = [];
  recipients = [];

  if (typeof window.loadFolder === "function") {
    window.loadFolder("inbox", "Inbox");
  }
}