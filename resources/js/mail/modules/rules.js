import { state } from "../core/state";

/* ======================
HELPER: EXTRACT EMAIL
====================== */
function extractEmail(raw) {
  if (!raw) return "";

  // Microsoft Graph object
  if (typeof raw === "object") {
    const email = raw.emailAddress?.address || raw.address || "";
    return String(email).toLowerCase().trim();
  }

  // String format
  if (typeof raw === "string") {
    const str = raw.toLowerCase().trim();

    // Ambil email di dalam <>
    const bracketMatch = str.match(/<\s*([^>]+?)\s*>/);
    if (bracketMatch && bracketMatch[1]) {
      return bracketMatch[1].trim();
    }

    // Fallback: ambil pola email di mana pun
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

  // Microsoft Graph object
  if (typeof raw === "object") {
    const name = raw.emailAddress?.name || raw.name || "";
    return String(name).toLowerCase().trim();
  }

  // String format
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
HELPER: DEBUG LOGGER
====================== */
function debugRuleMail(mail, rules, normalized) {
  console.group("RULE ENGINE DEBUG");
  console.log("MAIL RAW:", mail);
  console.log("MAIL FROM RAW:", mail?.from);
  console.log("MAIL FROM TYPE:", typeof mail?.from);
  console.log("NORMALIZED FROM EMAIL:", normalized.fromEmail);
  console.log("NORMALIZED FROM NAME:", normalized.fromName);
  console.log("NORMALIZED SUBJECT:", normalized.subject);
  console.log("NORMALIZED BODY PREVIEW:", normalized.body.slice(0, 200));
  console.log("RULES:", rules);
  console.groupEnd();
}

function debugRuleCheck(rule, debugData) {
  console.group(`RULE CHECK [${rule.id ?? "no-id"}]`);
  console.log("RULE RAW:", rule);
  console.log("CONDITION TYPE:", rule.conditionType);
  console.log("CONDITION VALUE RAW:", rule.conditionValue);
  console.log("CONDITION VALUE NORMALIZED:", debugData.value);
  console.log("FROM EMAIL:", debugData.fromEmail);
  console.log("FROM NAME:", debugData.fromName);
  console.log("SUBJECT:", debugData.subject);
  console.log("BODY SAMPLE:", debugData.body.slice(0, 200));
  console.log("EMAIL MATCH:", debugData.emailMatch);
  console.log("NAME MATCH:", debugData.nameMatch);
  console.log("SUBJECT MATCH:", debugData.subjectMatch);
  console.log("BODY MATCH:", debugData.bodyMatch);
  console.log("FINAL MATCH:", debugData.match);
  console.groupEnd();
}

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

    /* ===== SENDER ===== */
    if (rule.conditionType === "senderContains") {
      emailMatch = !!fromEmail && fromEmail.includes(value);
      nameMatch = !!fromName && fromName.includes(value);

      if (emailMatch || nameMatch) {
        match = true;
      }
    }

    /* ===== SUBJECT ===== */
    if (rule.conditionType === "subjectContains") {
      subjectMatch = !!subject && subject.includes(value);

      if (subjectMatch) {
        match = true;
      }
    }

    /* ===== BODY ===== */
    if (rule.conditionType === "bodyContains") {
      bodyMatch = !!body && body.includes(value);

      if (bodyMatch) {
        match = true;
      }
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

    /* ===== APPLY ACTION ===== */
    if (match) {
      console.warn("RULE MATCHED ✅", {
        rule,
        mail,
        actionsBefore: { ...actions }
      });

      if (rule.delete) actions.delete = true;
      if (rule.read) actions.read = true;
      if (rule.folder) actions.moveTo = rule.folder;

      console.warn("RULE ACTIONS RESULT ✅", actions);
      break;
    }
  }

  if (!actions.delete && !actions.read && !actions.moveTo) {
    console.warn("NO RULE MATCHED ❌", {
      mail,
      normalized: {
        fromEmail,
        fromName,
        subject,
        bodySample: body.slice(0, 200)
      },
      rules
    });
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