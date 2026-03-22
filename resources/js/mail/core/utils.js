export function escapeHtml(text) {
  if (!text) return "";

  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

export function highlight(text, query) {
  if (!query) return text;

  const words = query.split(/\s+/).filter(Boolean);

  let result = text;

  words.forEach(word => {
    const regex = new RegExp(`(${escapeRegex(word)})`, "gi");
    result = result.replace(regex, `<mark>$1</mark>`);
  });

  return result;
}

function escapeRegex(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export function formatMailDate(dateStr) {
  if (!dateStr) return "";

  const date = new Date(dateStr);
  const now = new Date();
  const diffDays = Math.floor((now - date) / 86400000);

  if (diffDays === 0) {
    return date.toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  if (diffDays === 1) return "Yesterday";

  if (diffDays < 7) {
    return date.toLocaleDateString([], { weekday: "short" });
  }

  if (date.getFullYear() === now.getFullYear()) {
    return date.toLocaleDateString([], {
      month: "short",
      day: "numeric",
    });
  }

  return String(date.getFullYear());
}

export function debounce(fn, delay = 300) {
  let timer = null;

  return function debounced(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

export function getCsrfToken() {
  return document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content") || "";
}