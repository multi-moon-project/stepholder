import { qs } from "../core/dom";
import { safeFetch, safeText } from "../core/api";
import { skeletonPreview } from "../ui/skeleton";
import { undoManager } from "../core/undo";
import { loadFolder } from "./folder.js";

let attachments = [];

// STATE
let recipients = [];
let ccRecipients = [];
let bccRecipients = [];

/* =========================
   EDITOR LOADER
========================= */
export async function loadEditor() {
  if (window.tinymce) {
    console.log("✅ TinyMCE already loaded");
    return;
  }

  console.log("🚀 Loading TinyMCE...");

  const script = document.createElement("script");
  script.src = "https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js";

  document.body.appendChild(script);

  await new Promise((resolve) => {
    script.onload = () => {
      console.log("✅ TinyMCE loaded");
      resolve();
    };
  });
}

/* =========================
   INIT EDITOR (FIX FINAL)
========================= */
export function initEditor() {

  console.log("🔥 INIT EDITOR START");

  // 🔥 WAJIB: destroy semua instance lama
  if (window.tinymce) {
    console.log("🧹 Destroy old editors");
    if (window.tinymce.get("mailBody")) {
  window.tinymce.get("mailBody").remove();
}
  }

  const textarea = document.querySelector("#mailBody");

  if (!textarea) {
    console.error("❌ #mailBody NOT FOUND IN DOM");
    return;
  }

  console.log("✅ textarea found");

  const body = window.composeBody || "";

  console.log("📦 composeBody =", body);

  window.tinymce.init({
    selector: "#mailBody",
    init_instance_callback: (editor) => {
  console.log("🔥 FINAL FORCE CONTENT");

  const body = window.composeBody || "";

  editor.setContent(body);
},
    height: 350,
    menubar: true,
    plugins: ["link","image","table","lists","code","fullscreen"],
    toolbar:
      "undo redo | fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | code fullscreen",
    content_style: "body{font-family:Segoe UI;font-size:14px}",

    setup: (editor) => {

      editor.on("init", () => {

        console.log("✅ TinyMCE INIT EVENT");

        // 🔥 SET CONTENT
        editor.setContent(body);

        console.log("✅ Content injected (1x)");

        // 🔥 BACKUP INJECTION (ANTI BUG)
        setTimeout(() => {
          editor.setContent(body);
          console.log("🔁 Content injected (50ms)");
        }, 50);

        setTimeout(() => {
          editor.setContent(body);
          console.log("🔁 Content injected (200ms)");
        }, 200);

      });

    },
  });
}

/* =========================
   ATTACHMENT
========================= */
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

let attachmentMounted = false;

export function mountAttachmentInput() {
  if (attachmentMounted) return;
  attachmentMounted = true;

  document.addEventListener("change", (e) => {
    if (e.target.id !== "fileInput") return;

    const file = e.target.files[0];
    if (!file) return;

    attachments.push(file);
    renderAttachments();
  });
}

/* =========================
   RECIPIENT CHIP
========================= */

function getState(type) {
  if (type === "to") return recipients;
  if (type === "cc") return ccRecipients;
  if (type === "bcc") return bccRecipients;
}

function getContainer(type) {
  if (type === "to") return qs("#toContainer");
  if (type === "cc") return qs("#ccContainer");
  if (type === "bcc") return qs("#bccContainer");
}

function getInput(type) {
  if (type === "to") return qs("#mailToInput");
  if (type === "cc") return qs("#mailCcInput");
  if (type === "bcc") return qs("#mailBccInput");
}

function addRecipientTo(type, email) {
  const list = getState(type);
  const container = getContainer(type);
  const input = getInput(type);

  if (!list || list.includes(email)) return;

  list.push(email);

  const chip = document.createElement("div");
  chip.className = "recipient-chip";
  chip.dataset.email = email;

  chip.innerHTML = `${email}<span class="recipient-remove">×</span>`;

  chip.querySelector(".recipient-remove").onclick = () => {
    chip.remove();
    const idx = list.indexOf(email);
    if (idx > -1) list.splice(idx, 1);
  };

  container?.insertBefore(chip, input);
}

function initRecipientInput(selector, type) {
  const input = qs(selector);
  if (!input) return;

  input.addEventListener("keydown", (e) => {
    if (["Enter", ",", "Tab"].includes(e.key)) {
      e.preventDefault();

      const email = input.value.trim().replace(",", "");
      if (email && isValidEmail(email)) {
        addRecipientTo(type, email);
      }

      input.value = "";
    }
  });
}

export function initRecipientChips() {
  initRecipientInput("#mailToInput", "to");
  initRecipientInput("#mailCcInput", "cc");
  initRecipientInput("#mailBccInput", "bcc");
}

/* =========================
   COMPOSE
========================= */
export async function composeMail() {

  console.log("📨 OPEN COMPOSE");

  const preview = qs(".mail-preview");
  preview.innerHTML = skeletonPreview();

  recipients = [];
  ccRecipients = [];
  bccRecipients = [];
  attachments = [];

  await loadEditor();

  const html = await safeText("/mail/compose");

  console.log("📦 COMPOSE HTML LOADED");

  preview.innerHTML = html;

  setTimeout(() => {
    console.log("🚀 INIT COMPOSE MODULES");

    initEditor();
    initRecipientChips();
    mountAttachmentInput();

  }, 50);
}

/* =========================
   SEND
========================= */
export async function sendMail() {
  try {
    console.log("📤 SEND MAIL");

    const form = new FormData();

    ["to", "cc", "bcc"].forEach(type => {
      const input = getInput(type);
      if (input && input.value.trim()) {
        addRecipientTo(type, input.value.trim());
        input.value = "";
      }
    });

    const to = recipients.join(",");
    const cc = ccRecipients.join(",");
    const bcc = bccRecipients.join(",");

    console.log("📨 TO:", to);

    if (!to.trim()) {
      undoManager.notify("Recipient required ❌");
      return;
    }

    form.append("to", to);
    form.append("cc", cc);
    form.append("bcc", bcc);

    form.append("subject", qs("#mailSubject")?.value || "");

    const editor = tinymce.get("mailBody");

    const content = editor ? editor.getContent() : "";

    console.log("📝 BODY:", content);

    form.append("body", content);

    attachments.forEach(file => {
      form.append("attachments[]", file);
    });

    await safeFetch("/mail/send", {
      method: "POST",
      body: form,
    });

    undoManager.notify("Email sent ✅");

    recipients = [];
    ccRecipients = [];
    bccRecipients = [];
    attachments = [];

    loadFolder("inbox");

    qs(".mail-preview").innerHTML = `
      <div class="empty-preview">
        📧<br>Select an email to read
      </div>
    `;

  } catch (e) {
    console.error("❌ SEND ERROR:", e);
    undoManager.notify("Failed to send ❌");
  }
}

/* =========================
   UTIL
========================= */
function isValidEmail(email) {
  return /\S+@\S+\.\S+/.test(email);
}

export function toggleCc() {
  const row = qs("#ccRow");
  if (!row) return;
  row.style.display = row.style.display === "none" ? "flex" : "none";
}

export function toggleBcc() {
  const row = qs("#bccRow");
  if (!row) return;
  row.style.display = row.style.display === "none" ? "flex" : "none";
}