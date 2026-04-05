import { qs, qsa } from "../core/dom.js";
import { state } from "../core/state.js";

/* ======================
INIT
====================== */

let leadsMounted = false;

export function initLeads() {

    if (!qs(".leads-container")) return;

    // 🔥 prevent double bind
    if (leadsMounted) return;
    leadsMounted = true;

    bindSearch();
    bindRowClick();
}

/* ======================
SEARCH
====================== */

function bindSearch(){

    const input = qs(".leads-actions input");
    if(!input) return;

    input.addEventListener("input", e=>{
        const q = e.target.value.toLowerCase();

        qsa(".lead-row").forEach(row=>{
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(q) ? "flex" : "none";
        });
    });
}

/* ======================
CLICK ROW → PREVIEW
====================== */

function bindRowClick(){

    qsa(".lead-row").forEach(row=>{

        // 🔥 prevent duplicate bind
        if (row.dataset.bound === "1") return;
        row.dataset.bound = "1";

        row.addEventListener("click", ()=>{

            const name = qs(".lead-name", row)?.innerText || "";
            const email = qs(".lead-email", row)?.innerText || "";
            const source = qs(".lead-source", row)?.innerText || "";

            renderPreview({name,email,source});
        });

    });
}

/* ======================
PREVIEW PANEL
====================== */

function escapeHtml(str = "") {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function renderPreview(lead){

    const panel = qs(".leads-preview");
    if(!panel) return;

    const name = escapeHtml(lead.name || "(No Name)");
    const email = escapeHtml(lead.email || "");
    const source = escapeHtml(lead.source || "");

    panel.innerHTML = `
        <h3>${name}</h3>
        <p>${email}</p>

        <div style="margin-top:10px;color:#666;font-size:13px">
            Source: ${source}
        </div>

        <div style="margin-top:20px;display:flex;gap:10px">

            <button onclick="copyLead('${email}')">
                Copy Email
            </button>

            <button onclick="emailLead('${email}')">
                Send Email
            </button>

        </div>
    `;
}

/* ======================
ACTIONS
====================== */

export function copyLead(email){

    if (navigator.clipboard) {
        navigator.clipboard.writeText(email);
    } else {
        // fallback
        const input = document.createElement("input");
        input.value = email;
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
    }

    alert("Copied: " + email);
}

export function emailLead(email){

    // 🔥 FIX: multi-account support
    const token = state.tokenId || "";

    window.location.href =
        "/mail/compose?to=" +
        encodeURIComponent(email) +
        "&token_id=" +
        encodeURIComponent(token);
}