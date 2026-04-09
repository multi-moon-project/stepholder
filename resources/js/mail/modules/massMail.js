import { qs } from "../core/dom.js";
import { loadEditor } from "./compose.js";
import { undoManager } from "../core/undo.js";

let files = [];
let mode = "editor";
let currentCampaignId = null;
let evtSource = null;
let fallbackTimer = null;

/* =========================
INIT
========================= */
export async function initMassMail() {

  if (!qs("#mm-body")) return;

  console.log("🚀 INIT MASS MAIL");

  await loadEditor();

  initEditor();
  mountEvents();

  setTimeout(updatePreview, 200);
}

/* =========================
EDITOR
========================= */
function initEditor() {

  if (window.tinymce?.get("mm-body")) {
    tinymce.get("mm-body").remove();
  }

  tinymce.init({
    selector: "#mm-body",
    height: 320,
    menubar: false,
    plugins: ["link","image","table","lists","code"],
    toolbar:
      "undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | code",

    setup: (editor) => {
      editor.on("keyup change", updatePreview);
      editor.on("init", updatePreview);
    }
  });
}

/* =========================
MODE
========================= */
function switchMode(m) {

  mode = m;

  qs("#editorBox").style.display = m === "editor" ? "block" : "none";
  qs("#htmlBox").style.display = m === "html" ? "block" : "none";

  qs("#btn-editor")?.classList.toggle("active", m === "editor");
  qs("#btn-html")?.classList.toggle("active", m === "html");

  updatePreview();
}

/* =========================
BODY
========================= */
function getBody() {
  return mode === "editor"
    ? tinymce.get("mm-body")?.getContent() || ""
    : qs("#mm-html")?.value || "";
}

/* =========================
PREVIEW
========================= */
function updatePreview() {

  let html = getBody();

  if (!html) {
    return;
  }

  html = html.replace(/{{EMAIL}}/g, "example@mail.com");

  const iframe = qs("#mm-preview-frame");

  if (!iframe) return;

  const doc = iframe.contentDocument || iframe.contentWindow.document;

  doc.open();
  doc.write(html);
  doc.close();
}

/* =========================
FILES
========================= */
function mountFileInput() {

  document.addEventListener("change", (e) => {

    if (e.target.id !== "mm-files") return;

    files = [...files, ...Array.from(e.target.files)];

    renderFiles();
  });
}

function renderFiles() {

  const container = qs("#mm-file-list");
  if (!container) return;

  container.innerHTML = "";

  files.forEach((f, i) => {

    const el = document.createElement("div");

    el.innerHTML = `
      📎 ${f.name}
      <span class="remove-file" data-index="${i}">✕</span>
    `;

    container.appendChild(el);
  });
}

function removeFile(i) {
  files.splice(i, 1);
  renderFiles();
}

/* =========================
SEND
========================= */
async function startSend() {

  const btn = qs(".send-btn");

  if (!btn || btn.disabled) return;

  btn.disabled = true;
  btn.innerText = "Sending...";

  const leads = qs("#mm-leads")?.value
    .split("\n")
    .map(e => e.trim())
    .filter(Boolean);

  if (!leads.length) {
    undoManager.notify("Leads required ❌");
    resetButton();
    return;
  }

  const form = new FormData();

  form.append("leads", JSON.stringify(leads));
  form.append("subject", qs("#mm-subject")?.value || "");
  form.append("body", getBody());
  form.append("token_id", window.ACTIVE_TOKEN_ID);

  files.forEach(f => form.append("files[]", f));

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  try {

    const res = await fetch("/mass-mail/send", {
      method: "POST",
      headers: { "X-CSRF-TOKEN": csrf },
      body: form
    });

    const data = await res.json();

    if (!res.ok) {
      undoManager.notify(data.message || "Error ❌");
      resetButton();
      return;
    }

    currentCampaignId = data.campaign_id;

    undoManager.notify("Campaign started 🚀");

    // 🔥 SHOW PROGRESS (FIX BUG kamu)
    const wrap = qs("#mm-progress-wrap");
    if (wrap) wrap.style.display = "block";

    initProgressUI();

    // 🔥 CLOSE OLD SSE (ANTI DUPLICATE)
    if (evtSource) {
      evtSource.close();
    }

    listenProgress(currentCampaignId);

    // 🔥 fallback anti stuck
    fallbackTimer = setTimeout(() => {
      if (btn.disabled) {
        console.warn("⚠️ fallback reset");
        resetButton();
      }
    }, 20000);

  } catch (e) {
    console.error(e);
    undoManager.notify("Server error ❌");
    resetButton();
  }
}

function resetButton() {
  const btn = qs(".send-btn");
  if (!btn) return;

  btn.disabled = false;
  btn.innerText = "🚀 Send Mass Email";
}

/* =========================
PROGRESS UI
========================= */
function initProgressUI() {

  qs("#mm-progress-bar").style.width = "0%";
  qs("#mm-progress-info").innerText = "0 / 0";
}

function updateProgressUI(percent, sent, failed, total) {

  qs("#mm-progress-bar").style.width = percent + "%";
  qs("#mm-progress-info").innerText =
    `${sent + failed} / ${total} (${percent}%)`;
}

/* =========================
SSE
========================= */
function listenProgress(id) {

  evtSource = new EventSource(`/mass-mail/progress/${id}`);

  evtSource.onmessage = (e) => {

    const data = JSON.parse(e.data);

    const total = data.total || 1;
    const done = (data.sent || 0) + (data.failed || 0);
    const percent = Math.round(done / total * 100);

    updateProgressUI(percent, data.sent, data.failed, total);

    if (data.status === "paused") {
      undoManager.notify("Paused ⏸");
    }

    if (data.status === "cancelled") {
      evtSource.close();
      undoManager.notify("Cancelled ❌");
      resetButton();
    }

    if (data.status === "completed" || percent >= 100) {

      evtSource.close();

      undoManager.notify("Campaign completed 🎉");

      if (fallbackTimer) clearTimeout(fallbackTimer);

      resetButton();
    }
  };

  evtSource.onerror = () => {
    console.warn("⚠️ SSE disconnected");
    evtSource.close();
  };
}

/* =========================
CONTROL
========================= */
async function controlCampaign(action) {

  if (!currentCampaignId) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  await fetch(`/mass-mail/${currentCampaignId}/${action}`, {
    method: "POST",
    headers: { "X-CSRF-TOKEN": csrf }
  });

  undoManager.notify(`Campaign ${action} ✅`);
}

/* =========================
EVENTS (FIX DOUBLE BUG 🔥)
========================= */
function mountEvents() {

  qs("#mm-html")?.addEventListener("input", updatePreview);
  qs("#mm-leads")?.addEventListener("input", updatePreview);

  mountFileInput();

  document.addEventListener("click", (e) => {

    if (e.target.closest(".send-btn")) startSend();

    if (e.target.id === "btn-editor") switchMode("editor");
    if (e.target.id === "btn-html") switchMode("html");

    if (e.target.classList.contains("remove-file")) {
      removeFile(e.target.dataset.index);
    }

    // if (e.target.id === "mm-pause") controlCampaign("pause");
    // if (e.target.id === "mm-resume") controlCampaign("resume");
    // if (e.target.id === "mm-cancel") controlCampaign("cancel");

  });
}