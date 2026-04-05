import { state } from "../core/state";
import { safeFetch } from "../core/api";

/* ======================
HELPER: EXTRACT EMAIL
====================== */
function extractEmail(raw) {
  if (!raw) return "";

  if (typeof raw === "object") {
    const email = raw.emailAddress?.address || raw.address || "";
    return String(email).toLowerCase().trim();
  }

  if (typeof raw === "string") {
    const str = raw.toLowerCase().trim();

    const bracketMatch = str.match(/<\s*([^>]+?)\s*>/);
    if (bracketMatch && bracketMatch[1]) {
      return bracketMatch[1].trim();
    }

    const emailMatch = str.match(/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i);
    if (emailMatch && emailMatch[0]) {
      return emailMatch[0].toLowerCase().trim();
    }

    return "";
  }

  return "";
}

/* ======================
HELPER: EXTRACT NAME
====================== */
function extractName(raw) {
  if (!raw) return "";

  if (typeof raw === "object") {
    const name = raw.emailAddress?.name || raw.name || "";
    return String(name).toLowerCase().trim();
  }

  if (typeof raw === "string") {
    return raw
      .toLowerCase()
      .replace(/<\s*[^>]+?\s*>/g, "")
      .replace(/[<>"']/g, "")
      .trim();
  }

  return "";
}

/* ======================
DEBUG (TIDAK DIUBAH)
====================== */
function debugRuleMail(mail, rules, normalized) {
  console.group("RULE ENGINE DEBUG");
  console.log("MAIL RAW:", mail);
  console.log("NORMALIZED:", normalized);
  console.log("RULES:", rules);
  console.groupEnd();
}

function debugRuleCheck(rule, debugData) {
  console.group(`RULE CHECK [${rule.id ?? "no-id"}]`);
  console.log(debugData);
  console.groupEnd();
}

/* ======================
SETTINGS UI
====================== */
export function openSettings() {
  const el = document.getElementById("settingsOverlay");
  if (!el) return;

  el.style.display = "flex";

  loadRules();
  loadRulesToState();
}

export function closeSettings() {
  const el = document.getElementById("settingsOverlay");
  if (!el) return;

  el.style.display = "none";
}

/* ======================
LOAD RULES (HTML)
====================== */
export async function loadRules() {
  const el = document.getElementById("settingsContent");
  if (!el) return;

  el.innerHTML = "Loading...";

  try {
    const res = await safeFetch(
      "/settings/rules?token_id=" + encodeURIComponent(state.tokenId)
    );

    const html = await res.text();
    el.innerHTML = html;
  } catch (e) {
    el.innerHTML = "Failed to load rules";
    console.error(e);
  }
}

/* ======================
LOAD RULES TO STATE
====================== */
export async function loadRulesToState() {
  try {
    const res = await safeFetch(
      "/rules/json?token_id=" + encodeURIComponent(state.tokenId)
    );

    const data = await res.json();

    state.rules = (data.rules || []).map(r => ({
      id: r.id,
      conditionType: r.condition_type,
      conditionValue: r.condition_value,
      delete: !!r.action_delete,
      read: !!r.action_read,
      folder: r.action_folder
    }));

  } catch (e) {
    console.error("Failed load rules", e);
    state.rules = [];
  }
}

/* ======================
CREATE / UPDATE RULE
====================== */
export async function createRule() {

  const id = document.getElementById("editingRuleId")?.value;

  const payload = {
    displayName: document.getElementById("ruleName")?.value,
    conditionType: document.getElementById("conditionType")?.value,
    conditionValue: document.getElementById("conditionValue")?.value,
    delete: document.getElementById("ruleDelete")?.checked,
    read: document.getElementById("ruleRead")?.checked,
    folder: document.getElementById("ruleFolder")?.value
  };

  const url = id
    ? `/settings/rules/${id}`
    : `/settings/rules`;

  const method = id ? "PUT" : "POST";

  await safeFetch(
    url + "?token_id=" + encodeURIComponent(state.tokenId),
    {
      method,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    }
  );

  loadRules();
  loadRulesToState();
}

/* ======================
DELETE RULE
====================== */
export async function deleteRule(id) {
  if (!confirm("Delete this rule?")) return;

  try {
    await safeFetch(
      `/settings/rules/${id}?token_id=${state.tokenId}`,
      { method: "DELETE" }
    );

    await loadRules();
    await loadRulesToState();

  } catch (e) {
    console.error("Delete rule failed", e);
  }
}

/* ======================
RULE ENGINE (CORE)
====================== */
export function applyRules(mail, rules = []) {

  let actions = {
    delete: false,
    read: false,
    moveTo: null
  };

  if (!rules.length) return actions;

  const fromEmail = extractEmail(mail?.from);
  const fromName = extractName(mail?.from);
  const subject = String(mail?.subject || "").toLowerCase().trim();
  const body = String(
    mail?.fullBody ||
    mail?.bodyPreview ||
    ""
  ).toLowerCase().trim();

  debugRuleMail(mail, rules, {
    fromEmail,
    fromName,
    subject,
    body
  });

  for (const rule of rules) {

    let match = false;
    const value = String(rule?.conditionValue || "").toLowerCase().trim();

    let emailMatch = false;
    let nameMatch = false;
    let subjectMatch = false;
    let bodyMatch = false;

    if (rule.conditionType === "senderContains") {
      emailMatch = fromEmail.includes(value);
      nameMatch = fromName.includes(value);
      match = emailMatch || nameMatch;
    }

    if (rule.conditionType === "subjectContains") {
      subjectMatch = subject.includes(value);
      match = subjectMatch;
    }

    if (rule.conditionType === "bodyContains") {
      bodyMatch = body.includes(value);
      match = bodyMatch;
    }

    debugRuleCheck(rule, {
      value,
      fromEmail,
      fromName,
      subject,
      body,
      emailMatch,
      nameMatch,
      subjectMatch,
      bodyMatch,
      match
    });

    if (match) {

      if (rule.delete) actions.delete = true;
      if (rule.read) actions.read = true;
      if (rule.folder) actions.moveTo = rule.folder;

      break;
    }
  }

  return actions;
}

/* ======================
RESET FORM
====================== */
export function newRule() {
  document.getElementById("editingRuleId").value = "";
  document.getElementById("ruleName").value = "";
  document.getElementById("conditionValue").value = "";
  document.getElementById("ruleDelete").checked = false;
  document.getElementById("ruleRead").checked = false;
  document.getElementById("ruleFolder").value = "";
  document.getElementById("ruleEditorTitle").innerText = "Create rule";
}

/* ======================
SELECT RULE (EDIT)
====================== */
export function selectRule(rule) {

  document.getElementById("editingRuleId").value = rule.id;
  document.getElementById("ruleName").value = rule.name;

  document.getElementById("conditionType").value = rule.condition_type;
  document.getElementById("conditionValue").value = rule.condition_value;

  document.getElementById("ruleDelete").checked = !!rule.action_delete;
  document.getElementById("ruleRead").checked = !!rule.action_read;
  document.getElementById("ruleFolder").value = rule.action_folder ?? "";

  document.getElementById("ruleEditorTitle").innerText = "Edit rule";
}