import { qs, qsa } from "../core/dom.js";
import { state } from "../core/state.js";

/* ======================
INIT
====================== */

export function initLeads() {

    if (!qs(".leads-container")) return; // auto detect page

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

function renderPreview(lead){

    const panel = qs(".leads-preview");

    if(!panel) return;

    panel.innerHTML = `
        <h3>${lead.name || '(No Name)'}</h3>
        <p>${lead.email}</p>

        <div style="margin-top:10px;color:#666;font-size:13px">
            Source: ${lead.source}
        </div>

        <div style="margin-top:20px;display:flex;gap:10px">

            <button onclick="copyLead('${lead.email}')">
                Copy Email
            </button>

            <button onclick="emailLead('${lead.email}')">
                Send Email
            </button>

        </div>
    `;
}

/* ======================
ACTIONS
====================== */

export function copyLead(email){
    navigator.clipboard.writeText(email);
    alert("Copied: " + email);
}

export function emailLead(email){
    window.location.href = "/mail/compose?to=" + email;
}