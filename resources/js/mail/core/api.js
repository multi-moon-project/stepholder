export async function safeFetch(url, options = {}) {
  try {
    const response = await fetch(url, options);
    if (!response.ok) {
      throw new Error(`Request failed: ${response.status} ${response.statusText}`);
    }
    return response;
  } catch (error) {
    console.error("Fetch error:", url, error);
    return null;
  }
}

export async function safeJson(url, options = {}) {
  const res = await safeFetch(url, options);
  if (!res) return null;

  try {
    return await res.json();
  } catch (error) {
    console.error("JSON parse error:", url, error);
    return null;
  }
}

export async function safeText(url, options = {}) {
  const res = await safeFetch(url, options);
  if (!res) return null;

  try {
    return await res.text();
  } catch (error) {
    console.error("Text parse error:", url, error);
    return null;
  }
}