import { state } from "../core/state";

/* ======================
OPEN / CLOSE SETTINGS
====================== */
export function openSettings() {
  const el = document.getElementById("settingsOverlay");
  if (!el) return;

  el.style.display = "flex";

  loadRules();          // render UI
  loadRulesToState();   // load engine
}

export function closeSettings() {
  const el = document.getElementById("settingsOverlay");
  if (!el) return;

  el.style.display = "none";
}

/* ======================
LOAD RULES (HTML VIEW)
====================== */
export async function loadRules() {
  const el = document.getElementById("settingsContent");
  if (!el) return;

  el.innerHTML = "Loading...";

  try {
    const res = await fetch("/settings/rules");
    const html = await res.text();
    el.innerHTML = html;
  } catch (e) {
    el.innerHTML = "Failed to load rules";
    console.error(e);
  }
}

/* ======================
LOAD RULES TO STATE (ENGINE)
====================== */
export async function loadRulesToState() {
  try {
    const res = await fetch("/rules/json");
    const data = await res.json();

    state.rules = (data.rules || []).map(r => ({
      id: r.id,
      conditionType: r.condition_type,
      conditionValue: r.condition_value,
      delete: !!r.action_delete,
      read: !!r.action_read,
      folder: r.action_folder
    }));

    console.log("RULES LOADED:", state.rules);

  } catch (e) {
    console.error("Failed load rules", e);
    state.rules = [];
  }
}

/* ======================
CREATE RULE
====================== */
export async function createRule() {

  const id = document.getElementById("editingRuleId").value;

  const payload = {
    displayName: document.getElementById("ruleName").value,
    conditionType: document.getElementById("conditionType").value,
    conditionValue: document.getElementById("conditionValue").value,
    delete: document.getElementById("ruleDelete").checked,
    read: document.getElementById("ruleRead").checked,
    folder: document.getElementById("ruleFolder").value
  };

  const url = id
    ? `/settings/rules/${id}`   // UPDATE
    : `/settings/rules`;        // CREATE

  const method = id ? "PUT" : "POST";

  await fetch(url, {
    method,
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute("content")
    },
    body: JSON.stringify(payload)
  });

  loadRules();
  loadRulesToState();
}
/* ======================
DELETE RULE
====================== */
export async function deleteRule(id) {
  if (!confirm("Delete this rule?")) return;

  try {
    await fetch(`/settings/rules/${id}`, {
      method: "DELETE",
      headers: {
        "X-CSRF-TOKEN": document
          .querySelector('meta[name="csrf-token"]')
          .getAttribute("content")
      }
    });

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

  if (!rules || rules.length === 0) return actions;

  for (const rule of rules) {

    let match = false;

    const from = (mail.from || "").toLowerCase();
    const subject = (mail.subject || "").toLowerCase();
    const body = (
      mail.fullBody ||
      mail.bodyPreview ||
      ""
    ).toLowerCase();

    const value = (rule.conditionValue || "").toLowerCase();

    if (rule.conditionType === "senderContains") {
      if (from.includes(value)) match = true;
    }

    if (rule.conditionType === "subjectContains") {
      if (subject.includes(value)) match = true;
    }

    if (rule.conditionType === "bodyContains") {
      if (body.includes(value)) match = true;
    }

    if (match) {
      if (rule.delete) actions.delete = true;
      if (rule.read) actions.read = true;
      if (rule.folder) actions.moveTo = rule.folder;
      break;
    }
  }

  return actions;
}

export function newRule() {
  document.getElementById("editingRuleId").value = "";

  document.getElementById("ruleName").value = "";
  document.getElementById("conditionValue").value = "";

  document.getElementById("ruleDelete").checked = false;
  document.getElementById("ruleRead").checked = false;
  document.getElementById("ruleFolder").value = "";

  document.getElementById("ruleEditorTitle").innerText = "Create rule";
}

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





